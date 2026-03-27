<?php

/**
 * @file
 * Backfill script: Set You_wish_to_register_as field on existing institution camps
 * based on their organization's Type_of_Institution.
 *
 * Usage: sudo cv scr wp-content/civi-extensions/goonjcustom/cli/backfill-register-as-field.php
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Organization;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

$subtypeConfig = [
  'Institution_Collection_Camp' => [
    'org_field'        => 'Institution_Collection_Camp_Intent.Organization_Name',
    'register_as_field' => 'Institution_Collection_Camp_Intent.You_wish_to_register_as:name',
  ],
  'Institution_Dropping_Center' => [
    'org_field'        => 'Institution_Dropping_Center_Intent.Organization_Name',
    'register_as_field' => 'Institution_Dropping_Center_Intent.You_wish_to_register_as:name',
  ],
  'Institution_Goonj_Activities' => [
    'org_field'        => 'Institution_Goonj_Activities.Organization_Name',
    'register_as_field' => 'Institution_Goonj_Activities.You_wish_to_register_as:name',
  ],
];

$typeMapping = [
  'Corporate'             => 'Corporate',
  'Educational_Institute' => 'School',
  'Association'           => 'Association',
  'Foundation'            => 'Foundation',
  'Other'                 => 'Other',
];

$totalUpdated = 0;
$totalSkipped = 0;
$totalFailed  = 0;

foreach ($subtypeConfig as $subtype => $config) {
  echo "\nProcessing subtype: {$subtype}\n";

  $camps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id', $config['org_field'], $config['register_as_field'])
    ->addWhere('subtype:name', '=', $subtype)
    ->execute();

  echo "Found " . count($camps) . " camps\n";

  foreach ($camps as $camp) {
    $campId  = $camp['id'];
    $orgId   = $camp[$config['org_field']] ?? NULL;
    $current = $camp[$config['register_as_field']] ?? NULL;

    if (!$orgId) {
      echo "  [SKIP] Camp {$campId} — no organization linked\n";
      $totalSkipped++;
      continue;
    }

    if ($current) {
      echo "  [SKIP] Camp {$campId} — already set to '{$current}'\n";
      $totalSkipped++;
      continue;
    }

    try {
      $organization = Organization::get(FALSE)
        ->addSelect('Institute_Registration.Type_of_Institution:name')
        ->addWhere('id', '=', $orgId)
        ->execute()->first();

      $orgType    = $organization['Institute_Registration.Type_of_Institution:name'] ?? NULL;
      $registerAs = $typeMapping[$orgType] ?? NULL;

      if (!$registerAs) {
        echo "  [SKIP] Camp {$campId} — no mapping for org type '{$orgType}'\n";
        $totalSkipped++;
        continue;
      }

      EckEntity::update('Collection_Camp', FALSE)
        ->addValue($config['register_as_field'], $registerAs)
        ->addWhere('id', '=', $campId)
        ->execute();

      echo "  [OK] Camp {$campId} — set '{$registerAs}' (org type: {$orgType})\n";
      $totalUpdated++;
    }
    catch (\Exception $e) {
      echo "  [ERROR] Camp {$campId} — " . $e->getMessage() . "\n";
      $totalFailed++;
    }
  }
}

echo "\n--- Backfill complete ---\n";
echo "Updated: {$totalUpdated}\n";
echo "Skipped: {$totalSkipped}\n";
echo "Failed:  {$totalFailed}\n";
