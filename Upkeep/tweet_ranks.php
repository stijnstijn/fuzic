<?php
/**
 * Automatically tweet stats when relevant
 */
namespace Fuzic\Upkeep;

use Fuzic;
use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

//recent events
if(date('W') == 1) {
    $year = date('Y') - 1;
    $week = date('W', mktime(23, 59, 59, 12, 31, $year));
} else {
    $week = date('W') - 1;
    $year = date('Y');
}

$twitters = array(
    'sc2' => new \Twitter(Fuzic\Config::TWITTER_CKEY, Fuzic\Config::TWITTER_CSECRET, Fuzic\Config::TWITTER_OAUTH, Fuzic\Config::TWITTER_OAUTHSECRET),
    'heroes' => new \Twitter(Fuzic\Config::TWITTER_CKEY_HEROES, Fuzic\Config::TWITTER_CSECRET_HEROES, Fuzic\Config::TWITTER_OAUTH_HEROES, Fuzic\Config::TWITTER_OAUTHSECRET_HEROES),
    'overwatch' => new \Twitter(Fuzic\Config::TWITTER_CKEY_OVERWATCH, Fuzic\Config::TWITTER_CSECRET_OVERWATCH, Fuzic\Config::TWITTER_OAUTH_OVERWATCH, Fuzic\Config::TWITTER_OAUTHSECRET_OVERWATCH)
);

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);

foreach ($games as $game => $game_info) {
    if (!isset($twitters[$game])) {
        continue;
    }

    $streams = $db->fetch_all("
    SELECT s.twitter, s.real_name
      FROM ".Models\Stream::TABLE." AS s,
           ranking_week AS r
     WHERE r.week = ".$week."
       AND r.year = ".$year."
       AND r.game = '".$game."'
       AND s.player = 1
       AND r.stream = s.".Models\Stream::IDFIELD."
     ORDER BY rank ASC
     LIMIT 3");

    $tweet = 'Week '.$week.', '.$year.' top '.$game_info['twitter'].' player streams: ';
    for ($i = 0; $i < count($streams); $i += 1) {
        $link = empty($streams[$i]['twitter']) ? $streams[$i]['real_name'] : '@'.$streams[$i]['twitter'];
        $tweet .= ($i + 1).'. '.$link.', ';
    }

    $subdomain = ($game == 'sc2') ? 'www' : $game;

    $tweet = substr($tweet, 0, -2).'. More: https://'.$subdomain.'.fuzic.nl/rankings/'.$year.'/week/'.$week.'/players/'."\n";

    $twitters[$game]->send($tweet);
}