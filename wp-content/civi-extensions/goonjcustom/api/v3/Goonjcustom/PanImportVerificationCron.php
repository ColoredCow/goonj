<?php

/**
 * @file
 */

use Civi\Api4\Contact;
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
 *  - Case 2: contribution PAN == contact PAN AND contact = Not Verified
 *    (already checked by Surepass, came back invalid) -> nothing to change;
 *    just mark the row processed.
 *  - Case 3: contact has no PAN -> format-check the contribution PAN, verify it
 *    via Surepass, and save it (with the resulting status) onto the contact.
 *    Invalid-format PANs are skipped (not saved); a Surepass API error leaves
 *    the row Pending for retry.
 *  - Case 4: contribution PAN != contact PAN AND contact = Verified -> keep the
 *    verified contact PAN (no Surepass call), overwrite the contribution's
 *    mismatched PAN with the contact's verified PAN, and mark the contribution
 *    Verified, for record consistency.
 *  - Case 5: contribution PAN != contact PAN AND contact = Not Verified ->
 *    verify the incoming contribution PAN via Surepass; if it passes it
 *    replaces the contact PAN (marked Verified), if it fails the existing
 *    contact PAN is kept.
 *
 * Scope: ONLY contributions flagged Pending = Yes are ever read or written.
 * A contact's other contributions are never touched, because those records have
 * already been shared for audit purposes.
 *
 * The ladder is exhaustive, so a single run finishes every row it reads and
 * nothing carries over to the next day:
 *  - a contribution with no PAN at all is marked processed (nothing to verify);
 *  - a contact PAN with no verification status counts as not verified, so it
 *    falls into Case 2 / Case 5 instead of dropping through;
 *  - each row is processed inside its own try/catch, so one bad row cannot
 *    abort the batch and strand the remaining rows.
 *
 * The only rows deliberately left Pending are ones where Surepass itself was
 * unreachable (api_error) or an unexpected row-level error occurred — both are
 * retried on the next run by design.
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

  $isCli = (PHP_SAPI === 'cli');

  // `_background` and `_logfile` are internal handoff params passed by the
  // spawned worker, so they are only trusted on CLI. basename() keeps the log
  // inside our own log directory.
  $isBackgroundWorker = $isCli && !empty($params['_background']);
  $logFile = ($isCli && !empty($params['_logfile']))
    ? $logDir . '/' . basename((string) $params['_logfile'])
    : $logDir . '/pan-import-' . date('Y-m-d_His') . '.log';

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
      ->addOrderBy('id')
      ->execute();

    // Group pending contributions by contact — one contact can have several,
    // and they must all be read together before deciding.
    $byContact = [];
    foreach ($pending as $row) {
      $byContact[(int) $row['contact_id']][] = $row;
    }

    $log('info', 'Pending contributions: ' . $pending->count() . ' across ' . count($byContact) . ' contact(s).');

    $counters = [
      'case1' => 0,
      'case2' => 0,
      'case3' => 0,
      'case4' => 0,
      'case5' => 0,
      'invalid_format' => 0,
      'no_contribution_pan' => 0,
      'api_error' => 0,
      'errors' => 0,
      'unhandled' => 0,
    ];

    foreach ($byContact as $contactId => $contributions) {
      $contactPanData = PanVerificationService::getContactPan($contactId);
      $contactPan = strtoupper(trim($contactPanData['pan_number'] ?? ''));
      $contactStatus = $contactPanData['pan_status'] ?? NULL;

      foreach ($contributions as $contribution) {
        $contributionId = (int) $contribution['id'];
        $contributionPan = strtoupper(trim($contribution['Contribution_Details.PAN_Card_Number'] ?? ''));

        // One contribution failing must never abort the whole batch — with
        // production data volumes that would leave every remaining row Pending
        // until the next run. Isolate each row: log it, leave it Pending, and
        // carry on with the rest.
        try {
          // No PAN came in on this contribution, so there is nothing to verify or
          // compare. Mark it processed, otherwise it would be re-read on every
          // future run forever.
          if ($contributionPan === '') {
            _goonjcustom_pan_import_mark_processed($contributionId);
            $counters['no_contribution_pan']++;
            $log('info', "SKIP contribution #$contributionId (contact #$contactId): no PAN on the imported contribution — nothing to verify, marked processed.");
            continue;
          }

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

          // CASE 2 — contribution PAN matches the contact PAN AND the contact
          // PAN is not Verified. "Not Verified" here means Surepass was already
          // called for this PAN and it came back invalid, so there is nothing new
          // to do: no fresh Surepass call, no PAN write. Just mark it processed.
          // Anything that is not explicitly Verified (including a missing status)
          // counts as not verified, so no row can fall through the ladder.
          if (
          $contributionPan !== ''
          && $contributionPan === $contactPan
          && $contactStatus !== PanVerificationService::PAN_STATUS_VERIFIED
          ) {
            _goonjcustom_pan_import_mark_processed($contributionId);
            $counters['case2']++;
            $log('info', "CASE 2 contribution #$contributionId (contact #$contactId): PAN matches already-checked (Not Verified) contact PAN ($contactPan) — no change, marked processed.");
            continue;
          }

          // CASE 3 — the contact has no PAN yet. Format-check the contribution
          // PAN, verify it via Surepass, and save it (with the resulting status)
          // onto the contact. This is the only case that spends a Surepass call
          // and writes to the contact.
          if ($contactPan === '' && $contributionPan !== '') {
            // Never call Surepass on a malformed PAN, and never store one on the
            // contact. Mark processed so it does not get re-scanned forever.
            if (!PanVerificationService::isValidPanFormat($contributionPan)) {
              _goonjcustom_pan_import_mark_processed($contributionId);
              $counters['invalid_format']++;
              $log('info', "CASE 3 contribution #$contributionId (contact #$contactId): PAN '$contributionPan' has invalid format — not saved, marked processed.");
              continue;
            }

            $api = PanVerificationService::verifyPanViaApi($contributionPan);

            // The API call itself failed (auth/network/5xx) — we did not get a
            // real answer. Leave the row Pending so it retries on the next run.
            if (!empty($api['api_error'])) {
              $counters['api_error']++;
              $log('warning', "CASE 3 contribution #$contributionId (contact #$contactId): Surepass API error — left Pending for retry ({$api['message']}).");
              continue;
            }

            $status = !empty($api['verified'])
            ? PanVerificationService::PAN_STATUS_VERIFIED
            : PanVerificationService::PAN_STATUS_NOT_VERIFIED;

            _goonjcustom_pan_import_write_contact_pan($contactId, $contributionPan, $status);
            _goonjcustom_pan_import_mark_processed($contributionId);

            // Refresh the in-memory contact PAN/status so this contact's other
            // pending contributions in THIS run compare against the value we just
            // saved (they will hit Case 1/2 rather than calling Surepass again).
            $contactPan = $contributionPan;
            $contactStatus = $status;

            $counters['case3']++;
            $log('info', "CASE 3 contribution #$contributionId (contact #$contactId): no contact PAN — saved '$contributionPan' as $status, marked processed.");
            continue;
          }

          // CASE 4 — the contribution PAN differs from the contact PAN, and the
          // contact PAN is already Verified. The verified contact PAN is trusted,
          // so we keep it (no Surepass call) and overwrite the contribution's
          // wrong PAN with the contact's verified PAN, so the stored records stay
          // consistent (the receipt already reads the contact PAN first).
          if (
          $contributionPan !== ''
          && $contactPan !== ''
          && $contributionPan !== $contactPan
          && $contactStatus === PanVerificationService::PAN_STATUS_VERIFIED
          ) {
            _goonjcustom_pan_import_correct_contribution_pan($contributionId, $contactPan);
            $counters['case4']++;
            $log('info', "CASE 4 contribution #$contributionId (contact #$contactId): contribution PAN '$contributionPan' != verified contact PAN '$contactPan' — corrected contribution PAN to '$contactPan' and set it Verified, marked processed.");
            continue;
          }

          // CASE 5 — the contribution PAN differs from the contact PAN, and the
          // contact PAN is not Verified (never confirmed). Verify the incoming
          // contribution PAN via Surepass: if it passes it replaces the contact
          // PAN (marked Verified); if it fails, the existing contact PAN is kept.
          // As in Case 2, anything not explicitly Verified counts as not verified.
          if (
          $contributionPan !== ''
          && $contactPan !== ''
          && $contributionPan !== $contactPan
          && $contactStatus !== PanVerificationService::PAN_STATUS_VERIFIED
          ) {
            // Invalid-format incoming PAN — nothing to verify. Keep the existing
            // contact PAN and mark the row processed.
            if (!PanVerificationService::isValidPanFormat($contributionPan)) {
              _goonjcustom_pan_import_mark_processed($contributionId);
              $counters['invalid_format']++;
              $log('info', "CASE 5 contribution #$contributionId (contact #$contactId): incoming PAN '$contributionPan' has invalid format — kept existing contact PAN '$contactPan', marked processed.");
              continue;
            }

            $api = PanVerificationService::verifyPanViaApi($contributionPan);

            // API error — leave the row Pending so it retries on the next run.
            if (!empty($api['api_error'])) {
              $counters['api_error']++;
              $log('warning', "CASE 5 contribution #$contributionId (contact #$contactId): Surepass API error — left Pending for retry ({$api['message']}).");
              continue;
            }

            if (!empty($api['verified'])) {
              // Incoming PAN verified — it replaces the contact's unverified PAN.
              _goonjcustom_pan_import_write_contact_pan($contactId, $contributionPan, PanVerificationService::PAN_STATUS_VERIFIED);
              _goonjcustom_pan_import_mark_processed($contributionId);

              // Refresh the in-memory contact PAN/status so this contact's other
              // pending contributions this run compare against the new value.
              $contactPan = $contributionPan;
              $contactStatus = PanVerificationService::PAN_STATUS_VERIFIED;

              $counters['case5']++;
              $log('info', "CASE 5 contribution #$contributionId (contact #$contactId): incoming PAN '$contributionPan' verified — replaced unverified contact PAN, marked processed.");
              continue;
            }

            // Incoming PAN could not be verified — keep the existing contact PAN.
            _goonjcustom_pan_import_mark_processed($contributionId);
            $counters['case5']++;
            $log('info', "CASE 5 contribution #$contributionId (contact #$contactId): incoming PAN '$contributionPan' not verified — kept existing contact PAN '$contactPan', marked processed.");
            continue;
          }

          // None of the five cases matched — a data anomaly (e.g. an empty
          // contribution PAN, or a contact PAN with no verification status).
          // Leave it Pending and log it so it stays visible for review.
          $counters['unhandled']++;
          $log('warning', "ANOMALY contribution #$contributionId (contact #$contactId): matched none of the 5 cases (contribution PAN '$contributionPan', contact PAN '$contactPan', status '" . ($contactStatus ?? 'NULL') . "') — left as Pending.");
        }
        catch (\Throwable $e) {
          $counters['errors']++;
          $log('error', "ERROR contribution #$contributionId (contact #$contactId): " . get_class($e) . ': ' . $e->getMessage() . ' — left Pending, continuing with the rest of the batch.');
        }
      }
    }

    $log('info', "Job finished. Case 1: {$counters['case1']} | Case 2: {$counters['case2']} | Case 3: {$counters['case3']} | Case 4: {$counters['case4']} | Case 5: {$counters['case5']} | Invalid format: {$counters['invalid_format']} | No PAN on contribution: {$counters['no_contribution_pan']} | API errors (left pending): {$counters['api_error']} | Row errors (left pending): {$counters['errors']} | Anomalies (left pending): {$counters['unhandled']}");

    return civicrm_api3_create_success(
      [
        'log_file' => $logFile,
        'case1' => $counters['case1'],
        'case2' => $counters['case2'],
        'case3' => $counters['case3'],
        'case4' => $counters['case4'],
        'case5' => $counters['case5'],
        'invalid_format' => $counters['invalid_format'],
        'no_contribution_pan' => $counters['no_contribution_pan'],
        'errors' => $counters['errors'],
        'api_error' => $counters['api_error'],
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

/**
 * Save a PAN onto a contact, with its verification status, and record that
 * Surepass was called (PAN_API_Status = Called).
 *
 * Setting PAN_API_Status = Called matters: the standalone bulk-verify cron only
 * picks up contacts where PAN_API_Status is Not_Called / NULL, so marking it
 * Called here prevents that cron from making a second (paid) Surepass call for
 * the same PAN.
 *
 * @param int $contactId
 * @param string $pan
 *   Already uppercased + trimmed, valid-format PAN.
 * @param string $status
 *   One of the PanVerificationService::PAN_STATUS_* constants.
 */
function _goonjcustom_pan_import_write_contact_pan(int $contactId, string $pan, string $status): void {
  Contact::update(FALSE)
    ->addWhere('id', '=', $contactId)
    ->addValue('PAN_Card_Details.PAN_Card_Number', $pan)
    ->addValue('PAN_Card_Details.PAN_Verification_Status:name', $status)
    ->addValue('PAN_Card_Details.PAN_API_Status:name', 'Called')
    ->execute();
}

/**
 * Overwrite a contribution's PAN with the given (correct, verified) value, mark
 * the contribution's PAN status as Verified, and mark it processed — in a
 * single update. Used by Case 4, where the contribution carried a wrong PAN and
 * the contact's verified PAN is the source of truth.
 *
 * @param int $contributionId
 * @param string $pan
 *   The contact's verified PAN to stamp onto the contribution.
 */
function _goonjcustom_pan_import_correct_contribution_pan(int $contributionId, string $pan): void {
  Contribution::update(FALSE)
    ->addWhere('id', '=', $contributionId)
    ->addValue('Contribution_Details.PAN_Card_Number', $pan)
    ->addValue('Contribution_Details.PAN_Card_Verified:name', PanVerificationService::PAN_STATUS_VERIFIED)
    ->addValue('PAN_Import.Pending_PAN_Verification', FALSE)
    ->execute();
}
