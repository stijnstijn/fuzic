<?php
/*
 * Import sessions from twinge.tv
 * To be used if, like, you *cough* accidentally delete a day's worth of sessions *cough*
 */
namespace Fuzic\Upkeep;
chdir(dirname(__FILE__));

require '../Crawler/class.StreamChecker.php';
require '../init.php';

use Fuzic\Lib;
use Fuzic\Config;
use Fuzic\Models;
use Fuzic\Crawler;


function make_headers($token) {
    $headers = array(
        'X-Requested-With: XMLHttpRequest',
        'Referer: http://www.twinge.tv/channels/directory',
        'X-Csrf-Token: '.$token
    );
    $cookies = file('/srv/www/fuzic/cache/cookie.jar');
    foreach($cookies as $line) {
        $line = trim($line);
        $bits = explode("\t", $line);
        if(strpos($line, 'twinge.tv') !== false) {
            if($bits[5] == 'XSRF-TOKEN') {
                $headers[] = 'X-XSRF-TOKEN: '.$bits[6];
            }
        }
    }

    return $headers;
}

function get_token($url) {
    $handoff = get_url($url, array('Accept-Content' => true), false, true);
    preg_match("/window.handoff._token = \"([^\" ]+)\";/si", $handoff, $match);

    if(!isset($match[1])) {
        echo 'Error getting token from URL '.$url;
        exit;
    }

    return $match[1];
}

if (count($argv) < 4) {
    echo 'Correct usage: php '.basename(__FILE__)." [start time] [end time] [game]\n";
    echo 'Will import session data from twinge.tv'."\n";
    exit;
}

$failure = $argv[1]; //1464565621;
$resume = $argv[2]; //1464567841;

$streams = array();
$to_add = array();

$game = implode(' ', array_slice($argv, 3));

$max = 3;
echo 'Getting stream index for game '.$game."\n";

$token = get_token('http://www.twinge.tv/channels/directory');

if(!is_file($game.'.cache')) {

    for($i = 1; $i <= $max; $i += 1) {
        echo 'Parsing page '.$i."\n";
        $headers = make_headers($token);
        $page = get_url('http://www.twinge.tv/api/channels/rating/desc/?page='.$i.'&active=false&game='.urlencode($game), $headers, false, true);

        file_put_contents('cache', $page);
        $json = json_decode($page, true);
        if(!$json) {
            echo 'Could not parse stream index'."\n";
            exit;
        }

        if($max == 3) {
            $max = $json['last_page'];
        }
        foreach($json['data'] as $stream) {
            if(strtotime($stream['laststream_at']) > $failure) {
                $streams[] = $stream['name'];
            }
        }
    }

    echo count($streams).' streams will be checked.'."\n";
    foreach($streams as $stream) {
        $stream = trim($stream);
        echo 'Crawling sessions for '.$stream."\n";
        $token = get_token('http://www.twinge.tv/channels/'.$stream);
        $headers = make_headers($token);
        $overview = get_url('http://www.twinge.tv/api/channel/'.$stream.'/streamBreakdown/', $headers, false, true);
        $overview = json_decode($overview, true);
        if(!$json) {
            echo 'Could not parse stream overview'."\n";
            exit;
        }
        foreach($overview as $session) {
            $time = strtotime($session['created_at']);
            if($time > $resume || $time < $failure - (86400 * 7)) {
                continue;
            }


            $token = get_token('http://www.twinge.tv/channels/'.$stream.'/streams/#/'.$session['stream_id']);
            $headers = make_headers($token);
            $datapoints = get_url('http://www.twinge.tv/api/stream/'.$session['stream_id'].'/peeks/', $headers, false, true);
            $new_session = array('stream' => $stream, 'datapoints' => array(), 'peak' => 0, 'time' => 0, 'vh' => 0, 'average' => 0);
            $json = json_decode($datapoints, true);
            if(!$json) {
                echo 'Invalid peeks for '.$stream." session ".$session['stream_id'].': skipping'."\n";
                continue;
            }
            $peeks = $json['peeks'];
            $start = PHP_INT_MAX;
            $end = 0;
            foreach($peeks as $peek) {
                $time = strtotime($peek['created_at']);
                if($time < $failure || $time > $resume || $peek['game'] != $game) {
                    continue;
                }

                $new_session['datapoints'][$time] = $peek['viewers'];
                $start = min($start, $time);
                $end = max($end, $time);
            }

            if(count($new_session['datapoints']) > 0) {
                $new_session['start'] = $start;
                $new_session['end'] = $end;
                $to_add[] = $new_session;
                echo 'Found session for '.$stream."\n";
            }
        }
    }

    $h = fopen($game.'.cache', 'w');
    fwrite($h, json_encode($to_add));
    fclose($h);
}

