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

$db = findDB($_GET['db']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($_GET['col']);

$cursor = $m->find(array(),"","",50);
$docs = array();
while($doc = $m->getNext($cursor)) {
	if($doc==-1) return false; //Mongo connection error
	if(empty($doc['_id'])) return false; //Mongo error
	$docs[] = $doc;
}

echo json_encode($docs);

?>
