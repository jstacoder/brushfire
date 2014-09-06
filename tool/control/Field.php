<?
namespace control;
class Field{
	static $ruleAliases = array(
		'phone' => 'f.toDigits,!v.filled,v.phone',
		'zip' => '!v.filled,v.zip',
		'name' => 'f.name,f.trim,!v.filled',
		'email' => '!v.filled,v.email',
		'password' => '!v.filled,v.lengthRange|3;50',
		'userBirthdate' => '!v.filled,v.date,v.age|18;130',
		'ip4' => array('f.trim','!v.filled',array('!v.regex','@[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}@','ip4 format')),
		'title' => array('f.trim',array('f.regex','@[^a-z0-9_\- \']@i'),'!v.filled'),
		'basicText' => array('f.trim','f.conditionalNl2Br',array('f.stripTags','br,a,b,i,u,ul,li,ol,p','href'),'f.trim','v.filled','v.htmlTagContextIntegrity'),
		
	);
	function __invoke($type,$prepend=null,$append=null){
		return self::read($type,$prepend,$append);
	}
	static function read($type,$prepend=null,$append=null){
		$rules = static::$types[$type];
		if($prepend){
			$rules = self::prepend($prepend,$rules);
		}
		if($append){
			$rules = self::append($append,$rules);
		}
		return $rules;
	}
	
	///potentially some input vars are arrays.  To prevent errors in functions that expect field values to be strings, this function is here.
	static function makeString(&$value){
		if(is_array($value)){
			$valueCopy = $value;
			$value = self::getString(array_shift($valueCopy));
		}
	}
	///note, this can be a waste of resources; a reference $value going in is remade on assignment from the return of this function, so use makeString on references instead
	static function getString($value){
		if(is_array($value)){
			return self::getString(array_shift($value));
		}
		return $value;
	}
	//to append a rule to a series
	static function append($rule,$rules){
		$rules = Arrays::stringArray($rules);
		$rules[] = $rule;
		return $rules;
	}
	//to prepend a rule to a series
	static function prepend($rule,$rules){
		$rules = Arrays::stringArray($rules);
		array_unshift($rules,$rule);
		return $rules;
	}
}
