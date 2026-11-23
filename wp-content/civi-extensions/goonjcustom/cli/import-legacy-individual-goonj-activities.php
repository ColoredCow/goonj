<?php

/**
 * @file
 * Imports legacy individual Goonj activities from the old system.
 *
 * For every sheet row the script creates:
 * - one Goonj Activities record (activity details, logistics and outcome),
 * - one activity row ("Collection Camp Activity") for the sheet's Goonj
 *   Activity Type, Completed, as the live flow creates when the record is
 *   authorized and the outcome is filled,
 * - an "Organize Goonj Activities" activity on the organiser (when found),
 *   like the live flow does.
 *
 * The organisers must already exist (they are imported first with the CiviCRM
 * contact import). The "Contact ID", the organiser's "State", "City" and
 * "Address", "Goonj Event Record Type", "Activity Status", "Activity ID" and
 * "FY" columns have no field on the record (they describe the organiser, or
 * are always the same) and are not imported.
 *
 * Rules agreed with Goonj (coloredcow-admin/goonj-crm#234), the same as for
 * the camp and institution activity sheets:
 * - the person in "POC Details" is the organiser; rows with no person details
 *   are imported without an organiser;
 * - Coordinating Urban POC = the "Attended By" person;
 * - an activity code used twice (in the sheet, or already by another record
 *   in the CRM) gets an extra 0 at the start of the number;
 * - "Total Monetary Contributed" is saved as the total online monetary
 *   contributions, as on the institution activities.
 * Other values that are not in the sheet are not filled in.
 *
 * No email may go out for these old activities:
 * - the record is created already Authorized in one save, so the hooks that
 *   act when an existing record becomes Authorized (authorization email, QR,
 *   activity rows, Organize activity) do not run; the script creates the
 *   activity row and the Organize activity itself;
 * - "Email Sent" (logistics email to the Attended By person, sent by the
 *   daily Goonj activities job) and "Feedback Email Sent" (feedback email to
 *   the organiser) are saved, so the jobs treat these records as already
 *   emailed; "Feedback Reminder Sent" is saved too, as on the camps;
 * - the organiser is set in a second save: set while the record is created,
 *   a hook links the organiser's latest induction to the record.
 *
 * Each row is saved in one transaction, so a row is either fully saved or not
 * saved at all.
 *
 * Every row that is not imported (organiser not found, a value that cannot be
 * matched, an error while saving) is written to an issues sheet: the sheet's
 * own columns plus "Import status" and "Reason".
 *
 * Runs as a dry run by default: nothing is saved, and every row is listed in a
 * CSV report with the matches and what would be created. Rows already
 * imported are skipped, so the script can be run again safely.
 *
 * Usage:
 *   Dry run:
 *     CSV=/path/to/sheet.csv cv scr wp-content/civi-extensions/goonjcustom/cli/import-legacy-individual-goonj-activities.php
 *   Save only the first 2 records (to show the client first):
 *     LIMIT=2 APPLY=1 CSV=... cv scr ...
 *   Save all (records already saved are skipped):
 *     APPLY=1 CSV=... cv scr ...
 *   Only some sheet lines (line numbers as in the spreadsheet, data starts on 3):
 *     ROWS=3-12 CSV=... cv scr ...
 *   Organiser for sheet lines whose person cannot be matched (sheet line =
 *   individual contact id, comma separated):
 *     ORGANISER_FOR_LINES=16=532153 CSV=... cv scr ...
 *   Goonj activity type for a sheet text that is not an option's name or
 *   label (sheet text = option name, separated by |):
 *     ACTIVITY_TYPES="Menstrual Hygiene Session (CTB)=Menstrual_Hygenie_Dialogue_CTB_" CSV=... cv scr ...
 *   (an "Other" type keeps the sheet text as "Other Activity Details").
 *   Rows whose organiser has details but is not found go to the issues sheet;
 *   to import those without an organiser:
 *     ALLOW_NO_ORGANISER=1 APPLY=1 CSV=... cv scr ...
 *   Report (every row) and issues sheet (rows not imported) locations,
 *   default: next to the CSV:
 *     REPORT=/path/to/report.csv ISSUES=/path/to/issues.csv
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\OptionValue;
use Civi\Api4\Phone;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Api4\UFMatch;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Imports one legacy sheet of individual Goonj activities.
 */
class GoonjLegacyIndividualActivityImport {

  /**
   * Sheet columns, in order, as on the second header row.
   */
  const COLUMNS = [
    'Contact ID',
    'First Name',
    'Last Name',
    'Mobile',
    'Email',
    'State',
    'City',
    'Address',
    'Goonj Activity Code',
    'Created Date(YYYY-MM-DD)',
    'Goonj Activity Type',
    'Start Date',
    'End Date',
    'Goonj Event Record Type',
    'Coordinating Goonj Office',
    'Activity Status',
    'Venue',
    'State',
    'City',
    'Activity ID',
    'Attended By mail',
    'Attended By Name',
    'Support person details',
    'No. of Attendees',
    'Total Monetary Contributed',
    'Total Product Sale',
    'No. of Sessions',
    'Any unique efforts made by organizers',
    'Difficulty/challenge faced by organizers',
    'Rate the activity',
    'Any remarks for internal use',
    'FY',
  ];

  /**
   * Columns added to the issues sheet.
   */
  const ISSUE_COLUMNS = ['Import status', 'Reason'];

