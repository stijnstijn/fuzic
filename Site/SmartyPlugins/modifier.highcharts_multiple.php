<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Format event data so the Highcharts Javascript will be able to parse it
 *
 * @param   array $array Array expected to contain keys `data` (datapoints),
 *                       `labels` (timestamps) and `legend` (streams)
 *
 * @return  string  The data formatted as HTML attributes parseable by the site's scripts
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_highcharts_multiple($array) {
    $viewers = '[';
    foreach ($array['data'] as $seq) {
        $viewers .= json_encode_ints($seq).',';
    }

    return 'data-chart="'.substr($viewers, 0, -1).']" data-labels=\''.json_encode_ints($array['labels'])."' data-legend='".json_encode_strings($array['legend'])."'";
}
