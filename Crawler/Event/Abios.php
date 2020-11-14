<?php
namespace Fuzic\Crawler\Event;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class AbiosChecker extends EventCalendarFetcher {
    const CALENDAR_ID = 'abios';
    const CALENDAR_PREFIX = 'abios-';

    public function run() {
        $url = 'https://abiosgaming.com/ajax/calendar-matches?take=50&upcoming=true';
        try {
            $source = get_url($url, array(), false, true);
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve Abios Gaming live events from '.$url.'; cURL error, aborting');

            return;
        }

        try {
            $db = new Lib\mysqldb();
        } catch(Lib\DBException $e) {
            $this->log('Could not connect to database in Abios crawler', Crawler\StreamChecker::LOG_EMERGENCY);

            return;
        }

        $json = json_decode($source, true);
        if(json_last_error() != JSON_ERROR_NONE) {
            $this->log('Invalid Abios JSON: '.json_last_error_msg());
            return;
        }
        if(!is_array($json)) {
            $this->log('Abios JSON did not contain an array');
            return;
        }

        foreach($json as $live) {
            $game = Crawler\StreamChecker::map_game_name($live['game_title']);
            if(!$game || $live['start'] > time()) {
                continue;
            }

            $remote_ID = $live['stage_id'];
            $event_ID = static::CALENDAR_PREFIX.$remote_ID;

            $name = (isset($live['tournament_title']) && !empty($live['tournament_title'])) ? $live['tournament_title'].": ".$live['title'] : $live['title'];

            $event = array(
                'game' => $game,
                'id' => $event_ID,
                'remote_ID' => $remote_ID,
                'name' => $name,
                'wiki' => '',
                'short_name' => $live['tournament_abbrev'],
                'streams' => array()
            );

            foreach($live['casters'] as $caster) {
                $link = Crawler\StreamChecker::map_stream_link($caster['link']);
                if(!$link) {
                    $this->log('Abios stream link '.$caster['link'].' could not be mapped to a stream', Crawler\StreamChecker::LOG_WARNING);
                    continue;
                } else {
                    $event['streams'][] = $link;
                }
            }

            $this->add_event($event_ID, $event);

            if(isset($live['compA_name']) && isset($live['compB_name'])) {
                $player1 = $live['compA_name'];
                $player2 = $live['compB_name'];

                $player1_obj = Models\Stream::find(['where' => ['real_name = ?' => [$player1]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT], $db);
                $player2_obj = Models\Stream::find(['where' => ['real_name = ?' => [$player2]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT], $db);

                $this->add_match(array(
                    'event' => $event_ID,
                    'match' => $player1.' vs '.$player2,
                    'player1' => ($player1_obj ? $player1_obj->get_ID() : 0),
                    'player2' => ($player2_obj ? $player2_obj->get_ID() : 0)
                ));
            }
        }
    }
}