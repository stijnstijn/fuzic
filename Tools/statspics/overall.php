<?php
namespace Fuzic;

define('MONTHS_OVERALL', 12);

$x = 722;
$y = 24 + 60 + 400 + 33;

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
write_text($img, 16, 0, 20, 40, $white, 'bold', date('F Y', strtotime('01-'.MONTH.'-'.(YEAR - 1))).' - '.date('F Y', strtotime('01-'.MONTH.'-'.YEAR)));
write_text($img, 24, 0, 20, 80, $white, 'bold', $games[GAME]['name'].' average concurrent viewers');

$stats = $db->fetch_all("SELECT * FROM overall WHERE game = ".$db->escape(GAME)." AND (year < ".YEAR." OR (year = ".YEAR." AND month <= ".MONTH.")) ORDER BY year DESC, month DESC, day DESC");

rounded_rect($img, 24, 100, $width - 48, $height - 100 - 24, $white);


$overall = array();
$months = 0;
$peak = 0;
foreach ($stats as $day) {
    if($day['peak'] == 0) {
        continue;
    }

    if (!isset($overall[$day['year']][$day['month']])) {
        $overall[$day['year']][$day['month']] = array('peak' => 0, 'average' => 0, 'days' => 0);
        $months += 1;
    }
    $overall[$day['year']][$day['month']]['days'] += 1;
    $overall[$day['year']][$day['month']]['average'] += $day['average'];
    if ($overall[$day['year']][$day['month']]['peak'] < $day['peak']) {
        $overall[$day['year']][$day['month']]['peak'] = $day['peak'];
    }
    $peak = max($peak, $day['peak']);

    if ($months == 24) {
        break;
    }
}


ksort($overall);
foreach ($overall as $year => $months) {
    ksort($overall[$year]);
}

$overall_months = array();
$avg_max = 0;
foreach ($overall as $year => $months) {
    foreach ($months as $month => $data) {
        $data['overall'] = $data['days'] > 0 ? $data['average'] / $data['days'] : 0;
        $avg_max = max($avg_max, $data['overall']);
        $data['year'] = $year;
        $data['month'] = $month;
        $overall_months[] = $data;
    }
}

$y_offset = 125;
$chart_h = $height - 200;

$grid_color = $black;
//vert axis
imageline($img, 75, $y_offset, 75, $y_offset + $chart_h, $grid_color);

//x legend
$div = $avg_max / 10;
$length = $y - 100;
$offset = 0;
$index = $avg_max;
for ($i = 0; $i <= 10; $i += 1) {
    $gridline_color = $i == 10 ? $grid_color : $darkgrey;
    imageline($img, 76, $y_offset + $offset, $width - 40, $y_offset + $offset, $gridline_color);
    if ($i == 9) {
        $offset += 2;
    } elseif ($i == 10) {
        $index = 0;
    }
    $bbox = width_text(10, 0, 'regular', number_format(floor($index)));
    $twidth = $bbox[2] - $bbox[0];
    write_text($img, 10, 0, 36 + 32 - $twidth, $y_offset + $offset + 5, $grid_color, 'regular', number_format(floor($index)));
    $offset += ($chart_h / 10);
    $index -= $div;
}

$x_offset = 85;
foreach ($overall_months as $month) {
    $height = ($month['overall'] / $avg_max) * $length;
    $size = width_text(10, 0, 'regular', $month['month'].' '.substr($month['year'], 2).'xx');
    $text = imagecreatetruecolor(abs($size[2] - $size[0]) - 4, 12);
    imagefill($text, 0, 0, $white);
    write_text($text, 10, 0, 0, abs($size[7]), $grid_color, 'regular', $month['month'].'-\''.substr($month['year'], 2));
    $text_rotated = imagerotate_sample($text, M_PI_2, $white);
    imagecopy($img, $text_rotated, $x_offset + 2, $y_offset + $chart_h + 7, 0, 0, imagesx($text_rotated), imagesy($text_rotated));

    //average
    imagefilledrectangle($img, $x_offset - 2, $y_offset + $length - $height, $x_offset + 20 - 2, $y_offset + $chart_h, $game_color);
    rounded_corners($img, $x_offset - 2, $y_offset + $length - $height, 21, 20, $white, false, array(true, true, false, false));

    $x_offset += 25;
}

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
imagepng($img, '../../Site/assets/stats/'.GAME.'-'.YEAR.'-'.str_pad(MONTH, 2, '0', STR_PAD_LEFT).'-overall.png');