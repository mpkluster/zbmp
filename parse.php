
<?php

	
	// %%  Place the entire json inside [] and add to each line end a comme ",": 
	// %%     
	// %%      sed '1s/^/[/;$!s/$/,/;$s/$/]/' bdsEventsSample.json > file.json
	// %%
	// %%  For increase performance during tests just use only the first 100 rows of the file: 
	// %%
	// %%      head -20000 file.json | tail -10000 > light.json
	// %%
	// %%

	ini_set('memory_limit', -1);

	// Function to calculate square of value - mean
	function sd_square($x, $mean) { return pow($x - $mean,2); }

	// Function to calculate standard deviation (uses sd_square)    
	function sd($array) {
	// square root of sum of squares devided by N-1
		return sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
	}

	function cmp($a, $b) {
		if ($a["timestamp"] == $b["timestamp"]) {
			return 0;
		}
		return ($a["timestamp"] < $b["timestamp"]) ? -1 : 1;
	}

	function findBin( $value, array $limits ) {
		$index = 0;
		while ( $value < $limits[$index] ) $index++;
		return $index;
	}
	// // Define the custom sort function
	// function custom_sort($a,$b) {
	// 	return $a['timestamp']>$b['sessionId'];
	// }

	$jsonfile = 'file.json';
	//$jsonfile = 'light.json';
	$jsonstring = file_get_contents($jsonfile);
	$jsondata = json_decode( $jsonstring, true );

	//usort($jsondata, "custom_sort");
     


	// var_dump( $jsondata );
	//var_dump( $jsondata[6] );
	// echo "\n";
	
	// foreach ( $jsondata as $k => $element ) {
	// 	echo $element["sessionId"];
	// 	echo "\n";
	// }

	$only_adRequested_sessions_timestamps = array();
    $only_pageRequested_sessions_sdks = array();
	$only_errors = array();
	$firstInteraction_timestamps = array();
	$interaction_timestamps = array();
	$screenShown_clientTimestamps = array();
	$interactionTimeDelays = array();

	$json_data_length = count( $jsondata );

	// create an associative array containing the timestamp of all the adRequested events
	foreach ( $jsondata as $k => $element ) {
		if ( strcmp($element["name"], "adRequested") == 0 ) {
			// ensure that one adRequest cannot exist twice with the same sessionId
			if ( ! array_key_exists($element["sessionId"], $only_adRequested_sessions_timestamps) ) 
				$only_adRequested_sessions_timestamps[ $element["sessionId"] ] = $element["timestamp"];
			else {
				echo "Conflict\n";
				exit(-1);
			}
		}

		if ( strcmp($element["name"], "userError") == 0 ) {
            $only_errors[$element["sessionId"]]['server'] = $element["timestamp"];
            $only_errors[$element["sessionId"]]['client'] = array_key_exists("clientTimestamp", $element) ? $element["clientTimestamp"] : null;
        }
	}

	if ( count($only_adRequested_sessions_timestamps) < 1 ) {
		echo "No adRequests to analyse. Exit.\n";
		exit(-1);
	}

    // sort the associative array according to the timestamps
	asort( $only_adRequested_sessions_timestamps );
	// print_r( array_keys($only_adRequested_sessions_timestamps, max($only_adRequested_sessions_timestamps)) );

	$only_adRequested_length = count( $only_adRequested_sessions_timestamps );
	$only_errors_length = count( $only_errors );
	
	$counter = 0;

	$engagementEvents = array();//array_fill(0, $only_adRequested_length, 0);
	$interaction_timestamps_indexes = array();
	$interaction_array = array();
	$interaction_counter = 0;
	$firstInteraction_array = array();

    // attributes
    $sdks = array();
    $objects = array();
    $sdks_objects = array();


	$first_interactions = 0;

    // strip all sessions that do not have an adRequested event
    foreach ( $jsondata as $k => $element ) {
        // beware that not all entries have the sessionId attribute
        if ( array_key_exists("sessionId", $element) ) {
            // in this session the ad was not requested, ergo should be discarded
            if ( ! array_key_exists($element["sessionId"], $only_adRequested_sessions_timestamps) ) {
                unset( $jsondata[$counter] );
            }
        }
        else unset( $jsondata[$counter] );

        $counter ++;
    }

    foreach ( $jsondata as $k => $element ) {
        if ((strcmp($element["name"], "pageRequested") == 0) && array_key_exists("sdk", $element)) {
            if (!array_key_exists($element["sessionId"], $only_pageRequested_sessions_sdks)) {
                $only_pageRequested_sessions_sdks[$element["sessionId"]] = $element["sdk"];
            }
        }
    }
	

	foreach ( $jsondata as $k => $element ) {
		// beware that not all entries have the sessionId attribute
		if ( array_key_exists($element["sessionId"], $only_adRequested_sessions_timestamps) ) {

			// first interactions
			if ( strcmp($element["name"], "firstInteraction") == 0 ) {
				// ensure this event is unique
				if ( array_key_exists($element["sessionId"], $firstInteraction_timestamps) ) {
					// echo "MESS! double firstInteraction event present for the session: " . $element["sessionId"] . " at the time: " . $element["timestamp"] . "\n";
					// if ( array_key_exists($element["sessionId"], $only_errors) ) {
					// 	echo "However, there is an error present at the time: " . $only_errors[$element["timestamp"]]['server'] . "\n";
					// }

					$first_interactions ++;
				}
				else {
					$firstInteraction_timestamps[ $element["sessionId"] ]["client"] = array_key_exists("clientTimestamp", $element) ? $element["clientTimestamp"] : null;
					$firstInteraction_timestamps[ $element["sessionId"] ]['server'] = $element["timestamp"];
					$firstInteraction_array[ $element["sessionId"] ] = $element["timestamp"];
					
					if (array_key_exists($element["sessionId"], $engagementEvents)) {
						$engagementEvents[$element["sessionId"]]++;
					} else {
						$engagementEvents[$element["sessionId"]] = 1;
					}

					// now fill up the attributes
					$sdk = $only_pageRequested_sessions_sdks[$element["sessionId"]];
					if (!array_key_exists($sdk, $sdks)) $sdks[$sdk] = array();
					array_push($sdks[$sdk], array("sessionId" => $element["sessionId"], "timestamp" => $element["timestamp"]));

					if (array_key_exists("objectClazz", $element)) {
						$objectClazz = $element["objectClazz"];
						if ( $objectClazz == null ) $objectClazz = "null";

						if ( ! array_key_exists($objectClazz, $objects) ) $objects[$objectClazz] = array();
							array_push($objects[$objectClazz], array("sessionId" => $element["sessionId"], "timestamp" => $element["timestamp"]));
					}
				}
			}

			// same for interactions
			if ( strcmp($element["name"], "interaction") == 0 ) {
				if ( array_key_exists($element["sessionId"], $interaction_timestamps_indexes ) ) {
					$interaction_timestamps_indexes[ $element["sessionId"] ] ++;
				}
				else {
					$interaction_timestamps_indexes[ $element["sessionId"] ] = 1;
				}

				$interaction_timestamps[ $element["sessionId"] ]["client"][ $interaction_timestamps_indexes[ $element["sessionId"] ]-1 ] = array_key_exists("clientTimestamp", $element) ? $element["clientTimestamp"] : null;
				$interaction_timestamps[ $element["sessionId"] ]['server'][ $interaction_timestamps_indexes[ $element["sessionId"] ]-1 ] = $element["timestamp"];
				$interaction_array[$interaction_counter] = array("sessionId"=>$element["sessionId"], "timestamp"=>$element["timestamp"]);
				$interaction_counter++;

				if (array_key_exists($element["sessionId"], $engagementEvents)) {
					$engagementEvents[$element["sessionId"]]++;
				} else {
					$engagementEvents[$element["sessionId"]] = 1;
				}

				// now fill up the attributes
				$sdk = $only_pageRequested_sessions_sdks[$element["sessionId"]];
				if (!array_key_exists($sdk, $sdks)) $sdks[$sdk] = array();
				array_push($sdks[$sdk], array("sessionId" => $element["sessionId"], "timestamp" => $element["timestamp"]));

				if (array_key_exists("objectClazz", $element)) {
					$objectClazz = $element["objectClazz"];
					if ( $objectClazz == null ) $objectClazz = "null";

					if ( ! array_key_exists($objectClazz, $objects) ) $objects[$objectClazz] = array();
						array_push($objects[$objectClazz], array("sessionId" => $element["sessionId"], "timestamp" => $element["timestamp"]));
				}
			}

			// // This is not ok, because we have multiple firstInteractions in the json with the same id
			// if ( (strcmp($element["name"], "firstInteraction") == 0) || (strcmp($element["name"], "interaction") == 0) ) {
				
			// }

			if ( strcmp($element["name"], "screenShown") == 0 ) {
			$screenShown_clientTimestamps[ $element["sessionId"] ]['client'] = array_key_exists("clientTimestamp", $element) ? $element["clientTimestamp"] : null;
			$screenShown_clientTimestamps[ $element["sessionId"] ]['server'] = $element["timestamp"];
			}

		}
	}

	if ( count($interaction_array) != $interaction_counter ) { echo "HUGE error\n"; exit(-1); }

	asort($firstInteraction_array);
	usort($interaction_array, "cmp");
