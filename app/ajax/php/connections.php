<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$db = findDB($_GET['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$connections = $m->connections();
echo json_encode($connections);

?>
