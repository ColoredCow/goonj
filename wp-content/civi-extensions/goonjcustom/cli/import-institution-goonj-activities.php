<?php

/**
 * CLI Script to import institution goonj activities (optimized).
 * Assumes CSV headers exactly as provided by the user at the end of the prompt.
 */

use Civi\Api4\Contact;
use Civi\Api4\StateProvince;
use Civi\Api4\Relationship;
use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/* =========================
 * Config
 * ========================= */
const CSV_FILE_PATH = '/path/to/institutionactivities.csv'; // <-- change this
const DRY_RUN = false; // true to only print what would be created

/* =========================
 * Helpers
 * ========================= */

function ddmmyyyy_to_ymd(?string $d): ?string {
  $d = trim((string)$d);
  if ($d === '' || $d === '0') return null;
  $dt = \DateTime::createFromFormat('d-m-Y', $d);
  return $dt ? $dt->format('Y-m-d') : null;
}

function clean_number(?string $val): ?float {
  $val = trim((string)$val);
  if ($val === '') return null;
  $val = str_replace([",", " "], "", $val);
  if (!is_numeric($val)) return null;
  return (float)$val;
}

/** Find state id by name (exact match on api4 'name') */
function get_state_id(?string $state_name): ?int {
  $state_name = trim((string)$state_name);
  if ($state_name === '') return null;

  $st = StateProvince::get(FALSE)
    ->addWhere('name', '=', $state_name)
    ->execute()
    ->first();

  return $st['id'] ?? null;
}

/** Lookup org office by display name 'LIKE %...'. */
function get_office_id(?string $office_name): ?int {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return null;

  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office') // fixed subtype case
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();

  return $row['id'] ?? null;
}

/** Get contact id for "Attended By" (email preferred; fallback by display_name). */
function get_attended_id(?string $attendedBy): ?int {
  $attendedBy = trim((string)$attendedBy);
  if ($attendedBy === '') return null;

  // Try as email
  $byEmail = Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $attendedBy)
    ->execute()
    ->first();
  if (!empty($byEmail['id'])) return (int)$byEmail['id'];

  // Fallback: try by display_name
  $byName = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('display_name', '=', $attendedBy)
    ->execute()
    ->first();
  return $byName['id'] ?? null;
}

/** Find/derive the initiator based on First Name + (Email or Mobile). */
function get_initiator_id(array $data): ?int {
  $firstName = trim((string)($data['First Name'] ?? ''));
  $email     = trim((string)($data['Email'] ?? ''));
  $mobile    = trim((string)($data['Mobile'] ?? ''));

  if ($firstName !== '' && $email !== '') {
    $row = Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email.email', '=', $email)
      ->execute()
      ->first();
    if (!empty($row['id'])) return (int)$row['id'];
  }

  if ($firstName !== '' && $mobile !== '') {
    $row = Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone.phone', '=', $mobile)
      ->execute()
      ->first();
    if (!empty($row['id'])) return (int)$row['id'];
  }

  return null;
}

/** Organization id by exact organization_name */
function get_organization_id(?string $organization_name): ?int {
  $organization_name = trim((string)$organization_name);
  if ($organization_name === '') return null;

  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('organization_name', '=', $organization_name)
    ->execute()
    ->first();

  return $row['id'] ?? null;
}

/** Find PoC contact using both phone/email if provided. */
function get_poc_id(?string $poc_phone, ?string $poc_email): ?int {
  $poc_phone = trim((string)$poc_phone);
  $poc_email = trim((string)$poc_email);

  $q = Contact::get(FALSE)->addSelect('id');

  if ($poc_phone !== '') {
    $q->addJoin('Phone AS p', 'LEFT')
      ->addWhere('p.phone', '=', $poc_phone);
  }
  if ($poc_email !== '') {
    $q->addJoin('Email AS e', 'LEFT')
      ->addWhere('e.email', '=', $poc_email);
  }

  $row = $q->execute()->first();
  return $row['id'] ?? null;
}

/**
 * Assign coordinator if email not given, by relationship type + office.
 */
