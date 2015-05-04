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

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['db'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_POST['col']);

$index = json_decode($_POST['index']);

$unique = false;
$background = false;
$dropdups = false;
$sparse = false;

if(!empty($_POST['unique']) && $_POST['unique']=="true") $unique = true;
if(!empty($_POST['background']) && $_POST['background']=="true") $background = true;
if(!empty($_POST['dropdups']) && $_POST['dropdups']=="true") $dropdups = true;
if(!empty($_POST['sparse']) && $_POST['sparse']=="true") $sparse = true;

//add index
if(!empty($index)) {
	$success = $m->ensureIndex($index,$_POST['name'],$unique,$background,$dropdups,$sparse);
	if($success) echo 1; else echo 0;
} else {
	echo 0;
}
?>