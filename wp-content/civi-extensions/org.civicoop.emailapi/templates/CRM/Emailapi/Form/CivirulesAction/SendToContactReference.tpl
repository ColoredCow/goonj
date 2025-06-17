<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-email-send">
  <div class="help">{$ruleActionHelp}</div>
  <div class="crm-section">
    <div class="label">{$form.from_name.label}</div>
    <div class="content">{$form.from_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.from_email.label}</div>
    <div class="content">{$form.from_email.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.entity.label}</div>
    <div class="content">{$form.entity.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.contact_reference.label}</div>
    <div class="content">{$form.contact_reference.html}</div>
    <div class="content" id="contact_reference_note">{ts}You can also use an Entity Reference field if the referenced entity is a contact.{/ts}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.template_id.label}</div>
    <div class="content">{$form.template_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.location_type_id.label}</div>
    <div class="content">{$form.location_type_id.html}</div>
    <div class="content" id="location_note">{ts}Note: primary e-mailaddress will be used if location type e-mailaddress not found{/ts}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section cc">
    <div class="label">{$form.cc.label}</div>
    <div class="content">{$form.cc.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section bcc">
    <div class="label">{$form.bcc.label}</div>
    <div class="content">{$form.bcc.html}</div>
    <div class="clear"></div>
  </div>

  {if ($has_case)}
    <div class="crm-section">
      <div class="label">{$form.file_on_case.label}</div>
      <div class="content">{$form.file_on_case.html}</div>
      <div class="clear"></div>
    </div>
  {/if}
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
  <script type="text/javascript">
    cj(function() {
      cj('#location_type_id').change(function() {
        triggerFallBackPrimary();
      });
      cj('#entity').change(function() {
        contactReferenceUpdate();
      });
      triggerFallBackPrimary();
      contactReferenceUpdate();
    });
  function triggerFallBackPrimary() {
    var locType = cj('#location_type_id').val();
    cj('#location_note').hide();
    if (locType) {
      cj('#location_note').show();
    }
  }
  function contactReferenceUpdate() {
  entity = CRM.$('#entity').val();
    if (entity) {
      CRM.$('#contact_reference').crmEntityRef({
        create: false,
        api: {params: {'custom_group_id.extends': entity}},
      });
    }
    else {
      CRM.$('#contact_reference').val(null).trigger('change');
    }
  }
  </script>
{/literal}
