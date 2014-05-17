<?
/// Useful array related functions I didn't, at one time, find in php
class Arrays{
	///If input is string, creates an array by splitting on commas
	/**
	@param	ignoreBlank	explode will create an array with one element no matter what, so ignore blank simple returns empty array if the var is blank
	*/
	static function stringArray($var,$ignoreBlank=false){
		if($ignoreBlank && !$var){
			return array();
		}
		if(!is_array($var)){
			return explode(',',$var);
		}
		return $var;
		
	}
	/// Checks if elemen of an arbitrarily deep array is set
	/**
	@param	keys array comma separated list of keys to traverse
	@param	array	array	The array which is parsed for the element
	*/
	static function isElement($keys,$array){
		$keys = self::stringArray($keys);
		$lastKey = array_pop($keys);
		$array = self::getElement($keys,$array);
		return isset($array[$lastKey]);
	}
	
	/// Gets an element of an arbitrarily deep array using list of keys for levels
	/**
	@param	keys array comma separated list of keys to traverse
	@param	array	array	The array which is parsed for the element
	@param	force	string	determines whetehr to create parts of depth if they don't exist
	*/
	static function getElement($keys,$array,$force=false){
		$keys = self::stringArray($keys);
		foreach($keys as $key){
			if(!isset($array[$key])){
				if(!$force){
					return;
				}
				$array[$key] = array();
			}
			$array = $array[$key];
		}
		return $array;
	}
	/// Same as getElement, but returns reference instead of value
	/**
	@param	keys array comma separated list of keys to traverse
	@param	array	array	The array which is parsed for the element
	@param	force	string	determines whetehr to create parts of depth if they don't exist
	*/
	static function &getElementReference($keys,&$array,$force=false){
		$keys = self::stringArray($keys);
		foreach($keys as &$key){
			if(!is_array($array)){
				$array = array();
				$array[$key] = array();
			}elseif(!isset($array[$key])){
				if(!$force){
					return;
				}
				$array[$key] = array();
			}
			$array = &$array[$key];
		}
		return $array;
	}
	