  const COL_FIRST_NAME = 1;
  const COL_LAST_NAME = 2;
  const COL_MOBILE = 3;
  const COL_EMAIL = 4;
  const COL_CODE = 8;
  const COL_CREATED = 9;
  const COL_TYPE = 10;
  const COL_START = 11;
  const COL_END = 12;
  const COL_OFFICE = 14;
  const COL_VENUE = 16;
  const COL_STATE = 17;
  const COL_CITY = 18;
  const COL_ATTENDED_BY_EMAIL = 20;
  const COL_ATTENDED_BY_NAME = 21;
  const COL_SUPPORT_PERSON = 22;
  const COL_ATTENDEES = 23;
  const COL_MONETARY = 24;
  const COL_PRODUCT_SALE = 25;
  const COL_SESSIONS = 26;
  const COL_UNIQUE_EFFORTS = 27;
  const COL_DIFFICULTY = 28;
  const COL_RATE = 29;
  const COL_REMARKS = 30;

  /**
   * The first sheet line with data (two header rows).
   */
  const FIRST_DATA_LINE = 3;

  /**
   * Settings read from the environment.
   */
  private string $csvPath;

  /**
   * Save (TRUE) or dry run.
   */
  private bool $apply;

  /**
   * Import rows whose organiser is not found without an organiser.
   */
  private bool $allowNoOrganiser;

  /**
   * Stop after this many records.
   */
  private ?int $limit = NULL;

  /**
   * First and last sheet line to process (ROWS).
   */
  private ?int $fromLine = NULL;

  /**
   * Last sheet line to process (ROWS).
   */
  private ?int $toLine = NULL;

  /**
   * Report CSV path.
   */
  private string $reportPath;

  /**
   * Issues sheet path.
   */
  private string $issuesPath;

  /**
   * Organiser contact id given for a sheet line (ORGANISER_FOR_LINES).
   */
  private array $organiserForLines = [];

  /**
   * The contacts given with ORGANISER_FOR_LINES, keyed by contact id.
   */
  private array $givenOrganisers = [];

  /**
   * Sheet activity type => option name, given with ACTIVITY_TYPES.
   */
  private array $typeMap = [];

  /**
   * The sheet's first header row (column groups), copied to the issues sheet.
   */
  private array $groupHeader = [];

  /**
   * Values of a row that cannot be imported, keyed by sheet line.
   */
  private array $rowErrors = [];

  /**
   * Sheet codes changed because the sheet uses them twice, keyed by line.
   */
  private array $changedCodes = [];

  /**
   * Goonj offices by name.
   */
  private array $offices = [];

  /**
   * Attended By staff, keyed by email|name.
   */
  private array $staff = [];

  /**
   * Indian states by name.
   */
  private array $states = [];

  /**
   * Goonj activity type options: lower-case label or name => option.
   */
  private array $engagementTypes = [];

  /**
   * "Rate the activity" options: lower-case label => value.
   */
  private array $rates = [];

  /**
   * Reads the settings from the environment.
   */
  public function __construct() {
    $this->csvPath = (string) getenv('CSV');
    $this->apply = getenv('APPLY') === '1';
    $this->allowNoOrganiser = getenv('ALLOW_NO_ORGANISER') === '1';

    $limit = trim((string) getenv('LIMIT'));
    if ($limit !== '') {
      if (!ctype_digit($limit) || (int) $limit < 1) {
        $this->fail("LIMIT must be a number like 2, got: $limit");
      }
      $this->limit = (int) $limit;
    }

    $rows = trim((string) getenv('ROWS'));
    if ($rows !== '') {
      if (!preg_match('/^(\d+)(?:-(\d+))?$/', $rows, $m)) {
        $this->fail("ROWS must look like 5 or 3-12, got: $rows");
      }
      $this->fromLine = (int) $m[1];
      $this->toLine = isset($m[2]) ? (int) $m[2] : (int) $m[1];
    }

    $this->organiserForLines = $this->lineMap('ORGANISER_FOR_LINES');

    $types = trim((string) getenv('ACTIVITY_TYPES'));
    if ($types !== '') {
      foreach (explode('|', $types) as $pair) {
        if (!preg_match('/^\s*(.+?)\s*=\s*(.+?)\s*$/', $pair, $m)) {
          $this->fail("ACTIVITY_TYPES must look like Sheet text=Option_name|..., got: $types");
        }
        $this->typeMap[mb_strtolower($m[1])] = $m[2];
      }
    }

    $base = preg_replace('/\.csv$/i', '', $this->csvPath);
    $report = trim((string) getenv('REPORT'));
    $this->reportPath = $report !== '' ? $report : $base . '-report-' . date('Ymd-His') . '.csv';
    $issues = trim((string) getenv('ISSUES'));
    $this->issuesPath = $issues !== '' ? $issues : $base . '-issues-' . date('Ymd-His') . '.csv';
  }

  /**
   * Reads a "line=contactId,line=contactId" setting.
   */
  private function lineMap(string $name): array {
    $value = trim((string) getenv($name));
    $map = [];
    if ($value === '') {
      return $map;
    }
    foreach (explode(',', $value) as $pair) {
      if (!preg_match('/^\s*(\d+)\s*=\s*(\d+)\s*$/', $pair, $m)) {
        $this->fail("$name must look like 16=532153,20=597199, got: $value");
      }
      $map[(int) $m[1]] = (int) $m[2];
    }
    return $map;
  }

