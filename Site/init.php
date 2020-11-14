<?php
namespace Fuzic\Site;

use Fuzic\Models;


require_once dirname(dirname(__FILE__)).'/init.php';

//autoload our controllers
spl_autoload_register(auto_loader('Site/Controller', 'class'));

//initialize session
$session = Models\UserSession::acquire();
if (isset($_POST['login_attempt'])) {
    try {
        $user = $session->upgrade();
    } catch (Models\UserException $e) {
        $user = new Models\User($session->get('user'));
    }
} else {
    $user = new Models\User($session->get('user'));
}

header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['logout'])) {
    Models\User::logout();
}

//timezones
$__timezone = 'Europe/London';
if (isset($_COOKIE['timezone'])) {
    $timezones = unserialize(file_get_contents(ROOT.'/Site/assets/timezones.json'));
    if (isset($timezones[$_COOKIE['timezone']])) {
        $__timezone = $_COOKIE['timezone'];
    }
}
date_default_timezone_set($__timezone);