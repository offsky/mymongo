'use strict';

/*
	Helper function for key value storage
	
	When local storage is available and has never previously been used or rejected, we need
	to prompt the user for permission.  Without permission, or without local storage enabled 
	we need to fall back to session storage, either real or fake(hashtable).
	
	Dependencies: hashtable.js,  jquery.json-2.2
	
	Local storage is supported in at least (could be more):
	Mac: Safari 5+, Firefox 4+, Chrome 10+, Opera 11+
	PC: Chrome 9+, Firefox 3.6+, IE8+
	
	Storage limits for permanent local storage
	Mac Safari: 2561k
	Mac Chrome: 2561k 
	Mac Firefox: 4939k
	Mac Opera: 1910k, but it asks for more room once
	
	Storage limits for temporary session storage
	Mac Safari: unlimited?  At least 50M
	Mac Chrome: 2561k
	Mac Firefox: unlimited?  At least 50M
	Mac Opera: 5061k

	Mac Safari,Chrome,Firefox and Opera have separate limts for local and session

	Copyright Toodledo 2011
*/
angular.module('Store', []).factory('Store', [function() {

	var ST_LOCAL_REJECTED = 'ST_LOCAL_REJECTED'; //when the users has previously rejected local storage.  Using session
	var ST_LOCAL_INUSE = 'ST_LOCAL_INUSE'; //when local storage is already in use
	var ST_LOCAL_POSSIBLE = 'ST_LOCAL_POSSIBLE'; //a virgin install with local possible
	var ST_SESSION = 'ST_SESSION'; //local is not available, using session
	var ST_HASHTABLE = 'ST_HASHTABLE'; //local and storage is not available, using hashtable storage	
	
	var debug = false;
	var state;
	var localDBCached = undefined;
	var sessionDBCached = undefined;
	var isJson;
	var hashtable;
	var sessionAvailableCached = undefined;
	var localAvailableCached = undefined;

	//==============================================================================
	// Initializes the class. It is called at the end of the store() class definition
	function init() {		
		//A regular expression to determine if a value is a json string
		try {
			isJson = new RegExp('^("(\\\\.|[^"\\\\\\n\\r])*?"|[,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t])+?$')
		} catch (e) {
			isJson = /^(true|false|null|\[.*\]|\{.*\}|".*"|\d+|\d+\.\d+)$/
		}
		
		state = ST_HASHTABLE; //can always do fake session storage via class variables
		hashtable = new Hashtable();

		//check for session storage
		if(sessionAvailable()) {
			state = ST_SESSION; //can probably do session storage
		}
		
		//check for local storage
		if(localAvailable()) { //local is almost certainly available if session is, but maybe not
			state = ST_LOCAL_POSSIBLE; 
			grantLocalPermission();
		}
	}


	//==============================================================================
	// Returns true of local storage is being used
	// TESTS: yes
	function isLocal() {	
		return (state==ST_LOCAL_INUSE);
	};

	//==============================================================================
	// Returns a reference to the session storage object if it exists.
	// TESTS: yes
	function sessionStorageRef() {
		if(sessionDBCached!=undefined) return sessionDBCached;
		sessionDBCached = undefined;
		if(window.sessionStorage) sessionDBCached = window.sessionStorage;

		return sessionDBCached;
	};

	//==============================================================================
	// Returns a reference to the local storage object if it exists.
	// TESTS: yes
	function localStorageRef() {
		if(localDBCached!=undefined) return localDBCached;
		localDBCached = undefined;
		if(window.localStorage) localDBCached = window.localStorage; //for some reason, this query is slow, so we cache it
		else if(window.globalStorage) localDBCached = window.globalStorage[location.hostname];
		return localDBCached;
	};

	//==============================================================================
	// Tests whether local storage engine is available
	// TESTS: yes
	function localAvailable() {	
		if(localAvailableCached!=undefined) return localAvailableCached;
		localAvailableCached = false;
		if(window.localStorage || window.globalStorage) {
			var database  = localStorageRef();
			try {
				database.setItem("testLocalStorageAvailable",1);
			} catch (err) {
				if (err.code == 22 || err.code==1014) {  //safari,chrome=22 firefox=1014 memory full
					$('body').trigger("lowmemory");
					localAvailableCached = true;
					return true;
				}
	    		if(debug)console.log('store localAvailable error',err);
				return false;
			}
			if(database.getItem("testLocalStorageAvailable")!=1) return false;
			database.removeItem("testLocalStorageAvailable");
			localAvailableCached = true;
			return true;
		}
		return false;	
	};

	//==============================================================================
	// Tests whether session storage engine is available
	// TESTS: yes
	function sessionAvailable() {	
		if(sessionAvailableCached!=undefined) return sessionAvailableCached;
		sessionAvailableCached = false;
		
		if(window.sessionStorage) {
			try {
				window.sessionStorage.setItem("testSessionAvailable",1);
			} catch (err) {
				if (err.code == 22 || err.code==1014) {  //safari,chrome=22 firefox=1014 memory full
					$('body').trigger("lowmemory");
					sessionAvailableCached = true;
				}
	    		if(debug)console.log('store sessionAvailable error',err);
				return false;
			}
			if(window.sessionStorage.getItem("testSessionAvailable")!=1) return false;
			window.sessionStorage.removeItem("testSessionAvailable");
			sessionAvailableCached = true;
			return true;
		}
		return false;
	};

	//==============================================================================
	// Tests for SQL and IE storage which we don't use, but here it is anyway
	//this.sqlAvailable = !!window.openDatabase;
	//this.ieAvailable = !!window.ActiveXObject;

	//==============================================================================
	// grants the library permission to use local storage
	// TESTS: yes
	function grantLocalPermission() {
		
		if(localAvailable()) { 
			var localDatabase = localStorageRef();
			state = ST_LOCAL_INUSE;
		}
	};

	//==============================================================================
	// revokes permission to use local storage and removes all local storage
	// TESTS: yes
	function revokeLocalPermission() {
		
		state = ST_HASHTABLE; //can always do fake session storage via hashtable
		
		if(sessionAvailable()) {
			state = ST_SESSION; //can probably do session storage
		}
		
		var local = localStorageRef();
		for(var i in local) {        
	        set(i,local[i]); //move data from local to session or class storage
		}    
		local.clear();
	};

	//==============================================================================
	// outputs the hashtable for debugging
	// TESTS: no
	function print() {
		/*
		if(this.database.items) {
			console.log("HASHTABLE");
			for (var i in this.database.items) {
				var value = this.database.getItem(i);
				if(!typecheck(value, 'Function')) console.log('key is: ' + i + ', value is: ' + value);
			}
		} else { 
			console.log("DATABASE");
			for (var i in this.database) {
				console.log('key is: ' + i + ', value is: ' + this.database.getItem(i));
			}
		}
		*/
	}

	//==============================================================================
	// compares the object to the type
	// This is more reliable method than a simple typeof comparison because "typeof []" returns Object, not Array.
	// TESTS: yes
	function typecheck(type, compare) {
		return !type ? false : type.constructor.toString().match(new RegExp(compare + '\\(\\)', 'i')) !== null; 
	};
	    
	//==============================================================================
	// encodes objects as json for storage
	// TESTS: yes
	function prepareForStorage(value) {
		if (value === undefined || value === null) return '';
		
		if (typecheck(value, 'Object') || typecheck(value, 'Array') || typecheck(value, 'Function')) {
	        return angular.toJson(value);
	    }
	        
	    return value;
	};

	//==============================================================================
	// decodes json encoded objects into the objects
	// TESTS: yes
	function prepareForRevival(value) {
	    if (value === undefined || value === null) return undefined;
	    
	    var out = "";
	   	try { //not sure why this causes an error for some stored values. For example: "r123"
	   		//out = isJson.test(value) ? $.evalJSON(value) : value;
	 		
	 		//TODO: This above line is what I had, but the below line is 10x faster. Do I need to do the isJson test?
	 		
	 		out = angular.fromJson(value);
	    } catch(err) {
	    	out = value;
	    }
	    return out;
	 };

	//==============================================================================
	// fetches the value from the key
	// TESTS: yes
	function get(key) {
		//console.log("get",key);
		
		if(key==undefined) return;
		
		var value = undefined;
		
		if(isLocal() && localAvailable()) { //attempt to get out of local storage if in use and available
			var db = localStorageRef();
			value = db.getItem(key);
			//if(debug && value) console.log("found",key,"in local");
		}
		if(value==undefined && sessionAvailable()) { //if not in local, attempt to get out of session
			var db = sessionStorageRef();
			value = db.getItem(key);
			//if(debug && value) console.log("found",key,"in session");
		}
		if(value==undefined && hashtable) { //if not in session, look in hashtable
			value = hashtable.getItem(key);
			//if(debug && value) console.log("found",key,"in hashtable");
		}
		
		if(value!=undefined) {
	  		value = prepareForRevival(value && value.value ? value.value : value); //  Gecko's getItem returns {value: 'the value'}, WebKit returns 'the value'
		}

		return value;           
	};

	//==============================================================================
	// sets the value for the key
	// TESTS: yes
	function set(key,value) {
		//console.log("set",key,value);
		
		if(key==undefined) return false;
		
		var success = false;
		
		if(isLocal() && localAvailable()) { //try to write to local if in use and available
			var db = localStorageRef();
			success = set_db(key,value,db);
		}
		if(!success && sessionAvailable()) { //if local didnt work, store to session if available
			var db = sessionStorageRef();
			success = set_db(key,value,db);
			if(debug && !this.once1) {
				console.log("LOCAL FULL USING SESSION");
				this.once1 = true;
			}
		}
		
		if(!success && hashtable) { //if session failed, write to hashtable
			success = set_db(key,value,hashtable);
			if(debug && !this.once2) {
				console.log("SESSION FULL USING HASHTABLE");
				this.once2 = true;
			}
		}
		
		return success;
	};

	//==============================================================================
	// Private function that writes data to the specified db
	// TESTS: not necessary
	function set_db(key,value,db) {	
		if(db) {
			var val = prepareForStorage(value);
			try {
				db.setItem(key,val);
			} catch (err) {
				if (err.code == 22 || err.code==1014) { //safari,chrome=22 firefox=1014 memory full
					$('body').trigger("lowmemory");
				}
	    		//if(debug) console.log('Storage error', err);
	    		db.removeItem(key); //it failed to write, so we should delete whatever was there (partial write, or old copy)
	    		return false;
			}
		}
		return true;
	};

	//==============================================================================
	// remove the key from all databases (local, session, hashtable)
	// TESTS: yes
	function remove(key) {
		//console.log("rem",key);
		
		if(key==undefined) return;
		
		if(isLocal() && localAvailable()) {
			var db = localStorageRef();
			db.removeItem(key);
		}
		
		if(sessionAvailable()) {
			var db = sessionStorageRef();
			db.removeItem(key);
		}
		
		if(hashtable) {
			hashtable.removeItem(key);
		}
	};

	//==============================================================================
	// deletes everything from both local, session and hashtable storage
	// TESTS: yes
	function flush() {        
	    var session = sessionStorageRef();
	    if(session) session.clear();
	    
	    var local = localStorageRef();
	    if(local) local.clear();
	    
	    if(hashtable) hashtable.clear();
	};

	init();

	return {
		init: init, get: get, set:set
	};
}]); //end factory and module
/*!
 *	The above libary borrows heavily from jStore.  See below.
 *
 * jStore - Persistent Client-Side Storage
 * http://code.google.com/p/jquery-jstore/
 * Copyright (c) 2009 Eric Garside (http://eric.garside.name)
 * 
 * Dual licensed under:
 * 	MIT: http://www.opensource.org/licenses/mit-license.php
 *	GPLv3: http://www.opensource.org/licenses/gpl-3.0.html
 */