<?php

require_once("../../../php/config.php");

$collections = null;

function findDB($name) {
	global $MYMONGO;
	foreach($MYMONGO as $db) {
		if($db['name']==$name) {
			return $db;
		}
	}
}

//select database
$db = findDB($_GET['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_GET['col']);

//parse query
$query = json_decode($_GET['query']);
if(empty($query)) $query = array();

//parse fields

//parse sort

//parse num
if(!empty($_GET['num'])) $num = intval($_GET['num']);
if(empty($num)) $num = 50;

//parse page
if(!empty($_GET['page'])) $page = intval($_GET['page']);
if(empty($page)) $page = 0;
$skip = $page*$num;


//do the query
$cursor = $m->find($query,"","",$num);

//do the skip
if(!empty($skip)) $m->skip($cursor,$skip);

$docs = array();
while($doc = $m->getNext($cursor)) {
	if($doc==-1) return false; //Mongo connection error
	if(empty($doc['_id'])) return false; //Mongo error
	$docs[] = $doc;
}

echo json_encode($docs);

?>
