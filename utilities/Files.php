<?
///Used to keep track of inclusions and to get better errors on failed requires
/**	
	@note	For this case, since phpe doesn't like it when you use "include" or "require" for method names, I have abbreviated the names.
*/
class Files{
	
	static private $included;///<an array of included files along with other arguments
	static private $currentInclude;///<internal use
	///used to factor out common functionality
	static function __callStatic($name,$arguments){
		self::$currentInclude = array(
				'file'=>$arguments[0],
				'globals'=>$arguments[1],
				'vars'=>$arguments[2],
				'type'=>$name
			);
		return call_user_func_array(array('self',$name),$arguments);
	}
	///include a file
	/**
	@param	file	file path
	@param	globalize	list of strings representing variables to globalize for the included file
	@param	vars	variables to extract for use by the file
	@param	extract	variables to extract from the included file to be returned
	@return	true or extracted varaibles if file successfully included, else false
	*/
	private static function inc($_file,$_globalize=null,$_vars=null,$_extract=null){
		if(is_file($_file)){
			self::logIncluded(true);
			if($_globalize){
				foreach($_globalize as $_global){
					global $$_global;
				}
			}
			if($_vars){
				extract($_vars,EXTR_SKIP);#don't overwrite existing
			}
			
			include($_file);
			
			if($_extract){
				foreach($_extract as $_var){
					$_return[$_var] = $$_var;
				}
				return $_return;
			}
			return true;
		}
		self::logIncluded(false);
	}
	///include a file once
	/**
	@param	file	file path
	@param	globalize	list of strings representing variables to globalize for the included file
	@param	vars	variables to extract for use by the file
	@param	extract	variables to extract from the included file to be returned
	@return	true or extracted varaibles if file successfully included, else false
	*/
	private static function incOnce($_file,$_globalize=null,$_vars=null,$_extract=null){
		if(is_file($_file)){
			self::logIncluded(true);
			if($_globalize){
				foreach($_globalize as $_global){
					global $$_global;
				}
			}
			if($_vars){
				extract($_vars,EXTR_SKIP);#don't overwrite existing
			}
			
			include_once($_file);
			
			if($_extract){
				foreach($_extract as $_var){
					$_return[$_var] = $$_var;
				}
				return $_return;
			}
			return true;
		}
		self::logIncluded(false);
	}
	///require a file
	/**
	@param	file	file path
	@param	globalize	list of strings representing variables to globalize for the included file
	@param	vars	variables to extract for use by the file
	@param	extract	variables to extract from the included file to be returned
	@return	true or extracted varaibles if file successfully included, else false
	*/
	private static function req($_file,$_globalize=null,$_vars=null,$_extract=null){
		if(is_file($_file)){
			self::logIncluded(true);
			if($_globalize){
				foreach($_globalize as $_global){
					global $$_global;
				}
			}
			if($_vars){
				extract($_vars,EXTR_SKIP);#don't overwrite existing
			}
			
			include($_file);
			
			if($_extract){
				foreach($_extract as $_var){
					$_return[$_var] = $$_var;
				}
				return $_return;
			}
			return true;
		}
		self::logIncluded(false);
		Debug::error('Could not include file "'.$_file.'"');
	}
	///require a file once
	/**
	@param	file	file path
	@param	globalize	list of strings representing variables to globalize for the included file
	@param	vars	variables to extract for use by the file
	@param	extract	variables to extract from the included file to be returned
	@return	true or extracted varaibles if file successfully included, else false
	*/
	private static function reqOnce($_file,$_globalize=null,$_vars=null,$_extract=null){
		if(is_file($_file)){
			self::logIncluded(true);
			if($_globalize){
				foreach($_globalize as $_global){
					global $$_global;
				}
			}
			if($_vars){
				extract($_vars,EXTR_SKIP);#don't overwrite existing
			}
			
			include_once($_file);
			
			if($_extract){
				foreach($_extract as $_var){
					$_return[$_var] = $$_var;
				}
				return $_return;
			}
			return true;
		}
		self::logIncluded(false);
		Debug::error('Could not include file "'.$_file.'"');
	}
	private static function logIncluded($result){
		self::$currentInclude['result'] = $result;
		self::$included[] = self::$currentInclude;
	}
	///get all the included files included by functions from this class
	static function getIncluded(){
		return self::$included;
	}
	///remove relative parts of a path that could be used for exploits
	static function removeRelative($path){
		return preg_replace(array('@((\.\.)(/|$))+@','@//+@'),'/',$path);
	}
	///prefix in case desire to use relative path or absolute path
	static function fileList($dir,$prefix='',$filterFn=null){
		$realPath = realpath($dir).'/';
		$files = array();
		foreach(scandir($realPath) as $v){
			if($v != '.' && $v != '..'){
				if(is_dir($realPath.$v)){
					$newFiles = self::fileLIst($realPath.$v,$prefix.$v.'/');
					Arrays::mergeInto($files,$newFiles);
				}else{
					if(!$filterFn || $filterFn($prefix.$v)){
						$files[] = $prefix.$v;
					}
				}
			}
		}
		return $files;
	}
	static function mime($path){
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $path);
		finfo_close($finfo);
		
		/* file -ib command does not determine what type of text a text file is.  So, use the type that the browser was expecting*/
		if($_SERVER['HTTP_ACCEPT'] && substr($mime,0,5) == 'text/'){
			$mime = array_shift(explode(',',$_SERVER['HTTP_ACCEPT']));
		}
		return $mime;
	}
	static function mimeExt($mime){
		$mime = explode('/',$mime);
		$mimes = array(
				'jpeg'=>'jpg',
				'javascript'=>'js'
			);
		if(key_exists($mime[1],$mimes)){
			return $mimes[$mime[1]];
		}
		return $mime[1];
	}
	///to handle various contents and to allow writing to folders that don't previously exist
	static function write($location,$content){
		$directory = dirname($location);
		if(!is_dir($directory)){
			mkdir($directory,0777,true);
		}
		file_put_contents($location,$content);
	}
}


