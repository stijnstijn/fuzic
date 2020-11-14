<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Get shortened month name for month number
 *
 * @param   integer $number Number
 *
 * @return  string      Shortened month name
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_month_short($number) {
    $number = clamp($number, 1, 12);
    return date("M", mktime(0, 0, 0, $number, 10));
}
