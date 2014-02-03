'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cCollections', ['$scope', '$rootScope', '$routeParams', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, $routeParams, Database) {

	$rootScope.selectedDB = "";
	$scope.db = null;

	//==================================================================
	// Called each time the view is loaded or reloaded
	$scope.init = function() {
		console.log("Collection Init");
		
		$rootScope.selectedDB = $routeParams.name;

		$scope.update();

		$scope.$watch(function() {
			$scope.update();
		});
	};

	$scope.update = function() {
		$scope.db = Database.get($rootScope.selectedDB);
	}

}]);

