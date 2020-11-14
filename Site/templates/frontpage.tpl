<h1>{$__game.name} live streaming statistics</h1>

<h2><i class="fa fa-bar-chart-o"></i> Past 24 hours</h2>
<div id="frontpage-panel">
{if count($live_events) > 0}
  <div class="live-events">
    <h3><i class="fa fa-circle"></i> <span class="red">LIVE</span> now:</h3>
{foreach $live_events as $event}
    <span><a href="{$__urlpath}{$event.__url}">{$event.short_name}</a></span>
{/foreach}
  </div>
{/if}
  <div class="chart" data-times='{$24h_times}' data-viewers="{$24h_viewers}">
    <table class="highlights">
{foreach from=$highlights item=highlight}    
      <tr>
        <td>{$highlight.index}</td>
        <td>{$highlight.viewers}</td>
        <td><a href="{$__urlpath}{$highlight.event.__url}">{$highlight.event.name|e}</a></td>
        <td>{$highlight.event.start|time} - {if $highlight.live}Live now!{else}{$highlight.event.end|time}{/if}</td>
      </tr>
{/foreach}
    </table>
  </div>
  <div class="intro">
    <p>Fuzic aggregates viewer numbers for {$__game.name} live streams and turns them into rankings, overviews and statistics. <a href="{$__urlpath}/about/">More &raquo;</a></p>
  </div>
  <ul>
    <li><i class="fa fa-eye"></i> {$viewers|thousands} people currently watching <a href="{$__urlpath}/streams/">{$__game.name} streams</a></li>
    <li><i class="fa fa-trophy"></i> Today's top streamer: <a href="{$__urlpath}{$top.__url}">{$top.real_name}</a> ({$top.average|thousands} average)</li>
  </ul>
</div>

{if count($top_streams) > 0}
<hr>

<h2><i class="fa fa-star"></i> This week's top player streams <a href="{$__urlpath}/rankings/streams/" class="more">more streams</a></h2>
<ol id="top-streams">
{foreach from=$top_streams item=stream name=rank}
  <li>
    {if !empty($stream.avatar) || !empty($stream.tl_logo)}<a class="button" href="{$__urlpath}/streams/{$stream.id}/"><img src="{if !empty($stream.avatar)}{$stream.avatar}{else}{$__urlpath}/assets/images/players/{$stream.tl_logo}{/if}" alt=""></a>{else}<div class="avatar"><a href="{$__urlpath}/streams/{$stream.id}/"><i class="fa fa-user"></i></a></div>{/if}
    <h3><a href="{$__urlpath}/streams/{$stream.id}/">{$stream.real_name}</a></h3>
    <div class="rank">#{$smarty.foreach.rank.iteration}</div>
    <div class="info">
      <h4>Peak</h4>
      <p>{$stream.peak|thousands}</p>
    </div>
  </li>
{/foreach}
</ol>
{/if}

{if count($events) > 0}
<hr>

<h2><i class="fa fa-calendar"></i> Recent events <a href="{$__urlpath}/events/?order_by=start&order=desc" class="more">more events</a></h2>
<ol id="top-events">
{foreach from=$events item=event}
  <li>
    <h3><a href="{$__urlpath}{$event.__url}">{$event.name|e}</a></h3>
    <ul class="info">
      <li>
        <h4>Peak</h4>
        <p>{$event.peak|thousands}</p>
      </li>
      <li>
        <h4>Average</h4>
        <p>{$event.average|thousands}</p>
      </li>
      <li class="tooltippable" title="Viewers&times;hours">
        <h4>V&times;H</h4>
        <p>{$event.vh|thousands}</p>
      </li>
    </ul>
  </li>
{/foreach}
</ol>
{/if}