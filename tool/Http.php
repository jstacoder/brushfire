<?
class Http{
	///parse a query string using a more standard less php specific rule (all repeated tokens turn into arrays, not just tokens with "[]")
	/**
	You can have this function include php field special syntax along with standard parsing.
	@param	string	string that matches form of a url query string
	@param	specialSyntax	whether to parse the string using php rules (where [] marks an array) in addition to "standard" rules
	*/
	function parseQuery($string,$specialSyntax = false){
		$parts = Tool::explode('&',$string);
		$array = array();
		foreach($parts as $part){
			list($key,$value) = explode('=',$part);
			$key = urldecode($key);
			$value = urldecode($value);
			if($specialSyntax && ($matches = self::getSpecialSyntaxKeys($key))){
				if(Arrays::isElement($matches,$array)){
					$currentValue = Arrays::getElement($matches,$array);
					if(is_array($currentValue)){
						$currentValue[] = $value;
					}else{
						$currentValue = array($currentValue,$value);
					}
					Arrays::updateElement($matches,$array,$currentValue);
				}else{
					Arrays::updateElement($matches,$array,$value);
				}
				unset($match,$matches);
			}else{
				if($array[$key]){
					if(is_array($array[$key])){
						$array[$key][] = $value;
					}else{
						$array[$key] = array($array[$key],$value);
					}
				}else{
					$array[$key] = $value;
				}
			}
		}
		return $array;
	}
	function buildQuery($array){
		$standard = array();
		foreach($array as $k=>$v){
			//exclude standard array handling from php array handling
			if(is_array($v) && !preg_match('@\[.*\]$@',$k)){
				$key = urlencode($k);
				foreach($v as $v2){
					$standard[] = $key.'='.urlencode($v2);
				}
				unset($array[$k]);
			}
		}
		$phpquery = http_build_query($array);
		$standard = implode('&',$standard);
		return Arrays::implode('&',array($phpquery,$standard));
	}
	///get all the keys invovled in a string that represents an array.  Ex: "bob[sue][joe]" yields array('bob','sue','joe')
	function getSpecialSyntaxKeys($string){
		if(preg_match('@^([^\[]+)((\[[^\]]*\])+)$@',$string,$match)){
			//match[1] = array name, match[2] = all keys
			
			//get names of all keys
			preg_match_all('@\[([^\]]*)\]@',$match[2],$matches);
			
			//add array name to beginning of keys list
			array_unshift($matches[1],$match[1]);
			
			//clear out empty key items
			Arrays::remove($matches[1],'',true);
			
			return $matches[1];
		}
	}
	///appends multiple (key=>value)s to a url, replacing any key values that already exist
	/**
	@param	kvA	array of keys to values array(key1=>value1,key2=>value2)
	@param	url	url to be appended
	*/
	static function appendsUrl($kvA,$url=null,$replace=true){
		foreach($kvA as $k=>$v){
			if(is_array($v)){
				foreach($v as $subv){
					$url = self::appendUrl($k,$subv,$url,$replace);
				}
			}else{
				$url = self::appendUrl($k,$v,$url,$replace);
			}
		}
		return $url;
	}
	///appends name=value to query string, replacing them if they already exist
	/**
	@param	name	name of value
	@param	value	value of item
	@param	url	url to be appended
	*/
	static function appendUrl($name,$value,$url=null,$replace=true){
		if(!isset($url)){
			$url = $_SERVER['REQUEST_URI'];
		}
		$add = urlencode($name).'='.urlencode($value);
		if(preg_match('@\?@',$url)){
			$urlParts = explode('?',$url,2);
			if($replace){
				//remove previous occurrence
				$urlParts[1] = preg_replace('@(^|&)'.preg_quote(urlencode($name)).'=(.*?)(&|$)@','$3',$urlParts[1]);
				if($urlParts[1][0] == '&'){
					$urlParts[1] = substr($urlParts[1],1);
				}
			}
			if($urlParts[1] != '&'){
				return $urlParts[0].'?'.$urlParts[1].'&'.$add;
			}
			return $urlParts[0].'?'.$add;
		}
		return $url.'?'.$add;
	}
	/**
	Removes key value pairs from url where key matches some regex.
	@param	regex	The regex to use for key matching.  If the regex does not contain the '@' for the regex delimiter, it is assumed the input is not a regex and instead just a string to be matched exactly against the key.  IE, '@bob@' will be considered regex while 'bob' will not
	*/
	static function removeFromQuery($regex,$url=null){
		if(!isset($url)){
			$url = urldecode($_SERVER['REQUEST_URI']);
		}
		if(!preg_match('@\@@',$regex)){
			$regex = '@^'.preg_quote($regex,'@').'$@';
		}
		$urlParts = explode('?',$url,2);
		if($urlParts[1]){
			$pairs = explode('&',$urlParts[1]);
			$newPairs = array();
			foreach($pairs as $pair){
				$pair = explode('=',$pair,2);
				#if not removed, include
				if(!preg_match($regex,urldecode($pair[0]))){
					$newPairs[] = $pair[0].'='.$pair[1];
				}
			}
			$url = $urlParts[0].'?'.implode('&',$newPairs);
		}
		return $url;
	}
	//resolves relative url paths into absolute url paths
	public function getAbsoluteUrl($url,$relativePath=null){
		$parts = explode('?',$url);
		preg_match('@(^.*?://.*?)(/.*$|$)@',$parts[0],$match);
		if(!$match){
			//url is completely relative, use relativePath as base
			$rParts = explode('?',$relativePath);
			preg_match('@(^.*?://.*?)(/.*$|$)@',$rParts[0],$match);
			$pathParts = explode('/',$match[2]);
			
			if($parts[0]){
				if($parts[0][0] == '/'){
					//relative to base of site
					$base = $pathParts[0];
					$pathParts = explode('/',$base.$parts[0]);
				}else{
					//relative to directory, so clear page part.   ie url = "view.php?m=bob"
					array_pop($pathParts);
					$pathParts = implode('/',$pathParts).'/'.$parts[0];
					$pathParts = explode('/',$pathParts);
				}
			}
		}else{
			$pathParts = explode('/',$match[2]);
		}
		$path = Tool::absolutePath($pathParts);
		$url = $match[1].$path;
		if($parts[1]){
			$url .= '?'.$parts[1];
		}
		return $url;
	}
	///relocate browser
	/**
	@param	location	location to relocate to
	@param	type	type of relocation; head for header relocation, js for javascript relocation
	*/
	static function redirect($location=null,$type='head'){
		if($type == 'head'){
			if(!$location){
				$location = $_SERVER['REQUEST_URI'];
			}
			header('Location: '.$location);
		}elseif($type=='js'){
			echo '<script type="text/javascript">';
			if(Tool::isInt($location)){
				if($location==0){
					$location = $_SERVER['REQUEST_URI'];
					echo 'window.location = '.$_SERVER['REQUEST_URI'].';';
				}else{
					echo 'javascript:history.go('.$location.');';
				}
			}else{
				echo 'document.location="'.$location.'";';
			}
			echo '</script>';
		}
		exit;
	}
	static $ip;
	///Get the ip at a given point in either HTTP_X_FORWARDED_FOR or just REMOTE_ADDR
	/**
	$config['loadBalancerIps'] is removed from 	HTTP_X_FORWARDED_FOR, after which slicePoint applies
	*/ 
	static function getIp($slicePoint=-1){
		if(!self::$ip){
			if($_SERVER['HTTP_X_FORWARDED_FOR']){
				#get first ip (should be client's ip)
				#X-Forwarded-For: clientIPAddress, previousLoadBalancerIPAddress-1, previousLoadBalancerIPAddress-2
				$ips = preg_split('@\s*,\s*@',$_SERVER['HTTP_X_FORWARDED_FOR']);
				if(Config::$x['loadBalancerIps']){
					$ips = array_diff($ips,Config::$x['loadBalancerIps']);
				}
				
				self::$ip = array_pop(array_slice($ips,$slicePoint,1));
				//make sure ip conforms (since this is a header variable that can be manipulated)
				if(!preg_match('@[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}@',self::$ip)){
					self::$ip = $_SERVER['REMOTE_ADDR'];
				}
			}else{
				self::$ip = $_SERVER['REMOTE_ADDR'];
			}
		}
		return self::$ip;
	}
}