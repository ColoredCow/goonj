<?php

/**
 * @file
 */

/**
 * Goonjcustom.MergeDuplicateContactsCron API.
 *
 * Merges duplicate contacts into real ones using data from a local CSV file.
 *
 * @param array $params
 *
 * @return array
 */

use Civi\Api4\Contact;

/**
 *
 */
function civicrm_api3_goonjcustom_merge_duplicate_contacts_cron($params) {
  $csvPath = ABSPATH . 'wp-content/uploads/2025/07/Merge-Duplciate-Sheet1-4.csv';
  \Civi::log()->info("[MergeDuplicatesCron] Using CSV path: $csvPath");

  if (!file_exists($csvPath)) {
    return civicrm_api3_create_error("❌ File not found at: $csvPath");
  }

  $file = fopen($csvPath, 'r');
  if (!$file) {
    return civicrm_api3_create_error("❌ Unable to open the CSV file.");
  }

  $header = fgetcsv($file, 0, ",", '"', "\\");
  if (!$header || count($header) < 2 || in_array('<!DOCTYPE html>', $header)) {
    fclose($file);
    return civicrm_api3_create_error("❌ Invalid or malformed CSV header.");
  }

  $groups = [];
  $rowIndex = 1;

  while (($row = fgetcsv($file, 0, ",", '"', "\\")) !== FALSE) {
    $rowIndex++;

    if (count($row) !== count($header)) {
      \Civi::log()->warning("Row #$rowIndex column count mismatch. Skipping.");
      continue;
    }

    $contact = array_combine($header, $row);
    $email = strtolower(trim($contact['email'] ?? ''));
    $firstName = strtolower(trim($contact['first_name'] ?? ''));
    $status = trim($contact['Status'] ?? '');
    $contactId = (int) ($contact['contact_id'] ?? 0);

    if (!$email || !$firstName || !$contactId || !in_array($status, ['Real', 'Duplicate'])) {
      continue;
    }

    $key = $email . '|' . $firstName;

    if (!isset($groups[$key])) {
      $groups[$key] = ['real' => NULL, 'duplicates' => [], 'email' => $email, 'first_name' => $firstName];
    }

    if ($status === 'Real') {
      $groups[$key]['real'] = $contactId;
    }
    else {
      $groups[$key]['duplicates'][] = $contactId;
    }
  }

  fclose($file);

  foreach ($groups as $key => $data) {
    $realId = $data['real'];
    $duplicates = $data['duplicates'];
    $email = $data['email'];
    $firstName = $data['first_name'];

    if (!$realId || empty($duplicates)) {
      continue;
    }

    foreach ($duplicates as $dupId) {
      try {
        Contact::mergeDuplicates(FALSE)
          ->setContactId($realId)
          ->setDuplicateId($dupId)
          ->setMode('aggressive')
          ->execute();

        \Civi::log()->info("Merged duplicate #$dupId into real #$realId for $key");
      }
      catch (Exception $e) {
        // \Civi::log()->error("Merge failed for duplicate #$dupId → real #$realId for $key: " . $e->getMessage());
      }
    }
  }

  return civicrm_api3_create_success([], $params, 'Goonjcustom', 'merge_duplicate_contacts_cron');
}
