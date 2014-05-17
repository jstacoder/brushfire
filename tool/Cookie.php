<?
class Cookie{
	///set a cookie
	/**
	@param	key	the key of the cookie
	@param	value	the value of the cookie
	@param	options	key based option array that will override the default options
		- path
		- expire
		- domain
		- secure
		- httponly
	@note	this function will also set the corresponding $_COOKIE variable
	*/
	static function set($key,$value,$options=null){
		foreach(Config::$x['cookieDefaultOptions'] as $k=>$v){
			if(!isset($options[$k])){
				$options[$k] = $v;
			}
		}
		setcookie($key,$value,$options['expire'],$options['path'],$options['domain'],$options['secure'],$options['httpsonly']);
		$_COOKIE[$key] = $value;
	}
	///remove a cookie
	/**
	@param	key	the key of the cookie
	@param	options	the options are needed to identify the cookie (cookies can have same names in different contexts), 
	@note	this function will also unset the corresponding $_COOKIE variable
	*/
	static function remove($key,$options=null){
		foreach(Config::$x['cookieDefaultOptions'] as $k=>$v){
			if(!isset($options[$k])){
				$options[$k] = $v;
			}
		}
		setcookie($key,'',-1,$options['path'],$options['domain']);
		unset($_COOKIE[$key]);
	}
}