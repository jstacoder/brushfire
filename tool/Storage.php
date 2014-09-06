<?
///Used to put structured data in and take things out of project storage
class Storage{
	static function get($name,$delete=false){
		clearstatcache();
		if(is_file($_ENV['storageFolder'].$name.'._.info')){
			$options = unserialize(file_get_contents($_ENV['storageFolder'].$name.'._.info'));
		}
		//perhaps throw exception on fail
		$contents = file_get_contents($_ENV['storageFolder'].$name);
		if($options['serialize']){
			$contents = unserialize($contents);
		}
		if($delete){
			@unlink($_ENV['storageFolder'].$name.'._.info');
			@unlink($_ENV['storageFolder'].$name);
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
		file_put_contents($_ENV['storageFolder'].$name,$contents);
		if($options){
			file_put_contents($_ENV['storageFolder'].$name.'._.info',serialize($options));
		}
	}
}


