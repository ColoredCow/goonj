document.addEventListener("DOMContentLoaded", function () {
  setTimeout(function () {
    var container = document.querySelector("#bootstrap-theme > crm-angular-js");
    var submitButton = container
      ? container.querySelector('button[ng-click="afform.submit()"]')
      : null;

    if (container && submitButton) {
      submitButton.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var angularElement = angular.element(container);
        var scope = angularElement.scope();
        if (scope) {
          var emailField = container.querySelector("input[type='email']");
          var phoneNumberField = container.querySelector(
            "af-field[name='phone'] input[type='text']"
          );
          var postalCodeField = container.querySelector(
            "af-field[name='postal_code'] input[type='text']"
          );

          var isValid = true;
          var errorMessage = "";

          // Email validation
          if (emailField) {
            var emailValue = emailField.value;
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailValue)) {
              errorMessage +=
                "Invalid email address. Please enter a valid email.\n";
              isValid = false;
            }
          }

          // Phone number validation
          if (phoneNumberField) {
            var phoneNumberValue = phoneNumberField.value;
            var phonePattern = /^\d{10}$/;
            if (!phonePattern.test(phoneNumberValue)) {
              errorMessage += "Please enter a valid 10-digit mobile number.\n";
              isValid = false;
            }
          }

          // Postal code validation
          if (postalCodeField) {
            var postalCodeValue = postalCodeField.value;
            var postalCodePattern = /^\d{6}$/;
            if (!postalCodePattern.test(postalCodeValue)) {
              errorMessage += "Please enter a valid 6-digit postal code.";
              isValid = false;
            }
          }

          if (!isValid) {
            CRM.alert(errorMessage, "Validation Error");
            return;
          }

          scope.$apply(function () {
            scope.afform.submit();
          });
        }
      });
    }
  }, 1000);
});
