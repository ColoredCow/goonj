{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $UrbanVisitLinks}
  {foreach from=$UrbanVisitLinks item=urbanLink}
    <div class="crm-section">
      <div class="label">{$urbanLink.label}</div>
      <div class="content"><a href="{$urbanLink.url}">{$urbanLink.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>