{crmScope extensionKey='org.civicoop.civirules'}
<h3>{$ruleTriggerHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-post-trigger-block-event">
  <div class="help">{$ruleTriggerHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.contact_id.label}</div>
        <div class="content">{$form.contact_id.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
