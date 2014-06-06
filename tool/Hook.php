<?
///For applying hooks
/**
spotname: where a run-hooks will be called
hookname: a name of a particular hook attached to a spotname
*/
class Hook{
	///it is not intended that hooks be applied in some specific order
	static $hooks = array();
	///add multiple hooks.  All arguments after spotName considered callbacks
	static function adds($spotName){
		$args = func_get_args();
		array_shift($args);
		foreach($args as $callback){
			self::$hooks[$spotName][] = $callback;
		}
	}
	///hooks that return === false are removed after run
	/**
	@param	options	various options:
		hookName : sets hookname, so as to allow identifying it for deletion or modification
		deleteAfter : #, deletes after # of uses
	*/
	static function add($spotName,$callback,$options=null){
		$hook = array('callback'=>$callback,'options'=>$options);
		return Arrays::addOnKey($options['hookName'],$hook,self::$hooks[$spotName]);
	}
	///run spot name with passed (non reference) values
	static function run($spotName){
		call_user_func_array(['Hook','runWithReferences'],func_get_args());
	}
	///pass by reference + variable number of parameters does not work with func_get_args, so either use debug_backtrace (expensive), or just prefill vars
	///this uses prefill, and prefills 13 params, thus limiting its use to 13 referenced variables (or 13 variables on closures), but unlimited non-reference variables
	static function runWithReferences($spotName,&$a1=null,&$a2=null,&$a3=null,&$a4=null,&$a5=null,&$a6=null,&$a7=null,&$a8=null,&$a9=null,&$a10=null,&$a11=null,&$a12=null,&$a13=null){
		if(self::$hooks[$spotName]){
			$argC = func_num_args();
			for($i = 1;$i<$argC;$i++){
				if($i<=13){
					$argName = 'a'.$i;
					$args[$i] = &$$argName;
				}else{
					$args[$i] = func_get_arg($i);
				}
			}
			foreach(self::$hooks[$spotName] as $k=>$hook){
				if(is_a($hook,'closure')){
					$return = call_user_func($hook['callback'],$a1,$a2,$a3,$a4,$a5,$a6,$a7,$a8,$a9,$a10,$a11,$a12,$a13);
				}else{
					$return = call_user_func_array($hook['callback'],(array)$args);
				}
				if($return === false){
					unset(self::$hooks[$spotName][$k]);
				}
				if(isset($hook['options']['deleteAfter'])){
					if($hook['options']['deleteAfter'] <= 1){
						unset(self::$hooks[$spotName][$k]);
					}else{
						self::$hooks[$spotName][$k]['options']['deleteAfter']--;
					}
				}
			}
		}
	}
	static function get($spotName){
		return self::$hooks[$spotName];
	}
	///if hookName present, delete that specific hook, else delete all hooks on spotName
	static function delete($spotName,$hookName=null){
		if($hookName == null){
			unset(self::$hooks[$spotName]);
		}else{
			unset(self::$hooks[$spotName][$hookName]);
		}
	}
}