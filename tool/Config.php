<?
/// used for dealing with framework instance configurations
class Config{
	///array of configuration options including defaults
	static public $x;
	
	///gets the defaults and deals with special config variable syntax
	static function get(){
		self::$x['logLocation'] = self::userFileLocation(self::$x['logLocation']);
		self::$x['aliasesFiles'] = (array)self::$x['aliasesFiles'];
		date_default_timezone_set(Config::$x['timezone']);
	}
	static function userFileLocation($file,$defaultLocation='.'){
		if(substr($file,0,1) != '/'){
			$file = Config::$x['projectFolder'].$defaultLocation.'/'.$file;
		}
		//since file base ensured (not purely relative), can run through absolutePath function
		return Tool::absolutePath($file);
	}
	///loads a user file, using a relative path if file doesn't start with "/"
	static function loadUserFile($file,$defaultLocation = '.',$globalize=null,$vars=null,$extract=null){
		if($file){
			$file = self::userFileLocation($file,$defaultLocation);
			return Files::req($file,$globalize,$vars,$extract);
		}
	}
	///loads user files using self::loadUserFile
	static function loadUserFiles($files,$defaultLocation='.',$globalize=null,$vars=null){
		if(is_array($files)){
			foreach($files as $file){
				self::loadUserFile($file,$defaultLocation,$globalize,$vars);
			}
		}elseif($files){
			self::loadUserFile($files,$defaultLocation,$globalize,$vars);
		}
	}
}