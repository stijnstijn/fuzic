 
  {include file='Event/card.tpl' event=$event stats=$stats}
  
  <hr>

  <section>
    <h3><i class="fa fa-bar-chart-o"></i> Event viewer numbers <span class="sort-by"><a href="export/">Export as image</a></span></h3>
    {include file='Event/chart.tpl' chart=$chart id='event' stats=$stats}
  </section>
  
  <hr>
  
  <section>
    <h3><i class="fa fa-list-alt"></i> Streams broadcasting this event</h3>
    <table class="table event-stream-table">
      <tr>
        <th>Stream</th>
        <th>Coverage</th>
        <th>Average</th>
        <th>Peak</th>
        <th>V&times;H</th>
      </tr>
{foreach from=$streams item=stream}
      <tr id="stream-{$stream.id}">
        <td><a href="{$stream.__url}">{if $stream.tl_featured == 1 || $stream.wiki != '' || $stream.twitter != ''}<i class="fa fa-star tooltippable" title="Notable streamer"></i> {/if}{$stream.real_name}</a> <i class="tooltippable fa fa-{if $stream.player == 1}gamepad{else}microphone{/if}" title="{if $stream.player == 1}Player{else}Caster{/if}"></i></td>
        <td class="time">{$stream.start|date_span:$stream.end}</td>
        <td class="average">{if $stream.average != 0}{$stream.average|thousands}{else}N/A{/if}</td>
        <td class="peak">{if $stream.peak != 0}{$stream.peak|thousands}{else}N/A{/if}</td>
        <td class="vh">{if $stream.vh != 0}{$stream.vh|thousands}{else}N/A{/if}</td>
      </tr>
{/foreach}
    </table>
  </section>

  {if !empty($matches)}
  <section>
    <h3><i class="fa fa-trophy"></i> Matches played in this event</h3>

    <table class="table event-stream-table">
      <tr>
        <th>Match</th>
        <th>Length</th>
      </tr>
      {foreach from=$matches item=match}
        <tr>
          <td>{if is_array($match.player1)}<a href="{$match.player1.__url}">{$match.player1.__label|e}</a>{else}{$match.player1|e}{/if} vs {if is_array($match.player2)}<a href="{$match.player2.__url}">{$match.player2.__label|e}</a>{else}{$match.player2|e}{/if}</td>
          <td class="time">{$match.start|date_span:$match.end}, {($match.end-$match.start)|time_approx}</td>
        </tr>
      {/foreach}
    </table>
  </section>
  {/if}
  
  <section>
    <h3><i class="fa fa-exchange"></i> Compare this event</h3>
    
    <form class="compare-events-form" data-url="{$__urlpath}/events/compare/{$event.id|friendly_url}/[query]/">
      <p>Enter the name of another event below to compare viewer numbers:</p>
      <input class="multibox" type="text" name="query" placeholder="Event name">
      <button><i class="fa fa-exchange"></i> Compare</button>
    </form>
  </section>