<?
///Used for simple database or file sessions with some allowed verification of authenticity built in
/** This class can be remade for heavy traffic sites by caching the read and make the update session time action only happen occasionally

*/
class Session{
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
	
	static function __callStatic($name,$arguments){
		if(self::$started){
			return call_user_func_array(array(self,'_'.$name),$arguments);
		}
	}
	///makes this class the session save handler, and tried to open a session
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
	static function open(){
		self::$data = new SessionData;
		if($_COOKIE['sessionId']){
			
			//ensure session is authentic
			if($_COOKIE['sessionKey'] == self::makeKey()){
				//check session actually exists
				if(self::$data->exists()){
					//refresh cookie with new expiry time
					if(Config::$x['sessionCookieExpiryRefresh']){
						if(rand(1,Config::$x['sessionCookieExpiryRefresh']) === 1){
							Cookie::set('sessionId',$_COOKIE['sessionId'],array('expire'=>Config::$x['sessionCookieExpiry']));
							Cookie::set('sessionKey',$_COOKIE['sessionKey'],array('expire'=>Config::$x['sessionCookieExpiry']));
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
			
			Cookie::set('sessionId',$id,array('expire'=>Config::$x['sessionCookieExpiry']));
			
			$key = self::makeKey();
			Cookie::set('sessionKey',$key,array('expire'=>Config::$x['sessionCookieExpiry']));
			
			self::$started = true;
			self::$data->create();
	}
	private static function _close(){}
	private static function _read(){
		$_SESSION = self::$data->get();
	}
	private static function _write(){
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
	private static function _destroy(){
		self::$started = false;
		session_unset();
		self::$data->delete();
		self::$data = null;
	}
	private static function _gc(){
		if(Config::$x['sessionUseDb']){
			Db::delete(Config::$x['sessionDbTable'],'time < '.strtotime(Config::$x['sessionExpiry']).' and permanent is null');
		}else{
			foreach(scandir(Config::$x['sessionFolder']) as $file){
				//file is not folder and is not permanent session
				if(!is_dir(Config::$x['sessionFolder'].$file) && !preg_match('@\.permanent$@',$file)){
					//file is older than expiry time
					if(filectime(Config::$x['sessionFolder'].$file) < strtotime(Config::$x['sessionExpiry'])){
						unlink(Config::$x['sessionFolder'].$file);
					}
				}
			}
		}
	}
	///used to make a session unpermanent
	static function makeUnpermanent(){
		if(Config::$x['sessionUseDb']){
			Db::update(Config::$x['sessionDbTable'],array('permanent'=>'null'),array('id'=>$_COOKIE['sessionId']));
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
		if(Config::$x['sessionUseDb']){
			Db::update(Config::$x['sessionDbTable'],array('permanent'=>'1'),array('id'=>$_COOKIE['sessionId']));
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
		if(Config::$x['sessionUseDb']){
			return Db::row(Config::$x['sessionDbTable'],array('id'=>$_COOKIE['sessionId']),'1');
		}else{
			//see if permanent session file exists
			if(is_file(Config::$x['sessionFolder'].$_COOKIE['sessionId'].'.permanent')){
				$this->file = Config::$x['sessionFolder'].$_COOKIE['sessionId'].'.permanent';
				return true;
			}elseif(is_file(Config::$x['sessionFolder'].$_COOKIE['sessionId'])){
				$this->file = Config::$x['sessionFolder'].$_COOKIE['sessionId'];
				return true;
			}
			return false;
		}
		
	}
	function delete(){
		if(Config::$x['sessionUseDb']){
			$data = Db::delete(Config::$x['sessionDbTable'],array('id'=>$_COOKIE['sessionId']));
		}else{
			$data = unlink($this->file);
		}
	}
	function get(){
		if(Config::$x['sessionUseDb']){
			$data = Db::row(Config::$x['sessionDbTable'],array('id'=>$_COOKIE['sessionId']),'data');
		}else{
			$data = file_get_contents($this->file);
		}
		$this->hash = md5($data);
		$data = $data ? unserialize($data) : null;
		return $data;
	}
	function create(){
		if(Config::$x['sessionUseDb']){
			$insert = Session::$other;
			$insert['id'] = $_COOKIE['sessionId'];
			$insert['time'] = time();
			Db::insert(Config::$x['sessionDbTable'],$insert);
		}else{
			$this->file = Config::$x['sessionFolder'].$_COOKIE['sessionId'];
			touch($this->file);
		}
	}
	///Update the other columns (besides data)
	function writeOther(){
		if(Config::$x['sessionUseDb']){
			$update = Session::$other;
			$update['time'] = time();
			Db::update(Config::$x['sessionDbTable'],$update,array('id'=>$_COOKIE['sessionId']));
		}else{
			//other corresponds to nothing on file based sessions
		}
	}
	function write($data){
		if(Config::$x['sessionUseDb']){
			$update = Session::$other;
			$update['time'] = time();
			$update['data'] = $data;
			Db::update(Config::$x['sessionDbTable'],$update,array('id'=>$_COOKIE['sessionId']));
		}else{
			file_put_contents($this->file,$data);
		}
	}
	function updateTime(){
		if(Config::$x['sessionUseDb']){
			Db::update(Config::$x['sessionDbTable'],array('time'=>time()),array('id'=>$_COOKIE['sessionId']));
		}else{
			touch($this->file);
		}
	}
}
