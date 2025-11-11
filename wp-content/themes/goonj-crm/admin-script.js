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

(function () {
  function deepQuery(root, selector) {
    const results = [];
    const walker = (node) => {
      if (!node) return;
      if (node.nodeType === 1) {
        try {
          if (node.matches && node.matches(selector)) results.push(node);
        } catch {}
      }
      try {
        if (node.querySelectorAll) node.querySelectorAll(selector).forEach((n) => results.push(n));
      } catch {}
      node.childNodes && node.childNodes.forEach((child) => walker(child));
      if (node.shadowRoot) walker(node.shadowRoot);
    };
    walker(root);
    return results;
  }

  function deepQueryOne(docOrRoot, selector) {
    const found = deepQuery(docOrRoot, selector);
    if (found && found.length) return found[0];
    return (docOrRoot.querySelector && docOrRoot.querySelector(selector)) || null;
  }

  function injectCityDropdownInContext(doc, rootElement) {
    if (!doc || !rootElement) return;

    function cleanLabelText(text) {
      return (text || '').trim().replace(/\*$/, '').trim();
    }

    function findStateFieldWrapper() {
      const selectors = [
        'af-field[name="state_province_id"]',
        'af-field[name="Institution_Collection_Camp_Intent.State"]',
        'af-field[name="Institution_Dropping_Center_Intent.State"]',
        'af-field[name="Collection_Camp_Intent_Details.State"]',
        'af-field[name="Dropping_Centre.State"]',
        'af-field[name="Institution_Goonj_Activities.State"]',
        'af-field[name="Goonj_Activities.State"]',
        'af-field[name="Urban_Planned_Visit.State"]',
        '.editrow_state_province-Primary-section',
        '.crm-summary-row[id*="state_province"]',
      ];
      for (const s of selectors) {
        const el = deepQueryOne(rootElement, s) || deepQueryOne(doc, s);
        if (el) return el;
      }
      const labels = deepQuery(rootElement, 'label').concat(deepQuery(doc, 'label'));
      for (const label of labels) {
        if (cleanLabelText(label.textContent) === 'State') {
          const af = label.closest('af-field') || label.closest('[class*="state"]');
          if (af) return af;
        }
      }
      return null;
    }

    function findCityFieldWrapper() {
      const selectors = [
        'af-field[name="city"]',
        'af-field[name="Collection_Camp_Intent_Details.City"]',
        'af-field[name="Institution_Collection_Camp_Intent.District_City"]',
        'af-field[name="Dropping_Centre.District_City"]',
        'af-field[name="Institution_Goonj_Activities.City"]',
        'af-field[name="Urban_Planned_Visit.City"]',
        'af-field[name="Institution_Dropping_Center_Intent.District_City"]',
        'af-field[name="Goonj_Activities.City"]',
        '.editrow_city-Primary-section',
        '#editrow-city-Primary',
        '.crm-summary-row[id*="city"]',
      ];
      for (const s of selectors) {
        const el = deepQueryOne(rootElement, s) || deepQueryOne(doc, s);
        if (el) return el;
      }
      const labels = deepQuery(rootElement, 'label').concat(deepQuery(doc, 'label'));
      for (const label of labels) {
        if (cleanLabelText(label.textContent) === 'City') {
          const af = label.closest('af-field') || label.closest('[class*="city"]');
          if (af) return af;
        }
      }
      return null;
    }

    function waitForElementsAndInject() {
      const start = Date.now();
      const interval = setInterval(() => {
        const stateFieldWrapper = findStateFieldWrapper();
        const stateChosenSpan =
          stateFieldWrapper &&
          (stateFieldWrapper.querySelector('.select2-chosen') ||
            stateFieldWrapper.querySelector('span[id^="select2-chosen"]') ||
            stateFieldWrapper.querySelector('span.select2-selection__rendered'));
        const cityFieldWrapper = findCityFieldWrapper();
        const cityInput =
          cityFieldWrapper &&
          (cityFieldWrapper.querySelector('input[type="text"]') ||
            cityFieldWrapper.querySelector('input'));
        if (stateFieldWrapper && stateChosenSpan && cityFieldWrapper && cityInput) {
          clearInterval(interval);
          setupDropdown({ stateFieldWrapper, stateChosenSpan, cityFieldWrapper, cityInput });
        }
        if (Date.now() - start > 30000) clearInterval(interval);
      }, 300);
    }

    function setupDropdown({ stateFieldWrapper, stateChosenSpan, cityFieldWrapper, cityInput }) {
      if (!cityFieldWrapper || cityFieldWrapper.querySelector('.citydd')) return;
      cityInput.style.display = 'none';
      const dd = buildCityDropdown(doc, cityInput);
      cityInput.parentElement.appendChild(dd.wrapper);
      const ddRefs = getCityDropdownRefs(cityFieldWrapper);
      let lastState = (stateChosenSpan.textContent || '').trim();
      if (lastState !== '') loadCities(lastState, ddRefs, cityInput.value && cityInput.value.trim());
      const obsTarget = stateChosenSpan;
      const stateObserver = new (doc.defaultView || window).MutationObserver(() => {
        const currentState = (stateChosenSpan.textContent || '').trim();
        if (currentState !== lastState && currentState !== '') {
          lastState = currentState;
          const currentCityValue = cityInput.value && cityInput.value.trim();
          loadCities(currentState, ddRefs, currentCityValue);
        }
      });
      stateObserver.observe(obsTarget, { characterData: true, childList: true, subtree: true });
    }

    function buildCityDropdown(ctxDoc, hiddenInput) {
      const wrapper = ctxDoc.createElement('div');
      wrapper.className = 'citydd';
      wrapper.setAttribute('data-citydd', '1');

      const toggle = ctxDoc.createElement('button');
      toggle.type = 'button';
      toggle.className = 'citydd-toggle';
      toggle.setAttribute('aria-haspopup', 'listbox');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.innerHTML = `<span class="citydd-label">Select a city</span><span class="citydd-caret">▾</span>`;

      const panel = ctxDoc.createElement('div');
      panel.className = 'citydd-panel';

      const search = ctxDoc.createElement('input');
      search.type = 'text';
      search.className = 'citydd-search';
      search.placeholder = 'Search city...';

      const list = ctxDoc.createElement('ul');
      list.className = 'citydd-list';
      list.setAttribute('role', 'listbox');

      const empty = ctxDoc.createElement('div');
      empty.className = 'citydd-empty';
      empty.textContent = 'No matches';

      panel.appendChild(search);
      panel.appendChild(list);
      panel.appendChild(empty);
      wrapper.appendChild(toggle);
      wrapper.appendChild(panel);

      function open() {
        wrapper.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        setTimeout(() => search.focus(), 0);
      }
      function close() {
        wrapper.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        try {
          toggle.focus();
        } catch {}
      }

      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        wrapper.classList.contains('open') ? close() : open();
      });
      toggle.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
          e.preventDefault();
          open();
        }
      });
      try {
        ctxDoc.addEventListener('click', (e) => {
          if (!wrapper.contains(e.target) && wrapper.classList.contains('open')) close();
        });
      } catch {}
      return { wrapper, toggle, panel, search, list, empty, hiddenInput, ctxDoc };
    }

    function getCityDropdownRefs(container) {
      const wrapper = container.querySelector('.citydd');
      return {
        wrapper,
        toggle: wrapper.querySelector('.citydd-toggle'),
        panel: wrapper.querySelector('.citydd-panel'),
        search: wrapper.querySelector('.citydd-search'),
        list: wrapper.querySelector('.citydd-list'),
        empty: wrapper.querySelector('.citydd-empty'),
        labelEl: wrapper.querySelector('.citydd-label'),
        hiddenInput:
          container.querySelector('input[type="text"]') || container.querySelector('input'),
      };
    }

    function loadCities(stateName, ddRefs, preselectValue = null) {
      const origin =
        (doc.defaultView && doc.defaultView.location && doc.defaultView.location.origin) ||
        window.location.origin;
      const baseUrl = `${origin}/wp-admin/admin-ajax.php`;
      const fetchFn =
        (doc.defaultView && doc.defaultView.fetch)
          ? doc.defaultView.fetch.bind(doc.defaultView)
          : fetch;
      fetchFn(baseUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'get_cities_by_state', state_name: stateName }),
      })
        .then((res) => res.json())
        .then((data) => {
          const cityList =
            data && data.success && data.data && data.data.cities && data.data.cities.length
              ? data.data.cities.map((c) => c.name)
              : [];
          if (!cityList.includes('Other')) cityList.push('Other');
          populateCityDropdown(ddRefs, cityList, preselectValue);
        })
        .catch(() => {
          populateCityDropdown(ddRefs, ['Other'], preselectValue);
        });
    }

    function populateCityDropdown(dd, cities, preselectValue) {
      const ctxDoc = dd.list.ownerDocument || doc;
      dd.list.innerHTML = '';
      const hasExistingIncorrectCity =
        preselectValue &&
        !cities.some((c) => c.toLowerCase() === (preselectValue || '').toLowerCase());
      if (hasExistingIncorrectCity) {
        const incorrectLi = ctxDoc.createElement('li');
        incorrectLi.className = 'citydd-item';
        incorrectLi.setAttribute('role', 'option');
        incorrectLi.setAttribute('tabindex', '-1');
        incorrectLi.textContent = `${preselectValue} (incorrect city name)`;
        incorrectLi.dataset.value = preselectValue;
        incorrectLi.dataset.unmatched = '1';
        incorrectLi.style.color = '#d9534f';
        dd.list.appendChild(incorrectLi);
      }

      const items = cities.map((name) => {
        const li = ctxDoc.createElement('li');
        li.className = 'citydd-item';
        li.setAttribute('role', 'option');
        li.setAttribute('tabindex', '-1');
        li.textContent = name;
        li.dataset.value = name;
        dd.list.appendChild(li);
        return li;
      });

      const allItems = hasExistingIncorrectCity ? [dd.list.firstChild, ...items] : items;

      const filter = (q) => {
        const query = (q || '').trim().toLowerCase();
        allItems.forEach((li) => {
          const match = li.textContent.toLowerCase().includes(query);
          li.style.display = match ? '' : 'none';
          li.removeAttribute('aria-current');
        });
        const firstVisible = allItems.find((li) => li.style.display !== 'none');
        dd.empty.style.display = firstVisible ? 'none' : 'block';
        if (firstVisible) firstVisible.setAttribute('aria-current', 'true');
      };

      dd.search.value = '';
      dd.empty.style.display = 'none';
      filter('');

      function selectValue(value, fromKeyboard = false) {
        dd.hiddenInput.value = value;
        try {
          dd.hiddenInput.dispatchEvent(
            new (ctxDoc.defaultView || window).Event('input', { bubbles: true })
          );
        } catch {}
        const selectedLi = allItems.find((li) => li.dataset.value === value);
        dd.labelEl.textContent = selectedLi ? selectedLi.textContent : value;
        Array.from(dd.list.children).forEach((li) =>
          li.setAttribute('aria-selected', li.dataset.value === value ? 'true' : 'false')
        );
        dd.wrapper.classList.remove('open');
        dd.toggle.setAttribute('aria-expanded', 'false');
        if (!fromKeyboard) try { dd.toggle.focus(); } catch {}
      }

      allItems.forEach((li) => li.addEventListener('click', () => selectValue(li.dataset.value)));

      dd.search.addEventListener('keydown', (e) => {
        const visibleItems = allItems.filter((li) => li.style.display !== 'none');
        const currentIdx = visibleItems.findIndex(
          (li) => li.getAttribute('aria-current') === 'true'
        );
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          const next = visibleItems[Math.min(currentIdx + 1, visibleItems.length - 1)];
          visibleItems.forEach((li) => li.removeAttribute('aria-current'));
          if (next) next.setAttribute('aria-current', 'true');
          next && next.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          const prev = visibleItems[Math.max(currentIdx - 1, 0)];
          visibleItems.forEach((li) => li.removeAttribute('aria-current'));
          if (prev) prev.setAttribute('aria-current', 'true');
          prev && prev.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
          e.preventDefault();
          const current = visibleItems[currentIdx >= 0 ? currentIdx : 0];
          if (current) selectValue(current.dataset.value, true);
        } else if (e.key === 'Escape') {
          e.preventDefault();
          dd.wrapper.classList.remove('open');
          dd.toggle.setAttribute('aria-expanded', 'false');
          dd.toggle.focus();
        }
      });

      dd.search.addEventListener('input', (e) => filter(e.target.value));

      if (preselectValue) {
        selectValue(preselectValue);
      } else {
        dd.labelEl.textContent = 'Select a city';
      }
    }

    waitForElementsAndInject();

    try {
      const ModalObserver = new (doc.defaultView || window).MutationObserver((mutations) => {
        mutations.forEach((m) => {
          if (m.addedNodes && m.addedNodes.length) {
            m.addedNodes.forEach((n) => {
              if (
                n.querySelector &&
                (n.querySelector('af-field[name="city"]') ||
                  n.querySelector(
                    'af-field[name="Institution_Collection_Camp_Intent.District_City"]'
                  ))
              ) {
                waitForElementsAndInject();
              }
              const found = deepQuery(n, 'af-field[name="city"]');
              if (found && found.length) waitForElementsAndInject();
            });
          }
        });
      });
      ModalObserver.observe(rootElement, { childList: true, subtree: true });
    } catch {}
  }

  try {
    injectCityDropdownInContext(document, document.body);
  } catch {}

  (function watchPopups() {
    const topObserver = new MutationObserver((mutations) => {
      mutations.forEach((m) => {
        m.addedNodes.forEach((node) => {
          try {
            if (node.tagName === 'IFRAME') {
              node.addEventListener('load', () => {
                const iframeDoc =
                  node.contentDocument || (node.contentWindow && node.contentWindow.document);
                if (!iframeDoc || iframeDoc.body.hasAttribute('data-city-dropdown-injected')) return;
                iframeDoc.body.setAttribute('data-city-dropdown-injected', 'true');
                injectCityDropdownInContext(iframeDoc, iframeDoc.body);
              });
              const d =
                node.contentDocument || (node.contentWindow && node.contentWindow.document);
              if (
                d &&
                d.readyState === 'complete' &&
                d.body &&
                !d.body.hasAttribute('data-city-dropdown-injected')
              ) {
                d.body.setAttribute('data-city-dropdown-injected', 'true');
                injectCityDropdownInContext(d, d.body);
              }
            }

            if (
              node.classList &&
              (node.classList.contains('ui-dialog') ||
                node.classList.contains('crm-popup') ||
                node.classList.contains('modal') ||
                node.classList.contains('searchkit-modal'))
            ) {
              if (!node.hasAttribute('data-city-dropdown-injected')) {
                node.setAttribute('data-city-dropdown-injected', 'true');
                injectCityDropdownInContext(document, node);
              }
            }

            if (node.querySelector || node.shadowRoot) {
              const found = deepQuery(node, 'af-field[name="city"]');
              if (found && found.length) {
                const ctxDoc = node.ownerDocument || document;
                if (!node.hasAttribute('data-city-dropdown-injected')) {
                  node.setAttribute('data-city-dropdown-injected', 'true');
                  injectCityDropdownInContext(ctxDoc, node);
                }
              }
            }
          } catch {}
        });
      });
    });

    try {
      topObserver.observe(document.body, { childList: true, subtree: true });
    } catch {}

    try {
      Array.from(document.querySelectorAll('iframe')).forEach((iframe) => {
        try {
          const d =
            iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
          if (d && d.body && !d.body.hasAttribute('data-city-dropdown-injected')) {
            d.body.setAttribute('data-city-dropdown-injected', 'true');
            injectCityDropdownInContext(d, d.body);
          }
          iframe.addEventListener('load', () => {
            const dd =
              iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
            if (dd && dd.body && !dd.body.hasAttribute('data-city-dropdown-injected')) {
              dd.body.setAttribute('data-city-dropdown-injected', 'true');
              injectCityDropdownInContext(dd, dd.body);
            }
          });
        } catch {}
      });

      Array.from(
        document.querySelectorAll('.ui-dialog, .crm-popup, .modal, .searchkit-modal')
      ).forEach((modal) => {
        if (!modal.hasAttribute('data-city-dropdown-injected')) {
          modal.setAttribute('data-city-dropdown-injected', 'true');
          injectCityDropdownInContext(document, modal);
        }
      });
    } catch (err) {
      console.warn('CityDropdown: Error while processing existing modals/iframes', err);
    }
  })();
})();
