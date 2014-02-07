<?php

require_once("../../../php/init.php");
require_once("_helpers.php");


$db = findDB($_POST['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_POST['col']);

//add index
$index = json_decode($_POST['index']);
if(!empty($index)) {

	$success = $m->ensureIndex($index,$_POST['name']);
	if($success) echo 1; else echo 0;
} else {
	echo 0;
}
?>