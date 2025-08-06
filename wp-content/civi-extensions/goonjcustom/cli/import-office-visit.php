<?php

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

function get_state_id($state_name) {
  $state = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();
  return $state['id'] ?? '';
}

function get_office_id($office_name) {
  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();
  return $contacts['id'] ?? '';
}

function get_initiator_id($data) {
  $firstName = trim($data['First Name'] ?? '');
  $email = trim($data['Email'] ?? '');
  $mobile = trim($data['Mobile'] ?? '');

  // 1️⃣ Try First Name + Email
  if (!empty($firstName) && !empty($email)) {
    $contact = Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      Civi::log()->info("Matched contact by First Name + Email: {$firstName} / {$email} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }

  // 2️⃣ Try First Name + Mobile
  if (!empty($firstName) && !empty($mobile)) {
    $contact = Contact::get(FALSE)
      ->addJoin('Phone AS phone', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      Civi::log()->info("Matched contact by First Name + Mobile: {$firstName} / {$mobile} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }

  return '';
}

function main() {
  $csvFilePath = '/Users/shubhambelwal/Sites/goonj/wp-content/civi-extensions/goonjcustom/cli/testing data - Institution collection camp (3).csv';

  echo "CSV File: $csvFilePath\n";
  if (!file_exists($csvFilePath)) {
    exit("Error: File not found.\n");
  }

  if (($handle = fopen($csvFilePath, 'r')) === FALSE) {
    echo "Error: Unable to open CSV file.\n";
    return;
  }

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    echo "Error: Unable to read header row from CSV file.\n";
    fclose($handle);
    return;
  }

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Error: Row $rowNum column mismatch.\n";
      continue;
    }

    $data = array_combine($header, $row);

    $values = [
      'source_contact_id' => get_initiator_id($data),
      'Office_Visit.Goonj_Processing_Center' => get_office_id($data['Coordinating Goonj Office'] ?? ''),
      'activity_date_time' => $data['visit data'] ?? '',
    ];

    try {
      \Civi\Api4\Activity::create(FALSE)
        ->addValue('activity_type_id:name', 'Office visit')
        ->addValue('status_id:name', 'Completed')
        ->addValue('source_contact_id', $values['source_contact_id'])
        ->addValue('Office_Visit.Goonj_Processing_Center', $values['Office_Visit.Goonj_Processing_Center'])
        ->addValue('activity_date_time', $values['activity_date_time'])
        ->execute();

      echo "✅ Imported activity for Camp Code\n";
    } catch (\Throwable $e) {
      echo "❌ Error for Camp Code: " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();