<?php

/**
 * Goonjcustom.MergeDuplicateContactsCron API.
 *
 * Merges duplicate contacts into real ones using data from a local CSV file.
 *
 * Execution mode:
 *  - When invoked from a web context (e.g. the CiviCRM Scheduled Jobs
 *    "Execute now" button on mod_php), the function spawns a detached
 *    `cv api` worker and returns immediately. The admin's browser is not
 *    held while the merge runs, so large CSVs (1000-2000+ contacts) will
 *    not time the page out.
 *  - When invoked from CLI (cv / system cron) or with `_background=1`,
 *    the function performs the actual merge work.
 *
 * Each run writes a timestamped log file under
 * `wp-content/uploads/civicrm-logs/merge-duplicates/` capturing every
 * merge, skip, and failure for later audit.
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_goonjcustom_merge_duplicate_contacts_cron($params) {
  $logDir = rtrim(\CRM_Core_Config::singleton()->configAndLogDir, '/') . '/merge-duplicates';
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, TRUE);
  }

  // Reuse the log file path from the spawning request when running as a
  // background worker, so spawn + worker write to the same file. Otherwise
  // generate a fresh timestamped filename.
  $logFile = !empty($params['_logfile'])
    ? $params['_logfile']
    : $logDir . '/merge-' . date('Y-m-d_His') . '.log';

  $isCli = (PHP_SAPI === 'cli');
  $isBackgroundWorker = !empty($params['_background']);

  if (!$isCli && !$isBackgroundWorker) {
    $cvPath = file_exists('/usr/local/bin/cv') ? '/usr/local/bin/cv' : 'cv';
    $wpRoot = rtrim(ABSPATH, '/');

    $cmd = sprintf(
      '( cd %s && PATH=/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin %s api Goonjcustom.merge_duplicate_contacts_cron _background=1 _logfile=%s < /dev/null > /dev/null 2>>%s & )',
      escapeshellarg($wpRoot),
      escapeshellarg($cvPath),
      escapeshellarg($logFile),
      escapeshellarg($logFile)
    );
    exec($cmd);

    \Civi::log()->info("[MergeDuplicatesCron] Spawned background worker. Log: $logFile");

    return civicrm_api3_create_success(
      [
        'status' => 'Started in background',
        'log_file' => $logFile,
        'message' => 'Merge is running in the background. Check the log file for progress.',
      ],
      $params,
      'Goonjcustom',
      'merge_duplicate_contacts_cron'
    );
  }

  set_time_limit(0);
  ignore_user_abort(TRUE);
  ini_set('memory_limit', '1024M');

  $log = function ($level, $msg) use ($logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg;
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    $civiLevel = in_array($level, ['info', 'warning', 'error', 'debug']) ? $level : 'info';
    \Civi::log()->{$civiLevel}('[MergeDuplicatesCron] ' . $msg);
  };

  $log('info', 'Job started (background worker, PID ' . getmypid() . ')');

  $csvPath = ABSPATH . 'wp-content/uploads/2026/05/Copy-of-uttarkhand-Duplicate-Sheet1.csv';
  $log('info', "CSV path: $csvPath");

  if (!file_exists($csvPath)) {
    $log('error', "File not found at: $csvPath");
    return civicrm_api3_create_error("File not found at: $csvPath");
  }

  $file = fopen($csvPath, 'r');
  if (!$file) {
    $log('error', 'Unable to open the CSV file.');
    return civicrm_api3_create_error("Unable to open the CSV file.");
  }

  $header = fgetcsv($file, 0, ",", '"', "\\");
  if (!$header || count($header) < 2 || in_array('<!DOCTYPE html>', $header)) {
    fclose($file);
    $log('error', 'Invalid or malformed CSV header.');
    return civicrm_api3_create_error("Invalid or malformed CSV header.");
  }

  $groups = [];
  $rowIndex = 1;

  while (($row = fgetcsv($file, 0, ",", '"', "\\")) !== FALSE) {
    $rowIndex++;
    if (count($row) !== count($header)) {
      $log('warning', "Row #$rowIndex column count mismatch. Skipping row.");
      continue;
    }

    $contact = array_change_key_case(array_combine($header, $row), CASE_LOWER);
    $email = strtolower(trim($contact['email'] ?? ''));
    $firstName = strtolower(trim($contact['first_name'] ?? ''));
    $status = trim($contact['status'] ?? '');

    $key = $email . '|' . $firstName;

    if (!$email || !$firstName || !in_array($status, ['Real', 'Duplicate'])) {
      continue;
    }

    if (!isset($groups[$key])) {
      $groups[$key] = ['real' => NULL, 'duplicates' => [], 'email' => $email];
    }

    if ($status === 'Real') {
      $groups[$key]['real'] = (int) $contact['contact_id'];
    }
    else {
      $groups[$key]['duplicates'][] = (int) $contact['contact_id'];
    }
  }

  fclose($file);

  $log('info', 'CSV parsed. Total groups: ' . count($groups));

  $mergedCount = 0;
  $failedCount = 0;
  $skippedGroups = 0;

  foreach ($groups as $key => $data) {
    $realId = $data['real'];
    $duplicates = $data['duplicates'];

    if (!$realId || empty($duplicates)) {
      $skippedGroups++;
      continue;
    }

    foreach ($duplicates as $dupId) {
      try {
        \Civi\Api4\Contact::mergeDuplicates(FALSE)
          ->setContactId($realId)
          ->setDuplicateId($dupId)
          ->setMode('safe')
          ->execute();

        $mergedCount++;
        $log('info', "MERGED dup #$dupId -> real #$realId ($key)");
      }
      catch (Exception $e) {
        $failedCount++;
        $log('error', "FAILED dup #$dupId -> real #$realId ($key): " . $e->getMessage());
      }
    }
  }

  $log('info', "Job finished. Merged: $mergedCount | Failed: $failedCount | Skipped groups: $skippedGroups");

  return civicrm_api3_create_success(
    [
      'log_file' => $logFile,
      'merged' => $mergedCount,
      'failed' => $failedCount,
      'skipped_groups' => $skippedGroups,
    ],
    $params,
    'Goonjcustom',
    'merge_duplicate_contacts_cron'
  );
}
