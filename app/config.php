<?php
	error_reporting(E_ALL);

	
	$MYMONGO = array();
	$MYMONGO[0] = array();
	$MYMONGO[0]['name'] = "Bitcoin";
	$MYMONGO[0]['hosts'] = "paulo.mongohq.com:10074"; //comma separated if multiple
	$MYMONGO[0]['user'] = "bitcoin";
	$MYMONGO[0]['password'] = "bitcoin12345";
	$MYMONGO[0]['replicaSet'] = false;
	$MYMONGO[0]['ssl'] = false;
	
	// $MYMONGO[1] = array();
	// $MYMONGO[1]['name'] = "Bitcoin";
	// $MYMONGO[1]['hosts'] = ["paulo.mongohq.com:10074"];
	// $MYMONGO[1]['user'] = "bitcoin";
	// $MYMONGO[1]['password'] = "bitcoin12345";
	// $MYMONGO[1]['replicaSet'] = false;
	// $MYMONGO[1]['ssl'] = false;


	$lib_dir = "/Users/jake/Sites/libraries/php/"; 

	//auto class loader.  When you use an unloaded class, it will automatically try to find it in libs/$class.php
	spl_autoload_register(function ($class) {
	    global $lib_dir;
	    include $lib_dir.strtolower($class).'.php';
	});

?>