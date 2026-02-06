<?php

/**
 * @file
 * Migrates "Volunteer Camp Feedback" fields (stored on Collection Camp) into
 * new "Collection Source Feedback" entities via API4.
 *
 * Run via `cv scr` for CiviCRM environment setup.
 *
 * Example:
 *   cv scr ../civi-extensions/goonjcustom/cli/migrate-collection-camp-feedback.php --limit=200 | tee migrate-collection-camp-feedback.txt
 *
 * Options:
 *   --dry-run       Do not create anything (just print what would happen).
 *   --limit=N       Batch size per API call (default: 100).
 *   --start-after-id=N  Resume after this Collection Camp id (default: 0).
 *   --max=N         Stop after creating N feedback rows (default: no limit).
 *   --skip-existing Skip camps which already have feedback (default: create all).
 */

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Enable error reporting (avoid deprecation noise).
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');
ini_set('memory_limit', '256M');

/**
 * Parse CLI options in the form:
 *   --key=value
 *   --flag
 *
 * @param array $argv
 * @return array
 */
function parse_opts(array $argv): array {
  $opts = [];
  foreach ($argv as $arg) {
    if (strncmp($arg, '--', 2) !== 0) {
      continue;
    }
    $arg = substr($arg, 2);
    if ($arg === '') {
      continue;
    }
    if (strpos($arg, '=') !== FALSE) {
      [$key, $value] = explode('=', $arg, 2);
      $opts[$key] = $value;
    }
    else {
      $opts[$arg] = TRUE;
    }
  }
  return $opts;
}

function as_int($value, int $default): int {
  if ($value === NULL || $value === '') {
    return $default;
  }
  if (is_bool($value)) {
    return $value ? 1 : 0;
  }
  return max(0, (int) $value);
}

function get_existing_feedback_id_for_camp(int $collectionCampId): ?int {
  $existing = \Civi\Api4\EckEntity::get('Collection_Source_Feedback', FALSE)
    ->addSelect('id')
    ->addWhere('subtype:label', '=', 'Feedback')
    ->addWhere('Collection_Source_Feedback.Collection_Camp_Code', '=', $collectionCampId)
    ->setLimit(1)
    ->execute()
    ->first();

  return $existing['id'] ?? NULL;
}

