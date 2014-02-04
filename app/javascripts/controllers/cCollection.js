'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollection', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, $routeParams, Database) {

	$rootScope.selectedDB = "";
	$rootScope.selectedCol = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.collection = null;

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Collection Init");
		
		$rootScope.selectedDB = $routeParams.name;
		$rootScope.selectedCol = $routeParams.collection;

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
		console.log("cCollection update");
		$rootScope.pagetitle = $rootScope.selectedDB+" "+$rootScope.selectedCol;

		$scope.db = Database.get($rootScope.selectedDB);
		$scope.collections = Database.getCollections($rootScope.selectedDB);
		$scope.indexes = Database.getIndexes($rootScope.selectedDB,$rootScope.selectedCol);
		
		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol);
		
		if($scope.collections && $scope.collections.length) {
			for(var i = 0;i<$scope.collections.length;i++) {
				if($scope.collections[i].name==$rootScope.selectedCol) $scope.collection = $scope.collections[i];
			}
		}
	}

}]);

