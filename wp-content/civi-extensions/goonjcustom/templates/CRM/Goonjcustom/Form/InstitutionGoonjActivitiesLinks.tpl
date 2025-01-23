{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $institutionGoonjActivitiesLinks}
  {foreach from=$institutionGoonjActivitiesLinks item=institutionGoonjActivitiesLink}
    <div class="crm-section">
      <div class="label">{$institutionGoonjActivitiesLink.label}</div>
      <div class="content"><a href="{$institutionGoonjActivitiesLink.url}">{$institutionGoonjActivitiesLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
