<?
///Used for getting data from model (called in the control) to templates
/**
The philosophy:
	Any server response which would normally engage the need for subdata collection (ie, not just outputting static html or images) is considered a "Page" response.
	Pages have common behaviors.  This class serves to:
		Provide a generic variable for data (like a $global, but contexted to the Page) to be used across the incontiguous parts (model, view, etc)
		Provide a standard input variable usable on both command line and web server pages
		Provide backend to frontend handling of messages (errors)
	
	Four types of messages are recognized:
		Errors: Things that prevent movement forward
		Warnings: Things tthat may later prevent movement forward
		Notices: Things to optmize movement forward
		Success: Indicator of movement forward
*/
class Page{
	use SingletonDefault;
	static $in;///<a combination of get and post using special handling of repeated tokens.  Name comes from stdin - "std".
	protected $originalIn;///<an unmodified version of $in
	public $messages;///<system messages to display to the user
	
	static function setPrimary($instanceName){
		static::$primary = static::$instances[$instanceName];
		self::$in =& self::$primary->in;
	}
	
	///prevent public instantiation
	function __construct($in=false,$cookieMessagesName=false){
		//+	parse input{
		if($in===false){//apply default
			//+	Handle GET and POST variables{
			$in['get'] = $_SERVER['QUERY_STRING'];
			//can cause script to hang (if no stdin), so don't run if in script unless configured to
			if(!Config::$x['inScript'] || Config::$x['scriptGetsStdin']){
				//multipart forms can either result in 1. input being blank or 2. including the upload.  In case 1, post vars can be taken from $_POST.  In case 2, need to avoid putting entire file in memory by parsing input
				if(!preg_match('@multipart/form-data@',$_SERVER['CONTENT_TYPE'])){
					$in['post'] = file_get_contents('php://input');
					$in['post'] = $in['post'] ? $in['post'] : file_get_contents('php://stdin');
				}elseif($_POST){
					$in['post'] = http_build_query($_POST);
				}
			}
			$in['post'] = Http::parseQuery($in['post'],Config::$x['pageInPHPStyle']);
			$in['get'] = Http::parseQuery($in['get'],Config::$x['pageInPHPStyle']);
			$this->in = Arrays::merge($in['get'],$in['post']);
			//+	}
		}elseif(is_array($in)){
			$this->in = $in;
		}else{
			$this->in = Http::parseQuery($in,Config::$x['pageInPHPStyle']);
		}
		$this->originalIn = $this->in;
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
							if(!array_diff($cookie['target'],Route::$urlTokens)){
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

	}
	/**
	-Concerns not exposed to SectionPage expect relavent data to be in Page, but, for convenience, SectionPage writes to itself, so, if data is not in page, check SectionPage.
	*/
	function __get($name){
		if($name == 'tool'){
			return $this->constructSectionPage();
		}
		return $this->tool->$name;
	}
	///various often used resources should be immediately available to SectionPage
	protected function constructSectionPage($additional=null){
		$this->tool = new SectionPage($this,$additional);
		if(!isset($this->tool->page)){
			$this->tool->page = $this;
			$this->tool->in =& $this->in;
			$this->tool->messages =& $this->messages;
		}
		if(!$this->tool->db){
			$this->tool->db = Db::$primary;
		}
		return $this->tool;
	}
	
	protected function error($message,$name=null,$options=null){
		$this->message($message,$name,'error',$options);
	}
	protected function success($message=null,$name=null,$options=null){
		if(!$message){
			$message = View::pageTitle().' successful';
		}
		$this->message($message,$name,'success',$context,$options);
	}
	protected function notice($message,$name=null,$options=null){
		$this->message($message,$name,'notice',$options);
	}
	protected function warning($message,$name=null,$options=null){
		$this->message($message,$name,'warning',$options);
	}
	protected function message($message,$name,$type,$options=null){
		$context = $context ? $context : 'default';
		$message = array('type'=>$type,'context'=>$context,'name'=>$name,'content'=>$message);
		if($options){
			$message = Arrays::merge($message,$options);
		}
		$this->messages[] = $message;
	}
		
	//checks if there is a field error for a given field in a given context
	protected function fieldError($field,$context='default'){
		return $this->getMessages('error',$context,$field);
	}
	///checks if there was an error.  Defaults to checking all contexts
	protected function errors($context=null){
		return $this->getMessages('error',$context);
	}
	///get messages based on context and name
	protected function getMessages($type=null,$context=null,$name=null){
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
	
	/**
	@param	fields	array	array with keys being fields and values being rules to apply to fields.  See appyFilterValidateRules for rule syntax
		self::filterAndValidate(
			array(
				'email' => rules
				'loginName' => rules
			)
		);
		
	@return	null, so use self::errors() to see if any errors were generated
	*/
	protected function filterAndValidate($fields,$options=true){
		if($options['filterArrays'] || !isset($options['filterArrays'])){
			foreach($fields as $field=>$rules){
				if(is_array($this->in[$field])){
					FieldIn::makeString($this->in[$field]);
				}
			}
		}
		
		foreach($fields as $field=>$rules){
			$continue = $this->applyFilterValidateRules($field, $rules,$options['errorOptions']);
			if(!$continue){
				break;
			}
		}
	}
	
	/**
	@param	rules	string or array	
		Rules can be an array of rules, or a string separated by "," for each rule.  
		Each rule can be a string or an array.  
		As a string, the rule should be in one of the following forms:
				"f:name|param1;param2" indicates InputFilter method
				"v:name|param1;param2" indicates InputValidate function
				"g:name|param1;param2" indicates global scoped function
				"class:name|param1,param2,param3" indicates static method "name: of class "class" 
				"p:name|param1,param2,param3" SectionPage function
				"name" replaced by FieldIn fieldType of the same name
		As an array, the rule function part (type:method) is the first element, and the parameters to the function part are the following elements.  Useful if function arguments contain commas or semicolons.  Ex:
			array('type:method','arg1','arg2','arg3')
		
		The "type:method" part can be prefixed with "!" to indicate there should be a break on error, and no more rules for that field should be applied
		The "type:method" part can be prefixed with "!!" to indicate there should be a break on error and no more rules for any field should be applied
		
		If array, first part of rule is taken as string with the behavior above without parameters and the second part is taken as the parameters; useful for parameters that include commas or semicolons or which aren't strings
		
		Examples for rules:
			1: 'v:email|bob.com,customClass:method|param1;param2',
			2: array('v:email|bob.com','customClass:method|param1;param2'),
			3: array(array('v:email','bob.com'),array('customClass:method','param1','param2')),
	*/
	protected function applyFilterValidateRules($field, $rules, $errorOptions){
		$originalRules = $rules;
		$rules = Arrays::stringArray($rules);
		for($i=0;$i<count($rules);$i++){
			$rule = $rules[$i];
			$params = array(&$this->in[$field]);
			if(is_array($rule)){
				$callback = array_shift($rule);
				$params2 = &$rule;
			}else{
				list($callback,$params2) = explode('|',$rule);
				
				if($params2){
					$params2 = explode(';',$params2);
				}
			}
			///merge field value param with the user provided params
			if($params2){
				Arrays::mergeInto($params,$params2);
			}
			
			//used in combination with !, like ?! for fields that, if not empty, should be validated, otherwise, ignored.
			$ignoreError = false;
			if(substr($callback,0,1) == '?'){
				$callback = substr($callback,1);
				$ignoreError = true;
			}
			
			if(substr($callback,0,2) == '!!'){
				$callback = substr($callback,2);
				$superBreak = true;
			}
			if(substr($callback,0,1) == '!'){
				$callback = substr($callback,1);
				$break = true;
			}
			
			list($type,$method) = explode(':',$callback);
			if(!$method){
				$method = $type;
				$type = '';
			}
			
			if(!$method){
				Debug::quit('Failed to provide method for input handler on field: '.$field, 'Rules:', $rules);
			}
			
			try{
				switch($type){
					case 'f':
						call_user_func_array(array('InputFilter',$method),$params);
					break;
					case 'v':
						call_user_func_array(array('InputValidate',$method),$params);
					break;
					case 'p':
						call_user_func_array(array($this->tool,$method),$params);
					break;
					case 'g':
						call_user_func_array($method,$params);
					break;
					case '':
						//get new rules and start over from current position
						if(!FieldIn::$types[$method]){
							Debug::quit('Unknown standard field on field '.$field,'Rule:',$rule);
						}
						$newRules = Arrays::stringArray(FieldIn::$types[$method]);
						if($i + 1 < count($rules)){
							$newRules = array_merge($newRules,array_slice($rules,$i + 1));
						}
						$rules = $newRules;
						$i = -1;
					break;
					default:
						call_user_func_array(array($type,$method),$params);
					break;
				}
			}catch(InputException $e){
				//add error to messages
				if(!$ignoreError){
					$this->error($e->getMessage(),$field,$errorOptions);
				}
				
				//super break will break out of all fields
				if($superBreak){
					return false;
				}
				//break will stop validators for this one field
				if($break){
					break;
				}
			}
		}
		return true;
	}
	///to prevent fabrication of system messages.  No spurious success messages!
	static function saveMessagesCode($data){
		return substr(sha1($_SERVER['HTTP_USER_AGENT'].$data.Config::$x['cryptKey']),0,10);
	}
	///puts messages in cookie for next pageload
	protected function saveMessages($targetPage=null){
		$cookie['data'] = serialize($this->messages);
		$cookie['code'] = self::saveMessagesCode($cookie['data']);
		if($targetPage){
			$cookie['target'] = $targetPage;
		}
		
		Cookie::set('_PageMessages',serialize($cookie));
	}
}