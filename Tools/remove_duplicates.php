<?php
/**
 * Delete duplicate EventStream records
 *
 * @package Fuzic
 */
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


require '../init.php';

$links = Models\EventStream::find(['key' => 'linkid', 'order' => ['event' => 'ASC', Models\EventStream::IDFIELD => 'ASC']]);

echo count($links)." stream links.\n";

foreach ($links as $link) {
    $double = Models\EventStream::find(['where' => [
        'event = ? AND stream = ? AND start = ? AND end = ? AND '.$db->escape_identifier(Models\EventStream::IDFIELD).' > ?' => [
            $link['event'],
            $link['stream'],
            $link['start'],
            $link['end'],
            $link[Models\EventStream::IDFIELD]
        ]
    ], 'return' => Lib\Model::RETURN_OBJECTS
    ]);

    $deleted = array();
    $count = 0;

    foreach ($double as $i => $dingle) {
        if (isset($links[$dingle->get(Models\EventStream::IDFIELD)])) {
            unset($links[$dingle->get(Models\EventStream::IDFIELD)]);
        }

        $count += 1;
        $deleted[$dingle->get(Models\EventStream::IDFIELD)] = true;

        $dingle->delete();
    }

    if (isset($count) && $count > 0) {
        echo "-----------------------------------\n";
        echo "Link ".$link[Models\EventStream::IDFIELD]."\n";
        echo "Deleting ".implode(', ', array_keys($deleted))."\n";
        echo "Duplicates: ".$count."\n";
    }

    $processed[$link[Models\EventStream::IDFIELD]] = true;
}