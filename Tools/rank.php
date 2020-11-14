<?php
/**
 * Calculate rankings for a week or month
 */
namespace Fuzic\Tools;

use Fuzic\Lib;


chdir(dirname(__FILE__));
require '../init.php';

if (count($argv) < 4) {
    echo 'usage: rank.php year month game'."\n";
} else {
    $start = period_start('month', intval($argv[2]), intval($argv[1]));
    $end = period_end('month', intval($argv[2]), intval($argv[1]));
    $ranking = new Lib\Ranking($start, $end, 'stream', false, $argv[3]);
    $ranking->rank();
}