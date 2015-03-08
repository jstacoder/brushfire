<?
$loader = new stdClass;
$loader->includer = debug_backtrace()[0]['file'];
$loader->root = realpath(dirname($loader->includer)).'/';

if(!$_ENV['projectFolder']){///<to avoid case in which another loader is already loaded
	$_ENV['projectFolder'] = $loader->root;
	$_ENV['systemFolder'] = realpath(dirname(__FILE__)).'/';
	require $_ENV['systemFolder'].'config.php';
	$_ENV['errorStackExclude'] = ['@^system:@'];
	unset($_ENV['logFolder']);
	$_ENV['logFile'] = $_ENV['storageFolder'].'log';
	$_ENV['inScript'] = true;
	unset($_ENV['errorPage']);
	require $_ENV['systemFolder'].'toolLoader.php';
}

FileContext::set('loader',$loader,$loader->includer);
