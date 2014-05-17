<?
///Used to put things in and take things out of instance storage
class Storage{
	static function get($name,$delete=false){
		clearstatcache();
		if(is_file(Config::$x['storageFolder'].$name.'._.info')){
			$options = unserialize(file_get_contents(Config::$x['storageFolder'].$name.'._.info'));
		}
		//perhaps throw exception on fail
		$contents = file_get_contents(Config::$x['storageFolder'].$name);
		if($options['serialize']){
			$contents = unserialize($contents);
		}
		if($delete){
			@unlink(Config::$x['storageFolder'].$name.'._.info');
			@unlink(Config::$x['storageFolder'].$name);
		}
		return $contents;
	}
	static function put($name,$contents,$options=null){
		if(is_array($contents)){
			$contents = serialize($contents);
			$options['serialize'] = true;
		}elseif(is_object($contents)){
			if(!method_exists($contents,'__toString')){
				$contents = serialize($contents);
				$options['serialize'] = true;
			}
		}
		file_put_contents(Config::$x['storageFolder'].$name,$contents);
		if($options){
			file_put_contents(Config::$x['storageFolder'].$name.'._.info',serialize($options));
		}
	}
}


