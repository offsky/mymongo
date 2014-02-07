<?php

require_once("../../../php/init.php");
require_once("_helpers.php");


$db = findDB($_POST['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_POST['col']);

//remove index
$res = $m->deleteIndex($_POST['index']);
if($res) echo 1; else echo 0;
?>
