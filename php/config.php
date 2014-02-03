<?php
	error_reporting(E_ALL);

	
	$MYMONGO = array();
	$MYMONGO[0] = array();
	$MYMONGO[0]['hosts'] = "paulo.mongohq.com:10074"; //comma separated if multiple
	$MYMONGO[0]['name'] = "bitcoin";
	$MYMONGO[0]['user'] = "bitcoin";
	$MYMONGO[0]['password'] = "bitcoin12345";
	$MYMONGO[0]['replicaSet'] = false;
	$MYMONGO[0]['ssl'] = false;
	$MYMONGO[0]['adminCollection'] = "healthcheck";
	
	$MYMONGO[1] = array();
	$MYMONGO[1]['hosts'] = "staff.mongohq.com:10097";
	$MYMONGO[1]['name'] = "toodledotest";
	$MYMONGO[1]['user'] = "mymongo";
	$MYMONGO[1]['password'] = "d928695aa98ac1231s";
	$MYMONGO[1]['replicaSet'] = false;
	$MYMONGO[1]['ssl'] = false;
	$MYMONGO[1]['adminCollection'] = "healthcheck";


	$lib_dir = "/Users/jake/Sites/mymongo/php/"; 

	//auto class loader.  When you use an unloaded class, it will automatically try to find it in libs/$class.php
	spl_autoload_register(function ($class) {
	    global $lib_dir;
	    include $lib_dir.strtolower($class).'.php';
	});

?>