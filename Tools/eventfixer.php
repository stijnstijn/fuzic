<?php
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


require '../init.php';
$eventstreams = Models\EventStream::find([
    'where' => ['end = start'],
    'return' => Lib\Model::RETURN_OBJECTS
]);

$db->start_transaction();

foreach ($eventstreams as $eventstream) {
    $event = new Models\Event($eventstream->get('event'));
    $sessions = Models\Session::find([
        'where' => [
            'stream = ?' => [$eventstream->get('stream')],
            '((start > ? AND start < ?) OR (end > ? AND end < ?))' => [$event->get('start'), $event->get('end'), $event->get('start'), $event->get('end'), 'relation' => 'AND']
        ]
    ]);
    $end = 0;
    $start = 1000000000000000000;

    if (count($sessions) == 0) {
        echo "Insufficient data (no session).\n";
        continue;
    }

    foreach ($sessions as $session) {
        if ($session['start'] < $start) {
            $start = ($session['start'] > $event->get('start')) ? $session['start'] : $event->get('start');
        }
        if ($session['end'] > $end) {
            $end = ($session['end'] > $event->get('end')) ? $session['end'] : $event->get('end');
        }
    }

    if ($end == 0 || $start == 1000000000000000000) {
        echo "Insufficient data.\n";
        continue;
    }

    echo "Event stream ".$eventstream->get(Models\EventStream::IDFIELD)." now from ".$start." to ".$end." (".count($sessions)." sessions processed)\n";
    $eventstream->set('start', $start);
    $eventstream->set('end', $end);
    $eventstream->update();
}

$db->commit();