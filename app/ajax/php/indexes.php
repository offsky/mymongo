<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_GET['db']);

$_GET['col'] = removeSlashes($_GET['col']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['db'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_GET['col']);
$indexes = $m->listIndexes();

echo json_encode($indexes);

?>
