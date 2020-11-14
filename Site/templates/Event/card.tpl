  <div class="card event-card">
    <div class="profile">
      <header>
        <p class="date">{$event.start|date_span:$event.end}</p>
        <h2><a href="{$event.__url}">{$event.name}</a></h2>
        <p class="franchise">{if isset($franchise) && $franchise}<a href="{$franchise.__url}">{$franchise.real_name|e}</a>{/if}</p>
{if !empty($event.wiki) || !empty($event.tl_id)}
        <div class="link-list">
          <ul>
      {if !empty($event.wiki)}
            <li><i class="fa fa-globe"></i> <a href="{if substr($event.wiki, 0, 4) != 'http'}http://wiki.teamliquid.net/{/if}{$event.wiki}" rel="external">Liquipedia</a></li>
      {/if}
      {if !empty($event.tl_id) && substr($event.tl_id, 0, 6) != 'abios-'}
            <li><i class="fa fa-calendar"></i> <a href="http://www.teamliquid.net/calendar/{date('Y', $event.start)}/{date('m', $event.start)}/#event_{$event.tl_id}" rel="external">TeamLiquid</a></li>
      {/if}
          </ul>
        </div>
{/if}
      </header>
      <ul class="statistics{if $event.end > (time() - ($check_delay * 5))} live{/if}">
        <li>
          <h3>Time</h3>
          <p class="event-time">{($event.end - $event.start)|time_approx}</p>
        </li>
        <li>
          <h3>Average</h3>
          <p class="event-average">{if isset($stats)}{$stats.average|thousands}{else}{$event.average|thousands}{/if}</p>
        </li>
        <li>
          <h3>Peak</h3>
          <p class="event-peak">{if isset($stats)}{$stats.peak|thousands}{else}{$event.peak|thousands}{/if}</p>
        </li>
        <li>
          <h3>Viewers &#215; Hours</h3>
          <p class="event-vh">{if isset($stats)}{$stats.vh|thousands}{else}{$event.vh|thousands}{/if}</p>
        </li>
        <li>
          <h3>Month rank</h3>
          <p class="event-month-rank">{if isset($rank_month) && $stats.vh != 0}<a href="{$__urlpath}/rankings/events/{date('Y', $event.start)}/month/{date('n', $event.start)}/?page={ceil(($rank_month+1) / 25)}">#{($rank_month+1)|thousands}</a>{else}&mdash;{/if}</p>
        </li>
        <li>
          <h3>All-time rank</h3>
          <p class="event-alltime-rank">{if isset($rank_all_time) && $stats.vh != 0}<a href="{$__urlpath}/events/?order_by=vh&order=desc&page={ceil(($rank_all_time+1) / 25)}">#{($rank_all_time+1)|thousands}</a>{else}&mdash;{/if}</p>
        </li>
{if $event.end > (time() - ($check_delay * 5))}
        <li class="live-notice">
          <span class="live-badge"><i class="fa fa-circle"></i> Live</span>
          This event is {if $current}currently broadcasting {if is_array($current.player1)}<a href="{$current.player1.__url}">{$current.player1.__label|e}</a>{else}{$current.player1|e}{/if} vs {if is_array($current.player2)}<a href="{$current.player2.__url}">{$current.player2.__label|e}</a>{else}{$current.player2|e}{/if}{else}being broadcasted right now{/if}! Viewer numbers will not be finalized until {if $current}the event{else}it{/if} ends.
        </li>
{/if}
      </ul>
    </div>
  </div>