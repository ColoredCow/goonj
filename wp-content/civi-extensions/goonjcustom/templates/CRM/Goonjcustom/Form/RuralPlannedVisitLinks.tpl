{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $eventsLinks}
  {foreach from=$eventsLinks item=eventLink}
    <div class="crm-section">
      <div class="label">{$eventLink.label}</div>
      <div class="content"><a href="{$eventLink.url}">{$eventLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
