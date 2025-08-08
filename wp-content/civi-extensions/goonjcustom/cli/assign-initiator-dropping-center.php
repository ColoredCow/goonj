<?php

/**
 * @file
 * CLI script to update initiator on Dropping Center (Collection_Camp) by title
 * and create a linked Civi Activity per row.
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\StateProvince;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/** Optional: quiet down CLI deprecation spam (upgrade plugins for a real fix). */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');

/* =========================
 * Helpers
 * ========================= */

/** Convert "DD-MM-YYYY" -> "YYYY-MM-DD" (returns null if invalid/empty) */
function dmy_to_ymd(?string $d): ?string {
  $d = trim((string)$d);
  if ($d === '') return null;
  $dt = \DateTime::createFromFormat('d-m-Y', $d);
  return $dt ? $dt->format('Y-m-d') : null;
}

/** Get state id by name (exact match) — unused here but kept for reuse */
function get_state_id($state_name) {
  $state = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();
  return $state['id'] ?? '';
}

/** Lookup office id by display name and subtype Goonj_Office — unused here but kept for reuse */
function get_office_id($office_name) {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return '';
  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office') // fixed subtype case
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();
  return $row['id'] ?? '';
}

/** Find initiator by First Name + (Email OR Mobile) */
function get_initiator_id(array $data) {
  $firstName = trim($data['First Name'] ?? '');
  $email     = trim($data['Email'] ?? '');
  $mobile    = trim($data['Mobile'] ?? '');

  // Try First Name + Email
  if ($firstName !== '' && $email !== '') {
    $contact = Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      \Civi::log()->info("Matched contact by First Name + Email: {$firstName} / {$email} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }

  // Try First Name + Mobile
  if ($firstName !== '' && $mobile !== '') {
    $contact = Contact::get(FALSE)
      ->addJoin('Phone AS phone', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();

    if (!empty($contact['id'])) {
      \Civi::log()->info("Matched contact by First Name + Mobile: {$firstName} / {$mobile} (ID: {$contact['id']})");
      return $contact['id'];
    }
  }

  return '';
}

/* =========================
 * Main
 * ========================= */

function main() {
  // Update path as needed
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - goonj activities contact (4).csv';

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

    // Camp code = the title of the Collection_Camp (Dropping Center)
    $campCode = trim($data['Dropping Center Code'] ?? '');
    if ($campCode === '') {
      echo "⚠️  Skipping row $rowNum — Camp Code missing.\n";
      continue;
    }

    // Parse Created Date -> Y-m-d 00:00:00
    $createdRaw = trim((string)($data['Created Date (DD/MM/YY)'] ?? ''));
    $createdYmd = dmy_to_ymd($createdRaw);
    // $activityDateTime = $createdYmd ? ($createdYmd . ' 00:00:00') : date('Y-m-d H:i:s');

    // Resolve initiator
    $initiatorId = get_initiator_id($data);
    try {
      // 1) Update initiator on camp with matching title
      EckEntity::update('Collection_Camp', FALSE)
        ->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId)
        ->addWhere('title', '=', $campCode)
        ->execute();

      // 2) Fetch camp id for link in the Activity
      $camp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $campCode)
        ->setLimit(1)
        ->execute()
        ->first();

      $campId = $camp['id'] ?? null;

      if (!$campId) {
        echo "❌ Row $rowNum ($campCode): camp not found after update — skipping activity.\n";
        continue;
      }

      // 3) Create activity for initiator
      Activity::create(FALSE)
        ->addValue('subject', $campCode)
        ->addValue('activity_type_id:name', 'Organize Dropping Center')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', $createdRaw)
        ->addValue('source_contact_id', $initiatorId)
        ->addValue('target_contact_id', $initiatorId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

      echo "✅ Row $rowNum ($campCode): initiator set and activity logged (camp id: $campId)\n";

    } catch (\Throwable $e) {
      echo "❌ Row $rowNum ($campCode): " . $e->getMessage() . "\n";
      continue;
    }
  }

  fclose($handle);
  echo "=== Done ===\n";
}

main();