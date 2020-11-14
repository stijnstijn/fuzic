<?php
/**
 * Miscellaneous functions
 *
 * @package Fuzic
 */

/**
 * @param string $folder Folder to look in (in `ROOT`)
 * @param string $type   `class` or `interface`
 *
 * @return Closure  Auto-loader function
 */
function auto_loader($folder, $type) {
    $function = function ($model_name) use ($folder, $type) {
        $name = explode('\\', $model_name);
        $name = array_pop($name);
        if ($name == 'Controller' || substr($model_name, 0 - strlen('Controller')) != 'Controller') {
            $path = ROOT.'/'.$folder.'/'.$type.'.'.$name.'.php';
        } else {
            $path = ROOT.'/'.$folder.'/'.str_replace('Controller', '', $name).'.php';
        }

        if (is_readable($path)) {
            require_once $path;
        }
    };
    return $function;
}

/**
 * Fix wrongly quoted JSON
 *
 * Thanks NikiC:
 * https://stackoverflow.com/questions/20348380/how-to-json-decode-invalid-json-with-apostrophe-instead-of-quotation-mark
 *
 * @param   string $json JSON to fix
 *
 * @return  string          Fixed JSON
 *
 * @package Fuzic
 */
function fix_JSON($json) {
    $regex = <<<'REGEX'
~
    "[^"\\]*(?:\\.|[^"\\]*)*"
    (*SKIP)(*F)
  | '([^'\\]*(?:\\.|[^'\\]*)*)'
~x
REGEX;

    return preg_replace_callback($regex, function ($matches) {
        return '"'.preg_replace('~\\\\.(*SKIP)(*F)|"~', '\\"', $matches[1]).'"';
    }, $json);
}

/**
 * Get UNIX timestamp for end of week/month
 *
 * @param  string  $type   'week' or 'month'
 * @param  integer $period Which week or month to calculate the end for
 * @param  integer $year   Year in which the week or month is
 *
 * @return  integer  End of period, as UNIX timestamp
 *
 * @package Fuzic
 */
function period_start($type, $period, $year) {
    if ($type == 'week') {
        return strtotime(intval($year).'W'.str_pad(intval($period), 2, '0', STR_PAD_LEFT).'1');
    } elseif($type == 'month') {
        return mktime(0, 0, 0, $period, 1, $year);
    } else {
        return mktime(0, 0, 0, 1, 1, $year);
    }
}

/**
 * Get UNIX timestamp for start of week/month
 *
 * @param  string  $type   'week' or 'month'
 * @param  integer $period Which week or month to calculate the start for
 * @param  integer $year   Year in which the week or month is
 *
 * @return  integer  Start of period, as UNIX timestamp
 *
 * @package Fuzic
 */
function period_end($type, $period, $year) {
    if ($type == 'week') {
        return strtotime(intval($year).'W'.str_pad(intval($period), 2, '0', STR_PAD_LEFT).'-7 23:59:59');
    } elseif($type == 'month') {
        return mktime(23, 59, 59, $period, cal_days_in_month(CAL_GREGORIAN, $period, $year), $year);
    } else {
        return mktime(23, 59, 59, 12, 31, $year);
    }
}

/**
 * Get a fancy "13h37m"-style representation of time
 *
 * @param $time  Time in seconds
 *
 * @return string  Formatted time
 *
 * @package Fuzic
 */
function time_approx($time) {
    $time = intval($time);

    if ($time < 60) {
        return $time.'s';
    }

    if ($time < 3600) {
        $minutes = floor($time / 60);
        $seconds = $time - ($minutes * 60);
        return $minutes.'m '.($seconds > 0 ? $seconds.'s' : '');
    }

    if ($time < 86400) {
        $hours = floor($time / 3600);
        $minutes = floor(($time - ($hours * 3600)) / 60);
        return $hours.'h '.($minutes > 0 ? $minutes.'m' : '');
    }

    if ($time < 604800) {
        $days = floor($time / 86400);
        $hours = floor(($time - ($days * 86400)) / 3600);
        return $days.'d '.($hours > 0 ? $hours.'h' : '');
    }

    $weeks = floor($time / 604800);
    $days = floor(($time - ($weeks * 604800)) / 86400);
    return $weeks.'w '.($days > 0 ? $days.'d' : '');
}

/**
 * Get amount of weeks/months for a given year
 *
 * @param  string  $type   'week' or 'month'
 * @param  integer $year   What year to look up the amount of periods for. Only really
 *                         relevant for weeks.
 *
 * @return  integer  Amount of weeks/months in a year
 *
 * @package Fuzic
 */
function units_per_year($type, $year) {
    if ($type == 'week') {
        $date = new DateTime;
        $date->setISODate($year, 53);
        return ($date->format("W") === "53" ? 53 : 52);
    } else {
        return 12;
    }
}

function get_week_year($time = 0) {
    if($time == -1) {
        $time = time();
    }

    $y = date('Y', $time);
    if(date('n', $time) == 1 && date('W', $time) > 5) {
        return $y - 1;
    } else if(date('n', $time) == 12 && date('W', $time) < 5) {
        return $y + 1;
    } else {
        return $y;
    }
}

/**
 * Get current year, based on current week number
 *
 * If week number is provided, use that week number for the current year.
 *
 * @param bool|false $week Week number, or `false` if not relevant
 *
 * @return int  Year
 *
 * @package Fuzic
 */
function year($week = false) {
    if (!$week || $week <= 52) {
        if (!$week) {
            $week = date('W');
        }

        if ($week == 1 && date('n') == 12) {
            return date('Y') + 1;
        }
    } else {
        $week = date('W', $week);
        if ($week == 1 && date('n', $week) == 12) {
            return date('Y', $week) + 1;
        }
    }

    return date('Y');
}

/**
 * Turn an array of integers into a (slimmed down) JSON representation
 *
 * @param  array $array Array to encode
 *
 * @return  string  Array encoded as JSON
 *
 * @package Fuzic-site
 */
function json_encode_ints($array) {
    $data = '[';
    foreach ($array as $value) {
        $data .= ''.intval($value).',';
    }
    return strlen($data) == 1 ? '[]' : substr($data, 0, -1).']';
}

/**
 * Turn an array of strings into a (slimmed down) JSON representation
 *
 * @param  array $array Array to encode
 *
 * @return  string  Array encoded as JSON
 *
 * @package Fuzic-site
 */
function json_encode_strings($array) {
    $data = '[';
    foreach ($array as $value) {
        $data .= '"'.addslashes($value).'",';
    }
    return strlen($data) == 1 ? '[]' : substr($data, 0, -1).']';
}

/**
 * Encode a list of integers, in a way that takes less space than
 * `json_encode`
 *
 * @param   array $array Array to encode
 *
 * @return  string          Encoded array
 *
 * @package Fuzic-site
 */
function datapoints_encode($array) {
    $data = '[';
    foreach ($array as $time => $viewers) {
        $data .= intval($time).':'.intval($viewers).',';
    }
    return substr($data, 0, -1).']';
}

/**
 * Counterpart to `datapoints_decode`
 *
 * @param   string $string String to decode
 *
 * @return  array           Decoded string
 *
 * @package Fuzic-site
 */
function datapoints_decode($string) {
    $raw = explode(',', substr($string, 1, -1));
    $data = array();
    foreach ($raw as $point) {
        $point = explode(':', $point);
        $data[$point[0]] = $point[1];
    }
    return $data;
}

/**
 * Like get_object_vars, but also shows protected values
 *
 * @param   object $object Object to get variables from
 *
 * @return  array               Variables from object
 *
 * @package Fuzic-site
 */
function get_object_vars_all($object) {
    $array = (array)$object;
    $return = array();

    foreach ($array as $key => $value) {
        $split = explode("\0", $key);
        $return[array_pop($split)] = $value;
    }

    return $return;
}

/**
 * Fetches an URL and returns its contents
 *
 * @param  string  $url             URL to fetch
 * @param  array   $headers         HTTP headers to send with the request, as an associative array
 * @param  boolean $include_headers Whether to include the HTTP headers in the response
 * @param  boolean $fake_UA         Whether to have the request present itself as an ordinary
 *                                  browser request rather than the Fuzic crawler.
 *
 * @return string  Page content
 *
 * @throws ErrorException In case of cURL error
 *
 * @package Fuzic
 */
function get_url($url, $headers = array(), $include_headers = false, $fake_UA = false, $timeout = 30, $post = false) {
    if (defined('CURL_FROM_CACHE')) {
        $hash = sha1($url.serialize($headers));
        if (is_readable(dirname(dirname(__FILE__)).'/cache/'.$hash)) {
            return file_get_contents(dirname(dirname(__FILE__)).'/cache/'.$hash);
        }
    }

    $curl = curl_init();
    $ua = $fake_UA ? 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36' : 'Fuzic/2.0 (http://www.fuzic.nl; crawler@fuzic.nl)';
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    curl_setopt($curl, CURLOPT_HEADER, $include_headers);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_USERAGENT, $ua);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    if($post) {
        curl_setopt($curl, CURLOPT_POST, 1);
        if(is_array($post) || is_string($post)) {
            curl_setop($curl, CURLOPT_POSTFIELDS, $post);
        }
    }

    if(!empty($headers)) {
        curl_setopt($curl, CURLOPT_COOKIEJAR, '/srv/www/fuzic/logs/cookie.jar');
        curl_setopt($curl, CURLOPT_COOKIEFILE, '/srv/www/fuzic/logs/cookie.jar');
    }
    $source = curl_exec($curl);

    if ($source === false) {
        throw new ErrorException('cURL error: '.curl_error($curl).' '.curl_errno($curl));
    }

    if($include_headers) {
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $source = "HTTP-Response-Code: ".$code."\r\n".$source;
    }

    if (defined('CURL_FROM_CACHE')) {
        $h = fopen(dirname(dirname(__FILE__)).'/cache/'.$hash, 'w');
        fwrite($h, $source);
        fclose($h);
    }

    return $source;
}

function post_url($url, $headers = array(), $include_headers = false, $fake_UA = false, $timeout = 30, $post = true) {
    return get_url($url, $headers, $include_headers, $fake_UA, $timeout, $post);
}

function extract_headers(&$response) {
    $response = explode("\r\n\r\n", $response);
    $hraw = explode("\r\n", array_shift($response));
    $headers = array();
    foreach($hraw as $line) {
        $line = explode(': ', $line);
        if(count($line) <= 1) {
            continue;
        }
        $headers[array_shift($line)] = implode(": ", $line);
    }
    $response = implode($response, "\r\n\r\n");
    return $headers;
}

/**
 * Retrieves DOMDocument nodes matching a class
 *
 * @param    object $dom   DOM fragment
 * @param    string $class Class to match
 *
 * @returns  object  matching elements
 *
 * @package Fuzic
 */
function getElementsByClassName($dom, $class) {
    if (!$dom instanceof DOMDocument) {
        $domdoc = new DOMDocument();
        $domdoc->appendChild($domdoc->importNode($dom, true));
        $dom = $domdoc;
    }

    $find = new DOMXpath($dom);

    $items = $find->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ".$class." ')]");
    unset($find);
    return $items;
}

/**
 * Strip characters from a string that would mess up an URL
 *
 * @param  string $string String to sanitize
 *
 * @return  string  Sanitized string
 *
 * @package Fuzic
 */
function friendly_url($string) {
    $string = strtr($string, array('$' => 'S',
                                   '@' => 'a',
                                   '¢' => 'c',
                                   '£' => 'l',
                                   '¥' => 'y',
                                   '©' => 'c',
                                   'ª' => 'a',
                                   '®' => 'r',
                                   '°' => 'o',
                                   '²' => '2',
                                   '³' => '3',
                                   'µ' => 'm',
                                   '¹' => '1',
                                   'º' => 'o',
                                   '¼' => '14',
                                   '½' => '12',
                                   '¾' => '34',
                                   'À' => 'A',
                                   'Á' => 'A',
                                   'Â' => 'A',
                                   'Ã' => 'A',
                                   'Ä' => 'A',
                                   'Å' => 'A',
                                   'Æ' => 'AE',
                                   'Ç' => 'C',
                                   'È' => 'E',
                                   'É' => 'E',
                                   'Ê' => 'E',
                                   'Ë' => 'E',
                                   'Ì' => 'I',
                                   'Í' => 'I',
                                   'Î' => 'I',
                                   'Ï' => 'I',
                                   'Ð' => 'D',
                                   'Ñ' => 'N',
                                   'Ò' => 'O',
                                   'Ó' => 'O',
                                   'Ô' => 'O',
                                   'Õ' => 'O',
                                   'Ö' => 'O',
                                   '×' => 'x',
                                   'Ø' => 'O',
                                   'Ù' => 'U',
                                   'Ú' => 'U',
                                   'Û' => 'U',
                                   'Ü' => 'U',
                                   'Ý' => 'Y',
                                   'Þ' => 'p',
                                   'ß' => 'B',
                                   'à' => 'a',
                                   'á' => 'a',
                                   'â' => 'a',
                                   'ã' => 'a',
                                   'ä' => 'a',
                                   'å' => 'a',
                                   'æ' => 'ae',
                                   'ç' => 'c',
                                   'è' => 'e',
                                   'é' => 'e',
                                   'ê' => 'e',
                                   'ë' => 'e',
                                   'ì' => 'i',
                                   'í' => 'i',
                                   'î' => 'i',
                                   'ï' => 'i',
                                   'ð' => 'd',
                                   'ñ' => 'n',
                                   'ò' => 'o',
                                   'ó' => 'o',
                                   'ô' => 'o',
                                   'õ' => 'o',
                                   'ö' => 'o',
                                   'ø' => 'o',
                                   'ù' => 'u',
                                   'ú' => 'u',
                                   'û' => 'u',
                                   'ü' => 'u',
                                   'ý' => 'y',
                                   'þ' => 'p',
                                   'ÿ' => 'y'
    ));
    $string = str_replace(' ', '-', trim(strtolower(preg_replace(array('/&amp;/', '/&/', '/\+/', '/([^a-zA-Z0-9 ]+)/siU', '/([ ]+)/'), array('and', 'and', 'plus', '', ' '), strip_tags($string)))));
    return empty($string) ? '-' : $string;
}

/**
 * Convert RGB values to HSL values
 *
 * @param $r Red colour component, 0-255
 * @param $g Green colour component, 0-255
 * @param $b Blue colour component, 0-255
 *
 * @return array HSL values, [H, S, L]
 */
function rgb2hsl($r, $g, $b) {
    $r /= 255;
    $g /= 255;
    $b /= 255;

    $min = min($r,$g,$b);
    $max = max($r,$g,$b);
    $del_max = $max - $min;

    $l = ($max + $min) / 2;

    if ($del_max == 0) {
        $h = 0;
        $s = 0;
    } else {
        if ($l < 0.5) {
            $s = $del_max / ($max + $min);
        } else {
            $s = $del_max / (2 - $max - $min);
        }

        $del_r = ((($max - $r) / 6) + ($del_max / 2)) / $del_max;
        $del_g = ((($max - $g) / 6) + ($del_max / 2)) / $del_max;
        $del_b = ((($max - $b) / 6) + ($del_max / 2)) / $del_max;

        if ($r == $max) {
            $h = $del_b - $del_g;
        } elseif ($g == $max) {
            $h = (1 / 3) + $del_r - $del_b;
        } elseif ($b == $max) {
            $h = (2 / 3) + $del_g - $del_r;
        }

        if ($h < 0) {
            $h += 1;
        }

        if ($h > 1) {
            $h -= 1;
        }
    }

    return [$h, $s, $l];
}

/**
 * Creates an image resource from a file, regardless of file type
 *
 * @param  string $path Path to the image file
 *
 * @return  resource  Image resource, or false if not a valid image
 *
 * @package Fuzic
 */
function imagecreatefromtype($path) {
    $data = @getimagesize($path);
    if (!$data) {
        return false;
    }
    if ($data['mime'] == 'image/png') {
        return imagecreatefrompng($path);
    } elseif ($data['mime'] == 'image/gif') {
        return imagecreatefromgif($path);
    } elseif ($data['mime'] == 'image/bmp') {
        return false;
    } else {
        return imagecreatefromjpeg($path);
    }
}

/**
 * Create fuzic logo image
 *
 * @param   array $colors Optional color of image. Should be an array with
 *                        three elements (red, green blue). Defaults to `255, 0, 128`.
 *
 * @return  resource        The logo image
 *
 * @package Fuzic
 */
function fuzic_icon($colors = array(255, 0, 128)) {
    $width = 1000;
    $height = 1000;
    $radius = floor($width * 0.2);

    $icon = imagecreatetruecolor($width, $height);
    imagealphablending($icon, false);
    imagesavealpha($icon, true);
    imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 255, 255, 255, 127));

    $color = imagecolorallocate($icon, $colors[0], $colors[1], $colors[2]);

    imagefilledrectangle($icon, $radius, 0, ($width - $radius), $height, $color);
    imagefilledrectangle($icon, 0, $radius, $width, ($height - $radius), $color);

    $size = ($radius * 2);
    imagefilledellipse($icon, $radius, $radius, $size, $size, $color);
    imagefilledellipse($icon, ($width - $radius), $radius, $size, $size, $color);
    imagefilledellipse($icon, $radius, ($height - $radius), $size, $size, $color);
    imagefilledellipse($icon, ($width - $radius), ($height - $radius), $size, $size, $color);


    $final = imagecreatetruecolor(200, 200);
    imagealphablending($final, false);
    imagesavealpha($final, true);

    imagecopyresampled($final, $icon, 0, 0, 0, 0, imagesx($final), imagesy($final), imagesx($icon), imagesy($icon));

    return $final;
}

