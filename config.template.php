<?
//recommendation: put your credentials info outside repo, and include file some where here

$base = realpath(dirname(__FILE__));
require $base.'/../preload.php';

///for use by system in things such as combined log
$config['projectName'] = 'Hot tub';

//+	Path configs{
//where the framework lies on your system
$config['systemFolder'] = '/www/brushfire/v9/versioned/';
//system public web resources
$config['systemPublicFolder'] = $config['systemFolder'].'public/';
//wherein lies project non-deployment specfic code
$config['projectFolder'] = $base.'/';
//project public web resources
$config['projectPublicFolder'] = $config['projectFolder'].'public/';

///path to view folder
$config['viewFolder'] = $config['projectFolder'].'view/';
///path to template folder (in case you want to use some common templates folder)
$config['templateFolder'] = $config['viewFolder'].'templates/';
///path to storage folder
$config['storageFolder'] = $config['projectFolder'].'storage/';
///path to the control files
$config['controlFolder'] = $config['projectFolder'].'control/';

//+	}

//+	Log configs {
///log location
/** path is relative to project folder*/
$config['logLocation'] = 'log';
///Max log size.  If you want only one error to show at a time, set this to 0
$config['maxLogSize'] = '0mb';
//+	}


//+	Route config {
///what file to call when page was not found; relative to instance/control/
$config['pageNotFound'] = '404page.php';
///what file to call when resource was not found; relative to instance/control/
$config['resourceNotFound'] = '404page.php';
///file to use for directory index page
/**when the Route is at the end of a path and hasn't called a control with a page name, it will see if this file exists within the last directory and try to load it.  If this config equates to false, the Route will not use any index page*/
$config['useIndex'] = 'index.php';
///the starting url path token that indicates that the system should look in the project public directory
$config['urlProjectFileToken'] = 'public';
///the starting url path token that indicates that the system should look in the system public directory
$config['urlSystemFileToken'] = 'brushfire';
///A parameter in the post or get that indicates a file is supposed to be downloaded instead of served.  Works for non-parse public directory files.  Additionally, serves to name the file if the param value is more than one character.
$config['downloadParamIndicator'] = 'download';
//+	}

//+	Error config {
///custom error handler.  Defaults to system error handler.
$config['errorHandler'] = 'Debug::handleError';
///custom error handler.  Defaults to system error handler.
$config['exceptionHandler'] = 'Debug::handleException';
///type of errors handled
$config['errorsHandled'] = E_ALL & ~ E_NOTICE & ~ E_STRICT;
///error page relative path to instance/.  Just make sure the error page doesn't have any errors
$config['errorPage'] = '';
///Messages to display in liue of an error page.  Can be set to single string or an array of strings.  If an array, random is chosen.
$config['errorMessage'] = array(
		"System error.  Contact Admin.  Error details follow:",
	);
///Assumes system files won't error and thus excludes them from debug report
$config['debugAssumePerfection'] = false;
///Determines the level of detail provided in the error message, 0-3
$config['errorDetail'] = 2;
///Display errors
$config['displayErrors'] = true;
///the view page used by the system to display user level errors on
$config['errorPage'] = 'view/templates/error.php';
///whether to throw error or exit on error
$config['throwErrors'] = 'view/templates/error.php';

//+	}
//+	Session config {
///date in the past after which inactive sessions should be considered expired
$config['sessionExpiry'] = '-1 day';
///folder to keep the file sessions if file sessions are used.  Php must be able to write to the folder.
$config['sessionFolder'] = $config['storageFolder'].'sessions/';
///determines whether to use the database for session data
$config['sessionUseDb'] = true;
///determines which table in the database to use for session data
$config['sessionDbTable'] = 'session';
-///the time at which the cookie is set to expire.
-$config['sessionCookieExpiry'] = '+1 year';
-///cookie expiry refresh probability; the denominator that an existing session cookie will be updated with the current sessionCookieExpiry on page load.  0, false, null = don't refresh
-$config['sessionCookieExpiryRefresh'] = 100;
//probability is probability/divisor
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
//+	}

//+	Encryption config {
///the cipher to use for the framework encyption class
$config['cryptCipher'] = MCRYPT_RIJNDAEL_128;
///the cipher mode to use for the framework encyption class
$config['cryptMode'] = MCRYPT_MODE_ECB;
///the cipher key to use for the framework encyption class.  Clearly, the safest thing would be to keep it as the default!
$config['cryptKey'] = $config['projectName'];
//+	}

///a list of directories to search recursively.  See Doc:Autoload Includes
$config['autoloadIncludes'] = array(
		'default'=>array(
			$config['systemFolder'].'utilities/',
			array($config['projectFolder'].'utilities/',array('moveDown'=>true,'stopPath'=>$config['projectFolder'].'utilities/section/')),
			$config['systemFolder'].'view/utilities/',
			array($config['projectFolder'].'view/utilities/',array('moveDown'=>true,'stopPath'=>$config['projectFolder'].'view/utilities/section/')),
			'/www/composer/',
		),
	);
///tells autoloader to attempt to autoload class from the utility/section folder in broadening scope, starting with the last urlToken and end at utility/section
$config['autoloadSection'] = true;

//+	Display related config {
///used to make the View::show function call other functions before parsing templates.  No arguments passed.
/**
@note, you can modify the View::show() arguments by modifying View::$showArgs;
*/
///used for @name like shortcuts in View::get template array.  See example in system/view/aliases.php
$config['aliasesFiles'] = $config['projectFolder'].'view/aliases.php';
//+	}

//+	CRUD related config {
//what to call when CRUD model encounters a bad id
$config['CRUDbadIdCallback'] = 'badId';
//+	}

//+	Misc config {
///email unique identifier; 
/**when sending an email, you have to generate a message id.  To prevent collisions, this id will be used in addition to some random string*/
$config['emailUniqueId'] = $config['projectName'].'-mail';

///cookie default options for use by Cookie class
$config['cookieDefaultOptions'] = array(
		'expire' => 0,
		'path'	=> '/',
		'domain' => null,
		'secure' => null,
		'httpsonly'=> null
	);
	
///time zone (db, internal functions)
$config['timezone'] = 'UTC';
//input taken from and output given to user.
$config['inOutTimezone'] = 'UTC';


///whether to parse input using php '[]' special syntax
$config['pageInPHPStyle'] = true;
///user not authorized page
$config['notAuthorizedPage'] = 'standardPage,,,notAuthorized';
///for finding first IP outside network on HTTP_X_FORWARDED_FOR
$config['loadBalancerIps'] = array();

$config['startTime'] = microtime(true);
//+	}
