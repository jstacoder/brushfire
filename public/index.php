<?
/**
command line & web logic.  Include config.  Include preloader.

@note command line reference
	p,path	the path, normally provided as a HTTP GET path
	h.host	the host
	m,mode	mode.  Otherwise passed by web server as config at server level.  Ex: apache SetEnv bob bob
	c,config	config file location.  Defaults to web default
	cookie	Passed as query string into $_COOKIE
	scriptGetsStdin	bool, whether to expect Stdin.  Only use when Stdin present or script will hang.
If in command line, Config "inScript" is set to true
	
*/
//if run by command line, argc (count) should always be >= 1, otherwise, if not run by command line, should not exist.
if($argc){
	$config['inScript'] = true;
	
	$args = getopt('p:q:m:c:h:',array('path:','query:','mode:','config:','cookie:','host:'));
	
	$config['envMode'] = $args['mode'] ? $args['mode'] : $args['m'];
	
	$_SERVER['QUERY_STRING'] = $args['query'] ? $args['query'] : $args['q'];
	$_SERVER['HTTP_HOST'] = $args['host'] ? $args['host'] : $args['h'];
	parse_str($_SERVER['QUERY_STRING'],$_GET);
	
	if($args['cookie']){
		parse_str($args['cookie'],$_COOKIE);
	}
	
	//system depends completely on request uri for path request handling
	$_SERVER['REQUEST_URI'] = $args['path'] ? $args['path'] : $args['p'];
	
	$configFile = $args['config'] ? $args['config'] : $args['c'];
	require_once ($configFile ? $configFile : __DIR__.'/../config.php');
	
}else{
	$config['envMode'] = getenv('mode');
	require_once __DIR__.'/../config.php';
}

require_once $config['systemLocation'].'/system/loader.php';
