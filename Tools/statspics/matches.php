<?php
namespace Fuzic;

define('MATCHES', 5);

$start = period_start('month', MONTH, YEAR);
$end = period_end('month', MONTH, YEAR);

$events = $db->fetch_all("SELECT event FROM ranking_month_event WHERE game = ".$db->escape(GAME)." AND year = ".YEAR." AND month = ".MONTH." ORDER BY rank DESC");

$top = array();
foreach($events as $event) {
    $event = new Models\Event($event['event']);
    $streams = $event->get_stream_IDs();
    //$db->toggle_debug(true, true);
    $interval = new Lib\Interval($streams, $event->get('start'), $event->get('end'), false, $event->get('game'), false, $event->get_ID());
    $datapoints = $interval->get_datapoints();
    $matches = Models\Match::find(['where' => ['event = ?' => $event->get_ID()]]);
    foreach($matches as $match) {
        $peak = 0;
        foreach($datapoints as $time => $viewers) {
            if($time < $match['start']) {
                continue;
            }
            if($time > $match['end']) {
                break;
            }
            $peak = max($peak, $viewers);
        }
        $match['peak'] = $peak;
        $match['event_name'] = $event->get('name');
        $top[] = $match;
    }
}

uasort($top, function($a, $b) {
    if($a['peak'] == $b['peak']) {
        return 0;
    } else {
        return ($a['peak'] > $b['peak']) ? -1 : 1;
    }
});

$top = array_slice($top, 0, 5);

$x = 722;
$y = 100 + (MATCHES * 50);
$img = imagecreatetruecolor($x, $y);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagesavealpha($img, true);
$width = imagesx($img);
$height = imagesy($img);
$line = 33;
$line_y = 0;
$radius = 20; //floor($width * 0.2);

$color = imagecolorallocate($img, 232, 44, 12);
$color2 = imagecolorallocate($img, 174, 33, 9);
$grey = imagecolorallocate($img, 221, 221, 211);
$darkgrey = imagecolorallocate($img, 188, 188, 188);
$black = imagecolorallocate($img, 0, 0, 0);
$transp = imagecolorallocatealpha($img, 255, 255, 255, 127);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$green = imagecolorallocate($img, 0, 220, 40);
$red = imagecolorallocate($img, 240, 0, 20);
$blue = imagecolorallocate($img, 20, 0, 240);

imagefill($img, 1, 1, imagecolorallocate($img, 255, 255, 255));
imagerectangle($img, 0, 0, $width - 1, $height - 1, $grey);

imageline($img, 0, 33, $width, 33, $grey);
drawtext($img, 10, 22, $games[GAME]['name'].', top '.MATCHES.' matches for '.date('F Y', strtotime('01-'.MONTH.'-'.YEAR)), $color, true);

