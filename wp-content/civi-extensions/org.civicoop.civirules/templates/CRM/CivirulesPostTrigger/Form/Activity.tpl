{crmScope extensionKey='org.civicoop.civirules'}
  <h3>{$ruleTriggerHeader}</h3>
  <div class="crm-block crm-form-block crm-civirule-post-trigger-block-activity">
    <div class="help">{$ruleTriggerHelp}</div>
    <div class="crm-section">
      <div class="label">{$form.record_type.label}</div>
      <div class="content">{$form.record_type.html}</div>
      <div class="clear"></div>
    </div>
      {if array_key_exists('trigger_op', $form)}
        <div class="crm-section">
          <div class="label">{$form.trigger_op.label}</div>
          <div class="content">{$form.trigger_op.html}</div>
          <div class="clear"></div>
        </div>
      {/if}
  </div>
  <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
{/crmScope}
