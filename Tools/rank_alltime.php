<?php
/**
 * Calculate all-time rankings
 */
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


ini_set('max_execution_time', 0);
chdir(dirname(__FILE__));
require_once '../init.php';

$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);

//calculate rankings for the past month (31 days, really)
$now = time();
$month_ago = $now - (86400 * 31);

foreach ($games as $game => $game_info) {
    //stream rankings
    $ranking = new Lib\Ranking($month_ago, $now, 'stream', false, $game);
    $ranking->rank_alltime();

    //event rankings
    $ranking = new Lib\EventRanking($month_ago, $now, $game);
    $ranking->rank_alltime();
}

//hide events with no (recorded) viewers
$no_data = Models\Event::find([
    'constraint' => 'start < '.$month_ago.' AND peak = 0',
    'return' => 'object'
]);

foreach ($no_data as $event) {
    $event->set('hidden', 1);
    $event->update();
}