	/// Updates an arbitrarily deep element in an array using list of keys for levels
	/** Traverses an array based on keys to some depth and then updates that element
	@param	keys array comma separated list of keys to traverse
	@param	array	array	The array which is parsed for the element
	@param	value the new value of the element
	*/
	static function updateElement($keys,&$array,$value){
		$element = &self::getElementReference($keys,$array,true);
		$element = $value;
	}
	/// Same as updateElement, but sets reference instead of value
	/** Traverses an array based on keys to some depth and then updates that element
	@param	keys array comma separated list of keys to traverse
	@param	array	array	The array which is parsed for the element
	@param	reference the new reference of the element
	*/
	static function updateElementReference($keys,&$array,&$reference){
		$element = &self::getElementReference($keys,$array,true);
		$element = &$reference;
	}
	
	
	///finds all occurrences of value and replaces them in arbitrarily deep array
	static function replaceAll($value,$replacement,$array){
		foreach($array as &$element){
			if(is_array($element)){
				$element = self::replaceAll($value,$replacement,$element);
			}elseif($element == $value){
				$element = $replacement;
			}
		}
		unset($element);
		return $array;
	}
	///finds all occurrences of value and replaces parents (parent array of the value) in arbitrarily deep array
	static function replaceAllParents($value,$replacement,$array,$parentDepth=1){
		foreach($array as &$element){
			if(is_array($element)){
				$newValue = self::replaceAllParents($value,$replacement,$element,$parentDepth);
				if(is_int($newValue)){
					if($newValue == 1){
						$element = $replacement;
					}else{
						return $newValue - 1;
					}
				}else{
					$element = $newValue;
				}
			}elseif($element == $value){
				return (int)$parentDepth;
			}
		}
		unset($element);
		return $array;
	}
	/// takes an array and flattens it to one level using separator to indicate key deepness 
	/**
	@param	array	a deep array to flatten
	@param	separator	the string used to indicate in the flat array a level of deepenss between key strings
	@param	keyPrefix used to prefix the key at the current level of deepness
	@return	array
	*/
	static function flatten($array,$separator='_',$keyPrefix=null){
		foreach($array as $k=>$v){
			if($fK){
				$key = $keyPrefix.$separator.$k;
			}else{
				$key = $k;
			}
			if(is_array($v)){
				$sArrays = self::arrayFlatten($v,$key,$separator);
				foreach($sArrays as $k2 => $v2){
					$sArray[$k2] = $v2;
				}
			}else{
				$sArray[$key] = $v;
			}
		}
		return $sArray;
	}
	///Takes an arary of arbitrary deepness and turns the keys into tags and values into data
	/**
	@param	array	array to be turned into xml
	@param	depth	internal use
	*/
	function toXml($array,$depth=0){
		foreach($array as $k=>$v){
			if(is_array($v)){
				$v = arrayToXml($v);
			}
			$ele[] = str_repeat("\t",$depth).'<'.$k.'>'.$v.'</'.$k.'>';
		}
		return implode("\n",$ele);
	}
	///Set the keys equal to the values or vice versa
	/**
	@param array the array to be used
	@param	type	"key" or "value".  Key sets all the values = keys, value sets all the keys = values
	@return array
	*/
	static function setEqualKeysValues($array,$type='key'){
		if($type == 'key'){
			$array = array_keys($array);
			foreach($array as $v){
				$newA[$v] = $v;
			}
		}else{
			$array = array_values($array);
			foreach($array as $v){
				$newA[$v] = $v;
			}
		}
		return $newA;
	}
	///mergers if two arrays, else returns the existing array.  $y overwrites $x on matching keys
	static function merge($x,$y){
		if(is_array($x)){
			if(is_array($y)){
				return array_merge($x,$y);
			}else{
				return $x;
			}
		}else{
			return $y;
		}
	}
	///Merges two arrays using references, destroying the second array
	static function mergeInto(&$x,&$y){
		if(!is_array($x)){
			$x = array($x);
		}
		if(is_array($y)){
			foreach($y as $k=>&$v){
				if(is_int($k)){
					$x[] = &$v;
				}else{
					$x[$k] = &$v;
				}
				unset($v);
			}
			unset($y);
		}
	}
	///for an incremented key array, find first gap in key numbers, or use end of array
	static function firstAvailableKey($array){
		if(!is_array($array)){
			return 0;
		}
		$key = 0;
		ksort($array);
		foreach($array as $k=>$v){
			if($k != $key){
				return $key;
			}
			$key++;
		}
		return $key;
	}
	///remove non numbers from an array
	/**
	@param	list	either a flat array or a comma separated values
	*/
	static function filterNumberList($list){
		if(!is_array($list)){
			$list = Arrays::explode(',',$list);
		}
		$filteredList = array();
		foreach($list as $v){
			if(Tool::isInt($v)){
				$filteredList[] = $v;
			}
		}
		return $filteredList ? $filteredList : array();
	}
	///if false or null key, append.  Otherwise, add at key.  Optionally, append to key=>array().
	/**
	@param	key	can be null or key or array.  If null, value added to end of array
	@param	value	value to add to array
	@param	array	array that will be modified
	@param	append	if true, if keyed value already exists, ensure keyed value is array and add new value to array
	*/
	static function addOnKey($key,$value,&$array,$append=false){
		if($key !== null && $key !== false){
			if($append && isset($array[$key])){
				if(is_array($array[$key])){
					$array[$key][] = $value;
				}else{
					$array[$key] = array($array[$key],$value);
				}
			}else{
				$array[$key] = $value;
			}
			return $key;
		}else{
			$array[] = $value;
			return count($array) - 1;
		}
	}
	///adds to the array and overrides duplicate elements
	/**removes all instances of some value in an array then adds the value according to the key
	@param	value	the value to be removed then added
	@param	array	the array to be modified
	@param	key	the key to be used in the addition of the value to the array; if null, value added to end of array
	*/
	static function addOverride($value,&$array,$key=null){
		self::remove($value);
		self::addOnKey($key,$value,$array);
		return $array;
	}
	///removes all instances of value from an array
	/**
	@param	value	the value to be removed
	@param	array	the array to be modified
	*/
	static function remove(&$array,$value = false,$strict = false){
		$existingKey = array_search($value,$array);
		while($existingKey !== false){
			unset($array[$existingKey]);
			$existingKey = array_search($value,$array,$strict);
		}
		return $array;
	}
	///separate certain keys from an array and put them into another, returned array
	static function separate($keys,&$array){
		$separated = array();
		foreach($keys as $key){
			$separated[$key] = $array[$key];
			unset($array[$key]);
		}
		return $separated;
	}
	/// for an array of subarrays, newArray[subarray[key]] = subArrayPart.  IE, group subarray parts into keyed array.
	/**
	@param	array	array used to make the return array
	@param	key	key to use in the sub arrays of input array to be used as the keys of the output array
	@param	name	value to be used in the output array.  If not specified, the value defaults to the rest of the array apart from the key
	@return	key to name mapped array
	*/
	function subsOnKey($array,$key = 'id',$name=null){
		if(is_array($array)){
			$newArray = array();
			foreach($array as $part){
				$keyValue = $part[$key];
				if($name){
					$newArray[$keyValue] = $part[$name];
				}else{
					unset($part[$key]);
					if(count($part) > 1 ){
						$newArray[$keyValue] = $part;
					}else{
						$newArray[$keyValue] = array_pop($part);
					}
				}
			}
			return $newArray;
		}
		return array();
	}
	/// same as subsOnKey, but combines duplicate keys into arrays; keyed value is always and array
	function compileSubsOnKey($array,$key = 'id',$name=null){
		if(is_array($array)){
			$newArray = array();
			foreach($array as $part){
				$keyValue = $part[$key];
				if($name){
					$newArray[$keyValue][] = $part[$name];
				}else{
					unset($part[$key]);
					if(count($part) > 1 ){
						$newArray[$keyValue][] = $part;
					}else{
						$newArray[$keyValue][] = array_pop($part);
					}
				}
			}
			return $newArray;
		}
		return array();
	}
	
