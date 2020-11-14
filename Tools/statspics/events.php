<?php
namespace Fuzic;

define('EVENT_COUNT', 10);
define('EHEIGHT', 70);

$x = 722;
$y = 24 + 125 + (EHEIGHT * EVENT_COUNT) + 22;

$img = imagecreatetruecolor($x, $y);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagesavealpha($img, true);
$width = imagesx($img);
$height = imagesy($img);

$color = get_color_values($games[GAME]['color']);
$game_color = imagecolorallocate($img, $color[0], $color[1], $color[2]);
$darken = 0.9;
$dark_color = imagecolorallocate($img, pow($color[0], $darken), pow($color[1], $darken), pow($color[2], $darken));
$grey = imagecolorallocate($img, 240, 240, 240);
$darkgrey = imagecolorallocate($img, 210, 210, 210);
$black = imagecolorallocate($img, 0, 0, 0);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);

imagefill($img, 1, 1, $game_color);
write_text($img, 16, 0, 20, 40, $white, 'bold', date('F Y', strtotime('01-'.MONTH.'-'.YEAR)));
write_text($img, 24, 0, 20, 80, $white, 'bold', $games[GAME]['name'].' top '.EVENT_COUNT.' events');

$stats = $db->fetch_all("SELECT r.*, e.* FROM ranking_month_event AS r, events AS e WHERE r.game = ".$db->escape(GAME)." AND r.year = '".YEAR."' AND r.month = '".MONTH."' AND r.event = e.".$db->escape_identifier(Models\Event::IDFIELD)." ORDER BY average DESC LIMIT ".EVENT_COUNT);

//table header
imageline($img, 24, 95, $width - 24, 95, $white);
imageline($img, 24, 96, $width - 24, 96, $white);

rounded_rect($img, 24, 131, $width - 48, $height - 156, $white);

//axis left
$chart_top = 125 + 24;

//stats!
$max = 0;
$y_offset = 14;
foreach ($stats as $event) {
    $max = max($event['peak'], $max);
}
foreach($stats as $event) {
    $plength = ($x - 80) * ($event['peak'] / $max);
    $peak_width = text_width(10, 'regular', number_format($event['peak']));
    $average_width = text_width(10, 'regular', number_format($event['average']));
    $avg_length = ($x - 80) * ($event['average'] / $max);

    imagefilledrectangle($img, 37, $chart_top + $y_offset + 4, 37 + $plength, $chart_top + $y_offset + 4 + 22, $game_color);
    rounded_corners($img, 37, $chart_top + $y_offset + 4, $plength + 1, 23, $white, false, array(false, true, false, true));

    if($peak_width < $plength - $avg_length) {
        write_text($img, 10, 0, 37 + $plength - $peak_width - 5, $chart_top + $y_offset + 20, $white, 'regular', number_format($event['peak']));
    } else {
        write_text($img, 10, 0, 37 + $plength + 5, $chart_top + $y_offset + 20, $black, 'regular', number_format($event['peak']));
    }
    imagefilledrectangle($img, 37, $chart_top + $y_offset + 4 + 10, 37 + $avg_length, $chart_top + $y_offset + 4 + 22 + 10, $dark_color);
    rounded_corners($img, 37, $chart_top + $y_offset + 4 + 10, $avg_length + 1, 23, $game_color, false, array(false, true, false, false));
    rounded_corners($img, 37, $chart_top + $y_offset + 4 + 10, $avg_length + 1, 23, $white, false, array(false, false, false, true));

    write_text($img, 12, 0, 39, $chart_top + $y_offset - 3, $black, 'regular', $event['name'].' ('.date('j M', $event['start']).')');

    //sigh
    $size = width_text(10, 0, 'regular', number_format($event['average']));
    $twidth = abs($size[4] - $size[0]);

    if($twidth < $avg_length) {
        write_text($img, 10, 0, 37 + $avg_length - $average_width - 5, $chart_top + $y_offset + 20 + 10, $white, 'regular', number_format($event['average']));
    }

    $y_offset += EHEIGHT;
}

$y_offset = -50;
//legend
rounded_rect($img, $width - 176 - 4, $y_offset + $chart_top, 151, 29, $game_color);

rounded_rect($img, $width - 99 - 4, $y_offset + $chart_top + 7, 15, 15, $dark_color);
write_text($img, 10, 0, $width - 77 - 4, $y_offset + $chart_top + 20, $white, 'regular', 'Average');

rounded_rect($img, $width - 169 - 4, $y_offset + $chart_top + 7, 15, 15, $white);
rounded_rect($img, $width - 169 - 3, $y_offset + $chart_top + 8, 13, 13, $game_color);
write_text($img, 10, 0, $width - 147 - 4, $y_offset + $chart_top + 20, $white, 'regular', 'Peak');

//copy logo
$fuzic = imagecreatefrompng(dirname(dirname(dirname(__FILE__))).'/Site/assets/images/base_logo.png');
$copy_height = 22;
$w = imagesx($fuzic) * ($copy_height / imagesy($fuzic));
imagecopyresampled($img, $fuzic, imagesx($img) - $w - 22, 22, 0, 0, $w, $copy_height, imagesx($fuzic), imagesy($fuzic));

//give the whole thing rounded borders
imagealphablending($img, false);
rounded_corners($img, 0, 0, imagesx($img), imagesy($img), $game_color, true);

if(!defined('INCLUDED') || !INCLUDED) {
    header('Content-type: image/png');
}

imagepng($img, '../../Site/assets/stats/'.GAME.'-'.YEAR.'-'.str_pad(MONTH, 2, '0', STR_PAD_LEFT).'-events.png');