<?php
/*
	Simple wrapper class for PHP's Mongo drivers that abstracts the connection and error handling

	Copyright Toodledo 2012
	
	Usage:
	
	$m = new mymongo("server","collection");
	$obj = array( "date" => date('r'));
	$m->insert($obj,false);
	
*/

$mongodb_calls = 0; //keeps track of number of db calls per page (for stats if enabled)
$mongodb_querytime = 0; //keeps track of the total tile waiting for mongo
$mongodb_callLog = array(); //set to false to prevent loging of queries

$verbose = false;
//a callback used for verbose logging
function mongo_log_cb($a, $b, $c) { 
	global $mongoErrorLog; 
	if($mongoErrorLog) {
		switch ($a) {
			case MongoLog::RS: $a="REPLSET"; break;
			case MongoLog::CON: $a= "CON"; break;
			case MongoLog::IO: $a= "IO"; break;
			case MongoLog::SERVER: $a= "SERVER"; break;
			case MongoLog::PARSE: $a= "PARSE"; break;
		} 
		switch ($b) {
			case MongoLog::WARNING: $b= "WARN"; break;
			case MongoLog::INFO: $b= "INFO";break;
			case MongoLog::FINE: $b= "FINE"; break;
		}
		
		error_log($a." ".$b." ".$c."\n",3,$mongoErrorLog);
	} 
}

class mymongo {
	public $connected = false; //true when we are connected to a server

	private $MyHost = null; //the server that I am connecting to in the cloud
	private $MyHosts = null; //Generated from $MyHosts, this is an array of the individual hosts
	private $MyUser = null; //my username for the database on that server (not the same as my username for the cloud service)
	private $MyPass = null; //my password for the database on that server
	private $MyDB = null; //the name of the database in the cloud
	private $MyTable = null; //the name of the collection that we are working on
	private $replicaSet = null;
	private $ssl = false;
	private $connectionType = 0; //A debugging variable. 0 means as specified, 1 means with replica set disabled, 2 means first of pair used, 3 means other of pair after query
	
