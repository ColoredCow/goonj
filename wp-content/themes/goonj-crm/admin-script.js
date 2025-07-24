setTimeout(function () {
  const iframe = document.querySelector("iframe");
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
}, 1500);

function injectCityDropdown() {
  // Helper function to clean label text (remove trailing *, trim whitespace)
  function cleanLabelText(text) {
    return text.trim().replace(/\*$/, '').trim();
  }

  const stateFieldWrapper =
    document.querySelector('af-field[name="state_province_id"]') ||
    document.querySelector('af-field[name="Institution_Collection_Camp_Intent.State"]') ||
    document.querySelector('af-field[name="Institution_Dropping_Center_Intent.State"]') ||
    document.querySelector('af-field[name="Collection_Camp_Intent_Details.State"]') ||
    document.querySelector('af-field[name="Dropping_Centre.State"]') ||
    document.querySelector('af-field[name="Institution_Goonj_Activities.State"]') ||
    document.querySelector('af-field[name="Goonj_Activities.State"]') ||
    document.querySelector('af-field[name="Urban_Planned_Visit.State"]') ||
    document.querySelector('.editrow_state_province-Primary-section') || // For CiviCRM summary
    Array.from(document.querySelectorAll("label"))
      .find((label) => cleanLabelText(label.textContent) === "State")
      ?.closest("af-field") ||
    document.querySelector('.crm-summary-row[id*="state_province"]'); // Fallback

  const stateChosenSpan =
    stateFieldWrapper?.querySelector(".select2-chosen") ||
    stateFieldWrapper?.querySelector('span[id^="select2-chosen"]');

  const cityFieldWrapper =
    document.querySelector('af-field[name="city"]') ||
    document.querySelector('af-field[name="Institution_Dropping_Center_Intent.District_City"]') || // Added for your HTML
    document.querySelector('af-field[name="Goonj_Activities.City"]') ||
    document.querySelector('.editrow_city-Primary-section') ||
    document.getElementById("editrow-city-Primary") ||
    Array.from(document.querySelectorAll("label"))
      .find((label) => cleanLabelText(label.textContent) === "City")
      ?.closest("af-field") ||
    document.querySelector('.crm-summary-row[id*="city"]');

  const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');

  if (!stateFieldWrapper || !stateChosenSpan || !cityFieldWrapper || !cityInput) {
    return false;
  }

  if (cityFieldWrapper.querySelector('select[name="city-dropdown"]')) {
    console.warn("⚠️ City dropdown already injected.");
    return true;
  }

  console.log("✅ Injecting responsive city dropdown...");

  cityInput.style.display = "none";

  const citySelect = document.createElement("select");
  citySelect.name = "city-dropdown";
  citySelect.className = "form-control";
  citySelect.style.width = "100%";
  citySelect.style.maxWidth = "100%";
  citySelect.innerHTML = `
    <option value="">Select a city</option>
    <option value="Other">Other</option>
  `;
  cityInput.parentElement.appendChild(citySelect);

  function applySelect2() {
    if (window.jQuery && jQuery.fn.select2) {
      jQuery(citySelect).select2("destroy");
      jQuery(citySelect).select2({
        placeholder: "Select a city",
        allowClear: true,
        width: "resolve",
        dropdownAutoWidth: true,
        minimumResultsForSearch: 0,
      });
      jQuery(citySelect).next(".select2-container").css({
        width: "100%",
        "max-width": "100%",
      });
    } else {
      setTimeout(applySelect2, 500); // Retry quietly
    }
  }

  applySelect2();

  citySelect.addEventListener("change", () => {
    cityInput.value = citySelect.value;
    cityInput.dispatchEvent(new Event("input", { bubbles: true }));
  });

  let lastState = stateChosenSpan.textContent.trim();

  if (lastState !== "") {
    loadCities(lastState);
  }

  const stateObserver = new MutationObserver(() => {
    const currentState = stateChosenSpan.textContent.trim();
    if (currentState !== lastState && currentState !== "") {
      console.log("📦 State changed:", lastState, "→", currentState);
      lastState = currentState;
      loadCities(currentState);
    }
  });

  stateObserver.observe(stateChosenSpan, {
    characterData: true,
    childList: true,
    subtree: true,
  });

  function loadCities(stateName) {
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
        citySelect.innerHTML = `<option value="">Select a city</option>`;
        if (data.success && data.data?.cities?.length) {
          data.data.cities.forEach((city) => {
            const opt = document.createElement("option");
            opt.value = city.name;
            opt.textContent = city.name;
            citySelect.appendChild(opt);
          });
        }
        citySelect.appendChild(new Option("Other", "Other"));
        applySelect2();
        if (cityInput.value) {
          jQuery(citySelect).val(cityInput.value).trigger("change");
        } else {
          jQuery(citySelect).trigger("change");
        }
      })
      .catch((err) => {
        console.error("❌ Error loading cities:", err);
      });
  }

  console.log("👀 Watching for state changes...");
  return true;
}

injectCityDropdown();

const bodyObserver = new MutationObserver(() => {
  const cityInput = document.querySelector('#city-Primary') || document.querySelector('input[name*="city"]') || document.querySelector('input[id*="district-city"]');
  if (cityInput && cityInput.style.display !== "none" && !document.querySelector('select[name="city-dropdown"]')) {
    console.log("🔄 Re-injecting city dropdown after DOM change...");
    injectCityDropdown();
  }
});
bodyObserver.observe(document.body, { childList: true, subtree: true });

setTimeout(() => {
  if (!document.querySelector('select[name="city-dropdown"]')) {
    bodyObserver.disconnect();
    console.warn("⚠️ Timed out waiting for fields. Ensure elements exist.");
  }
}, 30000);
