<?
/**
Namespaced class matched against ns folders.  If ns folders don't exist in $nsF, broaden scope, ending on default scope
*/
class Autoload{
	use SingletonDefault;
	
	///name space resource folders
	public $nsF = array();
	/**
	@param	nsF	[namespace => [folder,...],...]
	folder forms:
		path
		[path,options]
	options form:
		[
			moveUp => true/false
			moveDown => true/false
			stopFolders => folder names at traversal stops.  Files within the stop folder are not searched.
			stopPaths => folder paths at traversal stops.  Files within the stop path are not searched.
			stopMove => move (up or down) at which point to stop, not to include searching at the stop move
		]
	*/
	function __construct($nsF){
		$this->nsF['default'] = array();
		foreach((array)$nsF as $ns=>$rF){
			foreach($rF as $folder){
				//apply defaults
				if(!is_array($folder)){
					$this->nsF[$ns][] = array($folder,array('moveDown'=>true,'stopMove'=>20));
				}else{
					$this->nsF[$ns][] = $folder;
				}
			}
			
			
			if(Config::$x['autoloadSection']){
				Hook::add('prePage',array($this,'addSectionResources'),array('delete'=>1));
			}
		}
	}
	///spl autoload doesn't like protected methods.
	function auto($className){
		$this->req($className);
	}
	protected function req($className){
		$result = $this->load($className);
		//this is the only autoload and it has failed
		if(!$result['found'] && count(spl_autoload_functions()) == 1){
			$error = 'Attempt to autoload class "'.$className.'" has failed.  Tested folders: '."\n".implode("\n",array_keys($result['searched']));
			Debug::toss($error,__CLASS__.'Exception');
		}
	}
	///load a class based on current autoload nsF
	/**
	@param	className	name of the class and the name of the file without the ".php" extension
	*/
	protected function load($className){
		$excludePaths = array();
		//go throught all possible paths until either finding the class or failing
		while(!class_exists($className,false)){
			if($path = $this->findClass($className,$excludePaths)){
				require_once $path;
			}else{
				break;
			}
		}
		if(class_exists($className,false)){
			//found the class, perhaps hooks
			Hook::run('classLoaded',$className);
			return array('found'=>true);
		}
		return array('found'=>false,'searched'=>$excludePaths);
	}
	///Tries to load class, returns true on success
	protected function loaded($className){
		$result = $this->load($className);
		return $result['found'];
	}
	///finds a class looking in folders recursively
	/**
	@param	name	name of the class
	@param	folders	array of folders to check in
	*/
	protected function findClass($name,&$excludePaths){
		//resolve namespace
		$parts = explode('\\',$name);
		$name = array_pop($parts);
		$fileName = $name.'.php';
		
		if($parts){
			array_shift($parts);//clear empty value
			while($parts){
				if($folders = $this->nsF['\\'.implode('\\',$parts)]){
					break;
				}
				array_pop($parts);
			}
		}
		if(!$folders){
			$folders = $this->nsF['default'];
		}
		
		foreach($folders as $resource){
			list($path,$options) = $resource;
			if($path = $this->checkPath($fileName,$path,$options,$excludePaths)){
				return $path;
			}
		}
	}
	protected function checkPath($fileName,$path,$options,&$excludePaths,$move=0){
		if(isset($options['stopMove']) && $options['stopMove'] <= $move){
			return;
		}
		$path = Tool::absolutePath($path);
		
		if(!$path){
			return;
		}elseif($excludePaths[$path]){
			return;
		}elseif(isset($options['stopPaths']) && in_array($path,$options['stopPaths'])){
			return;
		}elseif(isset($options['stopFolders']) && in_array(dirname($path),$options['stopFolders'])){
			return;
		}
		
		$excludePaths[$path] = true;
		
		$dirs = array();
		if(is_dir($path)){	
			foreach(scandir($path) as $v){
				if($v != '.' && $v != '..'){
					if(is_dir($path.$v)){
						$dirs[] = $path.$v.'/';
					}else{
						if($v == $fileName){
							$filePath = $path.$fileName;
							if(!$excludePaths[$filePath]){
								$excludePaths[$filePath] = true;
								return $filePath;
							}
						}
					}
				}
			}
		}
		
		if($options['moveDown']){
			foreach($dirs as $path){
				if($path = $this->checkPath($fileName,$path,$options,$excludePaths,$move+1)){
					return $path;
				}
			}
		}elseif($options['moveUp']){
			if($path = $this->checkPath($fileName,$path.'../',$options,$excludePaths,$move+1)){
				return $path;
			}	
		}
	}
	
	///adds folder SectionPage is expected in
	protected function addSectionResources(){		
		$section = implode('/',Route::$urlTokens).'/';
		$base = Config::$x['projectFolder'].'tool/section/';
		array_unshift($this->nsF['default'],array(
				$base.$section, array('moveUp'=>true, 'stopPath' => [$base])
			));
		$base = Config::$x['projectFolder'].'view/tool/section/';
		array_unshift($this->nsF['default'],array(
				$base.$section, array('moveUp'=>true, 'stopPath' => [$base])
			));
		$this->addSection = false;
	}
	
	static $lastUndeployed;///< array of last undeployed autoloads
	///Removes all autoloads and puts them in the $lastUndeployed variable
	static function undeploy(){
		self::$lastUndeployed = spl_autoload_functions();
		foreach(self::$lastUndeployed as $autoload){
			spl_autoload_unregister($autoload);
		}
	}
	///Prepends an autoload
	static function prepend($newAutoload){
		$autoloads = spl_autoload_functions();
		foreach($autoloads as $autoload){
			spl_autoload_unregister($autoload);
		}
		spl_autoload_register($newAutoload);
		foreach($autoloads as $autoload){
			spl_autoload_register($autoload);
		}
	}
	
	///used to deploy an array of autoloads
	/**
	@autloaders	array	array of autoloads.  If null, defaults to self::$lastUndeployed
	*/
	static function deploy($autoloads=null){
		if(!$autoloads){
			$autoloads = self::$lastUndeployed;
		}
		foreach($autoloads as $autoload){
			spl_autoload_register($autoload);
		}
	}
	///used to repace current autoloads with last undeployed autoloads
	static function replace(){
		$autoloads = spl_autoload_functions();
		foreach($autoloads as $autoload){
			spl_autoload_unregister($autoload);
		}
		self::deploy();
	}
}