$to_add = json_decode(file_get_contents($game.'.cache'), true);

foreach($to_add as $index => $session) {
    unset($previous);
    foreach($session['datapoints'] as $time => $viewers) {
        if(isset($previous)) {
            $diff = $time - $previous['time'];
            $fake = $diff / Config::CHECK_DELAY;
            $fake_time = $previous['time'] + Config::CHECK_DELAY;
            $viewers_delta = (($viewers - $previous['viewers']) / $fake);
            $fake_viewers = $previous['viewers'] + $viewers_delta;
            while($fake_time < $time) {
                $to_add[$index]['datapoints'][round($fake_time)] = round($fake_viewers);
                $fake_viewers += $viewers_delta;
                $fake_time += Config::CHECK_DELAY;
            }
        }

        $previous = array('time' => $time, 'viewers' => $viewers);
    }
    ksort($to_add[$index]['datapoints']);

    $new_datapoints = array();
    unset($previous);
    $total_time = 0;
    $total_viewers = 0;
    foreach($to_add[$index]['datapoints'] as $time => $viewers) {
        $time = $time - $session['start'];
        $new_datapoints[$time] = $viewers;
        $to_add[$index]['peak'] = max($to_add[$index]['peak'], $viewers);
        if(isset($previous)) {
            $length = ($time - $previous['time']);
            $total_time += $length;
            $total_viewers += (($previous['viewers'] + $viewers) / 2) * $length;
        }

        $previous = array('time' => $time, 'viewers' => $viewers);
    }

    if($total_time == 0) {
        unset($to_add[$index]);
        continue;
    }

    $to_add[$index]['time'] = $total_time;
    $to_add[$index]['average'] = round($total_viewers / $total_time);
    $to_add[$index]['vh'] = round(($to_add[$index]['average'] * $total_time) / 3600);
    $to_add[$index]['datapoints'] = $new_datapoints;
}

$missing = array();

foreach($to_add as $session) {
    try {
        $stream = new Models\Stream(['provider' => 'twitch', 'remote_id' => $session['stream']]);
    } catch(Lib\ItemNotFoundException $e) {
        $missing[] = $session['stream'];
        continue;
    }

    $new = $db->insert_fetch_id(Models\Session::TABLE, array(
        'stream' => $stream->get_ID(),
        'start' => $session['start'],
        'end' => $session['end'],
        'peak' => $session['peak'],
        'average' => $session['average'],
        'vh' => $session['vh'],
        'time' => $session['time'],
        'game' => Crawler\StreamChecker::map_game_name($game)
    ));

    if(!$new) {
        echo 'Could not add session for '.$stream->get('name')."\n";
        var_dump($session);
        exit;
    }

    $db->insert(Models\SessionData::TABLE, array(
        Models\SessionData::IDFIELD => $new,
        'datapoints' => json_encode($session['datapoints']),
        'title' => '',
        'interpolated' => 1
    ));
    echo 'Added session for '.$session['stream'].' ('.date('r', $session['start']).' - '.date('r', $session['end']).', peak '.$session['peak'].', average '.$session['average'].")\n";
}

foreach($missing as $stream) {
    echo 'Unknown stream: '.$stream."\n";
}
