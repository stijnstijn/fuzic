<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Show first word of string only
 *
 * @param   string $string String to process
 *
 * @return  string              First word
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_cutoff($string, $length) {
    if(strlen($string) >= $length) {
        return substr($string, 0, $length).'...';
    }
    return $string;
}
