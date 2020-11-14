  {include file='Stream/card.tpl' stream=$stream stats=$stats}
 
  <hr>

  {if $stream.provider == 'douyu'}<div class="notice inline"><i class="fa fa-exclamation-circle"></i> This is a DouyuTV stream. As DouyuTV viewer numbers are unreliable and inaccurate they are not tracked.</div>{/if}
{if isset($events) && $events && count($events.items) > 0}
  <section class="full-width">
    <h3><i class="fa fa-calendar"></i> Events broadcasted in this session</h3>
    {include file='Event/list.tpl' data=$events.items view_settings=$events}
  </section>
  
  <hr>
{/if}
   
  <section>
    <h3><i class="fa fa-bar-chart-o"></i> Streaming session on {$session.start|date_format:'%B %e, %Y'}{if !empty($session.title)}: "{$session.title|e}"{/if}</h3>
    {if $session.interpolated}<div class="notice inline"><i class="fa fa-warning"></i> Specific data for part of this streaming session is missing and has been interpolated.</div>{/if}
    {include file='Session/chart.tpl' session=$session chart=$chart id='session'}
  </section>