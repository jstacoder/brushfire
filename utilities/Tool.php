<?
///General tools for use anywhere
class Tool{
	///get the size of a directory
	/**
	@param	dir	path to a directory
	*/
	static function dirSize($dir){//directory size
		if(is_array($subs=scandir($dir))){
			$size = 0;
			$subs=array_slice($subs,2,count($subs)-2);
			if($sub_count=count($subs)){
				for($i=0;$i<$sub_count;$i++){
					$temp_sub=$dir.'/'.$subs[$i];
					if(is_dir($temp_sub)){
						$size+=Tool::dirSize($temp_sub);
					}else{
						$size+=filesize($temp_sub);
					}
				}
			}
			return $size;
		}
	}
	static $regexExpandCache = array();
	///expand a regex pattern to a list of characters it matches
	static function regexExpand($regex){
		$ascii = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~'."\t\n";
		if(!self::$regexExpandCache[$regex]){
			preg_match_all($regex,$ascii,$matches);
			self::$regexExpandCache[$regex] = implode('',$matches[0]);
		}
		return self::$regexExpandCache[$regex];
	}
	///generate a random string
	/**
	@note this function is overloaded and can take either two or three params.
	@param	1	length, min length
	@param	2	length max, regex pattern
	@param	3	regex pattern
	
	Regex pattern:  Can evaluate to false (which defaults to alphanumeric).  Shoudl be with delimeter: Ex "#[a-z]#i"
	
	@return	random string matching the regex pattern
	*/
	static function randomString(){
		$args = func_get_args();
		if(func_num_args() >= 3){
			$length = rand($args[0],$args[1]);
			$match = $args[2];
		}else{
			$length = $args[0];
			//In case this is 3 arg overloaded with $match null for default
			if(!is_int($args[1])){
				$match = $args[1];
			}
		}
		if(!$match){
			$match = '@[a-z0-9]@i';
		}
		$allowedChars = self::regexExpand($match);
		$range = strlen($allowedChars) - 1;
		for($i=0;$i<$length;$i++){
			$string .= $allowedChars[mt_rand(0,$range)];
		}
		return $string;
	}
	
	///used for time based synchronization
	static function then($name=1){
		if(self::$then[$name]){
			return self::$then[$name];
		}
		return self::$then[$name] = time();
	}
	///pluralized a word.  Limited abilities.
	/**
	@param	word	word to pluralize
	@return	pluralized form of the word
	*/
	static function pluralize($word){
		if(substr($word,-1) == 'y'){
			return substr($word,0,-1).'ies';
		}
		if(substr($word,-1) == 'h'){
			return $word.'es';
		}
		return $word.'s';
	}
	///capitalize first letter in certain words
	/**
	@param	string	string to capitalize
	@return	a string various words capitalized and some not
	*/
	static function capitalize($string){
		$exclude = array('to', 'the', 'in', 'at', 'for', 'or', 'and', 'so', 'with', 'if', 'a', 'an', 'of', 
			'to', 'on', 'with', 'by', 'from', 'nor', 'not', 'after', 'when', 'while');
		$fullCap = array('cc');
		$words = preg_split('@[\t ]+@',$string);
		foreach($words as &$v){
			if(in_array($v,$fullCap)){
				$v = strtoupper($v);
			}elseif(!in_array($v,$exclude)){
				$v = ucfirst($v);
			}
		}unset($v);
		return implode(' ',$words);
	}
	///turns a camelCased string into a character separated string
	/**
	@note	consecutive upper case is kept upper case
	@param	string	string to morph
	@param	separater	string used to separate
	@return	underscope separated string
	*/
	static function camelToSeparater($string,$separater='_'){
		$string = preg_replace('@(?<![A-Z])[A-Z](?![A-Z])@e','\''.addslashes($separater).'\'.strtolower("$0")',$string);
		//although accronyms are not lower cased, they are block separated
		return preg_replace('@(?<![A-Z])[A-Z]@e','\''.addslashes($separater).'\'."$0"',$string);
	}
	