/**
 * Create twitter profile image
 *
 * @param   array $colors Background colors of image, as an array with items for
 *                        `red`, `green` and `blue` values
 *
 * @return  resource Image resource for 400x400 profile picture
 *
 * @package Fuzic
 */
function fuzic_twitter_icon($colors) {
    $overlay = imagecreatefrompng(ROOT.'/Site/assets/images/zic.png');

    //intentional double imagesx(): make it square
    $icon = imagecreatetruecolor(imagesx($overlay), imagesx($overlay));
    imagefill($icon, 1, 1, imagecolorallocate($icon, $colors[0], $colors[1], $colors[2]));

    imagecopyresampled($icon, $overlay, 0, 0, 0, 0, imagesx($icon), imagesy($icon), imagesx($overlay), imagesy($overlay));

    $final = imagecreatetruecolor(400, 400);
    imagecopyresampled($final, $icon, 0, 0, 0, 0, imagesx($final), imagesy($final), imagesx($icon), imagesy($icon));

    return $final;
}


/**
 * Get color values for a HTML/CSS color string
 *
 * @param   string $string Color string
 *
 * @return  array               Color, elements for `red`, `green` and `blue`
 *
 * @package Fuzic-site
 */
function get_color_values($string) {
    $string = str_replace('#', '', $string);
    $colors = array();
    if (strlen($string) == 3) {
        for ($i = 0; $i < 3; $i += 1) {
            $colors[] = hexdec(substr($string, $i, 1).substr($string, $i, 1));
        }
        return $colors;
    } elseif (strlen($string) == 6) {
        for ($i = 0; $i < 6; $i += 2) {
            $colors[] = hexdec(substr($string, $i, 2));
        }
        return $colors;
    } else {
        return false;
    }
}
/**
 * Draw rounded rectangle
 *
 * Will always have a border radius of 4px. Corners are anti-aliased.
 *
 * @param resource $image Image to draw to
 * @param int   $x     X coordinate, upper left corner
 * @param int   $y     Y coordinate, upper left corner
 * @param int   $width
 * @param int   $height
 * @param int  $color
 */
