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

// CiviCRM renders every profile row as `.crm-section.editrow_<field>-section`,
// but the custom field IDs baked into those class names are generated per
// environment, so the theme cannot target them directly. The rows used to be
// found by counting from the end with :nth-last-child(), which breaks the
// moment a field is added to or removed from the profile — adding the DPDP
// consent fields shifted every count by two, hid the consent checkbox, exposed
// the autofill fields, and left the 80(G) toggle hiding a *required* field.
//
// Stamp a semantic class on each row instead, matched on the label the
// contributor sees. Those labels are identical on every environment.
const GOONJ_ROW_CLASS_BY_LABEL = {
  "Collection Source": "goonj-autofill-field",
  Office: "goonj-autofill-field",
  Events: "goonj-autofill-field",
  Campaign: "goonj-autofill-field",
  "Confirm that the data entered is correct":
    "goonj-labelless-field goonj-confirm-field",
  "PAN Card Number": "goonj-pan-field",
  "Consent Given": "goonj-consent-field",
  "Age 18+ Declared": "goonj-consent-field",
};

// The 80(G) row's profile label is empty, so it is matched on the option label
// printed beside its checkbox rather than on the row label.
const GOONJ_ROW_CLASS_BY_OPTION = { "80(G)": "goonj-labelless-field" };

function goonjRowLabel(row) {
  const label = row.querySelector(".label");
  if (!label) return "";
  // The required marker and CiviCRM's trailing whitespace are not part of the
  // label a contributor reads.
  return label.textContent.replace(/\*/g, "").trim();
}

// Safe to call more than once — adding a class that is already present is a
// no-op, and each consumer calls it so none of them depends on listener order.
function goonjStampProfileRows() {
  const rows = document.querySelectorAll(
    '#crm-main-content-wrapper form .crm-public-form-item .crm-section[class*="editrow_"]'
  );
  rows.forEach(function (row) {
    const byLabel = GOONJ_ROW_CLASS_BY_LABEL[goonjRowLabel(row)];
    if (byLabel) {
      row.classList.add.apply(row.classList, byLabel.split(" "));
      return;
    }
    const optionText = row.textContent;
    Object.keys(GOONJ_ROW_CLASS_BY_OPTION).forEach(function (needle) {
      if (optionText.indexOf(needle) !== -1) {
        row.classList.add(GOONJ_ROW_CLASS_BY_OPTION[needle]);
      }
    });
  });

  // Afform renders its own markup and has no `editrow_` rows, so its consent
  // field is matched on the field name. That name is the same on every
  // environment, unlike the generated ids. `$=` covers both the camp forms,
  // where it hangs off the camp record, and the forms that hold the submitter
  // as a person and bind it straight to them.
  document
    .querySelectorAll('af-field[name$=".Consent_Given"]')
    .forEach(function (field) {
      field.classList.add("goonj-consent-field");
    });
}

// The DPDP notice is a long one — what we collect, why, how long we keep it,
// and how to withdraw. All of that beside a checkbox is a wall of text, so the
// checkbox keeps a one-line label and the notice collapses behind a Details
// toggle, with the full privacy policy a click further on.
//
// Neither piece of wording lives in this file. The line is the custom field's
// option label and the notice is the profile field's Field Post Help, which
// CiviCRM prints as `.description` under the input — so Goonj can reword both
// in the CiviCRM admin without a release, which matters while the text is
// still going through legal.
// Goonj cannot take a minor's data on the public forms — that needs a guardian's
// consent and proof of the relationship, which is a separate process run over
// email. Date of birth stays optional, but where someone has filled it in and it
// says they are under 18, it contradicts the box they ticked to say otherwise.
// The form stops rather than recording a declaration that is not true.
const GOONJ_MIN_CONSENT_AGE = 18;

const GOONJ_UNDERAGE_MESSAGE =
  "The date of birth entered is under " +
  GOONJ_MIN_CONSENT_AGE +
  ", but the box above confirms you are " +
  GOONJ_MIN_CONSENT_AGE +
  " or older. Please correct the date, or if you are under " +
  GOONJ_MIN_CONSENT_AGE +
  " ask a parent or guardian to write to us at mail@goonj.org.";

