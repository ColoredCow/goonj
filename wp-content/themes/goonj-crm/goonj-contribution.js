// Change div position based on radio button selection
document.addEventListener('DOMContentLoaded', function() {
    var donateMonthlyRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio[value="2"]');
    var recurringSection = document.querySelector('.crm-public-form-item.crm-section.is_recur-section');
    var fieldset = document.querySelector('.crm-public-form-item.crm-group.custom_pre_profile-group fieldset');
    var firstDiv = document.getElementById('editrow-custom_759'); // Use ID for precision
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
                recurringSection.style.top = '90px';
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
