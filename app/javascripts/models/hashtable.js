/*
	Hash table implementation for storing data in browser when localStorage is not available.
	Accessors mimic localStorage accessors.
	
	Copied from: http://www.mojavelinux.com/articles/javascript_hashes.html
	
	Copyright Toodledo 2011
*/



function Hashtable()
{
	this.length = 0;
	this.items = new Array();
	
	//can initialize hashtable via consturctor var hash = new Hashtable('one', 1, 'two', 2, 'three', 3);
	//TESTS: no
	for (var i = 0; i < arguments.length; i += 2) {
		if (typeof(arguments[i + 1]) != 'undefined') {
			this.items[arguments[i]] = arguments[i + 1];
			this.length++;
		}
	}
   
   	//wraps everything up into something that can be saved and returns it
	//TESTS: yes
	this.serialize = function() {
		var out = { };
		this.foreach(function(k,v) {
			out[k] = v;
		});
		
		return $.toJSON(out);
	}
	
	//restores a hashtable to a previous state with the passed in blob
	//TESTS: yes
	this.unserialize = function(str) {
		var obj = $.evalJSON(str);
		for (var i in obj) { 
			this.setItem(i,obj[i]);
		}
	}
   
	//removes an item and returns its value
	//TESTS: yes
	this.removeItem = function(in_key) {
		var tmp_previous;
		if (typeof(this.items[in_key]) != 'undefined') {
			this.length--;
			var tmp_previous = this.items[in_key];
			delete this.items[in_key];
		}
	   
		return tmp_previous;
	}

	//returns a value
	//TESTS: yes
	this.getItem = function(in_key) {
		return this.items[in_key];
	}

	//sets a value and returns the old value
	//TESTS: yes
	this.setItem = function(in_key, in_value) {
		var tmp_previous;
		if (typeof(in_value) != 'undefined') {
			if (typeof(this.items[in_key]) == 'undefined') {
				this.length++;
			} else {
				tmp_previous = this.items[in_key];
			}

			this.items[in_key] = in_value;
		}
	   
		return tmp_previous;
	}

	//true or false if the key exists
	//TESTS: yes
	this.hasItem = function(in_key) {
		return typeof(this.items[in_key]) != 'undefined';
	}

	//executes the callback for each item in the hashtable
	//TESTS: yes
	this.foreach = function(cb) {
		for (var i in this.items) { 
			var value = this.getItem(i);
			if(typeof(value)!="function") cb(i,value);
		}
	}
	
	//executes the callback for each item in the hashtable sorted by the sort function
	//Javascript cannot sort associative arrays, so we need to turn it into a numerical array with each element as a key/value object
	//TESTS: yes
	this.foreachSorted = function(sort,cb) {
		var sorted = new Array();
		this.foreach(function(k,v) {
			sorted.push({'k':k,'v':v});
		});
		sorted.sort(sort);
		
		var len = sorted.length;
		for(var i=0;i<len;i++) {
			cb(sorted[i].k,sorted[i].v);
		}
	}
	
	//flushing out the entire thing
	//TESTS: yes
	this.clear = function() {
		for (var i in this.items) {
			delete this.items[i];
		}

		this.length = 0;
	}
}