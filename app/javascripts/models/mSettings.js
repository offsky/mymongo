'use strict';
/* ==================================================================
	Makes certain settings inside the app stick across reloads

-----------------------------------------------------------------*/

angular.module('phpMongoAdmin.mSettings', []).factory('phpMongoAdmin.mSettings', ['$http', '$rootScope', 'Store', function($http, $rootScope, cache) {

	//==================================================================
	// Initialize the database array from the server
	function init() {

	};

	//==================================================================
	// Returns 1 for table 2 for json display of documents
	function getDisplayPref() {
		var value = cache.get("displayPref");
		if(value!=1 && value!=2) value = 2;
		return value;
	};

	//==================================================================
	// Sets 1 for table 2 for json display of documents
	function setDisplayPref(value) {
		cache.set("displayPref",value);
	};	

	//==================================================================
	//
	function getQuery(db,collection) {
		var value = cache.get("query"+db+"."+collection);
		if(!value) value = '';
		return value;
	};

	//==================================================================
	//
	function setQuery(db,collection,value) {
		cache.set("query"+db+"."+collection,value);
	};	

	//==================================================================
	//
	function getFields(db,collection) {
		var value = cache.get("fields"+db+"."+collection);
		if(!value) value = '';
		return value;
	};

	//==================================================================
	//
	function setFields(db,collection,value) {
		cache.set("fields"+db+"."+collection,value);
	};	

	//==================================================================
	//
	function getSort(db,collection) {
		var value = cache.get("sort"+db+"."+collection);
		if(!value) value = '';
		return value;
	};

	//==================================================================
	//
	function setSort(db,collection,value) {
		cache.set("sort"+db+"."+collection,value);
	};

	//==================================================================
	//
	function getPageSize() {
		var value = cache.get("pagesize");
		if(value==undefined || value==null) value = 1; //0=10. 1=50. 2=100
		return value;
	};

	//==================================================================
	//
	function setPageSize(value) {
		cache.set("pagesize",value);
	};	

	return {
		init: init, getPageSize:getPageSize, setPageSize:setPageSize, getDisplayPref:getDisplayPref, setDisplayPref:setDisplayPref, getSort:getSort, setSort:setSort, getQuery:getQuery, setQuery:setQuery, setFields:setFields, getFields:getFields
	};
}]); //end factory and module
