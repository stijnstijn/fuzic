{include file='Stream/card.tpl' stream=$stream stats=$stats}

<hr>

{if $stream.provider == 'douyu'}<div class="notice inline"><i class="fa fa-exclamation-circle"></i> This is a DouyuTV stream. As DouyuTV viewer numbers are unreliable and inaccurate they are not tracked.</div>{/if}

<section>
  <h3><i class="fa fa-bar-chart-o"></i> {if $stats_per == 'week'}Week{else}Month{/if}ly performance <span
      class="sort-by">{if $stats_per == 'week'}<a href="?per=month">Show monthly</a>{else}<a href="?per=week">Show
        weekly</a>{/if}</h3>
  <article class="controllable-chart stream" data-per="{$stats_per}" data-offset="0">
    <div class="chart-nav">
      <h4>Show:</h4>
      <ul>
        <li><i class="fa fa-arrow-left"></i> <a class="previous" href="?per={$stats_per}&amp;{$stats_per}-offset={$progression.previous}">Earlier</a></li>
        <li class="now"><span class="from">{$progression.first}</span> &mdash; <span class="to">{$progression.last}</span></li>
        <li{if $progression.next < 0} class="hidden"{/if}><a class="next" href="?per={$stats_per}&amp;{$stats_per}-offset={$progression.next}">Later</a> <i class="fa fa-arrow-right"></i></li>
      </ul>
    </div>
    <div class="chart-controls">
      <ul>
        <li{if !isset($__get.chart_type) || $__get.chart_type == 'rank'} class="current"{/if}><a href="?chart={$stats_per}ly&amp;chart_type=rank"><i class="fa fa-arrow-right"></i> Rank</a></li>
        <li{if isset($__get.chart_type) && $__get.chart_type == 'average'} class="current"{/if}><a href="?chart={$stats_per}ly&amp;chart_type=average"><i class="fa fa-arrow-right"></i> Average</a></li>
        <li{if isset($__get.chart_type) && $__get.chart_type == 'peak'} class="current"{/if}><a href="?chart={$stats_per}ly&amp;chart_type=peak"><i class="fa fa-arrow-right"></i> Peak</a></li>
        <li{if isset($__get.chart_type) && $__get.chart_type == 'vh'} class="current"{/if}><a href="?chart={$stats_per}ly&amp;chart_type=vh"><i class="fa fa-arrow-right"></i> Viewer hours</a></li>
        <li{if isset($__get.chart_type) && $__get.chart_type == 'time'} class="current"{/if}><a href="?chart={$stats_per}ly&amp;chart_type=time"><i class="fa fa-arrow-right"></i> Time streamed</a></li>
      </ul>
    </div>
    <div class="make-chart" data-label="Rank" {$progression|highcharts}></div>
  </article>
</section>
{if isset($sessions) && $sessions &&count($sessions.items) > 0}
  <hr>
  <section class="full-width">
    <h3><i class="fa fa-video-camera"></i> Recent streaming sessions{if $sessions.count > 10} <span class="byline">
      Total: {$sessions.count|thousands}</span>{/if}</h3>

    <div class="session-info">
      {include file='Session/list.tpl' data=$sessions.items view_settings=$sessions}
    </div>
  </section>
{/if}
{if isset($events) && $events && count($events.items) > 0}
  <hr>
  <section class="full-width">
    <h3><i class="fa fa-calendar"></i> Events broadcasted by this stream{if $events.count > 10} <span class="byline">
      Total: {$events.count|thousands}</span>{/if}</h3>
    {include file='Event/list.tpl' data=$events.items view_settings=$events}
  </section>
{/if}
{if isset($events_played) && $events_played && count($events_played.items) > 0}
  <hr>
  <section class="full-width">
    <h3><i class="fa fa-keyboard-o"></i> Events this streamer played in{if $events_played.count > 10} <span
        class="byline">Total: {$events_played.count|thousands}</span>{/if}</h3>
    {include file='Event/list.tpl' data=$events_played.items view_settings=$events_played}
  </section>
{/if}