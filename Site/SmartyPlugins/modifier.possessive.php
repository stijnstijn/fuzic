<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Adds possessive to noun
 *
 * @param   string $string The noun
 *
 * @return  string              The noun, plus possessive suffix
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_possessive($string) {
    return (substr(strip_tags($string), -1, 1) == 's') ? $string."'" : $string."'s";
}
