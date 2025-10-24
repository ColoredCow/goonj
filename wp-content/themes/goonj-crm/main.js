(function injectCityDropdownCSS() {
  const css = `
    .citydd { position: relative; font-family: inherit; }

    .citydd-toggle {
      -webkit-appearance: none; appearance: none;   /* prevent UA button styles (blue fills) */
      width: 100%;
      min-height: 40px;
      border: 1px solid #c9c9c9;
      border-radius: 10px;
      padding: 10px 36px 10px 12px;
      background: #f7f7f7;                          /* locked neutral background */
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      line-height: 1.2;
      outline: none;                                /* we add our own focus */
      -webkit-tap-highlight-color: transparent;     /* remove mobile blue flash */
    }

    .crm-container .citydd-toggle {
    background: #fff !important;
    border: 1px solid #c9c9c9;
    }

    .citydd-toggle::-moz-focus-inner { border: 0; }

    /* Custom accessible focus ring WITHOUT blue fill */
    .citydd-toggle:focus,
    .citydd-toggle:focus-visible,
    .citydd-toggle:active {
      box-shadow: none !important;
      outline: 2px solid #808080;
      outline-offset: 2px;
    }

    .citydd-toggle .citydd-caret { position:absolute; right:12px; pointer-events:none; }

    .citydd-panel {
      position: absolute; z-index: 1000; top: calc(100% + 4px); left: 0; right: 0;
      background: #fff; border: 1px solid #ccc; border-radius: 10px;
      box-shadow: 0 8px 24px rgba(0,0,0,.12); display: none;
    }
    .citydd.open .citydd-panel { display: block; }

    .citydd-search {
      width: 100% !important; border: 0; padding: 12px;
      border-top-left-radius: 10px; border-top-right-radius: 10px;
      font-size: 14px;
    }
    .citydd-list { max-height: 260px; overflow: auto; margin: 0; padding: 6px 0; list-style: none; padding-left: 0 !important; }
    .citydd-item { padding: 10px 12px; cursor: pointer; }
    .citydd-item[aria-selected="true"] { font-weight: 600; }
    .citydd-item:hover, .citydd-item[aria-current="true"] { background: #f5f5f5; }
    .citydd-empty { padding: 12px; color: #777; }
    .citydd .citydd-label { color: #111; }          /* selected text color like native input */
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);
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
		  <p class="fw-600 font-sans fz-20 mb-6">your induction is pending.</p>
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
  let errorElement = panFieldContainer.querySelector(".error-message");

  // Create error message element if it doesn't exist
  if (!errorElement) {
    errorElement = document.createElement("div");
    errorElement.className = "error-message";
    errorElement.style.color = "red";
    errorElement.style.display = "none";
    panFieldContainer.appendChild(errorElement);
  }

  // Function to show/hide error message
  function showError(message) {
    errorElement.textContent = message;
    errorElement.style.display = message ? "block" : "none";
  }

  // Function to toggle PAN field visibility
  function togglePanField() {
    const isChecked = checkbox.checked;
    if (isChecked) {
      panFieldContainer.style.display = "block";
      if (panInput) {
        panInput.required = true;
      }
    } else {
      panFieldContainer.style.display = "none";
      if (panInput) {
        panInput.value = "";
        panInput.required = false;
        showError("");
      }
    }
  }

  togglePanField();

  // Add event listener for checkbox changes
  checkbox.addEventListener("change", function () {
    togglePanField();
  });
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
  function waitForFieldsAndInit() {
    const stateFieldWrapper =
      document.querySelector('af-field[name="state_province_id"]') ||
      document.getElementById("editrow-state_province-Primary") ||
      document.querySelector('af-field[name="Institution_Collection_Camp_Intent.State"]') ||
      document.querySelector('af-field[name="Institution_Dropping_Center_Intent.State"]') ||
      document.querySelector('af-field[name="Collection_Camp_Intent_Details.State"]') ||
      document.querySelector('af-field[name="Dropping_Centre.State"]') ||
      document.querySelector('af-field[name="Institution_Goonj_Activities.State"]') ||
      document.querySelector('af-field[name="Goonj_Activities.State"]') ||
      document.querySelector('af-field[name="Urban_Planned_Visit.State"]') ||
      Array.from(document.querySelectorAll("label"))
        .find((label) => label.textContent.trim() === "State")
        ?.closest("af-field");

    const chosenSpan =
      stateFieldWrapper?.querySelector(".select2-chosen") ||
      stateFieldWrapper?.querySelector('span[id^="select2-chosen"]');

    const cityFieldWrapper =
      document.querySelector('af-field[name="city"]') ||
      document.querySelector('af-field[name="Collection_Camp_Intent_Details.City"]') ||
      document.querySelector('af-field[name="Goonj_Activities.City"]') ||
      document.getElementById("editrow-city-Primary") ||
      Array.from(document.querySelectorAll("label"))
        .find((label) => label.textContent.trim().startsWith("City"))
        ?.closest("af-field");

    const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');

    if (!stateFieldWrapper || !chosenSpan || !cityFieldWrapper || !cityInput) {
      requestAnimationFrame(waitForFieldsAndInit);
      return;
    }

    // Keep the original input hidden: we sync its value from the custom dropdown.
    cityInput.style.display = "none";

    if (!cityFieldWrapper.querySelector(".citydd")) {
      const dd = buildCityDropdown(cityInput);
      cityInput.parentElement.appendChild(dd.wrapper);
    }

    const ddRefs = getCityDropdownRefs(cityFieldWrapper);
    let lastState = chosenSpan.textContent.trim();

    function fetchAndPopulateCities(stateName, preselectValue = null) {
      if (!stateName) return;

      const baseUrl = `${window.location.origin}/wp-admin/admin-ajax.php`;
      fetch(baseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "get_cities_by_state",
          state_name: stateName,
        }),
      })
        .then((res) => res.json())
        .then((data) => {
          const cityList =
            data && data.success && data.data?.cities?.length
              ? data.data.cities.map((c) => c.name)
              : [];
          if (!cityList.includes("Other")) cityList.push("Other");
          populateCityDropdown(ddRefs, cityList, preselectValue);
        })
        .catch(() => {
          populateCityDropdown(ddRefs, ["Other"], preselectValue);
        });
    }

    const observer = new MutationObserver(() => {
      const currentState = chosenSpan.textContent.trim();
      if (currentState !== lastState && currentState !== "") {
        lastState = currentState;
        fetchAndPopulateCities(currentState);
      }
    });

    observer.observe(chosenSpan, { characterData: true, childList: true, subtree: true });

    // Initial fetch if state is already selected
    const initialState = chosenSpan.textContent.trim();
    const initialCity = cityInput.value.trim();
    if (initialState !== "") {
      lastState = initialState;
      fetchAndPopulateCities(initialState, initialCity);
    }
  }

  function buildCityDropdown(hiddenInput) {
    const wrapper = document.createElement("div");
    wrapper.className = "citydd";
    wrapper.setAttribute("data-citydd", "1");

    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "citydd-toggle";
    toggle.setAttribute("aria-haspopup", "listbox");
    toggle.setAttribute("aria-expanded", "false");
    toggle.innerHTML = `<span class="citydd-label">Select a city</span><span class="citydd-caret">▾</span>`;

    const panel = document.createElement("div");
    panel.className = "citydd-panel";

    const search = document.createElement("input");
    search.type = "text";
    search.className = "citydd-search";
    search.placeholder = "Search city...";

    const list = document.createElement("ul");
    list.className = "citydd-list";
    list.setAttribute("role", "listbox");

    const empty = document.createElement("div");
    empty.className = "citydd-empty";
    empty.textContent = "No matches";

    panel.appendChild(search);
    panel.appendChild(list);
    panel.appendChild(empty);
    wrapper.appendChild(toggle);
    wrapper.appendChild(panel);

    function open() {
      wrapper.classList.add("open");
      toggle.setAttribute("aria-expanded", "true");
      setTimeout(() => search.focus(), 0);
    }
    function close() {
      wrapper.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.focus();
    }

    toggle.addEventListener("click", (e) => {
      e.preventDefault();
      if (wrapper.classList.contains("open")) close(); else open();
    });

    toggle.addEventListener("keydown", (e) => {
      if (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        open();
      }
    });

    document.addEventListener("click", (e) => {
      if (!wrapper.contains(e.target) && wrapper.classList.contains("open")) close();
    });

    return { wrapper, toggle, panel, search, list, empty, hiddenInput };
  }

  function getCityDropdownRefs(container) {
    const wrapper = container.querySelector(".citydd");
    return {
      wrapper,
      toggle: wrapper.querySelector(".citydd-toggle"),
      panel: wrapper.querySelector(".citydd-panel"),
      search: wrapper.querySelector(".citydd-search"),
      list: wrapper.querySelector(".citydd-list"),
      empty: wrapper.querySelector(".citydd-empty"),
      labelEl: wrapper.querySelector(".citydd-label"),
      hiddenInput: container.querySelector('input[type="text"]'),
    };
  }

  /* ---------- Populate & wire up behavior ---------- */
  function populateCityDropdown(dd, cities, preselectValue) {
    dd.list.innerHTML = "";

    const items = cities.map((name) => {
      const li = document.createElement("li");
      li.className = "citydd-item";
      li.setAttribute("role", "option");
      li.setAttribute("tabindex", "-1");
      li.textContent = name;
      li.dataset.value = name;
      dd.list.appendChild(li);
      return li;
    });

    const filter = (q) => {
      const query = q.trim().toLowerCase();
      items.forEach((li) => {
        const match = li.textContent.toLowerCase().includes(query);
        li.style.display = match ? "" : "none";
        li.removeAttribute("aria-current");
      });
      const firstVisible = items.find((li) => li.style.display !== "none");
      dd.empty.style.display = firstVisible ? "none" : "block";
      if (firstVisible) firstVisible.setAttribute("aria-current", "true");
    };

    dd.search.value = "";
    dd.empty.style.display = "none";
    filter("");

    function selectValue(value, fromKeyboard = false) {
      dd.hiddenInput.value = value;
      dd.hiddenInput.dispatchEvent(new Event("input", { bubbles: true }));
      dd.labelEl.textContent = value;

      Array.from(dd.list.children).forEach((li) => {
        li.setAttribute("aria-selected", li.dataset.value === value ? "true" : "false");
      });

      dd.wrapper.classList.remove("open");
      dd.toggle.setAttribute("aria-expanded", "false");
      if (!fromKeyboard) dd.toggle.focus();
    }

    items.forEach((li) => {
      li.addEventListener("click", () => selectValue(li.dataset.value));
    });

    dd.search.addEventListener("keydown", (e) => {
      const visibleItems = items.filter((li) => li.style.display !== "none");
      const currentIdx = visibleItems.findIndex((li) => li.getAttribute("aria-current") === "true");

      if (e.key === "ArrowDown") {
        e.preventDefault();
        const next = visibleItems[Math.min(currentIdx + 1, visibleItems.length - 1)];
        visibleItems.forEach((li) => li.removeAttribute("aria-current"));
        if (next) next.setAttribute("aria-current", "true");
        next?.scrollIntoView({ block: "nearest" });
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        const prev = visibleItems[Math.max(currentIdx - 1, 0)];
        visibleItems.forEach((li) => li.removeAttribute("aria-current"));
        if (prev) prev.setAttribute("aria-current", "true");
        prev?.scrollIntoView({ block: "nearest" });
      } else if (e.key === "Enter") {
        e.preventDefault();
        const current = visibleItems[currentIdx >= 0 ? currentIdx : 0];
        if (current) selectValue(current.dataset.value, true);
      } else if (e.key === "Escape") {
        e.preventDefault();
        dd.wrapper.classList.remove("open");
        dd.toggle.setAttribute("aria-expanded", "false");
        dd.toggle.focus();
      }
    });

    dd.search.addEventListener("input", (e) => filter(e.target.value));

    if (preselectValue) {
      const exists = items.some((li) => li.dataset.value === preselectValue);
      selectValue(exists ? preselectValue : "Other");
    } else {
      dd.labelEl.textContent = "Select a city";
    }
  }

  requestAnimationFrame(waitForFieldsAndInit);
});