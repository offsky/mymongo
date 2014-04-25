<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}
$_POST['col'] = removeSlashes($_POST['col']);
$_POST['index'] = removeSlashes($_POST['index']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_POST['col']);

//remove index
$res = $m->deleteIndex($_POST['index']);
if($res) echo 1; else echo 0;
?>
