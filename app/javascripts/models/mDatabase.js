'use strict';

/* ==================================================================

-----------------------------------------------------------------*/

angular.module('phpMongoAdmin.mDatabase', []).factory('phpMongoAdmin.mDatabase', ['$http', '$rootScope', function($http, $rootScope) {

	$rootScope.client = {}; //The last copy of the client info
	$rootScope.databases = {}; //The last copy of the list of databases and info
	$rootScope.allCollections = {}; //The collections for the various databases
	$rootScope.allIndexes = []; //The collections for the various databases
	$rootScope.documents = []; // the array of documents that we are currently viewing

	var apiPath = $$config.apiPath;

	//==================================================================
	// Initialize the database array from the server
	function init() {
		if($rootScope.databases.length) return; //already initialized

		console.log("mDatabase INIT");

		$http.get(apiPath + '/databases.php')
			.success(function(data) {
				$rootScope.databases = data;
				console.log("mDatabase INIT final");
				// console.table(data);
				$rootScope.$broadcast('update_databases');
			})
			.error(function(data) {
				console.log("ERROR FETCHING mDatabase",data);
			});

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
	// Gets one db by name 
	function get(name) {
		console.log("get db",name,$rootScope.databases);
		return $rootScope.databases[name];
	};

	//==================================================================
	// Gets collections for this db 
	function getCollections(name) {
		console.log("getCollections",name,$rootScope.databases);

		if($rootScope.allCollections[name]!=undefined) return $rootScope.allCollections[name];

		$http.get(apiPath + '/collections.php?db='+name)
			.success(function(data) {
				$rootScope.allCollections[name] = data;
				$rootScope.allIndexes[name] = {};
				console.log("Collections Got");
//				console.log(data);
				$rootScope.$broadcast('update_collections');
			})
			.error(function(data) {
				console.log("ERROR FETCHING collections",data);
			});
	};

	//==================================================================
	// Gets indexes for this db and collection
	function getIndexes(name,collection) {
		console.log("getIndexes",name,collection);

		if($rootScope.allIndexes[name]!=undefined && $rootScope.allIndexes[name][collection]!=undefined) return $rootScope.allIndexes[name][collection];

		$http.get(apiPath + '/indexes.php?db='+name+'&col='+collection)
			.success(function(data) {
				if($rootScope.allIndexes[name]==undefined) $rootScope.allIndexes[name]={};
				$rootScope.allIndexes[name][collection] = data;
				console.log("Indexes Got");
				console.log(data);
				$rootScope.$broadcast('update_indexes');
			})
			.error(function(data) {
				console.log("ERROR FETCHING indexes",data);
			});
	};

	//==================================================================
	// Gets documents for this db and collection
	function getDocuments(name,collection,$query,$fields,$sort,$skip,$num) {
		console.log("getDocuments",name,collection);

		$rootScope.documents = [];
		
		$http.get(apiPath + '/documents.php?db='+name+'&col='+collection)
			.success(function(data) {
				$rootScope.documents = data;
				console.log("docs Got");
				console.log(data);
				$rootScope.$broadcast('update_docs');
			})
			.error(function(data) {
				console.log("ERROR FETCHING docs",data);
			});
	};

	return {
		init: init, get: get, getCollections:getCollections, getIndexes:getIndexes, getDocuments:getDocuments
	};
}]); //end factory and module
