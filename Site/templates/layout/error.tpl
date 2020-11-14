<div class="popup error">
  <h3>Error</h3>
  <p>{$error}</p>
{if isset($stacktrace)}
  <p>Stack trace: {$stacktrace}</p>
{/if}
{if isset($errors)}
  <ul class="list">
{foreach from=$errors item=error}
    <li>{$error|e}</li>
{/foreach}
  </ul>{/if}
</div>