  /**
   * Runs the import.
   */
  public function run(): void {
    if ($this->csvPath === '' || !is_readable($this->csvPath)) {
      $this->fail('Set CSV to the sheet path. Not readable: ' . $this->csvPath);
    }

    echo $this->apply ? "MODE: APPLY (records will be saved)\n" : "MODE: DRY RUN (nothing is saved)\n";
    echo "CSV: {$this->csvPath}\n";
    echo "Report: {$this->reportPath}\n";
    echo "Issues sheet: {$this->issuesPath}\n";
    if ($this->limit) {
      echo "LIMIT: stop after {$this->limit} record(s)\n";
    }

    $rows = $this->readSheet();
    echo 'Rows selected: ' . count($rows) . "\n\n";

    $this->resolveLookups($rows);

    $report = fopen($this->reportPath, 'w');
    if (!$report) {
      $this->fail('Cannot write the report: ' . $this->reportPath);
    }
    fputcsv($report, [
      'sheet_line', 'activity_code', 'organiser', 'organiser_email', 'status',
      'organiser_contact_id', 'match', 'match_note', 'record_id',
      'activity_row_id', 'organize_activity_id', 'note',
    ], ',', '"', '\\');

    // Rows that are not imported, in the sheet's own format plus the reason,
    // so they can be fixed and given back to this script as CSV.
    $issues = fopen($this->issuesPath, 'w');
    if (!$issues) {
      $this->fail('Cannot write the issues sheet: ' . $this->issuesPath);
    }
    fputcsv($issues, array_merge(array_pad(array_slice($this->groupHeader, 0, count(self::COLUMNS)), count(self::COLUMNS), ''), ['Import', '']), ',', '"', '\\');
    fputcsv($issues, array_merge(self::COLUMNS, self::ISSUE_COLUMNS), ',', '"', '\\');
    $issueCount = 0;

    $counts = [];
    $done = 0;
    foreach ($rows as $line => $row) {
      if ($this->limit && $done >= $this->limit) {
        break;
      }
      // Something in the bootstrap sets a 300 second limit; a full sheet can
      // take longer. Reset it for every row.
      set_time_limit(0);

      $result = $this->processRow($line, $row);
      $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
      if (in_array($result['status'], ['CREATED', 'DRY_RUN_OK'], TRUE)) {
        $done++;
      }

      fputcsv($report, [
        $line,
        $result['code'] ?? $row[self::COL_CODE],
        trim($row[self::COL_FIRST_NAME] . ' ' . $row[self::COL_LAST_NAME]),
        $row[self::COL_EMAIL],
        $result['status'],
        $result['contact_id'] ?? '',
        $result['match'] ?? '',
        $result['match_note'] ?? '',
        $result['record_id'] ?? '',
        $result['activity_row_id'] ?? '',
        $result['activity_id'] ?? '',
        $result['note'] ?? '',
      ], ',', '"', '\\');

      if ($result['status'] === 'CREATED') {
        echo "Line $line: {$result['code']} => record {$result['record_id']}\n";
      }
      if (!in_array($result['status'], ['CREATED', 'DRY_RUN_OK', 'SKIPPED_ALREADY_IMPORTED'], TRUE)) {
        $issueCount++;
        $reason = trim(($result['note'] ?? '') . (!empty($result['match_note']) ? ' (' . $result['match_note'] . ')' : ''));
        fputcsv($issues, array_merge($row, [$result['status'], $reason]), ',', '"', '\\');
        if ($result['status'] === 'ERROR') {
          echo "Line $line: ERROR, nothing saved for this line: {$result['note']}\n";
        }
      }
    }
    fclose($report);
    fclose($issues);

    $this->printSummary($counts);
    echo "\nRows not imported: $issueCount, listed with the reason in {$this->issuesPath}\n";
  }

  /**
   * Reads and checks the sheet. Returns the selected rows keyed by sheet line.
   */
  private function readSheet(): array {
    $handle = fopen($this->csvPath, 'r');
    $groupHeader = fgetcsv($handle, 0, ',', '"', '\\');
    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if ($groupHeader === FALSE || $header === FALSE) {
      $this->fail('The sheet has no header rows.');
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $header = array_map('trim', $header);
    // An issues sheet from an earlier run has two extra columns at the end.
    if (array_slice($header, count(self::COLUMNS)) === self::ISSUE_COLUMNS) {
      $header = array_slice($header, 0, count(self::COLUMNS));
    }
    if ($header !== self::COLUMNS) {
      $this->fail("The sheet columns are not the expected ones.\nExpected: " . implode(' | ', self::COLUMNS) . "\nFound:    " . implode(' | ', $header));
    }
    $this->groupHeader = $groupHeader;

    $all = [];
    $line = self::FIRST_DATA_LINE - 1;
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
      $line++;
      if (count($row) === count(self::COLUMNS) + count(self::ISSUE_COLUMNS)) {
        $row = array_slice($row, 0, count(self::COLUMNS));
      }
      if (count($row) !== count(self::COLUMNS)) {
        $this->fail("Sheet line $line has " . count($row) . ' columns, expected ' . count(self::COLUMNS));
      }
      $all[$line] = array_map('trim', $row);
    }
    fclose($handle);

    // Worked out on the whole sheet (not only ROWS) so every run gives each
    // row the same code.
    $this->makeDuplicateCodesUnique($all);

    $rows = [];
    foreach ($all as $line => $row) {
      if ($this->fromLine === NULL || ($line >= $this->fromLine && $line <= $this->toLine)) {
        $rows[$line] = $row;
      }
    }
    return $rows;
  }

  /**
   * Gives an activity code used by more than one row a unique code.
   *
   * The first row keeps the code; each later row with the same code gets an
   * extra 0 at the start of the number: 2023/TND/Know/004 becomes
   * 2023/TND/Know/0004.
   */
  private function makeDuplicateCodesUnique(array &$rows): void {
    $sheetCodes = [];
    foreach ($rows as $row) {
      $sheetCodes[$row[self::COL_CODE]] = TRUE;
    }

    $used = [];
    foreach ($rows as $line => $row) {
      $code = $row[self::COL_CODE];
      if (strpos($code, '/') === FALSE) {
        continue;
      }
      $final = $code;
      if (isset($used[$code])) {
        do {
          $final = $this->addZero($final);
        } while (isset($used[$final]) || isset($sheetCodes[$final]));
        $rows[$line][self::COL_CODE] = $final;
        $this->changedCodes[$line] = "Code $code is used twice in the sheet, saved as $final.";
      }
      $used[$final] = TRUE;
    }
  }

