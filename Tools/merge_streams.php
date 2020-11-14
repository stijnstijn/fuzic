<?php
/**
 * Manually merge one stream into the other
 */
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [stream 1 ID] [stream 2 ID]\n";
    echo 'Stream 1 will be deleted and have its data added to stream 2\'s'."\n";
    exit;
}

try {
    $stream1 = new Models\Stream($argv[1]);
} catch(Lib\ItemNotFoundException $e) {
    $stream1 = new Models\Stream(['name' => $argv[1]]);
}

try {
    $stream2 = new Models\Stream($argv[2]);
} catch(Lib\ItemNotFoundException $e) {
    $stream2 = new Models\Stream(['name' => $argv[2]]);
}

$db->toggle_debug(true, true);

$db->query("UPDATE ".Models\EventStream::TABLE." SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ".Models\Session::TABLE." SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ".Models\User::TABLE." SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ".Models\Datapoint::TABLE." SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));


//merge rankings
$ranking_alltime = $db->fetch_all("SELECT * FROM ranking_alltime WHERE stream = ".$db->escape($stream1->get_ID()));
foreach($ranking_alltime as $rank) {
    $data = $db->fetch_single("SELECT * FROM ranking_alltime WHERE game = ".$db->escape($rank['game'])." AND stream = ".$db->escape($stream2->get_ID()));
    if(!$data) {
        continue;
    }
    $peak = max($data['peak'], $rank['peak']);
    $time = $data['time'] + $rank['time'];
    $average = ($data['average'] * $data['time'] + $rank['average'] * $rank['time']) / $time;
    $vh = floor($average * $time / 3600);
    $db->query("UPDATE ranking_alltime SET peak = ".$db->escape($peak).", time = ".$db->escape($time).", average = ".$db->escape($average).", vh = ".$db->escape($vh)." WHERE id = ".$data['id']);
    $db->query("DELETE FROM ranking_alltime WHERE id = ".$rank['id']);
}

$ranking_month = $db->fetch_all("SELECT * FROM ranking_month WHERE stream = ".$db->escape($stream1->get_ID()));
foreach($ranking_month as $rank) {
    $data = $db->fetch_single("SELECT * FROM ranking_month WHERE year = ".$db->escape($rank['year'])." AND month = ".$db->escape($rank['month'])." AND game = ".$db->escape($rank['game'])." AND stream = ".$db->escape($stream2->get_ID()));
    if(!$data) {
        continue;
    }
    $peak = max($data['peak'], $rank['peak']);
    $time = $data['time'] + $rank['time'];
    $average = ($data['average'] * $data['time'] + $rank['average'] * $rank['time']) / $time;
    $vh = floor($average * $time / 3600);
    $db->query("UPDATE ranking_month SET peak = ".$db->escape($peak).", time = ".$db->escape($time).", average = ".$db->escape($average).", vh = ".$db->escape($vh)." WHERE id = ".$data['id']);
    $db->query("DELETE FROM ranking_month WHERE id = ".$rank['id']);
}

$ranking_month = $db->fetch_all("SELECT * FROM ranking_month_e WHERE stream = ".$db->escape($stream1->get_ID()));
foreach($ranking_month as $rank) {
    $data = $db->fetch_single("SELECT * FROM ranking_month_e WHERE year = ".$db->escape($rank['year'])." AND month = ".$db->escape($rank['month'])." AND game = ".$db->escape($rank['game'])." AND stream = ".$db->escape($stream2->get_ID()));
    if(!$data) {
        continue;
    }
    $peak = max($data['peak'], $rank['peak']);
    $time = $data['time'] + $rank['time'];
    $average = ($data['average'] * $data['time'] + $rank['average'] * $rank['time']) / $time;
    $vh = floor($average * $time / 3600);
    $db->query("UPDATE ranking_month_e SET peak = ".$db->escape($peak).", time = ".$db->escape($time).", average = ".$db->escape($average).", vh = ".$db->escape($vh)." WHERE id = ".$data['id']);
    $db->query("DELETE FROM ranking_month_e WHERE id = ".$rank['id']);
}

$ranking_month = $db->fetch_all("SELECT * FROM ranking_week WHERE stream = ".$db->escape($stream1->get_ID()));
foreach($ranking_month as $rank) {
    $data = $db->fetch_single("SELECT * FROM ranking_week WHERE year = ".$db->escape($rank['year'])." AND week = ".$db->escape($rank['week'])." AND game = ".$db->escape($rank['game'])." AND stream = ".$db->escape($stream2->get_ID()));
    if(!$data) {
        continue;
    }
    $peak = max($data['peak'], $rank['peak']);
    $time = $data['time'] + $rank['time'];
    $average = ($data['average'] * $data['time'] + $rank['average'] * $rank['time']) / $time;
    $vh = floor($average * $time / 3600);
    $db->query("UPDATE ranking_week SET peak = ".$db->escape($peak).", time = ".$db->escape($time).", average = ".$db->escape($average).", vh = ".$db->escape($vh)." WHERE id = ".$data['id']);
    $db->query("DELETE FROM ranking_week WHERE id = ".$rank['id']);
}

$ranking_month = $db->fetch_all("SELECT * FROM ranking_week_e WHERE stream = ".$db->escape($stream1->get_ID()));
foreach($ranking_month as $rank) {
    $data = $db->fetch_single("SELECT * FROM ranking_week_e WHERE year = ".$db->escape($rank['year'])." AND week = ".$db->escape($rank['week'])." AND game = ".$db->escape($rank['game'])." AND stream = ".$db->escape($stream2->get_ID()));
    if(!$data) {
        continue;
    }
    $peak = max($data['peak'], $rank['peak']);
    $time = $data['time'] + $rank['time'];
    $average = ($data['average'] * $data['time'] + $rank['average'] * $rank['time']) / $time;
    $vh = floor($average * $time / 3600);
    $db->query("UPDATE ranking_week_e SET peak = ".$db->escape($peak).", time = ".$db->escape($time).", average = ".$db->escape($average).", vh = ".$db->escape($vh)." WHERE id = ".$data['id']);
    $db->query("DELETE FROM ranking_week_e WHERE id = ".$rank['id']);
}

$db->query("UPDATE ranking_alltime SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ranking_week SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ranking_week_e SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ranking_month SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));
$db->query("UPDATE ranking_month_e SET stream = ".$db->escape($stream2->get_ID())." WHERE stream = ".$db->escape($stream1->get_ID()));

foreach (['wiki', 'twitter', 'avatar', 'tl_id'] as $field) {
    if (empty($stream2->get($field))) {
        $stream2->set($field, $stream1->get($field));
    }
}

if ($stream2->get('team') == Models\Team::TEAMLESS_ID) {
    $stream2->set('team', $stream1->get('team'));
}

if ($stream2->get('real_name') == $stream2->get('name')) {
    $stream2->set('real_name', $stream1->get('real_name'));
}

$stream1->delete();