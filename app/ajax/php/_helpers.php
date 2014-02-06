<?php

function findDB($name) {
	global $MYMONGO;
	foreach($MYMONGO as $db) {
		if($db['name']==$name) {
			return $db;
		}
	}
}

?>
