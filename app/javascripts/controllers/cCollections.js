'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollections', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, $routeParams, Database) {

	$rootScope.selectedDB = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.users = [];

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Collections Init");
		
		$rootScope.selectedDB = $routeParams.name;

		$scope.update();

		var promise = Database.getUsers($scope.selectedDB);
		promise.success(function(data) {
			$scope.users = data;
			if($scope.users=="null") $scope.users=[];
		});
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

	//==================================================================
	//
	$scope.update = function() {
		console.log("cCollections update");
		$rootScope.pagetitle = $rootScope.selectedDB;

		$scope.db = Database.get($rootScope.selectedDB);
		$scope.collections = Database.getCollections($rootScope.selectedDB);
	};

	//==================================================================
	// Collects the options for adding the collection and sends to model
	$scope.addCollection = function() {
		if($scope.addCollectionName==undefined || $scope.addCollectionName=="") return;
		Database.addCollection($rootScope.selectedDB,$scope.addCollectionName,$scope.addCollectionCapped,$scope.addCollectionSize,$scope.addCollectionMax);
		
		$scope.showAddCollection = false;
		$scope.addCollectionName = "";
	};

}]);

