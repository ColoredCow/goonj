<h3>{$ruleTriggerHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-cron_trigger-block-no-case_activity-since">
    <div class="crm-section">
        <div class="label">{$form.case_type.label}</div>
        <div class="content">{$form.case_type.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.case_status.label}</div>
        <div class="content">{$form.case_status.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.activity_type.label}</div>
        <div class="content">{$form.activity_type.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.activity_status.label}</div>
        <div class="content">{$form.activity_status.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.offset.label}</div>
        <div class="content">{$form.offset.html} {$form.offset_unit.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
