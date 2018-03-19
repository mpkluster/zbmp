
# run
# source('analysis.r'); analyse_er()

# Powered by zbmp (aka zamejski bosanc mattia petroni)

# change point functions requirements
library(bcp)
library(cpm)
library(xlsx)
library(PASWR)


# # FEATURE SELECTION
# library(RWeka)
# library(nnet)
# library(e1071)
# library(randomForest)
# library(rpart)
# library(tree)
# library(party)
# library(MASS)
# library(mitools)
# library(boot)
# library(survey)
# library(relaimpo)
# library(scales)
# library(caret)
# library(strucchange)
# library(sandwich)
# library(zoo)
# library(modeltools)
# library(stats4)
# library(mvtnorm)
# library(Rserve)
# library(kernlab)
# library(class)
# library(lattice)
# library(mlbench)

## Caret based feature selection (ML)
# featureSelection <- function( dataFrame, form, method ) {
#
# 	tempData <- as.data.frame(dataFrame) # data.matrix(dataFrame, rownames.force = NA)
#
# 	set.seed( as.integer((as.double(Sys.time())*1000+Sys.getpid()) %% 2^31) )
# 	seeds <- vector(mode = "list", length = 91)
# 	for(i in 1:90) seeds[[i]] <- sample.int(1000, 27)
# 	seeds[[91]] <- sample.int(1000, 1)
#
# 	# feature selection training
#
# 	# perform with SVM
# 	control <- trainControl(method="repeatedcv", number=10, repeats=3, seeds=seeds)
#
# 	model <- train(form, data=tempData, method=method, trControl=control )  #, preProcess="scale"
# 	importance <- varImp(model, scale=FALSE)
# 	# print("****************************")
# 	# print("SVM based feature selection")
# 	# print(" ");
# 	a1 <- importance$importance[,1]
#
# 	return( a1 )
#
# }

changePoint_detection_CPM <- function( signal, cpmType) {
	
	resMW <- processStream(signal, cpmType=cpmType, ARL0=500 )
	breaks <- diff(c(0,resMW$changePoints,length(signal)))
	trendGroup <- unlist(sapply(c(1:length(breaks)), function(i){
		rep(i,breaks[i])
	}))

	segmentMeanValues <- rep(tapply(signal, trendGroup, mean), breaks)
	detectionTimeValues <- rep(0, length(signal))
	detectionTimeValues[resMW$detectionTimes] <- signal[resMW$detectionTimes]

	return(list(detectionTimeValues, segmentMeanValues, resMW$changePoints))
}

changePoint_detection_BCP <- function( signal, threshold ) {

	bcp <- bcp(signal, p0 = 0.2, w0 = threshold)
	changePoints <- which(bcp$posterior.prob >= threshold)

	breaks <- diff(c(0,changePoints,length(signal)))
	trendGroup <- unlist(sapply(c(1:length(breaks)), function(i){
		rep(i,breaks[i])
	}))

	segmentMeanValues <- rep(tapply(signal, trendGroup, mean), breaks)

	return( list(bcp, changePoints, segmentMeanValues ) )

}


multivariateBCP <- function( data, threshold ) {
	groups = colnames(data[,2:length(data)])

	bcp <- bcp(as.matrix(data), p0 = 0.2, w0 = threshold)
	changePoints <- which(bcp$posterior.prob >= threshold)

	return( list(changePoints, bcp) )
}


