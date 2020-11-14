<?php
/*
 * Gets all players from Aligulac, save to file (for OCRing)
 */
namespace Fuzic\Tools;
chdir(dirname(__FILE__));
require '../init.php';

use Fuzic\Lib;
use Fuzic\Config;
use Fuzic\Models;
use Fuzic\Crawler;

$apikey = Config::ALIGULAC_API_KEY;

$next = '/api/v1/player/?apikey='.$apikey.'&format=json&limit=250';
$players = fopen('players-raw-sc2.txt', 'w');
$i = 0;

while(true) {
    if($i % 3 == 0) {
        sleep(1);
    }
    $data = get_url('http://aligulac.com'.$next);
    $data = json_decode($data, true);

    foreach($data['objects'] as $player) {
        $tag = $player['tag'];
        if(preg_replace('/[^a-zA-Z0-9]/siU', '', $tag) !== '') {
            fwrite($players, $tag."\n");
        } else {
            echo 'Skipping '.$tag."\n";
        }
    }

    if(isset($data['meta']) && isset($data['meta']['next']) && !empty($data['meta']['next'])) {
        $next = $data['meta']['next'];
    } else {
        break;
    }
    $i += 1;
    echo 'Fetched '.$i.' responses...'."\n";
}

fclose($players);
exec("awk '!seen[$0]++' players-raw-sc2.txt > sc2players-new.lst");
@unlink('sc2players.lst');
rename('sc2players-new.lst', 'sc2players.lst');
unlink('sc2players-new.lst');
unlink('players-raw-sc2.txt');