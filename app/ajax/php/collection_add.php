<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}

$_POST['name'] = removeSlashes($_POST['name']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

$success = $m->addCollection($_POST['name'],$_POST['capped'],$_POST['size'],$_POST['max']);

echo json_encode($success);
?>
