<?php

/**
 * @file
 * CLI Script to assign Office Visit activities via CSV in CiviCRM.
 */

use Civi\Api4\Phone;
use Civi\Api4\Email;
use Civi\Api4\Activity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Get CSV path from wp-config.php constant.
$csvFilePath = OFFICE_VISIT_CSV_FILE_PATH;

if (!$csvFilePath || !file_exists($csvFilePath)) {
  exit("❌ Error: OFFICE_VISIT_CSV_FILE_PATH not set or file does not exist.\n");
}

echo "📄 CSV File: $csvFilePath\n";

/**
 * Read and parse CSV.
 */
function readContactsFromCsv(string $filePath): array {
  $contacts = [];

  if (($handle = fopen($filePath, 'r')) !== false) {
    $header = fgetcsv($handle, 1000, ',');
    $requiredCols = [
      'email', 'phone', 'description_of_material', 'delivered_by',
      'delivered_by_contact', 'goonj_office', 'visit_date'
    ];

    foreach ($requiredCols as $col) {
      if (!in_array($col, $header)) {
        throw new Exception("Missing required column: $col");
      }
    }

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
      $data = array_combine($header, $row);

      $contacts[] = [
        'email' => trim($data['email']),
        'phone' => trim($data['phone']),
        'description_of_material' => trim($data['description_of_material']),
        'delivered_by' => trim($data['delivered_by']),
        'delivered_by_contact' => trim($data['delivered_by_contact']),
        'goonj_office' => trim($data['goonj_office']),
        'visit_date' => trim($data['visit_date']),
      ];
    }

    fclose($handle);
  }

  return $contacts;
}

/**
 * Assign Office Visit activity.
 */
function assignOfficeVisitActivity(array $data): void {
  try {
    $contactId = null;

    // Lookup contact by email
    if (!empty($data['email'])) {
      $result = Email::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('email', '=', $data['email'])
        ->execute()
        ->first();
      $contactId = $result['contact_id'] ?? null;
    }

    // Fallback to phone
    if (!$contactId && !empty($data['phone'])) {
      $result = Phone::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('phone', '=', $data['phone'])
        ->execute()
        ->first();
      $contactId = $result['contact_id'] ?? null;
    }

    if (!$contactId) {
      echo "❌ No contact found for email: {$data['email']} or phone: {$data['phone']}\n";
      return;
    }

    // Create Office Visit activity
    Activity::create(FALSE)
      ->addValue('activity_type_id:name', 'Office visit')
      ->addValue('status_id:name', 'Completed')
      ->addValue('activity_date_time', $data['visit_date'])
      ->addValue('source_contact_id', $contactId)
      ->addValue('subject', $data['description_of_material'])
      ->addValue('Material_Contribution.Contribution_Date', $data['visit_date'])
      ->addValue('Material_Contribution.Delivered_By', $data['delivered_by'])
      ->addValue('Material_Contribution.Delivered_By_Contact', $data['delivered_by_contact'] ?: null)
      ->addValue('Material_Contribution.Goonj_Office', $data['goonj_office'] ?: null)
      ->execute();

    echo "✅ Office Visit assigned for Contact ID $contactId\n";

  } catch (Exception $e) {
    echo "❌ Error assigning visit for {$data['email']}: " . $e->getMessage() . "\n";
  }
}

/**
 * Main runner.
 */
function main(): void {
  try {
    echo "🚀 Starting Office Visit Import...\n";
    $contacts = readContactsFromCsv(OFFICE_VISIT_CSV_FILE_PATH);

    if (empty($contacts)) {
      echo "⚠️ No records found in CSV.\n";
      return;
    }

    foreach ($contacts as $data) {
      assignOfficeVisitActivity($data);
    }

    echo "✅ All Office Visits processed.\n";

  } catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
  }
}

main();