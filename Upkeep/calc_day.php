<?php
/**
 * Calculate daily stats
 */
namespace Fuzic\Upkeep;

use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require_once '../init.php';

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);

$d = date('j');
$m = date('n');
$y = date('Y');

$start = mktime(0, 0, 0, $m, $d, $y);
$end = mktime(23, 59, 59, $m, $d, $y);

foreach ($games as $game => $game_info) {
    $audience = $db->fetch_all("SELECT `time`, viewers FROM audience WHERE game = ".$db->escape($game)." AND `time` >= ".$start." AND `time` <= ".$end." ORDER BY `time` ASC");
    $stats = array('peak' => 0);
    $stats_eventless = array('peak' => 0);
    $total_time = 0;
    $total_viewers = 0;
    $total_viewers_eventless = 0;
    unset($previous);

    //for each datapoint on this day, find out how many people were watching events
    $subtract = array();
    $events = Models\EventStream::find_between(
        mktime(0, 0, 0, $m, $d, $y),
        mktime(23, 59, 59, $m, $d, $y),
        $game
    );
    foreach($events as $stream) {
        $lower = $stream['start'] < $start ? $start : $stream['start'];
        $upper = $stream['end'] > $end ? $end : $stream['end'];
        $interval = new Lib\Interval($stream['stream'], $lower, $upper);
        $datapoints = $interval->get_datapoints();
        foreach($datapoint as $time => $viewers) {
            if(isset($subtract[$time])) {
                $subtract[$time] += $viewers;
            } else {
                $subtract[$time] = $viewers;
            }
        }
    }

    //go through the datapoints for them statz
    //do everything twitce; once overall, once without events
    foreach ($audience as $datapoint) {
        $viewers_eventless = isset($subtract[$datapoint['time']]) ? $datapoint['viewers'] - $subtract[$datapoint['time']] : $datapoint['viewers'];

        if ($datapoint['viewers'] > $stats['peak']) {
            $stats['peak'] = $datapoint['viewers'];
        }

        if ($viewers_eventless > $stats_eventless['peak']) {
            $stats_eventless['peak'] = $datapoint['viewers'];
        }

        if (isset($previous)) {
            $interval_time = $datapoint['time'] - $previous['time'];
            $interval_average = ($datapoint['viewers'] + $previous['viewers']) / 2;
            $interval_average_eventless = ($viewers_eventless + $previous['viewers_eventless']) / 2;

            $total_viewers += ($interval_average * $interval_time);
            $total_viewers_eventless += ($interval_average_eventless * $interval_time);
            $total_time += $interval_time;
        }

        $previous = array('time' => $datapoint['time'], 'viewers' => $datapoint['viewers'], 'viewers_eventless' => $viewers_eventless);
    }

    if ($total_time == 0) {
        $stats['average'] = 0;
        $stats_eventless['average'] = 0;
    } else {
        $stats['average'] = $total_viewers / $total_time;
        $stats_eventless['average'] = $total_viewers_eventless / $total_time;
    }

    $stats['vh'] = ($total_time * $stats['average']) / 3600;
    $stats_eventless['vh'] = ($total_time * $stats_eventless['average']) / 3600;

    $db->query("DELETE FROM overall WHERE game = ".$db->escape($game)." AND day = ".intval($d)." AND month = ".intval($m)." AND year = ".intval($y));
    $db->insert('overall', [
        'game' => $game,
        'day' => $d,
        'month' => $m,
        'year' => $y,
        'week' => date('W', $start),
        'average' => $stats['average'],
        'peak' => $stats['peak'],
        'vh' => $stats['vh'],
        'average_eventless' => $stats_eventless['average'],
        'peak_eventless' => $stats_eventless['peak'],
        'vh_eventless' => $stats_eventless['vh']
    ]);
}