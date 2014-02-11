<?php

require_once("../../../php/init.php");
require_once("_helpers.php");


//select database
$db = findDB($_GET['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_GET['col']);

//parse query
$query = json_decode($_GET['query']);
if(empty($query)) $query = array();

//parse fields
$fields = json_decode($_GET['fields']);
if(empty($fields)) $fields = array();

//parse sort
$sort = json_decode($_GET['sort']);
if(empty($sort)) $sort = array();

//parse num
if(!empty($_GET['num'])) $num = intval($_GET['num']);
if(empty($num)) $num = 50;

//parse page
if(!empty($_GET['page'])) $page = intval($_GET['page']);
if(empty($page)) $page = 0;
$skip = $page*$num;


//do the query
$cursor = $m->find($query,$fields,$sort);

//get the explain
$explain = $m->explain($cursor);

//do the skip
if(!empty($skip)) $m->skip($cursor,$skip);

$docs = array();
while(($doc = $m->getNext($cursor)) && $num) {
	if($doc==-1) return false; //Mongo connection error
	if(empty($doc['_id'])) return false; //Mongo error
	$doc = private_transformation_read($doc); //in config.php
	$docs[] = $doc;
	$num--;
}

echo json_encode(array("docs"=>$docs,"explain"=>$explain));

?>
