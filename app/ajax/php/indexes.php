<?php

require_once("../../../php/config.php");

$collections = null;

function findDB($name) {
	global $MYMONGO;
	foreach($MYMONGO as $db) {
		if($db['name']==$name) {
			return $db;
		}
	}
}

$db = findDB($_GET['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_GET['col']);
$indexes = $m->listIndexes();

echo json_encode($indexes);

?>
