{if $stream.provider == 'twitch'}{$provider='Twitch.tv'}{$stream_url="http://www.twitch.tv/{$stream.remote_id}"}{/if}
{if $stream.provider == 'azubu'}{$provider='Azubu.tv'}{$stream_url="http://www.azubu.tv/{$stream.remote_id}"}{/if}
{if $stream.provider == 'dailymotion'}{$provider='Dailymotion'}{$stream_url="http://www.dailymotion.com/video/{$stream.remote_id}"}{/if}
{if $stream.provider == 'livestream'}{$provider='Livestream.com'}{$stream_url="http://www.livestream.com/{$stream.remote_id}"}{/if}
{if $stream.provider == 'goodgame'}{$provider='Goodgame.ru'}{$stream_url="http://www.goodgame.ru/channel/{$stream.remote_id}"}{/if}
{if $stream.provider == 'hitbox'}{$provider='Hitbox.tv'}{$stream_url="http://www.hitbox.tv/{$stream.remote_id}"}{/if}
{if $stream.provider == 'youtube'}{$provider='YouTube'}{if substr($stream.remote_id, 0, 2) == 'UC' && strlen($stream.remote_id) == 24}{$stream_url="https://www.youtube.com/channel/{$stream.remote_id}"}{else}{$stream_url="https://www.youtube.com/user/{$stream.remote_id}"}{/if}{/if}
{if $stream.provider == 'dingit'}{$provider='DingIt.tv'}{$stream_url="https://www.dingit.tv/channel/{$stream.remote_id}"}{/if}
{if $stream.provider == 'afreeca'}{$provider='Afreeca'}{$stream_url="http://play.afreeca.com/{$stream.remote_id}"}{/if}
{if $stream.provider == 'douyu'}{$provider='DouyuTV'}{$stream_url="http://douyutv.com/{$stream.remote_id}"}{/if}
{if $stream.provider == 'streamme'}{$provider='Stream.me'}{$stream_url="http://stream.me/{$stream.remote_id}"}{/if}

  <div class="card player-card">
    {if empty($stream.avatar) && empty($stream.tl_logo)}<div class="avatar"><i class="fa fa-user"></i></div>{else}<img alt="" src="{if !empty($stream.avatar)}{$stream.avatar}{else}{$__urlpath}/assets/images/players/{$stream.tl_logo}{/if}">{/if}
    
    <div class="profile{if $live} live{/if}" id="stream-{$stream.id|e}">
      <header>
        <h2><a href="{$stream.__url}">{$stream.real_name}</a></h2>
        <!--<p class="team">{if isset($team) && $team && $team.id != 74}<a href="{$team.__url}">{$team.team|e}</a>{elseif $stream.player}Teamless{/if}</p>-->
        <div class="link-list">
          <ul>
            <li><i class="fa fa-youtube-play"></i> <a href="{$stream_url}" rel="external">{$provider}</a></li>
      {if !empty($stream.twitter)}
            <li><i class="fa fa-twitter"></i> <a href="https://www.twitter.com/{$stream.twitter}" rel="external">Twitter</a></li>
      {/if}
      {if !empty($stream.wiki)}
            <li><i class="fa fa-globe"></i> <a href="http://wiki.teamliquid.net/{$stream.wiki}" rel="external">Liquipedia</a></li>
      {/if}
      {if !empty($stream.tl_id)}
            <li><i class="fa fa-file-video-o"></i> <a href="http://www.teamliquid.net/video/streams/{$stream.tl_id}" rel="external">TeamLiquid</a></li>
      {/if}
          </ul>
        </div>
        <div class="type {if $stream.player}player{else}caster{/if}">
          <i class="fa fa-{if $stream.player}gamepad{else}microphone{/if} fa-2x"></i>
          <span>{if $stream.player}Player{else}Event stream{/if}</span>
        </div>
      </header>
    {if $stream.notable == 1}<i class="fa fa-star tooltippable notable-badge" title="Notable streamer"></i> {/if}
      <ul class="statistics">
        <li>
          <h3>Streamed</h3>
          <p>{$stats.time|time_approx}</p>
        </li>
        <li>
          <h3>Average</h3>
          <p>{$stats.average|thousands}</p>
        </li>
        <li>
          <h3>Peak</h3>
          <p>{$stats.peak|thousands}</p>
        </li>
        <li>
          <h3>Viewers &times; Hours</h3>
          <p>{$stats.vh|thousands}</p>
        </li>
        <li>
          <h3>Current rank</h3>
          <p{if $rank &&  $rank.delta != NULL} class="tooltippable" title="Previous week's rank: #{$rank.rank - $rank.delta}{if $rank.delta != 0} (this week {if $rank.delta < 0}-{else}+{/if}{abs($rank.delta)}){/if}"{/if}>{if $rank.rank}<i class="fa fa-{if !$rank || $rank.delta == 0}circle{elseif $rank.delta < 0}arrow-circle-up{else}arrow-circle-down{/if}"></i> {/if}{if $rank}<a href="/rankings/players/{date('Y')}/week/{date('W')}/?page={ceil($rank.rank / 25)}">#{$rank.rank|thousands}</a>{else}&mdash;{/if}</p>
        </li>
{if $live}
        <li class="live-notice">
          <a class="live-badge" href="{$stream_url}" rel="external"><i class="fa fa-circle"></i> Live</a> <span>{$stream.real_name} is streaming{if isset($live_event)} <a href="{$live_event.__url}">{$live_event.short_name|e}</a>{/if} right now! Join {$current_viewers|thousands} other viewer{if $current_viewers != 1}s{/if} at <a href="{$stream_url}" rel="external" class="live-link">{$provider}</a>.</span>
        </li>
{/if}
      </ul>
    </div>
  </div>