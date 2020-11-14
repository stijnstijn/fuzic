<h1><i class="fa fa-calendar"></i> {if $ranking.type == 'month'}{$ranking.period|month}{else}Week {$ranking.period}{/if} {$ranking.year} event ranking</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/events/' settings=$settings}
  
  <div class="table-wrap">
{if count($settings.items) > 0}
    <table class="table events-table rank-table">
      {$table_headers = ['#' => 'rank', 'Event' => '', 'Average' => 'average', 'Peak' => 'peak', 'Time' => 'time', 'V&times;H' => 'vh']}
      {include file='layout/overview_pagelist.tpl' settings=$settings headers=$table_headers}
    {foreach from=$settings.items item=event}
      <tr>
        <td>{$event.rank|thousands}</td>
        <td{if $event.name != $event.short_name} class="tooltippable" title="{$event.name}"{/if}><a href="/events/{$event.id}-{$event.name|friendly_url}/">{$event.short_name|e}</a></td>
        <td>{$event.average|thousands}</td>
        <td>{$event.peak|thousands}</td>
        <td>{$event.time|time_approx}</td>
        <td>{$event.vh|thousands}</td>
      </tr>
    {/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$settings span=6}
    </table>
{else}
    <div class="notice">No events available.</div>
{/if}
  </div>
</div>