	///turns a string into a camel cased string
	/**
	@param	string	string to camelCase
	*/
	static function toCamel($string){
		preg_match('@[ _]*[^ _]*@',$string,$match);
		$firstWord = strtolower($match[0]);
		$cString = $firstWord;
		preg_match_all('@[ _]+([^ _]+)@',$string,$match);
		if($match[1]){
			foreach($match[1] as $word){
				$cString .= ucfirst($word);
			}
		}
		return $cString;
	}
	///take string and return the accronym
	static function acronym($string,$separaterPattern='@[_ ]+@',$seperater=''){
		$parts = preg_split($separaterPattern,$string);
		foreach($parts as $part){
			$acronym[] = $part[0];
		}
		return implode($seperater,$acronym);
	}
	///determines if string is a float
	static function isFloat($x){
		if((string)(float)$x == $x){
			return true;
		}
	}
	///determines if a string is an integer.  Limited to php int size
	static function isInt($x){
		if(is_int($var)){
			return true;
		}
		if((string)(int)$x == $x && $x !== true & $x !== false && $x !== null){
			return true;
		}
	}
	///determines if a string is an integer.  Not limited to php int size
	static function isInteger($x){
		if(self::isInt($x)){
			return true;
		}
		if(preg_match('@\s*[0-9]+\s*@',$x)){
			return true;
		}
	}
	///escapes the delimiter and delimits the regular expression.
	/**If you already have an expression which has been preg_quoted in all necessary parts but without concern for the delimiter
	@string	string to delimit
	@delimiter	delimiter to use.  Don't use a delimiter quoted by preg_quote
	*/
	static function pregDelimit($string,$delimiter='@'){
		return $delimiter.preg_replace('/\\'.$delimiter.'/', '\\\\\0', $string).$delimiter;
	}
	///checks if there is a regular expression error in a string
	/**
	@regex	regular expression including delimiters
	@return	false if no error, else string error
	*/
	static $regexError;
	static function regexError($regex){
		$currentErrorReporting = error_reporting();
		error_reporting($current & ~E_WARNING);
		
		set_error_handler(array('self','captureRegexError'));
	
		preg_match($regex,'test');
		
		error_reporting($currentErrorReporting);
		restore_error_handler();
		
		if(self::$regexError){
			$return = self::$regexError;
			self::$regexError == null;
			return $return;
		}
	}
	///temporary error catcher used with regexError
	static function captureRegexError($code,$string){
		self::$regexError = $string;
	}
	///quote a preg replace string
	static function pregQuoteReplaceString($str) {
		return preg_replace('/(\$|\\\\)(?=\d)/', '\\\\\1', $str);
	}
	///test matches against subsequent regex
	/**
	@param	subject	text to be searched
	@param	regexes	patterns to be matched.  A "!" first character, before the delimiter, negates the match on all but first pattern
	*/
	static function pregMultiMatchAll($subject,$regexes){
		$first = array_shift($regexes);
		preg_match_all($first,$subject,$matches,PREG_SET_ORDER);
		foreach($matches as $k=>$match){
			foreach($regexes as $regex){
				if(substr($regex,0,1) == '!'){
					if(preg_match(substr($regex,1),$match[0])){
						unset($matches[$k]);
					}
				}else{
					if(!preg_match($regex,$match[0])){
						unset($matches[$k]);
					}
				}
			}
		}
		return $matches;
	}
	static function matchAny($regexes,$subject){
		foreach($regexes as $regex){
			if(preg_match($regex,$subject)){
				return true;
			}
		}
	}
	///translate human readable size into bytes
	static function byteSize($string){
		preg_match('@(^|\s)([0-9]+)\s*([a-z]{1,2})@i',$string,$match);
		$number = $match[2];
		$type = strtolower($match[3]);
		switch($type){
			case 'k':
			case 'kb':
				return $number * 1024;
			break;
			case 'mb':
			case 'm':
				return $number * 1048576;
			break;
			case 'gb':
			case 'g':
				return $number * 1073741824;
			break;
			case 'tb':
			case 't':
				return $number * 1099511627776;
			break;
			case 'pb':
			case 'p':
				return $number * 1125899906842624;
			break;
		}
	}
	///like the normal implode but removes empty values
	static function explode($separator,$string){
		$array = explode($separator,$string);
		Arrays::remove($array);
		return array_values($array);
	}
	///does not care whether relative folders exist (unlike file include functions).  Does not work when |relative-to object| not given
	///Found here b/c can be applied to HTTP paths, not just file paths
	static function absolutePath($pathParts){
		if(!is_array($pathParts)){
			$pathParts = explode('/',$pathParts);
		}
		$absParts = array();
		foreach($pathParts as $pathPart){
			if($pathPart == '..'){
				array_pop($absParts);
			}elseif($pathPart != '.'){
				$absParts[] = $pathPart;
			}
		}
		return implode('/',$absParts);
	}
	///escape various characters with slashes (say, for quoted csv's)
	static function slashEscape($text,$characters='\"'){
		return preg_replace('@['.preg_quote($characters).']@','\\\$0',$text);
	}
	///unescape the escape function
	static function slashUnescape($text,$characters='\"'){
		return preg_replace('@\\\(['.preg_quote($characters).'])@','$1',$text);
	}
}