<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Formats a number with decimals, etc
 *
 * @param   string $string Number to format
 *
 * @return  string                  Formatted number
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_thousands($string) {
    return number_format(intval($string));
}
