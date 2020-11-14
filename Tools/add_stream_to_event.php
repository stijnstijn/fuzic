<?php
/**
 * Manually create a link between an event and a stream
 */
namespace Fuzic\Tools;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [stream name] [event id] [--silent]\n";
    echo '--silent toggles debug output'.PHP_EOL;
    exit;
}

$silent = isset($argv[3]) && trim($argv[3]) == '--silent';

$stream = new Models\Stream($argv[1]);
$event = new Models\Event($argv[2]);

$exists = Models\EventStream::find([
    'where' => 'stream = '.$db->escape($stream->get_ID()).' AND event = '.$db->escape($event->get_ID())
]);

if ($exists) {
    if(!$silent) {
        echo 'Link already exists.'."\n";
    }
    exit;
}

$session = Models\Session::find([
    'where' => [
        'stream = ? AND ((start > ? AND start < ?) OR (end > ? AND end < ?) OR (start < ? AND end > ?))' => [$stream->get_ID(), $event->get('start'), $event->get('end'), $event->get('start'), $event->get('end'), $event->get('start'), $event->get('start')]
    ],
    'return' => 'single'
]);

$datapoints = Models\Datapoint::find([
    'where' => [
        'stream = ? AND `time` >= ? AND `time` <= ?' => [$stream->get_ID(), $event->get('start'), $event->get('end')]
    ],
    'order_by' => ['`time`' => 'ASC']
]);

if (!$session || empty($session)) {
    if (!$datapoints) {
        if(!$silent) {
            echo 'No matching sessions found'."\n";
        }
        exit;
    }
    reset($datapoints);
    $first = reset($datapoints);
    $last = end($datapoints);
    $start = $first['time'];
    $end = $last['time'];

} else {
    $start = ($session['start'] > $event->get('start')) ? $session['start'] : $event->get('start');
    $end = ($session['end'] < $event->get('end')) ? $session['end'] : $event->get('end');
}

Models\EventStream::create([
    'event' => $event->get_ID(),
    'stream' => $stream->get_ID(),
    'start' => $start,
    'end' => $end
]);

$event->recalc_stats();
echo 'Stream '.$stream->get_ID().' added to event '.$event->get('name').PHP_EOL;