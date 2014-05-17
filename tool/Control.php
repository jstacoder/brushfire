<?
///Standard control functions
class Control{
	use SingletonDefault;
	function __construct($page=null){
		if(!$page){
			global $page;
		}
		$this->page = $page;
		$this->view = View::init(null,$page);
	}
	///Attempt to get id from user request
	/**
		Look for "id" in.  Look for integers in url path, starting from base outwards
	*/
	protected function getId(){
		$id = abs($this->page->in['id']);
		if(!$id){
			$tokens = Route::$urlTokens;
			krsort($tokens);
			foreach($tokens as $token){
				$id = abs($token);
				if($id){
					break;
				}
			}
		}
		
		$this->page->tool->id = $id;
		return $id;
	}
	///setup .  Since this is a primaryId, error 
	protected function primaryId($type=null){
		$id = $this->getId($type);
		if(!$id){
			self::badId();
		}
		
		$this->view->json['id'] = $id;
		if(!$type){
			$type = Arrays::at(Route::$urlTokens,-2);
			if(strtolower($type) == 'read'){
				$type = Arrays::at(Route::$urlTokens,-3);
			}
			$type = strtolower($type);
		}
		$this->view->json['id_type'] = $type;
	}
	protected function error($message){
		$this->page->error($message);
		Config::loadUserFiles(Config::$x['errorPage'],null,null,array('error'=>$message));
		exit;
	}
	protected function badId(){
		unset(View::$primary->json['id'],View::$primary->json['id_type']);
		unset($this->page->tool->id);
		$this->error('Id not found');
	}
	//+	special handling of class "model" to localize to page section specific model{
	protected function sectionAutoloadSetup(){
		spl_autoload_register(array($this,'sectionAutoload'),true,true);
	}
	protected function sectionClass(){
		if($type = ucwords(Tool::toCamel(implode(' ',array_slice(Route::$parsedUrlTokens,0,-1))))){
			return $type.'Section';
		}
	}
	protected function getOwner($item){
		if($item['user_id__owner']){
			return $item['user_id__owner'];
		}
		if($item['user_id']){
			return $item['user_id'];
		}
		if($item['user_id__creater']){
			return $item['user_id__creater'];
		}
		$keys = array_keys($item);
		foreach($keys as $key){
			if(preg_match('@user_id__@',$key)){
				return $item[$key];
			}
		}
	}
	protected function sectionAutoload($className){
		if($className == 'Section'){
			if($sectionClass = $this->sectionClass()){
				$result = Autoload::load($sectionClass);
				if($result['found']){
					class_alias($sectionClass,'Section',true);
				}
			}
		}
	}
	//+	}
	static function req($path){
		$file = Config::userFileLocation($path,'control').'.php';
		return files::req($file,array('page'));
	}
}