<?php
/**
 * Recalculate daily stats
 */
namespace Fuzic\Tools;

chdir(dirname(__FILE__));
require '../init.php';

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);

foreach ($games as $game => $game_info) {
    $begin = $db->fetch_field("SELECT MIN(start) FROM sessions WHERE game = ".$db->escape($game));
    $last_day = $db->fetch_field("SELECT MAX(time) FROM datapoints WHERE game = ".$db->escape($game));
    if (!$begin || $begin == 0) {
        continue;
    }
    $year = date('Y', $begin);
    $year_now = date('Y');
    echo $game."\n";

    for ($y = $year; $y <= $year_now; $y += 1) {
        for ($m = 1; $m <= 12; $m += 1) {
            $days = cal_days_in_month(CAL_GREGORIAN, $m, $y);
            for ($d = 1; $d <= $days; $d += 1) {
                $start = mktime(0, 0, 0, $m, $d, $y);
                $end = mktime(23, 59, 59, $m, $d, $y);

                if ($start < $begin || $start > $last_day) {
                    continue;
                }

                $audience = $db->fetch_all("SELECT `time`, viewers FROM audience WHERE game = ".$db->escape($game)." AND `time` >= ".$start." AND `time` <= ".$end." ORDER BY `time` ASC");
                $stats = array('peak' => 0);
                $total_time = 0;
                $total_viewers = 0;

                foreach ($audience as $datapoint) {
                    if ($datapoint['viewers'] > $stats['peak']) {
                        $stats['peak'] = $datapoint['viewers'];
                    }

                    if (isset($previous)) {
                        $interval_time = $datapoint['time'] - $previous['time'];
                        $interval_average = ($datapoint['viewers'] + $previous['viewers']) / 2;

                        $total_viewers += ($interval_average * $interval_time);
                        $total_time += $interval_time;
                    }

                    $previous = array('time' => $datapoint['time'], 'viewers' => $datapoint['viewers']);
                }

                if ($total_time == 0) {
                    $stats['average'] = 0;
                } else {
                    $stats['average'] = $total_viewers / $total_time;
                }

                $stats['vh'] = ($total_time * $stats['average']) / 3600;

                $db->query("DELETE FROM overall WHERE game = ".$db->escape($game)." AND day = ".intval($d)." AND month = ".intval($m)." AND year = ".intval($y));
                $db->insert('overall', [
                    'game' => $game,
                    'day' => $d,
                    'month' => $m,
                    'year' => $y,
                    'week' => date('W', $start),
                    'average' => $stats['average'],
                    'peak' => $stats['peak'],
                    'vh' => $stats['vh']
                ]);
            }
        }
    }
}