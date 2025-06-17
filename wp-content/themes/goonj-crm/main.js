// Consolidated DOMContentLoaded listener
document.addEventListener("DOMContentLoaded", function () {
	// Message handling
	handleUrlMessages();

	// Form reset handling
	handleFormReset();

	// Form validation
	setupFormValidation();

	// Limit installments input length
	limitInstallmentsInput();
});

// Message handling logic
function handleUrlMessages() {
	var hash = window.location.hash.substring(1); // Remove the '#'
	var params = new URLSearchParams(hash);
	var message = params.get("message");

	if (message) {
		var messageDiv = document.getElementById("custom-message");
		if (messageDiv) {
			if (
				message === "not-inducted-volunteer" ||
				message === "individual-user"
			) {
				messageDiv.innerHTML = `
					  <p class="fw-600 font-sans fz-20 mb-6">You are not registered as a volunteer with us.</p>
					  <p class="fw-400 font-sans fz-16 mt-0 mb-24">To set up a collection camp, please take a moment to fill out the volunteer registration form below. We can't wait to have you on board!</p>
				  `;
			} else if (
				message === "dropping-center" ||
				message === "dropping-center-individual-user"
			) {
				messageDiv.innerHTML = `
		  <p class="fw-600 font-sans fz-20 mb-6">You are not registered as a volunteer with us.</p>
		  <p class="fw-400 font-sans fz-16 mt-0 mb-24">To set up a dropping center, please take a moment to fill out the volunteer registration form below. We can't wait to have you on board!</p>
		  `;
			} else if (message === "past-collection-data") {
				messageDiv.innerHTML = `
					  <div class="w-520 mt-30 m-auto">
						  <p class="fw-400 fz-20 mb-11 font-sans">Goonj Collection Camp</p>
						  <p class="fw-400 fz-16 mt-0 mb-24 font-sans">It seems like you have created collection camps in the past. Would you like to duplicate the location details from your last collection camp?</p>
					  </div>
				  `;
			} else if (message === "collection-camp-page") {
				messageDiv.innerHTML = `
					  <div class="w-520 mt-30">
						  <p class="fw-400 fz-20 mb-11 font-sans">Goonj Collection Camp</p>
						  <p class="fw-400 fz-16 mt-0 mb-24 font-sans">Please provide the details related to the collection camp you want to organize. These details will be sent to Goonj for authorization.</p>
					  </div>
				  `;
			} else if (message === "not-inducted-for-dropping-center") {
				messageDiv.innerHTML = `
					  <div class="w-520 mt-30">
						  <p class="fw-400 fz-20 mb-11 font-sans">You are not registered as a volunteer with us.</p>
						  <p class="fw-400 fz-16 mt-0 mb-24 font-sans">To set up a dropping centre, please take a moment to fill out the volunteer registration form below. We can't wait to have you on board!</p>
					  </div>
				  `;
			}
		}
	}
}

// Temporary form reset handling
function handleFormReset() {
	setTimeout(function () {
		var resetButton = document.querySelector('button[type="reset"]');

		if (resetButton) {
			resetButton.addEventListener("click", function (event) {
				event.preventDefault();

				// Refresh the page to reset all fields
				location.reload(true);
			});
		}
	}, 1000);
}

// Form validation setup
function setupFormValidation() {
	const fields = [
		{
			labelText: "Mobile Number",
			regex: /^\d{10}$/,
			errorMessage: "Please enter a valid 10-digit mobile number.",
		},
		{
			labelText: "Phone",
			regex: /^\d{10}$/,
			errorMessage: "Please enter a valid 10-digit mobile number.",
		},
		{
			labelText: "PAN Card Number",
			regex: /^[a-zA-Z0-9]{10}$/,
			errorMessage: "Please enter a valid 10-digit PAN card number.",
		},
	];

	fields.forEach((field) => {
		const label = Array.from(document.querySelectorAll("label")).find((el) =>
			el.textContent.includes(field.labelText)
		);
	
		if (label) {
			const input = document.querySelector(
				`input[name="${label.getAttribute("for")}"]`
			);
	
			if (input) {
				const form = input.closest("form");
	
				if (form) {
					form.addEventListener("submit", function (event) {
						const value = input.value.trim();
	
						// If the field is required, validate it
						if (field.required && !value) {
							event.preventDefault();
							alert(`${field.labelText} is required.`);
							input.focus();
							return;
						}
	
						// If the field has a regex validation, apply it only when value is present
						if (value && field.regex && !field.regex.test(value)) {
							event.preventDefault();
							alert(field.errorMessage);
							input.focus();
						}
					});
				}
			}
		}
	});
	
}

