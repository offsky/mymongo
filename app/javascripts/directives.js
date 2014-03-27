'use strict';

/* Directives */


angular.module('phpMongoAdmin.directives', []).directive('jsoneditor', function() {
    return {
		restrict: "A", //attribute <div slice-scroll>...</div>

		scope: {
			jsoneditor: '=',	//Bi-directional binding.
			dirty: '='
		},

		link: function(scope, container, attrs) {
			var IdidIt = false;

			var wasEdited = function() {
				if(editor) {
					IdidIt = true;
					scope.jsoneditor = editor.get();
					scope.dirty=true;
					// console.log("was Edited",scope.jsoneditor);
					scope.$apply();
				}
			};

			var wasError = function(error) {
				// console.log("error",error);
			};

			var update = function() {
				if(editor) {
					var json = angular.toJson(scope.jsoneditor)
					// console.log("update",json,scope.jsoneditor);

					editor.set(scope.jsoneditor);
				}
			};

			scope.dirty=false;
     		var editor = new jsoneditor.JSONEditor(container[0], {
     			change: wasEdited,
     			name: "Document"
     		});
	
			//need to watch the master array for changes to length
			scope.$watch(function(scope) {
				return scope.jsoneditor;
			}, function (value) {
				if(!IdidIt) {
					update();
				} //It might have been this directive that changed it, so ignore circular updates
				IdidIt = false;
			});

			scope.$on('$destroy', function() { //need to clean up the event watchers when the scope is destroyed
				editor = null; //TODO: is there a better way to destroy editor?
			});
		}
	};
});