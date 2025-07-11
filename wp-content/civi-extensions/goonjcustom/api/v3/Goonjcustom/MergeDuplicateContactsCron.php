<?php

/**
 * Goonjcustom.MergeDuplicateContactsCron API.
 *
 * Merges duplicate contacts into real ones using data from a local CSV file.
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_goonjcustom_merge_duplicate_contacts_cron($params) {
  $csvPath = ABSPATH . 'wp-content/uploads/2025/07/Merge-Duplciate-Sheet1-1.csv';
  \Civi::log()->info("[MergeDuplicatesCron] 📁 CSV path: $csvPath");

  if (!file_exists($csvPath)) {
    return civicrm_api3_create_error("File not found at: $csvPath");
  }

  $file = fopen($csvPath, 'r');
  if (!$file) {
    return civicrm_api3_create_error("Unable to open the CSV file.");
  }

  $header = fgetcsv($file, 0, ",", '"', "\\");
  if (!$header || count($header) < 2 || in_array('<!DOCTYPE html>', $header)) {
    fclose($file);
    return civicrm_api3_create_error("Invalid or malformed CSV header.");
  }

  $groups = [];
  $rowIndex = 1;

  while (($row = fgetcsv($file, 0, ",", '"', "\\")) !== false) {
    $rowIndex++;
    if (count($row) !== count($header)) {
      \Civi::log()->warning("⚠️ Row #$rowIndex column count mismatch. Skipping row.");
      continue;
    }

    $contact = array_combine($header, $row);
    $email = strtolower(trim($contact['email'] ?? ''));
    $firstName = strtolower(trim($contact['first_name'] ?? ''));
    $status = trim($contact['Status'] ?? '');

    $key = $email . '|' . $firstName;

    if (!$email || !$firstName || !in_array($status, ['Real', 'Duplicate'])) {
      continue;
    }

    if (!isset($groups[$key])) {
      $groups[$key] = ['real' => null, 'duplicates' => [], 'email' => $email];
    }

    if ($status === 'Real') {
      $groups[$key]['real'] = (int) $contact['contact_id'];
    } else {
      $groups[$key]['duplicates'][] = (int) $contact['contact_id'];
    }
  }

  fclose($file);

  foreach ($groups as $key => $data) {
    $realId = $data['real'];
    $duplicates = $data['duplicates'];
    $email = $data['email'];

    if (!$realId || empty($duplicates)) {
      continue;
    }

    foreach ($duplicates as $dupId) {
      try {
        \Civi\Api4\Contact::mergeDuplicates(FALSE)
          ->setContactId($realId)
          ->setDuplicateId($dupId)
          ->setMode('safe')
          ->execute();

        \Civi::log()->info("[MergeDuplicatesCron] Merged duplicate #$dupId → real #$realId ($key)");
      } catch (Exception $e) {
      }
    }
  }

  return civicrm_api3_create_success([], $params, 'Goonjcustom', 'merge_duplicate_contacts_cron');
}