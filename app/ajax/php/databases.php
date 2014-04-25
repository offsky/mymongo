<?php

require_once("../../php/init.php");

$dbs = array();

//loop through each db configured in the config file. 
//If fast is set thats it. If fast is unset do a healthcheck and gather info.
foreach($MYMONGO as $db) {
	if(empty($_GET['fast']) || (!empty($_GET['one']) && $_GET['one']==$db['name']) ) {
		$test = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
		$db['health'] = $test->health($db['replicaSet'],$db['adminCollection']);
		
		$info = $test->db_info();
		$db['info'] = $info[0];
		$db['stats'] = $info[1];
		$db['status'] = $info[2];
		$db['host'] = $info[3];
		$db['cmd'] = $info[4];
		$db['pool'] = $info[5];
		$db['profile'] = $info[6];
	}
	unset($db['password']); //for security

	if(empty($_GET['one']) || $_GET['one']==$db['name'] ) $dbs[$db['name']] = $db;
}

echo json_encode($dbs);

?>
