<?
/// used for dealing with framework instance configurations
class Config{
	///sets some defaults
	static function init(){
		$_ENV['logLocation'] = self::userFileLocation($_ENV['logLocation']);
		$_ENV['aliasesFiles'] = (array)$_ENV['aliasesFiles'];
		$_ENV['protocol'] = strtolower(explode('/',$_SERVER['SERVER_PROTOCOL'])[0]);
		date_default_timezone_set($_ENV['timezone']);
	}
	static function userFileLocation($file,$defaultLocation='.'){
		if(substr($file,0,1) != '/'){
			$file = $_ENV['projectFolder'].$defaultLocation.'/'.$file;
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
	///loads named config from config directory into $_ENV.
	///@note  @names are encloded with 'config.'name'.php'
	static function load($name){
		$file = $_ENV['configFolder'].'config.'.$name.'.php';
		self::loadUserFile($file,'.');
	}
}