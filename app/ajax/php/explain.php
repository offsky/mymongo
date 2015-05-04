<?php

require_once("../../php/init.php");
require_once("_helpers.php");

$_GET['col'] = removeSlashes($_GET['col']);
$_GET['query'] = removeSlashes($_GET['query']);
$_GET['fields'] = removeSlashes($_GET['fields']);
$_GET['sort'] = removeSlashes($_GET['sort']);

//select database
$db = findDB($_GET['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['db'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_GET['col']);

//parse query
if(!empty($_GET['query'])) $query = json_decode($_GET['query']);
if(empty($query)) $query = array();

//parse fields
if(!empty($_GET['fields'])) $fields = json_decode($_GET['fields']);
if(empty($fields)) $fields = array();

//parse sort
if(!empty($_GET['sort'])) $sort = json_decode($_GET['sort']);
if(empty($sort)) $sort = array();

$error = null;
$explain = null;

//do the query
$cursor = $m->find($query,$fields,$sort);
if($cursor==null) $error = $m->lastErrMsg;

//get the explain
if($cursor!==null) {
	$explain = $m->explain($cursor);
	if($explain==null) $error = $m->lastErrMsg;
}

echo json_encode(array("explain"=>$explain,"error"=>$error));

?>
