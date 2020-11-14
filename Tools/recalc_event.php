<?php
/**
 * Recalc audience for specific event
 */
namespace Fuzic\Tools;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 2) {
    echo 'Correct usage: php '.basename(__FILE__)." [event id]\n";
    exit;
}

$event = new Models\Event($argv[1]);

$event->recalc_stats();