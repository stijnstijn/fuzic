{if !isset($order_bit)}
  {if isset($view) && !empty($view.order_by) && is_string($view.order_by)}
    {$order_bit="&amp;order_by={$view.order_by}&amp;order={strtolower($view.order)}"}
  {else}
    {$order_bit=''}
  {/if}
{/if}

{if !isset($filter_bit)}
  {if isset($view.filters) && !empty($view.filters)}
    {$filter_bit = ''}
    {foreach from=$view.filters key=column item=value}
      {$filter_bit = "`$filter_bit`&amp;`$column`=`$value`"}
    {/foreach}
  {else}
    {$filter_bit = ''}
  {/if}
{/if}


{if !isset($in_bit)}
  {if isset($view.in)}
    {$in_bit="&amp;in={$view.in}"}
  {else}
    {$in_bit=''}
  {/if}
{/if}

{if !isset($search_bit)}
  {if isset($view.query)}
    {$search_bit = "&amp;query={$view.query}"}
  {else}
    {$search_bit = ''}
  {/if}
{/if}

{if !isset($url_bit)}
  {$url_bit = "`$order_bit``$filter_bit``$in_bit``$search_bit`"}
{/if}