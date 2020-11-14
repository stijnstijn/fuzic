<h1><i class="fa fa-users"></i> {$__game.name} eSports Teams</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/streams/' settings=$view_settings}

  <div class="table-wrap">
{if count($teams) > 0}
    <table class="table teams-table">
      <tr>
        <th>Team</th>
        <th>Players</th>
      </tr>
      {include file='layout/overview_pagelist.tpl' settings=$view_settings span=2}
{foreach from=$teams item=team}
      <tr>
        <td><a href="{$team.__url}">{if !empty($team.logo)}<img src="{$__urlpath}/assets/images/logos/{$team.logo}" alt="">{else}<div class="avatar"><i class="fa fa-users"></i></div>{/if}</a> <a href="{$team.__url}" class="teamname">{$team.team|e}</a></td>
        <td>{foreach from=$team.players item=player name=players}<a href="{$player.__url}">{$player.real_name}</a>{if $smarty.foreach.players.iteration < count($team.players)} &bull; {/if}{if $smarty.foreach.players.iteration == 4 && $smarty.foreach.players.iteration < count($team.players)}<a href="{$team.__url}">{count($team.players) - {$smarty.foreach.players.iteration}} more</a>{break}{/if}{/foreach}</td>
      </tr>
{/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings span=2}
    </table>
{else}
    <div class="notice">No teams available.</div>
{/if}

  </div>
</div>