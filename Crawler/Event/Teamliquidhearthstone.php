<?php
namespace Fuzic\Crawler\Event;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class TeamliquidhearthstoneChecker extends EventCalendarFetcher {
    const CALENDAR_ID = 'teamliquidhearthstone';
    const CALENDAR_PREFIX = 'h-';

    public function run() {
        $url = 'http://www.liquidhearth.com/calendar/xml/calendar.xml';

        try {
            $xml_source = get_url($url);
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
        $xml_calendar = array();

        foreach($liquid_data->getElementsByTagName('event') as $event_xml) {
            $name = $event_xml->getElementsByTagName('type')->item(0)->nodeValue;
            $tl_ID = $event_xml->getElementsByTagName('event-id')->item(0)->nodeValue;
            $xml_calendar[$tl_ID] = $name;
        }

        //matches
        $url = 'http://www.liquidhearth.com/contactus.php';
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

            if(empty($id[1]) || !isset($xml_calendar[$id[1]])) {
                continue;
            } else {
                $remote_ID = $id[1];
                $event_ID = static::CALENDAR_PREFIX.$remote_ID;
                $franchise = $xml_calendar[$id[1]];
            }

            $stage = getElementsByClassName($eventDOM, 'ev-stage');
            if($stage->length > 0) {
                $stage = $stage->item(0)->nodeValue;
            }

            $wiki = getElementsByClassName($eventDOM, 'wikibtn');
            if($wiki->length > 0) {
                $wiki = $wiki->item(0)->attributes->getNamedItem('href')->nodeValue;
                $wiki = explode('wiki.teamliquid.net/', $wiki);
                $wiki = $wiki[1];
            } else {
                $wiki = '';
            }

            $event = array(
                'id' => $event_ID,
                'remote_ID' => $remote_ID,
                'wiki' => ($wiki ? $wiki : ''),
                'game' => Crawler\StreamChecker::map_game_name('hearthstone'),
                'name' => ($stage ? $franchise.': '.$stage : $franchise),
                'short_name' => $franchise,
                'streams' => array()
            );

            $this->add_event($event_ID, $event);

            $matches = getElementsByClassName($eventDOM, 'ev-match');
            $matches = $matches->item(0)->getElementsByTagName('div');
            foreach($matches as $match) {
                if(strpos($match->nodeValue, 'LIVE') !== false || $matches->length == 1) {
                    preg_match('/(.+) vs (.+)LIVE!$/siU', $match->nodeValue, $players);
                    if(!isset($players[1]) || !isset($players[2]) || empty($players[1]) || empty($players[2])) {
                        continue;
                    }

                    $player1 = $players[1];
                    $player2 = $players[2];
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
    }
}