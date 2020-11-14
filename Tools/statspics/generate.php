<?php

namespace Fuzic;


use Fuzic\Models;

require_once dirname(dirname(dirname(__FILE__))).'/init.php';

define('INCLUDED', true);

define('LINE', 40);
define('OFFSETX', 8);
define('FONTSIZE', 12);

define('YEAR', date('m') > 1 ? date('Y') : date('Y') - 1);
define('MONTH', date('m') == 1 ? 12 : date('m') - 1);
define('GAME', 'sc2');
define('PLACEHOLDER', dirname(dirname(dirname(__FILE__))).'/Site/assets/images/person.png');

function imagerotate_sample($image, $angle, $background) {
    if($angle >= M_PI * 2) { //normalize to the 0...2pi domain
        $angle = fmod($angle, M_PI * 2);
    }

    if($angle < 0) { //more normalizing
        $angle = (M_PI * 2) + fmod($angle, M_PI * 2);
    }

    $imagesx = imagesx($image);
    $imagesy = imagesy($image);

    if($angle >= M_PI_2) { //do rotations of 90 degrees or multiples thereof first,
        $easy_angle = floor($angle / M_PI_2) * M_PI_2; //the fast way
        $angle = fmod($angle, M_PI_2);

        $new = ($easy_angle == M_PI) ? imagecreatetruecolor($imagesx, $imagesy) : imagecreatetruecolor($imagesy, $imagesx);
        imagealphablending($new, false);
        for($x = 0; $x < $imagesx; $x += 1) {
            for($y = 0; $y < $imagesy; $y += 1) {
                switch($easy_angle) {
                case M_PI_2: //90 degrees
                    imagesetpixel($new, $imagesy - $y - 1, $x, imagecolorat($image, $x, $y));
                    break;
                case M_PI: //180 degrees
                    imagesetpixel($new, $imagesx - $x - 1, $imagesy - $y - 1, imagecolorat($image, $x, $y));
                    break;
                case M_PI_2 * 3: //270 degrees
                    imagesetpixel($new, $y, $imagesx - $x - 1, imagecolorat($image, $x, $y));
                    break;
                }
            }
        }
        $image = $new;
    }

    if($angle == 0) { //if at this point there is no further rotation to do...
        return $image;
    }

    $imagesx = imagesx($image); //set again since dimensions may have changed
    $imagesy = imagesy($image);
    $cos = cos($angle); //save the cpu some work
    $sin = sin($angle);

    //size adjusted for rotated image
    $w = ($imagesx * $cos) + ($imagesy * $sin);
    $h = ($imagesy * $cos) - (-$imagesx * $sin);

    $new = imagecreatetruecolor($w, $h);
    imagefill($new, 0, 0, $background);
    $offset = cos(M_PI_2 - $angle) * $imagesy; //or the origin would be at the top left of the image

    for($x = -$offset; $x < $w; $x += 1) {
        for($y = 0; $y < $h; $y += 1) {
            $org_x = ($x * $cos + $y * $sin);
            if($org_x < 0 || $org_x > $imagesx) {
                continue;
            }
            $org_y = ($y * $cos - $x * $sin);
            if($org_y < 0 || $org_y > $imagesy) {
                continue;
            }

            $color = imagecolorat($image, $org_x, $org_y);
            if($color !== 0) {
                imagesetpixel($new, $x + $offset, $y, $color);
            }
        }
    }

    return $new;
}

function drawicon($img, $x, $y, $text, $color) {
    $size = imagettfbbox(FONTSIZE, 0, dirname(__FILE__).'/fontawesome-webfont.ttf', $text.'g');
    $height = $size[1] - $size[7];
    $offset_y = $height + ((LINE - $height) / 2);
    imagefttext($img, FONTSIZE, 0, $x, $y - LINE + $offset_y, $color, dirname(__FILE__).'/fontawesome-webfont.ttf', $text);
}

function drawtext($img, $x, $y, $text, $color, $bold = false, $fontsize = FONTSIZE, $width = 0, $cjk = false) {
    if(!$cjk) {
        if(is_korean($text)) {
            $cjk = true;
        }
    }
    if($cjk) {
        $font = $bold ? dirname(__FILE__).'/NotoSansCJKkr-Bold.otf' : dirname(__FILE__).'/NotoSansCJKkr-Regular.otf';
    } else {
        $font = $bold ? dirname(__FILE__).'/calibrib.ttf' : dirname(__FILE__).'/calibri.ttf';
    }
    $size = imagettfbbox($fontsize, 0, $font, $text.'gS');
    $x_offset = ($width > 0) ? $width - $size[4] : 0;
    imagefttext($img, $fontsize, 0, $x + $x_offset, $y, $color, $font, $text);
}

function text_width($fontsize, $font, $text) {
    $font = get_font($font, $text);
    $bbox = imageftbbox($fontsize, 0, $font, $text);
    $width = $bbox[2] - $bbox[0];

    return $width;
}

function write_text($image, $size, $angle, $x, $y, $color, $font, $text) {
    $font = get_font($font, $text);
    if(is_korean($text)) {
        $y += 1;
    }
    imagefttext($image, $size, $angle, $x, $y, $color, $font, $text);
}

function width_text($size, $angle, $font, $text) {
    $font = get_font($font, $text);
    return imagettfbbox($size, $angle, $font, $text);
}

function get_font($font, $text) {
    if(!in_array($font, ['light', 'regular', 'bold'])) {
        $font = 'regular';
    }
    if(is_korean($text)) {
        $fonts = [
            'light' => 'NotoSansCJKkr-Light.otf',
            'regular' => 'NotoSansCJKkr-Regular.otf',
            'bold' => 'NotoSansCJKkr-Bold.otf'
        ];
    } else {
        $fonts = [
            'light' => 'Aaux ProLight.ttf',
            'regular' => 'Aaux ProMedium.ttf',
            'bold' => 'Aaux ProBlack.ttf'
        ];

    }
    return dirname(dirname(dirname(__FILE__))).'/Site/assets/fonts/'.$fonts[$font];
}

function is_korean($text) {
    for($i = 0; $i < mb_strlen($text) - 1; $i += 1) {
        $char = mb_ord(mb_substr($text, $i, 1));
        if(
            ($char >= 0xAC00 && $char <= 0xD7A3) || //Hangul Syllables
            ($char >= 0x1100 && $char <= 0x11FF) || //Hangul Jamo
            ($char >= 0x3130 && $char <= 0x318F) || //Hangul Compatibility Jamo
            ($char >= 0xA960 && $char <= 0xA97F) || //Hangul Jamo Extended-A
            ($char >= 0xD7B0 && $char <= 0xD7FF)    //Hangul Jamo Extended-B
        ) {
            return true;
        }
    }

    return false;
}

$games = json_decode(file_get_contents(dirname(dirname(dirname(__FILE__))).'/games.json'), true);

//echo 'Generating top matches image...'."\n";
//include 'matches.php';

echo 'Generating top streamers image...'."\n";
include 'playerstreams.php';

echo 'Generating top event streamers image...'."\n";
include 'eventstreams.php';

echo 'Generating top events image...'."\n";
include 'events.php';

echo 'Generating overall trend image...'."\n";
include 'overall.php';

echo 'Done.'."\n";