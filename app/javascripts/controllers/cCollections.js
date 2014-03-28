'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollections', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, $routeParams, Database) {

	$rootScope.selectedDB = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.users = [];
	$scope.tab = 0;

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		// console.log("Collections Init");
		
		$rootScope.selectedDB = $routeParams.name;

		$scope.update();

		Database.getHealthcheck($rootScope.selectedDB,false);		
	};

	//==================================================================
	// Listens for broadcass that a db was updated and refreshes the list
	$rootScope.$on('update_databases', function() {
		console.log("cCollections update_databases");
		$scope.db = Database.get($rootScope.selectedDB);
	});
	$rootScope.$on('update_collections', function() {
		console.log("cCollections update_collections");
		$scope.update();
	});

	//==================================================================
	//
	$scope.getUsers = function() {
		$scope.tab=1;

		var promise = Database.getUsers($scope.selectedDB);
		promise.success(function(data) {
			$scope.users = data;
			if($scope.users=="null") $scope.users=[];
		});
	}

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
		if($scope.db.readonly || $scope.addCollectionName==undefined || $scope.addCollectionName=="") return;
		Database.addCollection($rootScope.selectedDB,$scope.addCollectionName,$scope.addCollectionCapped,$scope.addCollectionSize,$scope.addCollectionMax);
		
		$scope.showAddCollection = false;
		$scope.addCollectionName = "";
	};

	//==================================================================
	// 
	$scope.performance = function() {
		$scope.runningPerf = true;
		var promise = Database.runPerformance($rootScope.selectedDB);
		promise.then(function(data) {
			var time = data.data;
			$scope.runningPerf = false;
			$scope.testResults = time;
		});
	}

}]);