	///like the normal implode but ignores empty values
	static function implode($separator,$array){
		Arrays::remove($array);
		return implode($separator,$array);
	}
	///since you can't do something like bob()[1] (where bob() return array), instead do Arrays::at(bob(),1)
	///handles negative numbers as offset
	static function at($array,$index){
		if(Tool::isInt($index) && $index < 0){
			$array = array_slice($array,$index,1);
			return $array[0];
		}
		return $array[$index];
	}
	///takes an array of keys to extract out of one array and into another array
	static function &extract($keys,$extactee,&$extractTo=null,$forceKeys=true){
		if(!is_array($extractTo)){
			$extractTo = array();
		}
		foreach($keys as $key){
			if(array_key_exists($key,$extactee) || $forceKeys){ 
				$extractTo[$key] = $extactee[$key];
			}
		}
		return $extractTo;
	}
	///takes an array and maps its values to the keys of another array
	/**
		@param	map	array	array(to key => from key) 
			(new array key, in a sense, points to the old array key)
		@param	$numberDefault	wherein the key is a number, assume the value is both the key and the value
	*/
	static function &map($map,$extractee,&$extractTo=null,$numberDefault=false){
		if(!is_array($extractTo)){
			$extractTo = array();
		}
		if(!$numberDefault){
			foreach($map as $to=>$from){
				$extractTo[$to] = $extractee[$from];
			}
		}else{
			foreach($map as $to=>$from){
				if(is_int($to)){
					$extractTo[$from] = $extractee[$from];
				}else{
					$extractTo[$to] = $extractee[$from];
				}
			}
		}
		
		return $extractTo;
	}
	///checks if $subset is a sub set of $set starting at $start
	/*
	
		ex 1: returns true
			$subset = array('sue');
			$set = array('sue','bill');
		ex 2: returns false
			$subset = array('sue','bill','moe');
			$set = array('sue','bill');
	
	*/
	static function orderedSubset($subset,$set,$start=0){
		for($i=0;$i<$start;$i++){
			next($set);
		}
		while($v1 = current($subset)){
			if($v1 != current($set)){
				return false;
			}
			next($subset);
			next($set);
		}
		return true;
	}
	///count how many times a value is in an array
	static function countIn($value,$array,$max=null){
		$count = 0;
		foreach($array as $v){
			if($v == $value){
				$count++;
				if($max && $count == $max){
					return $max;
				}
			}
		}
		return $count;
	}
}
