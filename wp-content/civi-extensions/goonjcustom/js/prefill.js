(function () {
  console.log('[AfformPrefill] Script with logs and EntityRef + dependent autofill loaded');

  function setNested(obj, path, value) {
    const parts = path.split('.');
    let current = obj;
    for (let i = 0; i < parts.length - 1; i++) {
      if (!current[parts[i]]) current[parts[i]] = {};
      current = current[parts[i]];
    }
    current[parts[parts.length - 1]] = value;
  }

  function flattenObject(obj, prefix = '', result = {}) {
    Object.keys(obj).forEach(function (key) {
      const value = obj[key];
      const newKey = prefix ? `${prefix}.${key}` : key;
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        flattenObject(value, newKey, result);
      } else {
        result[newKey] = value;
      }
    });
    return result;
  }

  function waitForAngularAndPrefill(retries = 100) {
    if (!(window.angular && window.angular.module)) {
      if (retries <= 0) return console.error('[AfformPrefill] Angular not found');
      return setTimeout(() => waitForAngularAndPrefill(retries - 1), 200);
    }
    pollForFormAndPrefill();
  }

  function pollForFormAndPrefill(retries = 100) {
    const formElem = document.querySelector('[af-form], [af-fieldset], .crm-afform-form, .af-container');
    if (!formElem) {
      if (retries <= 0) return console.error('[AfformPrefill] Form not found');
      return setTimeout(() => pollForFormAndPrefill(retries - 1), 200);
    }

    const scope = angular.element(formElem).scope();
    if (!scope) {
      if (retries <= 0) return console.error('[AfformPrefill] Scope not found');
      return setTimeout(() => pollForFormAndPrefill(retries - 1), 200);
    }

    if (scope._afformPrefilled) {
      console.log('[AfformPrefill] Already prefilled ✅');
      return;
    }

    const data = window.CRM?.afformPrefillData;
    if (!data) return console.warn('[AfformPrefill] No afformPrefillData found ❗');

    const $timeout = angular.element(formElem).injector().get('$timeout');
    const $rootScope = angular.element(formElem).injector().get('$rootScope');

    $timeout(function () {
      const flattened = flattenObject(data);
      const scopePaths = ['data', 'model', 'formData', 'entity'];

      console.log('[AfformPrefill] Flattened keys:', Object.keys(flattened));

      // Set values in Angular scope
      scopePaths.forEach(path => {
        if (!scope[path]) scope[path] = {};
        Object.entries(flattened).forEach(([key, value]) => {
          setNested(scope[path], key, value);
          console.log(`[AfformPrefill] Set scope.${path}.${key} =`, value);
        });
      });

      scopePaths.forEach(path => {
        Object.keys(scope[path]).forEach(entity => {
          if (entity === 'Camp_Institution_Data' && typeof scope[path][entity] === 'object' && !Array.isArray(scope[path][entity])) {
            console.log(`[AfformPrefill] Wrapping ${path}.${entity} as array for join compatibility`);
            scope[path][entity] = [scope[path][entity]];
          }
        });
      });

      // Autofill standard input fields
      const inputs = formElem.querySelectorAll('input, select, textarea');
      console.log('[AfformPrefill] Total fields to check:', inputs.length);
      inputs.forEach((field) => {
        const fieldName = field.name || field.getAttribute('name');
        const ngModel = field.getAttribute('ng-model');
        const id = field.id || '';
        const fieldKey = ngModel?.replace(/^.*?entity\./, '') || fieldName || id;

        const matchedKey = Object.keys(flattened).find(k =>
          k === fieldKey || k.endsWith('.' + fieldKey) || (fieldKey && k.includes(fieldKey))
        );

        if (!matchedKey) {
          console.warn(`[AfformPrefill] No match found for field:`, { fieldName, ngModel, id });
          return;
        }

        const value = flattened[matchedKey];
        console.log(`[AfformPrefill] ✅ Matched ${matchedKey} →`, value);

        try {
          if (field.tagName.toLowerCase() === 'select') {
            field.value = value;
          } else if (field.type === 'checkbox') {
            field.checked = !!value;
          } else if (field.type === 'radio') {
            if (field.value == value) field.checked = true;
          } else {
            field.value = value;
          }

          ['input', 'change', 'blur'].forEach(evt => {
            const e = new Event(evt, { bubbles: true });
            field.dispatchEvent(e);
            angular.element(field).triggerHandler(evt);
          });

          console.log(`[AfformPrefill] Value set on DOM field [${fieldKey}]`);

        } catch (err) {
          console.error(`[AfformPrefill] Error setting field [${fieldKey}]`, err.message);
        }
      });

      document.querySelectorAll('.ng-isolate-scope').forEach(function (afFieldElem) {
        const fieldName = afFieldElem.getAttribute('name');
        const flatData = flattenObject(window.CRM.afformPrefillData);
        if (!flatData[fieldName]) {
          console.warn(`[EntityRef] No data to prefill for ${fieldName}`);
          return;
        }

        const value = flatData[fieldName];
        const isolateScope = angular.element(afFieldElem).isolateScope();
        const model = isolateScope?.getSetSelect;

        if (typeof model === 'function') {
          console.log(`[EntityRef] Setting ${fieldName} →`, value);
          model(value);

          if (typeof isolateScope?.$ctrl?.onSelectEntity === 'function') {
            isolateScope.$ctrl.onSelectEntity();
            console.log(`[EntityRef] onSelectEntity() triggered for ${fieldName}`);
          }

          isolateScope.$applyAsync?.();
        } else {
          console.warn(`[EntityRef] getSetSelect not found for ${fieldName}`);
        }
      });

      scope._afformPrefilled = true;
      try { scope.$apply(); $rootScope.$apply(); } catch (_) {}

      console.log('[AfformPrefill]Autofill completed!');
    }, 1000);
  }

  waitForAngularAndPrefill();
})();
