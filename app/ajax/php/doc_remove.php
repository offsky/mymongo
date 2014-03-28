<?php

require_once("../../../php/init.php");
require_once("_helpers.php");


//select database
$db = findDB($_POST['db']);

if($db['readonly']) {
	echo 0;
	exit();
}

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_POST['col']);

//parse id
$id = $_POST['id'];
$query = array('_id' => new MongoId($id));

//do the query
$success = $m->remove($query);

echo $success;

?>