	public $m = null; //the internal reference to the mongo driver
	public $db = null; //the internal reference to the collection that we are working on
	public $lastErr = null;
	public $lastErrMsg = null;
	

/* MYMONGO ===========================================================================
	Opens a connection to the database.  The underlying driver tries to use a persistent connection
	if available.  If not, it will make a new connection which has increased latency.  If we had
	to make a new connection, the first operation that we make after connecting will also be a little slower.
	Returns true/false on success/fail.
*/
	public function mymongo($host, $user, $pass, $database, $replicaSet=null, $ssl=false) {
		global $beta, $verbose;
		
		$this->MyHost = $host;
		$this->MyHosts = explode(",", $this->MyHost);
		$this->MyUser = $user;
		$this->MyPass = $pass;
		$this->MyDB = $database;
		$this->replicaSet = $replicaSet;
		if($ssl) $this->ssl = true;
		
		if($verbose) { //will turn on verbose logging to identify problems
			MongoLog::setModule(MongoLog::ALL);
			MongoLog::setLevel(MongoLog::ALL);
			MongoLog::setCallback('mongo_log_cb');
		}
		if(!empty($beta)) {
			global $mongoBypassConnect; //I can set this global flag to prevent mongo from connecting. Use this for testing what happens when mongo connection fails
			if($mongoBypassConnect) return false;
		}
		
		if(empty($host) || empty($user)) return; //cant connect without a host!

		return $this->connect();
	}

/* CONNECT ==================================================================================
	finishes the job of init
*/
	private function connect() {
		$start = microtime(true);
		
		//try to connect to the db. Keep a low timeout to prevent stalled DB crashing apache with hung PHP jobs
		$success = $this->connectClient();
		
		if(!$success && !empty($this->replicaSet)) {	//try again without the replica set.  It will connect randomly to either server, so 50% of the time writes will fail because it is on the secondary
			//echo "Disabling replica set<br />";
			$this->replicaSet = "";
			$this->connectionType = 1;
			$success = $this->connectClient();
		}
		
		if(!$success && count($this->MyHosts)) { //try again with first of pair only
			//echo "Taking first server in array";
			$this->MyHost = array_shift($this->MyHosts);
		
			$this->connectionType = 2;
			$success = $this->connectClient();
		}
		
		if(!$success && count($this->MyHosts)) {	//try again with first of pair only
			//echo "Taking second server in array";
			$this->MyHost = array_shift($this->MyHosts);
	
			$this->connectionType = 3;
			$success = $this->connectClient();
		}

		$this->performance($start,"CONNECT",$this->MyDB);

		if(empty($this->m)) return false;
		
		//try to select the db		
		$start = microtime(true);
		try {
			$this->db = $this->m->selectDB($this->MyDB); // select a database
			$this->db->setReadPreference(MongoClient::RP_PRIMARY_PREFERRED); //default to primary prefered which is better than default of primary only
		} catch(Exception $e) {
			//It does not appear that Mongo can fail here since it doesn't hit server for database select.
			$this->log_db_error("SELECTDB",$this->MyDB,$e->getMessage(),$e->getCode());
			$this->db = null;
			$this->closeDatabase();
			$this->m = null;
			return false; //could not select the database
		}

		$this->performance($start,"SELECTDB",$this->MyTable);
		
		$this->connected = true;
		return true;
	}

/* CONNECTCLIENT ==================================================================================
	Connects the MongoClient using the class settings.
*/
	private function connectClient() {		
		if(empty($this->MyHost)) return false;
		if(!class_exists("MongoClient")) return false;

		//try to connect to the db. Keep a low timeout to prevent stalled DB crashing apache with hung PHP jobs
		$flags = array("connectTimeoutMS" => 2000, "socketTimeoutMS"=>2000);
		if(!empty($this->replicaSet)) $flags['replicaSet'] = $this->replicaSet;
		if($this->ssl) $flags['ssl'] = true;
		
		try {			
			//echo "Connecting ".$this->MyHost." ".$flags['replicaSet']."<br />";
			$this->m = new MongoClient("mongodb://".$this->MyUser.":".$this->MyPass."@".$this->MyHost."/".$this->MyDB, $flags);	
		} catch(MongoConnectionException $e) {
			ini_set("mongo.ping_interval",1); //reduce ping interval in an attempt to jump start the discovery process

			$this->log_db_error("CONNECT",$this->MyDB,$e->getMessage(),$e->getCode());
			$this->closeDatabase();
			$this->m = null;
			return false;
		}
		return true;
	}
	
/* RECONNECT ============================================================================
	A write failed, so we need to reconnect to a different db server
*/
	public function reconnect($message) {		
		ini_set("mongo.ping_interval",1);
		if(!count($this->MyHosts)) return false; //if we don't have anything else to try, fail
		$this->replicaSet = "";
		
		$bad = str_replace(": not master","",$message); //find out who is failing
		
		$this->MyHost = array_shift($this->MyHosts);
		//echo "a ".$this->MyHost."<br />";
		if($bad==$this->MyHost) {
			//echo "BAD";
			if(count($this->MyHosts)) $this->MyHost = array_shift($this->MyHosts);
			else return false;
		}
		//echo "b ".$this->MyHost."<br />";	
		$this->connectionType = 4;
		return $this->connect(); //reconnect
	}
	
		
/* DISCONNECT ============================================================================
	Prevents further calls from working
*/
	public function disconnect() {
//		$this->closeDatabase();
		$this->m = null;
		$this->db = null;
		$this->connected = false;
	}
	
/* CLOSEDATABASE =========================================================================
	This does not need to be called unless perhaps I plan to do lots of processing after finishing
*/
	public function closeDatabase() {
		if($this->m) $this->m->close(true);
	}
	
/* REPLICAREAD ===========================================================================
	Pass in true/false to force reads from replica
*/
	public function replicaRead($useReplica) {
		$success = false;
		if($this->db==null) return false; //we never got connected
		if(empty($this->replicaSet)) return false; //cant set this property if not using replica sets

		if($useReplica) {
			$success = $this->db->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		} else {
			$success = $this->db->setReadPreference(MongoClient::RP_PRIMARY_PREFERRED);
		}

		return $success;
	}

/* CHANGETABLE ===========================================================================
	Changes the collection/table that we are operating on. This does not make a network call
	and does not cause any increased response time or connection issues. It just sets an 
	internal variable to be used on the next connect.
*/
	public function changeTable($newTable) {
		$this->MyTable = $newTable;	
	}
	
/* LISTCOLLECTIONS ============================================================================
	Returns information for each collection in this database
*/
	public function listCollections($ignore="") {
		if($this->db==null) return null; //we never got connected
		
		$result = array();
		
		try {
			//get namespaces, which we need to see size of capped collections
			$collection = $this->db->selectCollection("system.namespaces");
			$namespaces = array();
			$cursor = $collection->find(array());
			while($row = $cursor->getNext()) {
				if(!empty($row['options'])) $namespaces[$row['name']] = $row['options'];
				else $namespaces[$row['name']]=null;
			}

			$collections = $this->db->listCollections();
			foreach ($collections as $c) {
				$name = $c->getName();
				if($name!==$ignore) { //hide one collection
					$stats = $this->db->command(array("collStats"=>$name));
					$result[] = array('name'=>$name,'stats'=>$stats,'namespace'=>$namespaces[$this->MyDB.".".$name]);
				}
			}

		} catch(MongoException $e) {
			$this->log_db_error("listCollections",$this->MyTable,$e->getMessage(),$e->getCode());
			return null;
		} 

		return $result;
	}

/* ADDCOLLECTION ============================================================================
	Adds a new collection to the database
*/
	public function addCollection($name,$capped,$size,$max) {
		if($this->db==null) return false; //we never got connected
		if(empty($name)) return false;

		$options = array();
		if($capped==1 && $size>0) {
			$options['capped'] = true;
			$options['size'] = intval($size);
			if(!empty($max)) $options['max'] = intval($max);		
		}

		try {
			$result = $this->db->createCollection($name,$options);
		} catch(MongoException $e) {
			$this->log_db_error("addCollection",$name,$e->getMessage(),$e->getCode());
			return false;
		}

		//TODO: there does not currently seem to be a way to detect if it failed to create the collection

		return true;
	}


/* DELETECOLLECTION ============================================================================
	Removes a collection from the database
*/
	public function deleteCollection($name) {
		if($this->db==null) return false; //we never got connected
		if(empty($name)) return false;

		$collection = $this->db->selectCollection($name);

		try {
			$result = $collection->drop();
		} catch(MongoException $e) {
			$this->log_db_error("deleteCollection",$name,$e->getMessage(),$e->getCode());
			return false;
		}

		if($result['ok']) return true;
		return false;
	}

/* INSERT ================================================================================
	Inserts an object into a table. 
	Since object is passed by reference, it will return with the _id field set.
	If $safe is false, it will return immediately and not wait for a success/fail response
	returns null on error which doesn't necessarily mean that it failed. It could have just timed out waiting.
*/
	public function insert($object, $safe=true, $timeout=2000) {
		$this->log_db_mark("STARTING INSERT");
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		// select a collection (analogous to a relational database's table)
		$collection = $this->db->selectCollection($this->MyTable);

		$flags = array("timeout" => intval($timeout), "wtimeout" => intval($timeout));
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->insert($object, $flags);
		} catch(MongoException $e) {
			//if it is a timeout, it is possible that the insert still worked
			$this->log_db_error("INSERT1",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($object));
			$this->performance($start,"INSERT",$object,false);
			
			if($e->getCode()==10058 || $e->getCode()==16) { //not master, attempt to connect to the other one
				$success = $this->reconnect($e->getMessage());
				if(!$success) return null;
				return $this->insert($object,$safe,$timeout);
			}
			
			return null;
		}

