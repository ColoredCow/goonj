<?php

/**
 * @file
 * Imports legacy Goonj events from the old system.
 *
 * For every sheet row the script creates one CiviCRM event with:
 * - the event title (code), type, default role, start and end date, and the
 *   sheet's Created Date;
 * - the venue as the event location: Event Venue, City, State, India. A venue
 *   longer than one address line (96 characters) continues on the
 *   supplemental address lines, so no text is lost;
 * - "Goonj Events": Coordinating Goonj Office, Goonj Coordinating POC (Main)
 *   (the sheet's Goonj Coordinating POC), Agenda;
 * - "Goonj Events Outcome": Total Online Monetary Contributions (the sheet's
 *   Total Amount Contributed, as in the earlier event import), Product Sale
 *   Amount, Any Difficulty/challenge faced, Event MOM.
 * The "Goonj Event Status", "Goonj Event Record Type" and "FY" columns have no
 * field in the CRM and are not imported. "Number of Participants" is empty in
 * the sheet. Events are created like live events: active, not public, no
 * online registration.
 *
 * Rules agreed with Goonj (coloredcow-admin/goonj-crm#234), the same as for
 * the camp sheets:
 * - the Goonj Coordinating POC is the staff contact with the sheet's email
 *   (CRM login -> Employee of -> first name when several contacts share it);
 * - an event code used twice (in the sheet, or already by another event in the
 *   CRM) gets an extra 0 at the start of the number: 2023/GUJ/Volu/002 ->
 *   2023/GUJ/Volu/0002.
 * Other values that are not in the sheet are not filled in.
 *
 * No email may go out for these old events:
 * - "Outcome Email Sent" is saved, so the daily outcome job (which emails the
 *   Goonj Coordinating POC for every started event without it) skips them;
 * - "Last Reminder Sent" (participant feedback job) and "Event No Show
 *   Triggered" (participant no-show job) are saved too;
 * - no participants are created, so no Scheduled Reminder has anyone to
 *   email; every active event reminder is set up for one existing event.
 * CiviCRM always stamps a new event's created date with "now", so the sheet's
 * Created Date is saved in a second save. That save also creates the event QR
 * code (system hook), as edits of live events do; it sends nothing.
 *
 * Each row is saved in one transaction, so a row is either fully saved or not
 * saved at all.
 *
 * Every row that is not imported (a value that cannot be matched, an error
 * while saving) is written to an issues sheet: the sheet's own columns plus
 * "Import status" and "Reason".
 *
 * Runs as a dry run by default: nothing is saved, and every row is listed in a
 * CSV report with the matches and what would be created. Rows already
 * imported are skipped, so the script can be run again safely.
 *
 * Usage:
 *   Dry run:
 *     CSV=/path/to/sheet.csv cv scr wp-content/civi-extensions/goonjcustom/cli/import-legacy-goonj-events.php
 *   Save only the first 2 events (to show the client first):
 *     LIMIT=2 APPLY=1 CSV=... cv scr ...
 *   Save all (events already saved are skipped):
 *     APPLY=1 CSV=... cv scr ...
 *   Only some sheet lines (line numbers as in the spreadsheet, data starts on 3):
 *     ROWS=3-12 CSV=... cv scr ...
 *   Event type for sheet lines whose type is not an event type in the CRM
 *   (sheet line = event type name, comma separated):
 *     TYPE_FOR_LINES=33=Volunteer Meet CSV=... cv scr ...
 *   Report (every row) and issues sheet (rows not imported) locations,
 *   default: next to the CSV:
 *     REPORT=/path/to/report.csv ISSUES=/path/to/issues.csv
 */

use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Event;
use Civi\Api4\LocBlock;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Api4\UFMatch;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Imports one legacy sheet of Goonj events.
 */
class GoonjLegacyEventImport {

  /**
   * Sheet columns, in order, as on the second header row.
   */
  const COLUMNS = [
    'Event Title',
    'Event Type',
    'Coordinating Goonj Office',
    'State',
    'City',
    'Event Venue',
    'Created Date (YYYY-MM-DD)',
    'Start Date',
    'End Date',
    'Goonj Event Status',
    'Goonj Event Record Type',
    'Default Role',
    'Goonj Coordinating POC (Mail)',
    'Goonj Coordinating POC (Name)',
    'Number of Participants',
    'Total Amount Contributed',
    'Product Sale Amount',
    'Any Difficulty/challenge faced',
    'Agenda',
    'Event MOM',
    'FY',
  ];

  /**
   * Columns added to the issues sheet.
   */
  const ISSUE_COLUMNS = ['Import status', 'Reason'];

