/*
 * This script automatically checks the 'is_recur' checkbox by default.
 * The checkbox cannot be changed by the user.
 * When the checkbox is checked, the 'installments' field is enabled, and the 'recurHelp' section is displayed.
 */
document.addEventListener('DOMContentLoaded', function() {
    var isRecurCheckbox = document.getElementById('is_recur');
    var installmentsField = document.getElementById('installments');
    var recurHelp = document.getElementById('recurHelp');

    // Check the checkbox by default
    isRecurCheckbox.checked = true;

    // Prevent the user from changing the checkbox state
    isRecurCheckbox.addEventListener('click', function(event) {
        event.preventDefault();
    });

    // Enable the installments field and display recurHelp by default
    installmentsField.disabled = false;
    recurHelp.style.display = 'block';
});


document.addEventListener('DOMContentLoaded', function() {
    // Create the select dropdown
    var select = document.createElement('select');
    select.id = 'installments';
    select.name = 'installments';
    select.className = 'crm-form-text valid';

    var options = [
        { label: '1 year - 12 months', value: 12 },
        { label: '2 years - 24 months', value: 24 },
        { label: '3 years - 36 months', value: 36 },
        { label: '4 years - 48 months', value: 48 },
        { label: '5 years - 60 months', value: 60 },
        { label: '6 years - 72 months', value: 72 },
        { label: '7 years - 84 months', value: 84 }
    ];

    options.forEach(function(opt) {
        var option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        select.appendChild(option);
    });

    // Set default selected value to 84 (7 years)
    select.value = "84";

    // Replace the old input field
    installmentsField.parentNode.replaceChild(select, installmentsField);
});
