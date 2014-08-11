<?

///Fallback used in case page has no specific Page tool


//attempt to autoload setion if it is available
try{
	class_exists('Section');
}catch(AutoloadException  $e){}

//if section found, use it
if(class_exists('Section',false)){
	class Page extends Section{
		function __construct($page){
			$this->control = $page;
			$this->in =& $page->in;
			$this->messages =& $page->messages;
			$this->db = Db::primary();
			if(method_exists(get_parent_class(),'__construct')){
				return parent::__construct();
			}
		}
	}
}else{
	class Page{
		static $model,$id;
		function __construct($page){
			$this->control = $page;
			$this->in =& $page->in;
			$this->messages =& $page->messages;
			$this->db = Db::primary();
		}
	}
}
