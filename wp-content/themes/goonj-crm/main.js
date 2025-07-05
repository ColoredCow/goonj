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


// Dummy function to show usage
function fetchCitiesForState(stateName) {
  console.log("🔄 Fetching cities for:", stateName);
  // You can use stateName to do something, or map to ID, etc.
}

document.addEventListener('DOMContentLoaded', function () {
  const intervalId = setInterval(() => {
    const stateFieldWrapper = document.querySelector('af-field[name="state_province_id"]');
    const cityFieldWrapper = document.querySelector('af-field[name="city"]');
    const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');
    const chosenSpan = document.getElementById('select2-chosen-1');

    if (!stateFieldWrapper || !cityFieldWrapper || !cityInput || !chosenSpan) {
      console.log("⏳ Waiting for form fields to load...");
      return;
    }

    clearInterval(intervalId);
    console.log("✅ Form fields found. Initializing city dropdown.");

    cityInput.style.display = 'none';

    const citySelect = document.createElement('select');
    citySelect.className = 'form-control';
    citySelect.name = 'city-dropdown';
    citySelect.innerHTML = `
      <option value="">Select a city</option>
      <option value="Other">Other</option>
    `;
    cityInput.parentElement.appendChild(citySelect);

    // Initialize Select2 with search bar always visible
    function applySelect2() {
      if (window.jQuery && jQuery.fn.select2) {
        jQuery(citySelect).select2('destroy'); // in case already initialized
        jQuery(citySelect).select2({
          placeholder: "Select a city",
          allowClear: true,
          width: 'resolve',
          minimumResultsForSearch: 0 // 🔍 always show search bar
        });

        jQuery(citySelect).next('.select2-container').css({
          width: '100%',
          'max-width': '340px'
        });
      }
    }

    applySelect2();

    citySelect.addEventListener('change', () => {
      cityInput.value = citySelect.value;
      cityInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    let lastState = chosenSpan.textContent.trim();
    const observer = new MutationObserver(() => {
      const currentState = chosenSpan.textContent.trim();

      if (currentState !== lastState) {
        console.log("📦 State changed:", lastState, "→", currentState);
        lastState = currentState;

        fetch('https://goonj.test/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'get_cities_by_state',
            state_name: currentState,
          }),
        })
          .then((res) => res.json())
          .then((data) => {
            citySelect.innerHTML = `<option value="">Select a city</option>`;
            if (data.success && data.data?.cities?.length) {
              data.data.cities.forEach((city) => {
                const opt = document.createElement('option');
                opt.value = city.name;
                opt.textContent = city.name;
                citySelect.appendChild(opt);
              });

              citySelect.appendChild(new Option("Other", "Other"));
              applySelect2(); // Re-apply select2 after options update
              jQuery(citySelect).trigger('change');
            } else {
              console.warn("⚠️ No cities found for:", currentState);
              applySelect2();
            }
          })
          .catch((err) => {
            console.error("❌ Error loading cities:", err);
          });
      }
    });

    observer.observe(chosenSpan, {
      characterData: true,
      childList: true,
      subtree: true,
    });

    console.log("👀 Watching for state changes...");
  }, 500);
});
// document.addEventListener('DOMContentLoaded', function () {
//   setTimeout(() => {
//     // 1. Find hidden Angular crm-ui-select inputs
//     const stateInput = document.querySelector('af-field[name="Collection_Camp_Intent_Details.State"] input[crm-ui-select]');
//     const cityInputHidden = document.querySelector('af-field[name="Collection_Camp_Intent_Details.city_id"] input[crm-ui-select]');

//     if (!stateInput || !cityInputHidden) {
//       console.warn("❌ Couldn't find state or city input fields.");
//       return;
//     }

//     // 2. Replace city hidden input with <select>
//     const citySelect = document.createElement('select');
//     citySelect.id = cityInputHidden.id;
//     citySelect.name = cityInputHidden.name || cityInputHidden.id;
//     citySelect.className = 'form-control';
//     citySelect.required = cityInputHidden.required;
//     citySelect.appendChild(new Option('Select a city', ''));

