<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Format timestamp to time
 *
 * @param   integer $time Timestamp to format
 *
 * @return  string      Formatted timestamp
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_time($time) {
    return date('H:i', $time);
}
