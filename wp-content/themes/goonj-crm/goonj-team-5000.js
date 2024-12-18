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
