<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-contribution_status">
    <div class="crm-section">
        <div class="label">{$form.status_id.label}</div>
        <div class="content">{$form.status_id.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-contribution_cancel_reason">
    <div class="crm-section">
        <div class="label">{$form.cancel_reason.label}</div>
        <div class="content">{$form.cancel_reason.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
