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

?>