		//determine if there was an error, like a duplicate key
		if($safe && $result['ok']!=1) {
			$this->log_db_error("INSERT2",$this->MyTable,$result['err']." ".$result['errmsg'],$result['code'],$this->serializeQuery($object));
			$this->performance($start,"INSERT",$object,false);
			return null;
		}
		
		$this->performance($start,"INSERT",$object,true);

		//$object will now contain a _id field with the new mongo id number
		return $result;
	}
	
/* REMOVE ================================================================================
	Removes records from table based on criteria. 
	If safe is true returns number of records deleted, which can be zero. If safe is false, returns 1 always
*/
	public function remove($criteria,$safe=true,$timeout=2000) {
		$this->log_db_mark("STARTING REMOVE");
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$flags = array("timeout" => intval($timeout), "wtimeout" => intval($timeout));
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->remove($criteria, $flags); 
		} catch(MongoCursorException $e) {
			$this->log_db_error("REMOVE1",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($criteria));
			$this->performance($start,"REMOVE",$criteria,false);
			
			if($e->getCode()==10056 || $e->getCode()==16) { //not master, attempt to connect to the other one
				$success = $this->reconnect($e->getMessage());
				if(!$success) return null;
				return $this->remove($criteria,$safe,$timeout);
			}
			
			return null;
		} catch(MongoCursorTimeoutException $e) {
			//if it is a timeout, it is likely that the insert still worked
			$this->log_db_error("REMOVE2",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($criteria));
			$this->performance($start,"REMOVE",$criteria,false);
			return null;
		}
		
		if($safe && $result['ok']!=1) {
			$this->log_db_error("REMOVE3",$this->MyTable,$result['err']." ".$result['errmsg'],$result['code'],json_encode($criteria));
			$this->performance($start,"REMOVE",$criteria,false);
			return null;
		}
		
		$this->performance($start,"REMOVE",$criteria,true);
		
		if($safe && $result['ok']) return $result['n'];
		return $result;
	}

/* UPDATE ================================================================================
	Sets new data for criteria on active table
	If upsert is true, it will create the record if it does not exist
	returns null on error which doesn't necessarily mean that it failed. It could have just timed out waiting.
	if safe is true, on success returns the number of records altered. If safe is false, returns true always
*/
	public function update($criteria,$newdata,$safe=true,$upsert=false,$timeout=2000) {
		$this->log_db_mark("STARTING UPDATE");
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$flags = array("multiple" => true, "timeout" => intval($timeout), "wtimeout" => intval($timeout), "upsert" => $upsert);
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->update($criteria, $newdata, $flags);
		} catch(MongoException $e) {
			$this->log_db_error("UPDATE1",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery(array($criteria,$newdata)));
			$this->performance($start,"UPDATE",$criteria,false);
			
			if($e->getCode()==10054 || $e->getCode()==16) { //not master, attempt to connect to the other one
				$success = $this->reconnect($e->getMessage());
				if(!$success) return null;
				return $this->update($criteria,$newdata,$safe,$upsert,$timeout);
			}
			
			return null;
		}
		
		if($safe && $result['ok']!=1) {
			$this->log_db_error("UPDATE2",$this->MyTable,$result['err']." ".$result['errmsg'],$result['code'],$this->serializeQuery(array($criteria,$newdata)));
			$this->performance($start,"UPDATE",$criteria,false);
			return null;
		}
		$this->performance($start,"UPDATE",$criteria,true);
		
		if($safe && $result['ok']) return $result['n'];
		return $result;
	}
		
