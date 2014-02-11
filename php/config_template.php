<?php

	//This is a template. Put in your credentials and rename this file to config.php

	$MYMONGO = array();
	$MYMONGO[0] = array();
	$MYMONGO[0]['hosts'] = "abc.host.com:12345"; //comma separated if multiple
	$MYMONGO[0]['name'] = "dbName";
	$MYMONGO[0]['user'] = "dbUser";
	$MYMONGO[0]['password'] = "password";
	$MYMONGO[0]['replicaSet'] = false;
	$MYMONGO[0]['ssl'] = false;
	$MYMONGO[0]['adminCollection'] = "healthcheck"; // a collection that will be created and used for this tool to function perfectly
	
	// $MYMONGO[1] = array();
	// $MYMONGO[1]['hosts'] = "abc.host2.com:12345";
	// $MYMONGO[1]['name'] = "dbName2";
	// $MYMONGO[1]['user'] = "dbUser2";
	// $MYMONGO[1]['password'] = "password";
	// $MYMONGO[1]['replicaSet'] = false;
	// $MYMONGO[1]['ssl'] = false;
	// $MYMONGO[1]['adminCollection'] = "healthcheck";

	$showAdminCollection = false; //this will hide the adminCollection table from being displayed
	

	//if you need to provide some custom logic to transform documents
	//into a more readable format, here is where you do it.
	//for example, if you need to uncompress or decrypt data, do it here
 	function private_transformation_read($doc) {
 		//your code here, otherwise return $doc unchanged
 		return $doc;
 	}

 	//if you need to provide some custom logic to transform documents
	//into a more writtable format, here is where you do it.
	//for example, if you need to compress or encrypt data, do it here
 	function private_transformation_write($doc) {
 		//your code here, otherwise return $doc unchanged
 		return $doc;
 	}


?>