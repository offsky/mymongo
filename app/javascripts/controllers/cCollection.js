'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollection', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', 'phpMongoAdmin.mSettings', function($scope, $rootScope, $routeParams, Database, Settings) {

	$rootScope.selectedDB = "";
	$rootScope.selectedCol = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.collection = null;

	$scope.page = 1;
	$scope.pageSizeOptions = [{name:'10'},{name:'50'},{name:'100'}];
	$scope.pageSize = $scope.pageSizeOptions[1]; //records per page

	$scope.maxSize = 20; //number of pages to show in page bar
	$scope.tab = 0;
	$scope.displayMode = 2;
	$scope.tableHeadings = [];

	$scope.query = "";
	$scope.fields = "";
	$scope.sort = "";


	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Collection Init");
		
		//get saved display preferneces and sets watcher to store changes to preference
		$scope.displayMode = Settings.getDisplayPref();
		$scope.$watch('displayMode', function(newVal, oldVal){
   	 	Settings.setDisplayPref(newVal);
		});
   	 	
		$rootScope.selectedDB = $routeParams.name;
		$rootScope.selectedCol = $routeParams.collection;

		//Restore query params from settings if possible
		$scope.query = Settings.getQuery($rootScope.selectedDB,$rootScope.selectedCol);
		$scope.fields = Settings.getFields($rootScope.selectedDB,$rootScope.selectedCol);
		$scope.sort = Settings.getSort($rootScope.selectedDB,$rootScope.selectedCol);

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
	$rootScope.$on('update_docs', function() {
		console.log("update_docs");
		
		$scope.tableHeadings = Database.getTableHeadings();
	});
	
	//==================================================================
	//
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

	//==================================================================
	//
	$scope.selectPage = function(num) {
		$scope.page = num;

		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
	};

	//==================================================================
	//
	$scope.search = function() {
		console.log("search",$scope.query);

		Settings.setQuery($rootScope.selectedDB,$rootScope.selectedCol,$scope.query);	
		Settings.setFields($rootScope.selectedDB,$rootScope.selectedCol,$scope.fields);	
		Settings.setSort($rootScope.selectedDB,$rootScope.selectedCol,$scope.sort);	

		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
	};

	//==================================================================
	//
	$scope.deleteIndex = function(index) {
		Database.deleteIndex($rootScope.selectedDB,$rootScope.selectedCol,index);
	};

	//==================================================================
	//
	$scope.addIndex = function(index) {
		Database.addIndex($rootScope.selectedDB,$rootScope.selectedCol,$scope.i_name,$scope.i_index);		
	};


}]);

