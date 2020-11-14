{include 'layout/urlbits.tpl' scope='parent'}
{if isset($settings.pages) && $settings.pages.count > 1}
  <tr class="nav">
    <td{if isset($span) && $span > 1} colspan="{$span}"{/if}{if isset($headers) && count($headers) > 1} colspan="{count($headers)}"{/if}>
      <nav class="page-list">
      {if $settings.pages.page > 1}
        <a href="?page={$settings.pages.page-1}{$settings.urlbit}" class="previous"><i class="fa fa-arrow-left"></i></a>
      {/if}
      {foreach from=$settings.pages.links item=page}
        {if $page === false}&hellip;{else}<a href="?page={$page}{$settings.urlbit}"{if $page==$settings.pages.page} class="current"{/if}>{$page|e}</a>{/if}
      {/foreach}
      {if $settings.pages.page < $settings.pages.count}
        <a href="?page={$settings.pages.page+1}{$settings.urlbit}" class="next"><i class="fa fa-arrow-right"></i></a>
      {/if}
      </nav>
    </td>
  </tr>
{/if}
{if isset($headers)}
  <tr>
    {foreach from=$headers item=key key=header}
      <th>{if isset($settings) && !empty($key)}<a href="?order_by={$key|e}&amp;order={if $settings.order=='ASC'}desc{else}asc{/if}{$in_bit}">{/if}{if $header == 'V&times;H'}<abbr class="tooltippable" title="Viewers &times; hours">{/if}{$header}{if $header == 'V&times;H'}</abbr>{/if}{if isset($settings) && !empty($key)}</a>{if $settings.order_by==$key} <i class="fa fa-caret-{if $settings.order=='ASC'}up{else}down{/if}"></i>{/if}{/if}</th>
    {/foreach}
  </tr>
{/if}