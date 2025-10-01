<?php

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/** Helpers */
function parse_date_ddmmyy(?string $s): string {
  $s = trim((string)$s);
  if ($s === '') return '';
  $s = str_replace('-', '/', $s);
  $parts = explode('/', $s);
  if (count($parts) !== 3) return '';
  [$d, $m, $y] = $parts;
  $d = (int)$d; $m = (int)$m; $y = (int)$y;
  if ($y < 100) $y += 2000;
  if (!checkdate($m, $d, $y)) return '';
  return sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d);
}
function as_int($v): ?int {
  $v = trim((string)$v);
  if ($v === '' || !is_numeric($v)) return null;
  return (int)round((float)$v);
}
function as_float($v): ?float {
  $v = trim((string)$v);
  if ($v === '') return null;
  $v = str_replace(',', '', $v);
  if (!is_numeric($v)) return null;
  return (float)$v;
}
function get_office_id($office_name) {
  $office_name = trim((string)$office_name);
  if ($office_name === '') return '';
  $row = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_office')
    ->addWhere('display_name', 'LIKE', '%' . $office_name)
    ->setLimit(1)
    ->execute()
    ->first();
  return $row['id'] ?? '';
}

function main() {
  $csvFilePath = '/var/www/html/crm.goonj.org/wp-content/civi-extensions/goonjcustom/cli//Users/shubhambelwal/Sites/goonj/wp-content/civi-extensions/goonjcustom/cli/Dropping Center Import Data  - dispatch (1).csv';

  if (!file_exists($csvFilePath)) {
    exit("❌ File not found: $csvFilePath\n");
  }
  $handle = fopen($csvFilePath, 'r');
  if ($handle === FALSE) {
    exit("❌ Unable to open CSV file.\n");
  }

  $header = fgetcsv($handle, 0, ',', '"', '\\');
  if ($header === FALSE) {
    fclose($handle);
    exit("❌ Unable to read header row.\n");
  }
  $header = array_map('trim', $header);

  $rowNum = 1;
  while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
    $rowNum++;
    if (count($row) !== count($header)) {
      echo "⚠️  Row $rowNum skipped (column mismatch)\n";
      continue;
    }

    $data = array_combine($header, $row);
    $campCode = trim($data['Dropping Center Code'] ?? '');
    if ($campCode === '') {
      echo "⚠️  Row $rowNum skipped (no Camp Code)\n";
      continue;
    }

    try {
      $camp = EckEntity::get('Collection_Camp', TRUE)
        ->addSelect('id')
        ->addWhere('title', '=', $campCode)
        ->setLimit(1)
        ->execute()
        ->first();
      if (empty($camp['id'])) {
        echo "⚠️  Row $rowNum skipped (Camp not found: $campCode)\n";
        continue;
      }

      $dispatchDate = parse_date_ddmmyy($data['Date of Material Collection (DD/MM/YY)'] ?? '');
      $bags         = as_int($data['Number of Bags loaded in vehicle'] ?? '');
      $weightKg     = as_float($data['Material weight (In KGs)'] ?? '');
      $vehicleCat   = trim($data['Vehicle Category'] ?? '');
      $howFull      = trim($data['How much vehicle is filled'] ?? '');
      $typeVal      = trim($data['Type'] ?? '');
      $puCenterId   = get_office_id($data['To which PU/Center material is being sent'] ?? '');

      $create = EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addValue('title', 'Dropping Center Vehicle Dispatch')
        ->addValue('subtype', 'Vehicle Dispatch')
        ->addValue('Camp_Vehicle_Dispatch.Dropping_Center', $camp['id']);

      if ($dispatchDate) $create->addValue('Camp_Vehicle_Dispatch.Date_Time_of_Dispatch', $dispatchDate);
      if ($bags !== null) $create->addValue('Camp_Vehicle_Dispatch.Number_of_Bags_loaded_in_vehicle', $bags);
      if ($weightKg !== null) $create->addValue('Camp_Vehicle_Dispatch.Material_weight_In_KGs_', $weightKg);
      if ($vehicleCat !== '') $create->addValue('Camp_Vehicle_Dispatch.Vehicle_Category', $vehicleCat);
      if ($howFull !== '') $create->addValue('Camp_Vehicle_Dispatch.How_much_vehicle_is_filled', $howFull);
      if ($typeVal !== '') $create->addValue('Camp_Vehicle_Dispatch.Type', $typeVal);
      if ($puCenterId !== '') $create->addValue('Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent', $puCenterId);

      // NEW: Acknowledgement fields
      $create->addValue('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'Imported Dispatches');
      if ($dispatchDate) {
        $create->addValue('Acknowledgement_For_Logistics.Contribution_Date', $dispatchDate);
      }

      $res = $create->execute()->first();
      echo "✅ Row $rowNum: Dispatch created for Camp $campCode (ID: {$res['id']})\n";

    } catch (\Throwable $e) {
      echo "❌ Row $rowNum (Camp $campCode): " . $e->getMessage() . "\n";
    }
  }

  fclose($handle);
}

main();