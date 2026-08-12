// Change div position based on radio button selection
document.addEventListener('DOMContentLoaded', function() {
    const donateMonthlyRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio[value="2"]');
    const recurringSection = document.querySelector('.crm-public-form-item.crm-section.is_recur-section');
    const firstDiv = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset div:first-of-type');
    const radioButtons = document.querySelectorAll('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio');

    // Function to apply styles to recurring section and first div
    function applyRecurringStyles() {
        try {
            // Check if donateMonthlyRadio exists and is checked
            if (donateMonthlyRadio && donateMonthlyRadio.checked) {
                if (firstDiv) {
                    firstDiv.style.position = 'relative';
                    firstDiv.style.bottom = '55px';
                    firstDiv.style.marginBottom = '30px';
                    // Force reflow to ensure styles apply correctly
                    firstDiv.offsetHeight;
                }
                if (recurringSection) {
                    recurringSection.style.position = 'relative';
                    recurringSection.style.transform = 'translateY(105px)';
                    recurringSection.style.marginTop = '-26px';
                }
            } else {
                if (firstDiv) {
                    // Reset styles to prevent lingering effects
                    firstDiv.style.position = '';
                    firstDiv.style.bottom = '';
                    firstDiv.style.marginBottom = '';
                    // Force reflow to recalculate layout
                    firstDiv.offsetHeight;
                }
                // Only reset recurringSection if it was previously modified
                if (recurringSection && !donateMonthlyRadio.checked) {
                    recurringSection.style.position = '';
                    recurringSection.style.transform = '';
                    recurringSection.style.marginTop = '';
                }
            }
        } catch (error) {
            console.error('Error in applyRecurringStyles:', error);
        }
    }

    // Initial style check with safety
    if (donateMonthlyRadio) {
        applyRecurringStyles();
    }

    // Add event listener for radio button changes with safety
    if (radioButtons.length > 0) {
        radioButtons.forEach(function(radio) {
            radio.addEventListener('change', function() {
                try {
                    applyRecurringStyles();
                } catch (error) {
                    console.error('Error handling radio button change:', error);
                }
            });
        });
    } else {
        console.warn('No radio buttons found for donation form');
    }

    // Observe DOM changes to handle dynamic content loading
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length || mutation.removedNodes.length) {
                applyRecurringStyles();
            }
        });
    });

    // Observe the form container for dynamic changes
    const formContainer = document.querySelector('.crm-contribution-main-form-block');
    if (formContainer) {
        observer.observe(formContainer, { childList: true, subtree: true });
    } else {
        console.warn('Form container not found for mutation observer');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var radioButtons = document.querySelectorAll('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio');
    var recurringSection = document.querySelector('#crm-container #crm-main-content-wrapper form .crm-contribution-main-form-block .crm-public-form-item.crm-section.is_recur-section');
    var isRecurCheckbox = document.getElementById('is_recur');
    var installmentsField = document.getElementById('installments');
    if (!isRecurCheckbox) {
        var firstProfileDiv = document.querySelector('.CRM_Contribute_Form_Contribution_Main .crm-contribution-main-form-block fieldset.crm-profile > div:first-of-type');
        if (firstProfileDiv) {
            firstProfileDiv.style.display = 'none';
        }
    }
    

    // Set "Donate Once" as default on page load
    var donateOnceRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio[value="1"]');
    if (donateOnceRadio) {
        donateOnceRadio.checked = true;
    }

    // Replace installments text input with a dropdown
    if (installmentsField) {
        var select = document.createElement('select');
        select.id = 'installments';
        select.name = 'installments';
        select.className = 'crm-form-text valid';

        // Define dropdown options (months)
        var options = [
            { label: '6 months', value: 6 },
            { label: '9 months', value: 9 },
            { label: '12 months', value: 12 },
            { label: '15 months', value: 15 },
            { label: '18 months', value: 18 },
            { label: '21 months', value: 21 },
            { label: '24 months', value: 24 }
        ];

        // Populate dropdown with options
        options.forEach(function(opt) {
            var option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            select.appendChild(option);
        });

        // Set default value to 12 months
        select.value = "12";

        // Replace the text input with the dropdown
        installmentsField.parentNode.replaceChild(select, installmentsField);
    }

    // Function to toggle the recurring section and handle the checkbox
    function toggleRecurringSection() {
        var selectedRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio:checked');
        if (!selectedRadio) {
            recurringSection.style.setProperty('display', 'none', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = false;
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
            }
            return;
        }

        var selectedValue = selectedRadio.value;

        if (selectedValue === '2') { // "Donate Monthly" is selected
            recurringSection.style.setProperty('display', 'block', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = true;
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
            }
        } else {
            recurringSection.style.setProperty('display', 'none', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = false;
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
            }
        }
    }

    // Bind the change event to the radio buttons
    radioButtons.forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleRecurringSection();
        });
    });

    // Run on page load to set initial state
    toggleRecurringSection();
});

// Show the campaign field only on processing-center (office QR) pages.
// On CC/DC/event source pages the field stays hidden (CSS default).
document.addEventListener('DOMContentLoaded', function() {
    if (typeof goonjContribution === 'undefined' || !goonjContribution.puSourceFieldId) {
        return;
    }

    var urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has(goonjContribution.puSourceFieldId)) {
        return;
    }

    var campaignSection = document.querySelector(
        '.crm-contribution-main-form-block .editrow_contribution_campaign_id-section'
    );
    if (campaignSection) {
        campaignSection.style.setProperty('display', 'block', 'important');
    }
});

