<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */

/**
 * Outputs HTML select box
 *
 * @param   array  $options Selectbox options. Mapped as `value` => `label`.
 * @param   string $current Current value.
 * @param   string $name    Name of the select box.
 *
 * @return  string              Select box HTML.
 *
 * @throws  ErrorException      If `$options` is not an array
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
function smarty_modifier_selectbox($options, $current = '', $name = '') {
    if (!is_array($options)) {
        throw new ErrorException('Selectbox modifier expects an array of items as input');
    }

    $html = '';
    $html .= '<select name="'.$name.'">'."\n";
    foreach ($options as $value => $label) {
        $html .= '  <option value="'.htmlentities($value).'"'.(($value == $current) ? ' selected' : '').'>'.htmlentities($label)."</option>\n";
    }
    $html .= "</select>\n";

    return $html;
}
