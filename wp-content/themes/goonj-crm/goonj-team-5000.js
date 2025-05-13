/*
 * This script automatically checks the 'is_recur' checkbox by default.
 * The checkbox cannot be changed by the user.
 * It also replaces the installments text input with a select dropdown,
 * pre-selecting 7 years (84 months), and displays the recurHelp section.
 */
document.addEventListener('DOMContentLoaded', function () {
    var isRecurCheckbox = document.getElementById('is_recur');
    var installmentsField = document.getElementById('installments');
    var recurHelp = document.getElementById('recurHelp');

    // Ensure all elements are found before proceeding
    if (!isRecurCheckbox || !installmentsField || !recurHelp) return;

    // Automatically check the checkbox and prevent user interaction
    isRecurCheckbox.checked = true;
    isRecurCheckbox.addEventListener('click', function (event) {
        event.preventDefault();
    });

    // Enable installments field and display the help section
    recurHelp.style.display = 'block';

    // Create a select dropdown for duration options
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

    options.forEach(function (opt) {
        var option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        select.appendChild(option);
    });

    // Pre-select "7 years - 84 months"
    select.value = "84";

    // Replace the input field with the dropdown
    installmentsField.parentNode.replaceChild(select, installmentsField);
});
