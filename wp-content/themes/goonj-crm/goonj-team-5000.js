/*
 * This script runs on the 'team-5000' page.
 * It automatically checks the checkbox with the ID 'is_recur' and disables it.
 * The checkbox is checked by default, and users cannot uncheck it.
 */
document.addEventListener('DOMContentLoaded', function() {
    var isRecurCheckbox = document.getElementById('is_recur');
    isRecurCheckbox.checked = true;
    isRecurCheckbox.disabled = true;
});
