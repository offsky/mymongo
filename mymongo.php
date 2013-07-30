<?php
/*
	Simple wrapper class for PHP's Mongo drivers that abstracts the connection and error handling
	
USAGE
	
	Somewhere set this environment variable
	$MONGO['myServer'] = array("host"=>"my1.host.com:10009,my2.host.com:10009", "user"=>"myUsername", "pass"=>"myPassword", "db"=>"databaseName", "replicaSet"=>"replicaSetName");

	When you want to do something
	$m = new mymongo("myServer","collectionName");
	$obj = array( "date" => date('r'));
	$m->insert($obj);

LICENSE	

	Copyright (c) 2012-2013 Toodledo.com

	Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

class mymongo {
	public $connected = false; //true when we are connected to a server
	private $MyHost = null; //the server that I am connecting to in the cloud
	private $MyHosts = null; //Generated from $MyHosts, this is an array of the individual hosts
	private $MyUser = null; //my username for the database on that server (not the same as my username for the cloud service)
	private $MyPass = null; //my password for the database on that server
	private $MyDB = null; //the name of the database in the cloud
	private $MyTable = null; //the name of the collection that we are working on
	private $replicaSet = null;
	
	public $m = null; //the internal reference to the mongo driver
	public $db = null; //the internal reference to the collection that we are working on
	public $lastErr = null;
	

/* MYMONGO ===========================================================================
	This will create a connection to the specified database and cache the collection that I want to work on next
	The $connected instance variable will indicate success/fail. I will need to check this if I care about presenting
	an error to the user if the database is unavailable. Otherwise, all future db calls will return instantly with failure codes
*/
	public function mymongo($server,$table) {
		global $MONGO;
				
		$this->MyTable = $table;
		
		if(empty($MONGO[$server])) return;
		
		$this->connected = $this->init($MONGO[$server]['host'], $MONGO[$server]['user'], $MONGO[$server]['pass'], $MONGO[$server]['db'],$MONGO[$server]['replicaSet']);
	}
	
/* INIT ==================================================================================
	Opens a connection to the database.  The underlying driver tries to use a persistent connection
	if available.  If not, it will make a new connection which has increased latency.  If we had
	to make a new connection, the first operation that we make after connecting will also be a little slower.
	Returns true/false on success/fail.
*/
	public function init($host, $user, $pass, $database, $replicaSet=null) {		
		$this->MyHost = $host;
		$this->MyHosts = explode(",", $this->MyHost);
		$this->MyUser = $user;
		$this->MyPass = $pass;
		$this->MyDB = $database;
		$this->replicaSet = $replicaSet;
		
		return $this->connect();
	}

/* CONNECT ==================================================================================
	finishes the job of init
*/
	private function connect() {
				
		$flags = array("connectTimeoutMS" => 500);
		if(!empty($this->replicaSet)) $flags['replicaSet'] = $this->replicaSet;

		//try to connect to the db. Keep a low timeout to prevent stalled DB crashing apache with hung PHP jobs
		$success = $this->connectClient();
		
		if(!$success && !empty($this->replicaSet)) {	//try again without the replica set.  It will connect randomly to either server, so 50% of the time writes will fail because it is on the secondary
			$this->replicaSet = "";
			$this->connectionType = 1;
			$success = $this->connectClient();
		}
		
		if(!$success && count($this->MyHosts)) { //try again with first of pair only
			$this->MyHost = array_shift($this->MyHosts);
		
			$this->connectionType = 2;
			$success = $this->connectClient();
		}
		
		if(!$success && count($this->MyHosts)) {	//try again with first of pair only
			$this->MyHost = array_shift($this->MyHosts);
	
			$this->connectionType = 3;
			$success = $this->connectClient();
		}

		if(empty($this->m)) return false;
		
		//try to select the db		
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
		
		$this->connected = true;
		return true;
	}

