<?php

/**
 * @file
 * CLI Script to set hard bounces users to on hold via CSV in CiviCRM.
 */

use Civi\Api4\Email;
use Civi\Api4\Contact;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Fetch the CSV file path and Group ID from the constants defined in wp-config.php.
$csvFilePath = CSV_FILE_PATH;
$groupId = GROUP_ID;

// Check if the required constants are set.
if (!$csvFilePath || !$groupId) {
  exit("Error: Both CSV_FILE_PATH and GROUP_ID constants must be set.\n");
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
      ->execute()->single();

    // If email is found, process the contact.
    if (isset($result['contact_id'])) {
      $contactId = $result['contact_id'];

    //   // Opt out the contact.
    //   Contact::update(FALSE)
    //     ->addWhere('id', '=', $contactId)
    //     ->addValue('is_opt_out', TRUE)
    //     ->execute();

      // Add the contact to the specified group.
      GroupContact::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('group_id', GROUP_ID)
        ->addValue('status', 'Added')
        ->execute();

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
    $emails = readContactsFromCsv(CSV_FILE_PATH);

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