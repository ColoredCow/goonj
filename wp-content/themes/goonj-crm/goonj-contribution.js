document.addEventListener('DOMContentLoaded', function() {
    console.log('Style script loaded, setting up event listeners...');
    var donateMonthlyRadio = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio[value="2"]');
    var recurringSection = document.querySelector('.crm-public-form-item.crm-section.is_recur-section');
    var fieldset = document.querySelector('.crm-public-form-item.crm-group.custom_pre_profile-group fieldset');
    var firstDiv = document.getElementById('editrow-custom_759'); // Use ID for precision
    var radioButtons = document.querySelectorAll('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-radio');

    // Function to apply styles to recurring section and first div
    function applyRecurringStyles() {
        if (donateMonthlyRadio && donateMonthlyRadio.checked) {
            console.log('Donate Monthly selected, applying styles...');
            if (firstDiv) {
                firstDiv.style.position = 'relative';
                firstDiv.style.bottom = '55px';
                console.log('Styles applied to firstDiv: position relative, bottom 55px');
            } else {
                console.log('First div (editrow-custom_759) not found.');
            }
            if (recurringSection) {
                recurringSection.style.position = 'relative';
                recurringSection.style.top = '79px';
                console.log('Styles applied to recurringSection: position relative, top 79px');
            } else {
                console.log('Recurring section not found.');
            }
        } else {
            console.log('Donate Once selected, removing styles from firstDiv...');
            if (firstDiv) {
                firstDiv.removeAttribute('style'); // Remove all inline styles
                firstDiv.offsetHeight; // Force reflow to recalculate layout
                console.log('Styles removed from firstDiv, inline style attribute cleared');
            }
            // Do not modify recurringSection unless Donate Monthly is selected
        }
    }

    // Initial style check
    if (donateMonthlyRadio) {
        applyRecurringStyles();
    } else {
        console.log('Donate Monthly radio button not found.');
    }

    // Add event listener for all radio button changes
    radioButtons.forEach(function(radio) {
        radio.addEventListener('change', function() {
            applyRecurringStyles();
        });
    });
});
