(function(angular, $, _) {
  "use strict";
  // Example usage: <af-form ctrl="afform">
  angular.module('af').component('afForm', {
    bindings: {
      ctrl: '@'
    },
    require: {
      ngForm: 'form'
    },
    controller: function($scope, $element, $timeout, crmApi4, crmStatus, $window, $location, $parse, FileUploader) {
      var schema = {},
        data = {extra: {}},
        status,
        args,
        submissionResponse,
        autoSave = _.noop,
        saveDraftButtons = [],
        draftStatus = 'pristine',
        cancelDraftWatcher,
        uploadingDraftFiles = false,
        ts = CRM.ts('org.civicrm.afform'),
        ctrl = this;

      this.$onInit = function() {
        // This component has no template. It makes its controller available within it by adding it to the parent scope.
        $scope.$parent[this.ctrl] = this;

        $timeout(function() {
          ctrl.loadData()
            .then(setupDraftWatcher);

          ctrl.showSubmitButton = displaySubmitButton(args);
        });
      };

      this.registerEntity = function registerEntity(entity) {
        schema[entity.modelName] = entity;
        data[entity.modelName] = [];
      };
      this.getEntity = function getEntity(name) {
        return schema[name];
      };
      // Returns field values for a given entity
      this.getData = function getData(name) {
        return data[name];
      };
      this.getSchema = function getSchema(name) {
        return schema[name];
      };
      // Returns the 'meta' record ('name', 'description', etc) of the active form.
      // @see afform_civicrm_buildAsset() for whitelist of form metadata
      this.getFormMeta = function getFormMeta() {
        return $scope.$parent.meta;
      };
      this.resetForm = function() {
        this.ngForm.$setPristine();
        $scope.$parent.$broadcast('afFormReset');
        this.loadData();
      };
      // With no arguments this will prefill the entire form based on url args
      // and also check if the form is open for submissions.
      // With selectedEntity, selectedIndex & selectedId provided this will prefill a single entity
      this.loadData = function(selectedEntity, selectedIndex, selectedId, selectedField, joinEntity, joinIndex) {
        let toLoad = true;
        const params = {name: ctrl.getFormMeta().name, args: {}};
        // Load single entity
        if (selectedEntity) {
          toLoad = !!selectedId;
          params.args[selectedEntity] = {};
          params.args[selectedEntity][selectedIndex] = {};
          if (joinEntity) {
            params.fillMode = 'join';
            params.args[selectedEntity][selectedIndex].joins = {};
            params.args[selectedEntity][selectedIndex].joins[joinEntity] = {};
            params.args[selectedEntity][selectedIndex].joins[joinEntity][joinIndex] = {};
            params.args[selectedEntity][selectedIndex].joins[joinEntity][joinIndex][selectedField] = selectedId;
          } else {
            params.fillMode = 'entity';
            params.args[selectedEntity][selectedIndex][selectedField] = selectedId;
          }
        }
        // Prefill entire form
        else {
          params.fillMode = 'form';
          args = _.assign({}, $scope.$parent.routeParams || {}, $scope.$parent.options || {});
          _.each(schema, function (entity, entityName) {
            if (args[entityName] && typeof args[entityName] === 'string') {
              args[entityName] = args[entityName].split(',');
            }
          });
          params.args = args;
          ctrl.showSubmitButton = displaySubmitButton(args);
        }
        if (toLoad) {
          if (params.fillMode === 'form') {
            $element.block();
          }
          return crmApi4('Afform', 'prefill', params)
            .then((result) => {
              // In some cases (noticed on Wordpress) the response header incorrectly outputs success when there's an error.
              if (result.error_message) {
                disableForm(result.error_message);
                $element.unblock();
                return;
              }
              result.forEach((item) => {
                // Use _.each() because item.values could be cast as an object if array keys are not sequential
                _.each(item.values, (values, index) => {
                  data[item.name][index] = data[item.name][index] || {};
                  data[item.name][index].joins = data[item.name][index].joins || {};
                  angular.merge(data[item.name][index], values, {fields: _.cloneDeep(schema[item.name].data || {})});
                });
              });
              $element.unblock();
            }, (error) => {
              disableForm(error.error_message);
              $element.unblock();
            });
        }
        // Clear existing join selection
        else if (joinEntity) {
          data[selectedEntity][selectedIndex].joins[joinEntity][joinIndex] = {};
        }
        // Clear existing entity selection
        else if (selectedEntity) {
          // Delete object keys without breaking object references
          Object.keys(data[selectedEntity][selectedIndex].fields).forEach(key => delete data[selectedEntity][selectedIndex].fields[key]);
          // Fill pre-set values
          angular.merge(data[selectedEntity][selectedIndex].fields, _.cloneDeep(schema[selectedEntity].data || {}));
          data[selectedEntity][selectedIndex].joins = {};
        }
      };

      function displaySubmitButton(args) {
        if (args.sid && args.sid.length > 0) {
          return false;
        }
        return true;
      }

      // Used when submitting file fields
      var token = new URLSearchParams(window.location.search).get('_aff');
      var headers = {'X-Requested-With': 'XMLHttpRequest'};
      if (token) {
        headers['X-Civi-Auth-Afform'] = token;
      }
      this.fileUploader = new FileUploader({
        url: CRM.url('civicrm/ajax/api4/Afform/submitFile'),
        headers: headers,
        onAfterAddingFile: function(item) {
          setDraftStatus('unsaved');
        },
        onSuccessItem: onFileUploadSuccess,
        onCompleteAll: onFileUploadsComplete,
        onBeforeUploadItem: function(item) {
          status.resolve();
          status = CRM.status({start: ts('Uploading %1', {1: item.file.name})});
        }
      });

      function onFileUploadSuccess(item, response, status, headers) {
        if (response.values && response.values[0] && response.values[0].id) {
          var dataProvider = item.crmDataProvider;
          dataProvider.getFieldData()[item.crmFieldName] = response.values[0];
        }
      }

      function onFileUploadsComplete() {
        if (uploadingDraftFiles) {
          uploadingDraftFiles = false;
          setDraftStatus('saved');
          //
          if (draftStatus === 'unsaved') {
            autoSave();
          }
          status.resolve();
        } else {
          postProcess();
        }
      }

      // Set up background tasks for saving draft
      function setupDraftWatcher() {
        const buttons = getDraftButtons();
        const autoSaveEnabled = ctrl.getFormMeta().autosave_draft;

        if ((!autoSaveEnabled && !buttons.length) || !ctrl.showSubmitButton || !CRM.config.cid) {
          // No watchers needed
          return;
        }

        // Store initial state of any save-draft buttons on the form
        $.each(buttons, function(index, button) {
          saveDraftButtons[index] = {
            text: $(button).text(),
            icon: $(button).attr('crm-icon'),
          };
        });

        // If autosave enabled, save every ten seconds if changes have been made
        if (autoSaveEnabled) {
          autoSave = _.debounce(ctrl.submitDraft, 10000);
        }

        cancelDraftWatcher = $scope.$watch(() => data, function (newVal, oldVal) {
            if (oldVal) {
              if (draftStatus === 'pristine') {
                setDraftStatus('saved');
              } else {
                setDraftStatus('unsaved');
                autoSave(newVal);
              }
            }
          },
          true
        );
      }

      // Handle the logic for conditional fields
      this.checkConditions = function(conditions, op) {
        op = op || 'AND';
        // OR and AND have the opposite behavior so the logic is inverted
        // NOT works identically to OR but gets flipped at the end
        var ret = op === 'AND',
          flip = !ret;
        _.each(conditions, function(clause) {
          // Recurse into nested group
          if (_.isArray(clause[1])) {
            if (ctrl.checkConditions(clause[1], clause[0]) === flip) {
              ret = flip;
            }
          } else {
            // Angular can't handle expressions with quotes inside brackets, so they are omitted
            // Here we add them back to make valid js
            if (_.isString(clause[0]) && clause[0].charAt(0) !== '"') {
              clause[0] = clause[0].replace(/\[([^'"])/g, "['$1").replace(/([^'"])]/g, "$1']");
            }
            let parser1 = $parse(clause[0]);
            let parser2 = $parse(clause[2]);
            let result = compareConditions(parser1(data), clause[1], parser2(data));
            if (result === flip) {
              ret = flip;
            }
          }
        });
        return op === 'NOT' ? !ret : ret;
      };

      function compareConditions(val1, op, val2) {
        const yes = (op !== '!=' && !op.includes('NOT '));

        switch (op) {
          case '=':
          case '!=':
          // Legacy operator, changed to '=', but may still exist on older forms.
          case '==':
            // Case-insensitive string comparisons
            if (typeof val1 === 'string') {
              val1 = val1.toLowerCase();
            }
            if (typeof val2 === 'string') {
              val2 = val2.toLowerCase();
            }
            return angular.equals(val1, val2) === yes;

          case '>':
            return val1 > val2;

          case '<':
            return val1 < val2;

          case '>=':
            return val1 >= val2;

          case '<=':
            return val1 <= val2;

          case 'IS EMPTY':
            return !val1;

          case 'IS NOT EMPTY':
            return !!val1;

          case 'CONTAINS':
          case 'NOT CONTAINS':
            if (Array.isArray(val1)) {
              return val1.includes(val2) === yes;
            } else if (typeof val1 === 'string' && typeof val2 === 'string') {
              return val1.toLowerCase().includes(val2.toLowerCase()) === yes;
            }
            return angular.equals(val1, val2) === yes;

          case 'IN':
          case 'NOT IN':
            if (Array.isArray(val2)) {
              return val2.includes(val1) === yes;
            }
            return angular.equals(val1, val2) === yes;

          case 'LIKE':
          case 'NOT LIKE':
            if (typeof val1 === 'string' && typeof val2 === 'string') {
              return likeCompare(val1, val2) === yes;
            }
            return angular.equals(val1, val2) === yes;
        }
      }

      function likeCompare(str, pattern) {
        // Escape regex special characters in the pattern, except for % and _
        const regexPattern = pattern
          .replace(/([.+?^=!:${}()|\[\]\/\\])/g, "\\$1")
          .replace(/%/g, '.*') // Convert % to .*
          .replace(/_/g, '.'); // Convert _ to .
        const regex = new RegExp(`^${regexPattern}$`, 'i');
        return regex.test(str);
      }

      // Called after form is submitted and files are uploaded
      function postProcess() {
        var metaData = ctrl.getFormMeta(),
          dialog = $element.closest('.ui-dialog-content');

        $element.trigger('crmFormSuccess', {
          afform: metaData,
          data: data,
          submissionResponse: submissionResponse,
        });

        status.resolve();
        $element.unblock();

        if (dialog.length) {
          dialog.dialog('close');
        }

        else if (metaData.confirmation_type && metaData.confirmation_type === 'show_confirmation_message') {
          $element.hide();
          const $confirmation = $('<div class="afform-confirmation" />');
          $confirmation.text(metaData.confirmation_message);
          $confirmation.insertAfter($element);
        }

        else if ((!metaData.confirmation_type && metaData.redirect ) || (metaData.confirmation_type && metaData.confirmation_type === 'redirect_to_url' && metaData.redirect)) {
          var url = replaceTokens(metaData.redirect, submissionResponse[0]);
          if (url.indexOf('civicrm/') === 0) {
            url = CRM.url(url);
          } else if (url.indexOf('/') === 0) {
            let port = $location.port();
            port = port ? `:${port}` : '';
            url = `${$location.protocol()}://${$location.host()}${port}${url}`;
          }
          $window.location.href = url;
        }
      }

      function replaceTokens(str, vars) {
        function recurse(stack, values) {
          _.each(values, function(value, key) {
            if (_.isArray(value) || _.isPlainObject(value)) {
              recurse(stack.concat([key]), value);
            } else {
              var token = (stack.length ? stack.join('.') + '.' : '') + key;
              str = str.replace(new RegExp(_.escapeRegExp('[' + token + ']'), 'g'), value);
            }
          });
        }
        recurse([], vars);
        return str;
      }

      function validateFileFields() {
        var valid = true;
        $("af-form[ng-form=" + ctrl.getFormMeta().name +"] input[type='file']").each((index, fld) => {
          if ($(fld).attr('required') && $(fld).get(0).files.length == 0) {
            valid = false;
          }
        });
        return valid;
      }

      function disableForm(errorMsg) {
        $('af-form[ng-form="' + ctrl.getFormMeta().name + '"]')
          .addClass('disabled')
          .find('button[ng-click="afform.submit()"]').prop('disabled', true);
        CRM.alert(errorMsg, ts('Sorry'), 'error');
      }
      // NOTE: This function currently provides basic validation for email, phone number, and postal code fields.
      // For now, we have implemented these simple checks to meet current project requirements.
      // In the future, we need to change this.
      function customValidateFields() {
        var isValid = true;
        var errorMessage = "";

        // Email validation
        $element.find("input#institute-registration-email-of-institute-11, input[type='email']").each(function () {
          var emailValue = $(this).val().trim();
          if (emailValue !== "") {
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailValue)) {
              errorMessage += "Please enter a valid email address.\n";
              isValid = false;
            }
          }
        });
          
        // Phone number validation
        $element.find("af-field[name='phone'] input[type='text'], af-field[name='Material_Contribution.Delivered_By_Contact'] input[type='text'], af-field[name='Institute_Registration.Contact_number_of_Institution'] input[type='text']").each(function () {
          var phoneNumberValue = $(this).val().trim();
          if (phoneNumberValue !== "") {
            var phonePattern = /^\d{10}$/;  // 10-digit phone number pattern
            if (!phonePattern.test(phoneNumberValue)) {
              errorMessage += "Please enter a valid 10-digit mobile number.\n";
              isValid = false;
            }
          }
        });
        
        // Date validation for the Open Dropping Center, institute dropping center form to ensure the selected date is not in the past.
        if (['afformDroppingCenterDetailForm', 'afformInstitutionDroppingCenterIntent1'].includes(ctrl.getFormMeta().name)) {
          var dateField = $element.find("input.crm-form-date").val().trim(); 
          if (dateField !== "") {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var dateParts = dateField.split('/');
            var selectedDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            
            // Check if the selected date is in the past or today
            if (selectedDate <= today) {
              isValid = false;
              errorMessage += `The selected date (${dateField}) cannot be today or in the past.\n`;
            }
          }
        }    

        // Date validation for the Urban Planned Visit form to ensure the selected date is not in the past.
        if (['afformUrbanPlannedVisitIntentForm'].includes(ctrl.getFormMeta().name)) {
          var dateField = $element.find("input.crm-form-date").val().trim(); 
          if (dateField !== "") {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var dateParts = dateField.split('/');
            var selectedDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            
            // Check if the selected date is in the past
            if (selectedDate < today) {
              isValid = false;
              errorMessage += `The selected date (${dateField}) cannot be in the past.\n`;
            }
          }
        }

        if (ctrl.getFormMeta().name === 'afformInstitutionGoonjActivitiesIntent') {
          var dateField = $element.find("af-field[name='Institution_Goonj_Activities.Start_Date'] .crm-form-date-wrapper input.crm-form-date").val();
          if (dateField !== "") {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var dateParts = dateField.split('/');
            var selectedDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);

            // Check if the selected date is in the past
            if (selectedDate <= today) {
              isValid = false;
              errorMessage+=`The selected date (${dateField}) cannot be today or in the past.`;
            }
          }
        }
        
        // Collection camp start date and end date validation
        if (['afformCollectionCampIntentDetails', 'afformGoonjActivitiesIndividualIntentForm', 'afformInstitutionCollectionCampIntent'].includes(ctrl.getFormMeta().name)) {
          var dateFields = [
            {
              startDateField: "af-field[name='Collection_Camp_Intent_Details.Start_Date'] .crm-form-date-wrapper input.crm-form-date",
              endDateField: "af-field[name='Collection_Camp_Intent_Details.End_Date'] .crm-form-date-wrapper input.crm-form-date"
            },
            {
              startDateField: "af-field[name='Goonj_Activities.Start_Date'] .crm-form-date-wrapper input.crm-form-date",
              endDateField: "af-field[name='Goonj_Activities.End_Date'] .crm-form-date-wrapper input.crm-form-date"
            },
            {
              startDateField: "af-field[name='Institution_Collection_Camp_Intent.Collections_will_start_on_Date_'] .crm-form-date-wrapper input.crm-form-date",
              endDateField: "af-field[name='Institution_Collection_Camp_Intent.Collections_will_end_on_Date_'] .crm-form-date-wrapper input.crm-form-date"
            }
          ];
        
          var today = new Date();
          today.setHours(0, 0, 0, 0);
          
          var errorMessage = '';
          var isValid = true;
        
          dateFields.forEach(function(fields) {
            var startDateValue = $element.find(fields.startDateField).val();
            var endDateValue = $element.find(fields.endDateField).val();
            
            if (startDateValue && endDateValue) {
              var startDateParts = startDateValue.split('/');
              var endDateParts = endDateValue.split('/');
              
              var startDate = new Date(startDateParts[2], startDateParts[1] - 1, startDateParts[0]);
              var endDate = new Date(endDateParts[2], endDateParts[1] - 1, endDateParts[0]);
        
              // Check if the start date is today or in the past
              if (startDate <= today) {
                errorMessage += `Collections cannot start (${startDateValue}) today or in the past.\n`;
                isValid = false;
              }
        
              // Check if the end date is today or in the past
              if (endDate <= today) {
                errorMessage += `Collections cannot end (${endDateValue}) today or in the past.\n`;
                isValid = false;
              }
        
              // Check if the end date is before the start date
              if (endDate < startDate) {
                errorMessage += `Collections cannot end (${endDateValue}) before start (${startDateValue}).\n`;
                isValid = false;
              }
            }
          });
        }
        
        // Birth date validation
        var birthDateField = $element.find("af-field[name='birth_date'] input[type='text']");
        if (birthDateField.length) {
          var birthDateValue = birthDateField.val().trim();
          if (birthDateValue !== "") {
            var birthDateParts = birthDateValue.split('/');
            if (birthDateParts.length === 3) {
              var birthDate = new Date(birthDateParts[2], birthDateParts[1] - 1, birthDateParts[0]);
              var today = new Date();
              today.setHours(0, 0, 0, 0);
              
              if (birthDate.toDateString() === today.toDateString()) {
                errorMessage += `Date of Birth cannot be today.\n`;
                isValid = false;
              }
              
              if (birthDate > today) {
                errorMessage += `Date of Birth cannot be in the future.\n`;
                isValid = false;
              }
            } else {
              errorMessage += "Invalid Date of Birth format.\n";
              isValid = false;
            
            }
          }
        }
        
        // Postal code validation
        var postalCodeLabel = $element.find("label:contains('Postal Code')");
        var postalCodeField = postalCodeLabel.closest('af-field').find("input[type='text']");
        if (postalCodeField.length) {
          var postalCodeValue = postalCodeField.val().trim();
          if (postalCodeValue !== "") {
            var postalCodePattern = /^\d{6}$/;
            if (!postalCodePattern.test(postalCodeValue)) {
              errorMessage += "Please enter a valid 6-digit postal code.\n";
              isValid = false;
            }
          }
        }
        
        if (!isValid) {
          CRM.alert(errorMessage, ts("Form Error"));
        }
        
        return isValid;
      }

      this.submit = function() {
        if (!customValidateFields()) {
          return;
        }
        // validate required fields on the form
        if (!ctrl.ngForm.$valid || !validateFileFields()) {
          CRM.alert(ts('Please fill all required fields.'), ts('Form Error'));
          return;
        }
        status = CRM.status({});
        $element.block();
        if (cancelDraftWatcher) {
          cancelDraftWatcher();
        }

        crmApi4('Afform', 'submit', {
          name: ctrl.getFormMeta().name,
          args: args,
          values: data,
        }).then(function(response) {
          submissionResponse = response;
          if (ctrl.fileUploader.getNotUploadedItems().length) {
            _.each(ctrl.fileUploader.getNotUploadedItems(), function(file) {
              file.formData.push({
                params: JSON.stringify(_.extend({
                  token: response[0].token,
                  name: ctrl.getFormMeta().name
                }, file.crmApiParams()))
              });
            });
            ctrl.fileUploader.uploadAll();
          } else {
            postProcess();
          }
        })
        .catch(function(error) {
          status.reject();
          $element.unblock();
          CRM.alert(error.error_message || '', ts('Form Error'));
        });
      };

      this.submitDraft = function() {
        if (uploadingDraftFiles) {
          return;
        }
        setDraftStatus('saving');
        status = CRM.status({start: ts('Saving Draft'), success: ts('Draft saved')});
        crmApi4('Afform', 'submitDraft', {
          name: ctrl.getFormMeta().name,
          args: args,
          values: data,
        }).then(function(response) {
          status.resolve();
          if (ctrl.fileUploader.getNotUploadedItems().length) {
            uploadingDraftFiles = true;
            _.each(ctrl.fileUploader.getNotUploadedItems(), function(file) {
              file.formData.push({
                params: JSON.stringify(_.extend({
                  name: ctrl.getFormMeta().name
                }, file.crmApiParams()))
              });
            });
            ctrl.fileUploader.uploadAll();
          } else {
            setDraftStatus('saved');
          }
        });
      };

      function getDraftButtons() {
        return $element.find('button[ng-click="afform.submitDraft()"]');
      }

      function setDraftStatus(newStatus) {
        if (draftStatus === newStatus) {
          return;
        }
        if (draftStatus === 'unsaved' && newStatus === 'saved') {
          // If form was altered during a save operation, keep the 'unsaved' status
          return;
        }
        // Setting to 'unsaved' - restore buttons to initial state
        if (newStatus === 'unsaved' && !uploadingDraftFiles) {
          restoreDraftButtons();
        }
        // Change icon, text & disable button for 'saving' or 'saved' status
        else if (!uploadingDraftFiles) {
          const newText = newStatus === 'saving' ? ts('Saving Draft') : ts('Draft Saved');
          const newIcon = newStatus === 'saving' ? 'fa-spinner fa-spin' : 'fa-check';
          disableDraftButtons(newText, newIcon);
        }
        draftStatus = newStatus;
      }

      function disableDraftButtons(text, icon) {
        const buttons = getDraftButtons();
        $.each(buttons, function(index, button) {
          $(button).text(text).attr('disabled', true);
          $(button).prepend('<i class="crm-i ' + icon + '" aria-hidden="true"></i> ');
        });
      }

      function restoreDraftButtons() {
        const buttons = getDraftButtons();
        $.each(buttons, function(index, button) {
          const initialState = saveDraftButtons[index] || saveDraftButtons[0];
          $(button).text(initialState.text).attr('disabled', false);
          if (initialState.icon) {
            $(button).prepend('<i class="crm-i ' + saveDraftButtons[index].icon + '" aria-hidden="true"></i> ');
          }
        });
      }

    }
  });
})(angular, CRM.$, CRM._);
