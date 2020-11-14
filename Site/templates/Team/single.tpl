<h1>{if !empty($team.logo)}<img src="{$__urlpath}/assets/images/logos/{$team.logo}" class="header-logo">{else}<i class="fa fa-users"></i>{/if} {$team.team|e}</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/streams/'}

  <div class="table-wrap">
{if count($streams) > 0}
    <table class="table streams-table">
        {$table_headers = ['Player' => 'real_name', 'Last seen' => 'last_seen']}
        {include file='layout/overview_pagelist.tpl' headers=$table_headers}
{foreach from=$streams item=stream}
      <tr>
        <td><a href="{$stream.__url}">{$stream.real_name}</a></td>
        <td>{$stream.last_seen|date_short}</td>
      </tr>
{/foreach}
    </table>
{else}
    <div class="notice">No streams available.</div>
{/if}

  </div>
</div>