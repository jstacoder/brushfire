<?
///very simple encryption class
class Encryption{
	///encrypt using Config variables
	/**
	@param	data	data to encrypt
	*/
	static function encrypt($data){
		$ivSize = mcrypt_get_iv_size($_ENV['cryptCipher'],$_ENV['cryptMode']);
		$iv = mcrypt_create_iv($ivSize, MCRYPT_RAND);
		return $iv.mcrypt_encrypt($_ENV['cryptCipher'],$_ENV['cryptKey'],$data,$_ENV['cryptMode']);		
	}
	///unencrypt using Config variables
	/**
	@param	data	data to decrypt
	*/
	static function decrypt($data){
		$ivSize = mcrypt_get_iv_size($_ENV['cryptCipher'],$_ENV['cryptMode']);
		$iv = substr($data,0,$ivSize);
		$data = substr($data,$ivSize);
		return mcrypt_decrypt($_ENV['cryptCipher'],$_ENV['cryptKey'],$data,$_ENV['cryptMode'],$iv);
	}
}