<?php
/*
 * Epxerimental! Tries to get current matchup from stream screenshot via OCR
 */
namespace Fuzic\Tools;
chdir(dirname(__FILE__));
require '../init.php';

use Fuzic\Lib;
use Fuzic\Config;
use Fuzic\Models;
use Fuzic\Crawler;

if(!isset($argv[1]) || !is_readable($argv[1])) {
    echo 'Correct usage: php '.basename(__FILE__)." [screenshot.jpeg]\n";
    exit;
}

$full = @imagecreatefromjpeg($argv[1]);
if(!$full) {
    echo 'Not a valid JPEG file.'."\n";
    echo 'Correct usage: php '.basename(__FILE__)." [screenshot.jpeg]\n";
    exit;
}


//only look at bottom fourth of image
$image = imagecreatetruecolor(ceil(imagesx($full) * 0.2 / 2), ceil(imagesy($full) * 0.09));
imagecopy($image, $full, 0, 0, imagesx($image) * 2, imagesy($full) - ceil(imagesy($full) * 0.09), imagesx($image), imagesy($image));
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
$w = imagesx($image);
$h = imagesy($image);

//binarise image and invert colours, so letters become black on white
for($x = 0; $x < $w; $x += 1) {
    for($y = 0; $y < $h; $y += 1) {
        $color = imagecolorat($image, $x, $y);

        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        $hsl = rgb2hsl($r, $g, $b);
        if($hsl[2] < .5) {
            imagesetpixel($image, $x, $y, $white);
        } else {
            imagesetpixel($image, $x, $y, $black);
        }
    }
}

imagepng($image, 'screenshot-temp.png');

//this removes an extra file we need to keep track of
$cfg = fopen('ocrcfg', 'w');
fwrite($cfg, "load_system_dawg        F\nload_freq_dawg          F");
fclose($cfg);
exec('tesseract screenshot-temp.png text-version -l sc2 ocrcfg');

$data = file_get_contents('text-version.txt');
$words_raw = file_get_contents('sc2players.lst');

$data = explode("\n", $data);

$words_raw = explode("\n", $words_raw);
$words = array();
foreach($words_raw as $word) {
	$words[] = trim($word);
}

$detected = array();

foreach($data as $line) {
	$line = explode(" ", $line);
	foreach($line as $word) {
	    $to_check = preg_split('/[^a-zA-Z0-9]/siU', $word);
	    foreach($to_check as $word) {
            $word = trim($word);
            if(strlen($word) < 3) {
                continue; //sorry, MC and TY
            }
            foreach($words as $allowed) {
                if(empty(trim($allowed))) {
                    continue;
                }
                $word = strtolower($word);
                $ins_cost = 5 + max(5 - strlen($allowed), 0);
                $change_cost = 1 + max(12 - strlen($allowed), 0);
                $similarity = levenshtein($word, strtolower($allowed), $ins_cost, $change_cost, 5);
                if($similarity < 10) {
                    if(!isset($detected[$word])) {
                        $detected[$word] = array();
                    }

                    $detected[$word][$allowed] = $similarity;
                }
            }
        }
	}
}

if(count($detected) > 0) {
    echo 'Found players: '."\n";
    foreach($detected as $word => $matches) {
        asort($matches);
        $matches = array_keys($matches);
        echo array_shift($matches)."\n";
    }
} else {
    echo 'Sorry, no players found'."\n";
}