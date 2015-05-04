<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}

$_POST['col'] = removeSlashes($_POST['col']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['db'],$db['replicaSet'],$db['ssl']);

$success = $m->deleteCollection($_POST['col']);

echo json_encode($success);
?>
