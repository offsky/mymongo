<?php

require_once("../../../php/config.php");
require_once("_helpers.php");


$db = findDB($_GET['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_GET['col']);

//add index
$m->ensureIndex();

?>