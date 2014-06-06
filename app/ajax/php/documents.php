<?php

require_once("../../php/init.php");
require_once("_helpers.php");



$_GET['col'] = removeSlashes($_GET['col']);
$_GET['query'] = removeSlashes($_GET['query']);
$_GET['fields'] = removeSlashes($_GET['fields']);
$_GET['sort'] = removeSlashes($_GET['sort']);


//select database
$db = findDB($_GET['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

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

//parse num
if(!empty($_GET['num'])) $num = intval($_GET['num']);
if(empty($num)) $num = 50;

//parse page
if(!empty($_GET['page'])) $page = intval($_GET['page']);
if(empty($page)) $page = 0;
$skip = $page*$num;

$error = null;
$explain = null;

error_log(json_encode($_GET['query'])." = ".json_encode($query));

//do the query
$cursor = $m->find($query,$fields,$sort,$num,2000,$skip);
if($cursor==null) $error = $m->lastErrMsg;

//get the explain
if($cursor!==null) {
	$explain = $m->explain($cursor);
	if($explain==null) $error = $m->lastErrMsg;
}

$docs = array();
if($cursor!==null) {
	while($doc = $m->getNext($cursor)) {
		if($doc==-1) break; //Mongo connection error
		if(empty($doc['_id'])) break; //Mongo error
		$doc = private_transformation_read($_GET['db'],$_GET['col'],$doc); //in config.php
		$docs[] = $doc;
	}
}
echo json_encode(array("docs"=>$docs,"explain"=>$explain,"error"=>$error));

?>
