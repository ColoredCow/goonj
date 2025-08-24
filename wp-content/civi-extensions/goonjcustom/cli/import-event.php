<?php
/**
 * CLI: Import Goonj Events from CSV with Location (Address) creation
 * Usage: php import_goonj_events.php
 */

use Civi\Api4\Event;
use Civi\Api4\Contact;
use Civi\Api4\Address;
use Civi\Api4\LocBlock;
use Civi\Api4\StateProvince;
use Civi\Api4\Country;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/** ───── helpers ───── */
function logln(string $msg): void { echo $msg . PHP_EOL; }
function col(array $row, string $key): string { return trim((string)($row[$key] ?? '')); }

function get_office_id(?string $office_name): string {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return '';
  $rec = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name . '%')
    ->execute()
    ->first();
  return $rec['id'] ?? '';
}

function get_contact_id_by_email(?string $email): string {
  $email = trim((string)$email);
  if ($email === '') return '';
  $rec = Contact::get(FALSE)
    ->addSelect('id')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('email.email', '=', $email)
    ->execute()
    ->first();
  return $rec['id'] ?? '';
}

function resolve_country_id(string $countryName = 'India'): ?int {
  $rec = Country::get(FALSE)
    ->addSelect('id')
    ->addWhere('name', '=', $countryName)
    ->execute()
    ->first();
  return isset($rec['id']) ? (int)$rec['id'] : null;
}

/**
 * Try multiple ways to resolve a state under a given country.
 * Accepts "Delhi", "DL", etc.
 */
function resolve_state_id(?string $stateNameOrAbbr, ?int $countryId): ?int {
  $s = trim((string)$stateNameOrAbbr);
  if ($s === '') return null;

  // 1) exact name within country
  $q = StateProvince::get(FALSE)
    ->addSelect('id')
    ->addWhere('country_id', '=', $countryId)
    ->addWhere('name', '=', $s)
    ->execute()
    ->first();
  if (!empty($q['id'])) return (int)$q['id'];

  // 2) abbreviation within country
  $q = StateProvince::get(FALSE)
    ->addSelect('id')
    ->addWhere('country_id', '=', $countryId)
    ->addWhere('abbreviation', '=', strtoupper($s))
    ->execute()
    ->first();
  if (!empty($q['id'])) return (int)$q['id'];

  // 3) loose LIKE on name within country
  $q = StateProvince::get(FALSE)
    ->addSelect('id')
    ->addWhere('country_id', '=', $countryId)
    ->addWhere('name', 'LIKE', '%' . $s . '%')
    ->execute()
    ->first();
  if (!empty($q['id'])) return (int)$q['id'];

  return null;
}

/** ───── main ───── */
function main(): void {
  // ⬇️ change this path if needed
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli/Event Import Data - Sheet1.csv';

  logln("CSV File: $csvFilePath");
  if (!file_exists($csvFilePath)) exit("Error: File not found.\n");
  $handle = fopen($csvFilePath, 'r');
  if ($handle === FALSE) exit("Error: Unable to open CSV file.\n");

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) { fclose($handle); exit("Error: Unable to read header row.\n"); }

  // Resolve static country once
  $countryId = resolve_country_id('India');

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) { logln("❌ Row $rowNum: column mismatch. Skipping."); continue; }
    $data = array_combine($header, $row);

    // Core
    $title              = col($data, 'Event Title');
    $eventTypeRaw       = col($data, 'Event Type');
    $createdDate        = col($data, 'Created Date (DD-MM-YYYY)');
     
 	$startDate = col($data, 'Start Date (DD-MM-YYYY)');
 	$endDate = col($data, 'End Date (DD-MM-YYYY)');
    $defaultRoleName    = col($data, 'Default Role');
    $maxParticipantsRaw = col($data, 'Number of Participants');

    // Custom
    $agenda        = col($data, 'Agenda/Theme and Flow of the meeting');
    $productSale   = col($data, 'Total Product Sale');
    $totalContrib  = col($data, 'Total Amount Contributed');
    $venueFeedback = col($data, 'Feedback of the meet');

    $coordOfficeName = col($data, 'Coordinating Goonj Office');
    $coordOfficeId   = get_office_id($coordOfficeName);

    $pocEmail    = col($data, 'Goonj Coordinating POC (Main)');
    $pocContactId= get_contact_id_by_email($pocEmail);

    $venue = col($data, 'Event Venue'); 
    $city  = col($data, 'City');
    $state = col($data, 'State');
    $stateId = resolve_state_id($state, $countryId);

    // Coerce numerics
    $eventTypeId     = $eventTypeRaw !== '' ? (int)$eventTypeRaw : 9; // default to 9
    $maxParticipants = $maxParticipantsRaw !== '' ? (int)$maxParticipantsRaw : null;

    try {
      // 1) Create Address if we have any location info
      $locBlockId = null;
      if ($venue !== '' || $city !== '' || $stateId || $countryId) {
        $addrRes = Address::create(FALSE)
          ->addValue('street_address', $venue !== '' ? $venue : null)
          ->addValue('city', $city !== '' ? $city : null)
          ->addValue('state_province_id', $stateId)
          ->addValue('country_id', $countryId)
          // ->addValue('postal_code', null) // add if you later map a CSV column
          ->execute();

        $addressId = $addrRes[0]['id'] ?? null;

        if ($addressId) {
          $lbRes = LocBlock::create(FALSE)
            ->addValue('address_id', $addressId)
            ->execute();
          $locBlockId = $lbRes[0]['id'] ?? null;
        }
      }

      $ev = Event::create(FALSE)
        ->addValue('title', $title)
        ->addValue('event_type_id', $eventTypeId)
        ->addValue('default_role_id:name', $defaultRoleName ?: null)
        ->addValue('max_participants', $maxParticipants)
        ->addValue('start_date', $startDate)
        ->addValue('end_date',   $endDate)
        ->addValue('created_date', $createdDate);
      
        if ($locBlockId) {
        $ev->addValue('loc_block_id', $locBlockId);
      }
      $ev
        ->addValue('Goonj_Events.Goonj_Coordinating_POC_Main_', $pocContactId ?: null)
        ->addValue('Goonj_Events_Outcome.Outcome_Email_Sent', TRUE)
        ->addValue('Goonj_Events_Outcome.Outcome_Email_Sent_Date', $endDate)
        ->addValue('Goonj_Events.Agenda', $agenda !== '' ? $agenda : null)
        ->addValue('Goonj_Events_Outcome.Product_Sale_Amount', $productSale !== '' ? $productSale : null)
        ->addValue('Goonj_Events_Outcome.Online_Monetary_Contribution', $totalContrib !== '' ? $totalContrib : null)
        ->addValue('Goonj_Events_Outcome.Feedback_of_the_Venue', $venueFeedback !== '' ? $venueFeedback : null)
        ->addValue('Goonj_Events_Feedback.Last_Reminder_Sent', TRUE)
        ->addValue('Goonj_Events.Goonj_Coordinating_POC', $coordOfficeId !== '' ? $coordOfficeId : null);

      $result = $ev->execute();
      $newId = $result[0]['id'] ?? null;

      if ($newId) logln("✅ Row $rowNum: created Event id {$newId} — '{$title}'");
      else       logln("❌ Row $rowNum: Event created but no id returned for '{$title}'");

    } catch (\Throwable $e) {
      logln("❌ Row $rowNum: Error creating Event '{$title}': " . $e->getMessage());
    }
  }

  fclose($handle);
  logln("Done.");
}

main();