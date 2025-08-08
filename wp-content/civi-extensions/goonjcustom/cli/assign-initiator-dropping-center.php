<?php

/**
 * @file
 * CLI script to create Vehicle Dispatch records from a CSV for Dropping Centers.
 * Looks up the parent Collection_Camp by "Dropping Center Code" (title).
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Contact;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/** Optional: quiet down CLI deprecation spam (upgrade plugins for a real fix). */
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
  foreach ($aliases as $alias) {
    $key = $hmap[norm($alias)] ?? null;
    if ($key !== null && array_key_exists($key, $row)) {
      $val = trim((string)$row[$key]);
      if ($val !== '') return $val;
    }
  }
  return null;
}

/** Convert "DD/MM/YY" or "DD/MM/YYYY" -> "YYYY-MM-DD" */
function dmy_slash_to_ymd(?string $d): ?string {
  $d = trim((string)$d);
  if ($d === '') return null;
  $dt = \DateTime::createFromFormat('d/m/y', $d) ?: \DateTime::createFromFormat('d/m/Y', $d);
  return $dt ? $dt->format('Y-m-d') : null;
}

/** Parse integers/floats; return null if not numeric (after stripping commas/spaces). */
function clean_number(?string $val): ?float {
  $val = trim((string)$val);
  if ($val === '') return null;
  $val = str_replace([",", " "], "", $val);
  return is_numeric($val) ? (float)$val : null;
}

/** Lookup office contact id by display name and subtype Goonj_Office */
function get_office_id(?string $office_name): ?int {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return null;

  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office') // correct subtype case
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->execute()
    ->first();

  return isset($row['id']) ? (int)$row['id'] : null;
}

/* =========================
 * Main
 * ========================= */

function main(): void {
  // TODO: update path
  $csvFilePath = '/var/www/html/wp-content/civi-extensions/goonjcustom/cli/Final data cleanups - goonj activities contact (8).csv';

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

  /* Column aliases (handles minor naming drift) */
  $COL = [
    'camp_code'         => ['Dropping Center Code', 'Dropping Centre Code', 'Dropping Center code'],
    'collection_date'   => ['Date of Material Collection (DD/MM/YY)', 'Date of Material Collection (DD/MM/YYYY)', 'Date of Material Collection'],
    'bags_count'        => ['Number of Bags loaded in vehicle', 'Number of Bags Loaded in Vehicle'],
    'weight_kg'         => ['Material weight (In KGs)', 'Material Weight (in KGs)', 'Total Weight of Material Collected (Kg)'],
    'vehicle_category'  => ['Vehicle Category', 'Vehicle Category of material collected'],
    'fill_level'        => ['How much vehicle is filled', 'How much vehicle is filled '],
    'destination'       => ['To which PU/Center material is being sent', 'To which PU Center material is being sent'],
  ];

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "Row $rowNum: column mismatch, skipping.\n";
      continue;
    }
    $data = array_combine($header, $row) ?: [];

    // Read values
    $campCode   = getv($data, $hmap, $COL['camp_code']);
    if (!$campCode) {
      echo "⚠️  Row $rowNum: missing Dropping Center Code — skipping.\n";
      continue;
    }

    $dateRaw    = getv($data, $hmap, $COL['collection_date']);
    $bagsRaw    = getv($data, $hmap, $COL['bags_count']);   // may be "Imported Dispatches" -> becomes null
    $weightRaw  = getv($data, $hmap, $COL['weight_kg']);
    $vehCat     = getv($data, $hmap, $COL['vehicle_category']);
    $fillLevel  = getv($data, $hmap, $COL['fill_level']);
    $destName   = getv($data, $hmap, $COL['destination']);

    $bags   = clean_number($bagsRaw);
    $weight = clean_number($weightRaw);

    // Find parent camp by title
    $camp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id', 'title')
      ->addWhere('title', '=', $campCode)
      ->setLimit(1)
      ->execute()
      ->first();

    $campId = $camp['id'] ?? null;
    if (!$campId) {
      echo "❌ Row $rowNum ($campCode): parent camp not found — skipping.\n";
      continue;
    }

    // Convert collection date for created_date (entity expects Y-m-d H:i:s)
    $collectionDateYmd = dmy_slash_to_ymd($dateRaw);
    $createdDateTime = $collectionDateYmd ? ($collectionDateYmd . ' 00:00:00') : date('Y-m-d H:i:s');

    // Destination: try to resolve to office ID; if not found, keep the name
    $destIdOrName = null;
    if ($destName !== null && $destName !== '') {
      $oid = get_office_id($destName);
      $destIdOrName = $oid ?? $destName;
    }

    try {
      $create = EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', $campCode)
        ->addValue('subtype:label', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Institution_Dropping_Center', $campId)
        ->addValue('Camp_Vehicle_Dispatch.Number_of_Bags_loaded_in_vehicle', 'Imported Dispatches')  
        ->addValue('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Imported Dispatches')       // numeric or null
        ->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $weight)                 // numeric or null
        ->addValue('Camp_Vehicle_Dispatch.Vehicle_Category:label', $vehCat)
        ->addValue('Camp_Vehicle_Dispatch.How_much_vehicle_is_filled:label', $fillLevel)
        ->addValue('Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent', $destIdOrName)
        ->addValue('created_date', $createdDateTime);

      $result = $create->execute();
      $childId = $result[0]['id'] ?? null;

      echo "✅ Row $rowNum ($campCode): dispatch created"
         . ($childId ? " (id: $childId)" : "")
         . ".\n";
    } catch (\Throwable $e) {
      echo "❌ Row $rowNum ($campCode): " . $e->getMessage() . "\n";
      continue;
    }
  }

  fclose($handle);
  echo "=== Done ===\n";
}

main();