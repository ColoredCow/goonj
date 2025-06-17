<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-membership">
  <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.membership_status_id.label}</div>
        <div class="content">{$form.membership_status_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.set_is_override.label}</div>
        <div class="content">{$form.set_is_override.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
