<?
///class to allow mutually exclusive operations
class Lock{
	use SingletonDefault, OverClass { 
		OverClass::__call insteadof SingletonDefault; }
	static $types = array('file'=>'FileLock','cache'=>'CacheLock');
	public $typePreferences=['cache','file'];
}
///shared memory (Cache Class) locking.  Usually better than file locking.
class CacheLock{
	public $_success = true;
	function __construct(){
		try{
			if(!Cache::check()){
				$this->_success = false;
				return;
			}
		} catch(Exception $e){
			$this->_success = false;
		}
	}
	///ensure lock by using mutual exclusion logic
	function lock($name){
		if(Cache::get($name) == false){
			$token = mt_rand(1,999999);
			Cache::set($name.'-interest',$token);
			if(!Cache::get($name.'-interested')){
				Cache::set($name.'-interested',1);//must have passed if on all contenders
				$existingToken = Cache::get($name.'-interest');
				if($existingToken == $token && !Cache::get($name)){//only one contender will match existing token
					Cache::set($name,1);
					Cache::delete($name.'-interest');
					return true;
				}
			}
			Cache::delete($name.'-interest');
		}
		return false;
	}
	function on($name,$timeout=0){
		$cacheName = 'lock-'.$name;
		while($this->locks[$name] || !$this->lock($cacheName)){
			if(!$timeout || time() - $start >= $timeout){
				return false;
			}
			usleep(200000);//u:micro: 10^-6
		}
		$this->locks[$name] = $fh;
		return true;
	}
	function req($name,$timeout=0){
		if(!$this->on($name,$timeout)){
			Debug::toss('Failed to acquire lock "'.$name.'"',__CLASS__.'Exception');
		}
	}
	function isOn($name){
		if($this->on($name)){
			$this->off($name);
			return false;
		}
		return true;
	}
	function off($name){
		$cacheName = 'lock-'.$name;
		Cache::delete($cacheName);
		unset($this->locks[$name]);
		Cache::delete($cacheName.'-interested');//variable excution point makes deleting this in lock() to be impractical
	}
}
///Uses flock.  Flock does not persist after termination of the script, so locks will not persist then either.
/** With multiple servers, would either need to make the storage folder networked or use separate locking mechanism*/
class FileLock{
	public $_success = true;
	function construct($storageFolder=null){
		if(!$storageFolder){
			$storageFolder = class_exists('Config',false) ? $_ENV['storageFolder'] : '/tmp/';
		}
		$this->storageFolder = $storageFolder;
	}
	function on($name,$timeout=0){
		$file = $this->storageFolder.'lock-'.$name;
		$fh = fopen($file,'w');
		
		while($this->locks[$name] || !flock($fh,LOCK_EX|LOCK_NB)){
			if(!$timeout || time() - $start >= $timeout){
				return false;
			}
			usleep(200000);//u:micro: 10^-6
		}
		$this->locks[$name] = $fh;
		return true;
	}
	function req($name,$timeout=0){
		if(!$this->on($name,$timeout)){
			throw Exception('Failed to acquire lock "'.$name.'" in '.__CLASS__);
		}
	}
	function isOn($name){
		if($this->on($name)){
			$this->off($name);
			return false;
		}
		return true;
	}
	function off($name){
		$file = $this->storageFolder.'lock-'.$name;
		if($this->locks[$name]){
			flock($this->locks[$name], LOCK_UN);
			unset($this->locks[$name]);
		}
		clearstatcache();
		if(is_file($file)){
			unlink($file);
		}
	}
}
