'use strict';


/* ==================================================================

TODO: may need to encodeURIComponent the db/collection names
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin.mDatabase', []).factory('phpMongoAdmin.mDatabase', ['$http', '$rootScope', function($http, $rootScope) {

	$rootScope.client = {}; //The last copy of the client info
	$rootScope.databases = null; //The last copy of the list of databases and info
	$rootScope.allCollections = {}; //The collections for the various databases
	$rootScope.allIndexes = []; //The collections for the various databases
	$rootScope.documents = []; // the array of documents that we are currently viewing
	$rootScope.connections = []; // the array of open connections
	$rootScope.doc = {};
	$rootScope.explain = {};

	
	var apiPath = $$config.apiPath;

	//==================================================================
	// Initialize the database array from the server
	function init() {
		if($rootScope.databases && $rootScope.databases.length) return; //already initialized

		console.log("mDatabase INIT");

		var promise = getDatabases(true);
		promise.then(function() {
			getDatabases(false);
			getConnections();
		})

		//get the client info
		$http.get(apiPath + '/client.php')
			.success(function(data) {
				$rootScope.client = data;
				console.log(data);
				$rootScope.$broadcast('update_databases');
			})
			.error(function(data) {
				console.log("ERROR FETCHING client",data);
			});
	};

	//==================================================================
	// Gets info about the open connections
	function getConnections() {
		//gets the first db name because connections needs one open connection to be able to get the rest
		var first = Object.keys($rootScope.databases)[0];

		$http.get(apiPath + '/connections.php?db='+first)
			.success(function(data) {
				$rootScope.connections = data;
				console.log(data);
				$rootScope.$broadcast('update_databases');
			})
			.error(function(data) {
				console.log("ERROR FETCHING connections",data);
			});

	}
	
	//==================================================================
	// Gets all the databases. If fast=true it doesn't do any verification
	// If fast is false it will do a healthceck and gather statistics
	function getDatabases(fast) {
		
		var query = "";
		if(fast!=undefined && fast==true) query = "?fast=1";

		var promise = $http.get(apiPath + '/databases.php'+query);
		
		promise.success(function(data) {
			$rootScope.databases = data;
			console.log("getDatabases callback",fast);
			// console.table(data);
			$rootScope.$broadcast('update_databases');
		});
		promise.error(function(data) {
			console.log("ERROR getDatabases",fast,data);
		});
		return promise;
	}
	
	//==================================================================
	// Gets one db by name 
	function get(dbname) {
		console.log("get db",dbname,$rootScope.databases);
		return $rootScope.databases[dbname];
	};

	//==================================================================
	// Gets collections for this db 
	function getCollections(dbname) {
		console.log("getCollections",dbname,$rootScope.databases);

		if($rootScope.allCollections[dbname]!=undefined) return $rootScope.allCollections[dbname];

		$http.get(apiPath + '/collections.php?db='+dbname)
			.success(function(data) {
				//sort data
				data.sort(function(a,b) {
					if(a.name.toLowerCase()==b.name.toLowerCase()) return 0;
					if(a.name.toLowerCase()<b.name.toLowerCase()) return -1;
					return 1;
				})

				$rootScope.allCollections[dbname] = data;
				$rootScope.allIndexes[dbname] = {};
				//console.log("Collections Got");
				//console.log(data);
				$rootScope.$broadcast('update_collections');
			})
			.error(function(data) {
				console.log("ERROR FETCHING collections",data);
			});
	};

	//==================================================================
	// Gets indexes for this db and collection
	function getIndexes(dbname,collection) {
		console.log("getIndexes",dbname,collection);

		if($rootScope.allIndexes[dbname]!=undefined && $rootScope.allIndexes[dbname][collection]!=undefined) return $rootScope.allIndexes[dbname][collection];

		$http.get(apiPath + '/indexes.php?db='+dbname+'&col='+collection)
			.success(function(data) {
				if($rootScope.allIndexes[dbname]==undefined) $rootScope.allIndexes[dbname]={};
				$rootScope.allIndexes[dbname][collection] = data;
				console.log("Indexes Got");
				console.log(data);
				$rootScope.$broadcast('update_indexes');
			})
			.error(function(data) {
				console.log("ERROR FETCHING indexes",data);
			});
	};

	//==================================================================
	// Adds one index
	function addIndex(dbname,collection,name,index) {
		if(!index) return;
		
		console.log("addIndex",dbname,collection,name,index);

		$http.post(apiPath + '/index_add.php','db='+dbname+'&col='+collection+'&name='+name+'&index='+index, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				console.log("added indexes",data);

				$rootScope.allIndexes[dbname]=undefined;
				getIndexes(dbname,collection);
			})
			.error(function(data) {
				console.log("ERROR FETCHING indexes",data);
			});
	};

	//==================================================================
	// Deletes one index
	function deleteIndex(dbname,collection,index) {
		if(!index) return;

		console.log("deleteIndexes",dbname,collection,index);

		$http.post(apiPath + '/index_remove.php','db='+dbname+'&col='+collection+'&index='+index, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				console.log("deleted indexes",data);

				$rootScope.allIndexes[dbname]=undefined;
				getIndexes(dbname,collection);
			})
			.error(function(data) {
				console.log("ERROR deleting indexes",data);
			});
	};

	//==================================================================
	// Gets documents for this db and collection
	function getDocuments(dbname,collection,query,fields,sort,page,num) {
		page--; //1 indexed to 0 indexed conversion

		query = "{"+query+"}";		
		fields = "{"+fields+"}";		
		sort = "{"+sort+"}";		

		console.log("getDocuments",dbname,collection,query,fields,sort,page,num);

		$rootScope.documents = null;
		
		$http.get(apiPath + '/documents.php?db='+dbname+'&col='+collection+'&query='+query+'&fields='+fields+'&sort='+sort+'&page='+page+'&num='+num)
			.success(function(data) {
				$rootScope.documents = data.docs;
				$rootScope.explain = data.explain;
				console.log("docs Got");
				console.log(data);
				$rootScope.$broadcast('update_docs');
			})
			.error(function(data) {
				console.log("ERROR FETCHING docs",data);
			});
	};

	//==================================================================
	// Gets one document
	function getDocument(dbname,collection,id) {
		
		console.log("getDocument",dbname,collection,id);

		$rootScope.doc = {};
		
		$http.get(apiPath + '/document.php?db='+dbname+'&col='+collection+'&id='+id)
			.success(function(data) {
				$rootScope.doc = data;
				if($rootScope.doc=="null" || $rootScope.doc=="") $rootScope.doc = null;
				console.log("doc Got");
				console.log(data);
				$rootScope.$broadcast('update_doc');
			})
			.error(function(data) {
				console.log("ERROR FETCHING doc",data);
			});
	};

	//==================================================================
	// Delete document
	function deleteDocument(dbname,collection,id) {
		console.log("getDocument",dbname,collection,id);
		
		var promise = $http.post(apiPath + '/doc_remove.php','db='+dbname+'&col='+collection+'&id='+id, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}});
		
		promise.success(function(data) {
			$rootScope.doc = {};
			console.log("deleted document",data);
		});
		promise.error(function(data) {
			console.log("ERROR deleting document",data);
		});
		return promise;
	}

	return {
		init: init, get: get, getCollections:getCollections, getIndexes:getIndexes, deleteIndex:deleteIndex, addIndex:addIndex, getDocuments:getDocuments, getDocument:getDocument, deleteDocument:deleteDocument
	};
}]); //end factory and module
