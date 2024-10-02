{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $collectionCampLinks}
  {foreach from=$collectionCampLinks item=campLink}
    <div class="crm-section">
      <div class="label">{$campLink.label}</div>
      <div class="content"><a href="{$campLink.url}">{$campLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
