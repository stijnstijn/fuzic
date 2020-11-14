<?php
namespace Fuzic;

use Fuzic\Lib;


ini_set('max_execution_time', 0);
chdir(dirname(__FILE__));
require_once '../init.php';

define('DAYS', 90);

$start = intval(file_get_contents('rerank'));

$h = fopen('rerank', 'w');
fwrite($h, $start + (86400 * DAYS));
fclose($h);

echo 'Recalculating from '.date('r', $start).' til '.date('r', $start + (86400 * DAYS));

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);
foreach ($games as $game => $game_info) {
    $ranking = new Lib\Ranking($start, time(), 'stream', false, $game);
    $ranking->rank_since($start, $start + (86400 * DAYS), true);
}