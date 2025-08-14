<?php

/**
 * CLI script: Create "Office visit" activities from CSV.
 * CSV columns (example):
 * Contact ID | Visit Date (MM/DD/YY) | Visited to Goonj Office (GCOC) | First Name | Last Name | Street | City | State | Country | Mobile | Email | Contact Created Date (MM/DD/YY)
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/* =========================
 * Config
 * ========================= */

// Custom field keys on Activity (adjust to match your site)
const CF_OFFICE_FIELD     = 'Office_Visit.Goonj_Processing_Center';
const CF_VISITING_AS      = 'Office_Visit.You_are_Visiting_as';

// Activity type name
const ACTIVITY_TYPE_NAME  = 'Office visit';

// Safety switch: TRUE => preview only; FALSE => write to DB
const DRY_RUN = false;

/* =========================
 * Noise control for CLI
 * ========================= */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');

/* =========================
 * Helpers
 * ========================= */

/** Normalize a header key for matching: trim, lower, collapse inner spaces */
function norm(string $s): string {
  $s = preg_replace('/\s+/u', ' ', trim($s));
  return mb_strtolower($s);
}

/** Build a map of normalized header -> original header from the CSV header row */
function build_header_map(array $header): array {
  $map = [];
  foreach ($header as $h) {
    $map[norm($h)] = $h;
  }
  return $map;
}

/** Get a value from the CSV row using flexible header matching (tries aliases) */
function getv(array $row, array $hmap, array $aliases): ?string {
  foreach ((array)$aliases as $alias) {
    $key = $hmap[norm($alias)] ?? null;
    if ($key !== null && array_key_exists($key, $row)) {
      $val = trim((string)$row[$key]);
      if ($val !== '') return $val;
    }
  }
  return null;
}

/** Convert "MM/DD/YY" or "MM/DD/YYYY" -> "YYYY-MM-DD 00:00:00" */
function mdy_slash_to_datetime(?string $d): ?string {
  $d = trim((string)$d);
  if ($d === '') return null;
  $dt = \DateTime::createFromFormat('m/d/y', $d) ?: \DateTime::createFromFormat('m/d/Y', $d);
  return $dt ? $dt->format('Y-m-d 00:00:00') : null;
}

/** Preferred lookup: (First Name + Email) then (First Name + Mobile) */
function get_initiator_id(array $data): ?int {
  $firstName = trim($data['First Name'] ?? ($data['first_name'] ?? ''));
  $email     = trim($data['Email'] ?? ($data['email'] ?? ''));
  $mobile    = trim($data['Mobile'] ?? ($data['Phone'] ?? ($data['phone'] ?? '')));

  if ($firstName !== '' && $email !== '') {
    $contact = Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();
    if (!empty($contact['id'])) {
      \Civi::log()->info("Matched by First+Email: {$firstName} / {$email} (ID {$contact['id']})");
      return (int)$contact['id'];
    }
  }

  if ($firstName !== '' && $mobile !== '') {
    $contact = Contact::get(FALSE)
      ->addJoin('Phone AS phone', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();
    if (!empty($contact['id'])) {
      \Civi::log()->info("Matched by First+Mobile: {$firstName} / {$mobile} (ID {$contact['id']})");
      return (int)$contact['id'];
    }
  }

  return null;
}

/** Fallback lookup: by Email then Phone */
function find_contact_id_fallback(?string $email, ?string $phone): ?int {
  $email = trim((string)$email);
  $phone = trim((string)$phone);

  if ($email !== '') {
    $row = \Civi\Api4\Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()
      ->first();
    if (!empty($row['contact_id'])) return (int)$row['contact_id'];
  }

  if ($phone !== '') {
    $row = \Civi\Api4\Phone::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('phone', '=', $phone)
      ->execute()
      ->first();
    if (!empty($row['contact_id'])) return (int)$row['contact_id'];
  }

  return null;
}

/** Resolve contact id from CSV "Contact ID": numeric ID or external_identifier */
function resolve_contact_id_from_csv(?string $contactIdCsv): ?int {
  $raw = trim((string)$contactIdCsv);
  if ($raw === '') return null;

  if (ctype_digit($raw)) {
    $row = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', '=', (int)$raw)
      ->execute()
      ->first();
    return $row['id'] ?? null;
  }

  // Treat as external_identifier (your sample looks like one)
  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('external_identifier', '=', $raw)
    ->execute()
    ->first();

  return $row['id'] ?? null;
}

/** Lookup office contact id by display name and subtype Goonj_Office */
function get_office_id(?string $office_name): ?int {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return null;

  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name . '%')
    ->setLimit(1)
    ->execute()
    ->first();

  return isset($row['id']) ? (int)$row['id'] : null;
}

