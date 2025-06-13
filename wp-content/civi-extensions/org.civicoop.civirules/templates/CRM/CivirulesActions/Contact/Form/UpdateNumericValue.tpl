<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-contact_updatenumeric">
  <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.source_field_id.label}</div>
        <div class="content">{$form.source_field_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.target_field_id.label}</div>
        <div class="content">{$form.target_field_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.update_operation.label}</div>
        <div class="content">{$form.update_operation.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.update_operand.label}</div>
        <div class="content">{$form.update_operand.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
