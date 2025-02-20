<?php

/**
 * @file
 * CLI Script to assign material contribution via CSV in CiviCRM.
 */

use Civi\Api4\Email;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Fetch the CSV file path and Group ID from wp-config.php.
$csvFilePath = MATERIAL_CONTRIBUTION_CSV_FILE_PATH;
$groupId = MATERIAL_CONTRIBUTION_GROUP_ID;

// Check if constants are set.
if (!$csvFilePath || !$groupId) {
  exit("Error: Both MATERIAL_CONTRIBUTION_CSV_FILE_PATH and MATERIAL_CONTRIBUTION_GROUP_ID must be set.\n");
}

echo "CSV File: $csvFilePath\n";
echo "Group ID: $groupId\n";

/**
 * Reads email addresses and contribution dates from CSV.
 *
 * @param string $filePath
 *   Path to the CSV file.
 *
 * @return array List of contacts with 'email' and 'contribution_date'.
 *
 * @throws Exception If file is not readable or required columns are missing.
 */
function readContactsFromCsv(string $filePath): array {
  if (!file_exists($filePath) || !is_readable($filePath)) {
    throw new Exception("CSV file not found or not readable: $filePath");
  }

  $contacts = [];
  if (($handle = fopen($filePath, 'r')) !== FALSE) {
    $header = fgetcsv($handle, 1000, ',');
    if (!in_array('email', $header) || !in_array('activity_contribution_date', $header)) {
      throw new Exception("Error: 'email' or 'activity_contribution_date' column missing in CSV.");
    }

    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
      $row = array_combine($header, $data);

      // Clean values.
      $email = trim($row['email']);
      $contributionDate = trim($row['activity_contribution_date']);
      $goonjOffice = trim($row['goonj_office']);

      $contacts[] = [
        'email' => $email,
        'contribution_date' => $contributionDate,
        'goonj_office' => $goonjOffice,
      ];
    }
    fclose($handle);
  }

  return $contacts;
}

/**
 * Assigns material contribution by email.
 *
 * @param string $email
 * @param string $contributionDate
 */
function assignContributionByEmail(string $email, string $contributionDate, string $goonjOffice): void {
  try {

    if (empty($email)) {
      echo "No email found in database.\n";
      return;
    }
    // Find contact using Email API.
    $result = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()
      ->first();

    if (isset($result['contact_id'])) {
      $contactId = $result['contact_id'];

      // Assign material contribution.
      processContribution($contactId, $contributionDate);
    }
    else {
      echo "Contact with email $email not found.\n";
    }
  }
  catch (Exception $e) {
    echo "Error processing email $email: " . $e->getMessage() . "\n";
  }
}

/**
 * Processes the contribution assignment.
 *
 * @param int $contactId
 * @param string $contributionDate
 */
function processContribution(int $contactId, string $contributionDate): void {
  echo "Assigning contribution for Contact ID $contactId on $contributionDate.\n";
  // Add logic here to store or process the contribution in CiviCRM.
}

/**
 * Main function to process the CSV and update contacts.
 */
function main(): void {
  try {
    echo "=== Starting Material Contribution Assignment ===\n";
    $contacts = readContactsFromCsv(MATERIAL_CONTRIBUTION_CSV_FILE_PATH);

    if (empty($contacts)) {
      echo "No valid contacts found in CSV.\n";
      return;
    }

    foreach ($contacts as $contact) {
      assignContributionByEmail($contact['email'], $contact['contribution_date'], $contact['goonj_office']);
    }

    echo "=== Material Contribution Assignment Completed ===\n";
  }
  catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
  }
}

// Run the main function.
main();