// An 80(G) certificate cannot be issued without a PAN, and it needs an address
// to print on it. So for as long as the contributor has the 80(G) option
// ticked, require those fields, and let them go again when it is unticked.
//
// PAN applies to every monetary page. The address applies only to the microsite
// profile — everywhere else the profile already marks the address required and
// CiviCRM handles it.
//
// The rules are handed to CiviCRM's own validator, so the messages render in
// the page's existing error style and block submit alongside the other required
// fields. PAN *format* checking is separate and stays in main.js.
document.addEventListener('DOMContentLoaded', function() {
    var $ = window.CRM && CRM.$;
    if (!$) {
        return;
    }

    var PROFILE_TITLE = 'MS Individual Contribution';

    var ADDRESS_MESSAGE = 'To get the 80-G certificate, please enter your Address.';
    var PAN_MESSAGE = 'To get the 80-G certificate, please enter your PAN number.';
    var PAN_AND_ADDRESS_MESSAGE = 'To get the 80-G certificate, please enter your PAN number and Address.';

    var form = $('form.CRM_Contribute_Form_Contribution_Main');
    if (!form.length || !form.data('validator')) {
        return;
    }

    // CiviCRM prints the profile title in the fieldset's legend, so match on
    // that — the class holds the machine name instead, which is generated per
    // environment.
    var profiles = form.find('fieldset.crm-profile');
    var microsite = profiles.filter(function() {
        return $(this).children('legend').text().trim() === PROFILE_TITLE;
    });
    var isMicrosite = microsite.length > 0;
    var scope = isMicrosite ? microsite : profiles;

    // Both are custom fields, so their custom_NNN names are not the same on
    // every environment — match them on the labels contributors see.
    var checkbox = scope.find('input[type="checkbox"]').filter(function() {
        return $('label[for="' + this.id + '"]').text().indexOf('80(G)') !== -1;
    });
    var pan = scope.find('input[type="text"]').filter(function() {
        return $('label[for="' + this.id + '"]').text().trim() === 'PAN Card Number';
    }).first();

    // The address carries a location type suffix (-Primary, -5, ...).
    var address = scope.find('input[type="text"][name^="street_address-"]').first();

    if (!checkbox.length) {
        return;
    }

    // Only take charge of the address on the microsite, and only while its
    // profile leaves it optional. If the profile is ever changed to make it
    // mandatory in its own right, there is nothing for us to add — and nothing
    // we may take away when the option is unticked.
    var managesAddress = isMicrosite && address.length > 0 && !address.hasClass('required');
    var managesPan = pan.length > 0 && !pan.hasClass('required');

    if (!managesAddress && !managesPan) {
        return;
    }

    // Mark the rows we put messages under. Our messages are long sentences and
    // the row lays the field and its error out side by side, which leaves no
    // usable width on a phone — the stylesheet uses this class to drop the
    // message onto its own line there. It cannot key off the section's own
    // class because that carries the custom field ID, which differs per
    // environment.
    if (managesAddress) {
        address.closest('.crm-section').addClass('goonj-80g-field');
    }
    if (managesPan) {
        pan.closest('.crm-section').addClass('goonj-80g-field');
    }

    // `required` only measures the raw string length, so a field holding
    // nothing but spaces passes it. Trim before the rule reads the value,
    // otherwise "   " counts as an answer.
    function trim(value) {
        return typeof value === 'string' ? value.trim() : value;
    }

    function isBlank(field) {
        return !field.length || trim(field.val()) === '';
    }

    function require(field, message) {
        field.rules('add', {
            normalizer: trim,
            required: true,
            messages: { required: message }
        });
    }

    function release(field) {
        field.rules('remove', 'required normalizer');
        field.removeClass('crm-inline-error alert-danger').removeAttr('aria-invalid');
        form.find('label.crm-inline-error[for="' + field.attr('id') + '"]').remove();
    }

    // Both fields left blank is one problem, not two, so both of them say the
    // same thing. Resolved each time a message is shown rather than fixed when
    // the rule is added, so it follows what the contributor has filled in so
    // far.
    function bothMissing() {
        return managesAddress && managesPan && isBlank(address) && isBlank(pan);
    }

    function addressMessage() {
        return bothMissing() ? PAN_AND_ADDRESS_MESSAGE : ADDRESS_MESSAGE;
    }

    function panMessage() {
        return bothMissing() ? PAN_AND_ADDRESS_MESSAGE : PAN_MESSAGE;
    }

    function sync() {
        var wantsCertificate = checkbox.filter(':checked').length > 0;

        if (managesAddress) {
            if (wantsCertificate) {
                require(address, addressMessage);
            }
            else {
                release(address);
            }
        }

        if (managesPan) {
            if (wantsCertificate) {
                require(pan, panMessage);
            }
            else {
                release(pan);
            }
        }
    }

    checkbox.on('change', sync);

    // Also covers the option coming back ticked when CiviCRM re-renders the
    // form after a server-side validation failure.
    sync();
});