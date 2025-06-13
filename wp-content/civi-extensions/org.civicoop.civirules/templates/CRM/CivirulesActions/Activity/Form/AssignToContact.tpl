<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-activity">
  <div class="help">{$ruleActionHelp}</div>
    <div class="crm-section">
        <div class="label">{$form.use_contact_trigger.label}</div>
        <div class="content">{$form.use_contact_trigger.html}</div>
        <div class="clear"></div>
    </div>
     {if ($use_old_contact_ref_fields)}
        <div id='selectContact' class="crm-section">
            <div class="label">{ts}Assignee{/ts}</div>
            <div class="content">
                {include file="CRM/Contact/Form/NewContact.tpl" noLabel=true skipBreak=true multiClient=false showNewSelect=false contact_id=$assignee_contact_id}
            </div>
            <div class="clear"></div>
        </div>
    {else}
        <div id='selectContact' class="crm-section">
            <div class="label">{$form.assignee_contact_id.label}</div>
            <div class="content">{$form.assignee_contact_id.html}</div>
            <div class="clear"></div>
        </div>
    {/if}
    <div class="crm-section">
        <div class="label">{$form.send_email.label}</div>
        <div class="content">{$form.send_email.html}</div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
    <script type="text/javascript">
        cj(function() {
            cj('input[type=radio][name=use_contact_trigger]').change(triggerDelayChange);

            if(cj('input[type=radio][name=use_contact_trigger]:checked').val() === '1'){
                cj('#selectContact').hide();
            }
        });

        function triggerDelayChange(e) {
            if(e.target.value === '1'){
                cj('#selectContact').hide();
            }else{
                cj('#selectContact').show();
            }
        }
    </script>
{/literal}
