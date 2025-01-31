{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $eventsLinks}
  {foreach from=$eventsLinks item=eventsLinks}
    <div class="crm-section">
      <div class="label">{$eventsLinks.label}</div>
      <div class="content"><a href="{$eventsLinks.url}">{$eventsLinks.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
