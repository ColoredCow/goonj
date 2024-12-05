### Custom Changes in CiviCRM Plugin

Modified File: `wp-content/plugins/civicrm/civicrm/ext/afform/core/ang/af/afForm.component.js`

PR: [Field Validation](https://github.com/ColoredCow/goonj/pull/129).

NOTE: Custom field validation has been added to meet project requirements and priorities. This change should be revisited and replaced with a more robust solution in the future.

Modified File2: `wp-content/plugins/civicrm/civicrm/templates/CRM/Contribute/Form/Contribution/ThankYou.tpl`

PR: [Remove frequency for amount from thankyou page](https://github.com/ColoredCow/goonj/pull/442).

# Action Required Before Upgrading CiviCRM Plugin

Action Required: During the CiviCRM plugin upgrade, make sure to preserve or re-implement the custom validation changes. Failure to do so will result in the loss of essential validation features.

Recommended Steps: Backup the Modified File: Before upgrading, make sure to create a backup of afForm.component.js that includes our custom changes.

Review the Upgraded Plugin: After upgrading, review the `afForm.component.js` file in the updated plugin to check for any conflicts or overwrites.

Re-apply Changes: If necessary, re-apply the custom validation logic to the updated file.

Test the Changes: Thoroughly test the form functionality after re-applying the custom changes to ensure everything works as expected.
