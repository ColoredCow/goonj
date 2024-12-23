{crmScope key=prettyworkflowmessages}
<div class="help">
  <p>{ts}Use the following tokens in your branded template.{/ts}</p>
  <p>
    <ul>
      <li><strong>{literal}{system.workflow_message_subject}{/literal}</strong>: {ts}This will evaluate as default system workflow subject.{/ts}</li>
      <li><strong>{literal}{system.workflow_message_html}{/literal}</strong>: {ts}This will evaluate as default system workflow HTML message.{/ts}</li>
      <li><strong>{literal}{system.workflow_message_text}{/literal}</strong>: {ts}This will evaluate as default system workflow text message.{/ts}</li>
    </ul>
  </p>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-section">
  <div class="label">{$form.pretty_workflow_template.label}</div>
  <div class="content">
    {$form.pretty_workflow_template.html}<br/>
    <span class='description html-adjust'>{ts}This templates will be used for branding all system messages.{/ts}</span>
  </div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}