//    usort($sdks, "cmp");
//    usort($objects, "cmp");


    foreach ( $sdks as $key => $element ) {
        $array_to_sort = $element;
        usort( $array_to_sort, "cmp" );
        $sdks[$key] = $array_to_sort;
    }


    foreach ( $objects as $key => $element ) {
        $array_to_sort = $element;
        usort( $array_to_sort, "cmp" );
        $objects[$key] = $array_to_sort;
    }


//    $check_timestamps = fopen("check_timestamps", "w");
//    foreach ( $interaction_array as $key => $element ) {
//        fwrite($check_timestamps, $element["timestamp"] . "\n");
//    }
//    fclose($check_timestamps);


//    echo "\npageRequested: " . count($only_pageRequested_sessions_sdks) . "\n";
//    echo "adRequested: " . count($only_adRequested_sessions_timestamps) . "\n";
//
//    // print_r($sdks);
//    // print_r($objects);
//
//    print_r( array_keys( $sdks ) );
//    print_r( array_keys( $objects ) );


	// calculate the engagement rate
	$summa = 0;
	foreach ( $engagementEvents as $k => $element ) {
		$summa = $summa + $element;
	}
	$engagement_rate = floatval($summa)/floatval(count($only_adRequested_sessions_timestamps));

	echo $summa . " = " . array_sum($engagementEvents) . " = " . count($firstInteraction_array) . " + " . array_sum($interaction_timestamps_indexes) . "\n\n";
	
	// the number of repeated firstInteractions;
	echo $first_interactions . "\n";

	
	// interaction time delay estimation
	$file = fopen("delays.txt", "w");
	$summa = 0; $count = 0;
	foreach ( $firstInteraction_timestamps as $k => $element ) {
		if ( array_key_exists($k, $screenShown_clientTimestamps) ) {
			if ( $screenShown_clientTimestamps[$k]["client"] != null ) {
				if ( (! array_key_exists($k, $only_errors)) || (array_key_exists($k, $only_errors) && ($only_errors[$k]['server'] > $element["server"])) ) {
					if ( $element["client"] >= $screenShown_clientTimestamps[$k]["client"] ) {
						$interactionTimeDelays[$k] = $element["client"] - $screenShown_clientTimestamps[$k]["client"];
						$summa += $interactionTimeDelays[$k];
						$count++;
						fwrite($file, $interactionTimeDelays[$k]."\n");
					}
				}
			}
			// else
			// 	$interactionTimeDelays[$k] = $element["server"] - $screenShown_clientTimestamps[$k]["server"];

			// $summa += $interactionTimeDelays[$k];
			// $count++;
		}
	}
	fclose($file);

	$average_delay = 0;
	if ( $count > 0 ) $average_delay = $summa/$count;
	




	echo "\n\n************************************************************************\n";
	echo "************************************************************************\n";
	echo "************************************************************************\n\n\n";

	echo "Computing frequencies (bin size = 1hr)\n\n";
	// histogram
	// divide the sorted array into 30' or 60' long bins
	//$bin_size = 60;	 // 1 min
	//$bin_size = 300;	 // 5 min
	//$bin_size = 450;   // 7.5 min
	//$bin_size = 900;   // 15 min
	//$bin_size = 1800;  // 30 min
	$bin_size = 3600; // 1hr

	// we can do this because the array is sorted
	$adRequested_effective_start_time = reset( $only_adRequested_sessions_timestamps );
	$adRequested_effective_end_time = end( $only_adRequested_sessions_timestamps );

	// epoch start and end time needed for generating bin limiters
	$start_time = 1429178400;
	$end_time   = 1429214400;
	$number_of_bins = ceil(($end_time - $start_time)/$bin_size);

	$frequencies = array_fill(0, $number_of_bins, 0);
	$frequencies_engagements = array_fill(0, $number_of_bins, 0);
	$sample_average_engagements = array_fill(0, $number_of_bins, 0);
	$freq_index = 0;
	$limits = array_fill(0, $number_of_bins, 0);

    $sdk_attributes_frequencies = array();
    $objects_attributes_frequencies = array();

    foreach ( array_keys($sdks) as $key => $element )
        $sdk_attributes_frequencies[ $element ] = array_fill(0, $number_of_bins, 0);

    foreach ( array_keys($objects) as $key => $element )
        $objects_attributes_frequencies[ $element ] = array_fill(0, $number_of_bins, 0);

	$limit = $start_time + $bin_size;
	$limits[$freq_index] = $limit;



	foreach ( $only_adRequested_sessions_timestamps as $k => $timestamp ) {
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
			$limits[ $freq_index ] = $limit;
		}
		$frequencies[ $freq_index ] ++;
	}

	$clean_frequencies = array_merge($frequencies);
	
	$freq_index = 0;
	$limit = $start_time + $bin_size;

	foreach ( $firstInteraction_array as $k => $timestamp ) {
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
		}
		$frequencies_engagements[ $freq_index ] ++;
		// if the ad was requested in a previous time frame, then increase the frequencies of adrequest for the 
		// current time frame (that ad is still alive ergo it must be counted during computation of the engagement rate)
		if ( $only_adRequested_sessions_timestamps[$k] < $limit-$bin_size ) $frequencies[ $freq_index ] ++;
	}

	$freq_index = 0;
	$limit = $start_time + $bin_size;

	for ( $i=0; $i<count($interaction_array); $i++ ) {
		$timestamp = $interaction_array[$i]["timestamp"];
		$k = $interaction_array[$i]["sessionId"];
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
		}
		$frequencies_engagements[ $freq_index ] ++;
		// if the ad was requested in a previous time frame, then increase the frequencies of adrequest for the 
		// current time frame (that ad is still alive ergo it must be counted during computation of the engagement rate)
		if ( $only_adRequested_sessions_timestamps[$k] < $limit-$bin_size ) $frequencies[ $freq_index ] ++;
	}


    foreach ( $sdks as $key => $element ) {
        $freq_index = 0;
        $limit = $start_time + $bin_size;

        for ( $i=0; $i<count($element); $i++ ) {
            $timestamp = $element[$i]["timestamp"];
            if ( $timestamp >= $limit ) {
                $freq_index ++;
                $limit = $limit + $bin_size;
            }
            $sdk_attributes_frequencies[$key][ $freq_index ] ++;
        }
    }


    foreach ( $objects as $key => $element ) {
        $freq_index = 0;
        $limit = $start_time + $bin_size;

        for ( $i=0; $i<count($element); $i++ ) {
            $timestamp = $element[$i]["timestamp"];
            if ( $timestamp >= $limit ) {
                $freq_index ++;
                $limit = $limit + $bin_size;
            }
            $objects_attributes_frequencies[$key][ $freq_index ] ++;
        }
    }

