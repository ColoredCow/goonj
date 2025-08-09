<?php

/**
 * CLI Script: Assign "Material Contribution" activity per CSV row.
 * - Finds contact by (First Name + Email) or (First Name + Mobile) using get_initiator_id().
 *   Fallback: by Email, else by Phone.
 * - Finds Dropping Center by title (code) + subtype Dropping_Center.
 * - Creates Activity: Material Contribution (Completed) on the given date.
 * - Links Dropping Center via custom field on the Activity.
 */

use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\Activity;
use Civi\Api4\EckEntity;
use Civi\Api4\Contact;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/* =========================
 * Config
 * ========================= */
const CSV_FILE_PATH = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - goonj activities contact (11).csv';
const DRY_RUN       = false; // set true to test without writing

// Custom field key on Activity (adjust if different on your site)
const CF_DROPPING_CENTER = 'Material_Contribution.Dropping_Center'; // entity ref to Collection_Camp

/* =========================
 * Helpers
 * ========================= */

/** Quiet down deprecation noise in CLI */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');

/** Normalize header: trim, collapse spaces, lowercase */
function norm(string $s): string {
  $s = preg_replace('/\s+/u', ' ', trim($s));
  return mb_strtolower($s);
}

/** Build map: normalized header -> original header */
function build_header_map(array $header): array {
  $map = [];
  foreach ($header as $h) {
    $map[norm($h)] = $h;
  }
  return $map;
}

/** Get a value from a row by trying multiple aliases */
function getv(array $row, array $hmap, array $aliases): ?string {
  foreach ($aliases as $alias) {
    $key = $hmap[norm($alias)] ?? null;
    if ($key !== null && array_key_exists($key, $row)) {
      $val = trim((string)$row[$key]);
      if ($val !== '') return $val;
    }
  }
  return null;
}

/** Parse "DD/MM/YY" or "DD/MM/YYYY" -> "YYYY-MM-DD 00:00:00" */
function parse_contribution_date(?string $d): ?string {
  $d = trim((string)$d);
  if ($d === '') return null;
  $dt = \DateTime::createFromFormat('d/m/y', $d) ?: \DateTime::createFromFormat('d/m/Y', $d);
  return $dt ? $dt->format('Y-m-d 00:00:00') : null;
}

/**
 * Try (First Name + Email) then (First Name + Mobile) — your logic.
 */
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
      \Civi::log()->info("Matched contact by First Name + Email: {$firstName} / {$email} (ID: {$contact['id']})");
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
      \Civi::log()->info("Matched contact by First Name + Mobile: {$firstName} / {$mobile} (ID: {$contact['id']})");
      return (int)$contact['id'];
    }
  }

  return null;
}

/** Fallbacks if initiator lookup fails: by email then by phone */
function find_contact_id_fallback(?string $email, ?string $phone): ?int {
  $email = trim((string)$email);
  $phone = trim((string)$phone);

  if ($email !== '') {
    $row = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute()
      ->first();
    if (!empty($row['contact_id'])) return (int)$row['contact_id'];
  }

  if ($phone !== '') {
    $row = Phone::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('phone', '=', $phone)
      ->execute()
      ->first();
    if (!empty($row['contact_id'])) return (int)$row['contact_id'];
  }

  return null;
}

/** Find Dropping Center (Collection_Camp) id by title + subtype Dropping_Center */
function find_dropping_center_id(string $code): ?int {
  $code = trim($code);
  if ($code === '') return null;

  $row = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('title', '=', $code)
    ->addWhere('subtype:name', '=', 'Dropping_Center')
    ->setLimit(1)
    ->execute()
    ->first();

  return $row['id'] ?? null;
}

/* =========================
 * Main
 * ========================= */

function main(): void {
  $csv = CSV_FILE_PATH;
  echo "CSV File: $csv\n";
  if (!file_exists($csv) || !is_readable($csv)) {
    exit("Error: CSV not found or not readable.\n");
  }

  $fh = fopen($csv, 'r');
  if (!$fh) exit("Error: Unable to open CSV.\n");

  $header = fgetcsv($fh, 0, ',', '"', '\\');
  if ($header === FALSE) {
    fclose($fh);
    exit("Error: Unable to read header row.\n");
  }
  $hmap = build_header_map($header);

  // Column aliases to match your sheets
  $COL = [
    'center_code' => ['Dropping Center Code', 'dropping_center', 'Dropping Center'],
    'date'        => ['Contribution Date (DD/MM/YY)', 'Contribution Date (DD/MM/YYYY)', 'contribution_date'],
    'first_name'  => ['First Name', 'first_name'],
    'email'       => ['Email', 'email'],
    'phone'       => ['Mobile', 'Phone', 'phone'],
  ];

  $rowNum = 1;
  $created = 0; $skipped = 0; $errors = 0;

  while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Row $rowNum: column mismatch — skipping.\n";
      $skipped++; continue;
    }
    $data = array_combine($header, $row) ?: [];

    $email = getv($data, $hmap, $COL['email']);
    $phone = getv($data, $hmap, $COL['phone']);
    $code  = getv($data, $hmap, $COL['center_code']);
    $date  = getv($data, $hmap, $COL['date']);
    $first = getv($data, $hmap, $COL['first_name']);

    // Normalize keys so get_initiator_id() sees expected names
    if ($first !== null) $data['First Name'] = $first;
    if ($email !== null) $data['Email']      = $email;
    if ($phone !== null) { $data['Mobile'] = $phone; $data['Phone'] = $phone; }

    if (!$code) {
      echo "Row $rowNum: missing Dropping Center Code — skipping.\n";
      $skipped++; continue;
    }

    // 1) Your requested initiator lookup
    $contactId = get_initiator_id($data);

    // 2) Fallbacks if not found
    if (!$contactId) {
      $contactId = find_contact_id_fallback($email, $phone);
    }

    if (!$contactId) {
      echo "Row $rowNum ($code): contact not found (first: {$first}, email: {$email}, phone: {$phone}) — skipping.\n";
      $skipped++; continue;
    }

    $centerId = find_dropping_center_id($code);
    if (!$centerId) {
      echo "Row $rowNum ($code): Dropping Center not found — skipping.\n";
      $skipped++; continue;
    }

    $activityDateTime = parse_contribution_date($date);
    if (!$activityDateTime) {
      echo "Row $rowNum ($code): invalid contribution date '{$date}' — skipping.\n";
      $skipped++; continue;
    }

    if (DRY_RUN) {
      echo "DRY-RUN Row $rowNum: would create activity for contact {$contactId}, center {$centerId}, date {$activityDateTime}\n";
      $created++; continue;
    }

    try {
      Activity::create(FALSE)
        ->addValue('activity_type_id:name', 'Material Contribution')
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_date_time', $activityDateTime)
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->addValue(CF_DROPPING_CENTER, $centerId)
        ->execute();

      echo "✅ Row $rowNum: created activity for contact {$contactId} at center {$centerId} (date: {$activityDateTime})\n";
      $created++;
    }
    catch (\Throwable $e) {
      echo "❌ Row $rowNum ($code): " . $e->getMessage() . "\n";
      $errors++;
      continue;
    }
  }

  fclose($fh);
  echo "=== Done. Created: {$created}, Skipped: {$skipped}, Errors: {$errors} ===\n";
}

main();