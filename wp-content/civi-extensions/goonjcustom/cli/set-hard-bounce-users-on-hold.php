<?php

/**
 * @file
 * CLI Script to set hard bounces users to on hold via CSV in CiviCRM.
 */

use Civi\Api4\EntityTag;
use Civi\Api4\Email;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Fetch the CSV file path and Group ID from the constants defined in wp-config.php.
$csvFilePath = ON_HOLD_CSV_FILE_PATH;
$groupId = ON_HOLD_GROUP_ID;

// Check if the required constants are set.
if (!$csvFilePath || !$groupId) {
  exit("Error: Both ON_HOLD_CSV_FILE_PATH and GROUP_ID constants must be set.\n");
}

echo "CSV File: $csvFilePath\n";
echo "Group ID: $groupId\n";

/**
 * Reads email addresses from the provided CSV file.
 *
 * @param string $filePath
 *   Path to the CSV file.
 *
 * @return array List of email addresses.
 *
 * @throws Exception If the file is not readable or the 'email' column is missing.
 */
function readContactsFromCsv(string $filePath): array {
  if (!file_exists($filePath) || !is_readable($filePath)) {
    throw new Exception("CSV file not found or not readable: $filePath");
  }

  $contacts = [];
  if (($handle = fopen($filePath, 'r')) !== FALSE) {
    $header = fgetcsv($handle, 1000, ',');
    if (in_array('email', $header)) {
      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row = array_combine($header, $data);

        // Clean email by trimming any extra spaces.
        $email = trim($row['email']);
        $contacts[] = $email;
      }
    }
    else {
      throw new Exception("Error: 'email' column not found in CSV.");
    }
    fclose($handle);
  }

  return $contacts;
}

/**
 * Improved query to ensure case-insensitive matching.
 */
function onHoldContactByEmail(string $email): void {
  try {
    // Find contact using Email API.
    $result = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()->first();

    // If email is found, process the contact.
    if (isset($result['contact_id'])) {
      $contactId = $result['contact_id'];

      try {
        // Update the email to on hold.
        $updateEmailToOnHold = Email::update(FALSE)
          ->addValue('on_hold:name', 1)
          ->addWhere('contact_id', '=', $contactId)
          ->execute();
      }
      catch (Exception $e) {
        echo "Failed to update email to 'On Hold Bounce': " . $e->getMessage() . "\n";
      }

      try {
        // Add the tag 'Hard_Bounce' to the contact.
        $addTag = EntityTag::create(FALSE)
          ->addValue('entity_id', $contactId)
          ->addValue('entity_table', 'civicrm_contact')
          ->addValue('tag_id.name', 'Bounced_Cleanups_Required')
          ->execute();
      }
      catch (Exception $e) {
        echo "Failed to add 'Hard_Bounce' tag: " . $e->getMessage() . "\n";
      }

      try {
        // Add the contact to the specified group.
        $addToGroup = GroupContact::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('group_id', ON_HOLD_GROUP_ID)
          ->addValue('status', 'Added')
          ->execute();
      }
      catch (Exception $e) {
        echo "Failed to add contact to group: " . $e->getMessage() . "\n";
      }

      echo "Successfully onHold contact with email $email (ID $contactId) and added to group.\n";
    }
    else {
      echo "Contact with email $email not found.\n";
    }
  }
  catch (Exception $e) {
    echo "An error occurred while processing email $email: " . $e->getMessage() . "\n";
  }
}

/**
 * Main function to process the CSV and update contacts.
 */
function main(): void {
  try {
    echo "=== Starting onHold Process ===\n";
    $emails = readContactsFromCsv(ON_HOLD_CSV_FILE_PATH);

    if (empty($emails)) {
      return;
    }

    foreach ($emails as $email) {
      onHoldContactByEmail($email);
    }
    echo "=== onHold Process Completed ===\n";
  }
  catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
  }
}

// Run the main function.
main();
