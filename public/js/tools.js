/*Tools not specific to site*/
//globalThis, used to capture global context.  "this" keyword refers the object the function is a method of (in this case, the global context object)
gThis = (function(){return this;})();

//+	compatibility {
//bind Introduced in JavaScript 1.8.5
//see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Function/bind
//Example: ;(function(bob){alert(bob)}.bind(null,'sue'))()
if (!Function.prototype.bind) {
	//expects new this or null, followed by arguments to prefix function call with
	Function.prototype.bind = function (oThis) {
		if (typeof this !== "function") {
		// closest thing possible to the ECMAScript 5 internal IsCallable function
		throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
		}
		
		var aArgs = Array.prototype.slice.call(arguments, 1), //slice from 1 to end
			fToBind = this, 
			fNOP = function () {},//used to maintain the "this" prototype for check against instanceof later
			fBound = function () {
				//.apply calls a function using a passed "this" and arguments
				return fToBind.apply(this instanceof fNOP && oThis //checks to see if original function "this" is same as new oThis, or if oThis is not present
									? this //in which case, use the original function "this"
									: oThis,
								aArgs.concat(Array.prototype.slice.call(arguments)));//get an array from the arguments object, and concatenate it to preset arguments array
			};
		
		fNOP.prototype = this.prototype;
		fBound.prototype = new fNOP();
		
		return fBound;
	};
}
//+	}






bf.tool = function(){};

/*when no control over parameters passed, add scope level and use it to define variables at parent level for use within fn. 
	capture context, use bf.makeEnvironment.call(this,...)
	Used XXX to avoid name conflict*/
bf.makeEnvironment = function (fnXXX,envXXX){ 
	for(kXXX in envXXX){
		eval('var '+kXXX+' = '+envXXX[kXXX].toSource())
	}
	//remake function in current context
	eval('var newFnXXX =  '+fnXXX)
	return newFnXXX;
}

/*handles multiple windows
reconsider logic when windowName provided and no such window*/
bf.windows = {};
bf.relocate = function(loc,windowName,type){
	//call window relocate function if window exists and unclosed
	if(windowName && bf.windows[windowName] && !bf.windows[windowName].closed){
		bf.windows[windowName].bf.relocate(loc)
		return false;
	}
	if(windowName){
		if(type == 'window'){
			bf.windows[windowName] =  window.open(loc,windowName,newWindow);
		}else{
			bf.windows[windowName] =  window.open(loc,windowName);
		}
		return false;
	}
	if(type == 'tab'){
		return window.open(loc,'_blank');
		
	}else if(type == 'window'){
		return window.open(loc,null,newWindow);
	}
	
	if(typeof(loc) == 'number'){
		if(loc == 0){
			window.location.reload(false);
		}else{
			history.go(loc);
		}
	}else if(!loc){
		window.location = window.location+'';
	}else{
		window.location = loc;
	}
	return false;
}


bf.setCookie = function(name, value, exp){
	var c = name + "=" +escape( value )+";path=/;";
	if(exp){
		var expire = new Date();
		expire.setTime( expire.getTime() + exp);
		c += "expires=" + expire.toGMTString();
	}
	document.cookie = c;
}
bf.readCookie = function(name) {
	var name = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
	}
	return null;
}

//take event object and get position
bf.eventPosition = function(e){;
	if (!e.pageX){
		var scroll = bf.scrollPosition()
		return {left:e.clientX + scroll.left,top:e.clientY + scroll.top};
	}
	return {left:e.pageX,top:e.pageY};
}

//find top left corner of element
bf.findPosition = function(ele){
	var curleft = curtop = 0;
	if(ele.offsetParent){
		do{
			curleft += ele.offsetLeft;
			curtop += ele.offsetTop
		}while(ele = ele.offsetParent);
	}
	return [curleft,curtop];
}

//get the current scroll position of the web page
bf.scrollPosition = function(){
	if(!self.pageXOffset){
		var left = (document.body.scrollLeft || document.documentElement.scrollLeft);
		var top = (document.body.scrollTop || document.documentElement.scrollTop);
		return {left:left,top:top};
	}
	return {left:self.pageXOffset,top:self.pageYOffset};
}


