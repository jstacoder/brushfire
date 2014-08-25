<?
class InputFilter{
	static function apply($value){
		$args = func_get_args();
		$method = array_shift($args);
		return call_user_func_array(array('self',$method),$args);
	}
	
	///filter to boolean
	static function toBool(&$value){
		return $value = (bool)$value;
	}
	///filter to integer
	static function toInt(&$value){
		return $value = (int)$value;
	}
	///filter to absolute integer
	static function toAbsoluteInt(&$value){
		return $value = abs($value);
	}
	///filter to float
	static function toDecimal(&$value){
		return $value = (float)$value;
	}
	///filter all but digits
	static function toDigits(&$value){
		return $value = preg_replace('@[^0-9]@','',$value);
	}
	static function regexReplace(&$value,$regex,$newValue=''){
		return $value = preg_replace($regex,$newValue,$value);
	}
	static function toUrl(&$value){
		$value = trim($value);
		if(substr($value,0,4) != 'http'){
			$value = 'http://'.$value;
		}
		return $value;
	}
	static function toString(&$value){
		while(is_array($value)){
			$value = array_shift($value[0]);
		}
		return $value;
	}
	static function toName(&$value){
		InputFilter::trim($value);
		InputFilter::regexReplace($value,'@ +@',' ');
		$value = preg_split('@, *@', $value);
		array_reverse($value);
		$value = implode(' ',$value);
		InputFilter::regexReplace($value,'@[^a-z \']@i');
		return $value;
	}
	static function trim(&$value){
		return $value = trim($value);
	}
	static function toDate(&$value,$inOutTz=null){
		$inOutTz = $inOutTz ? $inOutTz : $_ENV['inOutTimezone'];
		return $value = (new Time($value,$inOutTz))->setZone($_ENV['timezone'])->date();
	}
	static function toDatetime(&$value,$inOutTz=null){
		$inOutTz = $inOutTz ? $inOutTz : $_ENV['inOutTimezone'];
		return $value = (new Time($value,$inOutTz))->setZone($_ENV['timezone'])->datetime();
	}
	static function toDefault(&$value,$default){
		if($value === null || $value === ''){
			$value = $default;
		}
		return $value;
	}
	static function toEmail(&$value){
		preg_match('@<([^>]+)>@',$value,$match);
		if(!$match){
			return $value;
		}
		$email = $match[1];
		return $email;
	}
	///on fields which may contain html, if they contain certain html, don't do nl to br
	static function conditionalNl2Br(&$value){
		if(!preg_match('@<div|<p|<table@',$value)){
			$value = preg_replace('@\r\n|\n|\r@','<br/>',$value);//nl2br doesn't remove newlines
		}
		return $value;
	}
	
	static $stripTagsAllowableTags;
	static $stripTagsAllowableAttributes;
	//doesn't verify start end tag context integrity.  Use validator htmlTagContextIntegrity
	static function stripTags(&$value,$allowableTags=null,$allowableAttributes=null){
		self::$stripTagsAllowableTags = Arrays::stringArray($allowableTags);
		self::$stripTagsAllowableAttributes = $allowableAttributes;
		return $value = preg_replace_callback('@(</?)([^>]+)(>|$)@',array(self,'stripTagsCallback'),$value);
	}
	static function stripTagsCallback($match){
		preg_match('@^[a-z]+@i',$match[2],$tagMatch);
		if($tagMatch){
			$tag = $match[0];
			$tagName = $tagMatch[0];
			if(!in_array($tagName,self::$stripTagsAllowableTags)){
				return '';
			}
			
			if($match[1] == '<'){
				//allow some appropriate attributes on opening tags
				$attributes = self::getAttributes($match[0],self::$stripTagsAllowableAttributes);
				
				if(substr($match[2],-1) == '/'){
					$close = ' />';
				}else{
					$close = '>';
				}
				if($callback){
					call_user_func_array($callback,array(&$tagMatch[0],&$attributes));
				}
				return '<'.$tagMatch[0].($attributes ? ' '.implode(' ',$attributes) : '').$close;
			}else{
				if($callback){
					call_user_func_array($callback,array(&$tagMatch[0]));
				}
				return '</'.$tagMatch[0].'>';
			}
		}else{
			return '';
		}
	}
	static function getAttributes($tag,$attributes){
		$attributes = Arrays::stringArray($attributes);
		$collected = array();
		foreach($attributes as $attribute){
			preg_match('@'.$attribute.'=([\'"]).+?\1@i',$tag,$match);
			if($match){
				$collected[] = $match[0];
			}
		}
		return $collected;
	}
}