  const COL_TITLE = 0;
  const COL_TYPE = 1;
  const COL_OFFICE = 2;
  const COL_STATE = 3;
  const COL_CITY = 4;
  const COL_VENUE = 5;
  const COL_CREATED = 6;
  const COL_START = 7;
  const COL_END = 8;
  const COL_STATUS = 9;
  const COL_RECORD_TYPE = 10;
  const COL_ROLE = 11;
  const COL_POC_EMAIL = 12;
  const COL_POC_NAME = 13;
  const COL_PARTICIPANTS = 14;
  const COL_TOTAL_AMOUNT = 15;
  const COL_PRODUCT_SALE = 16;
  const COL_DIFFICULTY = 17;
  const COL_AGENDA = 18;
  const COL_MOM = 19;

  /**
   * The first sheet line with data (two header rows).
   */
  const FIRST_DATA_LINE = 3;

  /**
   * Length of one address line in the CRM.
   */
  const ADDRESS_LINE_LENGTH = 96;

  private string $csvPath;
  private bool $apply;
  private ?int $limit = NULL;
  private ?int $fromLine = NULL;
  private ?int $toLine = NULL;
  private string $reportPath;
  private string $issuesPath;

  /**
   * Event type name given for a sheet line (TYPE_FOR_LINES).
   */
  private array $typeForLines = [];

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
   * Lookups resolved before any row is saved.
   */
  private array $offices = [];
  private array $staff = [];
  private array $states = [];
  private array $eventTypes = [];
  private array $roles = [];
  private ?int $indiaId = NULL;

  /**
   * Reads the settings from the environment.
   */
  public function __construct() {
    $this->csvPath = (string) getenv('CSV');
    $this->apply = getenv('APPLY') === '1';

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

    $types = trim((string) getenv('TYPE_FOR_LINES'));
    if ($types !== '') {
      foreach (explode(',', $types) as $pair) {
        if (!preg_match('/^\s*(\d+)\s*=\s*(.+?)\s*$/', $pair, $m)) {
          $this->fail("TYPE_FOR_LINES must look like 33=Volunteer Meet, got: $types");
        }
        $this->typeForLines[(int) $m[1]] = $m[2];
      }
    }

    $base = preg_replace('/\.csv$/i', '', $this->csvPath);
    $report = trim((string) getenv('REPORT'));
    $this->reportPath = $report !== '' ? $report : $base . '-report-' . date('Ymd-His') . '.csv';
    $issues = trim((string) getenv('ISSUES'));
    $this->issuesPath = $issues !== '' ? $issues : $base . '-issues-' . date('Ymd-His') . '.csv';
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
      echo "LIMIT: stop after {$this->limit} event(s)\n";
    }

    $rows = $this->readSheet();
    echo 'Rows selected: ' . count($rows) . "\n\n";

    $this->resolveLookups($rows);