//check if value is in an array
bf.arr = function(){};
bf.arr.vInA = function(v,a){
	var i;
	for(i in a){
		if(a[i] == v){
			return true;
		}
	}
	return false;
}

bf.arr.rmV = function(v,a){
	var i
	for(i in a){
		if(a[i] == v){
			unset(a[i])
		}
	}
}

//only add if not pre-existing
bf.arr.addUnique = function(v,a){
	if(!bf.arr.vInA(v,a)){
		a[a.length] = v;
	}
}
//get the first available key in a sequential array
bf.arr.firstAvailableKey = function(a){
	for(var i = 0; i < a.length; i++){
		if(typeof(a[i]) == 'undefined'){
			return i;
		}
	}
	return a.length;
}
//insert on first available key in a sequential array.  Returns key.
bf.arr.inOnAvailable = function(v,a){
	var key = bf.arr.firstAvailableKey(a)
	a[key] = v;
	return key;
}

//get key of first existing element
bf.arr.firstTakenKey = function(a){
	for(var i = 0; i < a.length; i++){
		if(typeof(a[i]) != 'undefined'){
			return i;
		}
	}
	return false;
}

//get first existing element
bf.arr.getFirstTaken = function(a){
	var key = bf.arr.firstTakenKey(a)
	return a[key];
}

//When deleting array element, element still counts towards arrays length.  This function ignores those elements
bf.arr.countedLength = function(a){
	count = 0;
	for(var i in a){
		count += 1;
	}
	return count;
}

bf.obj = {};
//get first object element
bf.obj.firstV = function(obj){
	for(var i in obj){
		return obj[i];
	}
}
//get first object key
bf.obj.firstK = function(obj){
	for(var i in obj){
		return i;
	}
}

//set page focus to element
bf.viewFocus = function(ele){
	var offset = $(ele).offset();
	console.log(offset);
	if(offset){
		window.scroll(offset.left,offset.top);
	}
}



//+	url related functions {
	
bf.url = {}//move functions into this
//get query string request variable
bf.getRequestVar = function(name,decode){
	decode = decode != null ? decode : true;
	url = window.location+'';
	if(/\?/.test(url)){
		var uriParts = url.split('?');
		regexName = name.replace(/(\[|\])/g,"\\$1");
		var regex = new RegExp('(^|&)'+regexName+'=(.*?)(&|$)','g');
		var match = regex.exec(uriParts[1]);
		if(match){
			if(decode){
				return decodeURIComponent(match[2]);
			}
			return match[2];
		}
	}
	return false;
}

bf.getUrl = function(url){
	url = url != null ? url : window.location
	url = url+'';//convert to string for vars like window.location
	return url
}

///take a url and break it up in to page and key value pairs
bf.getUrlParts = function(url,decode){
	decode = decode != null ? decode : true;
	url = bf.getUrl(url)
	var retPairs = []
	var urlParts = url.split('?');
	if(urlParts[1]){
		var pairs = urlParts[1].split('&');
		if(pairs.length > 0){
			for(i in pairs){
				var pair = pairs[i]
				var retPair = pair.split('=');
				if(decode){
					retPair[0] = decodeURIComponent(retPair[0])
					retPair[1] = decodeURIComponent(retPair[1])
				}
				retPairs.push(retPair)
			}
		}
	}
	return {page:urlParts[0],pairs:retPairs}
}
///make a url from page and key value pairs
bf.getUrlFromParts = function(parts,encode){
	encode = encode != null ? encode : true;
	url = parts.page
	if(parts.pairs.length > 0){
		var pairs = []
		for(i in parts.pairs){
			var pair = parts.pairs[i]
			if(encode){
				pair[0] = encodeURIComponent(pair[0])
				pair[1] = encodeURIComponent(pair[1])
			}
			pairs.push(pair[0]+'='+pair[1])
		}
		query = pairs.join('&')
		url = url+'?'+query
	}
	return url
}

RegExp.quote = function(str) {
    return (str+'').replace(/([.?*+^$[\]\\(){}|-])/g, "\\$1");
}

