<?php
namespace Fuzic\Site;

use Fuzic;
use Fuzic\Models;
use Fuzic\Lib;


/**
 * THE FRONTPAGE!
 *
 * @package Fuzic-site
 */
//past 24 hours
$now = time();
$wayback = $now - 86400;
$cutoff = $now - (Fuzic\Config::CHECK_DELAY * 5);
$audience = array_map(function ($a) {
    return $a['viewers'];
}, $db->fetch_all_indexed("SELECT time, viewers FROM audience WHERE game = ".$db->escape(ACTIVE_GAME)." AND time > ".$wayback.' ORDER BY time ASC', 'time'));
ksort($audience);


reset($audience);
//make sure the audience record spans 24 hours even if there is less data
//unless there is no data, in which case don't show anything
if (count($audience) > 0) {
    //pad outside
    for ($i = key($audience) - Fuzic\Config::CHECK_DELAY; $i >= $wayback; $i -= Fuzic\Config::CHECK_DELAY) {
        $audience[$i] = 0;
    }

    ksort($audience);
    end($audience);

    if (key($audience) < ($now - (Fuzic\Config::CHECK_DELAY * 3))) {
        for ($i = key($audience) + Fuzic\Config::CHECK_DELAY; $i < $now; $i += Fuzic\Config::CHECK_DELAY) {
            $audience[$i] = 0;
        }
    }

    //pad inside
    reset($audience);
    $new = array();
    while (false !== ($viewers = current($audience))) {
        $time = key($audience);
        $new[$time] = $viewers;
        next($audience);
        $next = key($audience);
        if (($next - $time) > (Fuzic\Config::CHECK_DELAY * 3)) {
            while ($time < ($next - Fuzic\Config::CHECK_DELAY)) {
                $time += Fuzic\Config::CHECK_DELAY;
                $new[$time] = 0;
            }
        }
    }
    $audience = $new;
}

//only show one in ten values, as else the chart on the front
//page becomes too precise and slow
$i = 0;
foreach ($audience as $key => $value) {
    if ($i % 10 != 0) {
        unset($audience[$key]);
    }
    $i += 1;
}
$timestamps = array_keys($audience);

//latest events
$events = Models\Event::find([
    'where' => "hidden = 0 AND game = ".$db->escape(ACTIVE_GAME)." AND end > ".$wayback.' AND (peak > 1500 || average > 500) AND start < '.time(),
    'order_by' => 'end',
    'order' => 'desc'
]);

$latest_events = array();
foreach ($events as $event) {
    $when = $event['end'] - (($event['end'] - $event['start']) / 2);
    $when = $now - $when;
    $index = floor((1 - ($when / 86400)) * count($timestamps));
    if ($index < 0) {
        continue;
    }
    $viewers = isset($timestamps[$index]) && isset($audience[$timestamps[$index]]) ? $audience[$timestamps[$index]] : 0;
    $latest_events[] = array(
        'index' => $index,
        'live' => (($now - $event['end']) < (Fuzic\Config::CHECK_DELAY * 5)),
        'viewers' => $viewers,
        'event' => $event
    );
}

//latest top stream
$top = Models\Session::find([
    'constraint' => 'end > '.$wayback." AND game = '".ACTIVE_GAME."'",
    'limit' => 1,
    'order_by' => 'vh',
    'return' => 'single',
    'order' => 'desc'
]);

if ($top) {
    try {
        $top_stream = new Models\Stream($top['stream']);
        $top = array_merge($top, $top_stream->get_all_data());
    } catch (\ErrorException $e) {
        echo '<!-- '.$e->getMessage().'-->';
    }
}

$top_streams = Lib\Ranking::get_current_week(false, 5);

//live events
$live_events = Models\Event::find([
    'where' => "game = '".ACTIVE_GAME."' AND end > ".$cutoff,
    'order_by' => 'vh',
    'order' => 'desc',
    'limit' => 3
]);

//dead events
$events = Models\Event::find([
    'limit' => 3,
    'order_by' => 'start',
    'order' => 'DESC',
    'where' => "hidden = 0 AND game = ".$db->escape(ACTIVE_GAME)." AND average > 50 AND end < ".$cutoff
]);

$tpl->assign('top', $top);
$tpl->assign('24h_times', json_encode_strings(array_map(function ($a) {
    return date('H:i', $a);
}, array_keys($audience))));
$tpl->assign('24h_viewers', json_encode_ints(array_values($audience)));
$tpl->assign('highlights', $latest_events);
$tpl->assign('viewers', $db->fetch_field("SELECT viewers FROM audience WHERE game = ".$db->escape(ACTIVE_GAME)." ORDER BY time DESC LIMIT 1"));
$tpl->assign('live_events', $live_events);
$tpl->assign('top_streams', $top_streams);
$tpl->assign('events', $events);

$tpl->add_JS('frontpage.js');

global $__games;
$tpl->set_title($__games[ACTIVE_GAME]['name'].' streaming statistics and rankings');
$tpl->layout('frontpage.tpl');