//echo "\n\nFrequencies\n";
//    print_r($frequencies);
//echo "\n\nFrequencies engagements\n";
//    print_r($frequencies_engagements);
//echo "\n\nSdks\n";
//    print_r($sdk_attributes_frequencies);
//echo "\n\nObject class\n";
//    print_r($objects_attributes_frequencies);

    echo "\n\n\n";


	$file_slow_freqs = fopen("frequencies_slow.txt", "w");
	fwrite($file_slow_freqs, "cleanfreq\tfrequencies\tengagements\tlimits\ter\n");
	for ( $i=0; $i<count($frequencies); $i++ )
		fwrite($file_slow_freqs, $clean_frequencies[$i] . "\t" . $frequencies[$i] . "\t" .
            $frequencies_engagements[$i] . "\t" . $limits[$i] . "\t" . $frequencies_engagements[$i]/$frequencies[$i] . "\n");
	fclose($file_slow_freqs);


    $file_slow_freqs = fopen("frequencies_slow_attributes_name_sdk.txt", "w");
    foreach ( array_keys($sdk_attributes_frequencies) as $key => $el ) fwrite($file_slow_freqs, $el . "\n");
    fclose($file_slow_freqs);
    $file_slow_freqs = fopen("frequencies_slow_attributes_sdk.txt", "w");
    foreach ( $sdk_attributes_frequencies as $key => $array_element) {
    	fwrite($file_slow_freqs, $array_element[0]);
        for ( $i=1; $i<count($array_element); $i++ ) {
            fwrite($file_slow_freqs, "\t" . $array_element[$i]);
        }
        fwrite($file_slow_freqs, "\n");
    }
    fclose($file_slow_freqs);


    $file_slow_freqs = fopen("frequencies_slow_attributes_name_objects.txt", "w");
    foreach ( array_keys($objects_attributes_frequencies) as $key => $el ) fwrite($file_slow_freqs, $el . "\n");
    fclose($file_slow_freqs);
    $file_slow_freqs = fopen("frequencies_slow_attributes_objects.txt", "w");
    foreach ( $objects_attributes_frequencies as $key => $array_element) {
        fwrite($file_slow_freqs, $array_element[0]);
        for ( $i=1; $i<count($array_element); $i++ ) {
            fwrite($file_slow_freqs, "\t" . $array_element[$i]);
        }
        fwrite($file_slow_freqs, "\n");
    }
    fclose($file_slow_freqs);


