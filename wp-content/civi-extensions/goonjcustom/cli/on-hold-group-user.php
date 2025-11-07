<?php

/**
 * @file
 * CLI Script to set hard-bounce (on-hold) status for all contacts in a given group.
 */

// Sudo cv scr /var/www/html/wp-content/civi-extensions/goonjcustom/cli/on-hold-group-user.php.
use Civi\Api4\Email;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Fetch Group ID from constants defined in wp-config.php.
$groupId = 128;

// Check if the required constant is set.
if (!$groupId) {
  exit("Error: ON_HOLD_GROUP_ID constant must be set.\n");
}

echo "=== Starting On-Hold Process for Group ID: $groupId ===\n";

/**
 * Get all contacts from a specific group.
 *
 * @param int $groupId
 *
 * @return array
 *   Array of contact IDs.
 */
function getContactsFromGroup(int $groupId): array {
  $contacts = [];

  try {
    $result = GroupContact::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('group_id', '=', $groupId)
      ->addWhere('status', '=', 'Added')
      ->execute();

    foreach ($result as $row) {
      $contacts[] = $row['contact_id'];
    }
  }
  catch (Exception $e) {
    echo "Failed to fetch contacts from group $groupId: " . $e->getMessage() . "\n";
  }

  return $contacts;
}

/**
 * Mark a contact’s email(s) as on hold and tag them as “Bounced_Cleanups_Required”.
 *
 * @param int $contactId
 */
function setContactOnHold(int $contactId): void {
  try {
    // Get all emails for this contact.
    $emails = Email::get(FALSE)
      ->addSelect('id', 'email', 'on_hold')
      ->addWhere('contact_id', '=', $contactId)
      ->execute();

    if ($emails->count() === 0) {
      echo "No emails found for contact ID $contactId.\n";
      return;
    }

    foreach ($emails as $emailRow) {
      try {
        // Set email on hold.
        Email::update(FALSE)
          ->addValue('on_hold', 1)
          ->addWhere('id', '=', $emailRow['id'])
          ->execute();

        echo "Set on-hold for email {$emailRow['email']} (Contact ID: $contactId)\n";
      }
      catch (Exception $e) {
        echo "Failed to set email on hold for contact $contactId: " . $e->getMessage() . "\n";
      }
    }

  }
  catch (Exception $e) {
    echo "Error processing contact $contactId: " . $e->getMessage() . "\n";
  }
}

/**
 * Main function to fetch group contacts and mark them as on hold.
 */
function main(int $groupId): void {
  $contacts = getContactsFromGroup($groupId);

  if (empty($contacts)) {
    echo "No contacts found in group $groupId.\n";
    return;
  }

  echo "Processing " . count($contacts) . " contacts...\n";

  foreach ($contacts as $contactId) {
    setContactOnHold($contactId);
  }

  echo "=== On-Hold Process Completed ===\n";
}

// Run the main function.
main($groupId);
