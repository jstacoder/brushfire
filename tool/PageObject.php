<?
class PageObject{
	public $item,$items;
	/**
	While PageTool holds within it items which are not a concern for the view, there is often data held within
		the PageTool which should be accessible by the view via page.  To shorten this process, and since there appears no
		foreseable mishap by which a PageTool property is loaded when there should be nothing, this get method maps unfound page attributes
		to the page tool
	
	Additionally, to make the tool methods available through the page objects, it is put into the "tool" attribute.  This happens to also allow
		the view to gain tool attributes over page attributes.
	*/
	
	function __get($name){
		if($name == 'tool'){
			$this->tool = new PageTool;
			$this->tool->item = &$this->item;
			$this->tool->items = &$this->items;
			return $this->tool;
		}
		return $this->tool->$name;
	}
	
}