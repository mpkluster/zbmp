# zbmp

Files of this repo
:---- analysis.r    (main code for data analysis)
:---- preparedata.r (data preparation for the R env)
:---- parser.php    (main json parser)


To run the data-insights test follow these general instructions:


Unix/Mac OS Terminal
----------------------------------------
Init the repo

1. git clone https://github.com/mpkluster/zbmp.git

2. cd data-insights

3. Get somehow the data "bdsEventsSample.gz" and put it here

Unpack

4. gzip -d bdsEventsSample.gz

5. mv bdsEventsSample bdsEventsSample.json

Correct the json format (framing the content between [] and add a comma at the end of each line)

6. sed '1s/^/[/;$!s/$/,/;$s/$/]/' bdsEventsSample.json > file.json

Run the data parser and wait till the end (<45s)

7. php parse.php

NOTE: the parser will require a lot od memory due to the big size of the json

--------------------------------------------
The parser will generate the following text files:

	delays.txt
	frequencies_fast.txt
	frequencies_fast_attributes_name_objects.txt
	frequencies_fast_attributes_name_sdk.txt
	frequencies_fast_attributes_objects.txt
	frequencies_fast_attributes_sdk.txt
	
	frequencies_slow.txt
	frequencies_slow_attributes_name_objects.txt
	frequencies_slow_attributes_name_sdk.txt
	frequencies_slow_attributes_objects.txt
	frequencies_slow_attributes_sdk.txt


"delays.txt"       contains the user time delay between the screen appearance of the ad and the first user interaction 

"frequencies_fast_..." contain the frequency count of each of the 28 different ad attribute (objectClazz or sdk) in every time frame of 15 min ranging from 10.00 am, April 16, 2015 to 08.00 pm, April 16, 2015  

"frequencies_slow_..." contain the frequency count of each of the 28 different ad attribute (objectClazz or sdk) in every time frame of 1 hr ranging from 10.00 am, April 16, 2015 to 08.00 pm, April 16, 2015  

To analyse the data you need to invoke RStudio.

NOTE: the analysis scripts plots and analyse only the data "frequencies_fast_..."




R Studio
----------------------------------------

Install the required libraries: 

library(lubridate)
library(bcp)
library(cpm)
library(xlsx)
library(PASWR)

Then in the R console do the following:

Set the working directory

1. setwd("***myfolder***/zbmp")

Run the preparation code in order to read the parsed data. The script will generate past_data.csv and new_data.csv. If these two files are not generated then rename the already existing ones (eg. 'mv _past_data.csv past_data.csv' )

2. source('preparedata.r')

Load the analyses and monitoring functions

3. source('analysis.r')

And at last have fun

4. analyse_er()




