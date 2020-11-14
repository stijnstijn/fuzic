<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Get month name for month number
 *
 * @param   integer $number Number
 *
 * @return  string      Month name
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_month($number) {
    $number = clamp($number, 1, 12);
    return date("F", mktime(0, 0, 0, $number, 10));
}
