<?
/// logic unrelated to a specific request
/** @file */

#Tool, used by config
require_once $config['systemFolder'].'tool/Tool.php';

#used by autoloader
require_once $config['systemFolder'].'tool/Arrays.php';
require_once $config['systemFolder'].'tool/Hook.php';
require_once $config['systemFolder'].'tool/CommonTraits.php';

#Config setting
require_once $config['systemFolder'].'tool/Config.php';
Config::init($config);
date_default_timezone_set(Config::$x['timezone']);

#Autoloader
require_once $config['systemFolder'].'tool/Autoload.php';
$autoload = Autoload::init(null,Config::$x['autoloadIncludes']);
spl_autoload_register(array($autoload,'auto'));

set_error_handler(Config::$x['errorHandler'],Config::$x['errorsHandled']);
set_exception_handler(Config::$x['exceptionHandler']);

Config::loadUserFiles(Config::$x['preRoute']);

#pre session request handling; for file serving and such.
require_once $config['systemFolder'].'tool/control/Route.php';
\control\Route::handle($_SERVER['REQUEST_URI']);