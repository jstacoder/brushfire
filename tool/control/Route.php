<?
namespace control;
///Used to handle requests by determining path, then determining controls
/**
Route Rules Logic
	Route will look with increasing specificity down the tokenised file path in the "control" folder for "routes.php" files
	if a matching rule is found, the Route rules loop starts over with the new path

Controls Calling Logic:
	Route will look with increasing specificity down the tokenised file path in the "control" folder for either $previousPath.$currentToken.'.php', or $previousPath.'control.php'
		bob/bill/sue bob:
			include: control/bob.php or control/control.php
			include: control/bob/bill.php or control/bob/control.php
			include: control/bill/sue.php or control/bill/sue/control.php

File Routing
	If, for some reason, Route is given a request that has a urlProjectFileToken or a systemPublicFolder prefix, Route will send that file after determining the path through the Route Rules Logic

@note	if you get confused about what is going on with the rules, you can print out both self::$matchedRules and self::$ruleSets at just about any time
*/
class Route{
	static $stopRouting;///<stops more rules from being called; use within rule file
	static $urlTokens = array();///<an array of url path parts; rules can change this array
	static $realUrlTokens = array();///<the original array of url path parts
	static $parsedUrlTokens = array();///<used internally
	static $unparsedUrlTokens = array();///<used internally
	static $matchedRules;///<list of rules that were matched
	static $urlBase;///<the string, untokenised, path of the url.  Use $_SERVER to get actual url
	static $currentToken;///<used internally; serves as the item compared on token compared rules
	///parses url, routes it, then calls off all the control until no more or told to stop
	static function handle($uri){
		self::parseRequest($uri);
		
		//url corresponds to public file directory, provide file
		if(self::$urlTokens[0] == \Config::$x['urlProjectFileToken']){
			self::sendFile(\Config::$x['instancePublicFolder']);
		}elseif(self::$urlTokens[0] == \Config::$x['urlSystemFileToken']){
			self::sendFile(\Config::$x['systemPublicFolder']);
		}
		
		self::routeRequest();
		\Hook::run('prePage');
	
//+	load controls and section page{
	
		global $control;
		$control = \Control::init();//we are now in the realm of dynamic pages
		
		//after this following line, self::$urlTokens has no more influence on routing.  Modify self::$unparsedUrlTokens if you want modify control flow
		self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);
		
		//page utility levels
		$pageUtillityLevel = self::getPageLevel(\Config::$x['projectFolder'].'tool/section/');
		$pageDisplayUtillityLevel = self::getPageLevel(\Config::$x['projectFolder'].'view/tool/section/');
		
