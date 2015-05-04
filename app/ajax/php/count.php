<?php

require_once("../../php/init.php");
require_once("_helpers.php");


//select database
$db = findDB($_GET['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['db'],$db['replicaSet'],$db['ssl']);

$_POST['col'] = removeSlashes($_POST['col']);
$_POST['query'] = removeSlashes($_POST['query']);


//select collection
$m->changeTable($_GET['col']);

//parse query
if(!empty($_GET['query'])) $query = json_decode($_GET['query']);
if(empty($query)) $query = array();

$explain = null;

$count = $m->count($query);

echo json_encode($count);

?>
