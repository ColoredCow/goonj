<div class="tabsBlockContent overlay-mode">
  <div class="row">
    <div class="col-md-9">
      <div class="contact-filter">

        <div class="crm-accordion-wrapper crm-search_filters-calendar-accordion collapsed">
          <div class="crm-accordion-header">
              {ts}Contact filter{/ts}
          </div><!-- /.crm-accordion-header -->
          <div class="crm-accordion-body">
            <table class="form-layout contact-filter-conteiner">
              <tr>
                <td style="vertical-align:top">
                    {$form.contact_id.html}
                  <div class="selected-contact-wrapper"></div>
                </td>
              </tr>
            </table>
          </div>
        </div>

      </div>
      <div class="group-filter">

        <div class="crm-accordion-wrapper crm-search_filters-calendar-accordion collapsed">
          <div class="crm-accordion-header">
              {ts}Group filter{/ts}
          </div>
          <div class="crm-accordion-body">
            <table class="form-layout group-filter-container">
              <tr>
                <td style="vertical-align:top">
                    {$form.group_id.html}
                  <div class="selected-group-wrapper"></div>
                </td>
              </tr>
            </table>
          </div>
        </div>

      </div>
      <div class="crm-accordion-wrapper crm-search_filters-calendar-accordion collapsed">
        <div class="crm-accordion-header">
            {ts}Edit Search Criteria{/ts}
        </div><!-- /.crm-accordion-header -->
        <div class="crm-accordion-body">

          <table class="form-layout">
            <tr>
                {if $event_is_enabled}
                  <td>
                    <label class="filter__item">
                      <input id="filterCheckboxEvents" type="checkbox" checked="checked"
                             class="styled_checkbox events_checkbox"/>
                      <span class="filter__item-checkbox" style="border-color:{$eventColor}">
                      <span class="filter__item-check" style="border-color:{$eventColor}"></span>
                    </span>
                      <span>{ts}Event{/ts}</span>
                    </label>
                  </td>
                {/if}

                {if $case_is_enabled}
                  <td>
                    <label class="filter__item">
                      <input id="filterCheckboxCase" type="checkbox" checked="checked"
                             class="styled_checkbox cases_checkbox"/>
                      <span class="filter__item-checkbox" style="border-color:{$caseColor}">
                      <span class="filter__item-check" style="border-color:{$caseColor}"></span>
                    </span>
                      <span>{ts}Case{/ts}</span>
                    </label>
                  </td>
                {/if}
              <td>
                <label class="filter__item">
                  <input id="filterCheckboxActivity" type="checkbox" checked="checked"
                         class="styled_checkbox activities_checkbox"/>
                  <span class="filter__item-checkbox" style="border-color:{$activityColor}">
                    <span class="filter__item-check" style="border-color:{$activityColor}"></span>
                  </span>
                  <span>{ts}Activity{/ts}</span>
                </label>
              </td>
            </tr>
            <tr>
                {if $event_is_enabled}
                  <td>
                      {$form.event_type.label}
                      {$form.event_type.html}
                  </td>
                {/if}

                {if $case_is_enabled}
                  <td>
                      {$form.case_type.label}
                      {$form.case_type.html}
                  </td>
                {/if}
              <td>
                  {$form.activity_type.label}
                  {$form.activity_type.html}
              </td>
            </tr>
          </table>

        </div><!-- /.crm-accordion-body -->
      </div><!-- /.crm-accordion-wrapper -->

      <div id="calendar" class="crm-summary-contactname-block crm-inline-edit-container"></div>
    </div>
  </div>

</div>

