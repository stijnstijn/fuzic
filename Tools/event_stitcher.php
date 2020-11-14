<?php
/** Stitch events really close to each other together, interactively */

namespace Fuzic\Tools;


use Fuzic\Models;
use Fuzic\Lib;

chdir(dirname(__FILE__));
require '../init.php';

$deleted = array(-1);
$processed = array(-1);
while(true) {
    $event = Models\Event::find(['limit' => 1, 'order_by' => ['start' => 'ASC'], 'where' => 'AND id NOT IN ('.implode(',', $processed).')', 'return' => Lib\Model::RETURN_SINGLE_OBJECT]);
    if(!$event) {
        echo "No more events.\n\n";
        break;
    }
    $processed[] = $event->get_ID();

    if(in_array($event->get_ID(), $deleted)) {
        continue;
    }

    $before = Models\Event::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['AND id NOT IN ('.implode(',', $deleted).') AND game = ? AND end < ? AND end > ?' => [$event->get('game'), $event->get('start'), $event->get('start') - 3601]]]);
    if(count($before) == 0) {
        //echo "No nearby events for ".$event->get('name')."\n";
        continue;
    }

    echo "---------------------------------------------------\nEvent ".$event->get_ID().": ".$event->get('name')."\n";
    echo 'Duplicates:'."\n";
    $i = 1;
    foreach($before as $before_event) {
        echo $i.'.           '.$before_event->get('name')." (".time_approx($event->get('start') - $before_event->get('end')).")\n";
        $i += 1;
    }
    echo 'Press enter to ignore, or enter number for merged event title (0 for current event)'."\n";

    $cmd = trim(fgets(STDIN));
    if(!empty($cmd)) {
        $start = $event->get('start');
        foreach($before as $before_event) {
            $start = min($start, $before_event->get('start'));

            $matches = Models\Match::find(['where' => ['event = ?' => $before_event->get_ID()], 'return' => Lib\Model::RETURN_OBJECTS]);
            foreach($matches as $match) {
                $match->set('event', $event->get_ID());
                $match->update();
            }

            $links = Models\EventStream::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$before_event->get_ID()]]]);
            foreach($links as $link) {
                $current_link = Models\EventStream::find(['return' => Lib\Model::RETURN_SINGLE_OBJECT, 'where' => ['event = ? AND stream = ?' => [$event->get_ID(), $link->get('stream')]]]);
                if($current_link) {
                    $current_link->set('start', min($current_link->get('start'), $link->get('start')));
                    $current_link->set('end', max($current_link->get('end'), $link->get('end')));
                    $current_link->update();
                    $link->delete();
                } else {
                    $link->set('event', $event->get_ID());
                    $link->update();
                }
            }

            $before_event->delete();
            $deleted[] = $before_event->get_ID();
        }

        if($cmd !== '0') {
            if(intval($cmd).'' !== $cmd) {
                $name = $cmd;
            } else {
                $name = $before[$cmd]->get('name');
            }
            echo "New name is ".$name."\n";
            $event->set('name', $name);
            $event->set('short_name', Models\Event::get_short_name($name));
            $event->update();
        }

        echo "Updated event ".$event->get_ID()."\n";

        $event->set('start', $start);
    }
}