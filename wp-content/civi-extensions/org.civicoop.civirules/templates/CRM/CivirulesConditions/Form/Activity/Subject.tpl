<h3>{$ruleConditionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_condition-block-activity_activitydetails">
  <div class="crm-section">
    <div class="label">{$form.operator.label}</div>
    <div class="content">{$form.operator.html}</div>
    <p class="description help">{ts}Matching is case-insensitive and ignores white space at the beginning and end of the text.{/ts}</p>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.text.label}</div>
    <div class="content">{$form.text.html}</div>
    <div class="clear"></div>
  </div>
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
