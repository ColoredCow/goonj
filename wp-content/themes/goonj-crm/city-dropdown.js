// City Dropdown - Custom searchable dropdown for city fields
// This file should NOT be loaded on contribution pages or team-5000 page

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

document.addEventListener("DOMContentLoaded", function () {
  function findAllStateCityPairs() {
    const pairs = [];
    
    const stateSelectors = [
      'af-field[name="state_province_id"]',
      'af-field[name="Institution_Collection_Camp_Intent.State"]',
      'af-field[name="Institution_Dropping_Center_Intent.State"]',
      'af-field[name="Collection_Camp_Intent_Details.State"]',
      'af-field[name="Dropping_Centre.State"]',
      'af-field[name="Institution_Goonj_Activities.State"]',
      'af-field[name="Goonj_Activities.State"]',
      'af-field[name="Urban_Planned_Visit.State"]',
    ];
    
    const citySelectors = [
      'af-field[name="city"]',
      'af-field[name="Collection_Camp_Intent_Details.City"]',
      'af-field[name="Goonj_Activities.City"]',
      'af-field[name="Institution_Dropping_Center_Intent.District_City"]',
    ];

    // Collect all state fields
    const stateFields = [];
    stateSelectors.forEach(selector => {
      const elements = document.querySelectorAll(selector);
      elements.forEach(el => {
        if (!stateFields.includes(el)) {
          stateFields.push(el);
        }
      });
    });

    // Also find by label
    const stateLabelFields = Array.from(document.querySelectorAll("label"))
      .filter((label) => label.textContent.trim() === "State" || label.textContent.trim() === "State *")
      .map(label => label.closest("af-field"))
      .filter(field => field && !stateFields.includes(field));
    
    stateFields.push(...stateLabelFields);

    const cityFields = [];
    citySelectors.forEach(selector => {
      const elements = document.querySelectorAll(selector);
      elements.forEach(el => {
        if (!cityFields.includes(el)) {
          cityFields.push(el);
        }
      });
    });

    const cityLabelFields = Array.from(document.querySelectorAll("label"))
      .filter((label) => {
        const text = label.textContent.trim();
        return text.startsWith("City") || text.startsWith("District");
      })
      .map(label => label.closest("af-field"))
      .filter(field => field && !cityFields.includes(field));
    
    cityFields.push(...cityLabelFields);

    // Also check for standard editrow fields
    const stateEditRow = document.getElementById("editrow-state_province-Primary");
    if (stateEditRow && !stateFields.includes(stateEditRow)) {
      stateFields.push(stateEditRow);
    }
    const cityEditRow = document.getElementById("editrow-city-Primary");
    if (cityEditRow && !cityFields.includes(cityEditRow)) {
      cityFields.push(cityEditRow);
    }

    // Match state and city fields by proximity (same parent or nearby in DOM)
    stateFields.forEach(stateField => {
      const chosenSpan = stateField?.querySelector(".select2-chosen") || 
                        stateField?.querySelector('span[id^="select2-chosen"]');
      
      if (!chosenSpan) return;

      let closestCity = null;
      let minDistance = Infinity;

      cityFields.forEach(cityField => {
        const cityInput = cityField?.querySelector('input[type="text"]');
        if (!cityInput || cityField.querySelector(".citydd")) return; // Skip if already initialized

        const distance = Math.abs(
          getDepth(stateField) - getDepth(cityField)
        );

        const sameParent = stateField.closest('fieldset') === cityField.closest('fieldset') ||
                          stateField.closest('af-fieldset') === cityField.closest('af-fieldset');

        if (sameParent && distance < minDistance) {
          minDistance = distance;
          closestCity = cityField;
        } else if (!closestCity && distance < minDistance) {
          minDistance = distance;
          closestCity = cityField;
        }
      });

      if (closestCity) {
        const cityInput = closestCity.querySelector('input[type="text"]');
        pairs.push({
          stateFieldWrapper: stateField,
          stateChosenSpan: chosenSpan,
          cityFieldWrapper: closestCity,
          cityInput: cityInput
        });
      }
    });

    return pairs;
  }

  function getDepth(element) {
    let depth = 0;
    let current = element;
    while (current.parentElement) {
      depth++;
      current = current.parentElement;
    }
    return depth;
  }

  function waitForFieldsAndInit() {
    const pairs = findAllStateCityPairs();

    if (pairs.length === 0) {
      requestAnimationFrame(waitForFieldsAndInit);
      return;
    }

    // Initialize each pair
    pairs.forEach(pair => {
      initializeCityDropdown(pair);
    });
  }

  function initializeCityDropdown({ stateFieldWrapper, stateChosenSpan, cityFieldWrapper, cityInput }) {
    if (!stateFieldWrapper || !stateChosenSpan || !cityFieldWrapper || !cityInput) {
      return;
    }

    // Keep the original input hidden: we sync its value from the custom dropdown.
    cityInput.style.display = "none";

    if (!cityFieldWrapper.querySelector(".citydd")) {
      const dd = buildCityDropdown(cityInput);
      cityInput.parentElement.appendChild(dd.wrapper);
    }

    const ddRefs = getCityDropdownRefs(cityFieldWrapper);
    let lastState = stateChosenSpan.textContent.trim();

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
      const currentState = stateChosenSpan.textContent.trim();
      if (currentState !== lastState && currentState !== "") {
        lastState = currentState;
        fetchAndPopulateCities(currentState);
      }
    });

    observer.observe(stateChosenSpan, { characterData: true, childList: true, subtree: true });

    // Initial fetch if state is already selected
    const initialState = stateChosenSpan.textContent.trim();
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
