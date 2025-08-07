<?php

use Civi\Api4\Phone;
use Civi\Api4\Email;
use Civi\Api4\Activity;
use Civi\Api4\EckEntity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('MATERIAL_CONTRIBUTION_CSV_FILE_PATH', '/Users/shubhambelwal/Sites/goonj/wp-content/civi-extensions/goonjcustom/cli/Untitled spreadsheet - Sheet1 (4).csv'); // ✅ Update path

function readContactsFromCsv(string $filePath): array {
  if (!file_exists($filePath) || !is_readable($filePath)) {
    throw new Exception("CSV file not found or not readable: $filePath");
  }

  $contacts = [];
  if (($handle = fopen($filePath, 'r')) !== FALSE) {
    $header = fgetcsv($handle, 1000, ',');
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
      $row = array_combine($header, $data);
      $contacts[] = [
        'first_name' => trim($row['First Name']),
        'last_name' => trim($row['Last Name']),
        'email' => trim($row['Email']),
        'mobile' => trim($row['Mobile']),
        'city' => trim($row['City']),
        'state' => trim($row['State']),
        'camp_code' => trim($row['Camp Code']),
        'contribution_date' => formatDate($row['Contribution Date (DD-MM-YYYY)']),
        'description_of_material' => $row['Type'], // Assuming "Type" is material description
      ];
    }
    fclose($handle);
  }

  return $contacts;
}

function formatDate($date): string {
  $d = DateTime::createFromFormat('d-m-Y', trim($date));
  return $d ? $d->format('Y-m-d') : date('Y-m-d');
}

function findContactId($email, $mobile) {
  if ($email) {
    $contact = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()->first();
    if ($contact) return $contact['contact_id'];
  }

  if ($mobile) {
    $contact = Phone::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('phone', '=', $mobile)
      ->execute()->first();
    if ($contact) return $contact['contact_id'];
  }

  return null;
}

function getCollectionCampId($campCode) {
  $camp = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('title', '=', $campCode)
    ->addWhere('subtype:name', '=', 'Collection_Camp')
    ->execute()
    ->first();
  return $camp['id'] ?? null;
}

function assignContribution(array $entry): void {
  $contactId = findContactId($entry['email'], $entry['mobile']);
  if (!$contactId) {
    echo "❌ Contact not found for email: {$entry['email']} / mobile: {$entry['mobile']}\n";
    return;
  }

  $campId = getCollectionCampId($entry['camp_code']);
  if (!$campId) {
    echo "❌ Collection Camp not found for Camp Code: {$entry['camp_code']}\n";
    return;
  }

  try {
    Activity::create(FALSE)
      ->addValue('subject', $entry['description_of_material'])
      ->addValue('activity_type_id:name', 'Material Contribution')
      ->addValue('status_id:name', 'Completed')
      ->addValue('activity_date_time', $entry['contribution_date'])
      ->addValue('source_contact_id', $contactId)
      ->addValue('Material_Contribution.Collection_Camp', $campId)
      ->addValue('Material_Contribution.Contribution_Date', $entry['contribution_date'])
      ->execute();

    echo "✅ Contribution assigned for Contact ID $contactId to Camp Code: {$entry['camp_code']}\n";
  } catch (\Exception $e) {
    echo "❌ Error assigning contribution: " . $e->getMessage() . "\n";
  }
}

function main(): void {
  try {
    echo "=== Starting Material Contribution Import ===\n";
    $contacts = readContactsFromCsv(MATERIAL_CONTRIBUTION_CSV_FILE_PATH);
    foreach ($contacts as $entry) {
      assignContribution($entry);
    }
    echo "=== Import Complete ===\n";
  } catch (\Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
  }
}

main();