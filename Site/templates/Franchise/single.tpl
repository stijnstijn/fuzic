<h1>{$franchise.real_name|e}</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/events/' settings=$view_settings}

  <div class="table-wrap">
{if count($events) > 0}
    <table class="table event-list">
      {$table_headers = ['Event' => 'name', 'When' => 'start', 'Average' => 'average', 'Peak' => 'peak', 'V&times;H' => 'vh']}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
{foreach from=$events item=event}
      <tr>
        <td><a href="{$event.__url}">{$event.name}</a></td>
        <td>{$event.start|date_shortest}</td>
        <td>{$event.average|thousands}</td>
        <td>{$event.peak|thousands}</td>
        <td>{$event.vh|thousands}</td>
      </tr>
{/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings span=5}
    </table>
{else}
    <div class="notice">No events available.</div>
{/if}
  </div>
</div>