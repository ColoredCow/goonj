{literal}
<script type="text/javascript">
    var moment_to_min_cron     = new Array("4,19,34,49", "11,41", "56", "56", "56", "56", "56", "56", "*", "*/5", "*/10");
    var moment_to_hour_cron    = new Array("*", "*", "*", "*/6", "*/12", "4", "4", "4", "*", "*", "*");
    var moment_to_day_cron     = new Array("*", "*", "*", "*", "*", "*", "*", "1", "*", "*", "*");
    var moment_to_month_cron   = new Array("*", "*", "*", "*", "*", "*", "*", "*", "*", "*", "*");
    var moment_to_weekday_cron = new Array("*", "*", "*", "*", "*", "*", "0", "*", "*", "*", "*");

    /**
     * Function to check selected values in Select Element
     */
    function getSelectedSeparatedBy(array_name, sep, def_value) {
        if (!sep)
            sep = ",";
        if (!def_value)
            def_value = "*";

        var str = CRM.$("select[name='" + array_name + "'] :selected").map(function () {
            return this.value;
        }).get();
        if (sep == '-' && str.length > 1) {
            var firstItem = str[0];
            var lastItem = str[str.length - 1];
            str = firstItem + '-' + lastItem;
        }
        else {
            var str = str.join(sep);
        }
        if (!str)
            str = def_value;

        return str;
    }

    /**
     * Function to generate crontab time based on user selection
     */
    function regenerateCronFormat() {
        var cron = "";

        var basic_val = CRM.$("select[name='basic_crontab'] :selected").val() - 1;
        if (CRM.$("select[name='basic_crontab']").length && basic_val != '-1') {
            // handle Simple crontab setup
            cron = moment_to_min_cron[basic_val] + " ";
            cron += moment_to_hour_cron[basic_val] + " ";
            cron += moment_to_day_cron[basic_val] + " ";
            cron += moment_to_month_cron[basic_val] + " ";
            cron += moment_to_weekday_cron[basic_val] + " ";
            CRM.$('.crm-job-form-block-name_crontab').hide();

        } else {
            // handle advanced crontab setup
            var hrSep = ',';
            if (CRM.$('#crontab_hour_range').prop("checked") == true) {
                hrSep = '-';
            }
            cron += getSelectedSeparatedBy('minute') + " ";
            cron += getSelectedSeparatedBy('hour[]', hrSep) + " ";
            cron += getSelectedSeparatedBy('day[]') + " ";
            cron += getSelectedSeparatedBy('month[]') + " ";
            cron += getSelectedSeparatedBy('weekday[]') + " ";
            CRM.$('.crm-job-form-block-name_crontab').show();
        }
        CRM.$("#crontab_frequency").val(CRM.$.trim(cron));
    }

    /**
     * Show hide element
     */
    function crontabshowhide() {
        if (CRM.$('#crontab_apply').prop("checked") == true) {
            CRM.$('.crm-job-form-block-name_crontab_frequency_offset').show();
            CRM.$('.crm-job-form-block-name_crontab_frequency').show();
            CRM.$('.crm-job-form-block-name_crontab').show();
            CRM.$('.crm-job-form-block-name_basic_crontab').show();
            CRM.$('#run_frequency').prop('disabled', true);
        }
        else {
            CRM.$('.crm-job-form-block-name_crontab_frequency_offset').hide();
            CRM.$('.crm-job-form-block-name_crontab_frequency').hide();
            CRM.$('.crm-job-form-block-name_crontab').hide();
            CRM.$('.crm-job-form-block-name_basic_crontab').hide();
            CRM.$('#run_frequency').prop('disabled', false);
        }
    }
    CRM.$(document).ready(function() {
        // show block in right order
        CRM.$('.crm-job-form-block-name_crontab_frequency_offset').insertAfter('.crm-job-form-block-run_frequency');
        CRM.$('.crm-job-form-block-name_crontab').insertAfter('.crm-job-form-block-run_frequency');
        CRM.$('.crm-job-form-block-name_crontab_frequency').insertAfter('.crm-job-form-block-run_frequency');
        CRM.$('.crm-job-form-block-name_basic_crontab').insertAfter('.crm-job-form-block-run_frequency');
        CRM.$('.crm-job-form-block-name_crontab_apply').insertAfter('.crm-job-form-block-run_frequency');

        regenerateCronFormat();
        crontabshowhide();
        CRM.$('#hour, #minute, #day, #month, #weekday, #basic_crontab, #crontab_hour_range').on('change', regenerateCronFormat);
        CRM.$('#crontab_apply').on('change', crontabshowhide);
        CRM.$('#crontab_frequency').attr("readonly", "readonly");
    });

</script>
{/literal}
<table style="display:none;">
    <tr class="crm-job-form-block-name_crontab">
        <td style="vertical-align: middle;"></td>
        <td>
            <table id="crontab_table">
                <tr class="crm-job-form-block-name_crontab_sub">
                    <th>{ts}Minute{/ts}</th>
                    <th>{ts}Hour{/ts}</th>
                    <th>{ts}Day{/ts}</th>
                    <th>{ts}Month{/ts}</th>
                    <th>{ts}Day of week{/ts}</th>
                </tr>
                <tr>
                    <td style="vertical-align: middle;">{$form.minute.html}</td>
                    <td>{$form.hour.html}
                        <br/>{$form.crontab_hour_range.label}:
                        {$form.crontab_hour_range.html}{help id="id-crontab_hour_range" file="CRM/Admin/Page/Job.extra.hlp"}
                    </td>
                    <td>{$form.day.html}</td>
                    <td>{$form.month.html}</td>
                    <td>{$form.weekday.html}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr class="crm-job-form-block-name_crontab_frequency" style="">
        <td class="label">{$form.crontab_frequency.label}</td>
        <td>{$form.crontab_frequency.html|crmAddClass:huge40}</td>
    </tr>
    <tr class="crm-job-form-block-name_crontab_frequency_offset" style="">
        <td class="label">{$form.crontab_offset.label}</td>
        <td>{$form.crontab_offset.html} {help id="id-crontab_offset" file="CRM/Admin/Page/Job.extra.hlp"}</td>
    </tr>
    <tr class="crm-job-form-block-name_crontab_apply" style="">
        <td class="label">{$form.crontab_apply.label}</td>
        <td>{$form.crontab_apply.html}<br />
            <div class="description">{ts}Once you enable this setting, 'Run frequency' setting never get used.{/ts}</div>
        </td>
    </tr>
    <tr class="crm-job-form-block-name_basic_crontab" style="">
        <td class="label">{$form.basic_crontab.label}</td>
        <td>{$form.basic_crontab.html}
        </td>
    </tr>
</table>