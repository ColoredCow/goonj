{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $droppingCenterLinks}
  {foreach from=$droppingCenterLinks item=droppingCenterLink}
    <div class="crm-section">
      <div class="label">{$droppingCenterLink.label}</div>
      <div class="content"><a href="{$droppingCenterLink.url}">{$droppingCenterLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
