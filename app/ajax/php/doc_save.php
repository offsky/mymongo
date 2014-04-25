<?php

require_once("../../php/init.php");
require_once("_helpers.php");

//select database
$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}

$_POST['col'] = removeSlashes($_POST['col']);
$_POST['doc'] = removeSlashes($_POST['doc']);

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_POST['col']);

//parse id
$id = $_POST['id'];
$doc = json_decode($_POST['doc'],true);

//process the data
$doc = private_transformation_write($_POST['db'],$_POST['col'],$doc); //in config.php

unset($doc['_id']);

//do the save
if($id=="new") {
	$success = $m->insert($doc);
} else {
	$query = array('_id' => new MongoId($id));
	$success = $m->update($query,array('$set'=>$doc));
}

if($success) echo $doc['_id'];
else echo 0;
?>
