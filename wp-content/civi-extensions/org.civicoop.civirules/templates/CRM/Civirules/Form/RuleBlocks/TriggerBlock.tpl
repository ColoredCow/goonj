{* block for linked trigger *}
<h3>Linked Trigger</h3>
<div class="crm-block crm-form-block crm-civirule-trigger-block">
  {if empty($form.rule_trigger_label.value)}
    <div class="crm-section">
      <div class="label">{$form.rule_trigger_select.label}</div>
      <div class="content">{$form.rule_trigger_select.html}</div>
      <div class="clear"></div>
    </div>
  {else}
    <div class="crm-section">
      <div id="civirule_triggerBlock-wrapper">
        <p class="bold">{$form.rule_trigger_label.value}</p>
        {if $triggerClass && $triggerClass->getTriggerDescription()}
          <p class="description">{$triggerClass->getTriggerDescription()}</p>
        {/if}
        {if $trigger_edit_params}
          <br><a class="button edit-button" href="{$trigger_edit_params}">{icon icon="fa-pencil"}{/icon}{ts}Edit trigger parameters{/ts}</a>
        {/if}
      </div>
    </div>
  {/if}
</div>

