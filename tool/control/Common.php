<?
namespace control; use \Control; use \Tool;
///Standard control functions
class Common{
	use \SingletonDefault;
	function __construct($control=null){
		$this->control = $control ? $control : Control::primary();
		$this->view = $this->control->view;
	}
	///Attempt to get id from user request
	/**
		Look for "id" in.  Look for integers in url path, starting from base outwards
	*/
	protected function getId(){
		$id = abs($this->control->in['id']);
		if(!$id){
			$tokens = Route::$tokens;
			krsort($tokens);
			foreach($tokens as $token){
				$id = abs($token);
				if($id){
					break;
				}
			}
		}
		
		if($id){
			$this->control->id = $id;
			return $id;
		}
	}
	protected function reqId($failCallback=null){
		return $this->primaryId($failCallback);
	}
	///setup .  Since this is a primaryId, error 
	protected function primaryId($failCallback=null){
		$id = $this->getId();
		if(!$id){
			if(!$failCallback){
				return call_user_func($failCallback);
			}
			$this->error('Id not found');
		}
		return $id;
	}
	protected function error($message){
		$this->control->error($message);
		\Config::loadUserFiles($_ENV['errorPage'],null,null,array('error'=>$message));
		exit;
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
	static function req($path){
		$file = \Config::userFileLocation($path,'control').'.php';
		return \Files::req($file,array('control'));
	}
	public $csrfToken = null;
	protected function getCsrfToken(){
		if(!$this->csrfToken){
			$this->csrfToken = Tool::randomString(20);
			$_SESSION['csrfToken'] = $this->csrfToken;
		}
		return $this->csrfToken;
	}
	protected function validateCsrfToken(){
		$csrfToken = $_SESSION['csrfToken'];
		unset($_SESSION['csrfToken']);
		if(!$this->control->in['csrfToken']){
			\Debug::toss('Missing CSRF token','InputException');
		}elseif($this->control->in['csrfToken'] != $csrfToken){
			\Debug::toss('CSRF token mismatch','InputException');
		}
	}
	protected function forceCsrfToken(){
		$csrfToken = $_SESSION['csrfToken'];
		unset($_SESSION['csrfToken']);
		if(!$this->control->in['csrfToken'] || $this->control->in['csrfToken'] != $csrfToken){
			die('csrf failure');
		}
	}
}
