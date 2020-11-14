<?php
namespace Fuzic\Crawler\Event;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class Teamliquidsc2Checker extends EventCalendarFetcher {
    const CALENDAR_ID = 'teamliquidsc2';
    const CALENDAR_PREFIX = '';

    public function run() {
        $url = 'http://www.teamliquid.net/calendar/xml/calendar.xml';

        $cache_path = dirname(dirname(dirname(__FILE__))).'/cache/tlsc2calendar.cache';
        $invalidate = 6 * 50;

        try {
            if(!file_exists($cache_path) || filemtime($cache_path) < (time() - $invalidate)) {
                $xml_source = get_url($url);
                file_put_contents($cache_path, $xml_source);
            } else {
                $this->log('Using cached version of calendar '.self::CALENDAR_ID);
                $xml_source = file_get_contents($cache_path);
            }
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve URL '.$url.'; cURL error, aborting');

            return;
        }

        if(!$xml_source || empty($xml_source)) {
            $this->log('TeamLiquid StarCraft 2 event calendar unavailable.', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        try {
            $db = new Lib\mysqldb();
        } catch(Lib\DBException $e) {
            $this->log('Could not connect to database in Splyce crawler', Crawler\StreamChecker::LOG_EMERGENCY);

            return;
        }

        $liquid_data = new \DOMDocument();
        $liquid_data->loadXML($xml_source);

        foreach($liquid_data->getElementsByTagName('event') as $event_xml) {
            $over = $event_xml->getAttribute('over');
            if($over == '1') {
                continue;
            }

            $game = Crawler\StreamChecker::map_game_name($event_xml->getElementsByTagName('type')->item(0)->nodeValue);
            if(!$game) {
                continue;
            }

            $name = $event_xml->getElementsByTagName('title')->item(0)->nodeValue;
            $desc = $event_xml->getElementsByTagName('description')->item(0)->nodeValue;

            $remote_ID = $event_xml->getElementsByTagName('event-id')->item(0)->nodeValue;
            $internal_ID = static::CALENDAR_PREFIX.$remote_ID;

            //this needs to be set or there will be complaints if we're using threaded checkers
            date_default_timezone_set('Europe/London');

            $day = $event_xml->parentNode;
            $month = $day->parentNode;
            $time = mktime($event_xml->getAttribute('hour'), $event_xml->getAttribute('minute'), 0, $month->getAttribute('num'), $day->getAttribute('num'), $month->getAttribute('year')) - (9 * 3600); //Korean time, for some reason
            if(time() < $time) { //if event start is in the future
                continue;
            }

            //put all data together
            $event = array(
                'wiki' => (($event_xml->getElementsByTagName('liquipedia-url')->length > 0) ? $event_xml->getElementsByTagName('liquipedia-url')->item(0)->nodeValue : ''),
                'id' => $internal_ID,
                'remote_ID' => $remote_ID,
                'game' => $game,
                'name' => $name.(!empty($desc) ? ': '.$desc : ''),
                'short_name' => (empty($event_xml->getElementsByTagName('short-title')->item(0)->nodeValue) ? $name : $event_xml->getElementsByTagName('short-title')->item(0)->nodeValue),
                'streams' => array()
            );
            $this->add_event($internal_ID, $event);
        }

        //matches
        $url = 'http://www.teamliquid.net/contactus.php';
        try {
            $source = get_url($url);
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve URL '.$url.'; cURL error, aborting');

            return;
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($source);

        $events = getElementsByClassName($dom, 'ev-live');

        foreach($events as $event) {
            $eventDOM = new \DOMDocument();
            $eventDOM->appendChild($eventDOM->importNode($event, true));
            $eventHTML = $eventDOM->saveHTML();

            preg_match('/data-event-id="([^"]+)"/siU', $eventHTML, $id);

            if(empty($id[1])) {
                continue;
            } else {
                $event_ID = static::CALENDAR_PREFIX.$id[1];
            }

            $matches = getElementsByClassName($event, 'ev-match');
            $matches = $matches->item(0)->getElementsByTagName('div');
            foreach($matches as $match) {
                if(strpos($match->nodeValue, 'LIVE') !== false || $matches->length == 1) {
                    $players = getElementsByClassName($match, 'ev-player');
                    $player1 = $players->item(0)->nodeValue;
                    $player2 = $players->item(1)->nodeValue;
                    if($player1 == 'TBD' || $player2 == 'TBD') {
                        continue;
                    }
                    $title = $player1.' vs '.$player2;

                    $player1_obj = Models\Stream::find(['where' => ['real_name = ?' => [$player1]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT], $db);
                    $player2_obj = Models\Stream::find(['where' => ['real_name = ?' => [$player2]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT], $db);

                    $match = array(
                        'event' => $event_ID,
                        'match' => $title,
                        'player1' => ($player1_obj ? $player1_obj->get_ID() : 0),
                        'player2' => ($player2_obj ? $player2_obj->get_ID() : 0)
                    );

                    $this->add_match($match);
                }
            }
        }

        $db->close();
        $db = NULL;
    }
}