		//get the section and page control
		while(self::$unparsedUrlTokens){
			self::$currentToken = array_shift(self::$unparsedUrlTokens);
			if(self::$currentToken){
				self::$parsedUrlTokens[] = self::$currentToken;
			}
//+		include section page, if at appropriate level {
			$level = count(self::$parsedUrlTokens);
			if($pageUtillityLevel && $level == $pageUtillityLevel){
				\Files::incOnce(\Config::$x['projectFolder'].'tool/section/'.implode('/',self::$parsedUrlTokens).'.php');
			}
			if($pageDisplayUtillityLevel && $level == $pageDisplayUtillityLevel){
				\Files::incOnce(\Config::$x['projectFolder'].'view/tool/section/'.implode('/',self::$parsedUrlTokens).'.php');
			}
//+		}
			
			//++ load the control {
			$path = \Config::$x['controlFolder'].implode('/',self::$parsedUrlTokens);
			//if named file, load, otherwise load generic control in directory
			$file = !is_dir($path) ? $path.'.php' : $path.'/control.php';
			$loaded = \Files::inc($file,['control'],self::$regexMatch);
			//++ }
			
			//not loaded and was last token, page not found
			if($loaded === false && !self::$unparsedUrlTokens){
				if(\Config::$x['pageNotFound']){
					\Config::loadUserFiles(\Config::$x['pageNotFound'],'control',array('control'));
					exit;
				}else{
					Debug::toss('Request handler encountered unresolvable token at control level.'."\nCurrent token: ".self::$currentToken."\nTokens parsed".print_r(self::$parsedUrlTokens,true));
				}
			}
		}
		
//+	}
	}
	///get the first (most specific to page) section-page tool
	/**@return tokens at which tool was found*/
	private static function getPageLevel($base){
		$tokens = self::$urlTokens;
		while($tokens){
			if(is_file($base.implode('/',$tokens).'.php')){
				return count($tokens);
			}
			array_pop($tokens);
		}
	}
	///internal use. initial breaking apart of url
	private static function parseRequest($uri){
		self::$realUrlTokens = explode('?',$uri,2);
		self::tokenise(self::$realUrlTokens[0]);
		
		//urldecode tokens.  Note, this can make some things relying on domain path info for file path info insecure
		foreach(self::$urlTokens as &$token){
			$token = urldecode($token);
		}
		unset($token);
		
		//Potentially, the urlTokens will change according to routes, but the real ones may be referenced
		self::$realUrlTokens = self::$urlTokens;
	}
	/// internal use. tokenises url
	/** splits url path on "/"
	@param	urlDir	str	path part of url string
	*/
	static $urlCaselessBase;///<urlBase, but cases removed
	private static function tokenise($urlDir){
		self::$urlTokens = \Tool::explode('/',$urlDir);
		self::$urlBase = $urlDir;
		self::$urlCaselessBase = strtolower($urlDir);
	}
	static $regexMatch=[];
	///internal use. Parses all current files and rules
	/** adds file and rules to ruleSets and parses all active rules in current file and former files
	@param	file	str	file location string
	*/
	private static function matchRules($path,&$rules){
		foreach($rules as $ruleKey=>&$rule){
			unset($matched);
			if(!isset($rule['flags'])){
				$flags = $rule[2] ? explode(',',$rule[2]) : array();
				$rule['flags'] = array_fill_keys(array_values($flags),true);
			
				//parse flags for determining match string
				if($rule['flags']['regex']){
					$rule['match'] = \Tool::pregDelimit($rule[0]);
					if($rule['flags']['caseless']){
						$rule['match'] .= 'i';
					}
					
				}else{
					if($rule['flags']['caseless']){
						$rule['match'] = strtolower($rule[0]);
					}else{
						$rule['match'] = $rule[0];
					}
				}
			}
			
			if($rule['flags']['caseless']){
				$subject = self::$urlCaselessBase;
			}else{
				$subject = self::$urlBase;
			}
			
			//test match
			if($rule['flags']['regex']){
				if(preg_match($rule['match'],$subject,self::$regexMatch)){
					$matched = true;
				}
			}else{
				if($rule['match'] == $subject){
					$matched = true;
				}
			}
			
			if($matched){
				self::$matchedRules[] = $rule;
				
				//++ apply replacement logic {
				if($rule['regex']){
					$replacement = preg_replace($rule['match'],$rule[1],self::$urlBase);
				}else{
					$replacement = $rule[1];
				}
			
				//remake url with replacement
				self::tokenise($replacement);
				self::$parsedUrlTokens = [];
				self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);
				//++ }
				
				//++ apply parse flag {
				if($rule['flags']['once']){
					unset($rules[$ruleKey]);
				}elseif($rule['flags']['file:last']){
					unset(self::$ruleSets[$path]);
				}elseif($rule['flags']['loop:last']){
					self::$unparsedUrlTokens = [];
				}
				//++ }
				
				return true;
			}
		} unset($rule);

		return false;
	}
	
	static $ruleSets;///<files containing rules that have been included
	
	///internal use. Gets files and then applies rules for routing
	private static function routeRequest(){
		self::$unparsedUrlTokens = array_merge([''],self::$urlTokens);
		
		while(self::$unparsedUrlTokens && !self::$stopRouting){
			self::$currentToken = array_shift(self::$unparsedUrlTokens);
			if(self::$currentToken){
				self::$parsedUrlTokens[] = self::$currentToken;
			}
			
			$path = \Config::$x['controlFolder'].implode('/',self::$parsedUrlTokens);
			if(!isset(self::$ruleSets[$path])){
				self::$ruleSets[$path] = (array)\Files::inc($path.'/routes.php',null,null,['rules'])['rules'];
			}
			if(!self::$ruleSets[$path]){
				continue;
			}
			//note, on match, matehRules resets unparsedTokens (having the effect of loopiing matchRules over again)
			self::matchRules($path,self::$ruleSets[$path]);
		}
		
		self::$parsedUrlTokens = [];
	}
	///internal use. Gets a file based on next token in the unparsedUrlTokens variable
	private static function getTokenFile($defaultName,$globalize=null,$extract=null){
		$path = \Config::$x['controlFolder'].implode('/',self::$parsedUrlTokens);
		//if path not directory, possibly is file
		if(!is_dir($path)){
			$file = $path.'.php';
		}else{
			$file = $path.'/'.$defaultName.'.php';
		}
		return \Files::inc($file,$globalize,null,$extract);
	}
	///internal use. attempts to find non php file and send it to the browser
	private static function sendFile($base){
		array_shift(self::$urlTokens);
		$filePath = escapeshellcmd(implode('/',self::$urlTokens));
		if($filePath == 'index.php'){
			\Config::loadUserFiles(\Config::$x['pageNotFound'],'control',array('page'));
		}
		$path = $base.$filePath;
		if(\Config::$x['downloadParamIndicator']){
			$saveAs = $_GET[\Config::$x['downloadParamIndicator']] ? $_GET[\Config::$x['downloadParamIndicator']] : $_POST[\Config::$x['downloadParamIndicator']];
		}
		\View::sendFile($path,$saveAs);
	}
	static function currentPath(){
		return '/'.implode('/',self::$parsedUrlTokens).'/';
	}
}
