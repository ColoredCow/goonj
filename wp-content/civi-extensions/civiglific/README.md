# CiviCRM Glific Integration

Integrates Glific with CiviCRM, enabling you to send messages to users directly from CiviCRM and sync your group contacts between CiviCRM and Glific.

Latest releases can be found in the [CiviCRM extensions directory](https://lab.civicrm.org/dashboard/projects/personal)

## Documentation

### Installation

1. Download the extension code
2. Put it in your CiviCRM extension directory
3. Go to `CiviCRM > Administer > System Settings > Extensions`
4. Find `civiglific` in the extension list and enable it
5. Optionally, you can also use the `cv` command-line tool to perform steps 3 and 4

## Setup

There are two main setup configurations you can perform with this extension:
1. **Migrating CiviCRM contact groups to Glific groups for messaging**
2. **Sending receipts and messages to contributors through WhatsApp**

### Setup 1: Migrate CiviCRM Group Contacts to Glific Groups

1. **Configure Glific credentials in CiviCRM Settings UI**

   Go to:

   ```
   Administer → System Settings → Civiglific Settings
   ```

   Add the following values:

   * Glific Phone Number
   * Glific Password
   * Glific API Base URL

2. **Add a navigation menu entry** in CiviCRM to access the Group Mapping screen. You can use the following URL:
   ```
   civicrm/group-mapping
   ```
3. **Open the “Rule Mapping” page** from the navigation menu. Here, you can map your **CiviCRM contact groups** to their corresponding **Glific groups** to enable synchronized messaging.

4. Add cron job to automatically migrate the contacts
``` php
Civiglific.civicrm_glific_contact_sync_cron
```

### Setup 2: Send Receipts or Messages to Monetary Contributors

1. **Configure Template IDs and PDF paths in CiviCRM Settings UI**

   Go to:

   ```
   Administer → System Settings → Civiglific Settings
   ```

   Configure:

   * Default Glific Template ID
   * Team 5000 Template ID
   * Persistent PDF Path (private storage path)
   * Saved PDF Path (public access path)

2. **Create a custom field** (Alphanumeric, Radio Buttons type) for **Contributions** to identify which contributors should receive WhatsApp messages or receipts.
3. Create message template on glific end, fetch those id and added it to step 1.
4. **Send messages or receipts automatically:** When submitting a contribution form, simply select the configured field, the message or receipt will be automatically sent to the contributor’s WhatsApp number using the Glific integration.


## Usage

Once the extension is installed and configured:

1. **Migrate Contacts from CiviCRM to Glific**
   - Go to the navigation menu that was added during setup.
   - Select the CiviCRM group you want to migrate.
   - Choose the corresponding Glific group where you want to merge the CiviCRM contacts.
   - That’s it! Once selected, the cron job will automatically merge and sync those contacts from CiviCRM to Glific.

2. **Automated Message Sending**
   - When a new contribution is recorded in CiviCRM and the configured custom field is selected, an automated WhatsApp message or receipt will be sent to the contributor using the linked Glific template.


### 4. PDF Receipts

* If persistent PDF paths are configured in **Civiglific Settings**,
  a copy of the sent receipt PDF will be stored for each transaction.

## Maintainers

Crafted by [ColoredCow](https://github.com/coloredcow/). For support or contributions, please submit issues or pull requests.