// The visible control is a datepicker showing the local format; the input the
// field is actually bound to carries the ISO value, which is the one to read.
function goonjBirthDateValue(scope) {
  const field = (scope || document).querySelector('af-field[name="birth_date"]');
  if (!field) return null;

  const inputs = field.querySelectorAll("input");
  for (let i = 0; i < inputs.length; i++) {
    const value = (inputs[i].value || "").trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  }

  return null;
}

function goonjIsUnderAge(isoDate) {
  const born = new Date(isoDate + "T00:00:00");
  if (isNaN(born.getTime())) return false;

  const today = new Date();
  let age = today.getFullYear() - born.getFullYear();
  const monthsApart = today.getMonth() - born.getMonth();

  // Their birthday has not come round yet this year.
  if (monthsApart < 0 || (monthsApart === 0 && today.getDate() < born.getDate())) {
    age--;
  }

  return age < GOONJ_MIN_CONSENT_AGE;
}

function goonjShowUnderAgeError(scope) {
  const field = scope.querySelector('af-field[name="birth_date"]');
  if (!field) return;

  let error = field.querySelector(".goonj-underage-error");
  if (!error) {
    error = document.createElement("div");
    error.className = "goonj-underage-error";
    error.setAttribute("role", "alert");
    field.appendChild(error);
  }
  error.textContent = GOONJ_UNDERAGE_MESSAGE;
  field.scrollIntoView({ block: "center" });
}

function goonjClearUnderAgeError(scope) {
  const error = (scope || document).querySelector(".goonj-underage-error");
  if (error) error.remove();
}

// Runs before the button's own handler, so the submit can be stopped rather
// than undone after the fact.
document.addEventListener(
  "click",
  function (event) {
    const button = event.target.closest('button[ng-click*="submit"]');
    if (!button) return;

    const scope = button.closest("af-form");
    if (!scope) return;

    goonjClearUnderAgeError(scope);

    // Only a ticked box makes an age claim. An unticked one says nothing, so
    // there is nothing for the date to contradict.
    const consent = scope.querySelector(
      '.goonj-consent-field input[type="checkbox"]'
    );
    if (!consent || !consent.checked) return;

    const birthDate = goonjBirthDateValue(scope);
    if (!birthDate || !goonjIsUnderAge(birthDate)) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    goonjShowUnderAgeError(scope);
  },
  true
);

