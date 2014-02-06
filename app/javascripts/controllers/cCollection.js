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

	$scope.page = 1;
	$scope.pageSizeOptions = [{name:'10'},{name:'50'},{name:'100'}];
	$scope.pageSize = $scope.pageSizeOptions[1]; //records per page

	$scope.maxSize = 20; //number of pages to show in page bar

	$scope.query = "";
	$scope.fields = "";
	$scope.sort = "";

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
		$scope.db = Database.get($rootScope.selectedDB);
	});
	$rootScope.$on('update_collections', function() {
		console.log("update_collections");
		$scope.collections = Database.getCollections($rootScope.selectedDB);
		if($scope.collections && $scope.collections.length) {
			for(var i = 0;i<$scope.collections.length;i++) {
				if($scope.collections[i].name==$rootScope.selectedCol) $scope.collection = $scope.collections[i];
			}
		}
	});
	$rootScope.$on('update_indexes', function() {
		console.log("update_indexes");
		$scope.indexes = Database.getIndexes($rootScope.selectedDB,$rootScope.selectedCol);
		
		$scope.i_name = "";
		$scope.i_index = "";
	});

	$scope.update = function() {
		console.log("cCollection update");
		$rootScope.pagetitle = $rootScope.selectedDB+" "+$rootScope.selectedCol;

		$scope.db = Database.get($rootScope.selectedDB);
		
		$scope.collections = Database.getCollections($rootScope.selectedDB);
		if($scope.collections && $scope.collections.length) {
			for(var i = 0;i<$scope.collections.length;i++) {
				if($scope.collections[i].name==$rootScope.selectedCol) $scope.collection = $scope.collections[i];
			}
		}

		$scope.indexes = Database.getIndexes($rootScope.selectedDB,$rootScope.selectedCol);
	
		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
	};

	$scope.selectPage = function(num) {
		console.log("goto page ",num);
		$scope.page = num;

		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
	};

	$scope.search = function() {
		console.log("search",$scope.query);

		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
	};

	$scope.deleteIndex = function(index) {
		Database.deleteIndex($rootScope.selectedDB,$rootScope.selectedCol,index);
	};

	$scope.addIndex = function(index) {
		Database.addIndex($rootScope.selectedDB,$rootScope.selectedCol,$scope.i_name,$scope.i_index);		
	};


}]);

