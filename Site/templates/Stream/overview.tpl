<h1>{if $subset == 'players'}<i class="fa fa-gamepad"></i> Player streams{else if $subset == 'casters'}<i class="fa fa-microphone"></i> Event streams{else}<i class="fa fa-video-camera"></i> {$__game.name} streamers{/if}</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/streams/' settings=$view_settings}

  <div class="table-wrap">
{if count($streams) > 0}
    <table class="table streams-table">
        {$table_headers = ['Stream' => 'real_name', 'Status' => 'last_status', 'Last seen' => 'last_seen']}
        {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
{foreach from=$streams item=stream}
      <tr>
        <td><a href="{$stream.__url}">{if $stream.notable == 1}<i class="fa fa-star tooltippable" title="Notable streamer"></i> {/if}{$stream.real_name}</a>{if false && !empty($stream.team)} <a class="team" href="/{$__urlpath}/teams/{$team_name|friendly_url}/">{$stream.team_name|firstword}</a>{/if} <i class="tooltippable fa fa-{if $stream.player == 1}gamepad{else}microphone{/if}" title="{if $stream.player == 1}Player{else}Event stream{/if}"></i>{if isset($stream.team) && is_array($stream.team) && $stream.team.__id != $teamless_ID}<a class="inline-badge" href="{$stream.team.__url}">{$stream.team.team}</a>{/if}</td>
        <td{if !empty($stream.last_status) && strlen($stream.last_status) > 40} title="{$stream.last_status|e}" class="tooltippable"{/if}>{if !empty($stream.last_status)}{$stream.last_status|e|truncate:40:'...'}{else}(No status){/if}</td>
        <td{if $stream.last_seen > (time() - ($check_delay * 4))} class="live">Live!{else}>{$stream.last_seen|date_shortest}{/if}</td>
      </tr>
{/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings span=2}
    </table>
{else}
    <div class="notice">No streams available.</div>
{/if}

  </div>
</div>