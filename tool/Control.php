<?
///See Doc:Concern Division Model
/**
Since this is the tools,templates data interface, this is the Control class

The control object should be available to all realms (tools, templates) as a sort of data-glue.
	in the model
		user input -> control -> tools -> control -> views
	in order for the control to call the tools and call the views with everything necessary, it must know all the input requirements of each, and how to handle the outputs of the Tools.
	case:
		template x needs to know:
			user input "b1"
			tool x return "items"
			tool y return "config"
		here, either the control must know that the template needs all of this, and then provide it specifically, or the control can provide a data-interface from which the template can select.  I chose to use the interface method.
Control provides standardised message handling (erros and such)	
	Four types of messages are recognized:
		Errors: Things that prevent movement forward
		Warnings: Things tthat may later prevent movement forward
		Notices: Things to optmize movement forward
		Success: Indicator of movement forward
	
Since Page can include control-like functions and control-like-data, Page is available within Control as control->control, and properties of Page are found when looking on Control instances.
*/
class Control{
	use SingletonDefaultPublic;
	static $in;///<a combination of get and post using special handling of repeated tokens.  Name comes from stdin - "std".
	static function setPrimary($instanceName){
		static::$primaryName = $instanceName;
		self::$in =& self::primary()->in;
	}
}
class ControlPublic{	
	public $in;///<the potentially tool-changed input, made available to templates, controls and tools
	public $originalIn;///<an unmodified version of $in
	public $messages;///<system messages to display to the user
	public $inputRuleAliases = null;///<when an input rule has no prefix, consider it an alias for other rules found in this->inputRuleAliases[rule].  Defaults to Field::$ruleAliases
	public $item;///<when a page is involving one item, use this variable to hold the data
	public $items;///<when a page is involving multiple items of the same type, use this variable to hold the data
	function __construct($in=false,$cookieMessagesName=false){
		//+	parse input{
		if($in===false){//apply default
			//+	Handle GET and POST variables{
			$in['get'] = $_SERVER['QUERY_STRING'];//we take it from here b/c php will replace characters like '.' and will ignore duplicate keys when forming $_GET
			//can cause script to hang (if no stdin), so don't run if in script unless configured to
			if(!$_ENV['inScript'] || $_ENV['scriptGetsStdin']){
				//multipart forms can either result in 1. input being blank or 2. including the upload.  In case 1, post vars can be taken from $_POST.  In case 2, need to avoid putting entire file in memory by parsing input
				if(substr($_SERVER['CONTENT_TYPE'],0,19) != 'multipart/form-data'){
					$in['post'] = file_get_contents('php://input');
					$in['post'] = $in['post'] ? $in['post'] : file_get_contents('php://stdin');
				}elseif($_POST){
					$in['post'] = http_build_query($_POST);
				}
			}
			if($_SERVER['CONTENT_TYPE'] == 'application/json'){
				$in['post'] = ['json'=>json_decode($in['post'])];
			}else{
				$in['post'] = Http::parseQuery($in['post'],$_ENV['pageInPHPStyle']);
			}
			$in['get'] = Http::parseQuery($in['get'],$_ENV['pageInPHPStyle']);
			$this->in = Arrays::merge($in['get'],$in['post']);
			//+	}
		}elseif(is_array($in)){
			$this->in = $in;
		}else{
			$this->in = Http::parseQuery($in,$_ENV['pageInPHPStyle']);
		}
		$this->originalIn = $this->in;
		
		if($_ENV['stripInputContexts']){
			$this->in = self::removeInputContexts($this->in);
		}
		//+	}
		
		//+	Handle COOKIE system messages{
		if($cookieMessagesName === false){//apply default
			$cookieMessagesName = '_PageMessages';
		}
		
		//Page message are intended to be shown on viewed pages, not ajax responses, so ignore on ajax
		if($cookieMessagesName && $_COOKIE[$cookieMessagesName] && !$this->in['_ajax']){
			do{
				$cookie = @unserialize($_COOKIE[$cookieMessagesName]);
				if(is_array($cookie)){
					$code = self::saveMessagesCode($cookie['data']);
					if($cookie['code'] == $code){
						if(is_array($cookie['target'])){
							if(!array_diff($cookie['target'],Route::$tokens)){
								$this->messages = @unserialize($cookie['data']);
							}else{
								//not on right page, so break
								break;
							}
						}else{
							$this->messages = @unserialize($cookie['data']);
						}
					}
				}
				Cookie::remove($cookieMessagesName);
			}while(false);
		}
		//+	}
		
		//load db if configured
		if($_ENV['database']['default']){
			$this->db = Db::init(null,$_ENV['database']['default']);
		}

	}
	/**
		Load view when accessed
		Use local tool attributes if not found
	*/
	function __get($name){
		if($name == 'view'){
			$this->view = View::primary();
			return $this->view;
		}
		return $this->lt->$name;
	}
	