function rounded_rect($image, $x, $y, $width, $height, $color) {
    imagefilledrectangle($image, $x + 4, $y, $x + $width - 4, $y + $height, $color);
    imagefilledrectangle($image, $x, $y + 4, $x + $width, $y + $height - 4, $color);

    imagefilledrectangle($image, $x + 2, $y + 1, $x + 4, $y + 4, $color);
    imagefilledrectangle($image, $x + 1, $y + 2, $x + 4, $y + 4, $color);

    imagefilledrectangle($image, $x + $width - 3, $y + 1, $x + $width - 2, $y + 4, $color);
    imagefilledrectangle($image, $x + $width - 3, $y + 2, $x + $width - 1, $y + 4, $color);

    imagefilledrectangle($image, $x + 2, $y + $height - 4, $x + 4, $y + $height - 1, $color);
    imagefilledrectangle($image, $x + 1, $y + $height - 4, $x + 4, $y + $height - 2, $color);

    imagefilledrectangle($image, $x + $width - 3, $y + $height - 4, $x + $width - 2, $y + $height - 1, $color);
    imagefilledrectangle($image, $x + $width - 3, $y + $height - 4, $x + $width - 1, $y + $height - 2, $color);

    $blend = [
        75 => [
            [$x + 1, $y + 1], [$x + $width - 1, $y + 1], [$x + 1, $y + $height - 1], [$x + $width - 1, $y + $height - 1],
            [$x + 3, $y], [$x + $width - 3, $y], [$x + 3, $y + $height], [$x + $width - 3, $y + $height],
            [$x, $y + 3], [$x + $width, $y + 3], [$x, $y + $height - 3], [$x + $width, $y + $height - 3]
        ],
        50 => [
            [$x + 2, $y], [$x + $width - 2, $y], [$x + 2, $y + $height], [$x + $width - 2, $y + $height],
            [$x, $y + 2], [$x + $width, $y + 2], [$x, $y + $height - 2], [$x + $width, $y + $height - 2]
        ]
    ];

    foreach($blend as $pct => $points) {
        foreach($points as $point) {
            $mix = 100 - $pct;
            $current = imagecolorat($image, $point[0], $point[1]);
            $r = floor((($mix * (($current >> 16) & 0xFF)) + ($pct * (($color >> 16) & 0xFF))) / 100);
            $g = floor((($mix * (($current >> 8) & 0xFF)) + ($pct * (($color >> 8) & 0xFF))) / 100);
            $b = floor((($mix * ($current & 0xFF)) + ($pct * ($color & 0xFF))) / 100);
            imagesetpixel($image, $point[0], $point[1], imagecolorallocate($image, $r, $g, $b));
        }
    }
}

