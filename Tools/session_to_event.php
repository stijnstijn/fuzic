<?php
/**
 * Create an event from a session ID (with that session being the timespan + viewership)
 */
namespace Fuzic\Tools;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [session id] [event name]\n";
    exit;
}

$session = new Models\Session($argv[1]);

$start = $session->get('start');
$end = $session->get('end');

array_shift($argv);
array_shift($argv);
$name = implode(' ', $argv)."\n";

$event = Models\Event::create([
    'start' => $start,
    'end' => $end,
    'tl_id' => 0,
    'short_name' => Models\Event::get_short_name($name),
    'name' => $name
]);

Models\EventStream::create([
    'event' => $event->get_ID(),
    'stream' => $session->get('stream'),
    'start' => $start,
    'end' => $end
]);

echo "Event created. Don't forget to run fix_franchises.php!\n";