'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollections', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, $routeParams, Database) {

	$rootScope.selectedDB = "";
	$scope.db = null;
	$scope.collections = null;

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Collections Init");
		
		$rootScope.selectedDB = $routeParams.name;

		$scope.update();
	};

	//==================================================================
	// Listens for broadcass that a db was updated and refreshes the list
	$rootScope.$on('update_databases', function() {
		console.log("update_databases");
		$scope.update();
	});
	$rootScope.$on('update_collections', function() {
		console.log("update_collections");
		$scope.update();
	});


	$scope.update = function() {
		console.log("cCollections update");
		$rootScope.pagetitle = $rootScope.selectedDB;

		$scope.db = Database.get($rootScope.selectedDB);
		$scope.collections = Database.getCollections($rootScope.selectedDB);
	}

}]);

