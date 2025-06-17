<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-generic_updatedate">
  <div class="help">{$ruleActionHelp}</div>
    {if $triggerObject eq 'Activity'}
    {/if}
    <div class="crm-section source_field_id-section">
        <div class="label">{$form.source_field_id.label}</div>
        <div class="content">{$form.source_field_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section target_field_id-section">
        <div class="label">{$form.target_field_id.label}</div>
        <div class="content">{$form.target_field_id.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section update_operation-section">
        <div class="label">{$form.update_operation.label}</div>
        <div class="content">{$form.update_operation.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section update_operand-section">
        <div class="label">{$form.update_operand.label}</div>
        <div class="content">{$form.update_operand.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{literal}
  <script>
    (function(CRM, $){
        $(function(){
            // Only display relevant fields for the chosen operation
            function fieldDisplay() {
                $('.source_field_id-section').toggle($('#update_operation').val()!=='set');
            }
            fieldDisplay();
            $('#update_operation').on('change', function(){
                fieldDisplay();
            });
        });
    })(CRM, CRM.$);
  </script>
{/literal}
