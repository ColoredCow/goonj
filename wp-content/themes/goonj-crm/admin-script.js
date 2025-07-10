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
console.log('hii')
const intervalId = setInterval(() => {
    const cityInput = document.getElementById('city-Primary');
    if (!cityInput) {
      console.log('⏳ Waiting for #city-Primary to load...');
      return;
    }

    clearInterval(intervalId);
    console.log('✅ city-Primary input found. Adding dropdown.');

    // Hide the text input
    cityInput.style.display = 'none';

    // Create the dropdown
    const citySelect = document.createElement('select');
    citySelect.className = 'form-control';
    citySelect.name = 'city-dropdown';
    citySelect.innerHTML = `<option value="">Select a city</option>`;

    // Append dropdown after the input
    cityInput.parentElement.appendChild(citySelect);

    // Load cities (static or AJAX)
    fetch('https://goonj.test/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: 'get_cities_by_state',
        state_name: 'Uttarakhand', // or make dynamic
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.data?.cities?.length) {
          data.data.cities.forEach((city) => {
            const opt = document.createElement('option');
            opt.value = city.name;
            opt.textContent = city.name;
            citySelect.appendChild(opt);
          });
          citySelect.appendChild(new Option('Other', 'Other'));
        }
        // Preselect if input had value
        if (cityInput.value) {
          citySelect.value = cityInput.value;
        }
      });

    // Update hidden input when dropdown changes
    citySelect.addEventListener('change', () => {
      cityInput.value = citySelect.value;
      cityInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }, 500);
  // --- CITY DROPDOWN INIT ---
//   const intervalId = setInterval(() => {
//     const stateFieldWrapper = document.querySelector('af-field[name="state_province_id"]');
//     const cityFieldWrapper = document.querySelector('af-field[name="City"]') || document.getElementById('editrow-city-Primary');
//     const cityInput = cityFieldWrapper?.querySelector('input[type="text"]');
//     const chosenSpan = document.getElementById('select2-chosen-1');

//     // if (!stateFieldWrapper || !cityFieldWrapper || !cityInput || !chosenSpan) {
//     //   console.log("⏳ Waiting for form fields to load...");
//     //   return;
//     // }

//     clearInterval(intervalId);
//     console.log("✅ Form fields found. Initializing city dropdown.");

//     cityInput.style.display = 'none';

//     const citySelect = document.createElement('select');
//     citySelect.className = 'form-control';
//     citySelect.name = 'city-dropdown';
//     citySelect.innerHTML = `
//       <option value="">Select a city</option>
//       <option value="Other">Other</option>
//     `;
//     cityInput.parentElement.appendChild(citySelect);

//     function applySelect2() {
//       if (window.jQuery && jQuery.fn.select2) {
//         jQuery(citySelect).select2('destroy');
//         jQuery(citySelect).select2({
//           placeholder: "Select a city",
//           allowClear: true,
//           width: 'resolve',
//           minimumResultsForSearch: 0
//         });
//         jQuery(citySelect).next('.select2-container').css({
//           width: '100%',
//           'max-width': '340px'
//         });
//       }
//     }

//     applySelect2();

//     citySelect.addEventListener('change', () => {
//       cityInput.value = citySelect.value;
//       cityInput.dispatchEvent(new Event('input', { bubbles: true }));
//     });

//     let lastState = chosenSpan.textContent.trim();
//     const observer = new MutationObserver(() => {
//       const currentState = chosenSpan.textContent.trim();
//       if (currentState !== lastState) {
//         console.log("📦 State changed:", lastState, "→", currentState);
//         lastState = currentState;

//         fetch('https://goonj.test/wp-admin/admin-ajax.php', {
//           method: 'POST',
//           headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
//           body: new URLSearchParams({
//             action: 'get_cities_by_state',
//             state_name: currentState,
//           }),
//         })
//           .then((res) => res.json())
//           .then((data) => {
//             citySelect.innerHTML = `<option value="">Select a city</option>`;
//             if (data.success && data.data?.cities?.length) {
//               data.data.cities.forEach((city) => {
//                 const opt = document.createElement('option');
//                 opt.value = city.name;
//                 opt.textContent = city.name;
//                 citySelect.appendChild(opt);
//               });
//               citySelect.appendChild(new Option("Other", "Other"));
//               applySelect2();
//               jQuery(citySelect).trigger('change');
//             } else {
//               console.warn("⚠️ No cities found for:", currentState);
//               applySelect2();
//             }
//           })
//           .catch((err) => {
//             console.error("❌ Error loading cities:", err);
//           });
//       }
//     });

//     observer.observe(chosenSpan, {
//       characterData: true,
//       childList: true,
//       subtree: true,
//     });

//     console.log("👀 Watching for state changes...");
//   }, 500);
});