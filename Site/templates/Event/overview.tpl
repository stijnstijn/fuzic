{assign var='cutoff' value=(time() - ($check_delay * 5))}
<h1>{if isset($franchise)}<i class="fa fa-umbrella"></i> {$franchise.real_name|e}{else}<i class="fa fa-calendar"></i> {$__game.name} events{/if}</h1>

<div class="column-wrapper">
    {include file='layout/calendar_control.tpl' calendar=$__calendar base_url='/rankings/events/' settings=$view_settings}

    <div class="table-wrap">
        {if count($events) > 0}
            <table class="table event-list ranked">
                {$table_headers = ['#' => 'vh', 'Event' => 'name', 'When' => 'start', 'Average' => 'average', 'Peak' => 'peak']}
                {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
                {foreach from=$events item=event name=rank}
                    <tr{if $event.end > $cutoff} class="live"{/if}>
                        <td>{($smarty.foreach.rank.index + 1 + (($view_settings.pages.page - 1) * 25))|thousands}</td>
                        <td{if $event.name != $event.short_name} class="tooltippable" title="{$event.name}"{/if}><a href="{$event.__url}">{$event.name|cutoff:40}</a></td>
                        <td{if $event.end > $cutoff} class="live">Live!{else}>{$event.start|date_shortest}{/if}</td>
                        <td>{$event.average|thousands}</td>
                        <td>{$event.peak|thousands}</td>
                    </tr>
                {/foreach}
                {include file='layout/overview_pagelist.tpl' settings=$view_settings span=6}
            </table>
        {else}
            <div class="notice">No events available.</div>
        {/if}
    </div>
</div>