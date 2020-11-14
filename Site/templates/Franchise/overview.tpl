<h1><i class="fa fa-umbrella"></i> {$__game.name} event franchises</h1>

<div class="column-wrapper">
  {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/franchises/' settings=$view_settings}

  <div class="table-wrap">
{if count($franchises) > 0}
    <table class="table streams-table">
        {$table_headers = ['Franchise' => 'name']}
        {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
{foreach from=$franchises item=franchise}
      <tr>
        <td><a href="{$franchise.__url}">{$franchise.real_name|e}</a></td>
      </tr>
{/foreach}
      {include file='layout/overview_pagelist.tpl' settings=$view_settings span=1}
    </table>
{else}
    <div class="notice">No franchises available.</div>
{/if}

  </div>
</div>