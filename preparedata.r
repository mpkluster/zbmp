
library(lubridate)
library(graphics)

# read the main data 
data <- as.data.frame(read.table("frequencies_fast.txt", sep="\t", header=TRUE))
timecol <- matrix(0, length(data[,1]), 1)
data <- cbind( timecol, data )

for ( i in 1:length(data[,1]) ) {
	timeinsec <- data[i,'limits']
	td <- seconds_to_period(timeinsec)
	data[i,1] <- sprintf('%02d:%02d', td@hour, minute(td) ) #paste(td$hour,":",td$minute,sep="")
}

colnames(data) <- c("Time", "Requested", "Adjusted", "Interactions", "Epoch.time", "Engagement.rate")

# objects
obj_names <- as.matrix( read.table("frequencies_fast_attributes_name_objects.txt", sep="\n", header=FALSE) )
objects <- as.matrix(read.table("frequencies_fast_attributes_objects.txt", sep="\t", header=FALSE))
objects <- as.data.frame( t(objects) )
colnames(objects) <- obj_names

# and sdks
sdk_names <- as.matrix( read.table("frequencies_fast_attributes_name_sdk.txt", sep="\n", header=FALSE) )
sdks <- as.matrix(read.table("frequencies_fast_attributes_sdk.txt", sep="\t", header=FALSE))
sdks <- as.data.frame( t(sdks) )
colnames(sdks) <- sdk_names

## merge all together
data <- cbind( data, sdks, objects )

write.csv( data, file="data.csv", row.names=FALSE )


## simulate past and new data
past <- data[1:(length(data[,1])-1),]
new  <- data[-(1:(length(data[,1])-1)),]

write.csv( past, file="past_data.csv", row.names=FALSE )
write.csv( new, file="new_data.csv", row.names=FALSE )



# histogram
timestamps <- as.matrix( read.table("ad_timestamps.txt", sep="\n", header=FALSE) )

sampling <- 1000
times_name <- matrix(0, floor(length(timestamps[,1])/sampling), 1)
times      <- as.matrix(timestamps[seq(1,length(timestamps[,1]), by=sampling ),1])

j <- 1;
for ( i in seq(1,length(timestamps[,1]), by=sampling ) ) {
  timeinsec <- timestamps[i]
  td <- seconds_to_period(timeinsec)
  times_name[j] <-  sprintf('%02d:%02d', td@hour, minute(td) ) #paste(td$hour,":",td$minute,sep="")
  j<-j+1
}

sampling <- 30000
times_name1 <- matrix(0, floor(length(timestamps[,1])/sampling), 1)
times1      <- as.matrix(timestamps[seq(1,length(timestamps[,1]), by=sampling ),1])
j <- 1;
for ( i in seq(1,length(timestamps[,1]), by=sampling ) ) {
  timeinsec <- timestamps[i]
  td <- seconds_to_period(timeinsec)
  times_name1[j] <-  sprintf('%02d:%02d', td@hour, minute(td) ) #paste(td$hour,":",td$minute,sep="")
  j<-j+1
}

times_name <- cbind( as.matrix(times_name) )
times <- (times-min(times))/(max(times)-min(times))

times_name1 <- cbind( as.matrix(times_name1) )
times1 <- (times1-min(times1))/(max(times1)-min(times1))

# default_par <- par(no.readonly=TRUE)

barplot( times, beside=TRUE, yaxt="n", ylab="", xlab="",  )
axis(side=2, at=times1, labels=times_name1, las=1 )
title(xlab="Index (x1000)", mgp=c(1,1,0))

# par(default_par)



