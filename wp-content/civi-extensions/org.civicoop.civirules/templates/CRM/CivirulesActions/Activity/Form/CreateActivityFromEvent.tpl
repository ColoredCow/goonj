<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-activity">
  <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.activity_type_id.label}</div>
        <div class="content">{$form.activity_type_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.status_id.label}</div>
        <div class="content">{$form.status_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.event_id_custom_field.label}</div>
      <div class="content">{$form.event_id_custom_field.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.event_start_date_custom_field.label}</div>
      <div class="content">{$form.event_start_date_custom_field.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
      <div class="label">{$form.event_end_date_custom_field.label}</div>
      <div class="content">{$form.event_end_date_custom_field.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.assignee_contact_id.label}</div>
        <div class="content">{$form.assignee_contact_id.html}</div>
        <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.activity_date_time.label}</div>
        <div class="content">{$form.activity_date_time.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.send_email.label}</div>
        <div class="content">{$form.send_email.html}</div>
        <div class="clear"></div>
    </div>
    {foreach from=$delayClasses item=delayClass}
        <div class="crm-section crm-activity_date_time-class" id="{$delayClass->getName()}">
            <div class="label"></div>
            <div class="content"><strong>{$delayClass->getDescription()}</strong></div>
            <div class="clear"></div>
            {include file=$delayClass->getTemplateFilename() delayPrefix='activity_date_time'}
        </div>
    {/foreach}
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
    <script type="text/javascript">
        cj(function() {
            cj('select#activity_date_time').change(triggerDelayChange);

            triggerDelayChange();
        });

        function triggerDelayChange() {
            cj('.crm-activity_date_time-class').css('display', 'none');
            var val = cj('#activity_date_time').val();
            if (val) {
                cj('#'+val).css('display', 'block');
            }
        }
    </script>
{/literal}
