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


<div class="column-wrapper">
  <div class="table-wrap">
    <table class="sessions-table">
        {$table_headers = ['Title' => 'title', 'When?' => 'start', 'Time' => 'time', 'Average' => 'average', 'Peak' => 'peak', 'V&times;H' => 'vh']}
        {include file='layout/overview_pagelist.tpl' settings=$view_settings headers=$table_headers}
        {foreach from=$data item=session}
            {if isset($session.live)}{$title=$live_title}{else}{$title = $session.title}{/if}
          <tr{if isset($session.live) && $session.live} class="live"{/if}>
            <td{if !empty($title) && strlen($title) > 40} title="{$title|e}" class="tooltippable"{/if}><a href="{if isset($session.live) && $session.live}{$stream_url}{else}{$__urlpath}{$session.__url}{/if}">{if isset($session.live) && $session.live}<i class="fa fa-circle tooltippable" title="Stream is currently broadcasting!"></i> {/if}{if !empty($title)}{$title|e|truncate:40:'...'}{else}{if isset($session.live) && $session.live}(no title){else}Session {$session.id}{/if}{/if}</a></td>
            <td>{if isset($session.live) && $session.live}{$session.start|date_format:'%e %b'} &mdash; <a href="{$stream_url}" rel="external">Live!</a>{else}{if date('d', $session.start) != date('d', $session.end)} {$session.start|date_format:'%e'} &mdash; {$session.end|date_format:'%e %b \'%y'}{else}{$session.start|date_format:'%e %b \'%y'}{/if}{/if}</td>
            <td>{(round($session.time / 60, 0) * 60)|time_approx}</td>
            <td>{if isset($session.live) && $session.live}N/A{else}{$session.average|thousands}{/if}</td>
            <td>{if isset($session.live) && $session.live}N/A{else}{$session.peak|thousands}{/if}</td>
            <td>{if isset($session.live) && $session.live}N/A{else}{$session.vh|thousands}{/if}</td>
          </tr>
        {/foreach}
    </table>
  </div>
</div>