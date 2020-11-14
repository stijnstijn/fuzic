<?php
/**
 * Determines franchises for all events based on their title
 *
 * Use after `Event::get_franchise()` has changed
 */
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require_once '../init.php';

if (count($argv) < 2) {
    $events = Models\Event::find(['order_by' => 'name', 'return' => 'object']);
} else {
    $event = new Models\Event($argv[2]);
    $events = array($event);
}

$db->start_transaction();

$indie = count($events);
$mapping = json_decode(file_get_contents('../Upkeep/events_mapping.json'), true);
foreach ($events as $event) {
    $franchise = Models\Event::get_franchise($event->get('name'));
    if ($franchise) {
        $franchise = new Models\Franchise($franchise);
        echo 'Franchise for '.$event->get('name').': '.$franchise->get('name')."\n";
        $event->set('franchise', $franchise->get_ID());
        $indie -= 1;
    } else {
        echo 'No franchise found for '.$event->get('name')."\n";
        $event->set('franchise', Models\Franchise::INDIE_ID);
    }
    $event->update();
}

$db->commit();

echo '------------------------------------'."\n";
echo "Events without franchises: ".$indie."\n";