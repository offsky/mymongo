<?php

require_once("../../../php/config.php");

$dbs = array();

foreach($MYMONGO as $db) {
	$test = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
	$db['health'] = $test->health($db['replicaSet'],$db['adminCollection']);
	
	$info = $test->info();
	$db['info'] = $info[0];
	$db['stats'] = $info[1];
	
	$dbs[$db['name']] = $db;
}

echo json_encode($dbs);

?>