///for removing parts from the url
bf.urlQueryFilter = function(regex,url,decode){
	var parts = bf.getUrlParts(url,decode)
	
	if(regex.constructor != RegExp){
		if(regex.constructor == String){
			regex = new RegExp('^'+RegExp.quote(regex)+'$','g');
		}else{
			//replace all
			regex = new RegExp('.*','g');
		}
	}
	if(parts.pairs.length > 0){
		var newPairs = []
		var foundPair = false
		for(i in parts.pairs){
			var pair = parts.pairs[i]
			if(!pair[0].match(regex)){
				newPairs.push(pair)
			}
		}
		parts.pairs = newPairs
	}
	return bf.getUrlFromParts(parts,decode)
}


//for adding variables to URL query string
bf.appendUrl = function(name,value,url,replace){
	replace = replace != null ? replace : false;
	
	if(replace){
		url = bf.urlQueryFilter(name,url)
	}
	var parts = bf.getUrlParts(url)
	parts.pairs.push([name,value])
	return bf.getUrlFromParts(parts)
}

/*
	pairs	either in form [[key,val],[key,val]] or {key:val,key:val}
*/
bf.appendsUrl = function(pairs,url,replace){
	for(i in pairs){
		if(typeof(pairs[i]) == 'object'){
			var key = pairs[i][0]
			var val = pairs[i][1]
		}else{
			var key = i
			var val = pairs[i]
		}
		url = bf.appendUrl(key,val,url,replace);
	}
	return url;
}
//+	}


//Count an objects properties
bf.count = function(object){
	var count = 0;
	for(var i in object){
		count++;
	}
	return count;
}

//find id in array of objects
bf.tool.findIdInObjArray = function(id,arr){
	for(i in arr){
		if(arr[i].id == id){
			return arr[i];
		}
	}
}

//Binary to decimal
bf.tool.bindec = function(bin){
	bin = (bin+'').split('').reverse();
	var dec = 0;
	for(var i = 0; i < bin.length; i++){
		if(bin[i] == 1){
			dec += Math.pow(2,i);
		}
	}
	return dec;
}


//Decimal to binary
bf.tool.decbin = function(dec){
	var bits = '';
	for(var into = dec; into >= 1; into = Math.floor(into / 2)){
		bits += into % 2;
	}
	var lastBit = Math.ceil(into);
	if(lastBit){
		bits += lastBit;
	}
	return bits.split('').reverse().join('');
}

bf.toInt = function(s){
	if(typeof(s) == 'string'){	
		s = s.replace(/[^0-9]+/g,' ');
		s = s.replace(/^ +/g,'');
		s = s.replace(/^0+/g,'');
		s = s.split(' ');
		num = parseInt(s[0]);
	}else{
		num = parseInt(s);
	}
	if(isNaN(num)){
		return 0;
	}
	return num;
}


bf.math = {};
bf.math.round = function(num, precision){
	var divider = new Number(bf.str.pad(1,precision+1,'0','right'))
	return Math.round(num * divider) / divider;
}

//will render string according to php rules
bf.str = function(str){
	if(typeof(str) == 'number'){
		return str+'';
	}
	if(!str){
		return '';
	}
	return str;
}
bf.str.pad = function(str,len,padChar,type){
	str = new String(str);
	if(!padChar){
		padChar = '0';
	}
	if(!type){
		type = 'left';
	}
	if(type == 'left'){
		while (str.length < len){
			str = padChar + str;
		}
	}else{
		while (str.length < len){
			str = str + padChar;
		}
	}
	return str;
}


//set words to upper case
bf.str.ucwords = function(string){
	if(string){
		string = string.split(' ');
		var newString = Array();
		var i = 0;
		$.each(string,function(){
			newString[newString.length] = this.substr(0,1).toUpperCase()+this.substr(1,this.length)
		});
		return newString.join(' ');
	}
}

//capitalize the string
bf.str.capitalize = function(string){
	return string.charAt(0).toUpperCase() + string.slice(1);
}
///htmlspecialchars()
bf.str.hsc = function(string){
	if(string === null){
		return ''
	}
	return $('<a></a>').text(string).html()
}


bf.toggle = function(value,values){
	if(value == values[0]) return values[1]
	return values[0]
}