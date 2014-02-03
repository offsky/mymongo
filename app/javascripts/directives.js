'use strict';

/* Directives */


angular.module('phpMongoAdmin.directives', []).
  directive('appVersion', ['version', function(version) {
    return function(scope, elm, attrs) {
      elm.text(version);
    };
  }]);
