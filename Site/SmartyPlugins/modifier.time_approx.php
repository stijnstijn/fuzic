<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Formats a number of seconds to a textual approximation (for example, "4h 5m")
 *
 * @param   integer $time Number to format
 *
 * @return  string      Formatted time.
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_time_approx($time) {
    return time_approx($time);
}