{literal}
<script type="text/javascript">
    CRM.$(function ($) {

        CRM.$(document).ready(function () {
            const $form = CRM.$('form.{/literal}{$form.formClass}{literal}');

            let contactIdSelect = CRM.$('#{/literal}{$form.contact_id.name}{literal}');
            let groupIdSelect = CRM.$('#{/literal}{$form.group_id.name}{literal}');

            {/literal}{if $event_is_enabled}{literal}
            let eventTypeSelect = CRM.$('#{/literal}{$form.event_type.name}{literal}');
            {/literal}{/if}{literal}

            {/literal}{if $case_is_enabled}{literal}
            let caseTypeSelect = CRM.$('#{/literal}{$form.case_type.name}{literal}');
            {/literal}{/if}{literal}

            let activityTypeSelect = CRM.$('#{/literal}{$form.activity_type.name}{literal}');
            let calendarSelects = CRM.$(
                    {/literal}{if $event_is_enabled}{literal}
                '#{/literal}{$form.event_type.name}{literal}' + ',' +
                    {/literal}{/if}{literal}

                    {/literal}{if $case_is_enabled}{literal}
                '#{/literal}{$form.case_type.name}{literal}' + ',' +
                    {/literal}{/if}{literal}

                '#{/literal}{$form.activity_type.name}{literal}'
            );
            var events_data;

            {/literal}{if $event_is_enabled}{literal}
            var filterCheckboxEvents = CRM.$('#filterCheckboxEvents');
            var checked_events = filterCheckboxEvents.prop('checked');
            {/literal}{/if}{literal}

            {/literal}{if $case_is_enabled}{literal}
            var filterCheckboxCase = CRM.$('#filterCheckboxCase');
            var checked_case = filterCheckboxCase.prop('checked');
            {/literal}{/if}{literal}

            var filterCheckboxActivity = CRM.$('#filterCheckboxActivity');
            var checked_activity = filterCheckboxActivity.prop('checked');

            if (!getCookie('selectedContactIds')) {
                setCookie('selectedContactIds', JSON.stringify([]));
            }

            if (!getCookie('selectedGroupIds')) {
                setCookie('selectedGroupIds', JSON.stringify([]));
            }

            var events_calendar = CRM.$('#calendar').fullCalendar({
                locale: '{/literal}{$locale}{literal}',
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,agendaWeek,agendaDay'
                },
                dayOfMonthFormat: 'ddd DD',
                timeFormat: '{/literal}{$timeFormat}{literal}',
                slotLabelFormat: '{/literal}{$timeFormat}{literal}',
                scrollTime: '{/literal}{$scrollTime}{literal}',
                height: '500',
                eventLimitText: "",
                defaultView: '{/literal}{$default_view}{literal}',
                nowIndicator: true,
                displayEventTime: true,
                eventSources: '',
                eventClick: function (event, element) {
                },
                dayClick: function (event, element) {
                },
                eventMouseover: function (calEvent, jsEvent) {
                },
                eventMouseout: function (calEvent, jsEvent) {
                },
                viewRender: function (view, element) {
                    renderEvents(view);
                },
                eventDrop: function (event, element) {
                    return false;
                },
                eventRender: function (eventObj, $el) {
                    setImgClass(eventObj.image_url, eventObj.contact_id);

                    let piwStyle = ' style="width:32px;height:32px;margin-right:1px;"';
                    let piStyle = ' style="width:32px;height:32px;"';
                    let img = '' +
                        '<div class="person-image-wrapper"' + piwStyle + '>' +
                        '<div class="person-image"' + piStyle + '>' +
                        '<img src="' + eventObj.image_url + '" alt="" class="circular" data-id="' + eventObj.contact_id + '" style="width:32px;height:32px;">' +
                        '</div>' +
                        '</div>' +
                        '';

                    $el.find('.fc-content').before(img);

                    let tooltipHtml = renderTooltip(eventObj);

                    $el.popover({
                        title: eventObj.display_name,
                        content: tooltipHtml,
                        trigger: 'hover',
                        placement: 'auto',
                        container: 'body',
                        html: true
                    });
                }
            });

            calendarSelects.crmSelect2();
            calendarSelects.change(function () {
                renderEvents(events_calendar.fullCalendar('getView'));
            });

            {/literal}{if $event_is_enabled}{literal}
            filterCheckboxEvents.change(function () {
                if (this.checked) {
                    checked_events = true;
                    events_calendar.fullCalendar('addEventSource', events_data['events']);

                    return;
                }

                checked_events = false;
                events_calendar.fullCalendar('removeEventSource', events_data['events']);
            });
            {/literal}{/if}{literal}

            {/literal}{if $case_is_enabled}{literal}
            filterCheckboxCase.change(function () {
                if (this.checked) {
                    checked_case = true;
                    events_calendar.fullCalendar('addEventSource', events_data['case']);

                    return;
                }

                checked_case = false;
                events_calendar.fullCalendar('removeEventSource', events_data['case']);
            });
            {/literal}{/if}{literal}

            filterCheckboxActivity.change(function () {
                if (this.checked) {
                    checked_activity = true;
                    events_calendar.fullCalendar('addEventSource', events_data['activity']);

                    return;
                }

                checked_activity = false;
                events_calendar.fullCalendar('removeEventSource', events_data['activity']);
            });

            contactIdSelect.change(function () {
                let contactIdVal = contactIdSelect.select2('val');
                let selectedContactIdsC = JSON.parse(getCookie('selectedContactIds'));
                let selectedContactIdsCSplited = spliting(selectedContactIdsC);

                if (selectedContactIdsCSplited.length >= 5) {
                    CRM.alert('{/literal}{ts}You can only view 5 calendars at a time.{/ts}{literal}', ts('Error'), 'error');

                    return;
                }

                if (CRM.$.inArray(contactIdVal + ':' + 1 || contactIdVal + ':' + 0, selectedContactIdsC) !== -1
                    || CRM.$('.selected-contact-wrapper').find('input[type=checkbox][data-id=' + contactIdVal + ']').length > 0
                ) {
                    return;
                }

                selectedContactIdsC.push(CRM.$(this).val() + ':' + 1);
                setCookie('selectedContactIds', JSON.stringify(selectedContactIdsC));
                renderSelectedContact();
                renderEvents(events_calendar.fullCalendar('getView'));

                CRM.$('#contact_id').val('');
                CRM.$('#contact_id').select2('val', '');
            });

            $form.on('click', '.selected-contact-wrapper .del', function () {
                let dataId = CRM.$(this).attr('data-id');

                CRM.$(this).parent('.selected-row').remove();

                let selectedContactIdsC = JSON.parse(getCookie('selectedContactIds'));
                selectedContactIdsC.splice(selectedContactIdsC.indexOf(dataId + ':' + 1 || dataId + ':' + 0), 1);
                setCookie('selectedContactIds', JSON.stringify(selectedContactIdsC));

                renderEvents(events_calendar.fullCalendar('getView'));
            });

            $form.on('change', '.selected-contact-wrapper input[type=checkbox]', function () {
                let dataId = CRM.$(this).attr('data-id');
                let selectedContactIdsC = JSON.parse(getCookie('selectedContactIds'));
                let selectedContactIdsCSplited = spliting(selectedContactIdsC);

                if (this.checked) {
                    if (selectedContactIdsCSplited.length >= 5) {
                        CRM.alert('{/literal}{ts}You can only view 5 calendars at a time.{/ts}{literal}', ts('Error'), 'error');
                        CRM.$(this).prop('checked', false);

                        return;
                    }

                    selectedContactIdsC[selectedContactIdsC.indexOf(dataId + ':' + '0')] = dataId + ':' + '1';
                } else {
                    selectedContactIdsC[selectedContactIdsC.indexOf(dataId + ':' + '1')] = dataId + ':' + '0';
                }

                setCookie('selectedContactIds', JSON.stringify(selectedContactIdsC));
                renderEvents(events_calendar.fullCalendar('getView'));
            });

            groupIdSelect.change(function () {
                let groupIdVal = groupIdSelect.select2('val');
                let selectedGroupIdsC = JSON.parse(getCookie('selectedGroupIds'));
                let selectedGroupIdsCSplited = spliting(selectedGroupIdsC);

                if (selectedGroupIdsCSplited.length >= 5) {
                    CRM.alert('{/literal}{ts}You can only view 5 calendars at a time.{/ts}{literal}', ts('Error'), 'error');
                    return;
                }

                let isGroupSelected = CRM.$.inArray(groupIdVal + ':' + 1 || groupIdVal + ':' + 0, selectedGroupIdsC) !== -1 || CRM.$('.selected-group-wrapper').find('input[type=checkbox][data-id=' + groupIdVal + ']').length > 0;

                if (isGroupSelected) {
                    return;
                }

                selectedGroupIdsC.push(CRM.$(this).val() + ':' + 1);
                setCookie('selectedGroupIds', JSON.stringify(selectedGroupIdsC));
                renderSelectedGroup();
                renderEvents(events_calendar.fullCalendar('getView'));
                CRM.$('#group_id').val('');
                CRM.$('#group_id').select2('val', '');
            });

            $form.on('click', '.selected-group-wrapper .del', function () {
                let dataId = CRM.$(this).attr('data-id');

                CRM.$(this).parent('.selected-row').remove();

                let selectedGroupIdsC = JSON.parse(getCookie('selectedGroupIds'));
                selectedGroupIdsC.splice(selectedGroupIdsC.indexOf(dataId + ':' + 1 || dataId + ':' + 0), 1);
                setCookie('selectedGroupIds', JSON.stringify(selectedGroupIdsC));
                renderEvents(events_calendar.fullCalendar('getView'));
            });

            $form.on('change', '.selected-group-wrapper input[type=checkbox]', function () {
                let dataId = CRM.$(this).attr('data-id');
                let selectedGroupIdsC = JSON.parse(getCookie('selectedGroupIds'));
                let selectedGroupIdsCSplited = spliting(selectedGroupIdsC);

                if (this.checked) {
                    if (selectedGroupIdsCSplited.length >= 5) {
                        CRM.alert('{/literal}{ts}You can only view 5 groups at a time.{/ts}{literal}', ts('Error'), 'error');
                        CRM.$(this).prop('checked', false);
                        return;
                    }

                    selectedGroupIdsC[selectedGroupIdsC.indexOf(dataId + ':' + '0')] = dataId + ':' + '1';
                } else {
                    selectedGroupIdsC[selectedGroupIdsC.indexOf(dataId + ':' + '1')] = dataId + ':' + '0';
                }

                setCookie('selectedGroupIds', JSON.stringify(selectedGroupIdsC));
                renderEvents(events_calendar.fullCalendar('getView'));
            });

            CRM.$('.row-contact-field').find('#s2id_contact_id').addClass('action-link').find('a').addClass('button').html('<span>{/literal}{ts}Select Contact{/ts}{literal}</span>');

            renderSelectedContact();
            renderSelectedGroup();

            function renderEvents(view) {
                let data = getEventsData();
                data['start'] = view.start.unix();
                data['end'] = view.end.unix();

                if (typeof events_calendar !== 'undefined') {
                    events_calendar.fullCalendar('removeEvents');
                }

                {/literal}
                const url = "{crmURL p="civicrm/ajax/calendar/overlaying" h=0}";
                {literal}

                CRM.$.ajax({
                    method: 'GET',
                    url: url,
                    data: data,
                    dataType: 'json',
                    success: function (data) {
                        events_data = data;

                        {/literal}{if $event_is_enabled}{literal}
                        if (typeof events_data['events'] !== 'undefined' && checked_events) {
                            events_calendar.fullCalendar('addEventSource', events_data['events']);
                        }
                        {/literal}{/if}{literal}

                        {/literal}{if $case_is_enabled}{literal}
                        if (typeof events_data['case'] !== 'undefined' && checked_case) {
                            events_calendar.fullCalendar('addEventSource', events_data['case']);
                        }
                        {/literal}{/if}{literal}

                        if (typeof events_data['activity'] !== 'undefined' && checked_activity) {
                            events_calendar.fullCalendar('addEventSource', events_data['activity']);
                        }

                        events_calendar.fullCalendar('rerenderEvents');
                    }
                });
            }

            function getEventsData() {
                {/literal}{if $event_is_enabled}{literal}
                let eventTypeId = eventTypeSelect.val();
                {/literal}{/if}{literal}

                {/literal}{if $case_is_enabled}{literal}
                let caseTypeId = caseTypeSelect.val();
                {/literal}{/if}{literal}

                let activityTypeId = activityTypeSelect.val();
                let selectedContactIdsC = spliting(JSON.parse(getCookie('selectedContactIds')));
                let selectedGroupIdsC = spliting(JSON.parse(getCookie('selectedGroupIds')));

                return {
                    {/literal}{if $event_is_enabled}{literal}
                    eventTypeId: eventTypeId,
                    {/literal}{/if}{literal}
                    {/literal}{if $case_is_enabled}{literal}
                    caseTypeId: caseTypeId,
                    {/literal}{/if}{literal}
                    activityTypeId: activityTypeId,
                    cid: selectedContactIdsC,
                    gid: selectedGroupIdsC
                };
            }

            function renderTooltip(event) {
                let tooltip = '<div class="tooltipEvent" >';

                tooltip += renderRowTooltip(event.title, '{/literal}{ts}Subject{/ts}{literal}');
                tooltip += renderRowTooltip(event.activityType, '{/literal}{ts}Type{/ts}{literal}');
                tooltip += renderRowTooltip(event.targetContact, '{/literal}{ts}With{/ts}{literal}');
                tooltip += renderRowTooltip(event.priority, '{/literal}{ts}Priority{/ts}{literal}');
                tooltip += renderRowTooltip(event.participantRole, '{/literal}{ts}Participant Role{/ts}{literal}');
                tooltip += renderRowTooltip(event.eventType, '{/literal}{ts}Type{/ts}{literal}');

                tooltip += '</div>';

                return tooltip;
            }

            function renderRowTooltip(value, title) {
                if (typeof value != 'undefined' && value != '') {
                    let tooltipContent = '<div><b>' + title + '</b>: ' + value + '</div>';
                    return tooltipContent;
                }

                return '';
            }

            function renderSelectedContact() {
                let selectedContactIdsC = JSON.parse(getCookie('selectedContactIds'));
                let contactIds = selectedContactIdsC.map(contact => contact.split(':')[0]);
                let contactIdsChecked = selectedContactIdsC.map(contact => contact.split(':')[1]);

                CRM.api3('Contact', 'get', {
                    'sequential': 1,
                    'return': ['display_name', 'email', 'image_URL', 'contact_type'],
                    'id': {'IN': contactIds}
                }).done(function (result) {
                    let rows = '';

                    if (result.is_error == 0) {
                        CRM.$.each(result.values, function (index, value) {
                            CRM.$.each(contactIds, function (i, v) {
                                if (value.contact_id == v) {
                                    value.is_checked = contactIdsChecked[i];
                                }
                            });

                            rows += redrerRowSelectedContact(value);
                        });

                    }

                    CRM.$('.selected-contact-wrapper').html(rows);
                });
            }

            function renderSelectedGroup() {
                let selectedGroupIdsC = JSON.parse(getCookie('selectedGroupIds'));
                let groupIds = selectedGroupIdsC.map(group => group.split(':')[0]);
                let groupIdsChecked = selectedGroupIdsC.map(group => group.split(':')[1]);

                CRM.api3('Group', 'get', {
                    'sequential': 1,
                    'return': ['title'],
                    'id': {'IN': groupIds}
                }).done(function (result) {
                    let rows = '';

                    if (result.is_error == 0) {
                        CRM.$.each(result.values, function (index, value) {
                            CRM.$.each(groupIds, function (i, v) {
                                if (value.id == v) {
                                    value.is_checked = groupIdsChecked[i];
                                }
                            });

                            rows += renderRowSelectedGroup(value);
                        });
                    }

                    CRM.$('.selected-group-wrapper').html(rows);
                });
            }

            function renderRowSelectedGroup(value) {
                let isChecked = value.is_checked == 1 ? 'checked' : '';

                return '' +
                    '<div class="selected-row">' +
                    '<div class="checkbox-subrow">' +
                    '<label for="show_group-' + value.id + '"><input ' +
                    'id="show_group-' + value.id + '" ' +
                    'name="show_group-' + value.id + '" ' +
                    'type="checkbox" ' +
                    'class="crm-form-checkbox" ' +
                    'data-id="' + value.id + '" ' +
                    isChecked +
                    '></label>' +
                    '</div>' +

                    '<div class="group-value">' +
                    '<div>' +
                    value.title +
                    '</div>' +
                    '</div>' +
                    '<div class="del ui-icon ui-icon-close" data-id="' + value.id + '">' +
                    '</div>' +
                    '</div>' +
                    '';
            }

            function getAbbreviation(display_name) {
                if (display_name != null) {
                    let displayNameItems = display_name.split(' ');

                    if (displayNameItems.length > 0) {
                        if (displayNameItems.length == 1) {
                            let abbreviation = displayNameItems[0].substring(0, 1);
                        } else if (displayNameItems.length == 2) {
                            let abbreviation = displayNameItems[0].substring(0, 1) + displayNameItems[1].substring(0, 1);
                        } else if (displayNameItems.length > 2) {
                            let abbreviation = displayNameItems[0].substring(0, 1) + displayNameItems[1].substring(0, 1) + displayNameItems[2].substring(0, 1);
                        }

                        return abbreviation.toUpperCase();
                    }
                }

                return '';
            }

            function redrerRowSelectedContact(value) {
                let isChecked = value.is_checked == 1 ? 'checked' : '';
                let imageURL = value.image_URL;

                if (!imageURL) {
                    if (value.contact_type == 'Individual') {
                        imageURL = '{/literal}{$imagePath}{literal}Person.svg';
                    } else if (value.contact_type == 'Organization') {
                        imageURL = '{/literal}{$imagePath}{literal}Organization.svg';
                    } else {
                        imageURL = '{/literal}{$imagePath}{literal}Person.svg';
                    }
                }

                let contactHref = CRM.url('civicrm/contact/view', {reset: 1, cid: value.contact_id});

                setImgClass(imageURL, value.contact_id);

                let piwStyle = ' style="width:32px;height:32px;"';

                return '' +
                    '<div class="selected-row">' +
                    '<div class="checkbox-subrow">' +
                    '<label for="show_contact-' + value.contact_id + '"><input ' +
                    'id="show_contact-' + value.contact_id + '" ' +
                    'name="show_contact-' + value.contact_id + '" ' +
                    'type="checkbox" ' +
                    'class="crm-form-checkbox" ' +
                    'data-id="' + value.contact_id + '" ' +
                    isChecked +
                    '></label>' +
                    '</div>' +

                    '<div class="person-image" ' + piwStyle + '>' +
                    '<img src="' + imageURL + '" alt="" class="circular" data-id="' + value.contact_id + '" style="width:32px;height:32px;">' +
                    '</div>' +

                    '<div class="contact-value">' +
                    '<div>' +
                    '<a href="' + contactHref + '" title="">' +
                    value.display_name +
                    '</a>' +
                    '</div>' +
                    '<div class="email-item">' +
                    value.email +
                    '</div>' +
                    '</div>' +
                    '<div class="del ui-icon ui-icon-close" data-id="' + value.contact_id + '">' +
                    '</div>' +
                    '</div>' +
                    '';
            }

            function setImgClass(imageURL, dataId) {
                let tmpImg = new Image();
                tmpImg.src = imageURL;

                CRM.$(tmpImg).one('load', function () {
                    let w = tmpImg.width;
                    let h = tmpImg.height;

                    CRM.$('.person-image img[data-id=' + dataId + ']').addClass(function () {
                        if (h === w) {
                            return 'square';
                        } else if (h > w) {
                            return 'portrait';
                        } else {
                            return 'landscape';
                        }
                    });
                });
            }

            function setCookie(cname, cvalue, exdays) {
                const d = new Date();
                d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
                const expires = 'expires=' + d.toUTCString();
                document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/';
            }

            function getCookie(cname) {
                let name = cname + '=';
                let ca = document.cookie.split(';');

                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];

                    while (c.charAt(0) == ' ') {
                        c = c.substring(1);
                    }

                    if (c.indexOf(name) == 0) {
                        return c.substring(name.length, c.length);
                    }
                }
                return '';
            }

            function spliting(selectedContactIdsC) {
                let returnArray = [];

                for (let i = 0; i < selectedContactIdsC.length; i++) {
                    let split = selectedContactIdsC[i].split(':');
                    if (split[1] == 1) {
                        returnArray.push(split[0]);
                    }
                }

                return returnArray;
            }
        });
    });
</script>
{/literal}
