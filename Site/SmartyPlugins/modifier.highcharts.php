<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Format data so the Highcharts Javascript will be able to parse it
 *
 * @param   array $array Array expected to contain keys `data` (datapoints) and
 *                       `labels` (timestamps)
 *
 * @return  string  The data formatted as HTML attributes parseable by the site's scripts
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_highcharts($array) {
    $data = '[';
    foreach ($array['data'] as $value) {
        $data .= ''.intval($value).',';
    }

    $labels = '[';
    foreach ($array['labels'] as $label) {
        $labels .= '"'.addslashes($label).'",';
    }

    return 'data-chart="'.substr($data, 0, -1).']" data-labels=\''.substr($labels, 0, -1)."]'";
}