/* FIND ==================================================================================
	Runs a query on a table and returns a cursor. 
	This doesn't actually run the query yet, so it is really fast.  When you iterate over
	the cursor, the query is run.
	The fields(comma separated string) specified will be returned or all fields if not
	sort is an array('age' => -1, 'date' => 1)
*/
	public function find($query,$fields = array(),$sort=null,$limit=0,$timeout=2000,$skip=0) {
		$this->log_db_mark("STARTING FIND");
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$skip = intval($skip);
		$limit = intval($limit);

		$collection = $this->db->selectCollection($this->MyTable);
		
		if(is_string($fields)) { 
			$fields = $this->fieldStrToArray($fields);
		}
		try {
			$cursor = $collection->find($query,$fields)->timeout($timeout);
			if(!empty($sort)) $cursor->sort($sort);
			if(!empty($skip)) $cursor->skip($skip);
			if(!empty($limit)) $cursor->limit($limit);
					//$cursor->hint("u_1__id_-1");

		} catch(MongoException $e) {
			$this->log_db_error("FIND",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($query));
			$this->performance($start,"FIND",$query,false);
			return null;
		}
		
		$this->performance($start,"FIND",$query,true);
		
		return $cursor;
	}

/* EXPLAIN ==================================================================================
	Returns information about the query on the passed cursor.  Includes the index, number or records
	matched and scanned. Also query time.
*/
	public function explain($cursor) {
		$start = microtime(true);
		if($this->db==null) return null; //we never got connected
		if($cursor==null) return null;
		
		try {
			$explain = $cursor->explain();
		} catch(MongoException $e) {
			$this->log_db_error("EXPLAIN",$this->MyTable,$e->getMessage(),$e->getCode(),"");
			$this->performance($start,"EXPLAIN","",false);
			return null;
		}

		if(empty($explain)) return null;
				
		if($explain['cursor']=="BasicCursor") $indexUsed = "None";
		else $indexUsed = $explain['cursor'];

		$info = array("index"=>$indexUsed,"matches"=>$explain['n'],"scanned"=>$explain['nscannedAllPlans'],"time"=>$explain['millis']);

		return $info;
	}
	
/* COUNT ==================================================================================
	Counts the number of records matching the query. Returns an integer
	Can pass a limit of the number to count before stopping
*/
	public function count($query, $limit=0) {
		$this->log_db_mark("STARTING COUNT");
		$start = microtime(true);
		$limit = intval($limit);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		try {
			$count = $collection->count($query,$limit);
		
		} catch(MongoException $e) {
			$this->log_db_error("COUNT",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($query));
			$this->performance($start,"COUNT",$query,false);
			return null;
		}
		
		$this->performance($start,"COUNT",$query,true);
		
		return $count;
	}

