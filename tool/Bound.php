<?
//sort of like javascripts bind functionality
class Bound{
	function __invoke(){
		return call_user_func_array($this->callable,array_merge($this->args,func_get_args()));
	}
	/**
	@param callable the callable to call on invoke
	@param remaining the arguments to prefix callable with
	*/
	function __construct($callable){
		$this->args = func_get_args();
		$this->callable = array_shift($this->args);
	}
}