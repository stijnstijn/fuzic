<?php
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;
use Fuzic\Config;

chdir(dirname(__FILE__));
require '../init.php';

$deleted = [];

$links = Models\EventStream::find(['where' => ['end < ?' => [time() - 86400]], 'order' => 'asc', 'order_by' => 'auto']);
$total = 0;
foreach($links as $link) {
    if(isset($deleted[$link[Models\EventStream::IDFIELD]])) {
        continue;
    }

    $duplicates = Models\EventStream::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => [
        $db->escape_identifier(Models\EventStream::IDFIELD).' != ? AND event = ? AND stream = ? AND start = ? AND end = ?' => [
            $link[Models\EventStream::IDFIELD],
            $link['event'],
            $link['stream'],
            $link['start'],
            $link['end'],
        ]
    ]]);

    if(count($duplicates) > 0) {
        //echo 'Link '.$link[Models\EventStream::IDFIELD].' has '.count($duplicates).' duplicates'.PHP_EOL;
        foreach($duplicates as $duplicate) {
            $deleted[$duplicate->get_ID()] = true;
            //echo 'Deleting '.$duplicate->get_ID().PHP_EOL;
            $duplicate->delete();
        }
        $total += count($duplicates);
    }
}

if($total > 0) {
    echo 'Total '.$total.' duplicates deleted'.PHP_EOL;
}