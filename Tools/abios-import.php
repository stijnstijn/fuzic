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

$events = get_url('https://abiosgaming.com/ajax/tournaments?past=true&skip=0&take=500&games[]=6&games[]=9&games[]=12&from=1510852201&query=', array(), false, true);
$events = json_decode($events, true);

$i = 1;
foreach($events['list'] as $event) {
    if($event['start'] > 1510852201) {
        continue;
    }

    $data = get_url('https://abiosgaming.com/ajax/tournament/'.$event['id'], array(), false, true);
    $data = json_decode($data, true);

    $competitors = array();
    foreach($data['teams'] as $team) {
        $competitors[$team['id']] = $team['name'];
    }

    echo '/!\ [Processed: '.$i.'] Found event: '.$data['title']."\n";
    $game = Crawler\StreamChecker::map_game_name($data['game_slug']);
    if(!$game) {
        echo 'Unknown game for event '.$data['id'].': '.$data['game_slug']."\n";
        continue;
    }

    $all_events = array();
    foreach($data['stages'] as $stage) {
        $stage_start = time() + 1;
        $stage_end = 0;
        foreach($stage['sub_stages'] as $substage) {
            foreach($substage['matches'] as $match) {
                $stage_start = min($match['start'], $stage_start);
                $stage_end = max($match['end'], $stage_end);
            }
        }

        $previous = $stage_start;
        $sub_matches = array();
        $stagenames = array();
        $start = false;
        $all_matches = array();

        foreach($stage['sub_stages'] as $substage) {
            foreach($substage['matches'] as $match) {
                $match['sub_stage'] = $substage['name'];
                $all_matches[] = $match;
            }
        }

        uasort($all_matches, function($a, $b) {
            if($a['start'] > $b['start']) {
                return 1;
            } elseif($a['start'] < $b['start']) {
                return -1;
            }
            return 0;
        });

        $all_matches[] = ['start' => time() + 1, 'end' => time() + 2, 'sub_stage' => 0, 'dummy' => true];
        foreach($all_matches as $match) {
            if(!in_array($match['sub_stage'], $stagenames) && !isset($match['dummy'])) {
                $stagenames[] = $match['sub_stage'];
            }

            if($match['start'] > $previous + 7200) {
                if(count($sub_matches) > 0) {
                    $title = $data['title'].': '.$stage['name'].' '.implode(', ', $stagenames).'';
                    echo "/!\ Sub-event: ".$title."\n";
                    echo 'Event lasts '.time_approx($end - $start)."\n";
                    echo 'Event starts '.date('r', $start)."\n";
                    echo 'Event ends '.date('r', $end)."\n";

                    $new_event_data = array('event' => array(), 'links' => array(), 'matches' => array());

                    $new_event_data['event'] = [
                        'tl_id' => 'abios-'.$event['id'],
                        'game' => $game,
                        'name' => $title,
                        'short_name' => Models\Event::get_short_name($title),
                        'franchise' => Models\Event::get_franchise($title),
                        'wiki' => (strstr($data['wiki_link'], 'teamliquid') ? $data['wiki_link'] : ''),
                        'start' => $start,
                        'end' => $end,
                        'hidden' => 2
                    ];

                    foreach($data['casters'] as $caster) {
                        $ID = Crawler\StreamChecker::map_stream_link($caster['link']);
                        if(!$ID) {
                            continue;
                        }

                        try {
                            $stream = new Models\Stream(['provider' => $ID['provider'], 'remote_id' => $ID['remote_ID']]);
                        } catch(Lib\ItemNotFoundException $e) {
                            echo "Stream not known: ".$caster['link']."\n";
                            continue;
                        }

                        $sessions = Models\Session::get_between($start, $end, $game);
                        if(count($sessions) == 0) {
                            echo "Stream ".$stream->get_ID()." was linked to event ".$data['title'].' ('.$data['id'].') but had no sessions'."\n";
                        }

                        $stream_start = $end;
                        $stream_end = $start;
                        foreach($sessions as $session) {
                            $stream_start = min($stream_start, $session['start']);
                            $stream_end = max($stream_end, $session['end']);
                        }

                        $new_event_data['links'][] = [
                            'event' => 0,
                            'stream' => $stream->get_ID(),
                            'start' => max($stream_start, $start),
                            'end' => min($stream_end, $end)
                        ];

                        echo 'Found linked streamer: '.$stream->get_ID()."\n";
                    }

                    foreach($sub_matches as $sub_match) {
                        $mstart = $db->fetch_field("SELECT time FROM audience WHERE time > ".$db->escape($sub_match['start'])." ORDER BY time ASC LIMIT 1");
                        $mend = $db->fetch_field("SELECT time FROM audience WHERE time > ".$db->escape($sub_match['end'])." ORDER BY time ASC LIMIT 1");

                        $player1_obj = Models\Stream::find(['where' => ['real_name = ?' => [$competitors[$sub_match['compA_id']]]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT]);
                        $player2_obj = Models\Stream::find(['where' => ['real_name = ?' => [$competitors[$sub_match['compB_id']]]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT]);

                        echo 'Found match: '.$competitors[$sub_match['compA_id']].' vs '.$competitors[$sub_match['compB_id']]."\n";

                        $new_event_data['matches'][] = [
                            'event' => 0,
                            'match' => $competitors[$sub_match['compA_id']].' vs '.$competitors[$sub_match['compB_id']],
                            'start' => $mstart,
                            'end' => $mend,
                            'player1' => ($player1_obj ? $player1_obj->get_ID() : 0),
                            'player2' => ($player2_obj ? $player2_obj->get_ID() : 0)
                        ];
                    }

                    $all_events[] = $new_event_data;
                }

                $sub_matches = array();
                $stagenames = array();
                $start = false;
            }

            if(!$start) {
                $start = $match['start'];
            }

            $end = $match['end'];
            $sub_matches[] = $match;
            $previous = $match['end'];
        }
    }

    foreach($all_events as $index => $event) {
        $session = 1;
        $duplicate = false;
        foreach($all_events as $other_index => $other_event) {
            if($index == $other_index) {
                continue;
            }
            if($other_event['event']['name'] == $event['event']['name']) {
                $duplicate = true;
                if($other_event['event']['start'] < $event['event']['start']) {
                    $session += 1;
                }
            }
        }
        if($duplicate) {
            if(count($event['matches']) == 1) {
                $all_events[$index]['event']['new_name'] = $all_events[$index]['event']['name'].': '.$event['matches'][0]['match'];
            } else {
                $all_events[$index]['event']['new_name'] = $all_events[$index]['event']['name'].' (Session '.$session.')';
            }
            echo "Renamed event to ".$all_events[$index]['event']['new_name']."\n";
        }
    }

    foreach($all_events as $data) {
        $db->start_transaction();
        if(isset($data['event']['new_name'])) {
            $data['event']['name'] = $data['event']['new_name'];
            unset($data['event']['new_name']);
        }
        $event = Models\Event::create($data['event']);
        foreach($data['links'] as $link) {
            $link['event'] = $event->get_ID();
            Models\EventStream::create($link);
        }
        foreach($data['matches'] as $match) {
            $match['event'] = $event->get_ID();
            Models\Match::create($match);
        }

        $db->commit();
        echo "Created event ".$data['event']['name'].' in database'."\n";
    }

    echo "\n";
    $i += 1;
    sleep(rand(1, 2));
}