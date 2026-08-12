(function injectCityDropdownCSS() {
  // City dropdown CSS moved to city-dropdown.js
  // This file is conditionally loaded based on page type
})();

/* ---------- Consolidated DOMContentLoaded listener ---------- */
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
      } else if (message === "waiting-induction-collection-camp") {
        messageDiv.innerHTML = `
		  <p class="fw-600 font-sans fz-20 mb-6">Your induction is pending.</p>
		  <p class="fw-400 font-sans fz-16 mt-0 mb-24"></p>
		  `;
      }  
        else if (
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
      regex: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
      errorMessage: "Invalid PAN format. Correct format: ABCDE1234F (5 letters, 4 digits, 1 letter).",
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
        if (!form) return;

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
document.addEventListener("DOMContentLoaded", function () {
  var formItems = document.querySelectorAll(
    ".crm-event-thankyou-form-block .crm-group.participant_info-group fieldset .crm-public-form-item"
  );

  formItems.forEach(function (item) {
    var label = item.querySelector(".label");

    if (label) {
      var labelText = label.textContent.trim();

      // Check if the label matches either of the two specific labels
      if (
        labelText === "Number of Adults Including You" ||
        labelText === "Number of Children Accompanying You"
      ) {
        item.style.setProperty("display", "none", "important");
      }
    }
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const checkbox = document.querySelector(
    ".crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-checkbox"
  );
  const panFieldContainer = document.querySelector(
    ".crm-contribution-main-form-block .custom_pre_profile-group fieldset > div:nth-last-child(5)"
  );
  const panInput = document.querySelector(
    ".crm-contribution-main-form-block .custom_pre_profile-group fieldset > div:nth-last-child(5) .content input"
  );
  const form = document.querySelector(".crm-contribution-main-form-block");

  if (!checkbox || !panFieldContainer || !panInput || !form) return;

  const PAN_REGEX = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;

  let errorElement = panFieldContainer.querySelector(".pan-error-message");
  if (!errorElement) {
    errorElement = document.createElement("div");
    errorElement.className = "pan-error-message";
    errorElement.style.color = "red";
    errorElement.style.display = "none";
    panFieldContainer.appendChild(errorElement);
  }

  function showError(message) {
    errorElement.textContent = message;
    errorElement.style.display = message ? "block" : "none";
  }

  function validatePan() {
    const value = panInput.value.trim();
    if (value && !PAN_REGEX.test(value)) {
      showError("Invalid PAN format. Correct format: ABCDE1234F (5 letters, 4 digits, 1 letter).");
      return false;
    }
    showError("");
    return true;
  }

  function togglePanField() {
    if (checkbox.checked) {
      panFieldContainer.style.display = "block";
    } else {
      panFieldContainer.style.display = "none";
      panInput.value = "";
      showError("");
    }
  }

  // Auto-uppercase as user types
  panInput.addEventListener("input", function () {
    const cursor = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(cursor, cursor);
  });

  // Validate only when user leaves the field
  panInput.addEventListener("blur", validatePan);

  // Block form submission if PAN is visible and invalid
  form.addEventListener("submit", function (event) {
    if (checkbox.checked && !validatePan()) {
      event.preventDefault();
      panInput.focus();
    }
  });

  togglePanField();

  checkbox.addEventListener("change", togglePanField);
});

document.addEventListener("DOMContentLoaded", function () {
  // Update the checkbox label text
  const checkboxLabel = document.querySelector('label[for="is_recur"]');
  if (checkboxLabel) {
    checkboxLabel.textContent =
      "Select Number of months you wish to contribute";
  }

  // Hide the installments label
  const installmentsLabel = document.querySelector('label[for="installments"]');
  if (installmentsLabel) {
    installmentsLabel.style.display = "none";
  }
});

document.addEventListener("DOMContentLoaded", function () {
  // City dropdown functionality moved to city-dropdown.js
  // which is conditionally loaded based on page type (excluded from contribution pages)
});

// An 80(G) certificate cannot be issued without a PAN, and it needs an address
// to print on it. So for as long as the contributor has the 80(G) option
// ticked, require those fields, and let them go again when it is unticked.
//
// PAN applies to every monetary page. The address applies only to the microsite
// profile — everywhere else the profile already marks the address required and
// CiviCRM handles it.
//
// The rules are handed to CiviCRM's own validator, so the messages render in
// the page's existing error style and block submit alongside the other required
// fields. PAN *format* checking is separate — see the block further up.
document.addEventListener('DOMContentLoaded', function() {
    // This file is loaded in the <head>, so this listener is registered before
    // jQuery's own — and CiviCRM attaches its validator from a jQuery ready
    // callback further down the page. Deferring by a tick lets that finish
    // first, whatever order the scripts happen to load in.
    window.setTimeout(setUp80gRequirements, 0);
});

function setUp80gRequirements() {
    var $ = window.CRM && CRM.$;
    if (!$) {
        return;
    }

    var PROFILE_TITLE = 'MS Individual Contribution';

    var ADDRESS_MESSAGE = 'To get the 80-G certificate, please enter your Address.';
    var PAN_MESSAGE = 'To get the 80-G certificate, please enter your PAN number.';
    var PAN_AND_ADDRESS_MESSAGE = 'To get the 80-G certificate, please enter your PAN number and Address.';

    var form = $('form.CRM_Contribute_Form_Contribution_Main');
    if (!form.length || !form.data('validator')) {
        return;
    }

    // CiviCRM prints the profile title in the fieldset's legend, so match on
    // that — the class holds the machine name instead, which is generated per
    // environment.
    var profiles = form.find('fieldset.crm-profile');
    var microsite = profiles.filter(function() {
        return $(this).children('legend').text().trim() === PROFILE_TITLE;
    });
    var isMicrosite = microsite.length > 0;
    var scope = isMicrosite ? microsite : profiles;

    // Both are custom fields, so their custom_NNN names are not the same on
    // every environment — match them on the labels contributors see.
    var checkbox = scope.find('input[type="checkbox"]').filter(function() {
        return $('label[for="' + this.id + '"]').text().indexOf('80(G)') !== -1;
    });
    var pan = scope.find('input[type="text"]').filter(function() {
        return $('label[for="' + this.id + '"]').text().trim() === 'PAN Card Number';
    }).first();

    // The address carries a location type suffix (-Primary, -5, ...).
    var address = scope.find('input[type="text"][name^="street_address-"]').first();

    if (!checkbox.length) {
        return;
    }

    // Only take charge of the address on the microsite, and only while its
    // profile leaves it optional. If the profile is ever changed to make it
    // mandatory in its own right, there is nothing for us to add — and nothing
    // we may take away when the option is unticked.
    var managesAddress = isMicrosite && address.length > 0 && !address.hasClass('required');
    var managesPan = pan.length > 0 && !pan.hasClass('required');

    if (!managesAddress && !managesPan) {
        return;
    }

    // Mark the rows we put messages under. Our messages are long sentences and
    // the row lays the field and its error out side by side, which leaves no
    // usable width on a phone — the stylesheet uses this class to drop the
    // message onto its own line there. It cannot key off the section's own
    // class because that carries the custom field ID, which differs per
    // environment.
    if (managesAddress) {
        address.closest('.crm-section').addClass('goonj-80g-field');
    }
    if (managesPan) {
        pan.closest('.crm-section').addClass('goonj-80g-field');
    }

    // `required` only measures the raw string length, so a field holding
    // nothing but spaces passes it. Trim before the rule reads the value,
    // otherwise "   " counts as an answer.
    function trim(value) {
        return typeof value === 'string' ? value.trim() : value;
    }

    function isBlank(field) {
        return !field.length || trim(field.val()) === '';
    }

    function require(field, message) {
        field.rules('add', {
            normalizer: trim,
            required: true,
            messages: { required: message }
        });
    }

    function release(field) {
        field.rules('remove', 'required normalizer');
        field.removeClass('crm-inline-error alert-danger').removeAttr('aria-invalid');
        form.find('label.crm-inline-error[for="' + field.attr('id') + '"]').remove();
    }

    // Both fields left blank is one problem, not two, so both of them say the
    // same thing. Resolved each time a message is shown rather than fixed when
    // the rule is added, so it follows what the contributor has filled in so
    // far.
    function bothMissing() {
        return managesAddress && managesPan && isBlank(address) && isBlank(pan);
    }

    function addressMessage() {
        return bothMissing() ? PAN_AND_ADDRESS_MESSAGE : ADDRESS_MESSAGE;
    }

    function panMessage() {
        return bothMissing() ? PAN_AND_ADDRESS_MESSAGE : PAN_MESSAGE;
    }

    function sync() {
        var wantsCertificate = checkbox.filter(':checked').length > 0;

        if (managesAddress) {
            if (wantsCertificate) {
                require(address, addressMessage);
            }
            else {
                release(address);
            }
        }

        if (managesPan) {
            if (wantsCertificate) {
                require(pan, panMessage);
            }
            else {
                release(pan);
            }
        }
    }

    checkbox.on('change', sync);

    // Also covers the option coming back ticked when CiviCRM re-renders the
    // form after a server-side validation failure.
    sync();
}
