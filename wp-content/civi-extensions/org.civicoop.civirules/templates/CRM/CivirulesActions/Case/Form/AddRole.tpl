{crmScope extensionKey='org.civicoop.civirules'}
<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-case_add_role">
  <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section case-role">
        <div class="label">{$form.role.label}</div>
        <div class="content">{$form.role.html}</div>
        <div class="clear"></div>
    </div>
  <div class="crm-section case-cid">
    <div class="label">{$form.cid.label}</div>
    <div class="content">{$form.cid.html}
      <p class="description">{ts}Leave empty to use the contact from the trigger{/ts}</p>
    </div>
    <div class="clear"></div>
  </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/crmScope}
