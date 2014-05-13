<?
///Commonly used traits


///Makes failure of call_user_func_array on a overriden __call easier to read
trait testCall{
	function __call($fnName,$args){
		return $this->__testCall($fnName,$args);
	}
	
	function __testCall($fnName,$args){
		if(!method_exists($this,$fnName)){
			Debug::error(get_called_class().' Method not found: '.$fnName);
		}
		return call_user_func_array(array($this,$fnName),$args);
	}
}

/**
The convenience of acting like there is just one, with the ability to handle multiple
Static calls default to primary instance.  If no primary instance, attempt to create one.

@note	__construct can not be protected because the RelfectionClass call to it is not considered a relative
*/
trait SingletonDefault{
	use testCall;
	/// object representing the primary instance
	static $primary;
	/// array of named instances
	static $instances = array();
	
	static function init($instanceName=null){
		$instanceName = $instanceName !== null ? $instanceName : 0;
		if(!isset(static::$instances[$instanceName])){
			$class = new ReflectionClass(get_called_class());
			$instance = $class->newInstanceArgs(array_slice(func_get_args(),1));
			static::$instances[$instanceName] = $instance;
			static::$instances[$instanceName]->name = $instanceName;
			
			//set primary if no instances except this one
			if(count(static::$instances) == 1){
				static::setPrimary($instanceName,$className);
			}
		}
		return static::$instances[$instanceName];
	}
	/// overwrite any existing primary with new construct
	static function primary($instanceName=null){
		$instanceName = $instanceName !== null ? $instanceName : 0;
		$class = new ReflectionClass(get_called_class());
		$instance = $class->newInstanceArgs(array_slice(func_get_args(),1));
		static::$instances[$instanceName] = $instance;
		static::$instances[$instanceName]->name = $instanceName;
		
		static::setPrimary($instanceName,$className);
		return static::$instances[$instanceName];
	}
	/// sets primary to some named instance
	static function setPrimary($instanceName){
		static::$primary = static::$instances[$instanceName];//php already assigns objects by reference
	}

	/// used to translate static calls to the primary instance
	static function __callStatic($fnName,$args){
		if(!static::$primary){
			static::init();
		}
		return call_user_func(array(static::$primary,'__call'),$fnName,$args);
	}
}

/**
The pattern: Load resource when used, not when class instantiated.  Calling a method, or getting an non-set property will cause a load.
*/
///Singleton default lazy loader
trait SDLL{
	use SingletonDefault;
	public $loaded = false;
	public $constructArgs = array();
	function __construct(){
		$this->constructArgs = func_get_args();
	}
	function __get($name){
		//load if not loaded
		if(!$this->loaded){
			call_user_func_array(array($this,'load'),(array)$this->constructArgs);
			$this->loaded = true;
		}
		return $this->$name;
	}
	function __call($fnName,$args){
		//load if not loaded
		if(!$this->loaded){
			call_user_func_array(array($this,'load'),(array)$this->constructArgs);
			$this->loaded = true;
		}
		return $this->__testCall($fnName,$args);
	}
	abstract function load();
}