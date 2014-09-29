<?
namespace control;
///Create Read Update Delete general class
class Crud{
	function __construct($control=null,$model=null){
		$this->control = $control ? $control : \Control::primary();
		$this->model = $model ? $model : $this->control->model;
	}
	/**
	@param	commands	list of commands to look for in input for running (will only run one, order by priority)
	@param	default	the command to use if none of the provided were found.  Will be run regardless of whether corersponding input command found
	*/
	function handle($commands=[],$default='read'){
		foreach($commands as $command){
			if($this->control->in['_'.$command]){
				$commandFn = $this->resolveFunction($command);
				$return = $commandFn($command);
				return ['command'=>$command,
					'return'=>$return,
					$command=>$return];
			}
		}
		return [];
	}
	function resolveFunction($command){
		if(method_exists($this->control->lt,$command)){
			return [$this->control->lt,$command];
		}
		if($this->model && method_exists($this->model,$command)){
			return [$this->model,$command];
		}
		return function($command){\Debug::toss('No handler function on CRUD with command '.$command);};
	}
	function whereEquals($fields){
		$where = [];
		foreach($fields as $field){
			$nonContextedField = end(explode('.',$field));
			unset($value);
			if(isset($this->control->in[$field])){
				$value = $this->control->in[$field];
			}elseif(isset($this->control->in[$nonContextedField])){
				$value = $this->control->in[$nonContextedField];
			}
			if(isset($value)){
				$line = \Db::ftvf($field,$value,$type);
				if($line){
					$where[] = $line;
				}
			}
		}
		return $where;
	}
}