// Whether the check-user step recognised this person as having already
// consented. These forms carry their prefill in the hash rather than the query
// string, so both are read.
function goonjAlreadyConsented() {
  const fromHash = new URLSearchParams(
    window.location.hash.replace(/^#\??/, "")
  ).get("goonjConsented");
  const fromQuery = new URLSearchParams(window.location.search).get(
    "goonjConsented"
  );

  return fromHash === "1" || fromQuery === "1";
}

function goonjSetUpConsentDetails() {
  const config = window.goonjConsent || {};

  // Someone who has already agreed should not be asked again every time they
  // set up a camp. Their consent is on their contact record and the date of it
  // is preserved, so there is nothing to collect here — the block is hidden
  // rather than re-asked. Nothing is written either way: hiding the field only
  // removes a question, it never records an answer.
  const consentedAlready = goonjAlreadyConsented();

  document.querySelectorAll(".goonj-consent-field").forEach(function (row) {
    if (row.dataset.goonjConsentReady) return;

    if (consentedAlready) {
      row.dataset.goonjConsentReady = "1";
      row.classList.add("goonj-consent-hidden");

      const fieldName = (row.className.match(/editrow_([a-z0-9_]+)-section/i) ||
        [])[1];
      const help = fieldName
        ? document.querySelector(".helprow-" + fieldName + "-section")
        : null;
      if (help) help.classList.add("goonj-consent-hidden");
      return;
    }

    // The wording sits on the option label beside the tick, not on the row
    // label, so that is what the controls attach to. The last selector is
    // Afform, which renders a checkbox through CiviCRM's option-list markup
    // rather than the profile's.
    const optionLabel = row.querySelector(
      ".crm-option-label-pair label, .content label, ul.crm-checkbox-list li label"
    );
    if (!optionLabel) return;
    row.dataset.goonjConsentReady = "1";

    // On a profile, CiviCRM does not nest the help text inside the field row —
    // it prints it as a sibling `.helprow-<field>-section` block, before the row
    // for Field Pre Help and after it for Field Post Help. Match on the field
    // name so the notice is found wherever Goonj chose to put the wording.
    const fieldName = (row.className.match(/editrow_([a-z0-9_]+)-section/i) ||
      [])[1];
    let notice = fieldName
      ? document.querySelector(".helprow-" + fieldName + "-section")
      : null;
    let pendingNoticeUrl = null;

    // Afform has no equivalent: it prints help as `{{:: help_post }}`, which
    // Angular escapes, so the same markup would show on screen as tags. The
    // notice is published once as a WordPress page instead and pulled in here —
    // which also means Goonj rewords it in one place rather than on every form.
    // It is fetched on first open, so a form nobody expands costs no request.
    if (!notice && config.noticeRestUrl) {
      notice = document.createElement("div");
      notice.className = "crm-section goonj-consent-notice-shared";
      row.insertAdjacentElement("afterend", notice);
      pendingNoticeUrl = config.noticeRestUrl;
    }

    const actions = document.createElement("span");
    actions.className = "goonj-consent-actions";

    if (notice) {
      notice.classList.add("goonj-consent-notice");
      notice.hidden = true;

      // "Details" says nothing about what is behind it. Naming the content is
      // what makes someone open it, and it is the question they actually have
      // at this point in the form.
      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "goonj-consent-toggle";
      toggle.setAttribute("aria-expanded", "false");
      toggle.appendChild(document.createTextNode("What we collect and why"));

      // The chevron rotates rather than the wording changing, so the line does
      // not reflow under the cursor as it is clicked.
      const chevron = document.createElement("span");
      chevron.className = "goonj-consent-chevron";
      chevron.setAttribute("aria-hidden", "true");
      chevron.textContent = "▾";
      toggle.appendChild(chevron);

      // The wording is the same on every form, so it is fetched once and held.
      let noticeHtml = null;
      let noticeRequest = null;

      function loadNotice() {
        if (noticeRequest || !pendingNoticeUrl) return noticeRequest;
        noticeRequest = fetch(pendingNoticeUrl, { credentials: "same-origin" })
          .then(function (response) {
            if (!response.ok) throw new Error("HTTP " + response.status);
            return response.json();
          })
          .then(function (page) {
            noticeHtml = (page.content && page.content.rendered) || "";
            return noticeHtml;
          })
          .catch(function (error) {
            // Forgotten so that opening the panel tries again rather than
            // inheriting a failure from a prefetch nobody saw.
            noticeRequest = null;
            throw error;
          });
        return noticeRequest;
      }

      // Asking for the wording the moment the cursor or keyboard focus reaches
      // the toggle means the panel is filled by the time it opens, instead of
      // opening on a loading line. Someone who never goes near it still costs
      // no request. A prefetch that fails is swallowed here — nothing is on
      // screen to report it against — and reported on the click instead.
      function prefetchNotice() {
        const request = loadNotice();
        if (request) request.catch(function () {});
      }
      toggle.addEventListener("pointerenter", prefetchNotice);
      toggle.addEventListener("focus", prefetchNotice);

      toggle.addEventListener("click", function () {
        const open = notice.hidden;
        notice.hidden = !open;
        toggle.setAttribute("aria-expanded", String(open));
        toggle.classList.toggle("is-open", open);

        if (!open || !pendingNoticeUrl) return;

        if (noticeHtml !== null) {
          notice.innerHTML = noticeHtml;
          return;
        }

        notice.textContent = "Loading…";
        loadNotice()
          .then(function (html) {
            notice.innerHTML = html;
          })
          .catch(function () {
            // The policy link beside this still works, so point at it rather
            // than leaving an empty panel open.
            notice.textContent =
              "We could not load this here — please see the privacy policy.";
          });
      });
      actions.appendChild(toggle);
    }

    if (config.policyUrl) {
      if (actions.childNodes.length) {
        const separator = document.createElement("span");
        separator.className = "goonj-consent-separator";
        separator.setAttribute("aria-hidden", "true");
        separator.textContent = "·";
        actions.appendChild(separator);
      }

      const link = document.createElement("a");
      link.className = "goonj-policy-link";
      link.href = config.policyUrl;
      link.textContent = config.policyTitle || "Privacy Policy";
      actions.appendChild(link);
    }

    // Placed after the label, never inside it. That label is the consent
    // checkbox's own `<label for>`, so anything within it can activate the
    // checkbox — a contributor opening the notice would have ticked consent
    // just by asking to read it.
    if (actions.childNodes.length) {
      optionLabel.insertAdjacentElement("afterend", actions);
    }
  });
}

// Opening the policy in the same tab would lose a half-filled form, so it is
// shown over the page instead. Delegated, because the same link is printed by
// the check-user template as well as built here, and both should behave alike.
// The href stays a real link so that middle-click, and any failure to fetch,
// still work.
document.addEventListener("click", function (event) {
  const link = event.target.closest("a.goonj-policy-link");
  if (!link) return;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
    return;
  }

  event.preventDefault();
  goonjOpenPolicyOverlay();
});