/**
 * Draw rounded corners
 *
 * Will always have a border radius of 4px. Can be used to give existing images rounded corners.
 *
 * @param resource $image Image to draw to
 * @param int   $x     X coordinate, upper left corner
 * @param int   $y     Y coordinate, upper left corner
 * @param int   $width
 * @param int   $height
 * @param int   $color
 * @param bool  $transparent
 * @param array $corners Draw corners: top left, top right, bottom left, bottom right (default all true)
 */
function rounded_corners($image, $x, $y, $width, $height, $color, $transparent = false, $corners = array(true, true, true, true)) {
    $blend = array(100 => [], 75 => [], 50 => []);

    if($corners[0]) { //top left corner
        $blend[100] = array_merge($blend[100], [[$x, $y], [$x + 1, $y], [$x, $y + 1]]);
        $blend[75] = array_merge($blend[75], [[$x + 2, $y], [$x, $y + 2]]);
        $blend[50] = array_merge($blend[50], [[$x + 1, $y + 1], [$x + 3, $y], [$x, $y + 3]]);
    }

    if($corners[1]) { //top right corner
        $blend[100] = array_merge($blend[100], [[$x + $width - 1, $y], [$x + $width - 2, $y], [$x + $width - 1, $y + 1]]);
        $blend[75] = array_merge($blend[75], [[$x + $width - 3, $y], [$x + $width - 1, $y + 2]]);
        $blend[50] = array_merge($blend[50],[[$x + $width - 2, $y + 1], [$x + $width - 4, $y], [$x + $width - 1, $y + 3]]);
    }

    if($corners[2]) { //bottom left corner
        $blend[100] = array_merge($blend[100], [[$x, $y + $height - 1], [$x + 1, $y + $height - 1], [$x, $y + $height - 2]]);
        $blend[75] = array_merge($blend[75], [[$x + 2, $y + $height - 1], [$x, $y + $height - 3]]);
        $blend[50] = array_merge($blend[50], [[$x + 1, $y + $height - 2], [$x + 3, $y + $height - 1], [$x, $y + $height - 4]]);
    }

    if($corners[3]) { //bottom right corner
        $blend[100] = array_merge($blend[100], [[$x + $width - 1, $y + $height - 1], [$x + $width - 2, $y + $height - 1], [$x + $width - 1, $y + $height - 2]]);
        $blend[75] = array_merge($blend[75], [[$x + $width - 3, $y + $height - 1], [$x + $width - 1, $y + $height - 3]]);
        $blend[50] = array_merge($blend[50], [[$x + $width - 2, $y + $height - 2], [$x + $width - 4, $y + $height - 1], [$x + $width - 1, $y + $height - 4]]);
    }

    foreach($blend as $pct => $points) {
        foreach($points as $point) {
            $mix = 100 - $pct;
            if($transparent) {
                imagesetpixel($image, $point[0], $point[1], imagecolorallocatealpha($image, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, 127 * ($pct / 100)));
            } else {
                $current = imagecolorat($image, $point[0], $point[1]);
                $r = floor((($mix * (($current >> 16) & 0xFF)) + ($pct * (($color >> 16) & 0xFF))) / 100);
                $g = floor((($mix * (($current >> 8) & 0xFF)) + ($pct * (($color >> 8) & 0xFF))) / 100);
                $b = floor((($mix * ($current & 0xFF)) + ($pct * ($color & 0xFF))) / 100);
                imagesetpixel($image, $point[0], $point[1], imagecolorallocate($image, $r, $g, $b));

            }
        }
    }
}

