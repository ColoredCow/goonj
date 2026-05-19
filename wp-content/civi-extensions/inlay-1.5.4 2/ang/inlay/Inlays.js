(function(angular, $, _) {

  function reloadFromApi(crmApi4) {
    return crmApi4({
      inlayTypes: ['InlayType', 'get', {}],
      inlays: ['Inlay', 'get', {select: ["id", "public_id", "name", "class", "status"], orderBy: {"class":"ASC", 'name': 'ASC'}}],
      inlaySetting: ['Setting', 'get', {select: ['inlay']}, 0],
      cors: ['OptionValue', 'get', {
        select: ["value", "option_group_id", "id"],
        where: [
          ["option_group_id:name", "=", "inlay_cors_origins"],
          ['is_active', '=', 1]
        ],
      }],
      corsOptionGroupID: ['OptionGroup', 'get', {
        select: ["id"],
        where: [["name", "=", "inlay_cors_origins"]],
      }, 0]
    });
  }

  angular.module('inlay').config(function($routeProvider) {
      $routeProvider.when('/inlays', {
        controller: 'InlayInlays',
        controllerAs: '$ctrl',
        templateUrl: '~/inlay/Inlays.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          various: function (crmApi4) {
            return reloadFromApi(crmApi4);
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  angular.module('inlay').controller('InlayInlays', function($scope, crmApi4, crmStatus, crmUiHelp, various, crmUiAlert) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('inlay');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/inlay/Inlays'}); // See: templates/CRM/inlay/Inlays.hlp
    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var ctrl = this;
    $scope.CRM = CRM;

    function importConfig(various) {
      console.log('importConfig', various);
      // Parse settings.
      $scope.settings = {
        'polyfill': false,
      };
      if (various.inlaySetting.value) {
        var settings = JSON.parse(various.inlaySetting.value);
        if (settings) {
          $scope.settings = settings;
          $scope.savedPublicBaseUrl = settings.publicBaseUrl || '';
          console.log("Loaded settings", settings);
        }
      }
      $scope.inlays = various.inlays;
      $scope.inlayUseUrls = {};
      $scope.inlayTypes = various.inlayTypes;
      $scope.inlayTypeOptions = $scope.inlayTypes.slice(0);
      // Enhance our inlays by looking up their types now.
      // Create types indexed by class.
      const typeClassToType = {};
      $scope.inlayTypes.forEach(t => { typeClassToType[t['class']] = t; });
      $scope.inlays.forEach(inlay => {
        inlay.inlayType = typeClassToType[inlay['class']] || null;
        if (inlay.inlayType) {
          inlay.editURL = CRM.url(inlay.inlayType.editURLTemplate.replace('{id}', inlay.id));
          inlay.useURL = CRM.url('civicrm/inlay/use', {id: inlay.id});
          console.log({ tpl: inlay.inlayType.editURLTemplate,  final : inlay.editURL, id: inlay.id});
        }
      });

      $scope.optionsListUrl = 'href="' + CRM.url('civicrm/admin/options', {gid: various.corsOptionGroupID['id'], reset: 1}) + '"';
      $scope.cors = various.cors || [];
    }

    importConfig(various);

    $scope.defaultBaseUrl = window.location.origin;
    $scope.cleanUrlWarning = (CRM.url('civicrm/inlay-api', {}, 'front').indexOf('?') > -1);

    $scope.updating = 0;
    $scope.updateAllInlays = function() {
      $scope.updating = 1;
      crmApi4('Inlay', 'createBundle', {}).then(r => {
        $scope.updating = 0;
        if (r.is_error) {
          alert(r.error);
        }
      });
    };

    $scope.toggleStatus = (inlay) => {
      let originalStatus = inlay.status;
      if (inlay.status === 'on' && confirm(ts("Turn OFF %1?", {1: inlay.name}))) {
        inlay.status = 'off';
      }
      else if (inlay.status === 'off' && confirm(ts("Turn ON %1?", {1: inlay.name}))) {
        inlay.status = 'on';
      }
      if (originalStatus != inlay.status) {
        CRM.status({start:"Saving", end:"Saved", error:"Oops"}, CRM.toJqPromise(
          crmApi4('Inlay', 'update', {
            values: { status: inlay.status },
            where: [
              ['id', '=', inlay.id]
            ]
          })
          .catch((e) => {
            console.error(e);
            inlay.status = originalStatus;
            alert(ts("Something went wrong!"));
          })
        ));
      }
    }

    $scope.saveSettings = function(andReload) {
      CRM.status({start:"Saving", end:"Saved", error:"Oops"}, CRM.toJqPromise(
        crmApi4('Setting', 'set', { values: { inlay: JSON.stringify($scope.settings) }})
        .then(r => {
          if (andReload) {
            console.log("reloading");
            return reloadFromApi(crmApi4).then(importConfig);
          }
        })
      ));
    };

    $scope.confirmDelete = function(inlay) {
      if (!confirm("Sure you want to delete this. It will instantly break any website still using it...")) {
        return;
      }
      crmApi4('Inlay', 'delete', {where: [["id", "=", inlay.id]]})
      .then(r => {
        $scope.inlays.splice($scope.inlays.indexOf(inlay), 1);
      });
    };

    $scope.copyInlay = async function(inlay) {
      let newName = prompt("Enter new name for the copy", inlay.name + ' copy');
      if (!newName) {
        return;
      }
      let newInlay = await crmApi4('Inlay', 'get', {where: [["id", "=", inlay.id]]}, 0);
      delete newInlay.id;
      delete newInlay.public_id;
      newInlay.name = newName;
      let savedInlay = await crmApi4('Inlay', 'create', {values: newInlay}, 0);
      const url = CRM.url(inlay.inlayType.editURLTemplate.replace('{id}', savedInlay.id));
      window.location = url;
    };

    $scope.addNewType = $scope.inlayTypeOptions[0];
    $scope.addNew = function(inlayType) {
      if ($scope.addNewType.class) {
        const url = CRM.url(inlayType.editURLTemplate.replace('{id}', 0));
        window.location = url;
      }
    };
    function encodeToHTML(s) {
      return s.replace(/[&<>'"]/g, x => { return {
       '&': '&amp;',
       '<': '&lt;',
       '>': '&gt;',
       '"': '&#34;',
       "'": '&#39;'
     }[x]; });
    }

    $scope.getScript = function(i) {
      var mainScript = encodeToHTML(`<script defer src="${i.scriptUrl}" data-inlay-id="${i.public_id}" ></script>`);
      var wpShortcode = encodeToHTML(`[inlay id="${i.public_id}"]`);
      console.log({mainScript, wpShortcode});

      crmUiAlert({template: `<p>Copy this code to the place you want the form to appear on your website</p><textarea rows=3 cols=60>${mainScript}</textarea><p>If you are using the
        <a href="https://github.com/artfulrobot/inlay-wp">Inlay WordPress plugin</a> you might want this shortcode:</p><input type="text" value="${wpShortcode}" />`,
        title: 'Inlay Embed Code',
        scope: $scope.$new()});
    //   crmUiAlert({template: '<a ng-click="ok()">Hello</a>', scope: $scope.$new()});
    }

    this.searchPublicID = () => {
      const publicID = prompt("Enter public ID code (looks like aa12bb34cc)");
      const found = document.querySelector(`tr[data-public-id="${publicID}"]`);
      if (!found) {
        crmUiAlert({title: 'Not found', status: 'Warning'});
        return;
      }
      found.closest('details').open = true;
      found.style.backgroundColor = 'var(--crm-c-amber)';
      found.style.transition = '';
      setTimeout(() => {
        found.style.transition = '2s background-color linear';
        found.style.backgroundColor = '';
      }, 2000);
    };
  });

})(angular, CRM.$, CRM._);
