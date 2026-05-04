(function(angular, $) {
  'use strict';

  angular.module('goonjEventRegistration', CRM.angRequires('goonjEventRegistration'));

  // Afform this module targets. Used for the success/duplicate redirect check.
  var AFFORM_NAME = 'afformGoonjEventRegistration';
  // Participant fieldset we inject the Event Type filter above. Matched by
  // af-fieldset name so the attach logic survives any FormBuilder edit that
  // keeps the fieldset (rename, reorder, add more af-fields, etc).
  var FIELDSET_SELECTOR = 'fieldset[af-fieldset="Participant1"]';

  // Tiny settings wrapper so admins can override URLs + labels from CRM
  // Settings without touching code. Defaults come from the vars bundle
  // (crmBundle('afform')) populated by our PHP hook; fallbacks keep the form
  // working even if settings are unset.
  function getConfig() {
    var cfg = (CRM && CRM.goonjEventRegistration) || {};
    return {
      successUrl: cfg.successUrl || '/event-sucess/',
      duplicateUrl: cfg.duplicateUrl || '/event-duplicate/',
      filterLabel: cfg.filterLabel || 'Event Type',
      filterHelp: cfg.filterHelp || 'Pick a type to filter the Event list below.',
      allTypesLabel: cfg.allTypesLabel || 'All types'
    };
  }

  // Inject the Event Type filter above the Participant1 fieldset and wire it
  // into each event_id autocomplete. Pulled out of the old attribute directive
  // so the run() block below can call it without any markup trigger — admins
  // cannot accidentally delete a filter that has no representation in the
  // Afform layout.
  function attachFilter(scope, fieldsetEl, $timeout, $compile, crmApi4) {
    var cfg = getConfig();
    var elem = angular.element(fieldsetEl);

    scope.gcFilterLabel = cfg.filterLabel;
    scope.gcFilterHelp = cfg.filterHelp;
    scope.gcEventTypeFilter = '';
    scope.gcEventTypes = [{value: '', label: cfg.allTypesLabel}];

    // Fetch Event Type options live; no hardcoded values. The Event
    // pseudoconstant resolves to the in-DB option_group — whatever admins
    // configure there shows up here on next page load.
    crmApi4('OptionValue', 'get', {
      select: ['value', 'label'],
      where: [['option_group_id:name', '=', 'event_type'], ['is_active', '=', true]],
      orderBy: {weight: 'ASC'}
    }).then(function(rows) {
      var fetched = (rows || []).map(function(r) {
        return {value: String(r.value), label: r.label};
      });
      scope.gcEventTypes = [{value: '', label: cfg.allTypesLabel}].concat(fetched);
    });

    var filterHtml =
      '<fieldset class="af-container gc-event-type-filter-injected" af-title="' + cfg.filterLabel + '">' +
      '  <div class="crm-af-field">' +
      '    <label>' + cfg.filterLabel + '</label>' +
      '    <select class="crm-form-select form-control" ng-model="gcEventTypeFilter" ' +
      '            ng-options="o.value as o.label for o in gcEventTypes"></select>' +
      '    <p class="help-block">' + cfg.filterHelp + '</p>' +
      '  </div>' +
      '</fieldset>';
    // Compile against the fieldset's scope so ng-model / ng-options bind,
    // otherwise the injected HTML is inert (Angular only compiles nodes
    // present at link time).
    var compiled = $compile(filterHtml)(scope);
    elem.before(compiled);

    // Patch each event_id af-field controller's getAutocompleteParams so
    // the Event Type filter flows into the server request as a filter.
    var patched = new WeakSet();
    function patchAfFields() {
      angular.forEach(fieldsetEl.querySelectorAll('af-field'), function(afFieldEl) {
        var afFieldScope = angular.element(afFieldEl).isolateScope() || angular.element(afFieldEl).scope();
        if (!afFieldScope || !afFieldScope.$ctrl || patched.has(afFieldScope.$ctrl)) {
          return;
        }
        var ctrl = afFieldScope.$ctrl;
        if (ctrl.fieldName !== 'event_id' || typeof ctrl.getAutocompleteParams !== 'function') {
          return;
        }
        var original = ctrl.getAutocompleteParams.bind(ctrl);
        ctrl.getAutocompleteParams = function() {
          var params = original() || {};
          if (scope.gcEventTypeFilter) {
            params.filters = angular.extend({}, params.filters || {}, {
              event_type_id: parseInt(scope.gcEventTypeFilter, 10)
            });
          }
          return params;
        };
        patched.add(ctrl);
      });
    }

    $timeout(patchAfFields, 300);
    scope.$watch(function() {
      return fieldsetEl.querySelectorAll('af-field').length;
    }, function() {
      $timeout(patchAfFields, 50);
    });

    // Clear selected events whenever the filter changes so users never
    // submit with a stale event that doesn't match the newly picked type.
    scope.$watch('gcEventTypeFilter', function(newVal, oldVal) {
      if (newVal === oldVal) {
        return;
      }
      var afCtrl = angular.element(document.querySelector('af-form')).controller('afForm');
      if (afCtrl && afCtrl.data && afCtrl.data.Participant1) {
        afCtrl.data.Participant1.forEach(function(p) {
          if (p && p.fields) {
            p.fields.event_id = null;
          }
        });
      }
      $(fieldsetEl).find('input.crm-ajax-select').each(function() {
        if ($(this).data('select2')) {
          $(this).select2('data', null);
        }
      });
    });
  }

  // Auto-attach on page load. The module is only loaded on Afforms that list
  // goonjEventRegistration in their `requires`, so finding a Participant1
  // fieldset is sufficient — no markup attribute to accidentally delete.
  // Retries briefly while Afform finishes its own compile pass.
  angular.module('goonjEventRegistration').run(function($timeout, $compile, crmApi4) {
    function tryAttach(attempt) {
      attempt = attempt || 0;
      if (attempt > 40) {
        return;
      }
      var fieldset = document.querySelector(FIELDSET_SELECTOR);
      if (!fieldset) {
        return $timeout(function() { tryAttach(attempt + 1); }, 250);
      }
      if (fieldset.__gcFilterAttached) {
        return;
      }
      var scope = angular.element(fieldset).scope();
      if (!scope) {
        return $timeout(function() { tryAttach(attempt + 1); }, 250);
      }
      fieldset.__gcFilterAttached = true;
      attachFilter(scope, fieldset, $timeout, $compile, crmApi4);
    }
    angular.element(document).ready(function() {
      $timeout(function() { tryAttach(0); }, 100);
    });
  });

  // Hook into Afform's crmFormSuccess event — fires after server returns.
  // If the server returned an empty Participant1 array it means every
  // selected event was a duplicate (the Participant hook silently skipped
  // them). In that case we override Afform's own redirect target.
  //
  // Why mutate data.afform.redirect instead of setting window.location.href:
  // Afform's postProcess() fires this event and THEN does its own
  // `window.location.href = metaData.redirect` a few lines later, which
  // would clobber our assignment (both are synchronous). `data.afform` is
  // the same object reference as metaData, so rewriting its redirect field
  // here makes Afform's own logic navigate to our duplicate URL instead.
  $(document).on('crmFormSuccess.goonjEventReg', function(evt, data) {
    if (!data || !data.afform || data.afform.name !== AFFORM_NAME) {
      return;
    }
    var resp = (data.submissionResponse && data.submissionResponse[0]) || {};
    var created = (resp.Participant1 || []).length;
    if (created === 0) {
      data.afform.redirect = getConfig().duplicateUrl;
    }
  });
})(angular, CRM.$);