  /**
   * Adds a 0 at the start of a code's number: 2023/TND/Know/004 -> .../0004.
   */
  private function addZero(string $code): string {
    $parts = explode('/', $code);
    $number = array_pop($parts);
    return implode('/', array_merge($parts, ['0' . $number]));
  }

  /**
   * Resolves every lookup the rows need.
   *
   * A row with a value that cannot be found is not imported; it goes to the
   * issues sheet with the reason.
   */
  private function resolveLookups(array $rows): void {
    $this->rates = $this->optionValues('Goonj_Activities_Outcome', 'Rate_the_activity');
    $this->engagementTypes = $this->engagementOptions();

    foreach ($rows as $line => $row) {
      if (isset($this->organiserForLines[$line])) {
        $organiserId = $this->organiserForLines[$line];
        if (!array_key_exists($organiserId, $this->givenOrganisers)) {
          $this->givenOrganisers[$organiserId] = Contact::get(FALSE)
            ->addSelect('id', 'display_name')
            ->addWhere('id', '=', $organiserId)
            ->addWhere('contact_type', '=', 'Individual')
            ->addWhere('is_deleted', '=', FALSE)
            ->execute()->first();
        }
        if (!$this->givenOrganisers[$organiserId]) {
          $this->rowErrors[$line][] = "ORGANISER_FOR_LINES contact $organiserId is not an individual";
        }
      }

      if (!$this->engagementType($row)) {
        $this->rowErrors[$line][] = "Goonj Activity Type is not an option: '{$row[self::COL_TYPE]}' (give it with ACTIVITY_TYPES)";
      }
      $rate = mb_strtolower($row[self::COL_RATE]);
      if ($rate !== '' && !isset($this->rates[$rate])) {
        $this->rowErrors[$line][] = "'Rate the activity' value not found: '{$row[self::COL_RATE]}'";
      }

      $office = $row[self::COL_OFFICE];
      if (!array_key_exists($office, $this->offices)) {
        $this->offices[$office] = $this->findOffice($office);
      }
      if (!$this->offices[$office]) {
        $this->rowErrors[$line][] = "Goonj office not found: '$office'";
      }

      $staffEmail = strtolower($row[self::COL_ATTENDED_BY_EMAIL]);
      $staffKey = $this->staffKey($row);
      if ($staffEmail !== '' && !array_key_exists($staffKey, $this->staff)) {
        $this->staff[$staffKey] = $this->findStaff($staffEmail, $row[self::COL_ATTENDED_BY_NAME]);
      }
      if ($staffEmail !== '' && empty($this->staff[$staffKey]['id'])) {
        $this->rowErrors[$line][] = "'Attended By' not resolved: $staffEmail ({$this->staff[$staffKey]['note']})";
      }

      $state = $row[self::COL_STATE];
      if (!array_key_exists($state, $this->states)) {
        $this->states[$state] = $this->findIndianState($state);
      }
      if (!$this->states[$state]) {
        $this->rowErrors[$line][] = "state not found: '$state'";
      }

      foreach ([self::COL_CREATED, self::COL_START, self::COL_END] as $col) {
        if (!$this->parseDate($row[$col])) {
          $this->rowErrors[$line][] = "bad date in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
      if ($this->parseDate($row[self::COL_START]) && $this->parseDate($row[self::COL_END]) && $this->parseDate($row[self::COL_START]) > $this->parseDate($row[self::COL_END])) {
        $this->rowErrors[$line][] = "Start Date {$row[self::COL_START]} is after End Date {$row[self::COL_END]}";
      }
      foreach ([self::COL_ATTENDEES, self::COL_MONETARY, self::COL_PRODUCT_SALE, self::COL_SESSIONS] as $col) {
        if ($row[$col] !== '' && $this->parseNumber($row[$col]) === NULL) {
          $this->rowErrors[$line][] = "not a number in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
      // CiviCRM saves < and > as &lt; and &gt;, so the saved text is longer.
      foreach ([self::COL_VENUE, self::COL_UNIQUE_EFFORTS, self::COL_SUPPORT_PERSON] as $col) {
        $saved = str_replace(['<', '>'], ['&lt;', '&gt;'], $this->singleLine($row[$col]));
        if (mb_strlen($saved) > 255) {
          $this->rowErrors[$line][] = "'" . self::COLUMNS[$col] . "' is longer than the field's 255 characters once saved (" . mb_strlen($saved) . ')';
        }
      }
    }

    echo "Lookups:\n";
    foreach ($this->offices as $name => $id) {
      echo "  Office      '$name' => " . ($id ?: 'NOT FOUND') . "\n";
    }
    foreach ($this->staff as $key => $staff) {
      echo "  Attended by $key => " . ($staff['id'] ?: 'NOT RESOLVED') . " {$staff['name']} ({$staff['note']})\n";
    }
    foreach ($this->typeMap as $text => $name) {
      echo "  Type        '$text' => option '$name' (given with ACTIVITY_TYPES)\n";
    }
    foreach ($this->organiserForLines as $organiserLine => $organiserId) {
      echo "  Organiser   line $organiserLine => " . (!empty($this->givenOrganisers[$organiserId]) ? "$organiserId {$this->givenOrganisers[$organiserId]['display_name']}" : "$organiserId NOT AN INDIVIDUAL") . " (given with ORGANISER_FOR_LINES)\n";
    }
    echo "\n";

    if ($this->rowErrors) {
      echo count($this->rowErrors) . " row(s) have values that cannot be imported; they go to the issues sheet.\n\n";
    }
  }

  /**
   * Processes one sheet row.
   */
  private function processRow(int $line, array $row): array {
    $result = ['code' => $row[self::COL_CODE], 'note' => $this->changedCodes[$line] ?? ''];
    if (!empty($this->rowErrors[$line])) {
      return ['status' => 'SKIPPED_BAD_DATA', 'note' => trim($result['note'] . ' ' . implode('; ', array_unique($this->rowErrors[$line])))] + $result;
    }

    $match = isset($this->organiserForLines[$line])
      ? ['id' => $this->organiserForLines[$line], 'method' => 'ORGANISER_FOR_LINES', 'note' => 'given with ORGANISER_FOR_LINES']
      : $this->matchOrganiser($row);
    $result += [
      'contact_id' => $match['id'],
      'match' => $match['method'],
      'match_note' => $match['note'],
    ];

    // The code this row is saved with: a code already used by another record
    // in the CRM gets an extra 0 at the start of the number, as for a code used
    // twice in the sheet. A record this row saved on an earlier run is found
    // under the code it was saved with.
    $code = $row[self::COL_CODE];
    while (TRUE) {
      $existing = $this->findExistingRecord($row, $code);
      if ($existing) {
        return ['status' => 'SKIPPED_ALREADY_IMPORTED', 'record_id' => $existing, 'code' => $code] + $result;
      }
      $sameCode = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $code)
        ->execute()->first();
      if (!$sameCode) {
        break;
      }
      $newCode = $this->addZero($code);
      $result['note'] = trim($result['note'] . " Code $code is already used by record {$sameCode['id']} in the CRM, saved as $newCode.");
      $code = $newCode;
    }
    $result['code'] = $code;

    if (!$match['id'] && $match['method'] !== 'NO_ORGANISER_IN_SHEET' && !$this->allowNoOrganiser) {
      return ['status' => 'SKIPPED_ORGANISER_' . $match['method'], 'note' => trim($result['note'] . ' Organiser not matched; set ALLOW_NO_ORGANISER=1 to import without organiser')] + $result;
    }

    if (!$this->apply) {
      return ['status' => 'DRY_RUN_OK', 'note' => trim($result['note'] . ($match['id'] ? ' with organiser' : ' without organiser'))] + $result;
    }

    $transaction = new \CRM_Core_Transaction();
    try {
      $recordId = $this->createRecord($row, $code);
      $this->setValuesAfterCreate($recordId, $row, $match['id']);
      $activityRowId = $this->createActivityRow($recordId, $row, $match['id']);
      $activityId = $match['id'] ? $this->createOrganizeActivity($row, $code, $match['id']) : NULL;
      $transaction->commit();

      return [
        'status' => 'CREATED',
        'record_id' => $recordId,
        'activity_row_id' => $activityRowId,
        'activity_id' => $activityId,
      ] + $result;
    }
    catch (\Throwable $e) {
      $transaction->rollback()->commit();
      return ['status' => 'ERROR', 'note' => trim($result['note'] . ' ' . $e->getMessage())] + $result;
    }
  }

  /**
   * Creates the record, already Authorized, in one save.
   *
   * Created in one save so the hooks that act when an existing record becomes
   * Authorized (authorization email, QR, activity rows, Organize activity) do
   * not run.
   */
  private function createRecord(array $row, string $code): int {
    $attendedById = $this->staff[$this->staffKey($row)]['id'] ?? NULL;
    $type = $this->engagementType($row);
    $monetary = $this->parseNumber($row[self::COL_MONETARY]);

    $values = [
      'subtype:name' => 'Goonj_Activities',
      'title' => $code,
      'created_date' => $this->parseDate($row[self::COL_CREATED]),
      'Collection_Camp_Core_Details.Status:name' => 'Authorized',
      'Goonj_Activities.How_do_you_want_to_engage_with_Goonj_' => [$type['value']],
      'Goonj_Activities.Other_Activity_Details' => $type['name'] === 'Other' ? $this->singleLine($row[self::COL_TYPE]) : NULL,
      'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_' => $this->singleLine($row[self::COL_VENUE]),
      'Goonj_Activities.City' => $this->singleLine($row[self::COL_CITY]),
      'Goonj_Activities.State' => $this->states[$row[self::COL_STATE]],
      'Goonj_Activities.Start_Date' => $this->parseDate($row[self::COL_START]),
      'Goonj_Activities.End_Date' => $this->parseDate($row[self::COL_END]),
      // The live logistics form saves the Attended By person and the support
      // person here.
      'Logistics_Coordination.Camp_to_be_attended_by' => $attendedById,
      'Logistics_Coordination.Support_person_details' => $this->singleLine($row[self::COL_SUPPORT_PERSON]),
      'Goonj_Activities_Outcome.No_of_Attendees' => $this->parseNumber($row[self::COL_ATTENDEES]),
      'Goonj_Activities_Outcome.Product_Sale_Amount' => $this->parseNumber($row[self::COL_PRODUCT_SALE]),
      'Goonj_Activities_Outcome.No_of_Sessions' => $row[self::COL_SESSIONS] !== '' ? (string) $this->parseNumber($row[self::COL_SESSIONS]) : NULL,
      'Goonj_Activities_Outcome.Any_unique_efforts_made_by_organizers' => $this->singleLine($row[self::COL_UNIQUE_EFFORTS]),
      'Goonj_Activities_Outcome.Did_you_face_any_challenges_' => $this->singleLine($row[self::COL_DIFFICULTY]),
      'Goonj_Activities_Outcome.Rate_the_activity' => $row[self::COL_RATE] !== '' ? $this->rates[mb_strtolower($row[self::COL_RATE])] : NULL,
      'Goonj_Activities_Outcome.Remarks' => $this->singleLine($row[self::COL_REMARKS]),
      'Core_Contribution_Details.Total_online_monetary_contributions' => $monetary === NULL ? NULL : (string) $monetary,

      // Marked as already emailed, so the scheduled jobs skip these records.
      // "Goonj Activities" job (logistics email to the Attended By person).
      'Logistics_Coordination.Email_Sent' => TRUE,
      // "Volunteer Feedback Goonj Activity" job (feedback email to the
      // organiser).
      'Logistics_Coordination.Feedback_Email_Sent' => TRUE,
      // "Volunteer Feedback Reminder" job, as on the camps.
      'Logistics_Coordination.Feedback_Reminder_Sent' => TRUE,
    ];

    $create = EckEntity::create('Collection_Camp', FALSE);
    foreach ($values as $field => $value) {
      if ($value !== NULL && $value !== '') {
        $create->addValue($field, $value);
      }
    }
    return (int) $create->execute()->single()['id'];
  }

  /**
   * Second save: the organiser, and the sheet values a hook overwrote.
   *
   * While the record is created, the setOfficeDetails hook replaces the
   * Goonj office with the state's office and puts a current coordinator in
   * "Coordinating Urban POC"; this save puts the sheet values back (the
   * Coordinating Urban POC is the "Attended By" person, as for the camps).
   * The organiser is set here and not while creating: set while creating, a
   * hook links the organiser's latest induction to the record.
   */
  private function setValuesAfterCreate(int $recordId, array $row, ?int $organiserId): void {
    $update = EckEntity::update('Collection_Camp', FALSE)
      ->addWhere('id', '=', $recordId)
      ->addValue('Goonj_Activities.Goonj_Office', $this->offices[$row[self::COL_OFFICE]])
      ->addValue('Goonj_Activities.Coordinating_Urban_Poc', $this->staff[$this->staffKey($row)]['id'] ?? NULL);
    if ($organiserId) {
      $update->addValue('Collection_Camp_Core_Details.Contact_Id', $organiserId);
    }
    $update->execute();
  }

  /**
   * Creates the activity row for the sheet's Goonj Activity Type.
   *
   * The live flow creates one row per activity type when the record is
   * authorized (title = the type's value, or the "Other" text), and marks it
   * Completed when the outcome is filled.
   */
  private function createActivityRow(int $recordId, array $row, ?int $organiserId): int {
    $type = $this->engagementType($row);
    $values = [
      'subtype:name' => 'Goonj_Activities',
      'title' => $type['name'] === 'Other' ? $this->singleLine($row[self::COL_TYPE]) : $type['value'],
      'Collection_Camp_Activity.Collection_Camp_Id' => $recordId,
      'Collection_Camp_Activity.Start_Date' => $this->parseDate($row[self::COL_START]),
      'Collection_Camp_Activity.End_Date' => $this->parseDate($row[self::COL_END]),
      'Collection_Camp_Activity.Organizing_Person' => $organiserId,
      'Collection_Camp_Activity.Activity_Status:name' => 'Completed',
    ];
    $create = EckEntity::create('Collection_Camp_Activity', FALSE);
    foreach ($values as $field => $value) {
      if ($value !== NULL && $value !== '') {
        $create->addValue($field, $value);
      }
    }
    return (int) $create->execute()->single()['id'];
  }

  /**
   * Creates the "Organize Goonj Activities" activity on the organiser.
   *
   * Same activity the live flow creates when a record is authorized. The
   * activity code in the subject is the link to the record.
   */
  private function createOrganizeActivity(array $row, string $code, int $organiserId): int {
    return (int) Activity::create(FALSE)
      ->addValue('subject', $code)
      ->addValue('activity_type_id:name', 'Organize Goonj Activities')
      // The live flow asks for "Authorized", which is not an activity status,
      // so every live Organize activity is saved as Completed.
      ->addValue('status_id:name', 'Completed')
      ->addValue('activity_date_time', $this->parseDate($row[self::COL_CREATED]))
      ->addValue('source_contact_id', $organiserId)
      ->addValue('target_contact_id', $organiserId)
      ->execute()->single()['id'];
  }

  /**
   * Returns the id of the record this sheet row already created, if any.
   */
  private function findExistingRecord(array $row, string $code): ?int {
    $record = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id')
      ->addWhere('subtype:name', '=', 'Goonj_Activities')
      ->addWhere('title', '=', $code)
      ->addWhere('Goonj_Activities.Start_Date', '=', $this->parseDate($row[self::COL_START]))
      ->addWhere('Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', '=', $this->singleLine($row[self::COL_VENUE]))
      ->execute()->first();
    return $record ? (int) $record['id'] : NULL;
  }

  /**
   * The option for a row's Goonj Activity Type: value, name and label.
   *
   * The sheet text is matched to the option's label, then to its name (with
   * spaces for underscores), then through ACTIVITY_TYPES.
   */
  private function engagementType(array $row): ?array {
    $text = mb_strtolower($this->singleLine($row[self::COL_TYPE]));
    if (isset($this->typeMap[$text])) {
      foreach ($this->engagementTypes as $option) {
        if ($option['name'] === $this->typeMap[$text]) {
          return $option;
        }
      }
      return NULL;
    }
    return $this->engagementTypes[$text] ?? NULL;
  }

  /**
   * The Goonj activity type options, keyed by lower-case label and name.
   */
  private function engagementOptions(): array {
    $optionGroupId = CustomField::get(FALSE)
      ->addSelect('option_group_id')
      ->addWhere('custom_group_id:name', '=', 'Goonj_Activities')
      ->addWhere('name', '=', 'How_do_you_want_to_engage_with_Goonj_')
      ->execute()->single()['option_group_id'];
    $all = OptionValue::get(FALSE)
      ->addSelect('value', 'name', 'label')
      ->addWhere('option_group_id', '=', $optionGroupId)
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    $options = [];
    foreach ($all as $option) {
      $options[mb_strtolower(trim(str_replace('_', ' ', $option['name'])))] = $option;
    }
    // Labels win over names.
    foreach ($all as $option) {
      $options[mb_strtolower($option['label'])] = $option;
    }
    return $options;
  }

  /**
   * Returns a field's options keyed by lower-case label => option value.
   */
  private function optionValues(string $group, string $field): array {
    $optionGroupId = CustomField::get(FALSE)
      ->addSelect('option_group_id')
      ->addWhere('custom_group_id:name', '=', $group)
      ->addWhere('name', '=', $field)
      ->execute()->single()['option_group_id'];
    $options = [];
    foreach (OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id', '=', $optionGroupId)
      ->addWhere('is_active', '=', TRUE)
      ->execute() as $option) {
      $options[mb_strtolower($option['label'])] = $option['value'];
    }
    return $options;
  }

  /**
   * Matches the organiser to an existing contact.
   *
   * A Goonj email (@goonj.org) is a Goonj staff address, never the
   * organiser's: such rows are not matched. Otherwise, among the contacts
   * that have the sheet email (any of their emails):
   * 1. first name + email,
   * 2. else email + mobile,
   * 3. else email only.
   * When the sheet has no email, or an email that no contact has and that is
   * not a valid address: first name + mobile.
   * When a step finds several contacts, the first one (lowest contact id) is
   * used.
   */
  private function matchOrganiser(array $row): array {
    $firstName = $this->normaliseName($row[self::COL_FIRST_NAME]);
    $email = strtolower($row[self::COL_EMAIL]);
    $mobile = $this->lastTenDigits($row[self::COL_MOBILE]);

    if ($firstName === '' && $this->normaliseName($row[self::COL_LAST_NAME]) === '' && $email === '' && $mobile === '') {
      return ['id' => NULL, 'method' => 'NO_ORGANISER_IN_SHEET', 'note' => 'No organiser details in the sheet; imported without organiser'];
    }

    if (str_ends_with($email, '@goonj.org')) {
      return ['id' => NULL, 'method' => 'GOONJ_EMAIL', 'note' => "Organiser email $email is a Goonj staff address"];
    }

    if ($email === '') {
      return $this->matchByFirstNameAndMobile($firstName, $mobile, 'No email in the sheet');
    }

    $ids = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addJoin('Contact AS contact', 'INNER', ['contact_id', '=', 'contact.id'])
      ->addWhere('email', '=', $email)
      ->addWhere('contact.is_deleted', '=', FALSE)
      ->addWhere('contact.contact_type', '=', 'Individual')
      ->execute()->column('contact_id');
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);

    if (!$ids) {
      if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        return $this->matchByFirstNameAndMobile($firstName, $mobile, 'Email in the sheet is not a valid address (' . $this->singleLine($email) . ')');
      }
      return ['id' => NULL, 'method' => 'NOT_FOUND', 'note' => 'No individual contact has this email'];
    }

    $note = count($ids) > 1 ? count($ids) . ' contacts have this email: ' . implode(',', $ids) : '';

    $byName = $this->filterByFirstName($ids, $firstName);
    if ($byName) {
      return ['id' => $byName[0], 'method' => 'first_name+email', 'note' => $note];
    }

    $byMobile = $this->filterByMobile($ids, $mobile);
    if ($byMobile) {
      return ['id' => $byMobile[0], 'method' => 'email+mobile', 'note' => $note];
    }

    return ['id' => $ids[0], 'method' => 'email', 'note' => $note];
  }

  /**
   * Matches an organiser without a usable email by first name + mobile.
   */
  private function matchByFirstNameAndMobile(string $firstName, string $mobile, string $why): array {
    if ($firstName === '' || $mobile === '') {
      return ['id' => NULL, 'method' => 'NO_EMAIL', 'note' => "$why, and first name or mobile is blank"];
    }
    $ids = Phone::get(FALSE)
      ->addSelect('contact_id')
      ->addJoin('Contact AS contact', 'INNER', ['contact_id', '=', 'contact.id'])
      ->addWhere('phone_numeric', 'IN', [$mobile, '91' . $mobile, '0' . $mobile])
      ->addWhere('contact.is_deleted', '=', FALSE)
      ->addWhere('contact.contact_type', '=', 'Individual')
      ->execute()->column('contact_id');
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);
    $byName = $this->filterByFirstName($ids, $firstName);
    if ($byName) {
      return ['id' => $byName[0], 'method' => 'first_name+mobile', 'note' => $why . (count($byName) > 1 ? '; ' . count($byName) . ' contacts match: ' . implode(',', $byName) : '')];
    }
    return ['id' => NULL, 'method' => 'NOT_FOUND', 'note' => "$why, and no contact with this first name and mobile"];
  }