//    fwrite($file_slow_freqs, "cleanfreq\tfrequencies\tengagements\tlimits\ter\n");
//    for ( $i=0; $i<count($frequencies); $i++ )
//    fwrite($file_slow_freqs, $clean_frequencies[$i] . "\t" . $frequencies[$i] . "\t" .
//        $frequencies_engagements[$i] . "\t" . $limits[$i] . "\t" . $frequencies_engagements[$i]/$frequencies[$i] . "\n");
//    fclose($file_slow_freqs);

	// $number_of_interactions_occurring_outside_the_time_frame = array_fill(0, $number_of_bins, 0);;
	// $number_of_all_interactions_inside_time_frames = array_fill(0, $number_of_bins, 0);


	// //foreach ( $only_adRequested_sessions_timestamps as $k => $timestamp ) {
	// $only_adRequested_sessions_timestamps_keys = array_keys($only_adRequested_sessions_timestamps);
	// $i = 0;
	// while ( $i < count($only_adRequested_sessions_timestamps) ) {
	// 	$k = $only_adRequested_sessions_timestamps_keys[$i];
	// 	$timestamp = $only_adRequested_sessions_timestamps[$k];

	// 	if ( $timestamp < $limit ) {
	// 		// now check if the interaction with the ad occurred inside the same time frame
	// 		// an interaction can be either "interaction" or "firstInteraction" 
	// 		// check firstInteraction first
	// 		if ( array_key_exists($k, $firstInteraction_timestamps) ) {
	// 			if ( $firstInteraction_timestamps[$k]['server'] < $limit ) { // the interaction occurred inside the same time frame
	// 				$frequencies_engagements[ $freq_index ]++;
	// 				$number_of_all_interactions_inside_time_frames[ $freq_index ]++;
	// 			}
	// 			else $number_of_interactions_occurring_outside_the_time_frame[ $freq_index ]++;
	// 		}
	// 		// now check interactions (this is more tricky because there can be more than one interaction per session)
	// 		if ( array_key_exists($k, $interaction_timestamps) ) {
	// 			for ( $j=0; $j<$interaction_timestamps_indexes[$k]; $j++ ) {
	// 				$int_timestamp = $interaction_timestamps[$k]['server'][$j];
	// 				if ( $int_timestamp < $limit ) { // the interaction occurred inside the same time frame
	// 					$frequencies_engagements[ $freq_index ]++;
	// 					$number_of_all_interactions_inside_time_frames[ $freq_index ]++;
	// 				}
	// 				else $number_of_interactions_occurring_outside_the_time_frame[ $freq_index ]++;
	// 			}
	// 		}
	// 		$frequencies[ $freq_index ] ++;
	// 		$i++;
	// 	}
	// 	else {
	// 		$freq_index ++;
	// 		$limit = $limit + $bin_size;
	// 		$limits[ $freq_index ] = $limit;
	// 	}
	// }

	// $file_slow_freqs = fopen("frequencies_slow.txt", "w");
	// fwrite($file_slow_freqs, "frequencies\tengagements\tin\tout\tlimits\n");
	// for ( $i=0; $i<count($frequencies); $i++ ) 
	// 	fwrite($file_slow_freqs, $frequencies[$i]."\t".$frequencies_engagements[$i]."\t".$number_of_all_interactions_inside_time_frames[$i]."\t".$number_of_interactions_occurring_outside_the_time_frame[$i]."\t".$limits[$i]."\n");
	// fclose($file_slow_freqs);

	// echo "\n\nSlow sampling (1hr interval). Number of bins: " . $number_of_bins . "\n\n";
	// print_r( $frequencies );
	// echo "\n\n";
	// print_r( $frequencies_engagements );
	// echo "\n\n";
	// print_r( $number_of_all_interactions_inside_time_frames );
	// echo "\n\n";
	// print_r( $number_of_interactions_occurring_outside_the_time_frame );
	// echo "\n\n";
	// print_r( $limits );


	echo "\n\n************************************************************************\n";
	echo "************************************************************************\n";
	echo "************************************************************************\n\n\n";

	echo "Computing frequencies (bin size = 15min)\n\n";

	// histogram
	// divide the sorted array into 30' or 60' long bins
	//$bin_size = 60;	 // 1 min
	//$bin_size = 300;	 // 5 min
	//$bin_size = 450;   // 7.5 min
	$bin_size = 900;     // 15 min
	//$bin_size = 1800;  // 30 min
	//$bin_size = 3600; // 1hr

	// we can do this because the array is sorted
	$adRequested_effective_start_time = reset( $only_adRequested_sessions_timestamps );
	$adRequested_effective_end_time = end( $only_adRequested_sessions_timestamps );

	// epoch start and end time needed for generating bin limiters
	$start_time = 1429178400;
	$end_time   = 1429214400;
	$number_of_bins = ceil(($end_time - $start_time)/$bin_size);

	$frequencies = array_fill(0, $number_of_bins, 0);
	$frequencies_engagements = array_fill(0, $number_of_bins, 0);
	$sample_average_engagements = array_fill(0, $number_of_bins, 0);
	$freq_index = 0;
	$limits = array_fill(0, $number_of_bins, 0);

    $sdk_attributes_frequencies = array();
    $objects_attributes_frequencies = array();

    foreach ( array_keys($sdks) as $key => $element )
        $sdk_attributes_frequencies[ $element ] = array_fill(0, $number_of_bins, 0);

    foreach ( array_keys($objects) as $key => $element )
        $objects_attributes_frequencies[ $element ] = array_fill(0, $number_of_bins, 0);

	$limit = $start_time + $bin_size;
	$limits[$freq_index] = $limit;

	//$number_of_interactions_occurring_outside_the_time_frame = array_fill(0, $number_of_bins, 0);;
	//$number_of_all_interactions_inside_time_frames = array_fill(0, $number_of_bins, 0);

	
	foreach ( $only_adRequested_sessions_timestamps as $k => $timestamp ) {
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
			$limits[ $freq_index ] = $limit;
		}
		$frequencies[ $freq_index ] ++;
	}

	$clean_frequencies = array_merge($frequencies);
	
	$freq_index = 0;
	$limit = $start_time + $bin_size;

	foreach ( $firstInteraction_array as $k => $timestamp ) {
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
		}
		$frequencies_engagements[ $freq_index ] ++;
		// if the ad was requested in a previous time frame, then increase the frequencies of adrequest for the 
		// current time frame (that ad is still alive ergo it must be counted during computation of the engagement rate)
		if ( $only_adRequested_sessions_timestamps[$k] < $limit-$bin_size ) $frequencies[ $freq_index ] ++;
	}

	$freq_index = 0;
	$limit = $start_time + $bin_size;

	for ( $i=0; $i<count($interaction_array); $i++ ) {
		$timestamp = $interaction_array[$i]["timestamp"];
		$k = $interaction_array[$i]["sessionId"];
		if ( $timestamp >= $limit ) {
			$freq_index ++;
			$limit = $limit + $bin_size;
		}
		$frequencies_engagements[ $freq_index ] ++;
		// if the ad was requested in a previous time frame, then increase the frequencies of adrequest for the 
		// current time frame (that ad is still alive ergo it must be counted during computation of the engagement rate)
		if ( $only_adRequested_sessions_timestamps[$k] < $limit-$bin_size ) $frequencies[ $freq_index ] ++;
	}


    foreach ( $sdks as $key => $element ) {
        $freq_index = 0;
        $limit = $start_time + $bin_size;

        for ( $i=0; $i<count($element); $i++ ) {
            $timestamp = $element[$i]["timestamp"];
            if ( $timestamp >= $limit ) {
                $freq_index ++;
                $limit = $limit + $bin_size;
            }
            $sdk_attributes_frequencies[$key][ $freq_index ] ++;
        }
    }


    foreach ( $objects as $key => $element ) {
        $freq_index = 0;
        $limit = $start_time + $bin_size;

        for ( $i=0; $i<count($element); $i++ ) {
            $timestamp = $element[$i]["timestamp"];
            if ( $timestamp >= $limit ) {
                $freq_index ++;
                $limit = $limit + $bin_size;
            }
            $objects_attributes_frequencies[$key][ $freq_index ] ++;
        }
    }

	// $only_adRequested_sessions_timestamps_keys = array_keys($only_adRequested_sessions_timestamps);
	// $i = 0;
	// while ( $i < count($only_adRequested_sessions_timestamps) ) {
	// 	$k = $only_adRequested_sessions_timestamps_keys[$i];
	// 	$timestamp = $only_adRequested_sessions_timestamps[$k];

	// 	if ( $timestamp < $limit ) {
	// 		// now check if the interaction with the ad occurred inside the same time frame
	// 		// an interaction can be either "interaction" or "firstInteraction" 
	// 		// check firstInteraction first
	// 		if ( array_key_exists($k, $firstInteraction_timestamps) ) {
	// 			if ( $firstInteraction_timestamps[$k]['server'] < $limit ) { // the interaction occurred inside the same time frame
	// 				$frequencies_engagements[ $freq_index ]++;
	// 				$number_of_all_interactions_inside_time_frames[ $freq_index ]++;
	// 			}
	// 			else $number_of_interactions_occurring_outside_the_time_frame[ $freq_index ]++;
	// 		}
	// 		// now check interactions (this is more tricky because there can be more than one interaction per session)
	// 		if ( array_key_exists($k, $interaction_timestamps) ) {
	// 			for ( $j=0; $j<$interaction_timestamps_indexes[$k]; $j++ ) {
	// 				$int_timestamp = $interaction_timestamps[$k]['server'][$j];
	// 				if ( $int_timestamp < $limit ) { // the interaction occurred inside the same time frame
	// 					$frequencies_engagements[ $freq_index ]++;
	// 					$number_of_all_interactions_inside_time_frames[ $freq_index ]++;
	// 				}
	// 				else $number_of_interactions_occurring_outside_the_time_frame[ $freq_index ]++;
	// 			}
	// 		}
	// 		$frequencies[ $freq_index ] ++;
	// 		$i++;
	// 	}
	// 	else {
	// 		$freq_index ++;
	// 		$limit = $limit + $bin_size;
	// 		$limits[ $freq_index ] = $limit;
	// 	}
	// }

	$file_fast_freqs = fopen("frequencies_fast.txt", "w");
	fwrite($file_fast_freqs, "cleanfreq\tfrequencies\tengagements\tlimits\ter\n");
	for ( $i=0; $i<count($frequencies); $i++ ) 
		fwrite($file_fast_freqs, $clean_frequencies[$i] . "\t" . $frequencies[$i] . "\t" .
            $frequencies_engagements[$i] . "\t" . $limits[$i] . "\t" . $frequencies_engagements[$i]/$frequencies[$i] . "\n");
	fclose($file_fast_freqs);


    $file_fast_freqs = fopen("frequencies_fast_attributes_name_sdk.txt", "w");
    foreach ( array_keys($sdk_attributes_frequencies) as $key => $element) fwrite($file_fast_freqs, $element . "\n");
    fclose($file_fast_freqs);
    $file_fast_freqs = fopen("frequencies_fast_attributes_sdk.txt", "w");
    foreach ( $sdk_attributes_frequencies as $key => $array_element) {
        fwrite($file_fast_freqs, $array_element[0]);
        for ( $i=1; $i<count($array_element); $i++ ) {
            fwrite($file_fast_freqs, "\t" . $array_element[$i]);
        }
        fwrite($file_fast_freqs, "\n");
    }
    fclose($file_fast_freqs);


    $file_fast_freqs = fopen("frequencies_fast_attributes_name_objects.txt", "w");
    foreach ( array_keys($objects_attributes_frequencies) as $key => $element) fwrite($file_fast_freqs, $element . "\n");
    fclose($file_fast_freqs);
    $file_fast_freqs = fopen("frequencies_fast_attributes_objects.txt", "w");
    foreach ( $objects_attributes_frequencies as $key => $array_element) {
        fwrite($file_fast_freqs, $array_element[0]);
        for ( $i=1; $i<count($array_element); $i++ ) {
            fwrite($file_fast_freqs, "\t" . $array_element[$i]);
        }
        fwrite($file_fast_freqs, "\n");
    }
    fclose($file_fast_freqs);

	// echo "\n\nQuick sampling (5min interval). Number of bins: " . $number_of_bins . "\n\n";
	// print_r( $frequencies );
	// echo "\n\n";
	// print_r( $frequencies_engagements );
	// echo "\n\n";
	// print_r( $limits );





	echo "************************************************************************\n";
	echo "************************************************************************\n";
	echo "************************************************************************\n\n\n";

	$error_counter = 0;
	// check error rate
	foreach ( $engagementEvents as $sessionId => $number ) {
		if ( array_key_exists($sessionId, $only_errors) ) {
			$error_counter++;
		}
	}


	$start_dt_time = gmdate('r',$start_time);
	$end_dt_time = gmdate('r',$end_time);

	// output
	echo "\n";
	echo "Start time: " . $start_time . ":\t" . $start_dt_time . "\n";
	echo "End time: " . $end_time . ":\t" . $end_dt_time . "\n";
	echo "Last adRequest: " . $adRequested_effective_end_time . "\n";
	echo "Length of the json: " . $json_data_length . "\n";
	echo "Length of cleaned jsonData: " . count($jsondata) . "\n";
	echo "Number of adRequests: " . count($only_adRequested_sessions_timestamps) . "\n";
    echo "Number of firstInteractions: " . count($firstInteraction_array) . "\n";
    echo "Number of interactions: " . count($interaction_array) . "\n";
	echo "Engagement events (first+inter): " . array_sum($engagementEvents) . "\n";
	echo "Ad requests events: " . count($only_adRequested_sessions_timestamps) . "\n";
	echo "Engagement rate: " . array_sum($engagementEvents)/count($only_adRequested_sessions_timestamps)*100 . "\n";
	echo "Total user error percentage of adRequested: " . ($only_errors_length/$only_adRequested_length*100) . "\n";
	echo "User error rate per interactive session: " . ($error_counter/count($engagementEvents)) . "\n";
	echo "Average number of interactions: " . array_sum($interaction_timestamps_indexes)/count($interaction_timestamps_indexes) . "\n";
	echo "Average delay: " . $average_delay . "\n";
	if ( count ($interactionTimeDelays) > 0 )
		echo "Standard deviation of the average delay: " . sd($interactionTimeDelays) . "\n";

