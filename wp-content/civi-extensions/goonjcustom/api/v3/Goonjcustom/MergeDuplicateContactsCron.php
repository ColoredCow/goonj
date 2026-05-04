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

  // Normalise Indian phone numbers to a canonical 10-digit form so different
  // formats (+91-9876543210, 91 98765 43210, 09876543210, 9876543210) all
  // group together.
  $normalizePhone = function ($raw) {
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
      $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
      $digits = substr($digits, 1);
    }
    return $digits;
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
    $contactId = (int) ($contact['contact_id'] ?? 0);
    $email = strtolower(trim($contact['email'] ?? ''));
    $firstName = strtolower(trim($contact['first_name'] ?? ''));
    $phone = $normalizePhone($contact['phone'] ?? '');
    $status = trim($contact['status'] ?? '');

    if (!$contactId || !$firstName || !in_array($status, ['Real', 'Duplicate'])) {
      continue;
    }

    // Per-row priority: prefer email, fall back to phone.
    if ($email) {
      $key = 'email:' . $email . '|' . $firstName;
      $matchedBy = 'email';
    }
    elseif ($phone) {
      $key = 'phone:' . $phone . '|' . $firstName;
      $matchedBy = 'phone';
    }
    else {
      $log('warning', "Row #$rowIndex (cid=$contactId) has neither email nor phone. Skipping.");
      continue;
    }

    if (!isset($groups[$key])) {
      $groups[$key] = ['real' => NULL, 'duplicates' => [], 'matched_by' => $matchedBy];
    }

    if ($status === 'Real') {
      $groups[$key]['real'] = $contactId;
    }
    else {
      $groups[$key]['duplicates'][] = $contactId;
    }
  }

  fclose($file);

  $emailGroupCount = count(array_filter($groups, fn($g) => $g['matched_by'] === 'email'));
  $phoneGroupCount = count(array_filter($groups, fn($g) => $g['matched_by'] === 'phone'));
  $log('info', "CSV parsed. Total groups: " . count($groups) . " (by email: $emailGroupCount, by phone: $phoneGroupCount)");

  $mergedCount = 0;
  $failedCount = 0;
  $skippedGroups = 0;
  $mergedByEmail = 0;
  $mergedByPhone = 0;

  foreach ($groups as $key => $data) {
    $realId = $data['real'];
    $duplicates = $data['duplicates'];
    $matchedBy = $data['matched_by'];

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
        if ($matchedBy === 'email') {
          $mergedByEmail++;
        }
        else {
          $mergedByPhone++;
        }
        $log('info', "MERGED [$matchedBy] dup #$dupId -> real #$realId ($key)");
      }
      catch (\Throwable $e) {
        $failedCount++;
        $log('error', "FAILED [$matchedBy] dup #$dupId -> real #$realId ($key): " . $e->getMessage());
      }
    }
  }

  $log('info', "Job finished. Merged: $mergedCount (email: $mergedByEmail, phone: $mergedByPhone) | Failed: $failedCount | Skipped groups: $skippedGroups");

  return civicrm_api3_create_success(
    [
      'log_file' => $logFile,
      'merged' => $mergedCount,
      'merged_by_email' => $mergedByEmail,
      'merged_by_phone' => $mergedByPhone,
      'failed' => $failedCount,
      'skipped_groups' => $skippedGroups,
    ],
    $params,
    'Goonjcustom',
    'merge_duplicate_contacts_cron'
  );
}