  /**
   * Keeps the contacts whose first name matches (lowest id first).
   */
  private function filterByFirstName(array $ids, string $firstName): array {
    if (!$ids || $firstName === '') {
      return [];
    }
    $matched = [];
    $contacts = Contact::get(FALSE)
      ->addSelect('id', 'first_name')
      ->addWhere('id', 'IN', $ids)
      ->addOrderBy('id')
      ->execute();
    foreach ($contacts as $contact) {
      if ($this->normaliseName((string) $contact['first_name']) === $firstName) {
        $matched[] = (int) $contact['id'];
      }
    }
    return $matched;
  }

  /**
   * Keeps the contacts that have the mobile number (lowest id first).
   */
  private function filterByMobile(array $ids, string $mobile): array {
    if ($mobile === '') {
      return [];
    }
    $matched = [];
    $phones = Phone::get(FALSE)
      ->addSelect('contact_id', 'phone_numeric')
      ->addWhere('contact_id', 'IN', $ids)
      ->execute();
    foreach ($phones as $phone) {
      if ($this->lastTenDigits((string) $phone['phone_numeric']) === $mobile) {
        $matched[(int) $phone['contact_id']] = (int) $phone['contact_id'];
      }
    }
    sort($matched);
    return array_values($matched);
  }

  /**
   * Finds the staff contact for an "Attended By" email.
   *
   * Several contacts can share a staff email. The one with a CRM login wins;
   * without one, the one with an active "Employee of" relationship; without
   * one, the one whose first name is the first word of the sheet's "Attended
   * By Name".
   */
  private function findStaff(string $email, string $sheetName): array {
    $contacts = Email::get(FALSE)
      ->addSelect('contact_id', 'contact.display_name', 'contact.first_name')
      ->addJoin('Contact AS contact', 'INNER', ['contact_id', '=', 'contact.id'])
      ->addWhere('email', '=', $email)
      ->addWhere('contact.is_deleted', '=', FALSE)
      ->addWhere('contact.contact_type', '=', 'Individual')
      ->execute()->indexBy('contact_id')->getArrayCopy();
    $ids = array_keys($contacts);
    $name = fn($id) => $contacts[$id]['contact.display_name'] ?? '';

    if (!$ids) {
      return ['id' => NULL, 'name' => '', 'note' => 'no contact has this email'];
    }
    if (count($ids) === 1) {
      return ['id' => (int) $ids[0], 'name' => $name($ids[0]), 'note' => 'only contact with this email'];
    }

    $withLogin = array_values(array_unique(UFMatch::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('contact_id', 'IN', $ids)
      ->execute()->column('contact_id')));
    if (count($withLogin) === 1) {
      return ['id' => (int) $withLogin[0], 'name' => $name($withLogin[0]), 'note' => 'has CRM login; ' . count($ids) . ' contacts share the email'];
    }
    if (count($withLogin) > 1) {
      return ['id' => NULL, 'name' => '', 'note' => 'several contacts with a CRM login: ' . implode(',', $withLogin)];
    }

    $employees = array_values(array_unique(Relationship::get(FALSE)
      ->addSelect('contact_id_a')
      ->addWhere('contact_id_a', 'IN', $ids)
      ->addWhere('relationship_type_id:name', '=', 'Employee of')
      ->addWhere('is_active', '=', TRUE)
      ->execute()->column('contact_id_a')));
    if (count($employees) === 1) {
      return ['id' => (int) $employees[0], 'name' => $name($employees[0]), 'note' => 'Employee of a Goonj office; ' . count($ids) . ' contacts share the email'];
    }

    $sheetFirstName = explode(' ', $this->normaliseName($sheetName))[0];
    $byName = $sheetFirstName === '' ? [] : array_values(array_filter($ids, fn($id) => $this->normaliseName((string) $contacts[$id]['contact.first_name']) === $sheetFirstName));
    if (count($byName) === 1) {
      return ['id' => (int) $byName[0], 'name' => $name($byName[0]), 'note' => "first name matches the sheet's attended-by name; " . count($ids) . ' contacts share the email'];
    }

    return ['id' => NULL, 'name' => '', 'note' => 'cannot pick one of: ' . implode(',', $ids)];
  }

