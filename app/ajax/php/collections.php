<?php

require_once("../../../php/init.php");
require_once("_helpers.php");

$db = findDB($_GET['db']);

$ignore="";
if($showAdminCollection==false) $ignore = $db['adminCollection'];

$m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$collections = $m->listCollections($ignore);
echo json_encode($collections);

?>