/* =========================
 * Main
 * ========================= */

function main(): void {
    
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - conatct test (10).csv';

  echo "CSV File: $csvFilePath\n";
  if (!file_exists($csvFilePath)) {
    exit("Error: File not found.\n");
  }

  if (($handle = fopen($csvFilePath, 'r')) === FALSE) {
    echo "Error: Unable to open CSV file.\n";
    return;
  }

  // Read header
  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    echo "Error: Unable to read header row.\n";
    fclose($handle);
    return;
  }
  $hmap = build_header_map($header);

  $COL = [
    'visit_date'  => ['Visit Date (MM/DD/YY)', 'Visit Date', 'Visited Date'],
    'office'      => ['Visited to Goonj Office (GCOC)', 'Visited to Goonj Office', 'GCOC'],
    'first_name'  => ['First Name', 'first_name'],
    'last_name'   => ['Last Name', 'last_name'],
    'mobile'      => ['Mobile', 'Phone', 'phone'],
    'email'       => ['Email', 'email'],
  ];

  $rowNum = 1; $created = 0; $skipped = 0; $errors = 0;

  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Row $rowNum: column mismatch — skipping.\n";
      $skipped++; continue;
    }
    $data = array_combine($header, $row) ?: [];

    $contactCsv = getv($data, $hmap, $COL['contact_id']);
    $visitRaw   = getv($data, $hmap, $COL['visit_date']);
    $officeName = getv($data, $hmap, $COL['office']);
    $first      = getv($data, $hmap, $COL['first_name']);
    $last       = getv($data, $hmap, $COL['last_name']);
    $mobile     = getv($data, $hmap, $COL['mobile']);
    $email      = getv($data, $hmap, $COL['email']);

    if (!$visitRaw || !$officeName) {
      echo "Row $rowNum: missing Visit Date or Office — skipping.\n";
      $skipped++; continue;
    }

    if ($first !== null) $data['First Name'] = $first;
    if ($email !== null) $data['Email']      = $email;
    if ($mobile !== null) { $data['Mobile'] = $mobile; $data['Phone'] = $mobile; }

    $contactId = get_initiator_id($data)
      ?: find_contact_id_fallback($email, $mobile)
      ?: resolve_contact_id_from_csv($contactCsv);

    if (!$contactId) {
      echo "Row $rowNum: contact not found (first: {$first}, email: {$email}, phone: {$mobile}, CSV ID: {$contactCsv}) — skipping.\n";
      $skipped++; continue;
    }

    $officeId = get_office_id($officeName);
    if (!$officeId) {
      echo "Row $rowNum: Goonj Office not found for '{$officeName}' — skipping.\n";
      $skipped++; continue;
    }

    $activityDateTime = mdy_slash_to_datetime($visitRaw);
    if (!$activityDateTime) {
      echo "Row $rowNum: invalid visit date '{$visitRaw}' — skipping.\n";
      $skipped++; continue;
    }


    try {
      $res = Activity::create(FALSE)
        ->addValue('activity_type_id:name', ACTIVITY_TYPE_NAME)
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_date_time', $activityDateTime)
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->addValue(CF_OFFICE_FIELD, $officeId)
        ->addValue(CF_VISITING_AS, 'Individual')
        ->execute();

      $newId = $res['id'] ?? null;
      echo "✅ Row $rowNum: created Office visit activity"
        . ($newId ? " (id: {$newId})" : "")
        . " for contact {$contactId} at office {$officeId} on {$activityDateTime}\n";
      $created++;
    } catch (\Throwable $e) {
      echo "❌ Row $rowNum: " . $e->getMessage() . "\n";
      $errors++; continue;
    }
  }

  fclose($handle);
  echo "=== Done. Created: {$created}, Skipped: {$skipped}, Errors: {$errors} ===\n";
}

main();