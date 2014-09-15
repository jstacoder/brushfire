<?
///Used for simple database or file sessions with some allowed verification of authenticity built in
class Session {
	///indicates that if the session is not started it should be started.
	static $start = true;
	///internal use.  Indicates the session is started and can be used
	static $started = false;
	
	///used with database sessions.  An array to be inserted into the session database table in addition to normal session data
	/**Note the presence of an array will force db write
	
	Example:
	Session::$other = array(
				'user' => $user,
				'user_name' => $userName,
				'is_admin' => true
			);
	*/
	static $other;
	
	///internal use.  data object
	static $data;
	
	///makes this class the session save handler, and tries to open a session
	///@note used b/c if handler set in loader, this class would be loaded on all requests
	static function start(){
		session_set_save_handler(
				array(Session,"open"),
				array(Session,"close"),
				array(Session,"read"),
				array(Session,"write"),
				array(Session,"destroy"),
				array(Session,"gc")
			);
		session_start();
	}
	private static function makeKey(){
		return substr(sha1(Http::getIp().$_SERVER['HTTP_USER_AGENT'].$_COOKIE['sessionId']),0,10);
	}
	
	///@note call start to register before using this method
	static function open(){
		self::$data = new SessionData;
		if($_COOKIE['sessionId']){
			//ensure session is authentic
			if($_COOKIE['sessionKey'] == self::makeKey()){
				//check session actually exists
				if(self::$data->exists()){
					//refresh cookie with new expiry time
					if($_ENV['sessionCookieExpiryRefresh']){
						if(rand(1,$_ENV['sessionCookieExpiryRefresh']) === 1){
							$expiry = $_ENV['sessionCookieExpiry'] ? (new Time($_ENV['sessionCookieExpiry']))->unix : 0;
							Cookie::set('sessionId',$_COOKIE['sessionId'],array('expire'=>$expiry));
							Cookie::set('sessionKey',$_COOKIE['sessionKey'],array('expire'=>$expiry));
						}
					}
					
					//set session as started
					self::$started = true;
					return true;
				}
			}
			
			Cookie::remove('sessionId');
			Cookie::remove('sessionKey');
			
			//re-run session in case instance wants to create new session
			self::open();
		}elseif(self::$start){
			self::create();
		}
	}
	static function create(){
			$id = md5(Http::getIp().$_SERVER['HTTP_USER_AGENT'].microtime().rand(1,20));
			
			$expiry = $_ENV['sessionCookieExpiry'] ? (new Time($_ENV['sessionCookieExpiry']))->unix : 0;
			
			Cookie::set('sessionId',$id,array('expire'=>$expiry));
			
			$key = self::makeKey();
			Cookie::set('sessionKey',$key,array('expire'=>$expiry));
			
			self::$started = true;
			self::$data->create();
	}
	static function close(){}
	static function read($id){
		if(!self::$started){ return; }
		
		$_SESSION = self::$data->get();
	}
	static function write($id,$data){
		if(!self::$started){ return; }
		
		$data = serialize($_SESSION);
		//data wasn't changed
		if(md5($data) == self::$data->hash){
			if(Session::$other){
				self::$data->writeOther($data);
			}else{
				self::$data->updateTime();
			}
		}else{
			self::$data->write($data);
		}
	}
	static function destroy($id){
		if(!self::$started){ return; }
		
		self::$started = false;
		session_unset();//frees the variables (like $_SESSION)
		self::$data->delete();
		self::$data = null;
		
		Cookie::remove('sessionId');
		Cookie::remove('sessionKey');
	}
	static function gc(){
		if(!self::$started){ return; }
		
		if($_ENV['sessionUseDb']){
			Db::delete($_ENV['sessionDbTable'],'updated__unix < '.strtotime($_ENV['sessionExpiry']).' and permanent is null');
		}else{
			foreach(scandir($_ENV['sessionFolder']) as $file){
				//file is not folder and is not permanent session
				if(!is_dir($_ENV['sessionFolder'].$file) && !preg_match('@\.permanent$@',$file)){
					//file is older than expiry time
					if(@filectime($_ENV['sessionFolder'].$file) < strtotime($_ENV['sessionExpiry'])){
						@unlink($_ENV['sessionFolder'].$file);
					}
				}
			}
		}
	}
	///used to make a session unpermanent
	static function makeUnpermanent(){
		if($_ENV['sessionUseDb']){
			Db::update($_ENV['sessionDbTable'],array('permanent'=>'null'),array('id'=>$_COOKIE['sessionId']));
		}else{
			if(preg_match('@\.permanent$@',self::$data->file)){
				$newFile = preg_replace('@.permanent$@','',self::$data->file);
				rename(self::$data->file,$newFile);
				self::$data->file = $newFile;
			}
		}
	}
	///used to make a session permanent; aka, garbase collector will not remove regardless of how long the inactivity was
	static function makePermanent(){
		if($_ENV['sessionUseDb']){
			Db::update($_ENV['sessionDbTable'],array('permanent'=>'1'),array('id'=>$_COOKIE['sessionId']));
		}else{
			if(!preg_match('@\.permanent$@',self::$data->file)){
				$newFile = self::$data->file.'.permanent';
				rename(self::$data->file,$newFile);
				self::$data->file = $newFile;
			}
		}
	}
}
///internal use
class SessionData{
	public $file;
	public $hash;
	
