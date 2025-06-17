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
    <div class="label">{$form.custom_value.label}</div>
    <div class="content">{$form.custom_value.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.template_id.label}</div>
    <div class="content">{$form.template_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section" style="display: none;">
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
        customValueUpdate();
      });
      triggerFallBackPrimary();
      customValueUpdate();
    });
  function triggerFallBackPrimary() {
    var locType = cj('#location_type_id').val();
    cj('#location_note').hide();
    if (locType) {
      cj('#location_note').show();
    }
  }
  function customValueUpdate() {
    entity = CRM.$('#entity').val();
    if (entity) {
      var el = CRM.$("#custom_value");
      var oldval = el.val();
      el.empty();
      CRM.$.get("{/literal}{crmURL p='civicrm/civirules/actions/emailapi_customfieldvalue/options' q='reset=1&entity=' h=0}{literal}" + entity, null, function(newOptions) {
        CRM.$.each(newOptions, function(value, key) {
          el.append(CRM.$("<option></option>").attr("value", value).text(key));
        });
        el.val(oldval);
      });
    }
    else {
      CRM.$('#custom_value').val(null).trigger('change');
    }
  }
  </script>
{/literal}
