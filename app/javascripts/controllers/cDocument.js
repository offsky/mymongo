'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cDocument', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', '$location', function($scope, $rootScope, $routeParams, Database, $location) {

	$rootScope.selectedDB = "";
	$rootScope.selectedCol = "";
	$rootScope.selectedDoc = "";
	$scope.db = null;
	$scope.collections = null;
	$scope.collection = null;
	$scope.confirm = 0;
	$scope.dirty = false;
	$scope.currDoc = null; //The current doc potentially edited but not saved
	$scope.saving = false;

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Document Init");
		
		$rootScope.selectedDB = $routeParams.name;
		$rootScope.selectedCol = $routeParams.collection;
		$rootScope.selectedDoc = $routeParams.doc;

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
	$rootScope.$on('update_doc', function() {
		//document updated
		$scope.currDoc = $rootScope.doc;
 	});

	//==================================================================
	//
	$scope.update = function() {
		console.log("cDocument update");
		$rootScope.pagetitle = $rootScope.selectedDB+" "+$rootScope.selectedCol;

		$scope.db = Database.get($rootScope.selectedDB);
		
		$scope.collections = Database.getCollections($rootScope.selectedDB);
		if($scope.collections && $scope.collections.length) {
			for(var i = 0;i<$scope.collections.length;i++) {
				if($scope.collections[i].name==$rootScope.selectedCol) $scope.collection = $scope.collections[i];
			}
		}
	
		Database.getDocument($rootScope.selectedDB,$rootScope.selectedCol,$rootScope.selectedDoc);
	};

	//==================================================================
	// Saves the edited document
	$scope.cancel = function() {
		console.log("cancel");
		$scope.currDoc = $rootScope.doc;
		$scope.dirty = false;
		if($scope.selectedDoc=="new") $location.path('/db/' + $rootScope.selectedDB + '/' + $rootScope.selectedCol); //go to the list
	};

	//==================================================================
	// Saves the edited document
	$scope.save = function() {
		console.log("save");
		if($scope.db.readonly) return;
		$rootScope.doc = $scope.currDoc;
		$scope.dirty = false;
		$scope.saving = true;

		var promise = Database.saveDocument($rootScope.selectedDB,$rootScope.selectedCol,$rootScope.selectedDoc,$scope.currDoc);
		promise.success(function(data) {
			$scope.saving = false;
			if($scope.selectedDoc=="new" && data!=="0") {
				$location.path('/db/' + $rootScope.selectedDB + '/' + $rootScope.selectedCol + '/' + data); //go to the list
			}
		});
	};

	//==================================================================
	// Deletes the document
	$scope.delete = function() {
		if($scope.db.readonly) return;
		$scope.confirm=2;
		var promise = Database.deleteDocument($rootScope.selectedDB,$rootScope.selectedCol,$rootScope.selectedDoc);
		promise.success(function() {
			$location.path('/db/' + $rootScope.selectedDB + '/' + $rootScope.selectedCol); //go to the list
		});
	};
}]);