/* CONNECTCLIENT ==================================================================================
	Connects the MongoClient using the class settings.
*/
	private function connectClient() {		
		if(empty($this->MyHost)) return false;
		
		$flags = array("connectTimeoutMS" => 500);
		if(!empty($this->replicaSet)) $flags['replicaSet'] = $this->replicaSet;
		
		try {			
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
		if($bad==$this->MyHost) {
			if(count($this->MyHosts)) $this->MyHost = array_shift($this->MyHosts);
			else return false;
		}

		$this->connectionType = 4;
		return $this->connect(); //reconnect
	}
	
		
/* DISCONNECT ============================================================================
	Prevents further calls from working
*/
	public function disconnect() {
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

/* INSERT ================================================================================
	Inserts an object into a table. 
	Since object is passed by reference, it will return with the _id field set.
	If $safe is false, it will return immediately and not wait for a success/fail response
	returns null on error which doesn't necessarily mean that it failed. It could have just timed out waiting.
*/
	public function insert($object, $safe=true, $timeout=500) {
				
		if($this->db==null) return null; //we never got connected
		
		// select a collection (analogous to a relational database's table)
		$collection = $this->db->selectCollection($this->MyTable);

		$flags = array("timeout" => intval($timeout));
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->insert($object, $flags);
		} catch(MongoException $e) {
			//if it is a timeout, it is possible that the insert still worked
			$this->log_db_error("INSERT1",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($object));
			
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
			return null;
		}
		
		//$object will now contain a _id field with the new mongo id number
		return $result;
	}
	
/* REMOVE ================================================================================
	Removes records from table based on criteria. 
	If safe is true returns number of records deleted, which can be zero. If safe is false, returns 1 always
*/
	public function remove($criteria,$safe=true,$timeout=500) {
				
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$flags = array("timeout" => intval($timeout));
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->remove($criteria, $flags); 
		} catch(MongoCursorException $e) {
			$this->log_db_error("REMOVE1",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($criteria));
			
			if($e->getCode()==10056 || $e->getCode()==16) { //not master, attempt to connect to the other one
				$success = $this->reconnect($e->getMessage());
				if(!$success) return null;
				return $this->remove($criteria,$safe,$timeout);
			}
			
			return null;
		} catch(MongoCursorTimeoutException $e) {
			//if it is a timeout, it is likely that the insert still worked
			$this->log_db_error("REMOVE2",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($criteria));
			return null;
		}
		
		if($safe && $result['ok']!=1) {
			$this->log_db_error("REMOVE3",$this->MyTable,$result['err']." ".$result['errmsg'],$result['code'],json_encode($criteria));
			return null;
		}
				
		if($safe && $result['ok']) return $result['n'];
		return $result;
	}

