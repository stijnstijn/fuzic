<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Format timestamp to short date
 *
 * @param   integer $time Timestamp to format
 *
 * @return  string      Formatted timestamp
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_date_short($time) {
    return date(Fuzic\Constants::DATETIME_SHORT, $time);
}