function assignCoordinatorByRelationshipType(?string $poc_email, ?string $officeDisplay, ?string $typeOfInstitution): ?int {
  $poc_email = trim((string)$poc_email);
  if ($poc_email !== '') {
    $row = Contact::get(FALSE)
      ->addSelect('id')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('email.email', '=', $poc_email)
      ->execute()
      ->first();
    return $row['id'] ?? null;
  }

  $relationshipTypeMap = [
    'Corporate'            => 'Corporate Coordinator of',
    'School'               => 'School Coordinator of',
    'College/University'   => 'College/University Coordinator of',
    'Association'          => 'Default Coordinator of',
    'Other'                => 'Default Coordinator of',
  ];
  $rt = $relationshipTypeMap[$typeOfInstitution] ?? 'Default Coordinator of';

  $officeId = get_office_id($officeDisplay);
  if (!$officeId) return null;

  $rels = Relationship::get(FALSE)
    ->addSelect('contact_id_a')
    ->addWhere('contact_id_b', '=', $officeId)
    ->addWhere('relationship_type_id:name', '=', $rt)
    ->addWhere('is_active', '=', TRUE)
    ->addOrderBy('start_date', 'DESC')
    ->execute();

  if ($rels->count() > 0) {
    return $rels->first()['contact_id_a'] ?? null;
  }

  if ($rt !== 'Default Coordinator of') {
    $rels = Relationship::get(FALSE)
      ->addSelect('contact_id_a')
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', 'Default Coordinator of')
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    return $rels->first()['contact_id_a'] ?? null;
  }

  return null;
}

/* =========================
 * Main
 * ========================= */

