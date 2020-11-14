<?php
namespace Fuzic\Crawler\Event;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class TeamliquidheroesChecker extends EventCalendarFetcher {
    const CALENDAR_ID = 'teamliquidheroes';
    const CALENDAR_PREFIX = 'hots-';

    public function run() {
        $url = 'http://www.teamliquid.net/advertising/';

        try {
            $html_source = get_url($url);
        } catch(\ErrorException $e) {
            $this->log('Could not retrieve URL '.$url.'; cURL error, aborting');

            return;
        }

        if(!$html_source || empty($html_source)) {
            $this->log('TeamLiquid SC2/Heroes event calendar unavailable.', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        $calendar = explode('<td class="calendar_today">', $html_source);
        if(count($calendar) == 1) {
            return;
        }

        $calendar = explode('<div id="streams_div">', $calendar[1]);

        $events = preg_split('/<div class="ev_(wiki|no)link">/siU', $calendar[0]);
        array_pop($events);
        array_shift($events);


        foreach($events as $event) {
            preg_match('/<a class="rightmenu" title="([^"]+) \(([^,]+), [0-9:]+\)" href="[^#]+#event_([0-9]+)">([^<]+)/si', $event, $details);
            preg_match('/<a target="_blank" href="http:\/\/wiki.teamliquid.net\/([^"]+)"/siU', $event, $wiki);

            //game needs to be non-sc2 and have 'heroes' in title
            if($details[2] != 'Other' || stripos($details[1], 'heroes') === false) {
                continue;
            }

            $remote_ID = $details[3];
            $id = self::CALENDAR_PREFIX.$remote_ID;
            $name = $details[1];

            //put all data together
            $event = array(
                'wiki' => (isset($wiki[1]) ? $wiki[1] : ''),
                'id' => $id,
                'remote_ID' => $remote_ID,
                'game' => Crawler\StreamChecker::map_game_name('heroes of the storm'),
                'name' => $name,
                'short_name' => $name,
                'streams' => array()
            );

            $this->add_event($id, $event);
        }
    }
}