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
        ts = CRM.ts('org.civicrm.afform'),
        ctrl = this;

      this.$onInit = function() {
        // This component has no template. It makes its controller available within it by adding it to the parent scope.
        $scope.$parent[this.ctrl] = this;

        $timeout(ctrl.loadData);
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
      this.getFormMeta = function getFormMeta() {
        return $scope.$parent.meta;
      };
      // With no arguments this will prefill the entire form based on url args
      // and also check if the form is open for submissions.
      // With selectedEntity, selectedIndex & selectedId provided this will prefill a single entity
      this.loadData = function(selectedEntity, selectedIndex, selectedId, selectedField) {
        let toLoad = true;
        const params = {name: ctrl.getFormMeta().name, args: {}};
        // Load single entity
        if (selectedEntity) {
          toLoad = !!selectedId;
          params.matchField = selectedField;
          params.args[selectedEntity] = {};
          params.args[selectedEntity][selectedIndex] = selectedId;
        }
        // Prefill entire form
        else {
          args = _.assign({}, $scope.$parent.routeParams || {}, $scope.$parent.options || {});
          _.each(schema, function (entity, entityName) {
            if (args[entityName] && typeof args[entityName] === 'string') {
              args[entityName] = args[entityName].split(',');
            }
          });
          params.args = args;
        }
        if (toLoad) {
          crmApi4('Afform', 'prefill', params)
            .then((result) => {
              // In some cases (noticed on Wordpress) the response header incorrectly outputs success when there's an error.
              if (result.error_message) {
                disableForm(result.error_message);
                return;
              }
              result.forEach((item) => {
                // Use _.each() because item.values could be cast as an object if array keys are not sequential
                _.each(item.values, (values, index) => {
                  data[item.name][index] = data[item.name][index] || {};
                  data[item.name][index].joins = {};
                  angular.merge(data[item.name][index], values, {fields: _.cloneDeep(schema[item.name].data || {})});
                });
              });
            }, (error) => {
              disableForm(error.error_message);
            });
        }
        // Clear existing contact selection
        else if (selectedEntity) {
          // Delete object keys without breaking object references
          Object.keys(data[selectedEntity][selectedIndex].fields).forEach(key => delete data[selectedEntity][selectedIndex].fields[key]);
          // Fill pre-set values
          angular.merge(data[selectedEntity][selectedIndex].fields, _.cloneDeep(schema[selectedEntity].data || {}));
          data[selectedEntity][selectedIndex].joins = {};
        }

        ctrl.showSubmitButton = displaySubmitButton(args);
      };

      function displaySubmitButton(args) {
        if (args.sid && args.sid.length > 0) {
          return false;
        }
        return true;
      }

      // Used when submitting file fields
      this.fileUploader = new FileUploader({
        url: CRM.url('civicrm/ajax/api4/Afform/submitFile'),
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        onCompleteAll: postProcess,
        onBeforeUploadItem: function(item) {
          status.resolve();
          status = CRM.status({start: ts('Uploading %1', {1: item.file.name})});
        }
      });

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

        else if (metaData.redirect) {
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
          .addClass("disabled")
          .find('button[ng-click="afform.submit()"]')
          .prop("disabled", true);
        CRM.alert(errorMsg, ts("Sorry"), "error");
      }
      // NOTE: This function currently provides basic validation for email, phone number, and postal code fields.
      // For now, we have implemented these simple checks to meet current project requirements.
      // In the future, we need to change this.
      function customValidateFields() {
        var isValid = true;
        var errorMessage = "";

        // Email validation
        var emailField = $element.find("input[type='email']");
        if (emailField.length) {
            var emailValue = emailField.val().trim();
            if (emailValue !== "") {
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailValue)) {
                    errorMessage += "Please enter a valid email.\n";
                    isValid = false;
                }
            }
          }
          
        // Phone number validation
        var phoneNumberField = $element.find("af-field[name='phone'] input[type='text'], af-field[name='Material_Contribution.Delivered_By_Contact'] input[type='text']");
        if (phoneNumberField.length) {
            var phoneNumberValue = phoneNumberField.val().trim();
            if (phoneNumberValue !== "") {
                var phonePattern = /^\d{10}$/;
                if (!phonePattern.test(phoneNumberValue)) {
                    errorMessage += "Please enter a valid 10-digit mobile number.\n";
                    isValid = false;
                }
            }
        }

    // Collection camp start date and end date validation
    var startDateValue = $element.find("af-field[name='Collection_Camp_Intent_Details.Start_Date'] .crm-form-date-wrapper input.crm-form-date").val();
    var endDateValue = $element.find("af-field[name='Collection_Camp_Intent_Details.End_Date'] .crm-form-date-wrapper input.crm-form-date").val();
    
    if (startDateValue && endDateValue) {
        var today = new Date();
        today.setHours(0, 0, 0, 0); 
        
        var startDateParts = startDateValue.split('/');
        var endDateParts = endDateValue.split('/');
        
        var startDate = new Date(startDateParts[2], startDateParts[1] - 1, startDateParts[0]);
        var endDate = new Date(endDateParts[2], endDateParts[1] - 1, endDateParts[0]);
        
        var errorMessage = '';
        var isValid = true;

        // Check if the start date is in the past or today
        if (startDate <= today) {
          errorMessage += `Collections cannot start (${startDateValue}) today or in the past.\n`;
          isValid = false;
        }
        // Check if the end date is in the past, today
        if (endDate <= today) {
          errorMessage += `Collections cannot end ( ${endDateValue}) today or in the past.\n`;
          isValid = false;
          }
          
        // Check if End Date is before Start Date
        if (endDate < startDate) {
          errorMessage += `Collections cannot end (${endDateValue}) before start (${startDateValue}).\n`;
          isValid = false;
        }
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
    
      this.submit = function () {
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

        crmApi4('Afform', 'submit', {
          name: ctrl.getFormMeta().name,
          args: args,
          values: data}
        ).then(function(response) {
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
    }
  });
})(angular, CRM.$, CRM._);
