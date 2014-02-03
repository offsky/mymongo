<?php

require_once("../../../php/config.php");

$mongo = new mymongo();

$dbs = array();

foreach($MYMONGO as $db) {
	$test = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
	$db['health'] = $test->health($db['replicaSet'],$db['adminCollection']);
	$db['info'] = $test->info();
	$dbs[$db['name']] = $db;
}

echo json_encode($dbs);

?>