function goonjOpenPolicyOverlay() {
  const config = window.goonjConsent || {};
  const previouslyFocused = document.activeElement;

  const overlay = document.createElement("div");
  overlay.className = "goonj-policy-overlay";
  overlay.setAttribute("role", "dialog");
  overlay.setAttribute("aria-modal", "true");
  overlay.setAttribute("aria-label", config.policyTitle || "Privacy Policy");

  const panel = document.createElement("div");
  panel.className = "goonj-policy-panel";

  const heading = document.createElement("h2");
  heading.className = "goonj-policy-heading";
  heading.textContent = config.policyTitle || "Privacy Policy";

  const close = document.createElement("button");
  close.type = "button";
  close.className = "goonj-policy-close";
  close.setAttribute("aria-label", "Close");
  close.innerHTML = "&times;";

  const body = document.createElement("div");
  body.className = "goonj-policy-body";
  body.textContent = "Loading…";

  panel.appendChild(close);
  panel.appendChild(heading);
  panel.appendChild(body);
  overlay.appendChild(panel);
  document.body.appendChild(overlay);
  document.body.classList.add("goonj-policy-open");

  function dismiss() {
    overlay.remove();
    document.body.classList.remove("goonj-policy-open");
    document.removeEventListener("keydown", onKeyDown);
    if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus();
  }

  function onKeyDown(event) {
    if (event.key === "Escape") dismiss();
  }

  close.addEventListener("click", dismiss);
  overlay.addEventListener("click", function (event) {
    if (event.target === overlay) dismiss();
  });
  document.addEventListener("keydown", onKeyDown);
  close.focus();

  if (!config.policyRestUrl) {
    // No page has been set as the privacy policy, so there is nothing to show
    // in place — send them to the link itself rather than an empty panel.
    body.textContent = "";
    const fallback = document.createElement("a");
    fallback.href = config.policyUrl || "#";
    fallback.textContent = "Open the privacy policy";
    body.appendChild(fallback);
    return;
  }

  fetch(config.policyRestUrl, { credentials: "same-origin" })
    .then(function (response) {
      if (!response.ok) throw new Error("HTTP " + response.status);
      return response.json();
    })
    .then(function (page) {
      body.innerHTML = (page.content && page.content.rendered) || "";
    })
    .catch(function () {
      body.textContent = "";
      const fallback = document.createElement("a");
      fallback.href = config.policyUrl || "#";
      fallback.target = "_blank";
      fallback.rel = "noopener";
      fallback.textContent = "We could not load the policy here. Open it in a new tab.";
      body.appendChild(fallback);
    });
}

document.addEventListener("DOMContentLoaded", function () {
  goonjStampProfileRows();
  goonjSetUpConsentDetails();

  // Afform builds its markup in Angular after this event, and rebuilds parts of
  // it as the form is used, so the consent row is not there to be found on the
  // first pass. Watch for it instead. Both functions skip anything they have
  // already handled, so repeated calls are cheap.
  if (!window.MutationObserver) return;
  let scheduled = false;
  new MutationObserver(function () {
    if (scheduled) return;
    scheduled = true;
    window.setTimeout(function () {
      scheduled = false;
      goonjStampProfileRows();
      goonjSetUpConsentDetails();
    }, 100);
  }).observe(document.body, { childList: true, subtree: true });
});

document.addEventListener("DOMContentLoaded", function () {
  goonjStampProfileRows();

  const checkbox = document.querySelector(
    ".crm-contribution-main-form-block .custom_pre_profile-group fieldset .crm-section .content .crm-multiple-checkbox-radio-options .crm-option-label-pair input.crm-form-checkbox"
  );
  const panFieldContainer = document.querySelector(
    ".crm-contribution-main-form-block .goonj-pan-field"
  );
  const panInput = document.querySelector(
    ".crm-contribution-main-form-block .goonj-pan-field .content input"
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
