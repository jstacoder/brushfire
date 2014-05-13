<?
///Uses flock.  Flock does not persist after termination of the script, so locks will not persist then either.
class Lock{
	static $locks;
	static function on($name,$timeout=0){
		$file = Config::$x['storageFolder'].'lock-'.$name;
		$fh = fopen($file,'w');
		
		while(self::$locks[$name] || !flock($fh,LOCK_EX|LOCK_NB)){
			if(!$timeout || time() - $start >= $timeout){
				return false;
			}
			usleep(200000);//over 10^6
		}
		self::$locks[$name] = $fh;
		return true;
	}
	static function isOn($name){
		if(self::on($name)){
			self::off($name);
			return false;
		}
		return true;
	}
	static function off($name){
		$file = Config::$x['storageFolder'].'lock-'.$name;
		if(self::$locks[$name]){
			flock(self::$locks[$name], LOCK_UN);
			unset(self::$locks[$name]);
		}
		clearstatcache();
		if(is_file($file)){
			unlink($file);
		}
	}
}
