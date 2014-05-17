<?
class EncryptedCookie{
	static $cookies;///references the first instantiated EncryptedCookie
	/**
	@param	key	cookie key to use
	@param	data	if present, this is used as $this->data, otherise, key is used to find cookie for data
	*/
	function __construct($key,$data=null,$options=null){
		$this->key = $key;
		if($data){
			$this->data = $data;
		}else{
			$this->data = self::get($key);
		}
		self::$cookies[$key] = &$this->data;
		$this->options = $options;
		Hook::add('preHTTPMessageBody',array($this,'out'),array('deleteAfter'=>1));
	}
	function out(){
		if($this->data){
			self::set($this->key,$this->data,$this->options);
		}
	}
	
	///see Cookie::set for params
	static function set($key,$value,$options=null){
		$value = Encryption::encrypt(serialize($value));
		Cookie::set($key,$value,$options);
	}
	static function get($key){
		if($_COOKIE[$key]){
			return unserialize(Encryption::decrypt($_COOKIE[$key]));
		}
	}
	///@note just use Cookie::remove on non instantiated EncryptedCookies
	function delete(){
		Cookie::remove($this->key,$this->options);
	}
}