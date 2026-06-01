<h3>{$ruleConditionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_condition-block-event-upcoming-events">
    <div class="crm-section sector-section">
        <div class="label">
            <label for="event-type-select">{ts}Event Type(s){/ts}</label>
        </div>
        <div class="content crm-select-container" id="event_type_block">
            {$form.event_type_id.html}
        </div>
        <div class="clear"></div>
    </div>
    <div class="crm-section sector-section">
        <div class="label">{$form.additional_wheres.label}</div>
        <div class="content">{$form.additional_wheres.html}
        <div class="description">{ts}Example: {/ts}<br>
        <pre>
        [
            ["Planning_Kantine.Openen_en_sluiten_nodig_", "!=","0"]
        ]
        </pre>
        </div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>