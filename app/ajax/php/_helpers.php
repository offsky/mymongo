<?php

//looks up the DB details by name from your config file
function findDB($name) {
	global $MYMONGO;
	foreach($MYMONGO as $db) {
		if($db['name']==$name) {
			return $db;
		}
	}
}

//remove slashes if your server is set to add them automatically
function removeSlashes($in) {
	if(get_magic_quotes_gpc()) return stripslashes($in);	
	return $in;
}

//Binary Data cannot get converted to JSON so can't be sent back to the application properly.
//It would be gibberish anyway.  If you want to see this data, you need to fill out the
//private_transformation_read() function in config() to transpose this data to readable text.
//This function will take any binary data not already converted and replace it with a placeholder.
ini_set('xdebug.max_nesting_level', 500);
function removeMongoBinData($in) {
	foreach($in as $key => $value) {
 		if(is_object($value) && get_class($value)=="MongoBinData") {
			$in[$key] = "MongoBinData";
		} else if(is_array($value)) { //need to recurse into arrays
			$in[$key] = removeMongoBinData($value);
		}
	}

	return $in;
}

?>
