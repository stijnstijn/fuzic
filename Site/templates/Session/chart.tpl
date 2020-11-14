{if !isset($id)}
<div class="warning">No ID set for this chart!</div>
{/if}
<article class="controllable-chart session">
  <nav>
    <ol>
      <li>{if $previous}<i class="fa fa-arrow-left"></i> <a href="{$previous.__url}">Previous</a>{/if}</li>
      <li class="now">{$session.start|date_span:$session.end}</li>
      <li>{if $next}<a href="{$next.__url}">Next</a> <i class="fa fa-arrow-right"></i>{/if}</li>
    </ol>
  </nav>
  <div class="chart-stats">
    <ul>
      <li>
        <h4>Time</h4>
        <p>{$session.time|time_approx}</p>
      </li>
      <li>
        <h4>Viewers &#215; hours</h4>
        <p>{$session.vh|thousands}</p>
      </li>
      <li>
        <h4>Average</h4>
        <p>{$session.average|thousands}</p>
      </li>
      <li>
        <h4>Peak</h4>
        <p>{$session.peak|thousands}</p>
      </li>
    </ul>
  </div>
  <div class="make-chart session" data-label="Viewers" {$chart|highcharts}>This chart requires Javascript for rendering.</div>
</article>