//     const wrapper = cityInputHidden.parentElement;
//     wrapper.replaceChild(citySelect, cityInputHidden);

//     // Remove old Select2 container (optional cleanup)
//     const oldSelect2Div = document.getElementById('s2id_' + citySelect.id);
//     if (oldSelect2Div) oldSelect2Div.remove();

//     // Reinit select2 on the new <select>
//     if (window.jQuery && jQuery.fn.select2) {
//       jQuery(citySelect).select2();
//       console.log("✅ Select2 reinitialized for city dropdown.");
//     }

//     // 3. Listen to state change and fetch cities
//     stateInput.addEventListener('change', function () {
//       const stateId = this.value;
//       console.log("📦 State changed to:", stateId);

//       if (!stateId) {
//         citySelect.innerHTML = '<option value="">Select a city</option>';
//         jQuery(citySelect).trigger('change');
//         return;
//       }

//       fetch(CRM.vars.mymodule.ajaxurl, {
//         method: 'POST',
//         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//         body: new URLSearchParams({
//           action: 'get_cities_by_state',
//           state_id: stateId,
//         }),
//       })
//         .then(res => res.json())
//         .then(data => {
//           if (data.success && data.data?.cities?.length > 0) {
//             citySelect.innerHTML = '<option value="">Select a city</option>';
//             data.data.cities.forEach(city => {
//               const opt = document.createElement('option');
//               opt.value = city.id;
//               opt.textContent = city.name;
//               citySelect.appendChild(opt);
//             });
//             jQuery(citySelect).trigger('change');
//             console.log("✅ Cities loaded into dropdown.");
//           } else {
//             console.warn("⚠️ No cities found for state ID:", stateId);
//           }
//         })
//         .catch(err => {
//           console.error("❌ Error fetching cities:", err);
//         });
//     });
//   }, 1000); // Allow Angular DOM rendering to finish
// });

// Hide specific form items in the thank you page for events.
// document.addEventListener('DOMContentLoaded', function () {
//   setTimeout(() => {
//     // 1. Find the city input via label
//     function findInputByLabel(labelText) {
//       const labels = Array.from(document.querySelectorAll('label'));
//       const label = labels.find(l => l.textContent.trim().includes(labelText));
//       if (!label) return null;
//       const inputId = label.getAttribute('for');
//       return inputId ? document.getElementById(inputId) : null;
//     }

//     const cityInput = findInputByLabel('Intent Details: city id');

//     if (!cityInput) {
//       console.error("❌ City input not found.");
//       return;
//     }

//     // 2. Fetch cities for state ID 1098 (Uttarakhand)
//     const stateId = 1098;

//     fetch(window.ajaxurl, {
//       method: 'POST',
//       headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//       body: new URLSearchParams({
//         action: 'get_cities_by_state',
//         state_id: stateId
//       })
//     })
//       .then(res => res.json())
//       .then(data => {
//         if (!data.success || !Array.isArray(data.data?.cities)) {
//           console.warn("⚠️ No cities returned.");
//           return;
//         }

//         const cities = data.data.cities;

//         // 3. Populate city input via Angular isolateScope
//         const angularElement = angular.element(cityInput);
//         const isolateScope = angularElement.isolateScope();

//         if (!isolateScope) {
//           console.error("❌ Cannot access Angular isolate scope.");
//           return;
//         }

//         isolateScope.select2Options.data = cities.map(city => ({
//           id: city.id,
//           text: city.name
//         }));

//         // 4. Apply Angular changes and trigger change event
//         isolateScope.$applyAsync(() => {
//           cityInput.value = '';
//           angularElement.triggerHandler('change');
//           console.log("✅ City dropdown updated for state ID 1098.");
//         });
//       })
//       .catch(err => {
//         console.error("❌ Error fetching cities:", err);
//       });
//   }, 1000); // Wait for DOM + Angular to render
// });
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
