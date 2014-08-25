<?
class Cron{
	/**
		$list = array(
				array(
					filter, script, arguments
				)
			)
		
		numeric array, value types:
			rXXX	: regular expresson match against YYYYMMDDHHMM
			XXX		: normal cron syntax
	*/
	static $list = array();
	static $running = array();///<not necessarily running, just with last known status of running
	static $lastParsedTime;
	static $args;///<args used by child cron scripts, from self::$list
	static function start(){
		if(!Lock::isOn('cron-running-'.$_ENV['projectName'])){
			if(Lock::on('cron-running-'.$_ENV['projectName'])){
				Debug::out('Starting');
				Control::req('scripts/crons/config');
				while(!Lock::isOn('cron-off-'.$_ENV['projectName'])){
					$time = new Time;
//+	ensure running only every minute {
					$parseTime = $time->format('YmdHi');
					if(self::$lastParsedTime == $parseTime){
						sleep(2);
						continue;
					}
					self::$lastParsedTime = $parseTime;
//+	}
//+	execute run items {
					$run = self::getRun($time);
					foreach($run as $i){
						$item = self::$list[$i];
						if(self::$running[$i]){
							$pid = pcntl_waitpid(self::$running[$i],$status,WNOHANG);
							if($pid == 0){
								//process is currently running, don't run again
								continue;
							}elseif($pid == -1){
								Debug::out('Process error:',$item);
								continue;
							}
						}
						$pid = pcntl_fork();
						if($pid == -1){
							Debug::quit('Could not fork process',$item);
						}elseif($pid){
							//this is the parent
							self::$running[$i] = $pid;
						}else{
							//this is the child
							self::$args = $item[2];
							$script = Config::userFileLocation($item[1],'control/scripts/crons');
							Control::req($script);
							exit;
						}
						
					}
//+	}
				}
				Lock::off('cron-running-'.$_ENV['projectName']);
			}else{
				Debug::quit('Failed to lock "cron-running"');
			}
		}else{
			Debug::quit('Cron already running');
		}
		
	}
	static function restart(){
		//ensure no errors in the config
		self::checkConfig();
		Debug::out('Restarting');
		self::stop();
		self::start();
	}
	static function stop(){
		Debug::out('Stopping');
		//set lock to tell existing cron to turn off
		if(!Lock::on('cron-off-'.$_ENV['projectName'])){
			Debug::quit('Lock off failed');
		}
		echo "\n";
		
		//wait for exist cron to turn off
		while(Lock::isOn('cron-running-'.$_ENV['projectName'])){
			echo '.';
			sleep(1);
		}
		
		//remove lock off
		Lock::off('cron-off-'.$_ENV['projectName']);
		Debug::out('Stopped');
	}
	static function checkConfig(){
		Control::req('scripts/crons/config');
		$run = self::getRun(new Time);
		Debug::out('Config check completed');
	}
	///calculate crons to run
	static function getRun($time){
		$datetime = $time->datetime();
		$cron['minute'] = (int)$time->format('i');
		$cron['hour'] = (int)$time->format('H');
		$cron['day'] = (int)$time->format('d');
		$cron['month'] = (int)$time->format('m');
		$cron['dow'] = (int)$time->format('w');
		//loop through each and add to $run
		$run = array();
		foreach(self::$list as $i=>$item){
			$filter = $item[0];
//+	regex match {
			if(substr($filter,0,1) == 'r'){//regex match
				$filter = substr($filter,1);
				if(preg_match('@'.$filter.'@',$datetime)){
					$run[] = $i;
				}
			}
//+	}
//+	standard cron syntax match {
			else{
				$partFail = false;
				$parts = preg_split('@\s+@',$filter);
				#parts: m h  dom mon dow
				if(count($parts) != 5){
					Debug::quit('Bad cron, not enough parts in standard format',$i,$item);
				}
				$i2 = 0;
				foreach($cron as $v){
					$part = $parts[$i2];
					//cron wildcard divider
					if(strpos($part,'*') !== false){
						$part = str_replace('*',$v,$part);
						$eval = eval('return '.$part.';');
						//make sure no decimal place (as */2 and such results in whole numbers for match)
						if((int)$eval != $eval){
							$partFail = true;
							break;
						}
					}else{
						if((int)$part != $v){
							$partFail = true;
							break;
						}
					}
					$i2++;
				}
				if(!$partFail){
					$run[] = $i;
				}
				
			}
//+	}
		}
		return $run;
	}
}
