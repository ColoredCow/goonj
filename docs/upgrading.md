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

### 3. Modification on receipt email template
We made modifications to the mail templates in CiviCRM → Mailings → Message Template → System Workflow Message. Specifically, we updated the following three templates:

- Contributions – Receipt (off-line)
- Contributions – Receipt (on-line)
- Contributions – Invoice

**Document:**
The custom code for the message templates is saved in the following files:

- [**Contributions – Receipt (off-line)**](https://docs.google.com/document/d/16DVrSJIr53f1RxnMwOlh9ArQv9TSU8e0xr_6qx_ipMQ/edit?tab=t.0#bookmark=id.32pvoqqsyxro)  
- [**Contributions – Receipt (on-line)**](https://docs.google.com/document/d/16DVrSJIr53f1RxnMwOlh9ArQv9TSU8e0xr_6qx_ipMQ/edit?tab=t.0#bookmark=id.1hltgupi60d0)  
- [**Contributions – Invoice**](https://docs.google.com/document/d/16DVrSJIr53f1RxnMwOlh9ArQv9TSU8e0xr_6qx_ipMQ/edit?tab=t.0#bookmark=id.xurmsqnh8ecl)  

### 4. Modification on event email template
We made modifications to the mail templates in CiviCRM → Mailings → Message Template → System Workflow Message. Specifically, we updated the following templates for event flow:

- Events - Pending Registration Expiration Notice
- Events - Registration Cancellation Notice
- Events - Registration Confirmation and Receipt (off-line)
- Events - Registration Confirmation and Receipt (on-line)
- Events - Registration Confirmation Invite
- Events - Registration Transferred Notice

**Document:**
The custom code for the message templates is saved in the following files:

- [**Events - Pending Registration Expiration Notice)**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.k7nbfbm8lkdz)  
- [**Events - Registration Cancellation Notice**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.bt36cqqw775o)  
- [**Events - Registration Confirmation and Receipt (off-line)**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.8yxtwd1zqc69)
- [**Events - Registration Confirmation and Receipt (on-line)**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.h6sq2aby2n6s)  
- [**Events - Registration Confirmation Invite**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.o28wm8hjzxp6)  
- [**Events - Registration Transferred Notice**](https://docs.google.com/document/d/1671sv0ImNDeij4JLrSQwOpnPu_zXkYbc077JfmNnDHI/edit?tab=t.0#bookmark=id.djrw4g5ykid6)   

**Note:**
The custom changes made to the receipt templates were implemented to meet specific project requirements and priorities. When updating CiviCRM, we need to revisit these templates and apply the necessary modifications accordingly.