// Array
// (
//     [0] => 24642
//     [1] => 29201
//     [2] => 29458
//     [3] => 27935
//     [4] => 30174
//     [5] => 32513
//     [6] => 31070
//     [7] => 36967
//     [8] => 37219
//     [9] => 35903
// )


// Array
// (
//     [0] => 1429182000	10-11
//     [1] => 1429185600	11-12
//     [2] => 1429189200	12-13
//     [3] => 1429192800	13-14
//     [4] => 1429196400	14-15
//     [5] => 1429200000	15-16
//     [6] => 1429203600	16-17
//     [7] => 1429207200	17-18
//     [8] => 1429210800	18-19
//     [9] => 1429214400	19-20
// )

// Length of the json: 2104038
// Length of cleaned jsonData: 2100244
// Number of adRequests: 315082
// Engagement rate: 2.9360610888594

//Start time: 1429178400:	Thu, 16 Apr 2015 10:00:00 +0000
//End time: 1429214400:	Thu, 16 Apr 2015 20:00:00 +0000
//Last adRequest: 1429214399.921548
//Length of the json: 2104038
//Length of cleaned jsonData: 2100244
//Number of adRequests: 315082
//Engagement events: 9251
//Ad requests events: 315082
//Engagement rate: 2.9360610888594
//Total user error percentage of adRequested: 2.7611859769838
//Average number of interactions: 2.0463743676223
//Average delay: 48.589316221274
//Standard deviation of the average delay: 468.20893260968


