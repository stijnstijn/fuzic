<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Transform string for URLs
 *
 * @param   string $string String to escape
 *
 * @return  string              Escaped string
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_friendly_url($string) {
    return friendly_url($string);
}
