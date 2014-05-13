tp.debug = function(){};
tp.debug.handleError = function(msg,file,line){
	alert('MSG: '+msg+"\nFILE: "+file+"\nLINE: "+line);
}

tp.backtrace = function(fn){
		if(!fn){
			fn = arguments.callee.caller
		}else{
			fn = fn.caller
		}
		if(!fn){
			return "";
		}

		var trace = tp.functionName(fn);

		trace="(";
		var args = [];
		for(var arg in fn.arguments){
			tp.arr.add(fn.arguments.toString(),args);
		}
		trace += '('+args.join(',')+")\n";
		return trace + tp.backtrace(fn);
}

tp.functionName = function(fn){
	var name=/\W*function\s+([\w\$]+)\(/.exec(fn);
	if(!name){
		return 'No name';
	}
	return name[1];
}
tp.alertAll = function(obj){
	for(i in obj){
		alert(i+' : '+obj);
	}
}


/**
-- Not written by me

 * Function : dump()
 * Arguments: The data - array,hash(associative array),object
 *    The level - OPTIONAL
 * Returns  : The textual representation of the array.
 * This function was inspired by the print_r function of PHP.
 * This will accept some data as the argument and return a
 * text that will be a more readable version of the
 * array/hash/object that is given.
 * Docs: http://www.openjs.com/scripts/others/dump_function_php_print_r.php
 */

tp.dump = function(arr,level) {
	var dumped_text = "";
	if(!level) level = 0;
	
	//The padding given at the beginning of the line.
	var level_padding = "";
	for(var j=0;j<level+1;j++) level_padding += "    ";
	
	if(typeof(arr) == 'object') { //Array/Hashes/Objects 
		for(key in arr){
			var value = arr[key]
			if(typeof(value) == 'object') { //If it is an array,
				dumped_text += level_padding + "'" + key + "' ...\n";
				dumped_text += tp.dump(value,level+1);
			} else {
				dumped_text += level_padding + "'" + key + "' => \"" + value + "\"\n";
			}
		}
	} else { //Stings/Chars/Numbers etc.
		dumped_text = "===>"+arr+"<===("+typeof(arr)+")";
	}
	return dumped_text;
}

//useful for quickly printing out non string or string variable
tp.alert = function(x){
	alert(tp.dump(x));
}

