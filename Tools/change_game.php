<?php
/**
 * Rename franchises that lack a full name via commandline
 */
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [stream name] [game]\n";
    exit;
}

$wayback = time() - (86400 * 2);

$streams = Models\Stream::find([
    'where' => [
        'last_seen > ?' => [$wayback],
        "tl_id != ''"
    ],
    'return' => Lib\Model::RETURN_OBJECTS
]);

foreach ($streams as $stream) {
    $test_hearth = get_url('http://www.liquidhearth.com/stream/'.urlencode($stream->get('tl_id')));
    if (strpos($test_hearth, 'This channel does not exist.') === false) {
        $db->query("UPDATE ".Models\Session::TABLE." SET game = 'hearthstone' WHERE end > ".$wayback." AND stream = '".$db->escape($stream->get_ID())."'");
        $db->query("UPDATE ".Models\Datapoint::TABLE." SET game = 'hearthstone' WHERE time > ".$wayback." AND stream = '".$db->escape($stream->get_ID())."'");
    }
    sleep(1);
}  