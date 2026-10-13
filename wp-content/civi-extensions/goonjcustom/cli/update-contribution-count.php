<?php

/**
 * @file
 * CLI Script to recalculate the Footfall & Contribution Details of collection
 * sources (camps, dropping centres and Goonj activities).
 *
 * Recalculates all five fields from the material contributions and the
 * Completed monetary contributions linked to each collection source, using
 * the same logic as the save hooks (CollectionBaseService::recalculateContributionStats):
 * - Total Number of unique contributors
 * - Total Number of unique material contributors
 * - Total Number of unique monetary contributors
 * - Total online monetary contributions
 * - Total cash/cheque monetary contributions.
 *
 * Runs as a dry run by default: nothing is saved, and every collection source
 * whose stored values differ is listed in a CSV report.
 *
 * Collection sources whose values were typed in by hand on an outcome form
 * (for example the Institution Activity Outcome Form, where coordinators enter
 * the monetary totals themselves) are never changed; they are listed in the
 * report as "skipped: hand-entered" so they can be reviewed.
 *
 * Usage:
 *   Dry run, all collection sources:
 *     cv scr wp-content/civi-extensions/goonjcustom/cli/update-contribution-count.php
 *   Dry run, only some collection sources:
 *     IDS=13056,12987 cv scr wp-content/civi-extensions/goonjcustom/cli/update-contribution-count.php
 *   Save the changes:
 *     APPLY=1 cv scr wp-content/civi-extensions/goonjcustom/cli/update-contribution-count.php
 */

use Civi\Api4\AfformSubmission;
use Civi\Api4\EckEntity;
use Civi\CollectionBaseService;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('REPORT_PATH', sys_get_temp_dir() . '/contribution-count-report-' . date('Ymd-His') . '.csv');
define('BATCH_SIZE', 500);

$fields = [
  'Core_Contribution_Details.Number_of_unique_contributors',
  'Core_Contribution_Details.Number_of_unique_material_contributors',
  'Core_Contribution_Details.Number_of_unique_monetary_contributors',
  'Core_Contribution_Details.Total_online_monetary_contributions',
  'Core_Contribution_Details.Total_cash_cheque_monetary_contributions',
];

// Outcome forms on which the Footfall & Contribution Details can be typed in by hand.
$handEntryForms = [
  'afformAdminCampOutcomeForm',
  'afformAdminFormForEditingInstitutionCampOutcomes',
  'afformInstitutionActivityOutcomeForm',
  'afformInstitutionActivityOutcomeForm2',
  'afformInstitutionActivityOutcomeEdit',
  'afformUpdateCustom_Goonj_Activities_Outcome',
];

/**
 * Returns the ids of collection sources that have a Footfall & Contribution
 * Details value typed in on one of the given outcome forms.
 */
function getHandEnteredCollectionSourceIds(array $formNames): array {
  $ids = [];
  $submissions = AfformSubmission::get(FALSE)
    ->addSelect('data')
    ->addWhere('afform_name', 'IN', $formNames)
    ->execute();

  foreach ($submissions as $submission) {
    foreach ((array) $submission['data'] as $entityRows) {
      foreach ((array) $entityRows as $row) {
        $fields = $row['fields'] ?? [];
        if (empty($fields['id'])) {
          continue;
        }
        foreach ($fields as $name => $value) {
          if (str_starts_with($name, 'Core_Contribution_Details.') && $value !== NULL && $value !== '') {
            $ids[(int) $fields['id']] = TRUE;
            break;
          }
        }
      }
    }
  }

  return $ids;
}

$apply = getenv('APPLY') === '1';
$onlyIds = array_filter(array_map('intval', explode(',', (string) getenv('IDS'))));

echo ($apply ? 'APPLY' : 'DRY RUN') . ': recalculating Footfall & Contribution Details' . ($onlyIds ? ' for ' . implode(', ', $onlyIds) : ' for all collection sources') . "\n";

$report = fopen(REPORT_PATH, 'w');
$header = ['id', 'title', 'subtype', 'saved'];
foreach ($fields as $field) {
  $shortName = str_replace('Core_Contribution_Details.', '', $field);
  $header[] = "$shortName (stored)";
  $header[] = "$shortName (correct)";
}
fputcsv($report, $header);

$handEnteredIds = getHandEnteredCollectionSourceIds($handEntryForms);
echo 'Collection sources with hand-entered values (never changed): ' . count($handEnteredIds) . "\n";

$stats = ['checked' => 0, 'different' => 0, 'saved' => 0, 'skipped_hand_entered' => 0, 'errors' => 0];
$lastId = 0;

while (TRUE) {
  $query = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id', 'title', 'subtype:name', ...$fields)
    ->addWhere('id', '>', $lastId)
    ->addOrderBy('id')
    ->setLimit(BATCH_SIZE);

  if ($onlyIds) {
    $query->addWhere('id', 'IN', $onlyIds);
  }

  $collectionSources = $query->execute();
  if (!count($collectionSources)) {
    break;
  }

  foreach ($collectionSources as $collectionSource) {
    $lastId = $collectionSource['id'];
    $stats['checked']++;

    try {
      $correct = CollectionBaseService::recalculateContributionStats($collectionSource['id'], FALSE);
    }
    catch (\Exception $e) {
      $stats['errors']++;
      echo "Error on collection source {$collectionSource['id']}: {$e->getMessage()}\n";
      continue;
    }

    $isDifferent = FALSE;
    foreach ($fields as $field) {
      $stored = $collectionSource[$field];
      // Blank and 0 both show as empty on the View tab, so only a real
      // difference in value counts.
      if (abs((float) $stored - (float) $correct[$field]) > 0.001) {
        $isDifferent = TRUE;
        break;
      }
    }

    if (!$isDifferent) {
      continue;
    }

    $stats['different']++;
    $isHandEntered = isset($handEnteredIds[$collectionSource['id']]);

    if ($isHandEntered) {
      $stats['skipped_hand_entered']++;
      $saved = 'skipped: hand-entered';
    }
    elseif ($apply) {
      CollectionBaseService::recalculateContributionStats($collectionSource['id']);
      $stats['saved']++;
      $saved = 'yes';
    }
    else {
      $saved = 'no';
    }

    $row = [$collectionSource['id'], $collectionSource['title'], $collectionSource['subtype:name'], $saved];
    foreach ($fields as $field) {
      $row[] = $collectionSource[$field];
      $row[] = $correct[$field];
    }
    fputcsv($report, $row);
  }
}

fclose($report);

echo "Checked: {$stats['checked']}\n";
echo "Different from the correct values: {$stats['different']}\n";
echo "Saved: {$stats['saved']}\n";
echo "Skipped, hand-entered: {$stats['skipped_hand_entered']}\n";
echo "Errors: {$stats['errors']}\n";
echo "Report: " . REPORT_PATH . "\n";
