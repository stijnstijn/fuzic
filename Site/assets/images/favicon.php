<?php
/**
 * Create favicon based on site color in CSS file
 */

$path = dirname(dirname(__FILE__)).'/images/favicon_cache_sc2.png';

global $__games;

//only regenerate if file doesn't exist yet or is older than 1 minute
if (!is_file($path) || filemtime($path) < (time() - 60)) {
    foreach($__games as $game => $data) {
        $colors = get_color_values($data['color']);
        $img = fuzic_icon($colors);
        imagepng($img, str_replace('sc2', $game, $path));
    }
}

header('Content-type: image/png');
echo file_get_contents(str_replace('sc2', ACTIVE_GAME, $path));