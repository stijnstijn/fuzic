<?php
/**
 * Automatically tweet stats when relevant
 */
namespace Fuzic\Upkeep;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require_once '../init.php';

spl_autoload_register(auto_loader('Site/Controller', 'class'));

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);
$twitters = array(
    'sc2' => new \Twitter(Fuzic\Config::TWITTER_CKEY, Fuzic\Config::TWITTER_CSECRET, Fuzic\Config::TWITTER_OAUTH, Fuzic\Config::TWITTER_OAUTHSECRET),
    'heroes' => new \Twitter(Fuzic\Config::TWITTER_CKEY_HEROES, Fuzic\Config::TWITTER_CSECRET_HEROES, Fuzic\Config::TWITTER_OAUTH_HEROES, Fuzic\Config::TWITTER_OAUTHSECRET_HEROES),
    'overwatch' => new \Twitter(Fuzic\Config::TWITTER_CKEY_OVERWATCH, Fuzic\Config::TWITTER_CSECRET_OVERWATCH, Fuzic\Config::TWITTER_OAUTH_OVERWATCH, Fuzic\Config::TWITTER_OAUTHSECRET_OVERWATCH)
);

//tweet at most once every 2 hours
if ($cache->get('event_tweet') < (time() - 7200)) {
    //recent events
    $wayback = time() - 3600;
    $cutoff = time() - (Fuzic\Config::CHECK_DELAY * 5);
    $waywayback = time() - 65000; //about 18 hours

    foreach ($games as $game => $game_info) {
        if (!isset($twitters[$game])) {
            continue;
        }

        $events = $db->fetch_all(
            "SELECT f.twitter, e.franchise, f.name AS franchise_name, e.peak, e.vh, e.average, e.".Models\Event::IDFIELD.", e.short_name, e.name
           FROM ".Models\Event::TABLE." AS e,
                ".Models\Franchise::TABLE." AS f
          WHERE e.end > ".$wayback." AND end < ".$cutoff."
            AND e.franchise != ".Models\Franchise::INDIE_ID."
            AND e.franchise = f.".Models\Franchise::IDFIELD."
            AND e.game = '".$game."'
            AND e.hidden = 0
            AND f.twitter_timestamp < ".$waywayback."
            AND (e.peak > 1000 OR e.average > 1000 OR e.vh > 2000)");

        $formats = array(
            '{peak} people watched {event} earlier today: {url}',
            '{peak} people watched today\'s {event} broadcast: {url}',
            //'{event} just finished with an average audience of {average}! {url}',
            '{event} just wrapped up with {peak} people watching it at peak. {url}',
            //'Average for {event} today: {average}. More stats at {url}',
            'Peak viewers for {event} today: {peak}. More stats at {url}',
            'Viewer numbers for {event} today: {peak} peak viewers, {average} average {url}',
            'Numbers for {event} are in: {peak} peak viewers, {average} average {url}'
        );

        

        shuffle($events);
        $events = array_slice($events, 0, 1);
        $subdomain = ($game == 'sc2') ? 'www' : $game;

        foreach ($events as $event) {
            $chart = Lib\Highcharts::get_event($event[Models\Event::IDFIELD]);
            $twitter_tag = empty($event['twitter']) ? '#'.str_replace(' ', '', $event['franchise_name']) : $event['twitter'];

            $max_key = false;
            $max = 0;
            foreach ($chart['data'] as $key => $viewers) {
                if ($viewers > $max) {
                    $max_key = $key;
                    $max = $viewers;
                }
            }

            if(!isset($chart['labels'][$max_key])) {
                continue;
            }

            $peak_time = $chart['labels'][$max_key];
            $match = Models\Match::find(['where' => [
                'event = ? AND start <= ? AND end >= ?' => [$event[Models\Event::IDFIELD], $peak_time, $peak_time]
            ],
                                         'return' => Lib\Model::RETURN_SINGLE_OBJECT
            ]);
            if ($match) {
                $players = explode(' vs ', $match->get('match'));
                if (!empty($match->get('player1'))) {
                    $player1 = new Models\Stream($match->get('player1'));
                    $player1 = !empty($player1->get('twitter')) ? '@'.$player1->get('twitter') : $player1->get('real_name');
                } else {
                    $player1 = $players[0];
                }
                if (!empty($match->get('player2'))) {
                    $player2 = new Models\Stream($match->get('player2'));
                    $player2 = !empty($player2->get('twitter')) ? '@'.$player2->get('twitter') : $player2->get('real_name');
                } else {
                    $player2 = $players[1];
                }
                if (rand(0, 1) > 0.5) {
                    $tweet = 'Peak viewers for '.$twitter_tag.' today: '.approx($event['peak']).' during '.$player1.' vs '.$player2.'. More: ';
                } else {
                    $tweet = 'The '.$twitter_tag.' broadcast today peaked at '.approx($event['peak']).' viewers during '.$player1.' vs '.$player2.'. More at ';
                }
                if (strlen($tweet) < 230) {
                    //generate stats image to attach to tweet
                    ob_start();
                    $controller = new Fuzic\Site\EventController(array('mode' => 'graph', 'id' => $event[Models\Event::IDFIELD], 'ajax' => false, null, $db));
                    $image = ob_get_clean();
                    $temp = fopen('temp.png', 'w');
                    fwrite($temp, $image);
                    fclose($temp);

                    $twitters[$game]->send($tweet.' https://'.$subdomain.'.fuzic.nl'.Models\Event::build_url($event), ['temp.png']);
                    unlink('temp.png');
                    $franchise = new Models\Franchise($event['franchise']);
                    $franchise->set('twitter_timestamp', time());
                    $franchise->update();
                    continue;
                }
            }

            $format = $formats[array_rand($formats)];
            $twitter_tag = (substr($twitter_tag, 0, 1) == '@' && strpos($format, '{event}') === 0) ? '.'.$twitter_tag : $twitter_tag;
            $tweet = str_replace(
                ['{event}', '{url}', '{peak}', '{average}'],
                [$twitter_tag, 'https://'.$subdomain.'.fuzic.nl'.Models\Event::build_url($event), approx($event['peak']), approx($event['average'])],
                $format);

            try {
                $twitters[$game]->send($tweet);
            } catch (\TwitterException $e) {
            }

            $franchise = new Models\Franchise($event['franchise']);
            $franchise->set('twitter_timestamp', time());
            $franchise->update();
        }

        if (count($events) > 0) {
            $cache->set('event_tweet', time());
        }
    }
}