/* UPDATE ================================================================================
	Sets new data for criteria on active table
	If upsert is true, it will create the record if it does not exist
	returns null on error which doesn't necessarily mean that it failed. It could have just timed out waiting.
	if safe is true, on success returns the number of records altered. If safe is false, returns true always
*/
	public function update($criteria,$newdata,$safe=true,$upsert=false,$timeout=500) {
				
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$flags = array("multiple" => true, "timeout" => intval($timeout), "upsert" => $upsert);
		if($safe) $flags['w'] = 1; else $flags['w'] = 0;

		try {
			$result = $collection->update($criteria, $newdata, $flags);
		} catch(MongoException $e) {
			$this->log_db_error("UPDATE1",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery(array($criteria,$newdata)));
			
			if($e->getCode()==10054 || $e->getCode()==16) { //not master, attempt to connect to the other one
				$success = $this->reconnect($e->getMessage());
				if(!$success) return null;
				return $this->update($criteria,$newdata,$safe,$upsert,$timeout);
			}
			
			return null;
		}
		
		if($safe && $result['ok']!=1) {
			$this->log_db_error("UPDATE2",$this->MyTable,$result['err']." ".$result['errmsg'],$result['code'],$this->serializeQuery(array($criteria,$newdata)));
			return null;
		}
				
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
	public function find($query,$fields = "",$sort="",$limit=0,$timeout=500) {
				
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$fields = $this->fieldStrToArray($fields);
		
		try {
			$cursor = $collection->find($query,$fields)->timeout($timeout);
			if(!empty($sort)) $cursor->sort($sort);
			if(!empty($limit)) $cursor->limit($limit);
		
		} catch(MongoException $e) {
			$this->log_db_error("FIND",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($query));
			return null;
		}
				
		return $cursor;
	}

/* COUNT ==================================================================================
	Counts the number of records matching the query. Returns an integer
	Can pass a limit of the number to count before stopping
*/
	public function count($query, $limit=0) {
		$limit = intval($limit);
		
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		try {
			$count = $collection->count($query,$limit);
		
		} catch(MongoException $e) {
			$this->log_db_error("COUNT",$this->MyTable,$e->getMessage(),$e->getCode(),$this->serializeQuery($query));
			return null;
		}
				
		return $count;
	}
		
	/* GETNEXT ==================================================================================
	Returns the next row on a cursor which may be null if there is no next row. This is a simple wrapper to catch exceptions.
	returns -1 on exception
*/
	public function getNext($cursor) {
		if(empty($cursor)) return -1;
		try {
			$row = $cursor->getNext();
		} catch(MongoCursorException $e) {
			$info = $this->serializeQuery($cursor->info());		
			$this->log_db_error("GETNEXT1",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return -1;
		} catch(MongoException $e) {
			$info = $this->serializeQuery($cursor->info());
			$this->log_db_error("GETNEXT2",$this->MyTable,$e->getMessage(),$e->getCode(),$info);
			return -1;
		}
		return $row;
	}
	
/* FINDONE ===============================================================================
	Runs a query on a table and returns one row with the fields(comma separated string) specified or all fields if not set.
	It is not possible to set a timeout, so it could take forever. Use with caution
*/
	public function findOne($query,$fields = "") {
				
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		$fields = $this->fieldStrToArray($fields);
		
		try {
			$result = $collection->findOne($query,$fields);
		} catch(MongoException $e) {
			$this->log_db_error("FINDONE",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($query));
			return null;
		}
				
		return $result;
	}
	
/* DISTINCT ==================================================================================
	Finds distinct values for a field returns an array. 
	NOTE: NOT THOROUGHLY TESTED
*/
	public function distinct($query) {
				
		if($this->db==null) return null; //we never got connected
				
		$fields = $this->fieldStrToArray($fields);
		
		try {
			$cursor = $this->db->command(array("distinct" => $this->MyTable, "key" => $query));
		
		} catch(MongoException $e) {
			$this->log_db_error("DISTINCT",$this->MyTable,$e->getMessage(),$e->getCode());
			return null;
		}
				
		return $cursor;
	}

/* AGGREGATE ==================================================================================
	Runs the aggregation commands in $ops.
	
	http://docs.mongodb.org/manual/reference/sql-aggregation-comparison/
	http://php.net/manual/en/mongocollection.aggregate.php
	
	NOTE: NOT THOROUGHLY TESTED
*/
	public function aggregate($ops) {
				
		if($this->db==null) return null; //we never got connected
				
		try {
			$collection = $this->db->selectCollection($this->MyTable);
			$result = $collection->aggregate($ops);
		} catch(MongoException $e) {
			$this->log_db_error("AGGREGATE",$this->MyTable,$e->getMessage(),$e->getCode(),json_encode($ops));
			return null;
		}
				
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
						
			$result = $this->db->command($command); 
	
		} catch(MongoException $e) {
			$this->log_db_error("MAPREDUCE",$this->MyTable,$e->getMessage(),$e->getCode());
			return null;
		}
				
		return $result;
	}

/* ensureIndex =========================================================================
	Creates an index on the collection
*/
	public function ensureIndex($index,$unique=false) {
				
		if($this->db==null) return null; //we never got connected
		
		$collection = $this->db->selectCollection($this->MyTable);
		
		try {
			$success = $collection->ensureIndex($index, array("background"=>true,"safe"=>true,"unique" => $unique));
		
		} catch(MongoException $e) {
			$this->log_db_error("ensureIndex",$this->MyTable,$e->getMessage(),$e->getCode());
			return false;
		} 
				
		return true;
	}


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
			$serialized = json_encode($object); 
		} catch(Exception $e) { //necessary because $object may have binary data that json encode can't understand
			$serialized = serialize($object); 
		} 
		return $serialized;
	}

/* LOG_DB_ERROR ==========================================================================
	logs the error to a file
*/
	// @codeCoverageIgnoreStart
	private function log_db_error($type,$db,$error,$code,$info='') {
		global $PHP_SELF;
		
		if(stristr($error,"Operation now in progress")) $error = "Timeout ($error)"; //"Operation now in progress" is code for timeout
		
		$this->lastErr = $code;

		$message = date('r')." MONGO ".Mongo::VERSION." ".$type." ERROR ".$code." \"".$error."\" AT '".$PHP_SELF."' ".$db." ".$info."\n";
		error_log($message);
	}
	// @codeCoverageIgnoreEnd

}
?>