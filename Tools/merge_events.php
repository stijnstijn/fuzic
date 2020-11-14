<?php
/**
 * Manually merge one event into the other
 */
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [event 1 ID] [event 2 ID]\n";
    echo 'Event 1 will be deleted and have its data added to event 2\'s'."\n";
    exit;
}

try {
    $event1 = new Models\Event($argv[1]);
} catch(Lib\ItemNotFoundException $e) {
    $event1 = new Models\Event(['name' => $argv[1]]);
}

try {
    $event2 = new Models\Event($argv[2]);
} catch(Lib\ItemNotFoundException $e) {
    $event2 = new Models\Event(['name' => $argv[2]]);
}

$matches = Models\Match::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$event1->get_ID()]]]);
foreach($matches as $match) {
    $match->set('event', $event2->get_ID());
    $match->update();
}

$links = Models\EventStream::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$event1->get_ID()]]]);
foreach($links as $link) {
    $current_link = Models\EventStream::find(['return' => Lib\Model::RETURN_SINGLE_OBJECT, 'where' => ['event = ? AND stream = ?' => [$event2->get_ID(), $link->get('stream')]]]);
    if($current_link) {
        $current_link->set('start', min($current_link->get('start'), $link->get('start')));
        $current_link->set('end', max($current_link->get('end'), $link->get('end')));
        $current_link->update();
        $link->delete();
    } else {
        $link->set('event', $event2->get_ID());
        $link->update();
    }
}

$event2->set('start', min($event1->get('start'), $event2->get('start')));
$event2->set('end', max($event1->get('end'), $event2->get('end')));
$event2->update();
$event2->recalc_stats();

$db->toggle_debug(true, true);
$event1->delete();