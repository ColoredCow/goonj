<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-contributionrecur_changenextscheduledate">
    <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.schedule_option.label}</div>
        <div class="content">{$form.schedule_option.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.schedule_date.label}</div>
        <div class="content">{$form.schedule_date.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
