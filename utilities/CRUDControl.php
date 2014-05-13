<?
///Create Read Update Delete general class
class CRUDControl{
	use SingletonDefault;
	function __construct($pageTool=null){
		$this->PageTool = $pageTool;
		if(!$this->PageTool){
			global $page;
			$this->PageTool = $page->tool;
		}
		$this->page = $this->PageTool->page;
	}
	function __call($fnName,$args){
		if(in_array($fnName,array('create','update','delete','read'))){
			return $this->handle(array($fnName),$args[0]);
		}
		return $this->__testCall($fnName,$args);
	}
	/**
	@param	commands	list of commands to look for in input for running (will only run one, order by priority)
	@param	default	the command to use if none of the provided were found.  Will be run regardless of whether corersponding input command found
	*/
	protected function handle($commands=array(),$default='read'){
		$commands = Arrays::stringArray($commands);
		
		$this->attempted = $this->called = array();
		foreach($commands as $command){
			if($this->page->in['_cmd_'.$command]){
				$return = $this->callFunction($command);
				if($return === null || $return === false){
					continue;
				}
				return new CRUDResult($command,$return,$this->page->in['_cmd_'.$command],array('control'=>$this));
			}
		}
		if($default && !in_array($default,$this->attempted)){
			$return = $this->callFunction($default,$this->page->in['_cmd_'.$command]);
			return new CRUDResult($default,$return,null,array('control'=>$this));
		}
		return new CRUDResult('',null);
	}
	protected function getFunction($command,$subcommand=null){
		if(!$subcommand){
			$subcommand = $this->page->in['_cmd_'.$command];
		}
		if(method_exists($this->PageTool,$command.'_'.$subcommand)){
			return array($this->PageTool,$command.'_'.$subcommand);
		}elseif(method_exists($this->PageTool,$command)){
			return array($this->PageTool,$command);
		}elseif(isset($this->PageTool->model) 
			&& $this->PageTool->model['table'] 
			&& $this->PageTool->CRUDModel
			&& method_exists($this->PageTool->CRUDModel,$command)
		){
			return array($this->PageTool->CRUDModel,$command);
		}
		return false;
	}
	//callbacks applied at base for antibot behavior
	protected function callFunction($command,$subcommand=null,$error=false){
		$this->attempted[] = $command;
		$function = $this->getFunction($command);
		if($function){
			$this->called[] = $command;
			$return = call_user_func($function);
			return $return;
		}
		if($error){
			$this->page->error('Unsupported command');
		}
	}
}
/*Note, the handling of a result in a standard way would potentially require standard action names, item titles, directory structure, id parameters, etc.  So, just make it easy to handle, don't actually handle*/
class CRUDResult{
	function __construct($type,$return,$subType=null,$info=null){
		$this->type = $type;
		$this->return = $return;
		$this->subType = $subType;
		if($info){
			$this->attempted = $info['control']->attempted;
			$this->called = $info['control']->called;
		}
		if($type){
			$this->$type = $return;
		}
	}
}