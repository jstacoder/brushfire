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
	use SDLL;
	/// reference to underlying Cacher instance (ie, memcached)
	public $cacher;	
	/**
	@param	connectionInfo	array:
		@verbatim
	 [
		[ip/name,port,weight]
	]
	*/
	function __construct($connectionInfo){
		$this->connectionInfo = $connectionInfo;
	}
	function load(){
		$this->cacher = new Memcached;
		if(!is_array(current($this->connectionInfo))){
			$this->connectionInfo = array($this->connectionInfo);
		}
		foreach($this->connectionInfo as $v){
			if(!$this->cacher->addserver($v[0],$v[1],$v[2])){
				Debug::quit('Failed to add cacher',$v);
			}
		}
		$this->cacher->set('on',1);
		if(!$this->cacher->get('on')){
			Debug::toss('Failed to get cache','CacheException');
		}
	}
	function __call($fnName,$args){
		if(method_exists(__class__,$fnName)){
			return parent::__call($fnName,$args);
		}elseif(method_exists($this->db,$fnName)){
			return call_user_func_array(array($this->cacher,$fnName),$args);
		}
		Debug::error(__class__.' Method not found: '.$fnName);
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
		$times = $this->cacher->get($name.':|:update:times',null,$casToken);
		if($times){
			if(time() > $times['nextUpdate']){
				if($options['timeout']){
					$times['nextUpdate'] += $options['timeout'];
				}else{
					$times = self::uTimes($options);
				}
				if($this->cacher->cas($casToken,$name.':|:update:times',$times,$times['nextExpiry'])){
					return self::uSet($name,$updateFunction,$options,array_slice(func_get_args(),3));
				}
			}
			$value = $this->cacher->get($name);
			if($this->cacher->getResultCode() == Memcached::RES_SUCCESS){
				return $value;
			}
		}
		return self::uSet($name,$updateFunction,$options,array_slice(func_get_args(),3));
	}
	protected function uSet($name,$updateFunction,$options,$args){
		$times = self::uTimes($options);
		$value = call_user_func_array($updateFunction,$args);
		$this->cacher->set($name,$value,$times['nextExpiry']);
		$this->cacher->set($name.':|:update:times',$times,$times['nextExpiry']);
		$this->cacher->set('bobs','sue',$times['nextExpiry']);
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
			$this->local[$key] = $this->get($key);
		}
		return $this->local[$key];
	}
	///local variable does not expire
	protected function lSet($key,$value,$expiry){
		$this->local[$key] = $value;
		$this->set($key,$value,$expiry);
	}
}