<?
class FieldOut{
	static $formFieldTypes = array(
			'phone'=>'text'
		);
	///used to apply formatting for display of form value.  PageTool functions will override if PageTool::$formFieldTypes[key] present.
	///first argument is type, the rest get passed on to the form function
	static function get(){
		$arguments = func_get_args();
		$type = array_shift($arguments);
		
		if(isset(PageTool::$formFieldTypes)  && PageTool::$formFieldTypes[$type]){
			Form::$valueCallbacks[] = array(PageTool,$type);
			$method = PageTool::$formFieldTypes[$type];
		}else{
			Form::$valueCallbacks[] = array(__class__,$type);
			$method = self::$formFieldTypes[$type];
		}
		$return = call_user_func_array(array('Form',$method),$arguments);
		array_pop(Form::$valueCallbacks);
		return $return;
	}
	
	static function phone(&$value){
		if(strlen($value) == 10){
			$areacode = substr($value,0,3);
			$part1 = substr($value,3,3);
			$part2 = substr($value,6);
			$value = '('.$areacode.') '.$part1.'-'.$part2;
		}
	}
	static function date(&$value){
		if($value && InputValidate::check('date',$value)){
			$value = (new Time($value,Config::$x['timezone']))->setZone(Config::$x['inOutTimezone'])->date();
		}
	}
	static function datetime(&$value){
		if($value && InputValidate::check('date',$value)){
			$value = (new Time($value,Config::$x['timezone']))->setZone(Config::$x['inOutTimezone'])->datetime();
		}
	}
	static function conditionalBr2Nl($value){
		if(!preg_match('@<div|<p|<table@',$value)){
			$value = preg_replace('@<br ?/>@',"\n",$value);
		}
		return $value;
	}
}
