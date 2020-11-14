<h1><i class="fa fa-video-camera"></i> {if $ranking.type == 'month'}{$ranking.period|month}{else}Week {$ranking.period}{/if} {$ranking.year} stream ranking</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/streams/' settings=$settings}

  <div class="table-wrap">
{if (($ranking.type == 'month' && $ranking.period == date('n')) || ($ranking.type == 'week' && $ranking.period == date('W'))) && $ranking.year == date('Y')}<div class="notice inline"><i class="fa fa-clock-o"></i> This is the current {$ranking.type}. Rankings will not be finalized until it ends.</div>{/if}
{if count($settings.items) > 0}
    <table class="table streams-table rank-table{if isset($subset) && !empty($subset)} relative{/if}">
      {$table_headers = ['#' => 'rank', 'Stream' => '', 'Average' => 'average', 'Peak' => 'peak', 'Time' => 'time', 'V&times;H' => 'vh']}
      {include file='layout/overview_pagelist.tpl' settings=$settings headers=$table_headers}
    {foreach from=$settings.items item=stream name=ranking}
      <tr>
        <td>{if isset($subset) && !empty($subset)}{($smarty.foreach.ranking.iteration + $settings.offset)|thousands} <span class="relative-rank tooltippable" title="Overall rank (players and casters)"> ({$stream.rank|thousands})</span>{else}{$stream.rank|thousands}{/if}</td>
        <td><a href="/streams/{$stream.id}/">{if isset($stream.notable) && $stream.notable == 1}<i class="fa fa-star tooltippable" title="Notable streamer"></i> {/if}{$stream.real_name}</a> <i class="tooltippable fa fa-{if $stream.player == 1}gamepad{else}microphone{/if}" title="{if $stream.player == 1}Player{else}Caster{/if}"></i></td>
        <td>{$stream.average|thousands}</td>
        <td>{$stream.peak|thousands}</td>
        <td class="tooltippable" title="&#177;{round($stream.time/3600)}h">{$stream.time|time_approx}</td>
        <td>{$stream.vh|thousands}</td>
      </tr>
    {/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$settings span=6}
    </table>
{else}
    <div class="notice">No ranking available.</div>
{/if}
  </div>
</div>