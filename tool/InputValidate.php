<?
class InputValidate{
	static $errorMessages = array(
//+	basic validators{
			'exists' => 'Missing field {_FIELD_}',
			'filled' => 'Missing field {_FIELD_}',
			'inTable' => 'No record of {_FIELD_} found',
			'notInTable' => 'A record of {_FIELD_} already present',
			'isInteger' => '{_FIELD_} must be an integer',
			'isFloat' => '{_FIELD_} must be a decimal',
			'regex' => '{_FIELD_} must match %s',
			'key' => '{_FIELD_} did not contain an accepted value',
			'in' => '{_FIELD_} did not contain an accepted value',
			'email' => '{_FIELD_} must be a valid email',
			'emailLine' => '{_FIELD_} did not match the format "NAME &lt;EMAIL&gt;',
			'url' => '{_FIELD_} must be a URL',
			'range_max' => '{_FIELD_} must be %s or less',
			'range_min' => '{_FIELD_} must be %s or more',
			'length' => '{_FIELD_} must be a of a length equal to %s',
			'lengthRange_max' => '{_FIELD_} must have a length of %s or less',
			'lengthRange_min' => '{_FIELD_} must have a length of %s or more',
			'date' => '{_FIELD_} must be a date.  Most date formats are accepted',
			'timezone' => '{_FIELD_} must be a timezone',
			'noTagIntegrity' => '{_FIELD_} is lacking HTML Tag context integrity.  That might pass on congress.gov, but not here.',
			'value' => '{_FIELD_} does not match expected value',
			'mime' => '{_FIELD_} must have one of the following mimes: %s',
			'notMime' => '{_FIELD_} must not have any of the following mimes: %s',
//+	}
//+	More specialized validators{			
			'phone_area' => 'Please include an area code in {_FIELD_}',
			'phone_check' => 'Please check {_FIELD_}',
			'zip' => '{_FIELD_} was malformed',
			'age_max' => '{_FIELD_} too old.  Must be at most %s',
			'age_min' => '{_FIELD_} too recent.  Must be at least %s',
//+	}
		);
	///true or false return instead of exception
	/**
	@param	method	method  to call
	@param	args...	anything after method param is passed to method
	*/
	static function check(){
		$args = func_get_args();
		$method = array_shift($args);
		try{
			call_user_func_array(array('self',$method),$args);
			return true;
		}catch(InputException $e){
			return false;
		}
	}
//+	basic validators{
	static function exists(&$value){
		if(!isset($value)){
			Debug::toss(self::$errorMessages['exists'],'InputException');
		}
	}
	static function filled(&$value){
		if(!isset($value) || $value === ''){
			Debug::toss(self::$errorMessages['filled'],'InputException');
		}
	}
	static function inTable(&$value,$table,$field='id'){
		if(!Db::check($table,array($field=>$value))){
			Debug::toss(self::$errorMessages['inTable'],'InputException');
		}
	}
	static function notInTable(&$value,$table,$field='id'){
		if(Db::check($table,array($field=>$value))){
			Debug::toss(self::$errorMessages['notInTable'],'InputException');
		}
	}
	static function isInteger(&$value){
		if(!Tool::isInt($value)){
			Debug::toss(self::$errorMessages['isInteger'],'InputException');
		}
	}
	static function isFloat(&$value){
		if(filter_var($value, FILTER_VALIDATE_FLOAT) === false){
			Debug::toss(self::$errorMessages['isFloat'],'InputException');
		}
	}
	static function value(&$value,$match){
		if($value !== $match){
			Debug::toss(self::$errorMessages['value'],'InputException');
		}
	}
	static function regex(&$value,$regex,$matchModel=null){
		if(!preg_match($regex,$value)){
			if(!$matchModel){
				$matchModel = Tool::regexExpand($regex);
			}
			Debug::toss(sprintf(self::$errorMessages['regex'],'"'.$matchModel.'"'),'InputException');
		}
	}
	static function key(&$value,$array){
		if(!isset($array[$value])){
			Debug::toss(self::$errorMessages['key'],'InputException');
		}
	}
	///see if value is in array.  Either array as 2nd parameter, or taken as remaining parameters
	static function in(&$value){
		$args = func_get_args();
		array_shift($args);
		if(is_array($args[0])){
			$array = $args[0];
		}else{
			$array = $args;
		}
		if(!in_array($value,$array)){
			Debug::toss(self::$errorMessages['in'],'InputException');
		}
	}
	static function email($value){
		if(!filter_var($value, FILTER_VALIDATE_EMAIL)){
			Debug::toss(self::$errorMessages['email'],'InputException');
		}
	}
	//potentially including name: joe johnson <joe@bob.com>
	static function emailLine($value){
		if(!self::check('email',$value)){
			preg_match('@<([^>]+)>@',$value,$match);
			$email = $match[1];
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				Debug::toss(self::$errorMessages['email'],'InputException');
			}
			if(!preg_match('@^[a-z0-9 _\-.]+<([^>]+)>$@i',$value)){
				Debug::toss(self::$errorMessages['emailLine'],'InputException');
			}
		}
	}
	static function url($value){
		if(!filter_var($value, FILTER_VALIDATE_URL)){
			Debug::toss(self::$errorMessages['url'],'InputException');
		}
		//the native filter doesn't even check if there is at least one dot (tld detection)
		if(strpos($value,'.') == false){
			Debug::toss(self::$errorMessages['url'],'InputException');
		}
	}
	static function range($value,$min=null,$max=null){
		if($max !== '' && $max !== null && $value > $max){
			Debug::toss(sprintf(self::$errorMessages['range_max'],$max),'InputException');
		}
		if($min !== '' && $min !== null && $value < $min){
			Debug::toss(sprintf(self::$errorMessages['range_min'],$min),'InputException');
		}
	}
	static function length($value,$length){
		$actualLength = strlen($value);
		if($actualLength != $length){
			Debug::toss(sprintf(self::$errorMessages['length'],$length),'InputException');
		}
	}
	static function lengthRange($value,$min=null,$max=null){
		$actualLength = strlen($value);
		if(Tool::isInt($max) && $actualLength > $max){
			Debug::toss(sprintf(self::$errorMessages['lengthRange_max'],$max),'InputException');
		}
		if(Tool::isInt($min) && $actualLength < $min){
			Debug::toss(sprintf(self::$errorMessages['lengthRange_min'],$min),'InputException');
		}
	}
	static function timezone($value){
		try {
			new DateTimeZone($value);
		} catch(Exception $e) {
			Debug::toss(self::$errorMessages['timezone'],'InputException');
		}
	}
	
	static function date($value){
		try{
			new Time($value);
		}catch(Exception $e){
			Debug::toss(self::$errorMessages['date'],'InputException');
		}
	}
	/**
	@param	mimes	array of either whole mimes "part/part", or the last part of the mime "part"
	*/
	static function mime($v,$name,$mimes){
		$mimes = Arrays::stringArray($mimes);
		$mime = File::mime($_FILES[$name]['tmp_name']);
		foreach($mimes as $matchMime){
			if(preg_match('@'.preg_quote($matchMime).'$@',$mime)){
				return true;
			}
		}
		$mimes = implode(', ',$mimes);
		Debug::toss(sprintf(self::$errorMessages['mime'],$mimes),'InputException');
	}
	/**
	@param	mimes	array of either whole mimes "part/part", or the last part of the mime "part"
	*/
	static function notMime($v,$name,$mimes){
		$mimes = Arrays::stringArray($mimes);
		$mime = File::mime($_FILES[$name]['tmp_name']);
		foreach($mimes as $matchMime){
			if(preg_match('@'.preg_quote($matchMime).'$@',$mime)){
				$mimes = implode(', ',$mimes);
				Debug::toss(sprintf(self::$errorMessages['notMime'],$mimes),'InputException');
			}
		}
		return true;
	}
