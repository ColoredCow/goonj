### Custom Changes in CiviCRM Plugin

#### 1. Custom Field Validation

**Modified File:** `wp-content/plugins/civicrm/civicrm/ext/afform/core/ang/af/afForm.component.js`

**PR:** [Field Validation](https://github.com/ColoredCow/goonj/pull/129)

**Note:** Custom field validation has been added to meet project requirements and priorities. This change should be revisited and replaced with a more robust solution in the future.

### Action Required Before Upgrading CiviCRM Plugin

1. **Backup the Modified File:** Ensure you have a backup of `afForm.component.js` and `qrcodecheckin.js` that includes custom changes.

2. **Review the Upgraded Plugin:** After upgrading, check for any conflicts or overwrites in the `afForm.component.js` and `qrcodecheckin.js` files.

3. **Re-apply Changes:** If necessary, manually re-apply the custom logic to the updated files.

4. **Test the Changes:** Thoroughly test the QR code check-in functionality and form validations to ensure everything works as expected after the re-application of changes.


#### 2. Changes in QR Code Extension for Event Volunteer Attendance Marking

**Modified File:** `wp-content/plugins/civicrm/civicrm/ext/afform/core/ang/qrcodecheckin.js`

- Replaced `window.location.pathname` with `window.location.href` for fetching the URL.
- Used `decodeURIComponent()` to decode the URL instead of the previous string replacement method.
  ```javascript
  var path_name = window.location.href;
  var sanitized_path_name = decodeURIComponent(path_name);
  ```
- API calls now use `CRM.api4()` instead of `CRM.api3()` for better performance and maintainability.
  ```javascript
  CRM.api4('Participant', 'update', {
    values: { "status_id": 2 },
    where: [["id", "=", participant_id]],
  }).then(function(results) {
    console.log(results);
    if (results) {
      CRM.$('#qrcheckin-status').html('Attended');
      CRM.$('#qrcheckin-status-line')
        .removeClass("qrcheckin-status-registered")
        .addClass("qrcheckin-status-attended");
      CRM.$('#qrcheckin-update-button').hide();
    }
  }, function(failure) {
    console.error("API request failed:", failure);
  });
  ```

