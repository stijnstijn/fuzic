<?php
/**
 * Routes page requests for the site
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Models;


chdir(dirname(__FILE__));

require_once 'init.php';
$tpl = new Templater($user, $db, $cache);
header('Content-Type: text/html; charset=utf-8');

if (defined('MAINTENANCE')) {
    echo $tpl->fetch('maintenance.tpl');
    exit;
}

$tpl->add_CSS('fuzic.css');
$tpl->add_JS('waypoints.min.js');
$tpl->add_JS('jQuery.formatDateTime.min.js');
$tpl->add_JS('jQuery.cookie.js');
$tpl->add_JS('//code.highcharts.com/highcharts.js');
$tpl->add_JS('fuzic.js');

$tpl->assign('__timezones', unserialize(file_get_contents(dirname(__FILE__).'/assets/timezones.json')));
$tpl->assign('__current_timezone', date_default_timezone_get());

$router = new \Klein\Klein();

//determine active game
$__games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);
preg_match('/([a-z]+)\.fuzic\.nl/siU', $_SERVER['SERVER_NAME'], $game);
if (isset($game[1]) && isset($__games[$game[1]])) {
    define('ACTIVE_GAME', $game[1]);
} elseif (!isset($game[1]) || in_array($game[1], array('alpha', 'www'))) {
    define('ACTIVE_GAME', 'sc2');
} else {
    $tpl->assign('__game', $__games['sc2']);
    header('HTTP/1.0 404 Not Found', true, 404);
    echo $tpl->fetch('404.tpl');
    exit;
}

$tpl->assign('__game', $__games[ACTIVE_GAME]);
$tpl->assign('__game_ID', ACTIVE_GAME);

//Routing: index
$router->respond('/[,|:dummy]', function ($request) {
    global $db, $cache, $tpl;
    $tpl->add_breadcrumb('Fuzic', '/');
    include 'frontpage.php';
});

//Routing: streams
$router->respond('/streams/[:streamID]/chart/[a:type]/[a:data]/', function ($request) use ($tpl, $db) {
    new StreamController(array('mode' => 'chart', 'id' => $request->streamID, 'action' => 'ajax-chart', 'type' => $request->type, 'data' => $request->data), $tpl, $db);
    exit;
});

$router->respond('/streams/[players|casters:subset]?/', function ($request) use ($tpl, $db) {
    new StreamController(array('mode' => 'show', 'subset' => $request->subset), $tpl, $db);
    exit;
});

$router->respond('/streams/[:streamID]/', function ($request) use ($tpl, $db) {
    new StreamController(array('mode' => 'show', 'id' => $request->streamID), $tpl, $db);
    exit;
});

//Routing: sessions
$router->respond('/sessions/[i:sessionID]/', function ($request) use ($tpl, $db) {
    new SessionController(array('mode' => 'show', 'id' => $request->sessionID), $tpl, $db);
    exit;
});

//Routing: events
$router->respond('/events/compare/[i:event1]/[i:event2]/', function ($request) use ($tpl, $db) {
    new EventController(array('mode' => 'compare', 'event1' => $request->event1, 'event2' => $request->event2), $tpl, $db);
    exit;
});

$router->respond('/events/?[i:eventID]?-?[*]?/export/', function ($request) use ($tpl, $db) {
    new EventController(array('mode' => 'graph', 'id' => $request->eventID, 'ajax' => !empty($request->ajax)), $tpl, $db);
    exit;
});

$router->respond('/events/?[i:eventID]?-?[*]?/[s:ajax]?/?', function ($request) use ($tpl, $db) {
    new EventController(array('mode' => 'show', 'id' => $request->eventID, 'ajax' => !empty($request->ajax)), $tpl, $db);
    exit;
});

//Routing: franchises
$router->respond('/franchises/', function ($request) use ($tpl, $db) {
    new FranchiseController([], $tpl, $db);
    exit;
});
$router->respond('/franchises/[:franchiseID]/', function ($request) use ($tpl, $db) {
    new EventController(array('franchise' => $request->franchiseID), $tpl, $db);
    exit;
});


//Routing: teams
$router->respond('/teams/[:teamID]?/?', function ($request) use ($tpl, $db) {
    new TeamController(array('mode' => 'show', 'id' => $request->teamID), $tpl, $db);
    exit;
});


//Routing: rankings
$router->respond('/rankings/[a:which]?/[i:year]?/[:type]?/[i:period]?/[players|casters:subset]?/?', function ($request) use($tpl, $db) {
    $type = isset($request->type) && in_array($request->type, ['month', 'week']) ? $request->type : 'week';

    if (isset($request->period)) {
        $period = ($type == 'month') ? clamp($request->period, 1, 12) : clamp($request->period, 1, 53);
    } else {
        $period = ($type == 'month') ? date('m') : date('W');
    }

    if(!isset($request->year) || empty($request->year)) {
        $year = ($type == 'week' && $period == 53 && date('j') >= 1) ? date('Y') - 1 : date('Y');
    } else {
        $year = intval($request->year);
    }

    if (isset($request->which)) {
        if ($request->which == 'events') {
            $which = 'event';
        } else {
            $which = 'stream';
        }
    } else {
        $which = 'stream';
    }

    new RankingController(array(
        $type => $period,
        'item' => $which,
        'year' => $year,
        'type' => $type,
        'subset' => $request->subset
    ), $tpl, $db);
});

//Routing: trends
$router->respond('/trends/[month|week|year:per]?/[vh|peek|average:type]?/?', function ($request) use ($tpl, $db) {
    new TrendController(['per' => $request->per, 'type' => $request->type], $tpl, $db);
});

//Routing: trends
$router->respond('/trends/chart/[month|week|year:per]/[vh|peak|average:type]/', function ($request) use ($tpl, $db) {
    new TrendController(['mode' => 'chart', 'per' => $request->per, 'type' => $request->type], $tpl, $db);
});

//Routing: Pages
$router->respond('/[:page]/?', function ($request) use ($router) {
    global $tpl;

    $source = dirname(__FILE__).'/pages/'.$request->page.'.md';
    if (is_readable($source)) {
        $parser = new \cebe\markdown\GithubMarkdown();
        $parser->html5 = true;
        $html = $parser->parse(file_get_contents($source));

        preg_match('/<h1>(.+)<\/h1>/siU', $html, $header);
        $title = trim(strip_tags($header[1]));

        $tpl->set_title($title);
        $tpl->add_breadcrumb($title, '/'.$request->page.'/');
        $tpl->set_buffer('<div class="page">'.$html.'</div>');
        $tpl->layout();
    } else {
        $router->abort(404);
    }
});


//Routing: ajax calls
$router->respond('/async/[:type]/[:action]/[:params]?/?', function ($request) use ($tpl, $db) {
    new AjaxController(array('type' => $request->type, 'mode' => $request->action, 'params' => $request->params), $tpl, $db);
});


//Routing: SCSS
$router->respond('[*]/[a:file].css', function ($request) use ($router, $__games) {
    $file = dirname(__FILE__).'/assets/style/'.preg_replace('/[^a-zA-Z0-9-]+/siU', '', $request->file).'.scss';

    //process scss
    if (is_readable($file)) {
        header('Content-type: text/css');

        $scss = new \scssc();
        $scss->setFormatter('scss_formatter_compressed');
        $scss->setImportPaths(dirname(__FILE__).'/assets/style/');
        $scss->registerFunction('img', function ($args) {
            return "'".WEBROOT.'/assets/images/'.$args[0][2][0]."'";
        });
        $scss->registerFunction('base-color', function ($args) use ($__games) {
            $c = $__games[ACTIVE_GAME]['color'];
            return array('color', hexdec(substr($c,1,2)), hexdec(substr($c,3,2)), hexdec(substr($c,5,2)), 255);
        });

        echo $scss->compile(file_get_contents($file));
    } else {
        $router->abort(404);
    }
});

//Routing: Favicon
$router->respond('/assets/images/[favicon|logo:image].png', function ($request) {
    require_once dirname(__FILE__).'/assets/images/'.$request->image.'.php';
});


//Routing: catch-all (aka 404...)
$router->onHttpError(function ($code) use ($router) {
    global $tpl;

    $flashes = $router->service()->flashes();
    if (!empty($flashes) && isset($flashes['info'])) {
        $tpl->assign('errors', $flashes['info']);
    }

    if ($code == 404) {
        $tpl->error('The page you\'re looking for doesn\'t exist anymore. Sorry! Maybe you can find what you\'re looking for via the <a href="/">front page</a>.');
    } elseif ($code == 500) {
        $tpl->error('Uh oh, something is broken!');
    } else {
        $tpl->error('Uh oh, some server error occurred!');
    }
});

//Routing: error pages
$router->onError(function ($router, $message) {
    $router->service()->flash($message);
    try {
        $router->abort(500);
    } catch (\Klein\Exceptions\HttpException $e) {
        $trace = $e->getTrace();
        //klein does some crazy things with exceptions so this ridiculous chain is needed to get to the actual exception
        foreach ($trace as $bit) {
            if ($bit['function'] == '{closure}') {
                foreach ($bit['args'] as $obj) {
                    if (is_object($obj) && $obj instanceof \ErrorException) {
                        $trace = $obj->getTrace();
                        echo '<div style="border-radius:8px;position:fixed;top:16px;right:16px;background:#F99;padding:1em;font-family:sans-serif;font-size:16px;"><h2 style="padding:0;margin:0 0 1em 0;font-weight:bold;color:#FFF;">'.get_class($obj).': '.$obj->getMessage().'</h2>';
                        echo '<ul style="border-radius:8px;list-style:none;padding:1em;background:rgba(255,255,255,0.5);color:#000;">';
                        foreach ($trace as $file) {
                            if (isset($file['file'])) {
                                $func = isset($file['class']) ? $file['class'].$file['type'].$file['function'] : $file['function'];
                                echo '<li style="font-family:monospace;">&#8594; '.$file['file'].' ('.$file['line'].'): '.$func.'()</li>';
                            }
                        }
                        echo '</ul></div>';
                    }
                }
            }
        }
    }
});

$router->dispatch();