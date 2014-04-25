<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}

$_POST['col'] = removeSlashes($_POST['col']);
$_POST['new'] = removeSlashes($_POST['new']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

$success = $m->renameCollection($_POST['col'],$_POST['new']);

echo json_encode($success);
?>