	///To account for multiple same-named inputs directing to different targets on the same page, contexts prefix input names, and are stripped out here
	///@note see $_ENV['stripInputContexts']
	static function removeInputContexts($in){
		foreach((array)$in as $k => $v){
			$newIn[array_slice(explode('-',$k,2),-1)[0]] = $v;
		}
		return $newIn;
	}
	function addLocalTool($tokens){
		if(!$tokens){
			$class = 'stdClass';
		}else{
			Files::incOnce($_ENV['projectFolder'].'tool/local/'.implode('/',$tokens).'.php');
			$class = '\\local\\'.implode('\\',$tokens);
			if(strpos($class,'-') !== false){
				$class = Tool::toCamel($class,false,'-');
			}
		}
		$this->addLocalClass($class);
	}
	function addLocalClass($class){
		$this->lt = new $class;
		$this->lt->control = $this;
		$this->lt->in =& $this->in;
		$this->lt->messages =& $this->messages;
		if(!$this->lt->db){
			$this->lt->db =& $this->db;
		}
	}
	
	function error($message,$name=null,$options=null){
		$this->message($message,$name,'error',$options);
	}
	function success($message=null,$name=null,$options=null){
		if(!$message){
			$message = View::pageTitle().' successful';
		}
		$this->message($message,$name,'success',$context,$options);
	}
	function notice($message,$name=null,$options=null){
		$this->message($message,$name,'notice',$options);
	}
	function warning($message,$name=null,$options=null){
		$this->message($message,$name,'warning',$options);
	}
	public $defaultContext = 'default';
	function message($message,$name,$type,$options=null){
		$context = $options['context'] ? $options['context'] : $this->defaultContext;
		$message = array('type'=>$type,'context'=>$context,'name'=>$name,'content'=>(string)$message);
		if($options){
			$message = Arrays::merge($message,$options);
		}
		$this->messages[] = $message;
	}
		
	//checks if there is a field error for a given field in a given context
	function fieldError($field,$context='default'){
		return $this->getMessages('error',$context,$field);
	}
	///returns errors
	function errors($context=null){
		return $this->getMessages('error',$context);
	}
	///checks if there was an error.  Defaults to checking all contexts
	function hasError($context=null){
		foreach((array)$this->messages as $message){
			if('error' != $message['type']){
				continue;
			}elseif($context && $context != $message['context']){
				continue;
			}
			return true;
		}
	}
	///get messages based on context and name
	function getMessages($type=null,$context=null,$name=null){
		$messages = array();
		foreach((array)$this->messages as $message){
			if($type && $type != $message['type']){
				continue;
			}elseif($context && $context != $message['context']){
				continue;
			}elseif($name !== null && $name != $message['name']){
				continue;
			}
			$messages[] = $message;
		}
		return $messages;
	}
	
	public $addRules = [];//used with ::validate() as predefined rules to be added to those passed in
	public $fields;//array of [field=>value,..], where value is after filtering
	//alias for filterAndValidate that applies non-conflicting $fieldValidaters and saves filtered fields to $this->fields
	function validate($rules,$options=null){
		$fields = Arrays::remove(array_keys($rules));
		
		if($this->addRules){
			$rules = Arrays::merge($this->addRules,$rules);
		}
		$result = $this->filterAndValidate($rules,$options);
		foreach($fields as $field){
			$this->fields[$field] = $this->in[$field];
		}
		return $result;
	}
	/**
	@param	fields	array	array with keys being fields and values being rules to apply to fields.  See appyFilterValidateRules for rule syntax
		self::filterAndValidate(
			array(
				'email' => rules
				'loginName' => rules
			)
		);
		
	@return	false if error in any context, else true
	*/
	function filterAndValidate($rules,$options=null){
		if($options['filterArrays'] || !isset($options['filterArrays'])){
			foreach($rules as $field=>$ruleSet){
				if(is_array($this->in[$field])){
					\control\Field::makeString($this->in[$field]);
				}
			}
		}
		
		foreach($rules as $field=>$ruleSet){
			unset($fieldValue, $byReference);
			$fieldValue = null;
			if(isset($this->in[$field])){
				$fieldValue = &$this->in[$field];
				$byReference = true;
			}
			$continue = $this->applyFieldRules($field, $fieldValue, $ruleSet, $this->lt, $options['errorOptions']);
			//since there was no in[field], yet the surrogate was manipulated, set the in[field] to the surrogate
			if(!$byReference && $fieldValue){
				$this->in[$field] = $fieldValue;
			}
			if(!$continue){
				break;
			}
		}
		return !$this->hasError();
	}
	
