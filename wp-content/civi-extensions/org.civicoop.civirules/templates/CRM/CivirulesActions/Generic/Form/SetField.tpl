<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-contact_updatenumeric">
  <div class="help">{$ruleActionHelp}</div>
      <div class="crm-section">
        <div class="label">{$form.entity.label}</div>
        <div class="content">{$form.entity.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.field.label}</div>
        <div class="content">{$form.field.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section" id="value_parent">
        <div class="label">{$form.value.label}</div>
        <div class="content">
            {$form.value.html}
            <select id="value_options" class="hiddenElement crm-form-select huge">

            </select>
        </div>
        <div class="clear"></div>
    </div>
    <div class="crm-section" id="multi_value_parent">
        <div class="label">{$form.multi_value.label}</div>
        <div class="content textarea">
            {$form.multi_value.html}
        </div>
        <div id="multi_value_options" class="hiddenElement content">

        </div>
        <div class="clear"></div>
    </div>
</div>
<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{literal}
    
<script type="text/javascript">

    CRM.$(function ($) {
      var all_fields = $('#field').html();
      $('#field').change(function() {
          var entity = $('#entity').val();
          var field = $('#field').val();
          var field = field.replace($('#entity').val()+'_', "");
          retrieveOptionsForEntityAndField(entity, field);
      });
      $('#entity').change(function() {
         var val = $('#entity').val();
          $('#field').html(all_fields);
          $('#field option').each(function(index, el) {
              if ($(el).val().indexOf(val+'_') != 0) {
                  $(el).remove();
              }
          });
          $('#field').trigger('change');
      });
      $('#entity').trigger('change');


    function retrieveOptionsForEntityAndField(entity, field) {
      var options = new Array();
      var multiple = false;
      civirules_action_updateOptionValues(options, multiple);

      CRM.api4(entity, 'getFields', {
        loadOptions: true,
        where: [["name", "=", field]]
      }).then(function(data) {
        civirules_action_updateOptionValues(data[0].options, data[0].serialize);
      });
    }

    function civirules_action_updateOptionValues(options, multiple) {
      if (options && Object.keys(options).length  > 0) {
        var select_options = '';
        var multi_select_options = '';
        var currentSelectedOption = $('#value').val();
        var selectedOptions = new Array();
        var selectedOption = '';
        var first = true;
        for(let [key, value] of Object.entries(options)) {
          var selected = '';
          var checked = '';
          if (key == currentSelectedOption || (!currentSelectedOption && first == true)) {
              selected='selected="selected"';
              selectedOption = key;
          }
          multi_select_options = multi_select_options + '<input type="checkbox" value="'+key+'" '+checked+'>'+value+'<br>';
          select_options = select_options + '<option value="'+key+'" '+selected+'>'+value+'</option>';
          first = false;
        }
        $('#value').val(selectedOption);
        // if (!multiple) {
          $('#value, #multi_value_options').addClass('hiddenElement');
          $('#value_options').html(select_options);
          $('#value_options').removeClass('hiddenElement');
          $('#value_options').change(function() {
            var value = $(this).val();
            $('#value').val(value);
          });
        // } else {
        //   $('#value, #value_options').addClass('hiddenElement');
        //   $('#multi_value_options').html(multi_select_options);
        //   $('#multi_value_options').removeClass('hiddenElement');
        //   $('#multi_value_options input[type="checkbox"]').change(function() {
        //     var currentOptions = $('#multi_value').val().match(/[^\r\n]+/g);
        //     if (!currentOptions) {
        //       currentOptions = new Array();
        //     }
        //     var value = $(this).val();
        //     var index = currentOptions.indexOf(value);
        //     if (this.checked) {
        //       if (index < 0) {
        //         currentOptions[currentOptions.length] = value;
        //         $('#multi_value').val(currentOptions.join('\r\n'));
        //       }
        //     } else {
        //       if (index >= 0) {
        //         currentOptions.splice(index, 1);
        //         $('#multi_value').val(currentOptions.join('\r\n'));
        //       }
        //     }
        //   });
        // }
      } else {
        $('#value_options, #multi_value_options').addClass('hiddenElement');
        $('#value').removeClass('hiddenElement');
      }
    }
    });
</script>
{/literal}