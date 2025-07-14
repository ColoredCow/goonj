document.addEventListener('DOMContentLoaded', function () {
  // --- FONT INJECTION FOR IFRAME ---
  setTimeout(function () {
    const iframe = document.querySelector('iframe');
    if (!iframe) {
      console.warn('⚠️ Iframe not found.');
      return;
    }

    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    if (!iframeDoc) {
      console.warn('⚠️ Unable to access iframe document.');
      return;
    }

    const style = iframeDoc.createElement('style');
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

    const fontFamily = 'Proxima Nova';
    ['p', 'span', 'button', 'a'].forEach(selector => {
      iframeDoc.querySelectorAll(selector).forEach(el => {
        el.style.setProperty('font-family', fontFamily, 'important');
      });
    });
  }, 1500);
const intervalId = setInterval(() => {
  const stateFieldWrapper = document.querySelector('af-field[name="state_province_id"]');
  const stateChosenSpan =
    stateFieldWrapper?.querySelector(".select2-chosen") ||
    stateFieldWrapper?.querySelector('span[id^="select2-chosen"]');

  const cityFieldWrapper = document.querySelector('af-field[name="city"]');
  const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');

  if (!stateFieldWrapper || !stateChosenSpan || !cityFieldWrapper || !cityInput) {
    console.log("⏳ Waiting for required fields...");
    return; // Keep waiting
  }

  clearInterval(intervalId);
  console.log("✅ Injecting responsive city dropdown...");

  // Avoid duplicate injection
  if (cityFieldWrapper.querySelector('select[name="city-dropdown"]')) {
    console.warn("⚠️ City dropdown already injected.");
    return;
  }

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
    }
  }

  applySelect2();

  citySelect.addEventListener("change", () => {
    cityInput.value = citySelect.value;
    cityInput.dispatchEvent(new Event("input", { bubbles: true }));
  });

  let lastState = stateChosenSpan.textContent.trim();

  const observer = new MutationObserver(() => {
    const currentState = stateChosenSpan.textContent.trim();
    if (currentState !== lastState && currentState !== "") {
      console.log("📦 State changed:", lastState, "→", currentState);
      lastState = currentState;

      const baseUrl = `${window.location.origin}/wp-admin/admin-ajax.php`;

      fetch(baseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          action: "get_cities_by_state",
          state_name: currentState,
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
          jQuery(citySelect).trigger("change");
        })
        .catch((err) => {
          console.error("❌ Error loading cities:", err);
        });
    }
  });

  observer.observe(stateChosenSpan, {
    characterData: true,
    childList: true,
    subtree: true,
  });

  console.log("👀 Watching for state changes...");
}, 500)
})