function create_feedback_for_camp(array $collectionCamp, bool $dryRun): ?int {
  $collectionCampId = (int) ($collectionCamp['id'] ?? 0);
  if (!$collectionCampId) {
    return NULL;
  }

  $collectionCampArea = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'] ?? NULL;
  $giveRating = $collectionCamp['Volunteer_Camp_Feedback.Give_Rating_to_your_camp'] ?? NULL;
  $howManyRating = $collectionCamp['Volunteer_Camp_Feedback.How_many_rating_do_you_give_to_our_goonj_member_who_attended_cam'] ?? NULL;
  $facedDifficulty = $collectionCamp['Volunteer_Camp_Feedback.Do_you_faced_any_difficulty_challenges_while_organising_or_on_ca'] ?? NULL;
  $remarkFeedback = $collectionCamp['Volunteer_Camp_Feedback.Any_remark_feedback_for_Goonj_what_can_be_improve_'] ?? NULL;
  $photoByteMax = $collectionCamp['Volunteer_Camp_Feedback.Photo_Byte_Max_4_'] ?? NULL;
  $image2 = $collectionCamp['Volunteer_Camp_Feedback.Image_2'] ?? NULL;
  $image3 = $collectionCamp['Volunteer_Camp_Feedback.Image_3'] ?? NULL;
  $image4 = $collectionCamp['Volunteer_Camp_Feedback.Image_4'] ?? NULL;
  $image5 = $collectionCamp['Volunteer_Camp_Feedback.Image_5'] ?? NULL;
  $lastReminderSent = $collectionCamp['Volunteer_Camp_Feedback.Last_Reminder_Sent'] ?? NULL;
  $mostMeaningfulPart = $collectionCamp['Volunteer_Camp_Feedback.What_was_the_most_meaningful_part_of_this_engagement_for_you_'] ?? NULL;
  $howDidThisEngagementMakeYouFeel = $collectionCamp['Volunteer_Camp_Feedback.How_did_this_engagement_make_you_feel_and_why_'] ?? NULL;
  $filledBy = $collectionCamp['Volunteer_Camp_Feedback.Filled_by'] ?? NULL;

  if ($dryRun) {
    echo "[dry-run] create Collection_Source_Feedback for Collection_Camp id={$collectionCampId}\n";
    return 0;
  }

  $create = \Civi\Api4\EckEntity::create('Collection_Source_Feedback', FALSE)
    ->addValue('title', 'Collection Camp Volunteer Feedback')
    ->addValue('subtype:label', 'Feedback')
    ->addValue('Collection_Source_Feedback.Collection_Camp_Code', $collectionCampId);

  // Only set optional fields if present (avoid API errors on empty strings).
  $optionalValues = [
    'Collection_Source_Feedback.Collection_Camp_Address' => $collectionCampArea,
    'Collection_Source_Feedback.Rate_Your_Camp_Experience_1_Lowest_10_Highest_' => $giveRating,
    'Collection_Source_Feedback.Rate_the_Goonj_Team_Member_Who_Attended_the_Camp_1_Lowest_10_Hig' => $howManyRating,
    'Collection_Source_Feedback.Did_you_face_any_difficulties_or_challenges_while_organizing_or_' => $facedDifficulty,
    'Collection_Source_Feedback.Please_share_any_feedback_for_Goonj_including_areas_where_we_can' => $remarkFeedback,
    'Collection_Source_Feedback.Please_share_camp_photos_and_video' => $photoByteMax,
    'Collection_Source_Feedback.Image_2' => $image2,
    'Collection_Source_Feedback.Image_3' => $image3,
    'Collection_Source_Feedback.Image_4' => $image4,
    'Collection_Source_Feedback.Video' => $image5,
    'Collection_Source_Feedback.Filled_By' => $filledBy,
    'Collection_Source_Feedback.Last_Reminder_Sent' => $lastReminderSent,
    'Collection_Source_Feedback.What_was_the_most_meaningful_part_of_this_engagement_for_you_' => $mostMeaningfulPart,
    'Collection_Source_Feedback.How_did_this_engagement_make_you_feel_and_why_' => $howDidThisEngagementMakeYouFeel,
  ];

  foreach ($optionalValues as $field => $value) {
    if ($value !== NULL && $value !== '') {
      $create->addValue($field, $value);
    }
  }

  $result = $create->execute();
  return (int) ($result[0]['id'] ?? 0) ?: NULL;
}