// Limit the length of installments input
function limitInstallmentsInput() {
	const installmentsInput = document.getElementById("installments");
	if (installmentsInput) {
		installmentsInput.addEventListener("input", function () {
			if (this.value.length > 3) {
				this.value = this.value.slice(0, 3); // Limit to 3 characters
			}
		});
	}
}

document.addEventListener("DOMContentLoaded", function () {
    let cancelButton = document.getElementById("_qf_Optout_cancel-bottom");

    if (cancelButton) {
      cancelButton.addEventListener("click", function (event) {
        event.preventDefault();
        window.location.href = "https://mail.google.com/";
      });
    }
});

// Hide specific form items in the thank you page for events.
document.addEventListener("DOMContentLoaded", function() {
	var formItems = document.querySelectorAll('.crm-event-thankyou-form-block .crm-group.participant_info-group fieldset .crm-public-form-item');
  
	formItems.forEach(function(item) {
	  var label = item.querySelector('.label');
	  
	  if (label) {
		var labelText = label.textContent.trim();
		
		// Check if the label matches either of the two specific labels
		if (labelText === "Number of Adults Including You" || labelText === "Number of Children Accompanying You") {
			item.style.setProperty('display', 'none', 'important');
		}
	  }
	});
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded, script running...');

    // Get the radio buttons, recurring section, is_recur checkbox, and installments field
    var radioButtons = document.querySelectorAll('input[name="custom_759"]');
    var recurringSection = document.querySelector('#crm-container #crm-main-content-wrapper form .crm-contribution-main-form-block .crm-public-form-item.crm-section.is_recur-section');
    var isRecurCheckbox = document.getElementById('is_recur');
    var installmentsField = document.getElementById('installments');

    // Debug: Check if elements are found
    console.log('Radio buttons found:', radioButtons.length);
    console.log('Recurring section found:', recurringSection);
    console.log('is_recur checkbox found:', isRecurCheckbox);
    console.log('Installments field found:', installmentsField);

    // Set "Donate Once" as default on page load
    var donateOnceRadio = document.querySelector('input[name="custom_759"][value="1"]');
    if (donateOnceRadio) {
        donateOnceRadio.checked = true;
        console.log('Donate Once radio button selected by default');
    }

    // Replace installments text input with a dropdown
    if (installmentsField) {
        // Create the new select dropdown
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

        // Set default value to 12 months (or any preferred default)
        select.value = "12";
        console.log('Installments dropdown created with default value:', select.value);

        // Replace the text input with the dropdown
        installmentsField.parentNode.replaceChild(select, installmentsField);
        console.log('Installments text input replaced with dropdown');
    }

    // Function to toggle the recurring section and handle the checkbox
    function toggleRecurringSection() {
        console.log('toggleRecurringSection called');
        var selectedRadio = document.querySelector('input[name="custom_759"]:checked');
        if (!selectedRadio) {
            console.log('No radio button selected, hiding recurring section by default');
            recurringSection.style.setProperty('display', 'none', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = false;
                console.log('is_recur checkbox unchecked (no radio selected)');
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
                console.log('Change event dispatched for is_recur (no radio selected)');
            }
            return;
        }

        var selectedValue = selectedRadio.value;
        console.log('Selected value:', selectedValue);

        if (selectedValue === '2') { // "Donate Monthly" is selected
            console.log('Showing recurring section');
            recurringSection.style.setProperty('display', 'block', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = true;
                console.log('is_recur checkbox checked (Donate Monthly selected)');
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
                console.log('Change event dispatched for is_recur (Donate Monthly selected)');
            }
        } else {
            console.log('Hiding recurring section');
            recurringSection.style.setProperty('display', 'none', 'important');
            if (isRecurCheckbox) {
                isRecurCheckbox.checked = false;
                console.log('is_recur checkbox unchecked (Donate Once selected)');
                // Dispatch change event to ensure CiviCRM updates the form
                var changeEvent = new Event('change');
                isRecurCheckbox.dispatchEvent(changeEvent);
                console.log('Change event dispatched for is_recur (Donate Once selected)');
            }
        }
    }

    // Bind the change event to the radio buttons
    radioButtons.forEach(function(radio) {
        radio.addEventListener('change', function() {
            console.log('Radio button changed:', radio.value);
            toggleRecurringSection();
        });
    });

    // Run on page load to set initial state
    console.log('Running initial toggle');
    toggleRecurringSection();
});