$y_offset = 50;
$peak_size = imagettfbbox(8, 0, dirname(__FILE__).'/calibri.ttf', 'peak');
$peak_pos = ($x / 2) - (abs($peak_size[4] - $peak_size[0]) / 2);
foreach($top as $match) {
    //sigh
    $size = imagettfbbox(8, 0, dirname(__FILE__).'/calibri.ttf', $match['event_name'].' ('.date('j M', $match['start']).')');
    $width = abs($size[4] - $size[0]);
    $x_pos = ($x / 2) - ($width / 2);
    imagettftext($img, 8, 0, $x_pos, $y_offset + 16, $color, dirname(__FILE__).'/calibri.ttf', $match['event_name'].' ('.date('j M', $match['start']).')');
    imagerectangle($img, 100, $y_offset + 19, $x - 100, $y_offset + 19 + 33, $grey);
    imagesetpixel($img, 100, $y_offset + 19, $white);
    imagesetpixel($img, $x - 100, $y_offset + 19, $white);
    imagesetpixel($img, 100, $y_offset + 19 + 33, $white);
    imagesetpixel($img, $x - 100, $y_offset + 19 + 33, $white);

    imageline($img, ($x / 2 - 50), $y_offset + 19, ($x / 2 - 50), $y_offset + 19 + 33, $grey);
    imageline($img, ($x / 2 + 50), $y_offset + 19, ($x / 2 + 50), $y_offset + 19 + 33, $grey);
    imagefilledrectangle($img, ($x / 2 - 49), $y_offset + 20, ($x / 2 + 49), $y_offset + 19 + 32, $color);

    $size = imagettfbbox(12, 0, dirname(__FILE__).'/calibri.ttf', number_format($match['peak']));
    $width = abs($size[4] - $size[0]);
    $x_pos = ($x / 2) - ($width / 2);
    imagettftext($img, 12, 0, $x_pos, $y_offset + 19 + 18, $white, dirname(__FILE__).'/calibri.ttf', number_format($match['peak']));
    imagettftext($img, 8, 0, $peak_pos, $y_offset + 19 + 28, $white, dirname(__FILE__).'/calibri.ttf', 'peak');

    $players = explode(' vs ', $match['match']);

    $size1 = imagettfbbox(8, 0, dirname(__FILE__).'/calibri.ttf', $players[0]);
    $x_pos1 = ($x / 2) - 65 - abs($size1[4] - $size1[0]);

    imagettftext($img, 12, 0, $x_pos1, $y_offset + 19 + 22, $color, dirname(__FILE__).'/calibri.ttf', $players[0]);
    imagettftext($img, 12, 0, ($x / 2) + 60, $y_offset + 19 + 22, $color, dirname(__FILE__).'/calibri.ttf', $players[1]);

    for($i = 0; $i < 2; $i += 1) {
        if(!empty($match['player'.($i + 1)]) && $match['player'.($i + 1)] != 0) {
            $stream = new Models\Stream($match['player'.($i + 1)]);
            $stream = $stream->get_all_data();
            if (!empty($stream['avatar'])) {
                try {
                    $logo = get_url($stream['avatar']);

                    $file = explode('/', $stream['avatar']);
                    $file = array_pop($file);
                    file_put_contents($file, $logo);
                    $logo = imagecreatefromtype($file);
                    if (!$logo) {
                        $logo = imagecreatefrompng(PLACEHOLDER);
                    }
                } catch (\ErrorException $e) {
                    $logo = imagecreatefrompng(PLACEHOLDER);
                }

                if(!$logo) {
                    $logo = imagecreatefrompng(PLACEHOLDER);
                }

                if($i == 0) {
                    imagecopyresampled($img, $logo, 101, $y_offset + 20, 0, 0, 32, 32, imagesx($logo), imagesy($logo));
                } else {
                    imagecopyresampled($img, $logo, ($x - 101 - 31), $y_offset + 20, 0, 0, 32, 32, imagesx($logo), imagesy($logo));
                }
                @unlink($file);
            }
        }
    }

    $y_offset += 55;
}

imagealphablending($img, false);

//rounded upper left corner
imageline($img, 0, 0, 0, 3, $transp);
imageline($img, 0, 0, 3, 0, $transp);
imagesetpixel($img, 1, 1, $transp);
imageline($img, 2, 1, 3, 1, $grey);
imageline($img, 1, 2, 1, 3, $grey);

//rounded upper right corner
imageline($img, $x - 1, 0, $x - 4, 0, $transp);
imageline($img, $x - 1, 0, $x - 1, 3, $transp);
imagesetpixel($img, $x - 2, 1, $transp);
imageline($img, $x - 3, 1, $x - 4, 1, $grey);
imageline($img, $x - 2, 2, $x - 2, 3, $grey);

//rounded lower left corner
imageline($img, 0, $y - 1, 0, $y - 4, $transp);
imageline($img, 0, $y - 1, 3, $y - 1, $transp);
imagesetpixel($img, 1, $y - 2, $transp);
imageline($img, 2, $y - 2, 3, $y - 2, $grey);
imageline($img, 1, $y - 3, 1, $y - 4, $grey);

//rounded lower right corner
imageline($img, $x - 1, $y - 1, $x - 4, $y - 1, $transp);
imageline($img, $x - 1, $y - 1, $x - 1, $y - 4, $transp);
imagesetpixel($img, $x - 2, $y - 2, $transp);
imageline($img, $x - 3, $y - 2, $x - 4, $y - 2, $grey);
imageline($img, $x - 2, $y - 3, $x - 2, $y - 4, $grey);

imagepng($img, '../../Site/assets/stats/'.GAME.'-'.YEAR.'-'.str_pad(MONTH, 2, '0', STR_PAD_LEFT).'-matches.png');