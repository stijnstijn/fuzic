<div class="column-wrapper">
  <div class="table-wrap">
    <table class="sessions-table">
      {$table_headers = ['Event' => 'name', 'When' => 'start']}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
  {foreach from=$data item=event}
      <tr>
        <td>{if isset($event.franchise) && isset($event.franchise.__id) && $event.franchise.__id != $indie_ID && is_array($event.franchise)}<a href="{$event.franchise.__url}" {if $event.franchise.real_name != $event.franchise.tag} title="{$event.franchise.real_name}"{/if} class="{if $event.franchise.real_name != $event.franchise.tag}tooltippable {/if}inline-badge">{$event.franchise.tag}</a>{/if}<a href="{$event.__url}">{$event.name}</a></td>
        <td>{$event.start|date_format:'%e %B %Y'}</td>
      </tr>
  {/foreach}
    </table>
  </div>
</div>