/**
 * Draw balloon box, arrow on bottom
 *
 * Rounded rectangle with an arrow pointing down. Position given is the coordinates the arrow points to.
 * The arrow and rectangle are anti-aliased.
 *
 * @param resource $image Image to render to
 * @param int   $x     X coordinate, arrow point
 * @param int   $y     Y coordinate, arrow point
 * @param int   $width
 * @param int   $height
 * @param int $color
 */
function balloon_top($image, $x, $y, $width, $height, $color) {
    //triangle
    $y -= 2;
    imagesetpixel($image, $x, $y, $color);
    imagefilledrectangle($image, $x - 1, $y - 1, $x + 1, $y - 1, $color);
    imagefilledrectangle($image, $x - 2, $y - 2, $x + 2, $y - 2, $color);
    imagefilledrectangle($image, $x - 3, $y - 3, $x + 3, $y - 3, $color);
    imagefilledrectangle($image, $x - 4, $y - 4, $x + 4, $y - 4, $color);
    imagefilledrectangle($image, $x - 5, $y - 5, $x + 5, $y - 5, $color);
    $blend = [
        50 => [
            [$x, $y + 1],
            [$x - 1, $y], [$x + 1, $y],
            [$x - 2, $y - 1], [$x + 2, $y - 1],
            [$x - 3, $y - 2], [$x + 3, $y - 2],
            [$x - 4, $y - 3], [$x + 4, $y - 3],
            [$x - 5, $y - 4], [$x + 5, $y - 4],
            [$x - 6, $y - 5], [$x + 6, $y - 5]
        ]
    ];

    rounded_rect($image, $x - floor($width / 2), $y - 6 - $height, $width, $height, $color);

    foreach($blend as $pct => $points) {
        foreach($points as $point) {
            $mix = 100 - $pct;
            $current = imagecolorat($image, $point[0], $point[1]);
            $r = floor((($mix * (($current >> 16) & 0xFF)) + ($pct * (($color >> 16) & 0xFF))) / 100);
            $g = floor((($mix * (($current >> 8) & 0xFF)) + ($pct * (($color >> 8) & 0xFF))) / 100);
            $b = floor((($mix * ($current & 0xFF)) + ($pct * ($color & 0xFF))) / 100);
            imagesetpixel($image, $point[0], $point[1], imagecolorallocate($image, $r, $g, $b));
        }
    }
}


