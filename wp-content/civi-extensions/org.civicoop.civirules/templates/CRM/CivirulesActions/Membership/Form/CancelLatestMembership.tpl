{crmScope extensionKey='civirules'}
  <h3>{$ruleConditionHeader}</h3>
  <div id="civirule_helptext_dialog-block">
    <p><label id="civirule_help_text-value">{ts}This action cancels the latest membership that is found using the configured types and statuses.
        It can be used with GroupContact entity actions like add contact to group or remove contact from group.{/ts}</label></p>
  </div>
  <div class="crm-block crm-form-block crm-civirule-rule_condition-block-cancel_membership">
    <div class="crm-section membership_status">
      <div class="label">{$form.membership_status_id.label}</div>
      <div class="content">{$form.membership_status_id.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section membership_type">
      <div class="label">{$form.membership_type_id.label}</div>
      <div class="content">{$form.membership_type_id.html}</div>
      <div class="clear"></div>
    </div>
  </div>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
{/crmScope}
