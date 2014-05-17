<?
///Fallback used in case page has no specific SectionPage tool


//attempt to autoload setion if it is available
try{
	class_exists('Section');
}catch(AutoloadException  $e){}

//if section found, use it
if(class_exists('Section',false)){
	class SectionPage extends Section{
		function __construct($page){
			$this->page = $page;
			$this->in =& $page->in;
			$this->messages =& $page->messages;
			if(method_exists(get_parent_class(),'__construct')){
				return parent::__construct();
			}
		}
	}
}else{
	class SectionPage{
		static $model,$id;
		function __construct($page){
			$this->page = $page;
			$this->in =& $page->in;
			$this->messages =& $page->messages;
		}
	}
}