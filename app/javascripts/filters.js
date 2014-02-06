'use strict';

/* Filters */

angular.module('phpMongoAdmin.filters', [])

//Displays bytes prettily 
.filter('bytes', function() {
	return function(bytes, precision) {
		if(bytes==null || bytes==undefined) return "<i class='fa fa-spinner fa-spin'></i>";
		if (bytes==0 || isNaN(parseFloat(bytes)) || !isFinite(bytes)) return '-';
		if (typeof precision === 'undefined') precision = 1;
		var units = ['bytes', 'kB', 'MB', 'GB', 'TB', 'PB'],
			number = Math.floor(Math.log(bytes) / Math.log(1024));
		return (bytes / Math.pow(1024, Math.floor(number))).toFixed(precision) +  ' ' + units[number];
	}
})

//Inserts a loading spinner if the value is null
.filter('nullspin', function() {
	return function(value) {
		if(value==null || value==undefined) return "<i class='fa fa-spinner fa-spin'></i>";
		return value;
	}
})

//Inserts a loading spinner if the value is null
.filter('healthDecode', function() {
	return function(value) {
		if(value==null || value==undefined) return "<i class='fa fa-spinner fa-spin'></i>";
		switch(value) {
			case 0: return "<i class='fa fa-check-circle'></i> Healthy";
			case 1: return "<i class='fa fa-exclamation-triangle'></i> Can't connect";
			case 2: return "<i class='fa fa-exclamation-triangle'></i> Unhealthy";
			case 3: return "<i class='fa fa-exclamation-triangle'></i> No Primary";
			case 4: return "<i class='fa fa-exclamation-triangle'></i> Remove Failed";
			case 5: return "<i class='fa fa-exclamation-triangle'></i> Write Failed";
			case 6: return "<i class='fa fa-exclamation-triangle'></i> Read Failed";
			case 7: return "<i class='fa fa-exclamation-triangle'></i> ReplicaSet Failover";
			case 8: return "<i class='fa fa-exclamation-triangle'></i> Uncaught Exception";
			case 9: return "<i class='fa fa-exclamation-triangle'></i> Update Failed";

		}
		return "<i class='fa fa-question-circle'></i> unknown";
	}
})



//Does syntax coloring of json. Pass in an object.
.filter('colorjson', function() {
	return function(json) {
		//http://stackoverflow.com/questions/4810841/how-can-i-pretty-print-json-using-javascript
		json = angular.toJson(json);
		json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace('"_id":{},','');
		return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
		  	var cls = 'number';
		  	if (/^"/.test(match)) {
				if (/:$/.test(match)) {
					 cls = 'key';
				} else {
					 cls = 'string';
				}
			} else if (/true|false/.test(match)) {
				cls = 'boolean';
			} else if (/null/.test(match)) {
				cls = 'null';
			}
			return '<span class="' + cls + '">' + match + '</span>';
		});
	}
})


;
