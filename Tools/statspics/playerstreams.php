<?php
namespace Fuzic;

define('PLAYER_STREAMS', 30);
if(!defined('LHEIGHT')) {
    define('LHEIGHT', 40);
}

$x = 722;
$y = 24 + 125 + (LINE * PLAYER_STREAMS);

$img = imagecreatetruecolor($x, $y);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagesavealpha($img, true);
$width = imagesx($img);
$height = imagesy($img);

$color = get_color_values($games[GAME]['color']);
$game_color = imagecolorallocate($img, $color[0], $color[1], $color[2]);
$grey = imagecolorallocate($img, 240, 240, 240);
$darkgrey = imagecolorallocate($img, 210, 210, 210);
$black = imagecolorallocate($img, 0, 0, 0);
$white = imagecolorallocatealpha($img, 255, 255, 255, 0);

imagefill($img, 1, 1, $game_color);
write_text($img, 16, 0, 20, 40, $white, 'bold', date('F Y', strtotime('01-'.MONTH.'-'.YEAR)));
write_text($img, 24, 0, 20, 80, $white, 'bold', $games[GAME]['name'].' top player streams');

$ranking = $db->fetch_all("
    SELECT s.real_name, s.remote_id, s.avatar, s.player, r.stream, r.rank, r.average, r.peak, r.vh, r.time, r.delta
      FROM streams AS s,  ranking_month_e AS r
     WHERE r.stream = s.".$db->escape_identifier(Models\Stream::IDFIELD)."
       AND r.year = ".YEAR."
       AND r.month = ".MONTH."
       AND r.game = ".$db->escape(GAME)."
       AND s.player = 1
     ORDER BY rank ASC
     LIMIT ".PLAYER_STREAMS);
$i = 1;
foreach($ranking as $index => $stream) {
    $ranking[$index]['rank'] = $i;
    $i += 1;
}

//calculate text widths for centering
$i = 0;
$peak_max = 0;
$avg_max = 0;
$vh_max = 0;
$time_max = 0;
$delta_max = 0;
$width10 = text_width(12, 'light', '10');

foreach($ranking as $index => $rank) {
    $p = $db->fetch_field("
        SELECT r.rank
          FROM ranking_month_e AS r
         WHERE r.stream = ".$db->escape($rank['stream'])."
           AND r.game = ".$db->escape(GAME)."
           AND r.year = ".(MONTH == 1 ? YEAR - 1 : YEAR)."
           AND r.month = ".(MONTH == 1 ? 12 : MONTH - 1));
    if(!$p) {
        $ranking[$index]['delta'] = NULL;
        $rank[$index]['delta'] = NULL;
    } else {
        $p = $db->fetch_field("
        SELECT COUNT(*)
          FROM ranking_month_e AS r
         WHERE r.game = ".$db->escape(GAME)."
           AND r.year = ".(MONTH == 1 ? YEAR - 1 : YEAR)."
           AND r.month = ".(MONTH == 1 ? 12 : MONTH - 1)."
           AND r.rank <= ".intval($p)."
           AND r.stream IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE)." WHERE player = 1 )");
        $ranking[$index]['delta'] = $rank['rank'] - $p;
        $rank['delta'] = $rank['rank'] - $p;
    }
    if ($rank['delta'] === NULL) {
        $delta_text = 'New!';
    } elseif ($rank['delta'] === 0) {
        $delta_text = '-';
    } else {
        if($rank['delta'] == 0) {
            $delta_text = '0';
        } else {
            $delta_text = ($rank['delta'] > 0) ? '-'.abs($rank['delta']) : '+'.abs($rank['delta']);
        }
    }
    $vh = number_format(round($rank['vh'] / 1000)).'k';
    $peak_max = max($peak_max, text_width(14, 'light', number_format($rank['peak'])));
    $avg_max = max($avg_max, text_width(14, 'light', number_format($rank['average'])));
    $vh_max = max($vh_max, text_width(14, 'light', $vh));
    $time_max = max($vh_max, text_width(14, 'light', round($rank['time'] / 3600).'h'));
    $delta_max = max($vh_max, text_width(14, 'light', $delta_text));
}

//table header
imageline($img, 24, 95, $width - 24, 95, $white);
imageline($img, 24, 96, $width - 24, 96, $white);

rounded_rect($img, 24, 125, $width - 48, $height - 149, $white);
write_text($img, 10, 0, 36 + 243 + ($vh_max / 2), 116, $white, 'regular', 'VxH');
write_text($img, 10, 0, 36 + 313 + ($avg_max / 2), 116, $white, 'regular', 'Average');
write_text($img, 10, 0, 36 + 412 + ($peak_max / 2), 116, $white, 'regular', 'Peak');
write_text($img, 10, 0, 36 + 502 + ($time_max / 2), 116, $white, 'regular', 'Time');
write_text($img, 10, 0, 36 + 571 + ($delta_max / 2), 116, $white, 'regular', 'Place');

//stats!
foreach($ranking as $rank) {
    //zebra-striped table rows
    if($i % 2 == 1) {
        if($i == count($ranking) - 1) {
            rounded_rect($img, 24, 125 + ($i * LHEIGHT), $width - 48, LHEIGHT, $grey);
            imagefilledrectangle($img, 24, 125 + ($i * LHEIGHT), $width - 24, 125 + ($i * LHEIGHT) + 5, $grey);
        } else {
            imagefilledrectangle($img, 24, 125 + ($i * LHEIGHT), $width - 24, 125 + ($i * LHEIGHT) + LHEIGHT, $grey);
        }
    }

    //delta stuff
    if ($rank['delta'] === NULL) {
        $delta_text = 'New!';
    } elseif ($rank['delta'] === 0) {
        $delta_text = '-';
    } else {
        if($rank['delta'] == 0) {
            $delta_text = '0';
        } else {
            $delta_text = ($rank['delta'] > 0) ? '-'.abs($rank['delta']) : '+'.abs($rank['delta']);
        }
    }

    if ($rank['delta'] === NULL || $rank['delta'] === 0) {
        $icon = '&#61713;';
    } else {
        $icon = ($rank['delta'] < 0) ? '&#61610;' : '&#61611;';
    }

    //retrieve logo from streaming service, or display default image
    if (!empty($rank['avatar'])) {
        try {
            $logo = get_url($rank['avatar']);

            $file = explode('/', $rank['avatar']);
            $file = array_pop($file);
            file_put_contents($file, $logo);
            $logo = imagecreatefromtype($file);
            if (!$logo) {
                $logo = imagecreatefrompng(PLACEHOLDER);
            }
        } catch (\ErrorException $e) {
            $logo = imagecreatefrompng(PLACEHOLDER);
        }

        if (!$logo) {
            $logo = imagecreatefrompng(PLACEHOLDER);
        }

        @unlink($file);
    } else {
        $logo = imagecreatefrompng(PLACEHOLDER);
    }

    //render columns
    $widthnum = text_width(12, 'light', $i + 1);
    write_text($img, 12, 0, 36 + (($width10 - $widthnum) / 2),151 + ($i * LHEIGHT), $black, 'light', $i + 1);

    imagecopyresampled($img, $logo, 36 + 27, 125 + 7 + ($i * LHEIGHT), 0, 0, 26, 26, imagesx($logo), imagesy($logo));
    rounded_corners($img, 36 + 27, 125 + 7 + ($i * LHEIGHT), 26, 26, ($i % 2 == 0 ? $white : $grey));

    write_text($img, 14, 0, 36 + 62, 152 + ($i * LHEIGHT), $black, 'regular', $rank['real_name']);

    $vh = number_format(round($rank['vh'] / 1000)).'k';
    $vh_width = text_width(14, 'light', $vh);
    write_text($img, 14, 0, 36 + 255 + (($vh_max - $vh_width) / 2), 152 + ($i * LHEIGHT), $black, 'light', $vh);

    $avg_width = text_width(14, 'light', number_format($rank['average']));
    write_text($img, 14, 0, 36 + 335 + (($avg_max - $avg_width) / 2), 152 + ($i * LHEIGHT), $black, 'light', number_format($rank['average']));

    $peak_width = text_width(14, 'light', number_format($rank['peak']));
    write_text($img, 14, 0, 36 + 425 + (($peak_max - $peak_width) / 2), 152 + ($i * LHEIGHT), $black, 'light', number_format($rank['peak']));

    $time_width = text_width(14, 'light', round($rank['time'] / 3600).'h');
    write_text($img, 14, 0, 36 + 515 + (($time_max - $time_width) / 2), 152 + ($i * LHEIGHT), $black, 'light', round($rank['time'] / 3600).'h');

    $delta_width = text_width(14, 'light', $delta_text);
    write_text($img, 14, 0, 36 + 585 + (($delta_max - $delta_width) / 2), 152 + ($i * LHEIGHT), $black, 'light', $delta_text);

    drawicon($img, 36 + 640, 164 + ($i * LHEIGHT), $icon, $darkgrey);
    $i += 1;
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

imagepng($img, '../../Site/assets/stats/'.GAME.'-'.YEAR.'-'.str_pad(MONTH, 2, '0', STR_PAD_LEFT).'-streams.png');