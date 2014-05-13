<?
/// logic unrelated to a specific request
/** @file */

#Tool, used by config
require_once $config['systemFolder'].'utilities/Tool.php';

#used by autoloader
require_once $config['systemFolder'].'utilities/Arrays.php';
require_once $config['systemFolder'].'utilities/Hook.php';
require_once $config['systemFolder'].'utilities/CommonTraits.php';

#Config setting
require_once $config['systemFolder'].'utilities/Config.php';
Config::$x = $config;
Config::get();
date_default_timezone_set(Config::$x['timezone']);

#Autoloader
require_once $config['systemFolder'].'utilities/Autoload.php';
$autoload = Autoload::init(null,Config::$x['autoloadIncludes']);
spl_autoload_register(array($autoload,'auto'));

set_error_handler(Config::$x['errorHandler'],Config::$x['errorsHandled']);
set_exception_handler(Config::$x['exceptionHandler']);

Config::loadUserFiles(Config::$x['preRoute']);

#pre session request handling; for file serving and such.
require_once $config['systemFolder'].'utilities/Route.php';
Route::handle($_SERVER['REQUEST_URI']);