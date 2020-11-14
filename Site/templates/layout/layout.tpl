<!DOCTYPE html>
<head>
  <title>{if !isset($__title) || empty($__title)}Fuzic &#8226; {$__game.name} streaming statistics and rankings{else}{$__title} &#8226; Fuzic {$__game.name} stream statistics{/if}</title>
  {foreach from=$__css item=stylesheet}
    <link rel="stylesheet" href="{$stylesheet.href}">
  {/foreach}
  <link href="//fonts.googleapis.com/css?family=Roboto:400,900,700" rel="stylesheet" type="text/css">
  {foreach from=$__javascript item=script}
    <script src="{$script.src}"></script>
  {/foreach}
  <meta property="og:image" content="https://{$__game.subdomain}.fuzic.nl/assets/images/logo.png">
  <meta property="og:title" content="Fuzic">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Fuzic">
  <meta property="og:description" content="{$__game.name} live stream rankings and statistics">
  <meta name="twitter:site" content="@fuzicnl">
  <meta name="twitter:site:id" content="1634013498">
  {if !empty($__twittercard)}
    {$__twittercard}
  {else}
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Fuzic">
    <meta name="twitter:description" content="{$__game.name} live stream rankings and statistics">
    <meta name="twitter:image" content="https://{$__game.subdomain}.fuzic.nl/assets/images/logo.png">
  {/if}
  <link rel="shortcut icon" type="image/png" href="/assets/images/favicon.png">
</head>
<body class="{$__bodyclass}">
<div id="top-bar">
</div>

<div id="main">
  <header id="top">
    <nav id="subsite-nav">
      <ul>
        <li>Live streaming stats and rankings for</li>
        <li{if $__game_ID == 'overwatch'} class="current"{/if}><a href="//overwatch.fuzic.nl">Overwatch</a></li>
        <li{if $__game_ID == 'hearthstone'} class="current"{/if}><a href="//hearthstone.fuzic.nl">Hearthstone</a></li>
        <li{if $__game_ID == 'heroes'} class="current"{/if}><a href="//heroes.fuzic.nl">Heroes of the Storm</a></li>
        <li{if $__game_ID == 'broodwar'} class="current"{/if}><a href="//broodwar.fuzic.nl">Brood War</a></li>
        <li{if $__game_ID == 'sc2'} class="current"{/if}><a href="//www.fuzic.nl">StarCraft II</a></li>
      </ul>
    </nav>

    <div id="site-header-wrap">
      <p class="logo"><a href="{$__urlpath}/">Fuzic</a> <span class="beta tooltippable"
                title="The site is still being worked on! Please read the blog for more information.">Beta</span></p>
      <nav id="site-nav">
        <ul>
          <li>
            <h3><a href="/rankings/">Rankings</a></h3>
            <ul>
              <li><a href="/rankings/streams/week/">Streams</a></li>
              <li><a href="/rankings/events/week/">Events</a></li>
            </ul>
          </li>
          <li>
            <h3><a href="/streams/">Streams</a></h3>
            <ul>
              <li><a href="/streams/players/">Players</a></li>
              <li><a href="/streams/casters/">Casters</a></li>
            </ul>
          </li>
          <li>
            <h3><a href="/events/">Events</a></h3>
            <ul>
              <li><a href="/events/?order_by=vh&amp;order=desc">All-time</a></li>
              <li><a href="/franchises/">Franchises</a></li>
            </ul>
          </li>
          <li>
            <h3><a href="/trends/">Trends</a></h3>
            <ul>
              <li><a href="/trends/">Overall</a></li>
              <!--<li><a href="/franchises/">Month</a></li>-->
            </ul>
          </li>
        </ul>
      </nav>

      <div id="timezone-wrapper">
        <label for="select-timezone"><span>Timezone</span></label>
        <select name="select-timezone" id="select-timezone">
          {foreach from=$__timezones item=zone key=offset}
            <option value="{$offset|e}"{if isset($__current_timezone) && $offset == $__current_timezone} selected="selected"{/if}>{$zone|e}</option>
          {/foreach}
        </select>
      </div>
    </div>
  </header>

  {if !empty($__breadcrumbs)}
    <nav class="breadcrumbs">
      <i class="fa fa-map-marker"></i>
      <ol>
        {foreach from=$__breadcrumbs item=crumb}
          <li><a href="{$crumb.url}">{$crumb.label}</a></li>
        {/foreach}
      </ol>
    </nav>
  {/if}
  {if false}
    <div class="notice"><i
              class="fa fa-warning"></i> Rankings may be unavailable or incomplete for a short while as the site undergoes maintenance.
    </div>
  {/if}

  {$__body}
</div>

<footer>
  <ul>
    <li><a href="/about/">About</a></li>
    <li><a href="/faq/"><abbr title="frequently asked questions">FAQ</abbr></a></li>
    <li><a href="/contact/">Contact</a></li>
    <li><a href="https://www.twitter.com/{if isset($__game.fuzic_twitter) && !empty($__game.fuzic_twitter)}{$__game.fuzic_twitter}{else}fuzicnl{/if}">Twitter</a></li>
    <li><a href="/blog/">Blog</a></li>
    <li><a href="/streamer-account/">request account</a></li>
  </ul>
  <p>Event, team and player data from <a href="http://www.teamliquid.net">TeamLiquid</a> and <a
            href="http://wiki.teamliquid.net/">Liquipedia</a></p>
</footer>

<div id="match-tooltip">
  <div>cocks</div>
</div>
<!-- Piwik -->
<script type="text/javascript">
  var _paq = _paq || [];
  _paq.push(["setDomains", ["*.{$__game.subdomain}.fuzic.nl"]]);
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//piwik.fuzic.nl/";
    _paq.push(['setTrackerUrl', u+'piwik.php']);
    _paq.push(['setSiteId', {$__game.piwik_id}]);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<noscript><p><img src="//piwik.fuzic.nl/piwik.php?idsite={$__game.piwik_id}" style="border:0;" alt="" /></p></noscript>
<!-- End Piwik Code -->
</body>