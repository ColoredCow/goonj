{crmScope key='org.civicoop.civirules'}
<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-addrelatedgroup">
  <div class="help">{$ruleActionHelp}</div>
  <div class="crm-section">
    <div class="label">{$form.rel_type_ids.label}</div>
    <div class="content">{$form.rel_type_ids.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section groups-single">
    <div class="label">{$form.group_id.label}</div>
    <div class="content">{$form.group_id.html}</div>
    <div class="clear"></div>
  </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
