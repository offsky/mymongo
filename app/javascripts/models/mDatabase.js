'use strict';
/* ==================================================================
The main model for retreiving information from the server and feeding
it to the controllers.

TODO: may need to encodeURIComponent the db/collection names
TODO: could pull out the collections and documents parts into separate models

-----------------------------------------------------------------*/

angular.module('phpMongoAdmin.mDatabase', []).factory('phpMongoAdmin.mDatabase', ['$http', '$rootScope', 'Store', '$q', function($http, $rootScope, cache, $q) {

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

		var promise = getDatabases();
		promise.then(function() {
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
				if($rootScope.connections=="null" || $rootScope.connections==null) $rootScope.connections=undefined;
				//console.log(data);
				$rootScope.$broadcast('update_databases');
			})
			.error(function(data) {
				console.log("ERROR FETCHING connections",data);
			});
	}
	
	//==================================================================
	// Gets all the databases quickly. 
		function getDatabases() {
		
		var promise = $http.get(apiPath + '/databases.php?fast=1');
		
		promise.success(function(data) {
			$rootScope.databases = data;
			//console.log("getDatabases callback");
			// console.table(data);
			$rootScope.$broadcast('update_databases');
		});
		promise.error(function(data) {
			console.log("ERROR getDatabases",data);
		});
		return promise;
	}
	
	//==================================================================
	// Does the healthcheck on all the databases
	function doHealthcheck() {
		angular.forEach($rootScope.databases, function(values, name){
			getHealthcheck(name,false);
		});
	}

	//==================================================================
	// Does a healthcheck on the databases and returns important stats
	function getHealthcheck(name,force) {
		// console.log("health",name);
		var now = new Date().getTime();

		//determine if the last healthcheck is recent enough
		var doCheck = true;
		if(force==false && $rootScope.databases[name].health!==undefined) {
			if($rootScope.databases[name].lasthealth>now-(60*1000)) {
				doCheck = false;
			}
		}

		if(doCheck) {
			var promise = $http.get(apiPath + '/databases.php?one='+name);

			promise.success(function(data) {
				$rootScope.databases[name] = data[name];
				$rootScope.databases[name].lasthealth = now;
				
				//console.log("getHealthcheck callback");
				// console.table(data);
				$rootScope.$broadcast('update_databases');
			});
			promise.error(function(data) {
				//console.log("ERROR getHealthcheck",data);
			});
		} else {
			var deferred = $q.defer();
			var promise = deferred.promise;
			deferred.resolve();
		}
		
		return promise;
	}

	//==================================================================
	// Gets one db by name 
	function get(dbname) {
		// console.log("get db",dbname,$rootScope.databases);
		return $rootScope.databases[dbname];
	};

	//==================================================================
	// Gets users for this db
	function getUsers(dbname) {
		console.log("getUsers",dbname,$rootScope.databases);

		if($rootScope.databases[dbname]==undefined) return [];
	
		var promise = $http.get(apiPath + '/users.php?db='+dbname);
		return promise;
	}

	//==================================================================
	// Gets collections for this db 
	function getCollections(dbname) {
		console.log("getCollections",dbname);

		//if I have the the collection cached this session return it
		if($rootScope.allCollections[dbname]!=undefined) return $rootScope.allCollections[dbname];
		
		//otherwise we need to refresh from server

		$http.get(apiPath + '/collections.php?db='+dbname)
			.success(function(data) {
				if(data=="null" || data==null) data=[];

				//sort data
				data.sort(function(a,b) {
					if(a.name.toLowerCase()==b.name.toLowerCase()) return 0;
					if(a.name.toLowerCase()<b.name.toLowerCase()) return -1;
					return 1;
				});

				$rootScope.allCollections[dbname] = data;
				$rootScope.allIndexes[dbname] = {};
				//console.log("Collections Got");
				//console.log(data);
				cache.set('cs'+dbname,data);

				$rootScope.$broadcast('update_collections');
			})
			.error(function(data) {
				console.log("ERROR FETCHING collections",data);
			});

		//but return old cached version temporarily
		return cache.get('cs'+dbname);
	};

	//==================================================================
	// Adds a collection to this db 
	function addCollection(dbname,name,capped,size,max) {
		console.log("addCollections",dbname,name,capped,size,max);
		if(name==undefined) return;
		if(dbname==undefined) return;
		
		if(capped==undefined) capped=0; else capped=1;
		if(size==undefined) size=0;
		if(max==undefined) max=0;

		$http.post(apiPath + '/collection_add.php','db='+dbname+'&name='+name+'&capped='+capped+'&size='+size+'&max='+max, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				$rootScope.allCollections[dbname]=undefined;
				getCollections(dbname);
			})
			.error(function(data) {
				console.log("ERROR adding collection",data);
			});
	};

	//==================================================================
	// Deletes a collection from this db 
	function deleteCollection(dbname,name) {
		console.log("deleteCollection",dbname,name);
		if(name==undefined) return;
		if(dbname==undefined) return;

		$rootScope.allCollections[dbname]=undefined;

		$http.post(apiPath + '/collection_remove.php','db='+dbname+'&col='+name, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				getCollections(dbname);
			})
			.error(function(data) {
				console.log("ERROR deleting collection",data);
			});
	};

	//==================================================================
	// Gets indexes for this db and collection
	function getIndexes(dbname,collection) {
		// console.log("getIndexes",dbname,collection);

		if($rootScope.allIndexes[dbname]!=undefined && $rootScope.allIndexes[dbname][collection]!=undefined) return $rootScope.allIndexes[dbname][collection];

		$http.get(apiPath + '/indexes.php?db='+dbname+'&col='+collection)
			.success(function(data) {
				if($rootScope.allIndexes[dbname]==undefined) $rootScope.allIndexes[dbname]={};
				$rootScope.allIndexes[dbname][collection] = data;
				// console.log("Indexes Got");
				// console.log(data);
				$rootScope.$broadcast('update_indexes');
			})
			.error(function(data) {
				// console.log("ERROR FETCHING indexes",data);
			});
	};

	//==================================================================
	// Adds one index
	function addIndex(dbname,collection,name,index,unique,background,dropdups,sparse) {
		if(!index) return;
		
		// console.log("addIndex",dbname,collection,name,index);

		$http.post(apiPath + '/index_add.php','db='+dbname+'&col='+collection+'&name='+name+'&index='+index+'&unique='+unique+'&background='+background+'&dropdups='+dropdups+'&sparse='+sparse, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				// console.log("added indexes",data);

				$rootScope.allIndexes[dbname]=undefined;
				getIndexes(dbname,collection);
			})
			.error(function(data) {
				// console.log("ERROR FETCHING indexes",data);
			});
	};

	//==================================================================
	// Deletes one index
	function deleteIndex(dbname,collection,index) {
		if(!index) return;

		// console.log("deleteIndexes",dbname,collection,index);

		$http.post(apiPath + '/index_remove.php','db='+dbname+'&col='+collection+'&index='+index, {'headers': {'Content-Type': 'application/x-www-form-urlencoded'}})
			.success(function(data) {
				// console.log("deleted indexes",data);

				$rootScope.allIndexes[dbname]=undefined;
				getIndexes(dbname,collection);
			})
			.error(function(data) {
				// console.log("ERROR deleting indexes",data);
			});
	};

	//==================================================================
	// Gets documents for this query on the db and collection
	function getDocuments(dbname,collection,query,fields,sort,page,num) {
		page--; //1 indexed to 0 indexed conversion

		query = "{"+query+"}";		
		fields = "{"+fields+"}";		
		sort = "{"+sort+"}";		

		console.log("getDocuments",dbname,collection,query,fields,sort,page,num);

		$rootScope.documents = null;
		$rootScope.explain = null;
		$rootScope.nolimit = null;
		$rootScope.error = null;
		
		$http.get(apiPath + '/documents.php?db='+dbname+'&col='+collection+'&query='+query+'&fields='+fields+'&sort='+sort+'&page='+page+'&num='+num)
			.success(function(data) {
				$rootScope.documents = data.docs;
				$rootScope.explain = data.explain;
				$rootScope.error = data.error;
				console.log("docs Got");
				//console.log(data);
				$rootScope.$broadcast('update_docs');
			})
			.error(function(data) {
				console.log("ERROR FETCHING docs",data);
			});

		explainQuery(dbname,collection,query,fields,sort);
	};

	//==================================================================
	// Takes a query which should be json and make sure it is
	function cleanQuery(query) {
		query = "{"+query+"}";
		// console.log("clean",query);

		//try once to see if it is valid
		try {
			var obj = angular.fromJson(query);
		} catch(e) {
			//conver ' to " and add missing quotes
			//TODO: This will not work correctly when you use a literal : in a query
			query = query.replace(/(['"])?([a-zA-Z0-9_]+)(['"])?:/g, '"$2":');
		}

		if(obj==undefined) {
			//try again
			try {
				var obj = angular.fromJson(query);
			} catch(e) {
				return false;
			}
		}

		var cleaned = angular.toJson(obj);
		cleaned = cleaned.substring(1,cleaned.length-1);
		// console.log("cleaned",cleaned);
		return cleaned;
	}

	//==================================================================
	// Gets count for this query on the db and collection
	function getCount(dbname,collection,query) {

		console.log("getCount",dbname,collection,query);

		$rootScope.documents = null;
		
		$http.get(apiPath + '/count.php?db='+dbname+'&col='+collection+'&query='+query)
			.success(function(data) {
				$rootScope.nolimit = parseInt(data);
				console.log("getCount Got");
				// console.log(data);
				$rootScope.$broadcast('update_docs');
			})
			.error(function(data) {
				console.log("ERROR getCount",data);
			});
	};

	//==================================================================
	// Gets explaination for this query on the db and collection
	function explainQuery(dbname,collection,query,fields,sort) {
		
		console.log("explainQuery",dbname,collection,query,fields,sort);
		
		$rootScope.explainFull = undefined;

		$http.get(apiPath + '/explain.php?db='+dbname+'&col='+collection+'&query='+query+'&fields='+fields+'&sort='+sort)
			.success(function(data) {
				$rootScope.explainFull = data.explain;
				$rootScope.error = data.error;
				$rootScope.nolimit = parseInt(data.explain.matches);
				console.log("explain Got");
				// console.log(data);
				$rootScope.$broadcast('update_docs');
			})
			.error(function(data) {
				console.log("ERROR explaining",data);
			});
	};

	//==================================================================
	// It will scan the current documents and attempt
	// to return an array of table headings for viewing data as table
	function getTableHeadings() {
		var headings = [];

		angular.forEach($rootScope.documents, function(value, key) {
			angular.forEach(value, function(v,col) {
				if(headings.indexOf(col)==-1 && col!='_id') headings.push(col);
			});
		});
		return headings;
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

	//==================================================================
	// Runs a performance test on the db in the admin Collection
	function runPerformance(dbname) {
		var promise = $http.get(apiPath + '/performance.php?db='+dbname)
			.error(function(data) {
				console.log("ERROR doing performance",data);
			});
		return promise;
	};


	return {
		init: init, get: get, doHealthcheck:doHealthcheck, 
		runPerformance:runPerformance, getCollections:getCollections, 
		addCollection:addCollection, getHealthcheck:getHealthcheck, 
		deleteCollection:deleteCollection, getIndexes:getIndexes, 
		explainQuery:explainQuery, getUsers:getUsers, deleteIndex:deleteIndex, 
		addIndex:addIndex, getDocuments:getDocuments, getTableHeadings:getTableHeadings,
		getDocument:getDocument, cleanQuery:cleanQuery, deleteDocument:deleteDocument
	};
}]); //end factory and module
