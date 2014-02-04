<?php

require_once("../../../php/config.php");

$m = new mymongo();

$info = $m->client_info();

echo json_encode($info);

?>