  /**
   * Key of a row's "Attended By" in the staff lookup.
   */
  private function staffKey(array $row): string {
    return strtolower($row[self::COL_ATTENDED_BY_EMAIL]) . '|' . $this->normaliseName($row[self::COL_ATTENDED_BY_NAME]);
  }

  /**
   * Finds a Goonj office by its exact name.
   */
  private function findOffice(string $name): ?int {
    $offices = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('organization_name', '=', $name)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute();
    return $offices->count() === 1 ? (int) $offices->first()['id'] : NULL;
  }

  /**
   * Finds an Indian state by its exact name.
   */
  private function findIndianState(string $name): ?int {
    $states = StateProvince::get(FALSE)
      ->addSelect('id')
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->addWhere('name', '=', $name)
      ->execute();
    return $states->count() === 1 ? (int) $states->first()['id'] : NULL;
  }

  /**
   * Prints the row counts.
   */
  private function printSummary(array $counts): void {
    echo "\nSummary:\n";
    ksort($counts);
    foreach ($counts as $status => $count) {
      echo sprintf("  %-30s %d\n", $status, $count);
    }
  }

  /**
   * Parses a 'YYYY-MM-DD H:MM:SS' sheet date.
   */
  private function parseDate(string $value): ?string {
    $date = \DateTime::createFromFormat('Y-m-d G:i:s', $value) ?: \DateTime::createFromFormat('Y-m-d H:i:s', $value);
    $errors = \DateTime::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
      return NULL;
    }
    return $date->format('Y-m-d H:i:s');
  }

  /**
   * Parses '1,500' style numbers.
   */
  private function parseNumber(string $value): ?float {
    $value = str_replace(',', '', trim($value));
    return ($value !== '' && is_numeric($value)) ? (float) $value : NULL;
  }

  /**
   * Collapses line breaks and repeated spaces (for one-line text fields).
   */
  private function singleLine(string $value): string {
    return trim(preg_replace('/\s+/u', ' ', $value));
  }

  /**
   * Lower-cases a name and collapses its spaces.
   */
  private function normaliseName(string $value): string {
    return mb_strtolower($this->singleLine($value));
  }

  /**
   * Returns the last ten digits of a phone number.
   */
  private function lastTenDigits(string $value): string {
    $digits = preg_replace('/\D/', '', $value);
    return strlen($digits) >= 10 ? substr($digits, -10) : '';
  }

  /**
   * Prints the message and stops.
   */
  private function fail(string $message): void {
    echo "ERROR: $message\n";
    exit(1);
  }

}

(new GoonjLegacyIndividualActivityImport())->run();
