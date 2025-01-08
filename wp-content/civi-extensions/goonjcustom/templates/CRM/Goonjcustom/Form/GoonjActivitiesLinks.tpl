{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{if $goonjActivitiesLinks}
  {foreach from=$goonjActivitiesLinks item=goonjActivitiesLinks}
    <div class="crm-section">
      <div class="label">{$goonjActivitiesLinks.label}</div>
      <div class="content"><a href="{$goonjActivitiesLinks.url}">{$goonjActivitiesLinks.url}</a></div>
      <div class="clear"></div>
    </div>
  {/foreach}
{/if}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
