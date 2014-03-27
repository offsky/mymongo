<?php

require_once("../../../php/init.php");
require_once("_helpers.php");

//select database
$db = findDB($_POST['db']);
$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);

//select collection
$m->changeTable($_POST['col']);

//parse id
$id = $_POST['id'];
$doc = json_decode($_POST['doc'],true);

$query = array('_id' => new MongoId($id));

//process the data
echo "#".$_POST['col'];
$doc = private_transformation_write($_POST['db'],$_POST['col'],$doc); //in config.php

unset($doc['_id']);
print_r($doc);

//do the query
$success = $m->update($query,array('$set'=>$doc));

if($success) echo 1;
else echo 0;
?>