	public $currentField = '';///< current field being parseds
	/**
	@param	rules	string or array	
		Rules can be an array of rules, or a string separated by "," for each rule.  
		Each rule can be a string or arrays
		As a string, the rule should be in one of the following forms:
				"f.name|param1;param2" indicates InputFilter method
				"v.name|param1;param2" indicates InputValidate function
				"g.name|param1;param2" indicates global scoped function
				"class.name|param1,param2,param3" indicates static method "name: of class "class" 
				"l.name|param1,param2,param3" Local tool method
				"name" replaced by Field fieldType of the same name
		As an array, the rule function part (type:method) is the first element, and the parameters to the function part are the following elements.
		
		The fn part can be prefixed with "!" to break on error with no more rules for that field should be applied
		The fn part can be prefixed with "!!" to break on error with no more rules for any field should be applied
		The fn part can be prefixed with "?" to indicate the validation is optional, and not to throw an error (useful when combined with '!' => '?!v.filled,email')
		The fn part can be prefixed with "~" to indicate if the validation does not fail, then there was an error
		
		
		If array, first part of rule is taken as string with the behavior above without parameters and the second part is taken as the parameters; useful for parameters that include commas or semicolons or which aren't strings
		
		Examples for rules:
			'f.trim,v.email'
			'CustomValidation.method|param2,param3'
			['f.trim',['v.regex','PATTERN']]
			['f.trim',[['!',['InputValidate','regex']],'PATTERN']]
	*/
	function applyFieldRules($field, &$value, $rules, $localTool, $errorOptions){
		//initialise with aliases
		if($this->inputRuleAliases === null){
			$this->inputRuleAliases = \control\Field::$ruleAliases;
		}
		
		$this->currentField = $field;
		$rules = Arrays::stringArray($rules);
		for($i=0;$i<count($rules);$i++){
			$rule = $rules[$i];
			unset($prefixOptions);
			$params = array(&$value);
			
			if(is_array($rule)){
				$callback = array_shift($rule);
				if(is_array($callback)){
					list($prefixOptions) = $this->rulePrefixOptions($callback[0]);
					$callback = $callback[1];
				}
				$paramsAdditional = &$rule;
			}else{
				list($callback,$paramsAdditional) = explode('|',$rule);
				
				if($paramsAdditional){
					$paramsAdditional = explode(';',$paramsAdditional);
				}
			}
			///merge field value param with the user provided params
			if($paramsAdditional){
				Arrays::mergeInto($params,$paramsAdditional);
			}
			
			if(!$prefixOptions){
				list($prefixOptions,$callback) = $this->rulePrefixOptions($callback);
			}
			
			//handle the base of the callback being an alias for more rules
			if(is_string($callback) && $this->inputRuleAliases[$callback]){
				$newRules = Arrays::stringArray($this->inputRuleAliases[$callback]);
				if($i + 1 < count($rules)){///there are rules after this alias, so combine alias with those existing after
					$newRules = array_merge($newRules,array_slice($rules,$i + 1));
				}
				$rules = $newRules;
				$i = -1;
				continue;
			}
			
			
			$callback = $this->ruleCallable($callback);
			if(!is_callable($callback)){
				Debug::toss('Rule not callable: '.var_export($rule,true));
			}
			try{
				call_user_func_array($callback,$params);
				if($prefixOptions['not']){
					$prefixOptions['not'] = false;
					Debug::toss('Failed to fail a notted rule: '.var_export($rule,true));
				}
			}catch(InputException $e){
				//this is considered a pass
				if($prefixOptions['not']){
					continue;
				}
				//add error to messages
				if(!$prefixOptions['ignoreError']){
					$this->error($e->getMessage(),$field,$errorOptions);
				}
				//super break will break out of all fields
				if($prefixOptions['superBreak']){
					return false;
				}
				//break will stop validators for this one field
				if($prefixOptions['break']){
					break;
				}
			}
		}
		return true;
	}
	function ruleCallable($callback){
		if(is_string($callback)){
			list($type,$method) = explode('.',$callback,2);
			if(!$method){
				$method = $type;
				unset($type);
			}
		}else{
			return $callback;
		}
		
		if(!$callback){
			Debug::toss('Failed to provide callback for input handler');
		}
		switch($type){
			case 'f':
				return ['InputFilter',$method];
			break;
			case 'v':
				return ['InputValidate',$method];
			break;
			case 'l':
				return [$this->lt,$method];
			break;
			case 'g':
				$method;
			break;
			default:
				if($type){
					return [$type,$method];
				}
				return $callback;
			break;
		}
	}
	function rulePrefixOptions($string){
		//used in combination with !, like ?! for fields that, if not empty, should be validated, otherwise, ignored.
		for($length = strlen($string), $i=0;	$i<$length;	$i++){
			switch($string[$i]){
				case '?':
					$options['ignoreError'] = true;
					break;
				case '!':
					if($string[$i + 1] == '!'){
						$i++;
						$options['superBreak'] = true;
					}else{
						$options['break'] = true;
					}
					break;
				case '~':
					$options['not'] = true;
					break;
				default:
					break 2;
			}
		}
		return  [$options,substr($string,$i)];
	}
	///to prevent fabrication of system messages.  No spurious success messages!
	static function saveMessagesCode($data){
		return substr(sha1($_SERVER['HTTP_USER_AGENT'].$data.$_ENV['cryptKey']),0,10);
	}
	///puts messages in cookie for next pageload
	function saveMessages($targetPage=null){
		$cookie['data'] = serialize($this->messages);
		$cookie['code'] = self::saveMessagesCode($cookie['data']);
		if($targetPage){
			$cookie['target'] = $targetPage;
		}
		
		Cookie::set('_PageMessages',serialize($cookie));
	}
	//saves messages before redirect
	function redirect($path=null){
		$this->saveMessages();
		Http::redirect($path);
	}
}
