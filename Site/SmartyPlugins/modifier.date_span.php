<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Formats two timestamps into a "x-y" timespan string
 *
 * @param   integer $start Start
 * @param   integer $end   End
 *
 * @return  string      Formatted timestamps
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_date_span($start, $end) {
    if (date('jn', $start) != date('jn', $end)) {
        return date(Fuzic\Constants::DATETIME_SHORT, $start).' &mdash; '.(($end > (time() - Fuzic\Config::CHECK_DELAY * 5)) ? 'Live!' : date(Fuzic\Constants::DATETIME_SHORT, $end));
    }
    return date(Fuzic\Constants::DATETIME_SHORT, $start).' &mdash; '.(($end > (time() - Fuzic\Config::CHECK_DELAY * 5)) ? 'Live!' : date(Fuzic\Constants::TIME_SHORT, $end));
}
