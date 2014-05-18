<?
/**
Expects tables:
	ban
		id,	identity, type_id, expire, created
	ban_types
		id, name, reason, threshold_time, limit, ban_duration

*/
class Ban extends SingletonDefault{
	static $primary,$instances;
	/**
	@param	identity	identity to identify banning info
	*/
	static function init($identity=null){
		$identity = $identity ? $identity : $_SERVER['REMOTE_ADDR'];
		return parent::init($identity);
	}
	
	static $banTypes;
	protected function __construct($identity,$db=null){
		if(!$db){
			$db = Db::$primary;
		}
		$this->db = $db;
		
		if(mt_rand(1,200) === 1){
			$this->maintenance();
		}
		$this->identity = $identity;
		$bans = unserialize(Cache::get('bans-'.$this->identity));
		if($bans){
			$this->banned($bans);
		}
		self::$banTypes = unserialize(Cache::get('table_ban_types'));
		if(!self::$banTypes){
			self::$banTypes = $this->db->columnKey('name','ban_type','1=1');
		}
	}
	///clears cached bans, but not db bans
	protected function clearBans($identity=null){
		$identity = $identity ? $identity : $_SERVER['REMOTE_ADDR'];
		Cache::delete('bans-'.$identity);
		Cache::delete('banning-'.$identity);
	}
	///load bans from db
	protected function maintenance(){
		$rows = $this->db->rows('select identity, bt.reason, b.expire
			from ban b
				left join ban_type bt on b.type_id_ = bt.id
			where (b.expire >= '.$this->db->quote(new Time).' or expire is null)');
		$identityBans = Arrays::compileSubsOnKey($rows,'identity');
		foreach($identityBans as $identity => $bans){
			$putBans = array();
			foreach($bans as $ban){
				$putBans[] = Arrays::extract(array('reason','expire'),$ban);
			}
			Cache::set('bans-'.$identity,serialize($putBans));
		}
	}
	///presents ban message, or clears ban if all expired
	protected function banned($bans){
		foreach($bans as $k=>$ban){
			if($ban['expire'] == -1 || $ban['expire'] > (new Time)->unix){
				die('Banned.  Reason: '.$ban['reason'].'; Until: '.($ban['expire'] == -1 ? 'Indefinite' : (new Time($ban['expire'])).' UTC'));
			}
		}
		//died on no ban, so all of them expired.  So, clear ban cache.
		Cache::delete('bans-'.$this->identity);
	}
	///get banning info for identity
	protected function getBanning(){
		return (array)unserialize(Cache::get('banning-'.$this->identity));
	}
	///set banning info ofr identity
	protected function setBanning($data){
		Cache::set('banning-'.$this->identity,serialize($data),0);
	}
	
	///Adds points to a ban type, and, upon limit, bans
	/**
	Upon reaching a limit, ban is applied, and, name+, if present, is incremented.
	Ban time, if present, is passed to new Time().  Otherwise, permanent.
	
	@param	name	ban type name
	@param	points	points to add to current
	*/
	protected function points($name,$points=1,$exitOnBan=true){
		$banType = self::$banTypes[$name];
		if(!$banType){
			return;
		}
		$banning = $this->getBanning();
		//append the ban type with the new instance
		$banning[$name][] = array(time(),$points);
		
		//+	check if over limit {
		$expired = time() - $banType['threshold_time'];
		foreach($banning[$name] as $k=>$instance){
			if($instance[0] < $expired){
				unset($banning[$name][$k]);
			}else{
				$total += $instance[1];
			}
		}
		//+	}
		if($total >= $banType['limit']){
			unset($banning[$name]);//ban is  being set, no reason to keep the banning  info (and might cause wrongful reban)
			$this->setBanning($banning);
			$this->points($name.'+',1,false);
			$this->ban($name,$exitOnBan);
		}
		$this->setBanning($banning);
	}
	///add a ban
	/**
	@param	name	name of ban_type
	@param	exit	whether to exit after adding ban
	*/
	protected function ban($name,$exit=true){
		$banType = self::$banTypes[$name];
		$this->db->insert('ban',array(
				'identity' => $this->identity,
				'type_id_' => $banType['id'],
				'expire' => ($banType['ban_duration'] ? new Time($banType['ban_duration']) : null),
				'created' => new Time
			));
		$bans = unserialize(Cache::get('bans-'.$this->identity));
		$bans[] = array('reason' =>$banType['reason'],'expire'=>(new Time($banType['ban_duration']))->unix);
		Cache::set('bans-'.$this->identity,serialize($bans));
		if($exit){
			$this->banned($bans);
		}
	}
}