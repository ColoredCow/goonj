setTimeout(function () {
  const iframe = document.querySelector("iframe");
  if (!iframe) return;
  
  const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

  // Create a style element
  const style = iframeDoc.createElement("style");
  style.textContent = `
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Regular.otf') format('opentype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Regular Italic.otf') format('opentype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Proxima Nova';
            src: url('/wp-content/themes/goonj-crm/fonts/Proxima Nova Bold Italic.otf') format('opentype');
            font-weight: bold;
            font-style: italic;
        }

        p, span, button, a {
            font-family: 'Proxima Nova', sans-serif !important;
        }

        /* City Dropdown Styles */
        .citydd { position: relative; font-family: inherit; }

        .citydd-toggle {
          -webkit-appearance: none; appearance: none; 
          width: 100%;
          min-height: 40px;
          border: 1px solid #c9c9c9;
          border-radius: 10px;
          padding: 10px 36px 10px 12px;
          background: #f7f7f7;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: space-between;
          line-height: 1.2;
          outline: none;
          -webkit-tap-highlight-color: transparent;
        }

        .crm-container .citydd-toggle {
          background: #fff !important;
          border: 1px solid #c9c9c9;
        }

        .citydd-toggle::-moz-focus-inner { border: 0; }

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
        .citydd .citydd-label { color: #111; }
    `;
  iframeDoc.head.appendChild(style);

  const fontFamily = "Proxima Nova";

  const paragraphs = iframeDoc.querySelectorAll("p");
  paragraphs.forEach(function (p) {
    p.style.setProperty("font-family", fontFamily, "important");
  });

  const spans = iframeDoc.querySelectorAll("span");
  spans.forEach(function (span) {
    span.style.setProperty("font-family", fontFamily, "important");
  });

  const buttons = iframeDoc.querySelectorAll("button");
  buttons.forEach(function (button) {
    button.style.setProperty("font-family", fontFamily, "important");
  });

  const anchors = iframeDoc.querySelectorAll("a");
  anchors.forEach(function (anchor) {
    anchor.style.setProperty("font-family", fontFamily, "important");
  });

  // Also run city dropdown in iframe context
  setTimeout(() => {
    injectCityDropdownInContext(iframeDoc, iframeDoc.body);
  }, 500);
}, 1500);

