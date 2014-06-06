<?
///For handling output and templates, generally assuming use of http
class View{
	use SDLL;
	function __construct($page=null){
		if(!$page){
			global $page;
		}
		$this->control = $page;
	}
	function load(){
		foreach(Config::$x['aliasesFiles'] as $file){
			$extract = Files::inc($file,null,null,array('aliases'));
			$this->aliases = Arrays::merge($this->aliases,$extract['aliases']);
		}
	}
	
	///used to get the content of a single template file
	/**
	@param	template	string path to template file relative to the templateFolder.  .php is appended to this path.
	@param	vars	variables to extract and make available to the template file
	@return	output from a template
	*/
	protected function getTemplate($template,$vars=null){
		$vars['thisTemplate'] = $template;
		$vars['page'] = $this->control;
		$vars['view'] = $this;//$this is reserved, can not use outside of object context
		
		ob_start();
		if(substr($template,-4) != '.php'){
			$template = $template.'.php';
		}
		Files::req(Config::$x['templateFolder'].$template,null,$vars);
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	static $showArgs;
	///used as the primary method to show a collection of templates.  @attention parameters are the same as the View::get function
	protected function show(){
		$this->showArgs = func_get_args();
		Hook::runWithReferences('viewPreShow',$this->showArgs);
		$output = call_user_func_array(array($this,'get'),$this->showArgs);
		Hook::runWithReferences('viewPostShow',$output);
		Hook::run('preHTTPMessageBody');
		echo $output['combine'];
	}
	
	///calls show then dies
	protected function end(){
		call_user_func_array(array($this,'show'),func_get_args());
		exit;
	}
	
	///used to get a collection of templates without displaying them
	/**
	@param	templates	the input or output form of View::parseTemplateString
		
		where subtemplates is the passed into get as $templates. 
		
		 In the case of subtemplates, named ouput of each subtemplate along with the total previous output of the subtemplates is passed to the supertemplate.  The output of each subtemplate is passed by name in a $templates array, and the total output is available under the variable $input.
	@return output from the templates
	*/
	protected function get($templates,$parseTemplates=true){
		if(!$recursing){
			//this parses all levels, so only need to run on non-recursive call
			if(!is_array($templates)){
				$templates = self::parseTemplateString($templates);
			}
			$templates = self::parseAliases($templates,$this->aliases);
		}
			
		$return['collect'] = array();
		while($templates){
			$template = array_pop($templates);
			if(is_array($template)){
				if($template[2]){
					$got = self::get($template[2],false);
					if($template[0]){
						$output = $this->getTemplate($template[0],array('templates'=>$got['collect'],'input'=>$got['combine']));
					}
					Arrays::addOnKey(($template[1] ? $template[1] : $template[0]),$output,$return['collect']);
				}else{
					$output = $this->getTemplate($template[0]);
					Arrays::addOnKey(($template[1] ? $template[1] : $template[0]),$output,$return['collect']);
				}
			}else{
				$output = $this->getTemplate($template);
				Arrays::addOnKey($template,$output,$return['collect']);
			}
			$return['combine'] .= $output;
		}
		
		return $return;
	}
	
	
	/**
		3 forms exceptable.

		Multiline:
			$templateString = '
				template
					subtemplate
						subsubtemplate
						subsubtemplate
				template';
						
		Single line:
			$templateString = 'template		subtemplate			subsubtemplate			subsubtemplate	template';
			#$templateString = 'template,,subtemplate,,,subsubtemplate,,,subsubtemplate,template';
		
		template forms
			normal form 
				form: "file[:name]"
				Ex: blog/read
				Ex: blog/read:ViewBlog
				
				file is file path relative to templates directory base, excluding .php
				name is alphanumeric
		
			special form !current
				loads template at current route path
			special form !children
				applies all following templates as subtemplates to previous template
			special form prefix @
				Used to represent an alias, though not parsed in this function.  Aliases are defined in $this->aliases
		
		@return	
		
		-	array with each element being a template
		-	an array of structured template arrays:
		@verbatim
array(
	array('templateFile','templateName',$subTemplates),
	array('templateFile2','templateName2',$subTemplates2),
)
		@endverbatim
	*/
	static function parseTemplateString($templateString){
		#single line, break into multiline
		if(!preg_match('/\n/',$templateString)){
			#ensure start template is at level 1
			$templateString = preg_replace('@^ *([^\s,])@',"\t".'$1',$templateString);
			#converge seperation
			$templateString = preg_replace('@,@',"\t",$templateString);
			#conform newline spacing
			$templateString = preg_replace('@\t+@',"\n".'$0',$templateString);
		}
		#remove start space and newlines
		$templateString = preg_replace('@^[ \n]*@','',$templateString);

		#remove excessive tabbing (items are now separated by newlines on all cases)
		preg_match('@^\t+@',$templateString,$match);
		if($match){
			$excessiveTabs =  str_repeat('\t',strlen($match[0]));
			$templateString = preg_replace('@(^|[^\t])'.$excessiveTabs.'@','$1',$templateString);
		}

		$templates = explode("\n",$templateString);
		$array = self::generateTemplatesArray($templates);
		
		#replace !current with current control to template location
		$array = Arrays::replaceAll('!current',implode('/',Route::$parsedUrlTokens),$array);
		
		#set !children to work with parseAliases
		$array = Arrays::replaceAllParents('!children','!children',$array,2);
		return $array;
	}
	///internal use for parseTemplateString
	static function generateTemplatesArray($templates,$depth=0,&$position=0){
		$templatesArray = array();
		$totalTemplates = count($templates);
		for(; $position < $totalTemplates; $position++){
			$template = $templates[$position];
			preg_match('@(\t*)([^\t]+$)@',$template,$match);
			$templateDepth = strlen($match[1]);
			$templateId = $match[2];
			
			#indicates sub templates, so add sub templates
			if($templateDepth > $depth){
				#add subtemplates to previous template
				$templatesArray[count($templatesArray) - 1][2] = self::generateTemplatesArray($templates,$templateDepth,$position);
			}
			#in too deep, return to parent
			elseif($depth > $templateDepth){
				return $templatesArray;
			}
			#in the same depth, add to array
			else{
				list($templateFile,$templateName) = explode(':',$templateId);
				$templatesArray[] = array($templateFile,$templateName);
			}
		}
		return $templatesArray;
	}
	///replaces aliases identified with @ALIAS_NAME in template array and defined in the alias file
	protected function parseAliases(&$array){
		foreach($array as &$item){
			//is aliased, find replacement
			if($item[0][0] == '@'){
				$item = $this->replaceTemplateAlias(substr($item[0],1),$item[2]);
			}
			if($item[2][0] == '@'){
				$item = $this->replaceTemplateAlias(substr($item[0],1));
			}
			if(is_array($item[2])){
				$item[2] = $this->parseAliases($item[2]);
			}
		}
		unset($item);
		
		return $array;
	}
	protected function replaceTemplateAlias($alias,$children=null){
		$tree = $this->aliases[$alias];
		if(!$tree){
			Debug::toss('Could not find alias: '.$alias);
		}
		if(!is_array($tree)){
			$tree = Arrays::at(self::parseTemplateString($tree),0);
		}
		
		if($children){
			$tree = Arrays::replaceAll('!children',$children,$tree);
		}
		
		return $tree;
	}
	
//+	css & js resource handling {
	
	///page css
	public $css = array();
	///page css put at the end after self::$css
	public $lastCss = array();
	///page js found at top of page
	public $topJs = array();
	///page js found at bottom of page
	public $bottomJs = array();
	///page js put at the end after self::$bottomJs
	public $lastJs = array();
	///determines whether addTag prepends or appends.  Note, mutli file addTag calls will be reverse sorted.
	public $tagAddOrder = '+';
	///used internally.
	/**
	@param	type	indicates whether tag is css, lastCss, js, or lastJs.  Prefix with "-" or "+" to temporarilty change the tag addition order
	@param args	additional args taken as files.  Each file in the passed parameters has the following special syntax:
		-starts with http(s): no modding done
		-starts with "/": no modding done
		-starts with "inline:": file taken to be inline css or js.  Code is wrapped in tags before output.
		-starts with none of the above: file put in path /instanceToken/type/file; ex: "/public/css/main.css"
		-if array, consider first element the tag naming key and the second element the file; Used for ensuring only one tag item of a key, regardless of file, is included.
	@note	if you don't want to have some tag be unique (ie, you want to include the same js multiple times), don't use this function; instead, just set tag variable (like $bottomJs) directly
	*/
	protected function addTag($type){
		if(in_array(substr($type,0,1),array('-','+'))){
			$originalTagAddOrder = $this->tagAddOrder;
			$this->tagAddOrder = substr($type,0,1);
			$type = substr($type,1);
		}
		if(in_array($type,array('css','lastCss'))){
			$uniqueIn = array('css','lastCss');
			$folder = 'css';
		}else{
			$uniqueIn = array('topJs','bottomJs','lastJs');
			$folder = 'js';
		}
		$files = func_get_args();
		array_shift($files);
		if($files){
			if($this->tagAddOrder == '-'){
				krsort($files);
			}
			$typeArray =& $this->$type;
			foreach($files as $file){
				if(is_array($file)){
					$key = $file[0];
					$file = $file[1];
				}
				
				if(preg_match('@^inline:@',$file)){
					$typeArray[] = $file;
				}
				//user is adding it, so assume css is at instance unless it starts with http or /
				else{
					if(substr($file,0,1) != '/' && !preg_match('@^http(s)?:@',$file)){
						$file = '/'.Config::$x['urlProjectFileToken'].'/'.$folder.'/'.$file;
					}
					foreach($uniqueIn as $unique){
						Arrays::remove($this->$unique,$file);
					}
					if(!$key){
						if($this->tagAddOrder == '-'){
							array_unshift($typeArray,$file);
						}else{
							$typeArray[] = $file;
						}
					}else{
						$typeArray[$key] = $file;
						unset($key);
					}
				}
			}
		}
		if($originalTagAddOrder){
			$this->tagAddOrder = $originalTagAddOrder;
		}
	}
	///Adds to the $css array and overrides duplicate elements.  Each argument considered css file.  See self::addTag for args details
	protected function addCss(){
		$args =	func_get_args();
		array_unshift($args,'css');
		call_user_func_array(array($this,'addTag'),$args);
	}
	///Adds css that will come after the regularly added css
	protected function addLastCss(){
		$args =	func_get_args();
		array_unshift($args,'lastCss');
		call_user_func_array(array($this,'addTag'),$args);
	}
	///Adds to the $js array and overrides duplicate elements.  Each argument considered js file.  See self::addTag for args details
	protected function addTopJs(){
		$args =	func_get_args();
		array_unshift($args,'topJs');
		call_user_func_array(array($this,'addTag'),$args);
	}
	///Adds to the $bottomJs array and overrides duplicate elements.  Each argument considered js file.  See self::addTag for args details
	/**
	The point of putting JS at the bottom is that often the js doesn't immediately have an effect on the page display, yet, if put at the top, the browser will wait until the javascript is loading to load the rest of the page.  This has to be balanced with the fact that some base javascript at the top of the page is needed for inline javascript that uses that base.  So, really, it comes done to preference.
	*/
	protected function addBottomJs(){
		$args =	func_get_args();
		array_unshift($args,'bottomJs');
		call_user_func_array(array($this,'addTag'),$args);
	}
	///Adds js that will come after the regularly added js
	protected function addLastJs(){
		$args =	func_get_args();
		array_unshift($args,'lastJs');
		call_user_func_array(array($this,'addTag'),$args);
	}
	
	///used by getCss to turn css array into html string
	static function getCssString($array,$urlQuery=null){
		foreach($array as $file){
			if(preg_match('@^inline:@',$file)){
				$css[] = '<style type="text/css">'.substr($file,7).'</style>';
			}else{
				if($urlQuery){
					$file = Http::appendsUrl($urlQuery,$file);
				}
				$css[] = '<link rel="stylesheet" type="text/css" href="'.$file.'"/>';
			}
		}
		return implode("\n",$css);
	}
	///Outputs css style tags with self::$css
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	protected function getCss($urlQuery=null){
		if($this->css){
			$css = self::getCssString($this->css,$urlQuery);
		}
		if($this->lastCss){
			$css .= self::getCssString($this->lastCss,$urlQuery);
		}
		return $css;
	}
	///used by getCss to turn css array into html string
	static function getJsString($array,$urlQuery=null){
		foreach($array as $file){
			//Intended to be used for plain script
			if(preg_match('@^inline:@',$file)){
				$js[] = '<script type="text/javascript">'.substr($file,7).'</script>';
			}else{
				if($urlQuery){
					$file = Http::appendsUrl($urlQuery,$file);
				}
				$js[] = '<script type="text/javascript" src="'.$file.'"></script>';
			}
		}
		return implode("\n",$js);
	}
	
	///	Outputs js script tags with self::$topJs
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	protected function getTopJs($urlQuery=null){
		if($this->topJs){
			$js = self::getJsString($this->topJs,$urlQuery);
		}
		return $js;
	}
	
	///	Outputs js script tags with self::$bottomJs and self::$lastJs
	/**
	@param	urlQuery	array	key=value array to add to the url query part; potentially used to force browser to refresh cached resources
	*/
	protected function getBottomJs($urlQuery=null){
		if($this->bottomJs){
			$js = self::getJsString($this->bottomJs,$urlQuery);
		}
		if($this->lastJs){
			$js .= self::getJsString($this->lastJs,$urlQuery);
		}
		return $js;
	}
	
	protected function loadSystemResources(){
		$tagAddOrder = $this->tagAddOrder;
		$this->tagAddOrder = '-';
		#general system js
		$js = array('http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js');
		foreach(array('tools.js','date.js','debug.js','ui.js') as $v){
			$js[] = '/'.Config::$x['urlSystemFileToken'].'/js/'.$v;
		}
		call_user_func_array(array($this,'addTopJs'),$js);
		$this->addCss('/'.Config::$x['urlSystemFileToken'].'/css/base.css');
		$this->tagAddOrder = $tagAddOrder;
	}
	
//+	}
	
	///Accumulated page json
	public $json = null;
	///prints the self::$json into the tp.json object.  Requires the previous declaration of tp js object on the page
	protected function getJson(){
		echo '<script type="text/javascript">tp.json = '.json_encode($this->json).';</script>';
	}
	///end script with xml
	static function endXml($content){
		Hook::run('preHTTPMessageBody');
		header('Content-type: text/xml; charset=utf-8');
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo $content; exit;
	}
	///end script with json
	static function endJson($content,$encode=true){
		Hook::run('preHTTPMessageBody');
		header('Content-type: application/json');
		if($encode){
			echo json_encode($content);
		}else{
			echo $content;
		}
		exit;
	}
	
	//it appears the browser parses once, then operating system, leading to the need to double escape the file name.  Use double quotes to encapsulate name
	static function escapeFilename($name){
		return Tool::slashEscape(Tool::slashEscape($name));
	}
	///send an actual file on the system via http protocol
	static function sendFile($path,$saveAs=null,$exit=true){
		//Might potentially remove ".." from path, but it has already been removed by the time the request gets here by server or browser.  Still removing for precaution
		$path = Files::removeRelative($path);
		if(is_file($path)){
			$mime = Files::mime($path);
			
			header('Content-Type: '.$mime);
			if($saveAs){
				header('Content-Description: File Transfer');
				if(strlen($saveAs) > 1){
					$fileName = $saveAs;
				}else{
					$fileName = array_pop(explode('/',$path));
				}
				header('Content-Disposition: attachment; filename="'.self::escapeFilename($fileName).'"');
			}
			
			echo file_get_contents($path);
		}elseif(Config::$x['resourceNotFound']){
			Config::loadUserFiles(Config::$x['resourceNotFound'],'control');
		}else{
			Debug::toss('Request handler encountered unresolvable file.  Searched at '.$path);
		}
		if($exit){
			exit;
		}
	}
	//simple standard logic to generate page title
	static function pageTitle(){
		return Tool::capitalize(Tool::camelToSeparater(implode(' ',Route::$parsedUrlTokens),' '));
	}
	static function contentHeaders($mime=null,$filename=null){
		if($mime){
			header('Content-Type: '.$mime);
		}
		if($filename){
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename="'.self::escapeFilename($filename).'"');
		}
	}
}