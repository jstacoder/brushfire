<?
///take a string and temporarily turn it into a file for operating on with Spl object handles
class StringFile extends FileObject{
	///will clear string to save resources
	function __construct(&$string){
		$this->file = tempnam(Config::$x['storageFolder'],'stringFile.');
		file_put_contents($this->file,$string);
		parent::__construct($this->file);
	}
	function __destruct(){
		unlink($this->file);
	}
}