/**
 * Draw balloon box, arrow on top
 *
 * Rounded rectangle with an arrow pointing up. Position given is the coordinates the arrow points to.
 * The arrow and rectangle are anti-aliased.
 *
 * @param resource $image Image to render to
 * @param int   $x     X coordinate, arrow point
 * @param int   $y     Y coordinate, arrow point
 * @param int   $width
 * @param int   $height
 * @param int $color
 */
function balloon_bottom($image, $x, $y, $width, $height, $color) {
    //triangle
    $y -= 2;
    imagesetpixel($image, $x, $y, $color);
    imagefilledrectangle($image, $x - 1, $y + 1, $x + 1, $y + 1, $color);
    imagefilledrectangle($image, $x - 2, $y + 2, $x + 2, $y + 2, $color);
    imagefilledrectangle($image, $x - 3, $y + 3, $x + 3, $y + 3, $color);
    imagefilledrectangle($image, $x - 4, $y + 4, $x + 4, $y + 4, $color);
    imagefilledrectangle($image, $x - 5, $y + 5, $x + 5, $y + 5, $color);
    $blend = [
        50 => [
            [$x, $y - 1],
            [$x - 1, $y], [$x + 1, $y],
            [$x - 2, $y + 1], [$x + 2, $y + 1],
            [$x - 3, $y + 2], [$x + 3, $y + 2],
            [$x - 4, $y + 3], [$x + 4, $y + 3],
            [$x - 5, $y + 4], [$x + 5, $y + 4],
            [$x - 6, $y + 5], [$x + 6, $y + 5]
        ]
    ];

    rounded_rect($image, $x - floor($width / 2), $y + 6, $width, $height, $color);


    foreach($blend as $pct => $points) {
        foreach($points as $point) {
            $mix = 100 - $pct;
            $current = imagecolorat($image, $point[0], $point[1]);
            $r = floor((($mix * (($current >> 16) & 0xFF)) + ($pct * (($color >> 16) & 0xFF))) / 100);
            $g = floor((($mix * (($current >> 8) & 0xFF)) + ($pct * (($color >> 8) & 0xFF))) / 100);
            $b = floor((($mix * ($current & 0xFF)) + ($pct * ($color & 0xFF))) / 100);
            imagesetpixel($image, $point[0], $point[1], imagecolorallocate($image, $r, $g, $b));
        }
    }
}
/**
 * Clamps a number between two values
 *
 * @param  integer $int Number to clamp
 * @param  integer $min Minimal value
 * @param  integer $max Maximal value
 *
 * @return  integer  Clamped value
 *
 * @throws  ErrorException  Throws an exception if $min > $max
 *
 * @package Fuzic
 */
function clamp($int, $min, $max) {
    if (!is_numeric($int) || !is_numeric($min) || !is_numeric($max)) {
        return $int;
    }

    if ($min > $max) {
        throw new ErrorException('clamp() expects the minimal value to be smaller than the maximal value');
    }

    if ($int > $max) {
        return $max;
    }

    if ($int < $min) {
        return $min;
    }

    return $int;
}

/**
 * Get short approximation for a large number
 *
 * @param integer $num Number to format
 *
 * @return  integer     Formatted number, e.g. `1.2k` for `1233`
 *
 * @package Fuzic-site
 */
function approx($num) {
    $num = intval($num);
    if ($num < 100) {
        return round(($num / 10), 0) * 10;
    }
    if ($num < 1000) {
        return round(($num / 100), 0) * 100;
    } else {
        return round(($num / 1000), 1).'k';
    }
}