function main(): void {
  global $argv;

  $opts = parse_opts($argv);

  if (!empty($opts['help']) || !empty($opts['h'])) {
    echo "Usage:\n";
    echo "  cv scr ../civi-extensions/goonjcustom/cli/migrate-collection-camp-feedback.php [--dry-run] [--skip-existing] [--limit=N] [--start-after-id=N] [--max=N]\n";
    exit(0);
  }

  $dryRun = !empty($opts['dry-run']);
  $skipExisting = !empty($opts['skip-existing']);
  $limit = as_int($opts['limit'] ?? NULL, 100);
  $startAfterId = as_int($opts['start-after-id'] ?? NULL, 0);
  $maxCreates = isset($opts['max']) ? as_int($opts['max'], 0) : NULL;

  echo "Starting migrate-collection-camp-feedback\n";
  echo "Options: dry-run=" . ($dryRun ? 'yes' : 'no') . ", skip-existing=" . ($skipExisting ? 'yes' : 'no') . ", limit={$limit}, start-after-id={$startAfterId}";
  if ($maxCreates !== NULL) {
    echo ", max={$maxCreates}";
  }
  echo "\n";

  $processed = 0;
  $created = 0;
  $skippedExisting = 0;
  $skippedNoRating = 0;
  $errors = 0;

  $lastSeenId = $startAfterId;

  while (TRUE) {
    $collectionCamps = \Civi\Api4\EckEntity::get('Collection_Camp', FALSE)
      ->addSelect(
        'id',
        'Collection_Camp_Intent_Details.Location_Area_of_camp',
        'Volunteer_Camp_Feedback.Give_Rating_to_your_camp',
        'Volunteer_Camp_Feedback.How_many_rating_do_you_give_to_our_goonj_member_who_attended_cam',
        'Volunteer_Camp_Feedback.Do_you_faced_any_difficulty_challenges_while_organising_or_on_ca',
        'Volunteer_Camp_Feedback.Any_remark_feedback_for_Goonj_what_can_be_improve_',
        'Volunteer_Camp_Feedback.Photo_Byte_Max_4_',
        'Volunteer_Camp_Feedback.Image_2',
        'Volunteer_Camp_Feedback.Image_3',
        'Volunteer_Camp_Feedback.Image_4',
        'Volunteer_Camp_Feedback.Image_5',
        'Volunteer_Camp_Feedback.Last_Reminder_Sent',
        'Volunteer_Camp_Feedback.What_was_the_most_meaningful_part_of_this_engagement_for_you_',
        'Volunteer_Camp_Feedback.How_did_this_engagement_make_you_feel_and_why_',
        'Volunteer_Camp_Feedback.Filled_by',
        'title'
      )
      ->addWhere('subtype:name', '=', 'Collection_Camp')
      // Only camps where rating exists (this was the original filter).
      ->addWhere('Volunteer_Camp_Feedback.Give_Rating_to_your_camp', 'IS NOT NULL')
      ->addWhere('id', '>', $lastSeenId)
      ->addOrderBy('id', 'ASC')
      ->setLimit($limit)
      ->execute();

    if (count($collectionCamps) === 0) {
      break;
    }

    $maxIdInBatch = $lastSeenId;
    foreach ($collectionCamps as $collectionCamp) {
      $processed++;
      $campId = (int) ($collectionCamp['id'] ?? 0);
      $campTitle = (string) ($collectionCamp['title'] ?? '');
      if ($campId > $maxIdInBatch) {
        $maxIdInBatch = $campId;
      }

      $rating = $collectionCamp['Volunteer_Camp_Feedback.Give_Rating_to_your_camp'] ?? NULL;
      if ($rating === NULL || $rating === '') {
        $skippedNoRating++;
        continue;
      }

      try {
        if ($skipExisting) {
          $existingId = get_existing_feedback_id_for_camp($campId);
          if ($existingId) {
            $skippedExisting++;
            echo "Skip (exists) camp_id={$campId} feedback_id={$existingId} title=\"{$campTitle}\"\n";
            continue;
          }
        }

        $newId = create_feedback_for_camp($collectionCamp, $dryRun);
        $created++;

        if ($dryRun) {
          echo "OK (dry-run) camp_id={$campId} title=\"{$campTitle}\"\n";
        }
        else {
          echo "Created feedback_id={$newId} for camp_id={$campId} title=\"{$campTitle}\"\n";
        }

        if ($maxCreates !== NULL && $maxCreates > 0 && $created >= $maxCreates) {
          echo "Reached max={$maxCreates}, stopping.\n";
          break 2;
        }
      }
      catch (\Throwable $e) {
        $errors++;
        echo "ERROR camp_id={$campId} title=\"{$campTitle}\": " . $e->getMessage() . "\n";
      }
    }

    if ($maxIdInBatch <= $lastSeenId) {
      echo "WARNING: paging did not advance (lastSeenId={$lastSeenId}, maxIdInBatch={$maxIdInBatch}). Stopping to avoid infinite loop.\n";
      break;
    }

    $lastSeenId = $maxIdInBatch;
    echo "Progress: processed={$processed}, created={$created}, skipped_existing={$skippedExisting}, errors={$errors}, next_start_after_id={$lastSeenId}\n";
  }

  echo "Done. processed={$processed}, created={$created}, skipped_existing={$skippedExisting}, skipped_no_rating={$skippedNoRating}, errors={$errors}\n";
}

main();
