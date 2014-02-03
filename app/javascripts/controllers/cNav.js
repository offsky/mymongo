'use strict';
/* ==================================================================
The controller for the top navigation. Also initializes the models
-----------------------------------------------------------------*/

angular.module('phpMongoAdmin').controller('cNav', ['$scope', '$rootScope', 'phpMongoAdmin.mDatabase', function($scope, $rootScope, Database) {

	//==================================================================
	// 
	$scope.init = function() {
		console.log("Nav Init");
		Database.init();
	};

}]);