function main(): void {
  $csv = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - Copy of conatct test 1 (1).csv';
  if (!file_exists($csv)) {
    exit("Error: CSV not found at " . CSV_FILE_PATH . PHP_EOL);
  }

  echo "CSV File: $csv\n";

  $fh = fopen($csv, 'r');
  if (!$fh) {
    exit("Error: Unable to open CSV file.\n");
  }

  $header = fgetcsv($fh, 0, ',', '"', '\\');
  if ($header === FALSE) {
    fclose($fh);
    exit("Error: Unable to read header row.\n");
  }

  $rowNum = 1;
  while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Row $rowNum: column mismatch, skipping.\n";
      continue;
    }

    $data = array_combine($header, $row);

    $campCode       = trim((string)($data['Goonj Activity Code'] ?? ''));
    $title          = $campCode ?: trim((string)($data['Institution name'] ?? 'Activity'));
    $typeOfInst     = trim((string)($data['Institution type'] ?? ''));
    $engageRaw      = trim((string)($data['Goonj Activity Type'] ?? ''));
    $venue          = trim((string)($data['Venue'] ?? ''));
    $city           = trim((string)($data['City'] ?? ''));
    $stateName      = trim((string)($data['State'] ?? ''));
    $officeDisplay  = trim((string)($data['Coordinating Goonj Office'] ?? ''));
    $statusLabel    = trim((string)($data['Activity Status'] ?? ''));
    $createdRaw     = trim((string)($data['Created Date (DD-MM-YYYY)'] ?? ''));
    $startDate      = trim((string)($data['Start Date (DD-MM-YYYY)'] ?? ''));
    $endDate       = trim((string)($data['End Date (DD-MM-YYYY)'] ?? ''));
    $attendedBy     = trim((string)($data['Attended By'] ?? ''));
    $support        = trim((string)($data['Support person details'] ?? ''));
    $activityType   = trim((string)($data['Goonj Activity Type'] ?? ''));
    $n_attendees    = clean_number($data['No. of Attendees'] ?? null);
    $n_sessions     = clean_number($data['No. of Sessions'] ?? null);
    $moneyContrib   = clean_number($data['Total Monetary Contributed'] ?? null);
    $productSale    = clean_number($data['Total Product Sale'] ?? null);
    $uniqueEfforts  = trim((string)($data['Any unique efforts made by organizers'] ?? ''));
    $challenges     = trim((string)($data['Difficulty/challenge faced by organizers'] ?? ''));
    $rating         = trim((string)($data['Rate the activity'] ?? ''));
    $remarks        = trim((string)($data['Any remarks for internal use'] ?? ''));

    $organizationName = trim((string)($data['Institution name'] ?? ''));
    $organizationId   = get_organization_id($organizationName);

    $stateId = get_state_id($stateName);
    $officeId = get_office_id($officeDisplay);

    $initiatorId = get_initiator_id($data);

    $coordinatorId = assignCoordinatorByRelationshipType(
      $data['Coordinating Urban Poc'] ?? '',
      $officeDisplay,
      $typeOfInst
    );

    $attendedId = get_attended_id($attendedBy);

    echo "Row $rowNum: importing [$campCode] '$title' ...\n";

    if (DRY_RUN) {
      echo "  (dry-run) would create Collection_Camp + Activity\n";
      continue;
    }

    try {
      // Create the parent EckEntity (Collection_Camp) with subtype Institution_Goonj_Activities
      $create = EckEntity::create('Collection_Camp', FALSE)
        ->addValue('subtype:name', 'Institution_Goonj_Activities')
        ->addValue('title', $title)
        ->addValue('Institution_Goonj_Activities.You_wish_to_register_as:label', $typeOfInst ?: null)
        ->addValue('Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_:label', $activityType ?: null)
        ->addValue('Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', $venue ?: null)
        ->addValue('Logistics_Coordination.Feedback_Email_Sent', 1)
        ->addValue('Institution_Goonj_Activities.City', $city ?: null)
        ->addValue('Logistics_Coordination.Support_person_details', $support ?: null)
        ->addValue('Logistics_Coordination.Camp_to_be_attended_by', $attendedId ?: null)
        ->addValue('Institution_Goonj_Activities.State', $stateId)
        ->addValue('Institution_Goonj_Activities.Start_Date', $startDate)
        ->addValue('Institution_Goonj_Activities.End_Date', $endDate)
        ->addValue('Institution_Goonj_Activities.Goonj_Office', $officeId)
        ->addValue('Institution_Goonj_Activities.Coordinating_Urban_Poc', $coordinatorId)
        ->addValue('Institution_Goonj_Activities.Institution_POC', get_poc_id($data['Mobile'] ?? '', $data['Email'] ?? ''))
        ->addValue('Institution_Goonj_Activities.Organization_Name', $organizationId)
        ->addValue('Institution_Goonj_Activities_Outcome.Cash_Contribution', $moneyContrib)
        ->addValue('Institution_Goonj_Activities_Outcome.Product_Sale_Amount', $productSale)
        ->addValue('Institution_Goonj_Activities_Outcome.No_of_Attendees', $n_attendees)
        ->addValue('Institution_Goonj_Activities_Outcome.No_of_Sessions', $n_sessions)
        ->addValue('Institution_Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers', $uniqueEfforts ?: null)
        ->addValue('Institution_Goonj_Activities_Outcome.Did_you_face_any_challenges_', $challenges ?: null)
        ->addValue('Institution_Goonj_Activities_Outcome.Rate_the_activity', $rating ?: null)
        ->addValue('Institution_Goonj_Activities_Outcome.Remarks', $remarks ?: null)
         ->addValue('Core_Contribution_Details.Total_online_monetary_contributions', $data['Total Monetary Contributed'] ?? '')
        ->addValue('Collection_Camp_Core_Details.Status', 'authorized')
        ->execute();

      $campId = $create[0]['id'] ?? null;
      if (!$campId) {
        echo "  ❌ Could not get camp id for $campCode\n";
        continue;
      }

      // Link organizer (initiator) to core
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $campId)
        ->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId)
        ->execute();

      echo "  ✅ Created Collection_Camp id=$campId\n";

      // Optional: create a child “Collection_Camp_Activity” record
      EckEntity::create('Collection_Camp_Activity', FALSE)
        ->addValue('subtype:name', 'Institution_Goonj_Activities')
        ->addValue('title', $engageRaw ?: $title)
        ->addValue('Collection_Camp_Activity.Collection_Camp_Id', $campId)
        ->addValue('Collection_Camp_Activity.Start_Date', $startDate)
        ->addValue('Collection_Camp_Activity.End_Date', $endDate)
        ->addValue('Collection_Camp_Activity.Organizing_Person', $initiatorId)
        ->addValue('Collection_Camp_Activity.Activity_Status', $statusLabel ?: 'completed')
        ->addValue('Collection_Camp_Activity.Attending_Goonj_PoC', $attendedId)
        ->execute();

      // Optional: log a Civi Activity
      Activity::create(FALSE)
        ->addValue('subject', $campCode ?: $title)
        ->addValue('activity_type_id:name', 'Organize Goonj Activities')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $initiatorId)
        ->addValue('target_contact_id', $organizationId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $campId)
        ->execute();

      echo "  📝 Logged activity for $campCode\n";

    } catch (\Throwable $e) {
      echo "  ❌ Error for $campCode (row $rowNum): " . $e->getMessage() . "\n";
      continue;
    }
  }

  fclose($fh);
  echo "=== Import complete ===\n";
}

main();