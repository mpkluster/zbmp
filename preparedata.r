
library(lubridate)

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






