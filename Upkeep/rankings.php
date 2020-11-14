<?php
/**
 * Calculate rankings
 */
namespace Fuzic\Upkeep;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;


ini_set('max_execution_time', 0);
chdir(dirname(__FILE__));
require_once '../init.php';

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);

//calculate rankings for the past month (31 days, really)
$now = time();
$month_ago = time() - (86400 * 31);

$last_update = $cache->get('rank_last');

if (!$last_update) {
    $last_update = $now - 3600;
}

$last_update -= Fuzic\Config::MAX_SESSION_PAUSE;
$last_update -= Fuzic\Config::CHECK_DELAY;

$cache->set('rank_last', time());

foreach ($games as $game => $game_info) {
    $ranking = new Lib\Ranking($last_update, time(), 'stream', false, $game);
    $ranking->rank_since($last_update);
    usleep(1000);

    //update alltime ranks
    //$ranking->rank_alltime();
    
    //do eventless alzo
    $ranking = new Lib\Ranking($last_update, time(), 'stream', false, $game, true);
    $ranking->rank_since($last_update);
    usleep(1000);

    //event rankings
    $ranking = new Lib\EventRanking($month_ago, $now, $game);
    $ranking->rank();
    usleep(1000);

    //check events
    $event_cutoff = $last_update - Fuzic\Config::MAX_SESSION_PAUSE;
    $events = Models\Event::find([
        'where' => [
            'end < ? AND end > ? AND game = ?' => [$last_update, $event_cutoff, $game]
        ],
        'return' => Lib\Model::RETURN_OBJECTS
    ]);

    foreach ($events as $event) {
        $sessions = Models\Session::find([
            'where' => [
                'game = ? AND ((start > ? AND start < ?) OR (end > ? AND end < ?) OR (start < ? AND end > ?))' => [$event->get('game'), $event->get('start'), $event->get('end'), $event->get('start'), $event->get('end'), $event->get('start'), $event->get('end')]
            ],
            'return' => Lib\Model::RETURN_OBJECTS
        ]);

        $length = $event->get('end') - $event->get('start');
        if ($length < Fuzic\Config::MAX_SESSION_PAUSE) {
            continue;
        }

        foreach ($sessions as $session) {
            if (Models\EventStream::find(['return' => Lib\Model::RETURN_BOOLEAN, 'where' => ['event = ? AND stream = ?' => [$event->get_ID(), $session->get('stream')]]])) {
                continue;
            }

            if ($session->get('peak') < 750 && Models\EventStream::find(['return' => Lib\Model::RETURN_AMOUNT, 'where' => ['stream = ?' => [$session->get('stream')]]]) < 10) {
                continue;
            }

            $diff = abs($session->get('start') - $event->get('start'));
            $diff += abs($session->get('end') - $event->get('end'));

            $franchise = new Models\Franchise($event->get('franchise'));
            $franchise_test = stripos($session->get('title'), $franchise->get('real_name')) !== false || stripos($session->get('title'), $franchise->get('name')) !== false;

            if ($diff == 0 || $diff / $length < 0.05 || ($franchise_test && $diff / $length < 0.05)) {
                $start = $session->get('start') < $event->get('start') ? $event->get('start') : $session->get('start');
                $end = $session->get('end') > $event->get('end') ? $event->get('end') : $session->get('end');

                $stream = new Models\Stream($session->get('stream'));

                echo 'Auto-linking session '.$session->get_ID().' from stream '.$stream->get('name').' to event '.$event->get('name')."\n";

                Models\EventStream::create([
                    'event' => $event->get_ID(),
                    'stream' => $stream->get_ID(),
                    'start' => $start,
                    'end' => $end,
                    'auto' => 1
                ]);
            }
        }
    }
}


//cache calendar for use on stream ranking pages
$cal_min = $db->fetch_single("SELECT week, year FROM ranking_week ORDER BY year ASC, week ASC");
$cal_max = $db->fetch_single("SELECT week, year FROM ranking_week ORDER BY year DESC, week DESC");
$cal = array();
for ($y = $cal_min['year']; $y <= $cal_max['year']; $y += 1) {
    $cal[$y] = array();
    $w_min = ($y == $cal_min['year']) ? $cal_min['week'] : intval(date('W', strtotime('01-01-'.$y)));
    $w_max = ($y == $cal_max['year']) ? $cal_max['week'] : units_per_year('week', $y);
    for ($w = $w_min; $w <= $w_max; $w += 1) {
        $m = intval(date('n', strtotime($y.'W'.str_pad($w, 2, '0', STR_PAD_LEFT))));
        if (!isset($cal[$y][$m])) {
            $cal[$y][$m] = array();
        }
        $cal[$y][$m][] = $w;
    }
}
$cache->set('calendar', $cal);

//now stats are up to date, tweet them where relevant
include 'tweet_events.php';

//calculate day stats
include 'calc_day.php';