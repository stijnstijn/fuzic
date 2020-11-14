<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Escapes HTML, and nothing but
 *
 * Similar to escape modifier, but simpler, and shorter
 *
 * @param   string $string String to escape
 *
 * @return  string              Escaped string
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_e($string) {
    return htmlspecialchars($string, ENT_QUOTES);
}
