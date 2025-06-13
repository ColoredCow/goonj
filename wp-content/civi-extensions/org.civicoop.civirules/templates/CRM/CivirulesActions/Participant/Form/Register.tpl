<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-participant-register">
  <div class="help">{$ruleActionHelp}</div>
  <div class="crm-section">
      <div class="label">{$form.event_id.label}</div>
      <div class="content">{$form.event_id.html}</div>
      <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.participant_role_id.label}</div>
    <div class="content">{$form.participant_role_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.participant_status_id.label}</div>
    <div class="content">{$form.participant_status_id.html}</div>
    <div class="clear"></div>
  </div>
    <div class="crm-section">
        <div class="label">{$form.registration_date.label}</div>
        <div class="content">{$form.registration_date.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.campaign_id.label}</div>
        <div class="content">{$form.campaign_id.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
