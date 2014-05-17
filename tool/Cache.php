<?
/**
Underlying cache functions
	set(key,value,+expirySeconds), 
		expirySeconds= 0, lasts till memcached stops
		handles arrays
	get(key)
	flush(), invalidate all cache
	
*/

class Cache{
	use OCSDLL{	OCSDLL::load as OCSDLL_load; }

	static $types = array('memcache'=>'MemCacher','apc'=>'ApCacher','file'=>'FileCacher');
	/**
	@param	type	see static types variable
	@param	connectionInfo	array:
		@verbatim
	 [
		[ip/name,port,weight]
	]
	*/
	function load(){
		call_user_func_array([$this,'OCSDLL_load'],func_get_args());
		if(!$this->check()){
			Debug::toss('Failed to get check cache',__CLASS__.'Exception');
		}
	}
	///sees if cache is working
	protected function check(){
		$this->under->set('on',1);
		if(!$this->under->get('on')){
			return false;
		}
		return true;
	}
	///updateGet for getting and potentially updating cache
	/**
	allows a single client to update a cache while concurrent connetions just use the old cache (ie, prevenut multiple updates).  Useful on something like a public index page with computed resources - if 100 people access page after cache expiry, cache is only re-updated once, not 100 times.
	
	Perhaps open new process to run update function
	
	@param	name	name of cache key
	@param	updateFunction	function to call in case cache needs updating or doesn't exist
	@param	options	
			[
				update => relative time after which to update
					ex: "+20 seconds"
				timeout => update timeout in seconds (optional)
					ex: "40"
				expiry => time after update time, where if update doesn't happen, backup cache expires (in seconds) (optional)
					ex: "120"
				serialize => whether to serialize and unserialize outputs [memcached already handles non-strings (arrays), don't need this for memcached]
			]
	@param additional	any additinoal args are passed to the updateFunction
	*/
	protected function uGet($name,$updateFunction,$options){
		$times = $this->under->get($name.':|:update:times',null,$casToken);
		if($times){
			if(time() > $times['nextUpdate']){
				if($options['timeout']){
					$times['nextUpdate'] += $options['timeout'];
				}else{
					$times = self::uTimes($options);
				}
				if($this->under->cas($casToken,$name.':|:update:times',$times,$times['nextExpiry'])){
					return self::uSet($name,$updateFunction,$options,array_slice(func_get_args(),3));
				}
			}
			$value = $this->under->get($name);
			if($value !== false){
				return $value;
			}
		}
		return self::uSet($name,$updateFunction,$options,array_slice(func_get_args(),3));
	}
	protected function uSet($name,$updateFunction,$options,$args){
		$times = self::uTimes($options);
		$value = call_user_func_array($updateFunction,$args);
		$this->under->set($name,$value,$times['nextExpiry']);
		$this->under->set($name.':|:update:times',$times,$times['nextExpiry']);
		$this->under->set('bobs','sue',$times['nextExpiry']);
		return $value;
	}
	///generates all times necessary for uget functions
	static function uTimes($options){
		$updateTime = new Time($options['update']);
		$updateTimeUnix = $updateTime->unix();
		if($updateTimeUnix < time()){
			Debug::toss('uGet Cache update time is previous to current time','CacheException');
		}
		if($options['expiry']){
			$expiryTimeUnix = $updateTime->relative('+'.$options['expiry'].' seconds')->unix();
			$times['nextExpiry'] = $expiryTimeUnix - time();
		}
		
		$times['nextUpdate'] = $updateTimeUnix;
		return $times;
	}
	
	public $local;
	///local get, to save calls to memcached
	protected function lGet($key){
		if(!$this->local[$key]){
			$this->local[$key] = $this->under->get($key);
		}
		return $this->local[$key];
	}
	///local variable does not expire
	protected function lSet($key,$value,$expiry){
		$this->local[$key] = $value;
		$this->under->set($key,$value,$expiry);
	}
}
class MemCacher extends Cache{
	public $_success = true;
	function __construct($connectionInfo=null){
		$this->connectionInfo = $connectionInfo;
		if(!class_exists('Memcached') || !$connectionInfo){
			$this->_success = false;
		}
	}
	function load(){
		$this->under = $this->cacher = new Memcached;
		if(!is_array(current($this->connectionInfo))){
			$this->connectionInfo = array($this->connectionInfo);
		}
		foreach($this->connectionInfo as $v){
			if(!$this->cacher->addserver($v[0],$v[1],$v[2])){
				Debug::toss('Failed to add cacher "'.$name.'"',__CLASS__.'Exception');
			}
		}
	}
}
class ApCacher extends Cache{
	public $_success = false;
}