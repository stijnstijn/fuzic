{if !isset($id)}
<div class="warning">No ID set for this chart!</div>
{/if}
<article class="controllable-chart event">
  <div class="chart-stats">
    <ul>
      <li>
        <h4>Time</h4>
        <p class="event-time">{($event.end - $event.start)|time_approx}</p>
      </li>
      <li>
        <h4>Viewers &times; hours</h4>
        <p class="event-vh">{$stats.vh|thousands}</p>
      </li>
      <li>
        <h4>Average</h4>
        <p class="event-average">{$stats.average|thousands}</p>
      </li>
      <li>
        <h4>Peak</h4>
        <p class="event-peak">{$stats.peak|thousands}</p>
      </li>
    </ul>
  </div>
  <div class="chart-matches">
{if isset($matches) && $matches}<ul>{foreach from=$matches item=match}
  <li>
    <h4>{$match.match|e}</h4>
    <p>{$match.rel_start}</p>
    <p>{$match.rel_end}</p>
  </li>
{/foreach}</ul>{/if}
  </div>
  <div class="make-chart event multiple" data-label="Viewers" {$chart|highcharts_multiple}></div>
</article>