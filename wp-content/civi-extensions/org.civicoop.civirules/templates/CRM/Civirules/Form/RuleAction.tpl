{* block for rule action data *}
<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block">
    {if (!empty($action_label))}
        <div class="crm-section">
            <div class="label"></div>
            <div class="content">{$action_label}</div>
            <div class="clear"></div>
        </div>
    {else}
        <div class="crm-section">
            <div class="label">{$form.rule_action_select.label}</div>
            <div class="content">{$form.rule_action_select.html}</div>
            <div class="clear"></div>
        </div>
    {/if}
</div>

{include file="CRM/Civirules/Form/RuleActionDelay.tpl"}

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
