<?php
/**
 * Remove outdated or irrelevant database rows
 */
namespace Fuzic\Upkeep;

use Fuzic;
use Fuzic\Models;
use Fuzic\Lib;


chdir(dirname(__FILE__));
require_once '../init.php';

$cutoff = time() - (86400 * 5);
$cutoff2 = time() - (86400 * 31);

//make streams that should be notable, notable
$eligible = Models\Stream::find(['where' => ['notable = 0 AND last_seen > ?' => [$cutoff]]]);
$db->start_transaction();
foreach($eligible as $stream) {
    if($stream['tl_featured'] != '0' || !empty($stream['wiki'])) {
        $db->query("UPDATE ".Models\Stream::TABLE." SET notable = 1 WHERE ".$db->escape_identifier(Models\Stream::IDFIELD)." = ".$db->escape($stream[Models\Stream::IDFIELD]));
        continue;
    }
    if(Models\Session::find(['return' => Lib\Model::RETURN_AMOUNT, 'where' => ['average > 500 AND end > ? AND stream = ?' => [$cutoff2, $stream[Models\Stream::IDFIELD]]]]) > 5) {
        $db->query("UPDATE ".Models\Stream::TABLE." SET notable = 1 WHERE ".$db->escape_identifier(Models\Stream::IDFIELD)." = ".$db->escape($stream[Models\Stream::IDFIELD]));
        continue;
    }
}
$db->commit();

/*
$db->query("DELETE FROM ".Models\Datapoint::TABLE." WHERE stream NOT IN (SELECT DISTINCT ".Models\Stream::IDFIELD." FROM ".Models\Stream::TABLE.")");
$db->query("DELETE FROM ".Models\Session::TABLE." WHERE stream NOT IN (SELECT DISTINCT ".Models\Stream::IDFIELD." FROM ".Models\Stream::TABLE.")");
$db->query("DELETE FROM ".Models\EventStream::TABLE." WHERE stream NOT IN (SELECT DISTINCT ".Models\Stream::IDFIELD." FROM ".Models\Stream::TABLE.")");

$orphans = $db->fetch_fields("
    SELECT ".Models\Stream::IDFIELD."
      FROM ".Models\Stream::TABLE."
     WHERE provider = 'twitch'
       AND ".Models\Stream::IDFIELD." NOT IN (SELECT DISTINCT stream FROM ".Models\Session::TABLE.")
       AND ".Models\Stream::IDFIELD." NOT IN (SELECT DISTINCT stream FROM ".Models\Datapoint::TABLE.")
       AND ".Models\Stream::IDFIELD." NOT IN (SELECT DISTINCT stream FROM ".Models\EventStream::TABLE.")
       AND tl_featured != 1
       AND last_seen < ".$cutoff);

foreach ($orphans as $stream_ID) {
    $stream = new Models\Stream($stream_ID);
    $stream->delete();
}

$db->query("UPDATE ".Models\Stream::TABLE." SET real_name = twitch_name WHERE real_name = ''");

$db->query("DELETE FROM ".Models\Team::TABLE." WHERE ".Models\Team::IDFIELD." NOT IN (SELECT DISTINCT team FROM ".Models\Stream::TABLE.")");

$orphans = $db->fetch_all("SELECT * FROM ".Models\Franchise::TABLE." WHERE ".Models\Franchise::IDFIELD." NOT IN ( SELECT DISTINCT franchise FROM ".Models\Event::TABLE.")");
foreach ($orphans as $franchise) {
    $franchise = new Models\Franchise($franchise[Models\Franchise::IDFIELD]);
    $franchise->delete();
}
*/

//Detect caster streams and mark them accordingly
//only check streams seen in the last 5 days
$streams = Models\Stream::find([
    'constraint' => 'player = 1 AND last_seen > '.$cutoff,
    'return' => 'object'
]);

foreach ($streams as $stream) {
    $sessions = Models\Session::find([
        'return' => 'count',
        'stream' => $stream->get_ID()
    ]);

    //if a stream has less than 5 sessions, there's not enough data to go off
    if ($sessions < 5) {
        continue;
    }

    $events = Models\EventStream::find([
        'return' => 'count',
        'stream' => $stream->get_ID()
    ]);

    //if more than 50% of the stream's sessions is events, it's reasonable to call it an event stream
    if (($events / $sessions) > 0.5) {
        $stream->set('player', 0);
        $stream->update();
    }
}

//delete old events with no streams
$events = Models\Event::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['end < ?' => [time() - 86400]]]);
foreach($events as $event) {
    $streams = $event->get_streams();
    if(count($streams) == 0) {
        $event->delete();
    }
}

/**
 * Automatically update twitter profile colors and image depending on site CSS
 */
$twitters = array(
    'sc2' => new \Twitter(Fuzic\Config::TWITTER_CKEY, Fuzic\Config::TWITTER_CSECRET, Fuzic\Config::TWITTER_OAUTH, Fuzic\Config::TWITTER_OAUTHSECRET),
    'heroes' => new \Twitter(Fuzic\Config::TWITTER_CKEY_HEROES, Fuzic\Config::TWITTER_CSECRET_HEROES, Fuzic\Config::TWITTER_OAUTH_HEROES, Fuzic\Config::TWITTER_OAUTHSECRET_HEROES),
    'overwatch' => new \Twitter(Fuzic\Config::TWITTER_CKEY_OVERWATCH, Fuzic\Config::TWITTER_CSECRET_OVERWATCH, Fuzic\Config::TWITTER_OAUTH_OVERWATCH, Fuzic\Config::TWITTER_OAUTHSECRET_OVERWATCH)
);

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);


foreach($twitters as $game => $twitter) {
    $color = $games[$game]['color'];
    $image = fuzic_twitter_icon(get_color_values($color));
    ob_start();
    imagepng($image);
    $image = ob_get_clean();
    $twitter->request('account/update_profile', 'POST', ['profile_link_color' => $color]);
    $twitter->request('account/update_profile_image', 'POST', ['image' => base64_encode($image)]);
}