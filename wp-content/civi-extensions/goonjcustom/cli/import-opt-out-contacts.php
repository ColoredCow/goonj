<?php

/**
 * @file
 * CLI Script to Opt Out Contacts and Add to Group via CSV in CiviCRM.
 *
 * Usage:
 *   cv php:script opt-out-contacts.php.
 */

use Civi\Api4\Email;
use Civi\Api4\Contact;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Configuration.
// Replace with your CSV file path.
define('CSV_FILE_PATH', '/Users/tarunjoshi/Downloads/Opted out List - Pardot (Contact listing) - civicrm_contribution (5).csv');
// Replace with the ID of the group to add contacts to.
define('GROUP_ID', 69);

/**
 * Reads email addresses from the provided CSV file.
 *
 * @param string $filePath Path to the CSV file.
 *
 * @return array List of email addresses.
 *
 * @throws Exception If the file is not readable or the 'email' column is missing.
 */

/**
 * In the readContactsFromCsv function, you can check for 'Do Not Email' column value.
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

        // Check if the 'Do Not Email' column indicates opt-out.
        if (isset($row['Do Not Email (Opt out contact)']) &&
              strtolower($row['Do Not Email (Opt out contact)']) == 'yes') {

          // Clean email by trimming any extra spaces.
          $email = trim($row['email']);
          $contacts[] = $email;
        }
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
function optOutContactByEmail(string $email): void {
  try {
    // Log the email being processed for debugging.
    error_log("Processing email: $email");

    // Find contact by email with case-insensitive search.
    $result = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()->single();

    error_log("Result: " . print_r($result, TRUE));

    // If email is found, process the contact.
    $contactId = $result['contact_id'];

    // Optionally, log the contact ID.
    error_log("Contact found with ID: $contactId");

    // Opt out the contact (update the contact status or attributes)
    Contact::update(FALSE)
      ->addWhere('id', '=', $contactId)
      ->addValue('is_opt_out', TRUE)
      ->execute();

    // Add the contact to the group.
    GroupContact::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('group_id', GROUP_ID)
      ->addValue('status', 'Added')
      ->execute();
    error_log("Successfully opted out contact with email $email (ID $contactId) and added to group.");
  }
  catch (Exception $e) {
    error_log("Error processing email $email: " . $e->getMessage());
  }
}

/**
 * Main function to process the CSV and update contacts.
 */
function main(): void {
  try {
    echo "=== Starting Opt-Out Process ===\n";
    $emails = readContactsFromCsv(CSV_FILE_PATH);
    // Log all emails read from CSV.
    error_log("All emails to process: " . print_r($emails, TRUE));

    if (empty($emails)) {
      echo "No emails to process.\n";
      return;
    }

    foreach ($emails as $email) {
      optOutContactByEmail($email);
    }
    echo "=== Opt-Out Process Completed ===\n";
  }
  catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
  }
}

// Run the main function.
main();
