<?
///in the absense of separated context between included files, the need for the class presents itself
///as an alternative, can use Files::req() instead
class FileContext{
	use SingletonDefault;
	public $contexts;
	function __get($name){
		$includer = debug_backtrace()[0]['file'];
		return $this->contexts[$includer][$name];
	}
	function __set($name,$value){
		$includer = debug_backtrace()[0]['file'];
		$this->contexts[$includer][$name] = $value;
	}
	protected function get($name,$context){
		return $this->contexts[$context][$name];
	}
	protected function set($name,$value,$context){
		$this->contexts[$context][$name] = $value;
	}
}
function fc(){
	return FileContext::primary();
}