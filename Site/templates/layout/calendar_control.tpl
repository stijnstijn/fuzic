{if !empty($settings.query)}{$querybit="?query={$settings.query}"}{else}{$querybit=''}{/if}
{if !empty($settings.order_by) && is_string($settings.order_by)}{$sort_bit="&amp;order_by={$settings.order_by}&amp;order={strtolower($settings.order)}"}{else}{$sort_bit=''}{/if}
<div class="sidebar-wrap">
  <hr>
{if isset($ranking.year)}
  <h3>Period</h3>
{foreach from=$calendar item=months key=year}
  <nav class="calendar" id="year-{$year}">
    <h4><a href="#" class="prev"><i class="fa fa-arrow-left"></i></a> {$year} <a href="#" class="next"><i class="fa fa-arrow-right"></i></a></h4>
    <ol class="months" id="nav-months-{$year}">
{foreach from=$months key=month item=weeks}
      <li>
        <h5{if $ranking.type == 'month' && $ranking.period == $month && $ranking.year == $year} class="current"{/if}><a href="{$__urlpath}{$base_url}{$year}/month/{$month}/{$querybit}">{$month|month_short}</a></h5>
        <ol class="weeks">
{foreach from=$weeks item=week}
          <li{if $ranking.type == 'week' && $ranking.period == $week.w && $ranking.year == $week.y} class="current"{/if}><a href="{$__urlpath}{$base_url}{$week.y}/week/{$week.w}/{$querybit}">{$week.w}</a></li>
{/foreach}
        </ol>
      </li>
{/foreach}
    </ol>
  </nav>
{/foreach}
{if isset($subset)}
  
  <hr>
  <h3>Show</h3>
  <nav class="show-only"><ul>
    <li><a href="{$__urlpath}{$base_url}{$ranking.year}/{$ranking.type}/{$ranking.period}/"><i class="fa fa{if empty($subset) || $subset == 'all'}-check{/if}-square"></i> All streams</a></li>
    <li><a href="{$__urlpath}{$base_url}{$ranking.year}/{$ranking.type}/{$ranking.period}/players/"><i class="fa fa{if $subset == 'players'}-check{/if}-square"></i> Players only</a></li>
    <li><a href="{$__urlpath}{$base_url}{$ranking.year}/{$ranking.type}/{$ranking.period}/casters/"><i class="fa fa{if $subset == 'casters'}-check{/if}-square"></i> Event streams only</a></li>
  </ul></nav>
{/if}
  <hr>
{/if}  
  <h3>Filter</h3>
  <form method="get" id="filter-form">
    <input type="text" name="query"{if isset($settings.query) && !empty($settings.query)} value="{$settings.query|e}"{/if}>
    <button><i class="fa fa-search"></i></button>
  </form>
  <hr>
</div>