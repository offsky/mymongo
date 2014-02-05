'use strict';
	

// Declare app level module which depends on filters, and services
angular.module('phpMongoAdmin', ['ngRoute','ngSanitize','phpMongoAdmin.filters', 'phpMongoAdmin.directives', 'phpMongoAdmin.mDatabase', 'ui.bootstrap']).
  config(['$routeProvider', function($routeProvider) {
    $routeProvider.when('/help', {templateUrl: 'views/help.html', controller: 'cHelp'});
    $routeProvider.when('/settings', {templateUrl: 'views/settings.html', controller: 'cSettings'});
    $routeProvider.when('/databases', {templateUrl: 'views/databases.html', controller: 'cDatabases'});
    $routeProvider.when('/db/:name', {templateUrl: 'views/collections.html', controller: 'cCollections'});
    $routeProvider.when('/db/:name/:collection', {templateUrl: 'views/collection.html', controller: 'cCollection'});
    $routeProvider.otherwise({redirectTo: '/databases'});
  }]);
