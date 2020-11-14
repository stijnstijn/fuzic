{if !isset($id)}
<div class="warning">No ID set for this chart!</div>
{/if}
<article class="controllable-chart" data-per="{$type}">
  <div class="chart-controls">
    <h4>Show:</h4>
    <ul>
      <li{if !isset($__get.chart_type) || $__get.chart_type == 'rank'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=rank"><i class="fa fa-arrow-right"></i> Rank</a></li>
      <li{if isset($__get.chart_type) && $__get.chart_type == 'average'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=average"><i class="fa fa-arrow-right"></i> Average</a></li>
      <li{if isset($__get.chart_type) && $__get.chart_type == 'peak'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=peak"><i class="fa fa-arrow-right"></i> Peak</a></li>
      <li{if isset($__get.chart_type) && $__get.chart_type == 'vh'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=vh"><i class="fa fa-arrow-right"></i> Viewer hours</a></li>
      <li{if isset($__get.chart_type) && $__get.chart_type == 'time'} class="current"{/if}><a href="?chart={$id}&amp;chart_type=time"><i class="fa fa-arrow-right"></i> Time streamed</a></li>
    </ul>
  </div>
  <div class="make-chart" data-label="Rank" {$data|highcharts}></div>
</article>