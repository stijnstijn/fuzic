<?php
/**
 * Manually create a link between all events in a franchise and a stream
 */
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;

chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 3) {
    echo 'Correct usage: php '.basename(__FILE__)." [stream name] [franchise id or tag]\n";
    exit;
}

if(is_numeric($argv[2])) {
    $events = Models\Event::find(['where' => ['franchise = ?' => [$argv[2]]]]);
} else {
    $franchise = Models\Franchise::find(['where' => ['tag = ?' => [$argv[2]]], 'return' => Lib\Model::RETURN_SINGLE_OBJECT]);
    if(!$franchise) {
        echo 'Franchise not found.'.PHP_EOL;
        exit;
    }
    $events = Models\Event::find(['where' => ['franchise = ?' => [$franchise->get_ID()]]]);
}

try {
    $stream = new Models\Stream($argv[1]);
} catch(Lib\ItemNotFoundException $e) {
    echo 'Stream not found.'.PHP_EOL;
    exit;
}

$echo = '';
foreach($events as $event) {
    $output = [];
    exec('php add_stream_to_event.php '.$argv[1].' '.$event['id'].' --silent', $output);
    $echo .= implode(PHP_EOL, $output).PHP_EOL;
}
echo trim($echo);