    $report = fopen($this->reportPath, 'w');
    if (!$report) {
      $this->fail('Cannot write the report: ' . $this->reportPath);
    }
    fputcsv($report, [
      'sheet_line', 'event_title', 'event_type', 'office_id', 'poc_email',
      'poc_contact_id', 'status', 'event_id', 'loc_block_id', 'address_id', 'note',
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
      set_time_limit(0);

      $result = $this->processRow($line, $row);
      $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
      if (in_array($result['status'], ['CREATED', 'DRY_RUN_OK'], TRUE)) {
        $done++;
      }

      fputcsv($report, [
        $line,
        $result['code'] ?? $row[self::COL_TITLE],
        $result['event_type'] ?? '',
        $this->offices[$row[self::COL_OFFICE]] ?? '',
        $row[self::COL_POC_EMAIL],
        $this->staff[$this->staffKey($row)]['id'] ?? '',
        $result['status'],
        $result['event_id'] ?? '',
        $result['loc_block_id'] ?? '',
        $result['address_id'] ?? '',
        $result['note'] ?? '',
      ], ',', '"', '\\');

      if ($result['status'] === 'CREATED') {
        echo "Line $line: {$result['code']} => event {$result['event_id']}\n";
      }
      if (!in_array($result['status'], ['CREATED', 'DRY_RUN_OK', 'SKIPPED_ALREADY_IMPORTED'], TRUE)) {
        $issueCount++;
        fputcsv($issues, array_merge($row, [$result['status'], trim($result['note'] ?? '')]), ',', '"', '\\');
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
   * Gives an event code used by more than one row a unique code.
   *
   * The first row keeps the code; each later row with the same code gets an
   * extra 0 at the start of the number: 2023/GUJ/Volu/002 becomes
   * 2023/GUJ/Volu/0002. A title that is not a code is kept as written.
   */
  private function makeDuplicateCodesUnique(array &$rows): void {
    $sheetCodes = [];
    foreach ($rows as $row) {
      $sheetCodes[$row[self::COL_TITLE]] = TRUE;
    }

    $used = [];
    foreach ($rows as $line => $row) {
      $code = $row[self::COL_TITLE];
      if (strpos($code, '/') === FALSE) {
        continue;
      }
      $final = $code;
      if (isset($used[$code])) {
        do {
          $final = $this->addZero($final);
        } while (isset($used[$final]) || isset($sheetCodes[$final]));
        $rows[$line][self::COL_TITLE] = $final;
        $this->changedCodes[$line] = "Code $code is used twice in the sheet, saved as $final.";
      }
      $used[$final] = TRUE;
    }
  }

  /**
   * Adds a 0 at the start of a code's number: 2023/GUJ/Volu/002 -> .../0002.
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
    $this->eventTypes = $this->optionNames('event_type');
    $this->roles = $this->optionNames('participant_role');
    $this->indiaId = (int) StateProvince::get(FALSE)
      ->addSelect('country_id')
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->setLimit(1)
      ->execute()->first()['country_id'];

    foreach ($rows as $line => $row) {
      $type = $this->typeForLines[$line] ?? $row[self::COL_TYPE];
      if (!isset($this->eventTypes[mb_strtolower($type)])) {
        $this->rowErrors[$line][] = "event type not found: '$type'";
      }
      if (!isset($this->roles[mb_strtolower($row[self::COL_ROLE])])) {
        $this->rowErrors[$line][] = "default role not found: '{$row[self::COL_ROLE]}'";
      }

      $office = $row[self::COL_OFFICE];
      if (!array_key_exists($office, $this->offices)) {
        $this->offices[$office] = $this->findOffice($office);
      }
      if (!$this->offices[$office]) {
        $this->rowErrors[$line][] = "Goonj office not found: '$office'";
      }

      $staffEmail = strtolower($row[self::COL_POC_EMAIL]);
      $staffKey = $this->staffKey($row);
      if ($staffEmail === '') {
        $this->rowErrors[$line][] = 'Goonj Coordinating POC (Mail) is empty; the field is required on events';
      }
      elseif (!array_key_exists($staffKey, $this->staff)) {
        $this->staff[$staffKey] = $this->findStaff($staffEmail, $row[self::COL_POC_NAME]);
      }
      if ($staffEmail !== '' && empty($this->staff[$staffKey]['id'])) {
        $this->rowErrors[$line][] = "Goonj Coordinating POC not resolved: $staffEmail ({$this->staff[$staffKey]['note']})";
      }

      $state = $row[self::COL_STATE];
      if (!array_key_exists($state, $this->states)) {
        $this->states[$state] = $this->findIndianState($state);
      }
      if (!$this->states[$state]) {
        $this->rowErrors[$line][] = "state not found: '$state'";
      }

      if (count($this->addressLines($row[self::COL_VENUE])) > 4) {
        $this->rowErrors[$line][] = 'Event Venue is longer than the four address lines';
      }
      foreach ([self::COL_CREATED, self::COL_START, self::COL_END] as $col) {
        if (!$this->parseDate($row[$col])) {
          $this->rowErrors[$line][] = "bad date in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
      if ($this->parseDate($row[self::COL_START]) > $this->parseDate($row[self::COL_END])) {
        $this->rowErrors[$line][] = 'Start Date is after End Date';
      }
      foreach ([self::COL_TOTAL_AMOUNT, self::COL_PRODUCT_SALE] as $col) {
        if ($row[$col] !== '' && $this->parseNumber($row[$col]) === NULL) {
          $this->rowErrors[$line][] = "not a number in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
      if ($row[self::COL_PARTICIPANTS] !== '') {
        $this->rowErrors[$line][] = "'Number of Participants' is filled; this script has no field for it";
      }
      if (mb_strlen($this->singleLine($row[self::COL_DIFFICULTY])) > 255) {
        $this->rowErrors[$line][] = "'Any Difficulty/challenge faced' is longer than 255 characters";
      }
      if (mb_strlen($this->singleLine($row[self::COL_AGENDA])) > 2000) {
        $this->rowErrors[$line][] = "'Agenda' is longer than 2000 characters";
      }
    }

    echo "Lookups:\n";
    foreach ($this->offices as $name => $id) {
      echo "  Office    '$name' => " . ($id ?: 'NOT FOUND') . "\n";
    }
    foreach ($this->staff as $key => $staff) {
      echo "  POC       $key => " . ($staff['id'] ?: 'NOT RESOLVED') . " {$staff['name']} ({$staff['note']})\n";
    }
    foreach ($this->typeForLines as $typeLine => $type) {
      echo "  Type      line $typeLine => '$type' (given with TYPE_FOR_LINES)\n";
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
    $type = $this->typeForLines[$line] ?? $row[self::COL_TYPE];
    $result = [
      'code' => $row[self::COL_TITLE],
      'event_type' => $this->eventTypes[mb_strtolower($type)] ?? $type,
      'note' => $this->changedCodes[$line] ?? '',
    ];
    if (!empty($this->rowErrors[$line])) {
      return ['status' => 'SKIPPED_BAD_DATA', 'note' => trim($result['note'] . ' ' . implode('; ', array_unique($this->rowErrors[$line])))] + $result;
    }

    // The code this row is saved with: a code already used by another event
    // in the CRM gets an extra 0 at the start of the number, as for a code used
    // twice in the sheet. An event this row saved on an earlier run is found
    // under the code it was saved with.
    $code = $row[self::COL_TITLE];
    while (TRUE) {
      $existing = $this->findExistingEvent($row, $code, $result['event_type']);
      if ($existing) {
        return ['status' => 'SKIPPED_ALREADY_IMPORTED', 'event_id' => $existing, 'code' => $code] + $result;
      }
      if (strpos($code, '/') === FALSE) {
        break;
      }
      $sameCode = Event::get(FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $code)
        ->addWhere('is_template', '=', FALSE)
        ->execute()->first();
      if (!$sameCode) {
        break;
      }
      $newCode = $this->addZero($code);
      $result['note'] = trim($result['note'] . " Code $code is already used by event {$sameCode['id']} in the CRM, saved as $newCode.");
      $code = $newCode;
    }
    $result['code'] = $code;

    $addressLines = $this->addressLines($row[self::COL_VENUE]);
    if (count($addressLines) > 1) {
      $result['note'] = trim($result['note'] . ' Venue saved on ' . count($addressLines) . ' address lines.');
    }

    if (!$this->apply) {
      return ['status' => 'DRY_RUN_OK'] + $result;
    }

    $transaction = new \CRM_Core_Transaction();
    try {
      [$addressId, $locBlockId] = $this->createLocation($row);
      $eventId = $this->createEvent($row, $code, $result['event_type'], $locBlockId);
      $transaction->commit();

      return [
        'status' => 'CREATED',
        'event_id' => $eventId,
        'loc_block_id' => $locBlockId,
        'address_id' => $addressId,
      ] + $result;
    }
    catch (\Throwable $e) {
      $transaction->rollback()->commit();
      return ['status' => 'ERROR', 'note' => trim($result['note'] . ' ' . $e->getMessage())] + $result;
    }
  }

  /**
   * Creates the event's address and location block.
   */
  private function createLocation(array $row): array {
    $lines = $this->addressLines($row[self::COL_VENUE]);
    $address = Address::create(FALSE)
      ->addValue('location_type_id:name', 'Main')
      ->addValue('is_primary', TRUE)
      ->addValue('street_address', $lines[0] ?? NULL)
      ->addValue('supplemental_address_1', $lines[1] ?? NULL)
      ->addValue('supplemental_address_2', $lines[2] ?? NULL)
      ->addValue('supplemental_address_3', $lines[3] ?? NULL)
      ->addValue('city', $this->singleLine($row[self::COL_CITY]) ?: NULL)
      ->addValue('state_province_id', $this->states[$row[self::COL_STATE]])
      ->addValue('country_id', $this->indiaId)
      ->execute()->single();
    $locBlock = LocBlock::create(FALSE)
      ->addValue('address_id', $address['id'])
      ->execute()->single();
    return [(int) $address['id'], (int) $locBlock['id']];
  }

  /**
   * Creates the event with every sheet value and "already emailed" flag.
   */
  private function createEvent(array $row, string $code, string $eventType, int $locBlockId): int {
    $values = [
      'title' => $code,
      'event_type_id:name' => $eventType,
      'default_role_id:name' => $this->roles[mb_strtolower($row[self::COL_ROLE])],
      'start_date' => $this->parseDate($row[self::COL_START]),
      'end_date' => $this->parseDate($row[self::COL_END]),
      'loc_block_id' => $locBlockId,
      'is_active' => TRUE,
      'is_public' => FALSE,
      'is_online_registration' => FALSE,
      'is_template' => FALSE,
      'is_show_location' => TRUE,
      'Goonj_Events.Coordinating_Goonj_Office' => $this->offices[$row[self::COL_OFFICE]],
      'Goonj_Events.Goonj_Coordinating_POC_Main_' => $this->staff[$this->staffKey($row)]['id'],
      'Goonj_Events.Agenda' => $this->singleLine($row[self::COL_AGENDA]) ?: NULL,
      'Goonj_Events_Outcome.Online_Monetary_Contribution' => $this->parseNumber($row[self::COL_TOTAL_AMOUNT]),
      'Goonj_Events_Outcome.Product_Sale_Amount' => $this->parseNumber($row[self::COL_PRODUCT_SALE]),
      'Goonj_Events_Outcome.Any_Difficulty_challenge_faced' => $this->singleLine($row[self::COL_DIFFICULTY]) ?: NULL,
      'Goonj_Events_Outcome.Event_MOM' => $this->paragraphs($row[self::COL_MOM]) ?: NULL,
      // Already emailed: the daily outcome job emails the Goonj Coordinating
      // POC for every started event without this.
      'Goonj_Events_Outcome.Outcome_Email_Sent' => TRUE,
      // Already done: participant feedback and no-show jobs.
      'Goonj_Events_Feedback.Last_Reminder_Sent' => TRUE,
      'Goonj_Events_Feedback.Event_No_Show_Triggered' => TRUE,
    ];
    $eventId = (int) Event::create(FALSE)->setValues($values)->execute()->single()['id'];

    // CiviCRM stamps a new event's created date with "now"; an edit keeps the
    // value it is given.
    Event::update(FALSE)
      ->addValue('created_date', $this->parseDate($row[self::COL_CREATED]))
      ->addWhere('id', '=', $eventId)
      ->execute();

    return $eventId;
  }

  /**
   * Returns the id of the event this sheet row already created, if any.
   */
  private function findExistingEvent(array $row, string $code, string $eventType): ?int {
    $event = Event::get(FALSE)
      ->addSelect('id')
      ->addWhere('title', '=', $code)
      ->addWhere('event_type_id:name', '=', $eventType)
      ->addWhere('start_date', '=', $this->parseDate($row[self::COL_START]))
      ->addWhere('is_template', '=', FALSE)
      ->execute()->first();
    return $event ? (int) $event['id'] : NULL;
  }

  /**
   * Splits the venue into address lines of at most 96 characters.
   *
   * Lines break between words; a word longer than a line is cut.
   */
  private function addressLines(string $venue): array {
    $text = $this->singleLine($venue);
    if ($text === '') {
      return [];
    }
    $wrapped = wordwrap($text, self::ADDRESS_LINE_LENGTH, "\n", TRUE);
    return array_map('trim', explode("\n", $wrapped));
  }

  /**
   * Finds the staff contact for a sheet email.
   *
   * When several contacts share the email: the one with a CRM login, then the
   * one that is "Employee of" a Goonj office, then the one whose first name
   * matches the sheet's name.
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
      return ['id' => (int) $byName[0], 'name' => $name($byName[0]), 'note' => "first name matches the sheet's POC name; " . count($ids) . ' contacts share the email'];
    }

    return ['id' => NULL, 'name' => '', 'note' => 'cannot pick one of: ' . implode(',', $ids)];
  }

  /**
   * Key of a row's Goonj Coordinating POC in the staff lookup.
   */
  private function staffKey(array $row): string {
    return strtolower($row[self::COL_POC_EMAIL]) . '|' . $this->normaliseName($row[self::COL_POC_NAME]);
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
   * Returns an option group's active options: lower-cased name/label => name.
   *
   * Names win over labels: labels can be changed in the admin UI.
   */
  private function optionNames(string $group): array {
    $all = OptionValue::get(FALSE)
      ->addSelect('name', 'label')
      ->addWhere('option_group_id:name', '=', $group)
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    $options = [];
    foreach ($all as $option) {
      $options[mb_strtolower($option['label'])] = $option['name'];
    }
    foreach ($all as $option) {
      $options[mb_strtolower($option['name'])] = $option['name'];
    }
    return $options;
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
   * Keeps the line breaks of a multi-line text, without blank-line runs.
   */
  private function paragraphs(string $value): string {
    $lines = array_map(fn($l) => $this->singleLine($l), preg_split('/\R/u', $value));
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
  }

  /**
   * Lower-cases a name and collapses its spaces.
   */
  private function normaliseName(string $value): string {
    return mb_strtolower($this->singleLine($value));
  }

  /**
   * Prints the message and stops.
   */
  private function fail(string $message): void {
    echo "ERROR: $message\n";
    exit(1);
  }

}

(new GoonjLegacyEventImport())->run();