	function exists(){
		if($_ENV['sessionUseDb']){
			return Db::row($_ENV['sessionDbTable'],array('id'=>$_COOKIE['sessionId']),'1');
		}else{
			//see if permanent session file exists
			if(is_file($_ENV['sessionFolder'].$_COOKIE['sessionId'].'.permanent')){
				$this->file = $_ENV['sessionFolder'].$_COOKIE['sessionId'].'.permanent';
				return true;
			}elseif(is_file($_ENV['sessionFolder'].$_COOKIE['sessionId'])){
				$this->file = $_ENV['sessionFolder'].$_COOKIE['sessionId'];
				return true;
			}
			return false;
		}
		
	}
	function delete(){
		if($_ENV['sessionUseDb']){
			$data = Db::delete($_ENV['sessionDbTable'],array('id'=>$_COOKIE['sessionId']));
		}else{
			$data = unlink($this->file);
		}
	}
	function get(){
		if($_ENV['sessionUseDb']){
			$data = Db::row($_ENV['sessionDbTable'],array('id'=>$_COOKIE['sessionId']),'data');
		}else{
			$data = file_get_contents($this->file);
		}
		$this->hash = md5($data);
		$data = $data ? unserialize($data) : null;
		return $data;
	}
	function create(){
		if($_ENV['sessionUseDb']){
			$insert = Session::$other;
			$insert['id'] = $_COOKIE['sessionId'];
			$insert['updated__unix'] = time();
			Db::insert($_ENV['sessionDbTable'],$insert);
		}else{
			$this->file = $_ENV['sessionFolder'].$_COOKIE['sessionId'];
			touch($this->file);
		}
	}
	///Update the other columns (besides data)
	function writeOther(){
		if($_ENV['sessionUseDb']){
			$update = Session::$other;
			$update['updated__unix'] = time();
			Db::update($_ENV['sessionDbTable'],$update,array('id'=>$_COOKIE['sessionId']));
		}else{
			//other corresponds to nothing on file based sessions
		}
	}
	function write($data){
		if($_ENV['sessionUseDb']){
			$update = Session::$other;
			$update['updated__unix'] = time();
			$update['data'] = $data;
			Db::update($_ENV['sessionDbTable'],$update,array('id'=>$_COOKIE['sessionId']));
		}else{
			file_put_contents($this->file,$data);
		}
	}
	function updateTime(){
		if($_ENV['sessionUseDb']){
			Db::update($_ENV['sessionDbTable'],array('updated__unix'=>time()),array('id'=>$_COOKIE['sessionId']));
		}else{
			touch($this->file);
		}
	}
}
