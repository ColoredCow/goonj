// Change div position based on radio button selection
document.addEventListener('DOMContentLoaded', function() {
    var donateMonthlyRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio[value="2"]');
    var recurringSection = document.querySelector('.crm-public-form-item.crm-section.is_recur-section');
    var fieldset = document.querySelector('.crm-public-form-item.crm-group.custom_pre_profile-group fieldset');
    var firstDiv = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset div:first-of-type');
    var radioButtons = document.querySelectorAll('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio');

    // Function to apply styles to recurring section and first div
    function applyRecurringStyles() {
        if (donateMonthlyRadio && donateMonthlyRadio.checked) {
            if (firstDiv) {
                firstDiv.style.position = 'relative';
                firstDiv.style.bottom = '55px';
            } else {
            }
            if (recurringSection) {
                recurringSection.style.position = 'relative';
                recurringSection.style.transform = 'translateY(100px)';
            }
        } else {
            if (firstDiv) {
                firstDiv.removeAttribute('style'); // Remove all inline styles
                firstDiv.offsetHeight; // Force reflow to recalculate layout
            }
            // Do not modify recurringSection unless Donate Monthly is selected
        }
    }

    // Initial style check
    if (donateMonthlyRadio) {
        applyRecurringStyles();
    }

    // Add event listener for all radio button changes
    radioButtons.forEach(function(radio) {
        radio.addEventListener('change', function() {
            applyRecurringStyles();
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var radioButtons = document.querySelectorAll('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio');
    var recurringSection = document.querySelector('#crm-container #crm-main-content-wrapper form .crm-contribution-main-form-block .crm-public-form-item.crm-section.is_recur-section');
    var isRecurCheckbox = document.getElementById('is_recur');
    var installmentsField = document.getElementById('installments');
    var isRecurCheckbox = document.getElementById('is_recur');

	// Check and lock the checkbox
	isRecurCheckbox.checked = true;
	isRecurCheckbox.addEventListener('click', function (event) {
		event.preventDefault();
	});

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