// Helper function to run city dropdown in any context (iframe or main document)
function injectCityDropdownInContext(doc, rootElement) {
  function cleanLabelText(text) {
    return text.trim().replace(/\*$/, '').trim();
  }

  function findStateFieldWrapper() {
    return (
      doc.querySelector('af-field[name="state_province_id"]') ||
      doc.querySelector('af-field[name="Institution_Collection_Camp_Intent.State"]') ||
      doc.querySelector('af-field[name="Institution_Dropping_Center_Intent.State"]') ||
      doc.querySelector('af-field[name="Collection_Camp_Intent_Details.State"]') ||
      doc.querySelector('af-field[name="Dropping_Centre.State"]') ||
      doc.querySelector('af-field[name="Institution_Goonj_Activities.State"]') ||
      doc.querySelector('af-field[name="Goonj_Activities.State"]') ||
      doc.querySelector('af-field[name="Urban_Planned_Visit.State"]') ||
      doc.querySelector('.editrow_state_province-Primary-section') ||
      Array.from(doc.querySelectorAll("label"))
        .find((label) => cleanLabelText(label.textContent) === "State")
        ?.closest("af-field") ||
      doc.querySelector('.crm-summary-row[id*="state_province"]')
    );
  }

  function findCityFieldWrapper() {
    return (
      doc.querySelector('af-field[name="city"]') ||
      doc.querySelector('af-field[name="Institution_Dropping_Center_Intent.District_City"]') ||
      doc.querySelector('af-field[name="Goonj_Activities.City"]') ||
      doc.querySelector('.editrow_city-Primary-section') ||
      doc.getElementById("editrow-city-Primary") ||
      Array.from(doc.querySelectorAll("label"))
        .find((label) => cleanLabelText(label.textContent) === "City")
        ?.closest("af-field") ||
      doc.querySelector('.crm-summary-row[id*="city"]')
    );
  }

  function waitForElementsAndInject() {
    const interval = setInterval(() => {
      const stateFieldWrapper = findStateFieldWrapper();
      const stateChosenSpan =
        stateFieldWrapper?.querySelector(".select2-chosen") ||
        stateFieldWrapper?.querySelector('span[id^="select2-chosen"]');

      const cityFieldWrapper = findCityFieldWrapper();
      const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');

      console.log({ stateFieldWrapper, stateChosenSpan, cityFieldWrapper, cityInput });

      if (stateFieldWrapper && stateChosenSpan && cityFieldWrapper && cityInput) {
        clearInterval(interval);
        setupDropdown({
          stateFieldWrapper,
          stateChosenSpan,
          cityFieldWrapper,
          cityInput,
        });
      }
    }, 300);

    setTimeout(() => clearInterval(interval), 30000);
  }

  function setupDropdown({ stateFieldWrapper, stateChosenSpan, cityFieldWrapper, cityInput }) {
    if (cityFieldWrapper.querySelector('.citydd')) return;

    cityInput.style.display = "none";

    const dd = buildCityDropdown(cityInput);
    cityInput.parentElement.appendChild(dd.wrapper);

    const ddRefs = getCityDropdownRefs(cityFieldWrapper);

    let lastState = stateChosenSpan.textContent.trim();
    if (lastState !== "") {
      loadCities(lastState, ddRefs, cityInput.value.trim());
    }

    const stateObserver = new MutationObserver(() => {
      const currentState = stateChosenSpan.textContent.trim();
      if (currentState !== lastState && currentState !== "") {
        lastState = currentState;
        // Pass the current city value to preserve it if it's not in the new list
        const currentCityValue = cityInput.value.trim();
        loadCities(currentState, ddRefs, currentCityValue);
      }
    });

    stateObserver.observe(stateChosenSpan, {
      characterData: true,
      childList: true,
      subtree: true,
    });
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

  function loadCities(stateName, ddRefs, preselectValue = null) {
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
      .catch((err) => {
        console.error("City dropdown error:", err);
        populateCityDropdown(ddRefs, ["Other"], preselectValue);
      });
  }

  function populateCityDropdown(dd, cities, preselectValue) {
    dd.list.innerHTML = "";

    console.log('populateCityDropdown called with:', { cities, preselectValue });

    // Check if preselectValue exists but is not in the cities list
    const hasExistingIncorrectCity = preselectValue && 
      !cities.some(c => c.toLowerCase() === preselectValue.toLowerCase());

    console.log('hasExistingIncorrectCity:', hasExistingIncorrectCity);

    // If there's an incorrect city, add it to the beginning of the list
    if (hasExistingIncorrectCity) {
      const incorrectLi = doc.createElement("li");
      incorrectLi.className = "citydd-item";
      incorrectLi.setAttribute("role", "option");
      incorrectLi.setAttribute("tabindex", "-1");
      incorrectLi.textContent = `${preselectValue} (incorrect city name)`;
      incorrectLi.dataset.value = preselectValue;
      incorrectLi.dataset.unmatched = "1";
      incorrectLi.style.color = "#d9534f"; // Red color for incorrect city
      dd.list.appendChild(incorrectLi);
      console.log('Added incorrect city to dropdown:', preselectValue);
    }

    const items = cities.map((name) => {
      const li = doc.createElement("li");
      li.className = "citydd-item";
      li.setAttribute("role", "option");
      li.setAttribute("tabindex", "-1");
      li.textContent = name;
      li.dataset.value = name;
      dd.list.appendChild(li);
      return li;
    });

    // Include the incorrect city item in the items array if it exists
    const allItems = hasExistingIncorrectCity 
      ? [dd.list.firstChild, ...items]
      : items;

    const filter = (q) => {
      const query = q.trim().toLowerCase();
      allItems.forEach((li) => {
        const match = li.textContent.toLowerCase().includes(query);
        li.style.display = match ? "" : "none";
        li.removeAttribute("aria-current");
      });
      const firstVisible = allItems.find((li) => li.style.display !== "none");
      dd.empty.style.display = firstVisible ? "none" : "block";
      if (firstVisible) firstVisible.setAttribute("aria-current", "true");
    };

    dd.search.value = "";
    dd.empty.style.display = "none";
    filter("");

    function selectValue(value, fromKeyboard = false) {
      dd.hiddenInput.value = value;
      dd.hiddenInput.dispatchEvent(new Event("input", { bubbles: true }));
      
      // Get the text content for display (might include "incorrect city name")
      const selectedLi = allItems.find(li => li.dataset.value === value);
      dd.labelEl.textContent = selectedLi ? selectedLi.textContent : value;

      Array.from(dd.list.children).forEach((li) => {
        li.setAttribute("aria-selected", li.dataset.value === value ? "true" : "false");
      });

      dd.wrapper.classList.remove("open");
      dd.toggle.setAttribute("aria-expanded", "false");
      if (!fromKeyboard) dd.toggle.focus();
    }

    allItems.forEach((li) => {
      li.addEventListener("click", () => selectValue(li.dataset.value));
    });

    dd.search.addEventListener("keydown", (e) => {
      const visibleItems = allItems.filter((li) => li.style.display !== "none");
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
      const exists = cities.some((c) => c.toLowerCase() === preselectValue.toLowerCase());
      selectValue(preselectValue);
    } else {
      dd.labelEl.textContent = "Select a city";
    }
  }

  waitForElementsAndInject();

  // Also observe for new modals/popups being added dynamically
  const modalObserver = new MutationObserver(() => {
    // Check if there are any city fields without dropdowns
    const cityFields = doc.querySelectorAll('af-field[name="city"], af-field[name="Institution_Dropping_Center_Intent.District_City"], af-field[name="Goonj_Activities.City"]');
    cityFields.forEach(field => {
      if (!field.querySelector('.citydd')) {
        waitForElementsAndInject();
      }
    });
  });
  modalObserver.observe(rootElement, { childList: true, subtree: true });
}

// Also run in parent context for non-iframe forms
injectCityDropdownInContext(document, document.body);

// Watch for iframes and inject into them
const iframeWatcher = setInterval(() => {
  const iframe = document.querySelector("iframe");
  if (iframe) {
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    if (iframeDoc && iframeDoc.body) {
      // Only inject if not already done
      if (!iframeDoc.body.hasAttribute('data-city-dropdown-injected')) {
        iframeDoc.body.setAttribute('data-city-dropdown-injected', 'true');
        setTimeout(() => {
          injectCityDropdownInContext(iframeDoc, iframeDoc.body);
        }, 500);
      }
    }
  }
}, 1000);

// Stop watching after 30 seconds
setTimeout(() => clearInterval(iframeWatcher), 30000);