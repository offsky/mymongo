'use strict';

/* ==================================================================

-----------------------------------------------------------------*/

angular.module('phpMongoAdmin.mDatabase', []).factory('phpMongoAdmin.mDatabase', ['$http', '$rootScope', function($http, $rootScope) {

	$rootScope.databases = []; //a pristine copy of the books array

	var apiPath = $$config.apiPath;

	//==================================================================
	// Initialize the award array from the database
	function init() {
		if($rootScope.databases.length) return; //already initialized

		console.log("mDatabase INIT");

		$http.get(apiPath + '/databases.php')
			.success(function(data) {
				$rootScope.databases = data;
				console.log("mDatabase INIT final");
				// console.table(data);
			})
			.error(function(data) {
				console.log("ERROR FETCHING mDatabase",data);
			});
	};

	//==================================================================
	// Gets one db by name 
	function get(name) {
		console.log("get",name,$rootScope.databases);
		return $rootScope.databases[name];
	};


	return {
		init: init, get: get
	};
}]); //end factory and module
