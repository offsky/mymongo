'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollection', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', 'phpMongoAdmin.mSettings', '$location', '$anchorScroll', function($scope, $rootScope, $routeParams, Database, Settings, $location, $anchorScroll) {

	$rootScope.selectedDB = "";
	$rootScope.selectedCol = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.collection = null;

	$scope.page = 1;
	$scope.pageSizeOptions = [{name:'10'},{name:'50'},{name:'100'}];
	$scope.pageSize = $scope.pageSizeOptions[1]; //records per page

	$scope.maxSize = 20; //number of pages to show in page bar
	$scope.tab = 0; //0= documents, 1=indexes, 2=stats
	$scope.tab2details = false; //to show the raw collection stats
	$scope.displayMode = 2;
	$scope.tableHeadings = [];
	$scope.i_back = true; //should default to background creation indexes

	$scope.query = "";
	$scope.fields = "";
	$scope.sort = "";

	$scope.query_t = false;
	$scope.query_f = false;
	$scope.fields_t = false;
	$scope.fields_f = false;
	$scope.sort_t = false;
	$scope.sort_f = false;

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

		$scope.pageSize = $scope.pageSizeOptions[Settings.getPageSize()];
		$scope.update();
	};

	//==================================================================
	// Listens for broadcass that a db was updated and refreshes the list
	$rootScope.$on('update_databases', function() {
		console.log("cCollection update_databases");
		$scope.db = Database.get($rootScope.selectedDB);
	});
	$rootScope.$on('update_collections', function() {
		console.log("cCollection update_collections");
		$scope.collections = Database.getCollections($rootScope.selectedDB);
		if($scope.collections && $scope.collections.length) {
			for(var i = 0;i<$scope.collections.length;i++) {
				if($scope.collections[i].name==$rootScope.selectedCol) $scope.collection = $scope.collections[i];
			}
		}
	});
	$rootScope.$on('update_indexes', function() {
		console.log("cCollection update_indexes");
		$scope.indexes = Database.getIndexes($rootScope.selectedDB,$rootScope.selectedCol);
		
		$scope.i_name = "";
		$scope.i_index = "";
	});
	$rootScope.$on('update_docs', function() {
		console.log("cCollection update_docs");
		
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

		//save page size setting
		var size = 0;
		angular.forEach($scope.pageSizeOptions, function(v,k) {
			if(v.name==$scope.pageSize.name) size=k;
		});
		Settings.setPageSize(size);
		$anchorScroll(); //scroll to top
	};

	//==================================================================
	//
	$scope.selectPage = function(num) {
		$scope.page = num;

		Database.getDocuments($rootScope.selectedDB,$rootScope.selectedCol,$scope.query,$scope.fields,$scope.sort,$scope.page,$scope.pageSize.name);
		$anchorScroll(); //scroll to top
	};

	//==================================================================
	// on blur, cleans the json data by adding quotes where missing
	// also sets the flags to drive the css feedback
	$scope.blurquery = function() {
		var cleaned = Database.cleanQuery($scope.query);
		if(cleaned===false) {
			$scope.query_f = true;
			$scope.query_t = false;
		} else {
			$scope.query_f = false;
			$scope.query_t = true;
			$scope.query = cleaned;
		}
	}

	//==================================================================
	// on blur, cleans the json data by adding quotes where missing
	// also sets the flags to drive the css feedback
	$scope.blurfields = function() {
		var cleaned = Database.cleanQuery($scope.fields);
		if(cleaned===false) {
			$scope.fields_f = true;
			$scope.fields_t = false;
		} else {
			$scope.fields_f = false;
			$scope.fields_t = true;
			$scope.fields = cleaned;
		}
	}

	//==================================================================
	// on blur, cleans the json data by adding quotes where missing
	// also sets the flags to drive the css feedback
	$scope.blursort = function() {
		var cleaned = Database.cleanQuery($scope.sort);
		if(cleaned===false) {
			$scope.sort_f = true;
			$scope.sort_t = false;
		} else {
			$scope.sort_f = false;
			$scope.sort_t = true;
			$scope.sort = cleaned;
		}
	}

	//==================================================================
	//
	$scope.search = function() {
		console.log("search",$scope.query);

		$scope.blursort();

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
		Database.addIndex($rootScope.selectedDB,$rootScope.selectedCol,$scope.i_name,$scope.i_index,$scope.i_unique,$scope.i_back,$scope.i_drop,$scope.i_sparse);		
	};

	//==================================================================
	//
	$scope.deleteCollection = function(index) {
		var result = prompt("Are you sure you want to DELETE this entire collection? If yes, please enter the name of the collection to confirm.");
		if(result==$rootScope.selectedCol) {
			Database.deleteCollection($rootScope.selectedDB,$rootScope.selectedCol);
			$location.path("db/"+$rootScope.selectedDB);
		}
	};

}]);

