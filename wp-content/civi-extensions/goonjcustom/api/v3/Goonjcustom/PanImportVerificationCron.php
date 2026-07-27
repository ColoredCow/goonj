<?php

/**
 * @file
 */

use Civi\Api4\Contribution;
use Civi\PanVerificationService;

/**
 * Goonjcustom.PanImportVerificationCron API specification.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 */
function _civicrm_api3_goonjcustom_pan_import_verification_cron_spec(&$spec) {
  // No public parameters. `_background` and `_logfile` are internal handoff
  // params used when the web trigger spawns the CLI worker.
}

/**
 * Goonjcustom.PanImportVerificationCron API.
 *
 * Reconciles PAN cards for contributions brought in through the accounts-team
 * import. Each imported contribution is flagged
 * `PAN_Import.Pending_PAN_Verification = Yes`; this job reads those rows,
 * compares the contribution PAN against the contact PAN, applies the agreed
 * case logic, and flips the flag to `No` once a row is handled.
 *
 * Cases implemented so far:
 *  - Case 1: contribution PAN == contact PAN AND contact = Verified -> nothing
 *    to change; just mark the row processed.
 *
 * Cases 2-5 are added incrementally. Until a case is implemented, rows that
 * don't match an implemented case are left as Pending = Yes for a later run.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_pan_import_verification_cron($params) {
  $logDir = rtrim(\CRM_Core_Config::singleton()->configAndLogDir, '/') . '/pan-import-verification';
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, TRUE);
  }

  // Reuse the spawning request's log file when running as the background
  // worker, so spawn + worker write to the same file.
  $logFile = !empty($params['_logfile'])
    ? $params['_logfile']
    : $logDir . '/pan-import-' . date('Y-m-d_His') . '.log';

  $isCli = (PHP_SAPI === 'cli');
  $isBackgroundWorker = !empty($params['_background']);

  // Web trigger (e.g. Scheduled Jobs "Execute now" on mod_php): spawn a
  // detached CLI worker and return immediately so the page never times out.
  if (!$isCli && !$isBackgroundWorker) {
    $cvPath = file_exists('/usr/local/bin/cv') ? '/usr/local/bin/cv' : 'cv';
    $wpRoot = rtrim(ABSPATH, '/');

    $cmd = sprintf(
      '( cd %s && HOME=/tmp PATH=/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin %s api Goonjcustom.pan_import_verification_cron _background=1 _logfile=%s < /dev/null > /dev/null 2>>%s & )',
      escapeshellarg($wpRoot),
      escapeshellarg($cvPath),
      escapeshellarg($logFile),
      escapeshellarg($logFile)
    );
    $execOutput = [];
    $execExit = NULL;
    exec($cmd, $execOutput, $execExit);

    if ($execExit === 0) {
      @file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] [INFO] Spawned background worker via cv api.' . PHP_EOL,
        FILE_APPEND
      );
      return civicrm_api3_create_success(
        [
          'status' => 'Started in background',
          'log_file' => $logFile,
          'message' => 'PAN import verification is running in the background. Check the log file for progress.',
        ],
        $params,
        'Goonjcustom',
        'pan_import_verification_cron'
      );
    }

    @file_put_contents(
      $logFile,
      sprintf('[%s] [ERROR] Failed to spawn background worker. Shell exit code: %d. Output: %s%s',
        date('Y-m-d H:i:s'), (int) $execExit, implode(' | ', $execOutput), PHP_EOL),
      FILE_APPEND
    );
    return civicrm_api3_create_error("Failed to spawn PAN import worker. See log: $logFile");
  }

  set_time_limit(0);
  ignore_user_abort(TRUE);
  ini_set('memory_limit', '1024M');

  $log = function ($level, $msg) use ($logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg;
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
  };

  $log('info', 'Job started (background worker, PID ' . getmypid() . ')');

  try {
    // Fetch every contribution flagged for PAN verification.
    $pending = Contribution::get(FALSE)
      ->addSelect('id', 'contact_id', 'Contribution_Details.PAN_Card_Number')
      ->addWhere('PAN_Import.Pending_PAN_Verification', '=', TRUE)
      ->execute();

    // Group pending contributions by contact — one contact can have several,
    // and they must all be read together before deciding.
    $byContact = [];
    foreach ($pending as $row) {
      $byContact[(int) $row['contact_id']][] = $row;
    }

    $log('info', 'Pending contributions: ' . $pending->count() . ' across ' . count($byContact) . ' contact(s).');

    $counters = ['case1' => 0, 'unhandled' => 0];

    foreach ($byContact as $contactId => $contributions) {
      $contactPanData = PanVerificationService::getContactPan($contactId);
      $contactPan = strtoupper(trim($contactPanData['pan_number'] ?? ''));
      $contactStatus = $contactPanData['pan_status'] ?? NULL;

      foreach ($contributions as $contribution) {
        $contributionId = (int) $contribution['id'];
        $contributionPan = strtoupper(trim($contribution['Contribution_Details.PAN_Card_Number'] ?? ''));

        // CASE 1 — contribution PAN matches the contact PAN AND the contact
        // PAN is already Verified. The record is already correct, so there is
        // nothing to change: no Surepass call, no PAN write. We only mark the
        // row processed so it drops out of future runs.
        if (
          $contributionPan !== ''
          && $contributionPan === $contactPan
          && $contactStatus === PanVerificationService::PAN_STATUS_VERIFIED
        ) {
          _goonjcustom_pan_import_mark_processed($contributionId);
          $counters['case1']++;
          $log('info', "CASE 1 contribution #$contributionId (contact #$contactId): PAN matches verified contact PAN ($contactPan) — no change, marked processed.");
          continue;
        }

        // No implemented case matched yet — leave Pending = Yes so it is picked
        // up once the relevant case is built.
        $counters['unhandled']++;
        $log('info', "SKIP contribution #$contributionId (contact #$contactId): no implemented case matched yet — left as Pending.");
      }
    }

    $log('info', "Job finished. Case 1: {$counters['case1']} | Left pending (unhandled cases): {$counters['unhandled']}");

    return civicrm_api3_create_success(
      [
        'log_file' => $logFile,
        'case1' => $counters['case1'],
        'unhandled' => $counters['unhandled'],
      ],
      $params,
      'Goonjcustom',
      'pan_import_verification_cron'
    );
  }
  catch (\Throwable $e) {
    $log('error', 'FATAL: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    return civicrm_api3_create_error(
      'Fatal error in PAN import verification: ' . $e->getMessage() . ' (see log: ' . $logFile . ')'
    );
  }
}

/**
 * Mark a contribution as processed by this job.
 *
 * Flips PAN_Import.Pending_PAN_Verification from Yes (1) to No (0) so the row
 * is excluded from all future runs.
 *
 * @param int $contributionId
 */
function _goonjcustom_pan_import_mark_processed(int $contributionId): void {
  Contribution::update(FALSE)
    ->addWhere('id', '=', $contributionId)
    ->addValue('PAN_Import.Pending_PAN_Verification', FALSE)
    ->execute();
}