## function for offline/online univariate/multivariate changepoint detection in time series data
analyse_er <- function( past_data_file = "past_data.csv", new_data_file = "new_data.csv", write_excel=FALSE ) {
	
	# bcp requires Markov Chain implementation, ergo a good random seed should be set in front
	set.seed( as.integer((as.double(Sys.time())*1000+Sys.getpid()) %% 2^31) )

	# main
	#tabela<-read.csv(file="data.csv", header=TRUE)

	# join past and new data
	new <- read.csv(file=new_data_file, header=TRUE)
	past <- read.csv(file=past_data_file, header=TRUE)
	tabela <- rbind(past, new)

	# compute engagement rates on each time frame
	y <- tabela[,'Interactions']/tabela[,'Adjusted']  #engagement rates

	# normalize the data
	tabela_norm <- tabela
	tabela_normInt <- tabela
	tabela_norm[,-(1:6)] <- tabela[,-(1:6)]/tabela[,'Adjusted']
	tabela_normInt[,-(1:6)] <- tabela[,-(1:6)]/tabela[,'Interactions']



	## ENGAGEMENT RATE ANALYSIS

	# threshold for discarding non important changes, ie if the posterior probability of the
	# mean change occurred at particular time is not at least 30%, then we can assume that the
	# change is due to randomness and hence it is not statistically significant
	threshold <- 0.3

	## cpm
	list_cpm_res <- changePoint_detection_CPM(y, 'Mann-Whitney')

	detectionTimeValues = list_cpm_res[[1]]
	# print( detectionTimeValues )
	segmentMeanValues = list_cpm_res[[2]]
	# print(segmentMeanValues)
	# plot( segmentMeanValues )
	changePoints = list_cpm_res[[3]]
	# print( changePoints )


	## bcp
	list_bcp_res <- changePoint_detection_BCP(y, threshold )

	bcp <- list_bcp_res[[1]]
	# print( bcp$posterior.mean )
	changePoints <- list_bcp_res[[2]]
	# print(changePoints)
	segmentMeanValues <- list_bcp_res[[3]]
	# print(segmentMeanValues)
	# plot(bcp, xaxlab = tabela[,1], main="Engagement Rate - change point detection")



	## data fusion
	data <- data.frame( cbind(y, tabela_norm[,-(1:6)]) )


	# perform the multivariate BCP on the data
	list_bcp <- multivariateBCP( data, threshold )
	changePoints <- list_bcp[[1]]
	bcp <- list_bcp[[2]]



	# check the statistical significance of the changes
	breaks <- diff(c(0,changePoints,length(y)))
	trendGroup <- unlist(sapply(c(1:length(breaks)), function(i){
		rep(i,breaks[i])
	}))

	segmentMeanValues <- rep(tapply(y, trendGroup, mean), breaks)
	means <- as.matrix(segmentMeanValues)
	## check if the change in the posterior mean is statistically significant
	from = 1;
	p_value <- matrix( 0, 1, length(changePoints) )

	for ( i in 1:length(changePoints) ) {
		point = changePoints[i]
		nextPoint = length( tabela[,'Interactions'] )
		if ( (i+1) <= length(changePoints) ) {
			nextPoint <- changePoints[i+1]
		}
		p1 <- means[point,1]
		p2 <- means[point+1,1]
		
		n1 <- point - from + 1  #sum(tabela[from:point,'Requested'])
		n2 <- nextPoint - point #sum(tabela[(point+1):nextPoint,'Requested'])
		pi1 <- sum(data[from:point,'y'])/n1
		pi2 <- sum(data[(point+1):nextPoint,'y'])/n2
		s1 <- sd(data[from:point,'y'])
		s2 <- sd(data[(point+1):nextPoint,'y'])
		d1 <- (s1^2)/n1
		d2 <- (s2^2)/n2
 		df <- floor(((d1+d2)^2)/(((d1^2)/(n1-1))+((d2^2)/(n2-1))))

		t <- (p1-p2)/sqrt(d1+d2)

		## Student test
		p_value[i] <- 2*pt( t, df ) 

		from <- point+1
	}

	groups = colnames(data[,1:length(data)])

	# plotting all the results combined in one graph
	default_par <- par(no.readonly=TRUE)
	plot(bcp, separated=TRUE, main=paste("Multivariate ( k =", length(data)-1, ") Engagement Rate - change point detection"), outer.margins=list(left = unit(4,"lines"), bottom = unit(3, "lines"), right=unit(2, "lines"), top=unit(2,"lines")), lower.area=unit(0.33, "npc"), size.points=unit(0.25,"char"), pch.points=20, colors = NULL, xlab = NULL, xaxlab = tabela[,'Time'], cex.axes = list(cex.xaxis = 0.75, cex.yaxis.lower = 0.75,cex.yaxis.upper.default = 0.75, cex.yaxis.upper.separated = 0.5))

	# Plotting posterior mean for each attribute
	# (cannot print all in one page - divide in two subgroups)
	par(pch=22) # plotting symbol and color 
	par(mfrow=c(4,4)) # all plots on one page 
	for(i in 2:17){
		plot.default(data[,i], main=groups[i], type='p', pch=16, cex=1, xlab="", ylab="", xaxt = "n", yaxt = "n", cex.lab=1.5 ) 
		points(tabela[,'Time'], bcp$posterior.mean[,i], col="red", type='p',pch = 16, cex=0.5, xaxt = "n")
		lines(tabela[,'Time'], bcp$posterior.mean[,i], col='red', lwd = 5, xaxt = "n")
		axis(1, at=1:length(tabela[,'Time']), labels=tabela[,'Time'], cex.axis=1.5 )
		axis(2, cex.axis=1.5 )
	}
	par(pch=22) # plotting symbol and color 
	par(mfrow=c(4,4)) # all plots on one page 
	for(i in 18:29){ # 2:17
		plot.default(data[,i], main=groups[i], type='p', pch=16, cex=1, xlab="", ylab="", xaxt = "n", yaxt = "n", cex.lab=1.5 ) 
		points(tabela[,'Time'], bcp$posterior.mean[,i], col="red", type='p',pch = 16, cex=0.5, xaxt = "n")
		lines(tabela[,'Time'], bcp$posterior.mean[,i], col='red', lwd = 5, xaxt = "n")
		axis(1, at=1:length(tabela[,'Time']), labels=tabela[,'Time'], cex.axis=1.5 )
		axis(2, cex.axis=1.5 )
	}

	## prepare data for storing into excel
	groups = colnames(data[,2:length(data)])

	# get the importance values and set the columns
	means <- bcp$posterior.mean;
	means <- means[,-1] # strip the first column (=er)
	colnames( means ) <- groups

	# separate sdks and objects
	sdks <- means[,1:7]
	objects <- means[,-(1:7)]

	# get the strings of the time in which the changes occurred
	column_names <- c()
	for ( i in 1:length(changePoints) ) {
		point <- changePoints[i]
		column_names <- c(column_names, toString( tabela[point,'Time'] ) )
	}

	# plot the importance
	par(pch=22) # plotting symbol and color
	par(mfrow=c(2,length(changePoints)))
	par(mar=c(7.5,6,4,1)+.1)
	for ( i in 1:length(changePoints) ) {
		point <- as.numeric(changePoints[i])
		# find change increment influence
		a <- sdks[point+1, which( (sdks[point+1,]-sdks[point,])>0 ) ]
		a <- sort( a, decreasing=TRUE )
		a <- as.matrix(a)
		xlabels <- row.names(a)
		
		barplot(a, ylim=c(0, +max(a)*1.3), beside=TRUE, main=paste("SDK attributes influence at ", column_names[i], "\np value = ", p_value[i]), ylab="", names.arg=xlabels, col="red", las=2, space=0.2 )
		title(ylab="Importance",mgp=c(4,1,0))
	}
	for ( i in 1:length(changePoints) ) {
		point <- as.numeric(changePoints[i])
		# find positive change influence
		a <- objects[point+1, which( (objects[point+1,]-sdks[point,])>0 ) ]
		a <- sort( a, decreasing=TRUE )
		a <- as.matrix(a)
		xlabels <- row.names(a)
		
		barplot(a, ylim=c(0, +max(a)*1.3), beside=TRUE, main=paste("objectClazz attributes influence at ", column_names[i], "\np value = ", p_value[i]), ylab="", names.arg=xlabels, col="red", las=2, space=0.1 )
		title(ylab="Importance",mgp=c(4,1,0))
	}
	par(default_par)

	data_to_write <- means

	if ( write_excel == TRUE ) {
		write.xlsx(data_to_write[,1:7], "Engagement_rate_analysis.xlsx", sheetName="SDK attributes", col.names=TRUE, row.names=FALSE, append=TRUE, showNA=TRUE)
		write.xlsx(data_to_write[,-(1:7)], "Engagement_rate_analysis.xlsx", sheetName="ObjectClazz attributes", col.names=TRUE, row.names=FALSE, append=TRUE, showNA=TRUE)
	}


	# perform the linear regression
	datafr <- as.data.frame( cbind(1:length(tabela[,1]), data[,'y']) )
	colnames(datafr) <- c('time', 'er')

	# build linear regression model on full data
	linearModel <- lm(er ~ time, data=datafr)

	trend <- ifelse( linearModel$coefficients[2] > 0, "positive", "negative" )
	regression_line <- linearModel$coefficients[1] + linearModel$coefficients[2]*datafr[,'time']
	# par(mfrow=c(2,2))
	# plot(linearMod)
	plot( datafr[,'time'], datafr[,'er'], xlab="Time",  ylab="", xaxt = "n", main=paste("Regression line\nTrend is", trend, "\nBeta coeff =", linearModel$coefficients[2]) )
	lines( datafr[,'time'], regression_line, col='red')
	axis(1, at=1:length(tabela[,'Time']), labels=tabela[,'Time'], cex.axis=1 )


	


	## Optional Machine Learning analysis of the attributes (simple feature 
	## selection with the caret package)

	# # sdks attributes
	# mean_diff<-as.matrix(list_bcp_res[[3]]) #as.matrix(bcp$posterior.mean);
	# colnames(mean_diff) <- "meandiff"
	# data <- dataSdks
	# #  "bayesglm" "svmLinear" "knn"
	# form <- as.formula('meandiff ~ .')

	# a1 <- featureSelection( data, form, "svmPoly" )
	# a2 <- featureSelection( data, form, "bayesglm" )
	# a3 <- featureSelection( data, form, "knn" )

	# a1 <- t(a1)
	# a1 <- (a1-mean(a1))/sd(a1)
	# a2 <- t(a2)
	# a2 <- (a2-mean(a2))/sd(a2)
	# a3 <- t(a3)
	# a3 <- (a3-mean(a3))/sd(a3)

	# m_sdks <- rbind( a1, a2, a3 )

	# groups = colnames(data[,1:length(data)-1])
	# colnames( m_sdks, do.NULL=TRUE )
	# colnames( m_sdks ) <- groups
	# View(m_sdks) 

	# barplot(m_sdks, ylim=c(-max(m_sdks), +max(m_sdks)), legend=c("SVM Poly","Bayesian GLM", "Random Forest"), beside=TRUE, main="Feature selection", ylab="Relative importance", names.arg=groups )

}





