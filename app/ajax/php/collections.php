<?php

require_once("../../../php/config.php");


$collections = null;

foreach($MYMONGO as $db) {
	if($db['name']==$_GET['db']) {
		$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
		$collections = $m->listCollections();
	}
}

echo json_encode($collections);

?>
