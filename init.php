<?php
/**
 * Sets up the application environment
 *
 * @package Fuzic
 */
namespace Fuzic;

use Fuzic\Lib;

//define('TEST_DB', 'fuzictest');

/**
 * Path to which all script files are relative
 */
define('ROOT', dirname(__FILE__));

/**
 * Log files go here
 */
define('LOG_DIR', ROOT.'/logs');

/**
 * URL to which all site URLs are relative
 */
define('WEBROOT', '');

//include config variables and misc functions
include ROOT.'/config.php';
require_once ROOT.'/Lib/misc.php';

//autoloading!
spl_autoload_register(auto_loader('Model', 'class'));
spl_autoload_register(auto_loader('Lib', 'class'));
spl_autoload_register(auto_loader('Lib', 'interface'));

date_default_timezone_set('Europe/London');

//include misc functions

//include composer-installed libraries
require_once ROOT.'/vendor/autoload.php';

//set up database connection
$db_name = defined('TEST_DB') ? TEST_DB : Config::DB_NAME;
$db = new Lib\mysqldb(Config::DB_PLACE, Config::DB_USER, Config::DB_PASSWD, $db_name);

//dbschema is a separate connection for abstract stuff
$dbschema = new Lib\mysqldb(Config::DB_PLACE, Config::DB_USER, Config::DB_PASSWD, 'information_schema');

//set up cache engine
$cache = new Lib\Cache('fuzic');