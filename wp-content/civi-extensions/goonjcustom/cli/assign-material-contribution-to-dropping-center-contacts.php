<?php

/**
 * @file
 * CLI Script to assign material contribution via CSV in CiviCRM.
 */

use Civi\Api4\Phone;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Activity;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Fetch the CSV file path and Group ID from wp-config.php.
$csvFilePath = MATERIAL_CONTRIBUTION_CSV_FILE_PATH_DROPPING_CENTER;

// Check if constants are set.
if (!$csvFilePath) {
  exit("Error: MATERIAL_CONTRIBUTION_CSV_FILE_PATH_DROPPING_CENTER must be set.\n");
}

echo "CSV File: $csvFilePath\n";

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
    if (!in_array('email', $header) || !in_array('dropping_center', $header) || !in_array('description_of_material', $header) || !in_array('phone', $header) || !in_array('contribution_date', $header)) {
      throw new Exception("Column missing in CSV.");
    }

    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
      $row = array_combine($header, $data);

      // Clean values.
      $email = trim($row['email']);
      $droppingCenterCode = trim($row['dropping_center']);
      $descriptionOfMaterial = trim($row['description_of_material']);
      $phone = trim($row['phone']);
      $contributionDate = trim($row['contribution_date']);

      $contacts[] = [
        'email' => $email,
        'dropping_center' => $droppingCenterCode,
        'description_of_material' => $descriptionOfMaterial,
        'phone' => $phone,
        'contribution_date' => $contributionDate,
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
function assignContributionByEmail(string $email, string $droppingCenterCode, string $descriptionOfMaterial, string $phone, string $contributionDate): void {
  try {

    if (empty($email) && empty($phone)) {
      echo "No email and phone found in database.\n";
      return;
    }

    if ($email) {
      // Find contact using Email API.
      $result = Email::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('email', '=', $email)
        ->execute()
        ->first();
    }
    else {
      // Find contact using Phone API.
      $result = Phone::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('phone', '=', $phone)
        ->execute()
        ->first();
    }

    if (isset($result['contact_id'])) {
      $contactId = $result['contact_id'];

      $droppingCenter = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $droppingCenterCode)
        ->addWhere('subtype:name', '=', 'Dropping_Center')
        ->execute()
        ->first();

      if (!$droppingCenter) {
      echo "Camp with title $droppingCenterCode not found.\n";
        return;
      }

      $droppingCenterId = $droppingCenter['id'];

      // Convert date from m/d/Y to Y-m-d H:i:s.
      $dateTime = DateTime::createFromFormat('m/d/Y', $contributionDate);
      $formattedContributionDate = $dateTime ? $dateTime->format('Y-m-d H:i:s') : NULL;

      // Assign material contribution.
      processContribution($contactId, $formattedContributionDate, $droppingCenterId, $descriptionOfMaterial);
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
function processContribution(int $contactId, string $formattedContributionDate, string $droppingCenterId, string $descriptionOfMaterial): void {
  try {
    $results = Activity::create(FALSE)
      ->addValue('subject', $descriptionOfMaterial)
      ->addValue('activity_type_id:name', 'Material Contribution')
      ->addValue('status_id:name', 'Completed')
      ->addValue('activity_date_time', $formattedContributionDate)
      ->addValue('source_contact_id', $contactId)
      ->addValue('Material_Contribution.Dropping_Center', $droppingCenterId)
      ->execute();
    echo "Assigning contribution for Contact ID $contactId\n and is assigned to $droppingCenterId\n";
  }
  catch (\CiviCRM_API4_Exception $ex) {
    \Civi::log()->debug("Exception while creating material contribution activity: " . $ex->getMessage());
    echo "Assigning contribution for Contact ID $contactId\nIs not assigned to $droppingCenterId\n";
  }
}

/**
 * Main function to process the CSV and update contacts.
 */
function main(): void {
  try {
    echo "=== Starting Material Contribution Assignment ===\n";
    $contacts = readContactsFromCsv(MATERIAL_CONTRIBUTION_CSV_FILE_PATH_DROPPING_CENTER);

    if (empty($contacts)) {
      echo "No valid contacts found in CSV.\n";
      return;
    }

    foreach ($contacts as $contact) {
      assignContributionByEmail($contact['email'], $contact['dropping_center'], $contact['description_of_material'], $contact['phone'], $contact['contribution_date']);
    }

    echo "=== Material Contribution Assignment Completed ===\n";
  }
  catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
  }
}

// Run the main function.
main();
