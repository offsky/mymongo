<?php

require_once("../../../php/init.php");

$m = new mymongo();

$info = $m->client_info();

echo json_encode($info);

?>
