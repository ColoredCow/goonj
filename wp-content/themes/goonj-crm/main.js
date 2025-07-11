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
    const checkbox = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-checkbox');
    const panFieldContainer = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset > div:nth-last-child(5)');
    const panInput = document.querySelector('.crm-contribution-main-form-block .custom_pre_profile-group fieldset > div:nth-last-child(5) .content input');
    const form = document.querySelector('.crm-contribution-main-form-block');
    let errorElement = panFieldContainer.querySelector('.error-message');

    // Create error message element if it doesn't exist
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.style.color = 'red';
        errorElement.style.display = 'none';
        panFieldContainer.appendChild(errorElement);
    }

    // Function to show/hide error message
    function showError(message) {
        errorElement.textContent = message;
        errorElement.style.display = message ? 'block' : 'none';
    }

    // Function to toggle PAN field visibility
    function togglePanField() {
        const isChecked = checkbox.checked;
        if (isChecked) {
            panFieldContainer.style.display = 'block';
            if (panInput) {
                panInput.required = true;
            }
        } else {
            panFieldContainer.style.display = 'none';
            if (panInput) {
                panInput.value = '';
                panInput.required = false;
                showError('');
            }
        }
    }

    togglePanField();

    // Add event listener for checkbox changes
    checkbox.addEventListener('change', function() {
        togglePanField();
    });

});

document.addEventListener('DOMContentLoaded', function() {
    // Update the checkbox label text
    const checkboxLabel = document.querySelector('label[for="is_recur"]');
    if (checkboxLabel) {
        checkboxLabel.textContent = 'Select Number of months you wish to contribute';
    }

    // Hide the installments label
    const installmentsLabel = document.querySelector('label[for="installments"]');
    if (installmentsLabel) {
        installmentsLabel.style.display = 'none';
    }
});