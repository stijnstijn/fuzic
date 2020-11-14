<?php
namespace Fuzic\Crawler\Event;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class SplyceChecker extends EventCalendarFetcher {
    const CALENDAR_ID = 'splyce';
    const CALENDAR_PREFIX = 'splyce-';

    public function run() {
        $time = time();
        $params = '{"command":"findEvents","params":{"inputs":{"dateFrom":'.($time - 3600).',"dateUntil":'.($time + 3600).'},"filters":{"Game":["Overwatch"]},"getRolloverLiveEvents":true},"timestamp":'.$time.'}';
        $url = 'https://www.splyce.gg/api/api.php?incoming='.urlencode($params);

        try {
            $db = new Lib\mysqldb();
        } catch(Lib\DBException $e) {
            $this->log('Could not connect to database in Splyce crawler', Crawler\StreamChecker::LOG_EMERGENCY);

            return;
        }

        try {
            $source = get_url($url);
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve Splyce Gaming live events from '.$url.'; cURL error, aborting');

            return;
        }

        $json = json_decode($source, true);

        if(!$json || !isset($json['events']) || !is_array($json['events'])) {
            $this->log('Invalid JSON from Splyce');

            return;
        }

        foreach($json['events'] as $live) {
            $game = Crawler\StreamChecker::map_game_name($live['Game']);
            $name = $live['Presenter'].": ".$live['Title'];
            $remote_ID = $live['Event_ID'].$live['EventDate_ID'];
            $event_ID = static::CALENDAR_PREFIX.$remote_ID;

            if(!$game || $live['LastLiveTime'] < ($time - Fuzic\Config::MAX_SESSION_PAUSE)) {
                $this->log('Splyce event '.$name.' is stale: ignoring', Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            if(strpos($live['URL'], 'wiki.teamliquid.net') !== false) {
                $wiki = explode('wiki.teamliquid.net', $live['URL']);
                $wiki = $wiki[1];
                $url = '';
            } else {
                $url = $live['URL'];
                $wiki = '';
            }

            $event = array(
                'game' => $game,
                'id' => $event_ID,
                'name' => $name,
                'wiki' => $wiki,
                'url' => $url,
                'short_name' => Models\Event::get_short_name($name),
                'streams' => array()
            );

            $caster = Crawler\StreamChecker::map_stream_link($live['StreamURL']);
            if(!$caster) {
                $this->log('Splyce stream link '.$live['StreamURL'].' could not be mapped to a stream', Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $this->log('Splyce stream link '.$live['StreamURL'].' linked to '.$name);
            $event['streams'][] = $caster;

            $this->add_event($event_ID, $event);
        }
    }
}