// 57 repeated firstInteractions
// MESS! double firstInteraction event present for the session: s1429209563x553001db76b145x06453061 at the time: 1429209675.022
// MESS! double firstInteraction event present for the session: s1429205290x03695970854f44x66826061 at the time: 1429205327.013
// MESS! double firstInteraction event present for the session: s1429205290x03695970854f44x66826061 at the time: 1429205329.581
// MESS! double firstInteraction event present for the session: s1429190914x552fb902c4eeb8x78778361 at the time: 1429190919.326
// MESS! double firstInteraction event present for the session: s1429205931xfd5f7984aa2dedx18053561 at the time: 1429205946.893
// MESS! double firstInteraction event present for the session: s1429188933x552fb14504f911x97782561 at the time: 1429188938.266
// MESS! double firstInteraction event present for the session: s1429189543xc8bbd4a06424e2x89971361 at the time: 1429189555.298
// MESS! double firstInteraction event present for the session: s1429191396x552fbae4c56152x33123861 at the time: 1429191410.046
// MESS! double firstInteraction event present for the session: s1429204939x552fefcb5e7728x30090761 at the time: 1429204950.904
// MESS! double firstInteraction event present for the session: s1429206474xc17230d5c39d39x58529561 at the time: 1429206513.481
// MESS! double firstInteraction event present for the session: s1429208200x029aaa60748628x64760061 at the time: 1429208215.695
// MESS! double firstInteraction event present for the session: s1429199347xb595937dc398d7x83584661 at the time: 1429199359.108
// MESS! double firstInteraction event present for the session: s1429213162x9f81b21c13ce78x55313261 at the time: 1429213167.341
// MESS! double firstInteraction event present for the session: s1429201535xf77c6e6a45a474x46908561 at the time: 1429201546.647
// MESS! double firstInteraction event present for the session: s1429195335xba22462b3dfee4x08715761 at the time: 1429195412.016
// MESS! double firstInteraction event present for the session: s1429210168xb14cddc59a68eex64933161 at the time: 1429210177.169
// MESS! double firstInteraction event present for the session: s1429210145x0fe38c905a34b7x20428461 at the time: 1429210159.538
// MESS! double firstInteraction event present for the session: s1429213658x42bf303a2f3693x15705661 at the time: 1429213702.508
// MESS! double firstInteraction event present for the session: s1429214322x37d4184ef0ffa4x48002261 at the time: 1429214330.39
// MESS! double firstInteraction event present for the session: s1429200004xfafc2ae44223fcx33290761 at the time: 1429200032.365
// MESS! double firstInteraction event present for the session: s1429211369x023217d841d619x58247661 at the time: 1429211372.082
// MESS! double firstInteraction event present for the session: s1429182936x552f99d8a39396x34804561 at the time: 1429182968.342
// MESS! double firstInteraction event present for the session: s1429200359x552fdde7363128x35178561 at the time: 1429200371.511
// MESS! double firstInteraction event present for the session: s1429188040xe48dbe2b5bf4adx60912661 at the time: 1429188048.535
// MESS! double firstInteraction event present for the session: s1429188040xe48dbe2b5bf4adx60912661 at the time: 1429188048.682
// MESS! double firstInteraction event present for the session: s1429188239x33c32c7afce896x68019461 at the time: 1429188254.847
// MESS! double firstInteraction event present for the session: s1429208200x029aaa60748628x64760061 at the time: 1429208215.69
// MESS! double firstInteraction event present for the session: s1429210511x909d407a91e378x28651161 at the time: 1429210519.145
// MESS! double firstInteraction event present for the session: s1429212489x04dabef908fb13x25611061 at the time: 1429212498.713
// MESS! double firstInteraction event present for the session: s1429210595xa935d852d63511x05063561 at the time: 1429210599.436
// MESS! double firstInteraction event present for the session: s1429204164x2bdec901db8eadx65614861 at the time: 1429204187.197
// MESS! double firstInteraction event present for the session: s1429182054x552f966661b7f6x51629061 at the time: 1429184829.433
// MESS! double firstInteraction event present for the session: s1429184131x552f9e833644b2x79666961 at the time: 1429184927.089
// MESS! double firstInteraction event present for the session: s1429201170x552fe112dad190x80510061 at the time: 1429201175.676
// MESS! double firstInteraction event present for the session: s1429190947x552fb92387eb56x90379461 at the time: 1429190983.817
// MESS! double firstInteraction event present for the session: s1429192944x703a0bd0ae276cx37474561 at the time: 1429192949.712
// MESS! double firstInteraction event present for the session: s1429189762x552fb48229bee3x44762161 at the time: 1429189794.592
// MESS! double firstInteraction event present for the session: s1429179089x552f8ad1a3a1d2x20436061 at the time: 1429179096.555
// MESS! double firstInteraction event present for the session: s1429214026x5530134a322264x46267361 at the time: 1429214030.202
// MESS! double firstInteraction event present for the session: s1429212628x55300dd47b9586x05118661 at the time: 1429212633.215
// MESS! double firstInteraction event present for the session: s1429201769x713161aa32c9a1x56422261 at the time: 1429201776.883
// MESS! double firstInteraction event present for the session: s1429194967x552fc8d745dd54x65401061 at the time: 1429196460.722
// MESS! double firstInteraction event present for the session: s1429208841x60428eddbb417dx97633961 at the time: 1429208856.277
// MESS! double firstInteraction event present for the session: s1429212834x5b6e94dbf946b2x88937061 at the time: 1429212855.021
// MESS! double firstInteraction event present for the session: s1429207999xe6f3c2609136dax97659961 at the time: 1429208004.756
// MESS! double firstInteraction event present for the session: s1429197236x552fd1b4376df2x60937061 at the time: 1429197244.571
// MESS! double firstInteraction event present for the session: s1429179354x552f8bdaf17c75x00354661 at the time: 1429179365.398
// MESS! double firstInteraction event present for the session: s1429204406xeb1c9e52d07514x72271061 at the time: 1429204411.131
// MESS! double firstInteraction event present for the session: s1429205701x552ff2c5198d25x18583961 at the time: 1429205723.308
// MESS! double firstInteraction event present for the session: s1429185100x552fa24cce03f0x90109061 at the time: 1429185111.389
// MESS! double firstInteraction event present for the session: s1429208798xc0558abcf4db36x53747961 at the time: 1429208801.167
// MESS! double firstInteraction event present for the session: s1429191793x552fbc7175e759x50682961 at the time: 1429191922.256
// MESS! double firstInteraction event present for the session: s1429191793x552fbc7175e759x50682961 at the time: 1429191924.628
// MESS! double firstInteraction event present for the session: s1429201274x552fe17a1f24c5x27803461 at the time: 1429202893.008
// MESS! double firstInteraction event present for the session: s1429208432x7b1365ce677f1cx85676361 at the time: 1429208437.038
// MESS! double firstInteraction event present for the session: s1429210793x553006a9abd962x52679361 at the time: 1429210800.614
// MESS! double firstInteraction event present for the session: s1429210940x5530073c8507f1x64792461 at the time: 1429210970.072



?>



