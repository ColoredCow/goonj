{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $institutionDroppingCenterLinks}
  {foreach from=$institutionDroppingCenterLinks item=institutionDroppingCenterLink}
    <div class="crm-section">
      <div class="label">{$institutionDroppingCenterLink.label}</div>
      <div class="content"><a href="{$institutionDroppingCenterLink.url}">{$institutionDroppingCenterLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
