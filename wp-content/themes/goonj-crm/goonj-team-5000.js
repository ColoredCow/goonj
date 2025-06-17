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
    var radioSection = document.getElementById('editrow-custom_759');

    if (!isRecurCheckbox || !installmentsField || !recurHelp) return;

    // Check and lock the checkbox
    isRecurCheckbox.checked = true;
    isRecurCheckbox.addEventListener('click', function (event) {
        event.preventDefault();
    });

    // Hide the radio button section
    if (radioSection) {
        radioSection.style.display = 'none';
        console.log('Radio button section (editrow-custom_759) hidden');
    }

    // Show help section
    recurHelp.style.display = 'block';

    // Create the new select dropdown
    var select = document.createElement('select');
    select.id = 'installments';
    select.name = 'installments';
    select.className = 'crm-form-text valid';

    var options = [
        { label: '1 year', value: 12 },
        { label: '3 years', value: 36 },
        { label: '7 years', value: 84 },
        { label: '10 years', value: 120 }
    ];

    options.forEach(function (opt) {
        var option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        select.appendChild(option);
    });

    // Set default value to 7 years
    select.value = "84";

    // Replace old input field with new dropdown
    installmentsField.parentNode.replaceChild(select, installmentsField);

    // Remove the adjacent label with text "installments"
    var labelForInstallments = document.querySelector('label[for="installments"]');
    if (labelForInstallments) {
        labelForInstallments.remove();
    }

    // Set "Donate Monthly" as default on page load
    var donateMonthlyRadio = document.querySelector('input[name="custom_759"][value="2"]');
    if (donateMonthlyRadio) {
        donateMonthlyRadio.checked = true;
        console.log('Donate Monthly radio button selected by default');
        // Dispatch change event to trigger toggleRecurringSection
        var changeEvent = new Event('change');
        donateMonthlyRadio.dispatchEvent(changeEvent);
        console.log('Change event dispatched for Donate Monthly radio button');
    }
});
