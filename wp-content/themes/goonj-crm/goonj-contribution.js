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
    var isRecurCheckbox = document.getElementById('#is_recur');
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