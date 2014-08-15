<?
/**
@note	namespaces always matter with autoloading.  Any subset of namespace path as nsF key, then take remaining path an affix it to tested folders (which may be the entire namespace path affixed to the default folders)
*/
class Autoload{
	use SingletonDefaultPublic;
}
class AutoloadPublic{
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
		}
	}

	///spl autoload doesn't like protected methods.
	function auto($className){
		$this->req($className);
	}

	function req($className){
		$result = $this->load($className);
		$lastAutoloader = array_slice(spl_autoload_functions(),-1)[0];
		//this is the last autoload and it has failed
		if(!$result['found'] && is_a($lastAutoloader,'Autoload')){
			$error = 'Attempt to autoload class "'.$className.'" has failed.  Tested folders: '."\n".implode("\n",array_keys($result['searched']));
			Debug::toss($error,__CLASS__.'Exception');
		}
	}

	///load a class based on current autoload nsF
	/**
	@param	className	name of the class and the name of the file without the ".php" extension
	*/
	function load($className){
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
	function loaded($className){
		$result = $this->load($className);
		return $result['found'];
	}
	///finds a class looking in folders recursively
	/**
	@param	name	name of the class
	@param	folders	array of folders to check in
	*/

	function findClass($name,&$excludePaths){
		//resolve namespace
		$parts = explode('\\',$name);
		$name = array_pop($parts);
		$fileName = $name.'.php';
		$affix = '';
		if($parts){
			$partsCopy = $parts;
			//test nsF keys
			while($parts){
				if($folders = $this->nsF['\\'.implode('\\',$parts)]){
					$found = true;
					break;
				}
				$poppedParts[] = array_pop($parts);
			}
			if($found){
				$affix = $poppedParts ? implode('/',$poppedParts).'/' : '';
			}else{
				$affix = implode('/',$partsCopy).'/';
			}
		}
		if(!$folders){
			$folders = $this->nsF['default'];
		}
		
		foreach($folders as $resource){
			list($path,$options) = $resource;
			if($path = $this->checkPath($fileName,$path.$affix,$options,$excludePaths)){
				return $path;
			}
		}
	}

	function checkPath($fileName,$path,$options,&$excludePaths,$move=0){
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
