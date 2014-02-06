<?php

require_once("../../../php/config.php");

$dbs = array();

//loop through each db configured in the config file. 
//If fast is set thats it. If fast is unset do a healthcheck and gather info.
foreach($MYMONGO as $db) {
	if(empty($_GET['fast'])) {
		$test = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
		$db['health'] = $test->health($db['replicaSet'],$db['adminCollection']);
		
		$info = $test->db_info();
		$db['info'] = $info[0];
		$db['stats'] = $info[1];
	}
	unset($db['password']); //for security

	$dbs[$db['name']] = $db;
}

echo json_encode($dbs);

?>