/* SKIP ==================================================================================
	SKips the number of specified rows on the cursor
	returns true/false on success
*/
	public function skip($cursor,$skip) {
		if(empty($cursor)) return false;
		$skip = intval($skip);
		if(empty($skip)) return true;

		try {
			$cursor->skip($skip);
		} catch(MongoCursorException $e) {
			$info = $this->serializeQuery($cursor->info());		
			$this->log_db_error("SKIP1",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return false;
		} catch(MongoCursorTimeoutException $e) {
			$info = $this->serializeQuery($cursor->info());
			$this->log_db_error("SKIP2",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return false;
		} catch(MongoException $e) {
			$info = $this->serializeQuery($cursor->info());
			$this->log_db_error("SKIP3",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return false;
		}
		
		return true;
	}

		
/* GETNEXT ==================================================================================
	Returns the next row on a cursor which may be null if there is no next row. This is a simple wrapper to catch exceptions.
	returns -1 on exception
*/
	public function getNext($cursor) {
		$this->log_db_mark("STARTING GETNEXT");
		if(empty($cursor)) return -1;
		try {
			$row = $cursor->getNext();
		} catch(MongoCursorException $e) {
			$info = $this->serializeQuery($cursor->info());		
			$this->log_db_error("GETNEXT1",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return -1;
		} catch(MongoCursorTimeoutException $e) {
			$info = $this->serializeQuery($cursor->info());
			$this->log_db_error("GETNEXT2",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return -1;
		} catch(MongoException $e) {
			$info = $this->serializeQuery($cursor->info());
			$this->log_db_error("GETNEXT3",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return -1;
		}
		
		return $row;
	}
	
/* FINDONE ===============================================================================
	Runs a query on a table and returns one row with the fields(comma separated string) specified or all fields if not set.
	It is not possible to set a timeout, so it could take forever. Use with caution
*/
	public function findOne($query,$fields = "") {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		if(is_string($fields)) {
			$fields = $this->fieldStrToArray($fields);
		}
		
		try {
			$result = $collection->findOne($query,$fields);
		} catch(MongoException $e) {
			$this->log_db_error("FINDONE",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($query));
			$this->performance($start,"FINDONE",$query,false);
			return null;
		}
		
		$this->performance($start,"FINDONE",$query,true);
		
		return $result;
	}
	
/* DISTINCT ==================================================================================
	Finds distinct values for a field returns an array. 
	NOTE: NOT THOROUGHLY TESTED
*/
	public function distinct($query) {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
				
		$fields = $this->fieldStrToArray($fields);
		
		try {
			$cursor = $this->db->command(array("distinct" => $this->MyTable, "key" => $query));
		
		} catch(MongoException $e) {
			$this->log_db_error("DISTINCT",$this->MyTable,$e->getMessage(),$e->getCode());
			$this->performance($start,"DISTINCT",$query,false);
			return null;
		}
		
		$this->performance($start,"DISTINCT",$query,true);
		
		return $cursor;
	}

/* AGGREGATE ==================================================================================
	Runs the aggregation commands in $ops.  Not fully tested
	
	http://docs.mongodb.org/manual/reference/sql-aggregation-comparison/
	http://php.net/manual/en/mongocollection.aggregate.php
	
	NOTE: NOT THOROUGHLY TESTED
*/
	public function aggregate($ops) {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
				
		try {
			$collection = $this->db->selectCollection($this->MyTable);
			$result = $collection->aggregate($ops);
		} catch(MongoException $e) {
			$this->log_db_error("AGGREGATE",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($ops));
			return null;
		}
		
		$this->performance($start,"AGGREGATE");
		
		return $result;
	}


/* MAPREDUCE ==================================================================================
	http://www.mongodb.org/display/DOCS/MapReduce
	
	This runs a map reduce function and returns the results inline.  Care must be taken to ensure
	that results are <16MB otherwise we need to send the results to a table instead using the "replace"
	option in "out"
	
	$map = "function() { emit(this.radius,1); }"; //which values to aggregate and send to the reducer
		
	$reduce = "function(k, vals) { ". //how to reduce the array of values for each key
			"var sum = 0;".
			"for (var i in vals) {".
				"sum += vals[i];". 
			"}".
			"return sum; }";
			
	NOTE: NOT THOROUGHLY TESTED
*/
	public function mapreduce($map,$reduce,$query='') {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
				
		try {
			
			$map = new MongoCode($map);
			$reduce = new MongoCode($reduce);
			$command = array(
				"mapreduce" => $this->MyTable, 
				"map" => $map,
				"reduce" => $reduce,
				"out" => array("inline" => 1)//	"out" => array("replace" => $out)));
				);
			if(!empty($query)) $command['query'] = $query;
						
			$result = $this->db->command($command,array("timeout"=>30000)); 
	
		} catch(MongoException $e) {
			$this->log_db_error("MAPREDUCE",$this->MyTable,$e->getMessage(),$e->getCode(),$this->MyTable);
			return null;
		}
		
		$this->performance($start,"MAPREDUCE");
		
		return $result;
	}


	
/* LISTINDEXES ============================================================================
	Shows the indexes for this collection
*/
	public function listIndexes() {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		try {
			$result = $collection->getIndexInfo();
		
		} catch(MongoException $e) {
			$this->log_db_error("listIndexes",$this->MyTable,$e->getMessage(),$e->getCode());
			return null;
		} 
		
		$this->performance($start,"listIndexes");
	
		return $result;
	}
	
/* LISTUSERS ============================================================================
	Shows the users for this db

	TODO: add user by   $collection->insert(array('user' => $username, 'pwd' => md5($username . ":mongo:" . $password), 'readOnly' => false));

*/
	public function listUsers() {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection("system.users");
		
		try {
			$rows = array();
			$cursor = $collection->find(array(),array("user"=>1,"readOnly"=>1));
			while($row = $cursor->getNext()) {
				$rows[] = $row;
			}
		
		} catch(MongoException $e) {
			$this->log_db_error("listUsers",$this->MyTable,$e->getMessage(),$e->getCode());
			return null;
		} 
		
		$this->performance($start,"listUsers");
	
		return $rows;
	}
	
	

/* ensureIndex =========================================================================
	Creates an index on the collection
	$index = array of keys with 1/-1 for direction Ex: array("c"=>1,"u"=>1,"k"=>1)
*/
	public function ensureIndex($index,$name=null,$unique=false,$background=true,$dropdups=false,$sparse=false) {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		if(empty($index)) return null;
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$data = array("background"=>$background,"safe"=>true,"w"=>1, "unique" => $unique, "dropDups"=>$dropdups, "sparse"=>$sparse);
		if(!empty($name)) $data['name'] = $name;

		try {
			$success = $collection->ensureIndex($index, $data);

		} catch(MongoException $e) {
			$this->log_db_error("ensureIndex",$this->MyTable,$e->getMessage(),$e->getCode());
			return false;
		}
		
		if(empty($success['ok'])) $success = false;
		else $success = true;

		$this->performance($start,"ensureIndex");
		
		return $success;
	}

/* deleteIndex =========================================================================
	Deletes the named index
*/
	public function deleteIndex($index) {
		$start = microtime(true);
		
		if($this->db==null) return null; //we never got connected
		if(empty($index)) return null;
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		try {
			$success = $this->db->command(array("deleteIndexes" => $collection->getName(), "index" => $index));
		} catch(MongoException $e) {
			$this->log_db_error("deleteIndex",$this->MyTable,$e->getMessage(),$e->getCode());
			return false;
		}
		if(empty($success['ok'])) $success = false;
		else $success = true;

		$this->performance($start,"deleteIndex");
		
		return $success;
	}
	
/* DOCSIZE =========================================================================
	Returns the size of the document

	//http://stackoverflow.com/questions/10406975/mongodb-php-how-do-you-query-object-sizes

*/
	// function docSize($id) {
	// 	$start = microtime(true);
		

	// 	$response = $this->db->execute("function() { return 'Hello, world!'; }");
	// 	print_r($response);

	// 	return;


	// 	if($this->db==null) return null; //we never got connected
		
	// 	// $collection = $this->db->selectCollection($this->MyTable);
	// 	$collection = $this->MyTable;

	// 	$query = json_encode(array("_id"=>$id));
	// 	$code = "function(){
	// 				return Object.bsonsize(db.{$collection}.findOne({$query})) 
	// 			}";
	// 		echo $this->MyDB;

	// 	echo $code;

	// 	try {
	// 		$result = $this->db->execute($code);
	// 	} catch(MongoException $e) {
	// 		$this->log_db_error("docSize",$this->MyTable,$e->getMessage(),$e->getCode());
	// 		return null;
	// 	}
		
	// 	$this->performance($start,"docSize");
		
	// 	return $result;
	// }


/* DEBUG ============================================================================

*/	
	// @codeCoverageIgnoreStart
	public function debug() {
		echo "CONNECTING TO : ".$this->MyDB."<br />";
		echo "Driver Version ".Mongo::VERSION." <a href='https://jira.mongodb.org/browse/PHP'>Docs</a><br>";
		echo "mongo.ping_interval:".ini_get("mongo.ping_interval")."<br>";
		
		echo "<br />CONNECTIONS<br />";
		if($this->m == null) {
			echo "No connection to Mongo";
			return;
		}
		
		$connections = $this->m->getConnections();		
		foreach($connections as $con) {
			echo $con['hash'];
			echo " last_ismaster ".$con['connection']['last_ismaster'];
			echo " connection_type_desc ".$con['connection']['connection_type_desc'];
			echo "<br />";
		}

		echo "<br />HOSTS<br />";
		$hosts = $this->m->getHosts();
		foreach($hosts as $name => $data) {
			if(stristr($name,$this->MyDB)) {
				if($data['state']==1) $data['state'] = "PRIMARY "; 
				else if($data['state']==2) $data['state'] = "SECONDARY "; 
				else $data['state'] = "<b style='color:red'>OTHER</b> ";
				echo $data['state'];
	
				if($data['health']==1) $data['health'] = "<span style='color:green'>online</span> "; 
				else $data['health'] = "<b style='color:red'>OFFLINE</b> ";
				echo $data['health'];
			
				echo $data['host']." ";
			
				if(!empty($data['ping'])) echo "ping:".$data['ping'];
				if(!empty($data['lastPing'])) echo " lastping:".(time()-$data['lastPing'])."s";
				echo "<br />";
			}
		}
		
		echo "<br />READPREF<br />";
		$pref = $this->db->getReadPreference();		
		print_r($pref);
		echo "<br />";

		$this->changeTable("healthcheck");	

		//do a test delete
		echo "<br />DELETE TEST<br />";
		$result = $this->remove(array("id"=>2),true,10000);
		if($result===null) echo "<b style='color:red'>Delete Failed</b>"; else echo "<span style='color:green'>Delete Succeed</span>";
		echo "<br />";
		
		//do a test write
		echo "<br />WRITE TEST<br />";
		$result = $this->insert(array("id"=>2, "time"=>date('r'),"what"=>"Mongo Debug write"),true,10000);
		if($result==null) echo "<b style='color:red'>Write Failed</b>"; else echo "<span style='color:green'>Write Succeed</span>";	
		echo "<br />";
	
		//do a test update
		echo "<br />UPDATE TEST<br />";
		$result = $this->update(array("id"=>2),array('$set'=>array("what"=>"Mongo Debug update")),true,false,10000);
		if($result==null) echo "<b style='color:red'>Update Failed</b>"; else echo "<span style='color:green'>Update Succeed</span>";	
		echo "<br />";
				
		//do a test read
		echo "<br />READ TEST<br />";
		$cursor = $this->find(array("id"=>2),'','',0,10000);
		$result = $this->getNext($cursor);
		if($result==-1) echo "<b style='color:red'>Read Failed</b>"; else echo "<span style='color:green'>Read Succeed</span>";
		echo "<br />";
		
		echo "<br />TABLES<br />";		
		try {
			$names = $this->db->getCollectionNames();
			print_r($names);
		} catch(MongoException $e) {
			$this->log_db_error("getCollectionNames",'',$e->getMessage(),$e->getCode());
		}
		
		echo "<br /><br />";
	
		if($this->db) {
			try {
				$result = $this->db->command(array('buildinfo'=>true)); 
				echo "Server Version ".$result['version']."<br />";
			} catch(MongoException $e) {
				echo "Server Version Unknown<br />";
				$this->log_db_error("BuildInfo",'',$e->getMessage(),$e->getCode());
			}
		}
		
		echo "mongo.ping_interval:".ini_get("mongo.ping_interval")."<br>";

		echo "Connection Type = ";
		//0 means as specified, 1 means with replica set disabled, 2 means second of pair used
		if($this->connectionType==0) echo "<b style='color:green'>As specified</b>";
		else if($this->connectionType==1) echo "<b style='color:red'>Replica set disabled</b>";
		else if($this->connectionType==2) echo "<b style='color:red'>First of pair used</b>";
		else if($this->connectionType==3) echo "<b style='color:red'>Second of pair used</b>";
		else if($this->connectionType==4) echo "<b style='color:red'>Failed over during query</b>";
	}
	// @codeCoverageIgnoreEnd

/* HEALTH ============================================================================
	returns 0 if no error. Returns integer error code if error
*/	
	public function health($checkReplicaSet=true,$collection=null) {
		if(!$this->m) return 1;
		if(empty($collection)) return 11;
		try {
			$hosts = $this->m->getHosts();
			$foundPrimary = false;
			foreach($hosts as $name => $data) {
				if(stristr($name,$this->MyDB)) {
					if($data['state']==1 && !$foundPrimary) $foundPrimary = true;
					if($data['health']!=1) return 2;
				}
			}
			if(!$foundPrimary && $checkReplicaSet) return 3;
	
			$this->changeTable($collection);	
		
			//do a test delete
			$result = $this->remove(array("id"=>1),true,10000);
			if($result===null) return 4;
		
			//do a test write
			$result = $this->insert(array("id"=>1, "time"=>date('r'),"what"=>"Mongo health write"),true,10000);
			if($result==null) return 5;
		
			//do a test update
			$result = $this->update(array("id"=>1),array('$set'=>array("what"=>"Mongo health update")),true,false,10000);
			if($result==null) return 9;
			
			//do a test read
			$cursor = $this->find(array("id"=>1),'','',10,10000);
			$result = $this->getNext($cursor);
			if($result==-1) return 6;
		
			//0 means as specified, 1 means with replica set disabled, 2 means second of pair used
			if($this->connectionType!=0 && $this->replicaSet) return 7;
			else if($this->connectionType!=0) return 10;
		} catch(MongoException $e) {
			return 8;
		}
		return 0;
	}
	

	/* CLIENTINFO ============================================================================
		Returns diagnostic information about a php mongo driver
	*/	
	public function client_info() {
		if(!class_exists("Mongo")) return 0;

		$ret = array();
		$ret['driver'] = Mongo::VERSION;
		$ret['ping'] = ini_get("mongo.ping_interval");
		$ret['ismaster'] = ini_get("mongo.is_master_interval");
		$ret['long'] = ini_get("mongo.long_as_object");
		$ret['native'] = ini_get("mongo.native_long");

		return $ret;
	}
	
	/* CONNECTIONS ============================================================================
		Returns a list of the open connections
	*/	
	public function connections() {
		$connections = null;

		if($this->m) {
			try {
				$connections = $this->m->getConnections();
			} catch(MongoException $e) {
				$connections = null;
				$this->log_db_error("connections",'',$e->getMessage(),$e->getCode());
			}
		}
		//$hosts = $m->getHosts();

		return $connections;
	}
	/* DBINFO ============================================================================
		Returns diagnostic information about a database
	*/	
	public function db_info() {
		$buildinfo = null;
		$dbStats = null;
		$serverStatus = null;
		$hostinfo = null;
		$cmdInfo = null;
		$connPoolStats = null;
		$profileLevel = null;

		if($this->db) {
			try {
				$buildinfo = $this->db->command(array('buildinfo'=>1));
				$dbStats = $this->db->command(array('dbStats'=>1));
				//$serverStatus = $this->db->command(array('serverStatus'=>1)); //Requires admin perms
				//$hostinfo = $this->db->command(array('hostInfo'=>1));  //Requires admin perms
				//$cmdInfo = $this->db->command(array('getCmdLineOpts'=>1));  //Requires admin perms
				//$connPoolStats = $this->db->command(array('connPoolStats'=>1));  //Requires admin perms
				$profileLevel = $this->db->getProfilingLevel();			
			} catch(MongoException $e) {
				$result = null;
				$this->log_db_error("db_info",'',$e->getMessage(),$e->getCode());
			}
		}
		return array($buildinfo,$dbStats,$serverStatus,$hostinfo,$cmdInfo,$connPoolStats,$profileLevel);
	}
	
/* PERFORMANCE ===========================================================================
	if on beta, prints out some timing information
*/
	private function performance($start,$type,$query=null,$success=true) {
		global $beta,$mongodb_querytime,$mongodb_calls,$mongodb_callLog;
		
		if(!empty($beta)) {
			$elapsed = (microtime(true)-$start)*1000; //ms
			$mongodb_querytime+=$elapsed;

			$mongodb_calls++;

			if($mongodb_callLog!==false) {
				$object = bin2hex(serialize($query));
				if($success) $mongodb_callLog[] = $type." : ".$elapsed." S #".$object;
				else $mongodb_callLog[] = $type." : ".$elapsed." F #".$object;
			}
			//echo $elapsed."ms $type $this->MyTable<br />";
		
			/*
			if(empty($_SERVER["REMOTE_ADDR"])) $ip = "";
			else $ip=dbString($_SERVER["REMOTE_ADDR"]);
			
			$url = $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
			$query = $_SERVER['REQUEST_METHOD']." ".$_SERVER['QUERY_STRING'];
			
			
			$bytes = memory_get_peak_usage(); //http://www.ibm.com/developerworks/opensource/library/os-php-v521/
			*/
		}
	}

	// @codeCoverageIgnoreStart
	public function print_performance() {
		global $mongodb_querytime,$mongodb_calls,$mongodb_callLog;
		echo "<br />db calls:".$mongodb_calls.". querytime:".floor($mongodb_querytime)."ms.";
		if($mongodb_callLog!==false) {
			echo "<pre>";
			foreach($mongodb_callLog as $call) {
				$pieces = explode("#", $call);
				echo $pieces[0];
				echo pack("H*" , $pieces[1]);
				echo "<br/>";
			}
			echo "</pre>";
		}
	}
	// @codeCoverageIgnoreEnd

/* FIELDSTRTOARRAY =======================================================================
	private function that turns a comma separated list of fields into the array needed by find() or findone()
*/	
	private function fieldStrToArray($fields) {
		$out = array();
		
		if(!empty($fields)) {
			$fields = explode(",",$fields);
			foreach($fields as $f=>$k) {
				$k = trim($k);
				if(!empty($k)) $out[$k] = true; //array('fieldname' => true)
			}
		}
		return $out;
	}
	
/* SERIALIZEQUERY =======================================================================
	Used by error logger, takes an object/array and converts to a string. Attempts to use
	json_encode first, if that fails, uses php serialize.
*/	
	public function serializeQuery($object) {
		try { 
			$serialized = json_encode($object); //this will generate errors in the log file, but they are ok because we are handling them
		} catch(Exception $e) { //necessary because $object may have binary data that json encode can't understand
			$serialized = serialize($object); 
		} 
		$serialized = str_replace("\n", " ", $serialized);
		return $serialized;
	}

/* LOG_DB_MARK ==========================================================================
	Puts a marker in the log file
*/
	// @codeCoverageIgnoreStart
	private function log_db_mark($mark) {
		global $mongoErrorLog, $verbose;
		if($mongoErrorLog && $verbose) error_log($mark."\n",3,$mongoErrorLog); 
	}
	// @codeCoverageIgnoreEnd
	

/* LOG_DB_ERROR ==========================================================================
	logs the error to a file and prints to the screen if on beta
*/
	// @codeCoverageIgnoreStart
	private function log_db_error($type,$db,$error,$code,$info='') {
		global $_SERVER, $PHP_SELF,$beta,$mongoErrorLog;
		
		if(stristr($error,"Operation now in progress")) $error = "Timeout ($error)"; //"Operation now in progress" is code for timeout
		
		$this->lastErr = $code;
		$this->lastErrMsg = $error;

		$self = $PHP_SELF ? $PHP_SELF : $_SERVER["PHP_SELF"];
		if(empty($self)) $self = $_SERVER['SCRIPT_NAME'];
		if(empty($self)) $self = $_SERVER['REQUEST_URI'];

		$message = date('r')." MONGO ".Mongo::VERSION." ".$type." ERROR ".$code." \"".$error."\" AT '".$self."' ".$db." ".$info."\n";
		//if(!empty($beta)) echo nl2br($message);
		
		error_log($message);
		if($mongoErrorLog) error_log($message,3,$mongoErrorLog);
	}
	// @codeCoverageIgnoreEnd
	
/* notifyAboutErrors ==========================================================================
	Checks if there are too many errors in the log and sends a warning if there is
*/
	// @codeCoverageIgnoreStart
	public function notifyAboutErrors($useAWS=false,$subject="") {
		global $mongoErrorLog;

		$res = $this->errorFrequency($mongoErrorLog);

		//give error if more than 5 errors or last 10 errors happened in less than 1 hr
		if($res[0]>10 && $res[1]<3600) {
			$message = $res[0]." errors total.";
			if($res[1]<3600) $message .= "At least 10 in the last hour.";
			
			//TODO: Notify someone
			
			return true;
		}
		return false;
	}
	// @codeCoverageIgnoreEnd

/* extractTime ==========================================================================
	Helper function for error checker. Extracts a time from the error message
*/
	// @codeCoverageIgnoreStart
	private function extractTime($error) {
		$position = stripos($error,"MONGO");
		$time = strtotime(substr($error,0,$position));
		return $time;
	}
	// @codeCoverageIgnoreEnd

/* errorFrequency ==========================================================================
	Given an error file path, it opens the file, counts the errors and how long ago the 10th
	newest error is.
*/
	// @codeCoverageIgnoreStart
	private function errorFrequency($file) {
		$lines = file($file); 
		$count = count($lines);
		//print_r($lines);
	
		$older = $lines[$count-10];
		if($older) $older = $this->extractTime($older);

		$difference = time()-$older;
		return array($count,$difference);
	}
	// @codeCoverageIgnoreEnd
}

?>