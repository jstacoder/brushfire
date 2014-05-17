<?
///class to allow mutually exclusive operations
class Lock{
	use SingletonDefault, OverClass { 
		OverClass::__call insteadof SingletonDefault;
		OverClass::__testCall insteadof SingletonDefault; }
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
	///ensure lock by using cas_token
	function lock($name){
		if(Cache::get($name,null,$casToken) == false){
			if(Cache::cas($casToken,$name,1)){
				return true;
			}
		}
		return false;
	}
	function on($name,$timeout=0){
		$cacheName = 'lock-'.$name;
		while($this->locks[$name] || !Cache::lock($cacheName)){
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
		
		if($this->locks[$name]){
			Cache::delete($cacheName);
			unset($this->locks[$name]);
		}
	}
}
///Uses flock.  Flock does not persist after termination of the script, so locks will not persist then either.
/** With multiple servers, would either need to make the storage folder networked or use separate locking mechanism*/
class FileLock{
	public $_success = true;
	function on($name,$timeout=0){
		$file = Config::$x['storageFolder'].'lock-'.$name;
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
		$file = Config::$x['storageFolder'].'lock-'.$name;
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
