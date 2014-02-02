<?php

require_once("../../config.php");

$mongo = new mymongo();

$dbs = array();

foreach($MYMONGO as $db) {
	$test = $mongo->init($db['hosts'],$db['user'],$db['password'],"",$db['replicaSet'],$db['ssl']);

	$dbs[] = $db;
}

echo json_encode($dbs);

?>
