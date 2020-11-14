<h1><i class="fa fa-exchange"></i> Event comparison</h1>

{$cutoff=(time() - ($check_delay * 5))}{if $event1.end > $cutoff || $event2.end > $cutoff}

<p class="notice">Note: events may not be finished yet. Viewer numbers will not be finalized until both events have finished!</p>
{/if}

<div class="comparison-wrap">
  <article class="event-comparison">
    <header>
      <p class="date">{$event1.start|date_span:$event1.end}</p>
      <h2><a href="{$__urlpath}{$event1.__url}">{$event1.name}</a></h2>
      <p class="franchise">{if $event1.franchise}<a href="{$event1.franchise.__url}">{$event1.franchise.name|e}</a>{else}(Independent event){/if}</p>
    </header>
    <ul>
      <li{if $event2.time < $event1.time} class="winner"{/if}>
        <h3>Length</h3>
        <p>{$event1.time|time_approx}</p>
      </li>
      <li{if $event2.average < $event1.average} class="winner"{/if}>
        <h3>Average</h3>
        <p>{$event1.average|thousands}</p>
      </li>
      <li{if $event2.peak < $event1.peak} class="winner"{/if}>
        <h3>Peak</h3>
        <p>{$event1.peak|thousands}</p>
      </li>
      <li{if $event2.vh < $event1.vh} class="winner"{/if}>
        <h3>Viewers &times; Hours</h3>
        <p>{$event1.vh|thousands}</p>
      </li>
      <li{if $event2.month > $event1.month || $event2.month == $event1.month} class="winner"{/if}>
        <h3>Month rank</h3>
        <p>{$event1.month|thousands}</p>
      </li>
      <li{if $event2.all_time > $event1.all_time || $event2.all_time == $event1.all_time} class="winner"{/if}>
        <h3>All-time rank</h3>
        <p>{$event1.all_time|thousands}</p>
      </li>
      <li class="permalink"><a href="{$event1.__url}"><i class="fa fa-external-link"></i> Event details</a></li>
    </ul>
  </article>
  
  <ul class="comparison-difference">
{$time_delta=round(($event1.time / $event2.time) * 100, 0)}  
    <li class="{if $time_delta > 100}green{elseif $time_delta < 100}red{else}grey{/if}">
      <i class="fa fa-{if $time_delta > 100}arrow-circle-up{elseif $time_delta < 100}arrow-circle-down{else}circle{/if}"></i> {$time_delta - 100}%
    </li>
{$average_delta=round(($event1.average / $event2.average) * 100, 0)}  
    <li class="{if $average_delta > 100}green{elseif $average_delta < 100}red{else}grey{/if}">
      <i class="fa fa-{if $average_delta > 100}arrow-circle-up{elseif $average_delta < 100}arrow-circle-down{else}circle{/if}"></i> {$average_delta - 100}%
    </li>
{$peak_delta=round(($event1.peak / $event2.peak) * 100, 0)}  
    <li class="{if $peak_delta > 100}green{elseif $peak_delta < 100}red{else}grey{/if}">
      <i class="fa fa-{if $peak_delta > 100}arrow-circle-up{elseif $peak_delta < 100}arrow-circle-down{else}circle{/if}"></i> {$peak_delta - 100}%
    </li>
{$vh_delta=round(($event1.vh / $event2.vh) * 100, 0)}  
    <li class="{if $vh_delta > 100}green{elseif $vh_delta < 100}red{else}grey{/if}">
      <i class="fa fa-{if $vh_delta > 100}arrow-circle-up{elseif $vh_delta < 100}arrow-circle-down{else}circle{/if}"></i> {$vh_delta - 100}%
    </li>
  </ul>
  
  <article class="event-comparison">
    <header>
      <p class="date">{$event2.start|date_span:$event2.end}</p>
      <h2><a href="{$__urlpath}{$event2.__url}">{$event2.name}</a></h2>
      <p class="franchise">{if $event2.franchise}<a href="{$event2.franchise.__url}">{$event2.franchise.name|e}</a>{else}(Independent event){/if}</p>
    </header>
    <ul>
      <li{if $event2.time > $event1.time} class="winner"{/if}>
        <h3>Length</h3>
        <p>{$event2.time|time_approx}</p>
      </li>
      <li{if $event2.average > $event1.average} class="winner"{/if}>
        <h3>Average</h3>
        <p>{$event2.average|thousands}</p>
      </li>
      <li{if $event2.peak > $event1.peak} class="winner"{/if}>
        <h3>Peak</h3>
        <p>{$event2.peak|thousands}</p>
      </li>
      <li{if $event2.vh > $event1.vh} class="winner"{/if}>
        <h3>Viewers &times; Hours</h3>
        <p>{$event2.vh|thousands}</p>
      </li>
      <li{if $event2.month < $event1.month || $event2.month == $event1.month} class="winner"{/if}>
        <h3>Month rank</h3>
        <p>{$event2.month|thousands}</p>
      </li>
      <li{if $event2.all_time < $event1.all_time || $event2.all_time == $event1.all_time} class="winner"{/if}>
        <h3>All-time rank</h3>
        <p>{$event2.all_time|thousands}</p>
      </li>
      <li class="permalink"><a href="{$event2.__url}">Event details <i class="fa fa-external-link"></i></a></li>
    </ul>
  </article>
</div>