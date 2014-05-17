<?
class PageObject{
	public $item,$items;
	/**
	While SectionPage holds within it items which are not a concern for the view, there is often data held within
		the SectionPage which should be accessible by the view via page.  To shorten this process, and since there appears no
		foreseable mishap by which a SectionPage property is loaded when there should be nothing, this get method maps unfound page attributes
		to the section page
	
	Additionally, to make the tool methods available through the page objects, it is put into the "tool" attribute.  This happens to also allow
		the view to gain tool attributes over page attributes.
	*/
	
	function __get($name){
		if($name == 'tool'){
			$this->tool = new SectionPage;
			$this->tool->item = &$this->item;
			$this->tool->items = &$this->items;
			return $this->tool;
		}
		return $this->tool->$name;
	}
	
}