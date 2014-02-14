<?php

require_once("../../../php/init.php");
require_once("_helpers.php");

$db = findDB($_GET['db']);
$iterations = 100; //the number of iterations to do in the perf test

if(empty($db['adminCollection'])) die(-1);//adminCollection disabled;

//start timer
$start = microtime(true);

$success = $m = new mymongo($db['hosts'],$db['user'],$db['password'],$db['name'],$db['replicaSet'],$db['ssl']);
$m->changeTable($db['adminCollection']);

if(!$success) die(-2); //couldn't connect

//do N inserts
for($i=0;$i<$iterations;$i++) {
	$ins = array('perf'=>uniqid('',true),'perf_i'=>$i);

	$success = $m->insert($ins);
	if($success==null) die(-3);
}

//do N updates
for($i=0;$i<$iterations;$i++) {
	$find = array('perf_i'=>$i);
	$update = array('$set'=>array('up'=>$i));
	$found = $m->update($find,$update);
	if(empty($found)) die(-4);
}

//do N reads
for($i=0;$i<$iterations;$i++) {
	$read = array('perf_i'=>$i);

	$found = $m->findOne($read);
	if(empty($found) || empty($found['perf']) || $found['up']!=$i) die(-5);
}

//do N deletes
for($i=0;$i<$iterations;$i++) {
	$remove = array('perf_i'=>$i);

	$found = $m->remove($remove);
	if(empty($found)) die(-6);
}

//stop timer
$end = microtime(true);
$time = $end-$start;

echo $time;
?>