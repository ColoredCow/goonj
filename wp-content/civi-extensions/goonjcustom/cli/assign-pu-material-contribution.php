<?php

/**
 * @file
 * CLI Script to assign material contribution via CSV in CiviCRM.
 *
 * Expected CSV headers (exact, case & spacing as in the sheet):
 *   Contact Created Date (MM/DD/YY)
 *   First Name
 *   Last Name
 *   Email
 *   Mobile
 *   Account ID
 *   Street
 *   City
 *   State
 *   Postal Code
 *   Country
 *   Contribution Date (MM/DD/YY)
 *   Description of Material
 *   DeliveredBy:Contact#
 *   Delivered By : Name
 *   Remarks
 *   Goonj Office
 */

use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\Contact;
use Civi\Api4\Activity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}
// --- CONFIG: set your CSV path here ---
$csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/goonj office material contribution - Goonj Office Contribution (1).csv';

echo "CSV File: {$csvFilePath}\n";

/**
 * Parse date like "5/2/2023" or "05/02/23" into YYYY-MM-DD.
 */
function parse_mmddyy_to_iso(?string $in): ?string {
  if (!$in) return null;
  $in = trim($in);
  if ($in === '') return null;

  // Normalize separators
  $in = str_replace(['.', '-', '\\'], '/', $in);
  $parts = explode('/', $in);
  if (count($parts) >= 3) {
    [$m,$d,$y] = $parts;
    $m = (int) $m;
    $d = (int) $d;
    $y = trim((string) $y);

    if (strlen($y) === 2) {
      $y = (int) $y;
      $y += ($y >= 70 ? 1900 : 2000);
    } else {
      $y = (int) $y;
    }

    if (checkdate($m, $d, $y)) {
      return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
  }

  // Fallback
  $ts = strtotime($in);
  return $ts ? date('Y-m-d', $ts) : null;
}

/**
 * Read CSV rows with EXACT headers preserved.
 */
function read_rows_from_csv(string $filePath): array {
  if (!file_exists($filePath) || !is_readable($filePath)) {
    throw new \RuntimeException("CSV file not found or not readable: {$filePath}");
  }

  $rows = [];
  if (($h = fopen($filePath, 'r')) !== false) {
    $header = fgetcsv($h, 0, ',');
    if (!$header) {
      throw new \RuntimeException("CSV header missing.");
    }

    // Preserve exact header strings as keys
    while (($data = fgetcsv($h, 0, ',')) !== false) {
      if (count($data) === 1 && trim((string)$data[0]) === '') {
        continue;
      }
      $row = [];
      foreach ($header as $i => $col) {
        $row[$col] = isset($data[$i]) ? trim((string) $data[$i]) : '';
      }
      $rows[] = $row;
    }
    fclose($h);
  }

  return $rows;
}

/**
 * Find a contact by:
 *  1. First name + email (if both present)
 *  2. First name + phone (if both present)
 */
function find_contact_id_by_name_and_email_or_phone(?string $firstName, ?string $email, ?string $phone): ?int {
  $firstName = $firstName ? trim($firstName) : '';
  $email     = $email ? trim($email) : '';
  $phone     = $phone ? trim($phone) : '';

  // 1. First name + email
  if ($firstName !== '' && $email !== '') {
    $res = Contact::get(FALSE)
      ->addJoin('Email AS email', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();
    if (!empty($res['id'])) {
      return (int) $res['id'];
    }
  }

  // 2. First name + phone
  if ($firstName !== '' && $phone !== '') {
    $res = Contact::get(FALSE)
      ->addJoin('Phone AS phone', 'LEFT')
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $phone)
      ->execute()
      ->first();
    if (!empty($res['id'])) {
      return (int) $res['id'];
    }
  }

  return null;
}

/**
 * Resolve Goonj Office display name -> Contact ID (Organization, subtype Goonj_office).
 */
function get_office_id($office_name) {
  $office_id = '';
  if (!$office_name) return $office_id;

  $contacts = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();

  if ($contacts && !empty($contacts['id'])) {
    $office_id = (string) $contacts['id'];
  }
  return $office_id;
}

/**
 * Create the Material Contribution activity with the required fields.
 */
function create_material_contribution_activity(array $ctx): void {
  $contactId = (int) $ctx['contact_id'];
  $dateIso   = $ctx['date_iso'] ?: date('Y-m-d');
  $subject   = $ctx['description'] ?: 'Material Contribution';
  $officeId  = $ctx['goonj_office_id'] ?? null;

  $req = Activity::create(FALSE)
    ->addValue('activity_type_id:name', 'Material Contribution')
    ->addValue('status_id:name', 'Completed')
    ->addValue('activity_date_time', $dateIso)
    ->addValue('source_contact_id', $contactId)
    ->addValue('target_contact_id', $contactId)
    ->addValue('Material_Contribution.Institute_Type', 9)
    ->addValue('subject', $subject)
    ->addValue('Material_Contribution.Contribution_Date', $dateIso);

  if (!empty($ctx['remarks'])) {
    $req->addValue('details', $ctx['remarks']);
  }

  if (!empty($officeId)) {
    $req->addValue('Material_Contribution.Goonj_Office', $officeId);
  }

  if (!empty($ctx['delivered_by_contact_id'])) {
    $req->addValue('Material_Contribution.Delivered_By_Contact', (int) $ctx['delivered_by_contact_id']);
  }

  if (!empty($ctx['delivered_by_name'])) {
    $req->addValue('Material_Contribution.Delivered_By', $ctx['delivered_by_name']);
  }

  try {
    $res = $req->execute();
    $id  = $res->first()['id'] ?? null;
    echo "✔ Created Material Contribution activity (ID: {$id}) for Contact {$contactId} on {$dateIso}\n";
  } catch (\CiviCRM_API4_Exception $ex) {
    echo "✖ Failed to create activity for Contact {$contactId}: {$ex->getMessage()}\n";
  }
}

/**
 * Process one CSV row using EXACT column names.
 */
function process_row(array $row): void {
  $firstName       = $row['First Name'] ?? '';
  $email           = $row['Email'] ?? '';
  $phone           = $row['Mobile'] ?? '';
  $dateRaw         = $row['Contribution Date (MM/DD/YY)'] ?? '';
  $description     = $row['Description of Material'] ?? '';
  $deliveredCidRaw = $row['DeliveredBy:Contact#'] ?? '';
  $deliveredName   = $row['Delivered By : Name'] ?? '';
  $remarks         = $row['Remarks'] ?? '';
  $officeName      = $row['Goonj Office'] ?? '';

  $dateIso = parse_mmddyy_to_iso($dateRaw);

  $contactId = find_contact_id_by_name_and_email_or_phone($firstName, $email, $phone);
  if (!$contactId) {
    echo "⚠ Skipping row: contact not found (first_name='{$firstName}', email='{$email}', phone='{$phone}')\n";
    return;
  }

  // Resolve office
  $officeId = null;
  if ($officeName !== '') {
    $officeId = get_office_id($officeName) ?: null;
    if (!$officeId) {
      echo "⚠ Goonj Office not resolved for '{$officeName}'.\n";
    }
  }

  // DeliveredBy:Contact# may be "NA" or blank; extract digits if present
  $deliveredByContactId = null;
  if ($deliveredCidRaw) {
    $digits = preg_replace('/\D+/', '', $deliveredCidRaw);
    if ($digits !== '') {
      $deliveredByContactId = (int) $digits;
    }
  }

  create_material_contribution_activity([
    'contact_id'              => $contactId,
    'date_iso'                => $dateIso,
    'description'             => $description,
    'delivered_by_contact_id' => $deliveredByContactId,
    'delivered_by_name'       => $deliveredName,
    'remarks'                 => $remarks,
    'goonj_office_id'         => $officeId,
  ]);
}

/**
 * Main.
 */
function main(string $csvFilePath): void {
  echo "=== Starting Material Contribution Assignment ===\n";
  try {
    $rows = read_rows_from_csv($csvFilePath);
    if (!$rows) {
      echo "No data rows found. Nothing to do.\n";
      return;
    }

    $count = 0;
    foreach ($rows as $row) {
      $count++;
      echo "Row #{$count}...\n";
      process_row($row);
    }

    echo "=== Completed: processed {$count} rows ===\n";
  } catch (\Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
  }
}

main($csvFilePath);
