<?php
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;
use Fuzic\Config;

chdir(dirname(__FILE__));
require '../init.php';

$streams = $db->fetch_all("
    SELECT * FROM ".$db->escape_identifier(Models\EventStream::TABLE)." AS s
        LEFT JOIN ".$db->escape_identifier(Models\Event::TABLE)." AS e
               ON e.".$db->escape_identifier(Models\Event::IDFIELD)." = s.event
         ORDER BY e.franchise ASC,
                  SUBSTRING(e.name, 0, 16) ASC,
                  e.start ASC
");

$deleted = [];

foreach($streams as $stream) {
    if(in_array($stream['event'], $deleted)) {
        continue;
    }

    $overlap = Models\EventStream::find([
        'where' => [$db->escape_identifier(Models\EventStream::IDFIELD).' != ? AND event != ? AND stream = ? AND ((start > ? AND start < ?) OR (end > ? AND end < ?))' => [
            $stream[Models\EventStream::IDFIELD], $stream['event'], $stream['stream'], $stream['start'], $stream['end'], $stream['start'], $stream['end']
        ]]
    ]);

    if(!$overlap) {
        continue;
    }

    try {
        $event = new Models\Event($stream['event']);
    } catch(Lib\ItemNotFoundException $e) {
        $link = new Models\EventStream($stream[Models\EventStream::IDFIELD]);
        $link->delete();
        echo 'Deleting orphan stream link '.$stream[Models\EventStream::IDFIELD].PHP_EOL;
        continue;
    }

    foreach($overlap as $overlap_stream) {
        try {
            $overlap_event = new Models\Event($overlap_stream['event']);
        } catch(Lib\ItemNotFoundException $e) {
            $link = new Models\EventStream($overlap_stream[Models\EventStream::IDFIELD]);
            $link->delete();
            echo 'Deleting orphan stream link '.$overlap_stream[Models\EventStream::IDFIELD].PHP_EOL;
            continue;
        }
        if($event->get('game') != $overlap_event->get('game')) {
            continue;
        }

        $start = max($stream['start'], $overlap_stream['start']);
        $end = min($stream['end'], $overlap_stream['end']);
        $length = $end - $start;

        if(($length >= ($stream['end'] - $stream['start']) * 0.8) &&
            ($length >= ($overlap_stream['end'] - $overlap_stream['start']) * 0.8)) {
            //overlap for at least 80% of stream that is being compared
            if(substr($event->get('tl_id'), 0, 1) != 'a' && substr($overlap_event->get('tl_id'), 0, 1) != 'a') {
                //require at least one abios-sourced event
                continue;
            }

            echo 'Merging '.$overlap_event->get('name').' ('.$overlap_event->get_ID().') into '.$event->get('name').' ('.$event->get_ID().')';

            if((substr($overlap_event->get('tl_id'), 0, 1) == 'a' && substr($event->get('tl_id'), 0, 1) != 'a') || (strpos($overlap_event->get('name'), 'HGC') !== false && strpos($event->get('name'), 'HGC') === false) || strlen($overlap_event->get('name')) > strlen($event->get('name'))) {
                //prioritize abios-based event names since they're usually more accurate
                $event->set('name', $overlap_event->get('name'));
                $event->set('franchise', $overlap_event->get('franchise'));
                echo ' and changing name';
                $event->update();
            }

            echo PHP_EOL;

            $deleted[] = $overlap_event->get_ID();
            $event->absorb($overlap_event->get_ID());
        }
    }
}