//+	}
//+	specialized validators{
	static function zip($value){
		if (!preg_match("/^([0-9]{5})(-[0-9]{4})?$/i",$value)) {
			Debug::toss(self::$errorMessages['zip'],'InputException');
		}
	}
	static function phone(&$value){
		if(strlen($value) == 11 && substr($value,0,1) == 1){
			$value = substr($value,1);
		}
		if(strlen($value) == 7){
			Debug::toss(self::$errorMessages['phone_area'],'InputException');
		}
		
		if(strlen($value) != 10){
			Debug::toss(self::$errorMessages['phone_check'],'InputException');
		}
	}
	
	static function age($value,$min=null,$max=null){
		$time = new Time($value);
		$age = $time->diff(new Time('now'));
		if(Tool::isInt($max) && $age->y > $max){
			Debug::toss(sprintf(self::$errorMessages['age_max'],$max),'InputException');
		}
		if(Tool::isInt($min) && $age->y < $min){
			Debug::toss(sprintf(self::$errorMessages['age_min'],$min),'InputException');
		}
	}
	static function htmlTagContextIntegrity($value){
		preg_replace_callback('@(</?)([^>]+)(>|$)@',array(self,'htmlTagContextIntegrityCallback'),$value);
		//tag hierarchy not empty, something wasn't closed
		if(self::$tagHierarchy){
			Debug::toss(self::$errorMessages['noTagIntegrity'],'InputException');
		}
	}
	static $tagHierarchy = array();
	static function htmlTagContextIntegrityCallback($match){
		preg_match('@^[a-z]+@i',$match[2],$tagMatch);
		$tagName = $tagMatch[0];
		
		if($match[1] == '<'){
			//don't count self contained tags
			if(substr($match[2],-1) != '/'){
				self::$tagHierarchy[] = $tagName;
			}
		}else{
			$lastTagName = array_pop(self::$tagHierarchy);
			if($tagName != $lastTagName){
				Debug::toss(self::$errorMessages['noTagIntegrity'],'InputException');
			}
		}
	}
}
//+	}
