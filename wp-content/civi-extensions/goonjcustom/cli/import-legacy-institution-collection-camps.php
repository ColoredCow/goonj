<?php

/**
 * @file
 * Imports legacy institution collection camps from the old system.
 *
 * For every sheet row the script creates:
 * - one Institution Collection Camp (camp details, logistics and outcome),
 * - one Vehicle Dispatch linked to the camp (when the row has dispatch data),
 * - an "Organize Institution Collection Camp" activity on the institution POC
 *   (when found) and on the organisation, like the live flow does.
 *
 * The organisations and the POC contacts must already exist (they are imported
 * first with the CiviCRM contact import). The organisation is found by its
 * name, which is unique; when two organisations have the name, the older one
 * (lowest contact id) is used. The "Contact ID", "Goonj Event ID", "FY" and
 * "Number of vehicles" columns have no field in the CRM and are not imported.
 *
 * Rules agreed with Goonj (coloredcow-admin/goonj-crm#234), the same as for
 * the individual camps sheet:
 * - the person in "Camp Organiser's details" is the camp's Institution POC;
 *   camps with no person details are imported without a POC;
 * - dispatch date and acknowledgement (contribution) date = camp End Date;
 * - Coordinating POC = the "Camp attended by" person;
 * - a camp code used twice (in the sheet, or already by another camp in the
 *   CRM) gets an extra 0 at the start of the number: 2022/MHR/Coll/552 ->
 *   2022/MHR/Coll/0552;
 * - an organiser whose wrong email was removed is matched by first name +
 *   mobile;
 * - fixed values on every camp: "Self Managed by Institution" = No when "Camp
 *   attended by" is filled; on every dispatch: "Number of Bags loaded in
 *   vehicle" and "No. of bags received at PU/Office" = "imported data", "How
 *   much vehicle is filled" = Full;
 * - "You wish to register as" comes from the sheet's "Register as" column,
 *   which Goonj filled for every row;
 * - the dispatch's institution section (name, address, email, contact number)
 *   is filled from the sheet's Institution Name, Address, and the POC's email
 *   and mobile.
 * Other values that are not in the sheet are not filled in.
 *
 * No email may go out for these old camps:
 * - every "email sent" / "reminder sent" field that a scheduled job checks is
 *   saved on the camp and the dispatch, so the jobs treat these camps as
 *   already emailed;
 * - the camp is created already Authorized in one save, so the hooks that act
 *   when an existing camp becomes Authorized (authorization email, QR,
 *   Organize activity, camp status) do not run;
 * - the dispatch is created without "To which PU/Center": setting it while
 *   creating the dispatch emails the Material Management Team.
 *
 * Each row is saved in one transaction, so a row is either fully saved or not
 * saved at all.
 *
 * Every row that is not imported (organisation or POC not found, a value that
 * cannot be matched, an error while saving) is written to an issues sheet: the
 * sheet's own columns plus "Import status" and "Reason".
 *
 * Runs as a dry run by default: nothing is saved, and every row is listed in a
 * CSV report with the matches and what would be created. Rows already
 * imported are skipped, so the script can be run again safely.
 *
 * Usage:
 *   Dry run:
 *     CSV=/path/to/sheet.csv cv scr wp-content/civi-extensions/goonjcustom/cli/import-legacy-institution-collection-camps.php
 *   Save only the first 2 camps (to show the client first):
 *     LIMIT=2 APPLY=1 CSV=... cv scr ...
 *   Save all (camps already saved are skipped):
 *     APPLY=1 CSV=... cv scr ...
 *   Only some sheet lines (line numbers as in the spreadsheet, data starts on 3):
 *     ROWS=3-12 CSV=... cv scr ...
 *   Organisation for sheet lines whose institution name is not found as
 *   written (sheet line = organisation contact id, comma separated):
 *     ORG_FOR_LINES=45=596713,284=597199 CSV=... cv scr ...
 *   Institution POC for sheet lines whose person cannot be matched (sheet
 *   line = individual contact id, comma separated):
 *     POC_FOR_LINES=788=532153 CSV=... cv scr ...
 *   Rows whose POC has details but is not found go to the issues sheet; to
 *   import those without a POC:
 *     ALLOW_NO_POC=1 APPLY=1 CSV=... cv scr ...
 *   Report (every row) and issues sheet (rows not imported) locations,
 *   default: next to the CSV:
 *     REPORT=/path/to/report.csv ISSUES=/path/to/issues.csv
 */

use Civi\Api4\Activity;
use Civi\Api4\Campaign;
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
 * Imports one legacy sheet of institution collection camps.
 */
class GoonjLegacyInstitutionCampImport {

  /**
   * Sheet columns, in order, as on the second header row.
   */
  const COLUMNS = [
    'Institution Type',
    'Educational institute type',
    'Institution Name',
    'State',
    'City',
    'First Name',
    'Last Name',
    'Mobile',
    'Email',
    'Contact ID',
    'Register as',
    'Address',
    'Code/ Title',
    'Coordinating Goonj Office',
    'Type',
    'Created Date (YYYY-MM-DD)',
    'Start Date',
    'End Date',
    'Location/Area of camp',
    'Camp Status',
    'State',
    'City',
    'Country',
    'Campaign Name',
    'Campaign ID (CRM)',
    'Camp Type',
    'Material weight (In KGs)',
    'Number of vehicles',
    'Vehicle Category',
    'Camp attended by (email)',
    'Camp attended by (Name)',
    'Support person details',
    'Pickup info',
    'cash/cheque monetary',
    'Product Sale Amount',
    'Any unique efforts made by organizers',
    'Difficulty/challenge faced by organizers',
    'Rate the camp',
    'Any remarks for interl use',
    'Goonj Event ID',
    'FY',
  ];

  /**
   * Columns the issues sheet adds after the sheet columns.
   */
  const ISSUE_COLUMNS = ['Import status', 'Reason'];

  const COL_INSTITUTION_NAME = 2;
  const COL_FIRST_NAME = 5;
  const COL_LAST_NAME = 6;
  const COL_MOBILE = 7;
  const COL_EMAIL = 8;
  const COL_REGISTER_AS = 10;
  const COL_ADDRESS = 11;
  const COL_CODE = 12;
  const COL_OFFICE = 13;
  const COL_TYPE = 14;
  const COL_CREATED = 15;
  const COL_START = 16;
  const COL_END = 17;
  const COL_LOCATION = 18;
  const COL_CAMP_STATUS = 19;
  const COL_CAMP_STATE = 20;
  const COL_CAMP_CITY = 21;
  const COL_COUNTRY = 22;
  const COL_CAMPAIGN_ID = 24;
  const COL_CAMP_TYPE = 25;
  const COL_WEIGHT = 26;
  const COL_VEHICLES = 27;
  const COL_VEHICLE_CATEGORY = 28;
  const COL_ATTENDED_BY_EMAIL = 29;
  const COL_ATTENDED_BY_NAME = 30;
  const COL_SUPPORT_PERSON = 31;
  const COL_PICKUP_INFO = 32;
  const COL_CASH = 33;
  const COL_PRODUCT_SALE = 34;
  const COL_UNIQUE_EFFORTS = 35;
  const COL_DIFFICULTY = 36;
  const COL_RATE = 37;
  const COL_REMARKS = 38;

  /**
   * First data line of the sheet (line 1 and 2 are headers).
   */
  const FIRST_DATA_LINE = 3;

  private string $csvPath;
  private bool $apply;
  private bool $allowNoPoc;
  private ?int $limit = NULL;
  private ?int $fromLine = NULL;
  private ?int $toLine = NULL;
  private string $reportPath;
  private string $issuesPath;

  /**
   * Organisation contact id given for a sheet line (ORG_FOR_LINES).
   */
  private array $orgForLines = [];

  /**
   * Institution POC contact id given for a sheet line (POC_FOR_LINES).
   */
  private array $pocForLines = [];

  /**
   * The contacts given with POC_FOR_LINES, keyed by contact id.
   */
  private array $givenPocs = [];

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
  private array $campaigns = [];
  private array $organisations = [];
  private array $vehicleCategories = [];
  private array $rates = [];
  private array $campTypes = [];
  private array $campStatuses = [];
  private array $registerAs = [];

  /**
   * Reads the settings from the environment.
   */
  public function __construct() {
    $this->csvPath = (string) getenv('CSV');
    $this->apply = getenv('APPLY') === '1';
    $this->allowNoPoc = getenv('ALLOW_NO_POC') === '1';

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

    $this->orgForLines = $this->lineMap('ORG_FOR_LINES');
    $this->pocForLines = $this->lineMap('POC_FOR_LINES');

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
        $this->fail("$name must look like 45=596713,284=597199, got: $value");
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
      echo "LIMIT: stop after {$this->limit} camp(s)\n";
    }

    $rows = $this->readSheet();
    echo 'Rows selected: ' . count($rows) . "\n\n";

    $this->resolveLookups($rows);

    $report = fopen($this->reportPath, 'w');
    if (!$report) {
      $this->fail('Cannot write the report: ' . $this->reportPath);
    }
    fputcsv($report, [
      'sheet_line', 'camp_code', 'institution', 'organisation_id', 'poc',
      'poc_email', 'status', 'poc_contact_id', 'match', 'match_note', 'camp_id',
      'dispatch_id', 'activity_ids', 'note',
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
      // Something in the bootstrap sets a 300 second limit; a full sheet takes
      // much longer. Reset it for every row.
      set_time_limit(0);

      $result = $this->processRow($line, $row);
      $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
      if (in_array($result['status'], ['CREATED', 'DRY_RUN_OK'], TRUE)) {
        $done++;
      }

      fputcsv($report, [
        $line,
        $result['code'] ?? $row[self::COL_CODE],
        $row[self::COL_INSTITUTION_NAME],
        $result['organisation_id'] ?? '',
        trim($row[self::COL_FIRST_NAME] . ' ' . $row[self::COL_LAST_NAME]),
        $row[self::COL_EMAIL],
        $result['status'],
        $result['contact_id'] ?? '',
        $result['match'] ?? '',
        $result['match_note'] ?? '',
        $result['camp_id'] ?? '',
        $result['dispatch_id'] ?? '',
        $result['activity_ids'] ?? '',
        $result['note'] ?? '',
      ], ',', '"', '\\');

      if ($result['status'] === 'CREATED') {
        echo "Line $line: {$result['code']} => camp {$result['camp_id']}\n";
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
   * Gives a camp code used by more than one row a unique code.
   *
   * The first row keeps the code; each later row with the same code gets an
   * extra 0 at the start of the number: 2022/MHR/Coll/552 becomes
   * 2022/MHR/Coll/0552 (Goonj's answer on coloredcow-admin/goonj-crm#234).
   * A title that is not a code ("Collection Camp") is kept as written.
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
        $final = $code;
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
   * Adds a 0 at the start of a code's number: 2021/MHR/Coll/069 -> .../0069.
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
    $this->vehicleCategories = $this->optionsByLabel('Camp_Vehicle_Dispatch', 'Vehicle_Category');
    $this->rates = $this->optionsByLabel('Camp_Outcome', 'Rate_the_camp');
    $this->campTypes = $this->optionsByLabel('Institution_collection_camp_Review', 'Is_the_camp_IHC_PCC_');
    $this->campStatuses = $this->optionsByLabel('Institution_collection_camp_Review', 'Camp_Status');
    $this->registerAs = $this->optionsByLabel('Institution_Collection_Camp_Intent', 'You_wish_to_register_as');

    foreach ($rows as $line => $row) {
      $orgKey = $this->organisationKey($line, $row);
      if (!array_key_exists($orgKey, $this->organisations)) {
        $this->organisations[$orgKey] = isset($this->orgForLines[$line])
          ? $this->findOrganisationById($this->orgForLines[$line])
          : $this->findOrganisation($row[self::COL_INSTITUTION_NAME]);
      }
      if (empty($this->organisations[$orgKey]['id'])) {
        $this->rowErrors[$line][] = 'organisation not found: ' . $this->organisations[$orgKey]['note'];
      }

      if (isset($this->pocForLines[$line])) {
        $pocId = $this->pocForLines[$line];
        if (!array_key_exists($pocId, $this->givenPocs)) {
          $this->givenPocs[$pocId] = Contact::get(FALSE)
            ->addSelect('id', 'display_name')
            ->addWhere('id', '=', $pocId)
            ->addWhere('contact_type', '=', 'Individual')
            ->addWhere('is_deleted', '=', FALSE)
            ->execute()->first();
        }
        if (!$this->givenPocs[$pocId]) {
          $this->rowErrors[$line][] = "POC_FOR_LINES contact $pocId is not an individual";
        }
      }

      $registerAs = $row[self::COL_REGISTER_AS];
      if ($registerAs !== '' && !isset($this->registerAs[$registerAs])) {
        $this->rowErrors[$line][] = "'Register as' value not found: '$registerAs'";
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
        $this->rowErrors[$line][] = "'Camp attended by' not resolved: $staffEmail ({$this->staff[$staffKey]['note']})";
      }

      $state = $row[self::COL_CAMP_STATE];
      if (!array_key_exists($state, $this->states)) {
        $this->states[$state] = $this->findIndianState($state);
      }
      if (!$this->states[$state]) {
        $this->rowErrors[$line][] = "state not found: '$state'";
      }

      $campaignId = $row[self::COL_CAMPAIGN_ID];
      if (!array_key_exists($campaignId, $this->campaigns)) {
        $this->campaigns[$campaignId] = ctype_digit($campaignId) ? Campaign::get(FALSE)
          ->addSelect('id', 'name', 'title')
          ->addWhere('id', '=', (int) $campaignId)
          ->execute()->first() : NULL;
      }
      if (!$this->campaigns[$campaignId]) {
        $this->rowErrors[$line][] = "campaign id not found: '$campaignId'";
      }

      $category = $row[self::COL_VEHICLE_CATEGORY];
      if ($category !== '' && !isset($this->vehicleCategories[$category])) {
        $this->rowErrors[$line][] = "vehicle category not found: '$category'";
      }
      if (!isset($this->rates[$row[self::COL_RATE]])) {
        $this->rowErrors[$line][] = "'Rate the camp' value not found: '{$row[self::COL_RATE]}'";
      }
      if (!isset($this->campTypes[$row[self::COL_CAMP_TYPE]])) {
        $this->rowErrors[$line][] = "camp type not found: '{$row[self::COL_CAMP_TYPE]}'";
      }
      if (!isset($this->campStatuses[$row[self::COL_CAMP_STATUS]])) {
        $this->rowErrors[$line][] = "camp status not found: '{$row[self::COL_CAMP_STATUS]}'";
      }
      if ($row[self::COL_TYPE] !== 'Collection camp') {
        $this->rowErrors[$line][] = "Type is '{$row[self::COL_TYPE]}', this script only imports 'Collection camp'";
      }
      if ($row[self::COL_COUNTRY] !== 'India') {
        $this->rowErrors[$line][] = "Country is '{$row[self::COL_COUNTRY]}', expected India";
      }
      foreach ([self::COL_CREATED, self::COL_START, self::COL_END] as $col) {
        if (!$this->parseDate($row[$col])) {
          $this->rowErrors[$line][] = "bad date in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
      foreach ([self::COL_WEIGHT, self::COL_CASH, self::COL_PRODUCT_SALE, self::COL_VEHICLES] as $col) {
        if ($row[$col] !== '' && $this->parseNumber($row[$col]) === NULL) {
          $this->rowErrors[$line][] = "not a number in '" . self::COLUMNS[$col] . "': '{$row[$col]}'";
        }
      }
    }

    echo "Lookups:\n";
    foreach ($this->offices as $name => $id) {
      echo "  Office      '$name' => " . ($id ?: 'NOT FOUND') . "\n";
    }
    foreach ($this->staff as $email => $staff) {
      echo "  Attended by $email => " . ($staff['id'] ?: 'NOT RESOLVED') . " {$staff['name']} ({$staff['note']})\n";
    }
    foreach ($this->campaigns as $id => $campaign) {
      echo "  Campaign id $id => " . ($campaign ? "'{$campaign['title']}' (name {$campaign['name']})" : 'NOT FOUND') . "\n";
    }
    foreach ($this->organisations as $key => $organisation) {
      if (str_starts_with($key, 'line:') || empty($organisation['id']) || $organisation['note'] !== 'found by name') {
        echo '  Organisation ' . $this->singleLine($key) . ' => ' . ($organisation['id'] ?: 'NOT FOUND') . " {$organisation['name']} ({$organisation['note']})\n";
      }
    }
    foreach ($this->pocForLines as $pocLine => $pocId) {
      echo "  POC         line $pocLine => " . (!empty($this->givenPocs[$pocId]) ? "$pocId {$this->givenPocs[$pocId]['display_name']}" : "$pocId NOT AN INDIVIDUAL") . " (given with POC_FOR_LINES)\n";
    }
    $found = count(array_filter($this->organisations, fn($o) => !empty($o['id'])));
    echo "  Organisations: $found of " . count($this->organisations) . " found\n\n";

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

    $organisation = $this->organisations[$this->organisationKey($line, $row)];
    $match = isset($this->pocForLines[$line])
      ? ['id' => $this->pocForLines[$line], 'method' => 'POC_FOR_LINES', 'note' => 'given with POC_FOR_LINES']
      : $this->matchPoc($row);
    $result += [
      'organisation_id' => $organisation['id'],
      'contact_id' => $match['id'],
      'match' => $match['method'],
      'match_note' => $match['note'],
    ];

    // The code this row is saved with: a code already used by another camp in
    // the CRM gets an extra 0 at the start of the number, as for a code used
    // twice in the sheet. A camp this row saved on an earlier run is found
    // under the code it was saved with.
    $code = $row[self::COL_CODE];
    while (TRUE) {
      $existing = $this->findExistingCamp($row, $code);
      if ($existing) {
        return ['status' => 'SKIPPED_ALREADY_IMPORTED', 'camp_id' => $existing, 'code' => $code] + $result;
      }
      if (strpos($code, '/') === FALSE) {
        break;
      }
      $sameCode = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $code)
        ->execute()->first();
      if (!$sameCode) {
        break;
      }
      $newCode = $this->addZero($code);
      $result['note'] = trim($result['note'] . " Code $code is already used by camp {$sameCode['id']} in the CRM, saved as $newCode.");
      $code = $newCode;
    }
    $result['code'] = $code;

    if (!$match['id'] && $match['method'] !== 'NO_POC_IN_SHEET' && !$this->allowNoPoc) {
      return ['status' => 'SKIPPED_POC_' . $match['method'], 'note' => trim($result['note'] . ' Institution POC not matched; set ALLOW_NO_POC=1 to import without POC')] + $result;
    }

    $hasDispatch = $row[self::COL_WEIGHT] !== '' || $row[self::COL_VEHICLE_CATEGORY] !== '';

    if (!$this->apply) {
      return [
        'status' => 'DRY_RUN_OK',
        'note' => trim($result['note'] . ' ' . ($hasDispatch ? 'camp + dispatch' : 'camp only (no dispatch data)') . ($match['id'] ? ' + POC' : ' (no POC)')),
      ] + $result;
    }

    $transaction = new \CRM_Core_Transaction();
    try {
      $campId = $this->createCamp($row, $code, $organisation, $match['id']);
      $this->setSheetValuesAfterHooks($campId, $row);
      $dispatchId = $hasDispatch ? $this->createDispatch($campId, $row) : NULL;
      $activityIds = $this->createOrganizeActivities($row, $code, $match['id'], $organisation['id']);
      $transaction->commit();

      return [
        'status' => 'CREATED',
        'camp_id' => $campId,
        'dispatch_id' => $dispatchId,
        'activity_ids' => implode(',', $activityIds),
      ] + $result;
    }
    catch (\Throwable $e) {
      $transaction->rollback()->commit();
      return ['status' => 'ERROR', 'note' => trim($result['note'] . ' ' . $e->getMessage())] + $result;
    }
  }

  /**
   * Creates the camp, already Authorized and Completed, in one save.
   *
   * Created in one save so the hooks that act when an existing camp becomes
   * Authorized (authorization email, QR, Organize activity, camp status) do
   * not run.
   */
  private function createCamp(array $row, string $code, array $organisation, ?int $pocId): int {
    $endDate = $this->parseDate($row[self::COL_END]);
    $attendedById = $this->staff[$this->staffKey($row)]['id'] ?? NULL;

    $values = [
      'subtype:name' => 'Institution_Collection_Camp',
      'title' => $code,
      'created_date' => $this->parseDate($row[self::COL_CREATED]),
      'Collection_Camp_Core_Details.Status:name' => 'Authorized',
      'Institution_collection_camp_Review.Camp_Status:name' => $this->campStatuses[$row[self::COL_CAMP_STATUS]],
      'Institution_collection_camp_Review.Campaign' => (int) $this->campaigns[$row[self::COL_CAMPAIGN_ID]]['id'],
      // The live flow puts the organisation's name here when the camp is
      // authorized.
      'Institution_collection_camp_Review.Name_of_the_Institution' => $organisation['name'],
      'Institution_Collection_Camp_Intent.Organization_Name' => $organisation['id'],
      'Institution_Collection_Camp_Intent.Institution_POC' => $pocId,
      'Institution_Collection_Camp_Intent.Collection_Camp_Address' => $this->singleLine($row[self::COL_LOCATION]),
      'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_' => $this->parseDate($row[self::COL_START]),
      'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_' => $endDate,
      'Institution_Collection_Camp_Intent.State' => $this->states[$row[self::COL_CAMP_STATE]],
      'Institution_Collection_Camp_Intent.District_City' => $this->singleLine($row[self::COL_CAMP_CITY]),
      'Institution_Collection_Camp_Intent.Country' => $row[self::COL_COUNTRY],
      // From the sheet's "Register as" column (Goonj, #234).
      'Institution_Collection_Camp_Intent.You_wish_to_register_as:name' => $this->registerAs[$row[self::COL_REGISTER_AS]] ?? NULL,
      'Institution_Collection_Camp_Logistics.Camp_to_be_attended_by' => $attendedById,
      // Fixed value Goonj asked for (coloredcow-admin/goonj-crm#234).
      'Institution_Collection_Camp_Logistics.Self_Managed_by_Institution' => $attendedById ? FALSE : NULL,
      'Institution_Collection_Camp_Logistics.Support_person_details' => $this->singleLine($row[self::COL_SUPPORT_PERSON]),
      'Institution_Collection_Camp_Logistics.Pickup_vehicle_info' => $this->singleLine($row[self::COL_PICKUP_INFO]),
      'Camp_Outcome.Rate_the_camp:name' => $this->rates[$row[self::COL_RATE]],
      'Camp_Outcome.Product_Sale_Amount' => $this->parseNumber($row[self::COL_PRODUCT_SALE]),
      'Camp_Outcome.Any_unique_efforts_made_by_Volunteer' => $row[self::COL_UNIQUE_EFFORTS],
      'Camp_Outcome.Any_Difficulty_challenge_faced' => $row[self::COL_DIFFICULTY],
      'Camp_Outcome.Any_other_remarks_and_suggestions_for_Urban_Relation_Team' => $row[self::COL_REMARKS],
      'Core_Contribution_Details.Total_cash_cheque_monetary_contributions' => $this->parseNumber($row[self::COL_CASH]),

      // Marked as already emailed, so the scheduled jobs skip these camps.
      // "Institution Collection Camp" job (logistics email).
      'Institution_Collection_Camp_Logistics.Email_Sent' => TRUE,
      // "Institution Camp Feedback" job: it reads this field even though the
      // field set belongs to the individual camps form.
      'Logistics_Coordination.Feedback_Email_Sent' => TRUE,
      // "Volunteer Feedback Reminder" job, as on the individual camps.
      'Logistics_Coordination.Feedback_Reminder_Sent' => TRUE,
      // Outcome reminder fields, as on the individual camps.
      'Camp_Outcome.Last_Reminder_Sent' => $endDate,
      'Camp_Outcome.Final_Reminder_Sent' => $endDate,
      'Camp_Outcome.Five_Day_Email_Sent' => TRUE,
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
   * Second save: the sheet values a hook overwrote.
   *
   * While the camp is created, the setOfficeDetails hook replaces the office
   * with the state's office, empties the camp type (IHC/PCC) and puts a random
   * current coordinator in "Coordinating POC". This save puts the sheet values
   * back. The Coordinating POC is the "Camp attended by" person, as Goonj
   * asked (coloredcow-admin/goonj-crm#234).
   */
  private function setSheetValuesAfterHooks(int $campId, array $row): void {
    EckEntity::update('Collection_Camp', FALSE)
      ->addWhere('id', '=', $campId)
      ->addValue('Institution_collection_camp_Review.Goonj_Office', $this->offices[$row[self::COL_OFFICE]])
      ->addValue('Institution_collection_camp_Review.Is_the_camp_IHC_PCC_:name', $this->campTypes[$row[self::COL_CAMP_TYPE]])
      ->addValue('Institution_collection_camp_Review.Coordinating_POC', $this->staff[$this->staffKey($row)]['id'] ?? NULL)
      ->execute();
  }

  /**
   * Creates the vehicle dispatch for the camp.
   *
   * "To which PU/Center material is being sent" is not set: setting it while
   * creating the dispatch emails the Material Management Team.
   */
  private function createDispatch(int $campId, array $row): int {
    // The dispatch date and the acknowledgement (contribution) date are the
    // camp's End Date, as Goonj asked (coloredcow-admin/goonj-crm#234). The
    // number of vehicles is not imported (Goonj: leave it out).
    $endDate = $this->parseDate($row[self::COL_END]);
    $values = [
      'subtype:name' => 'Vehicle_Dispatch',
      // Title the live institution dispatch forms give.
      'title' => 'Institution Collection Camp Vehicle Dispatch',
      'Camp_Vehicle_Dispatch.Institution_Collection_Camp' => $campId,
      'Camp_Vehicle_Dispatch.Date_Time_of_Dispatch' => $endDate,
      'Acknowledgement_For_Logistics.Contribution_Date' => $endDate,
      'Camp_Vehicle_Dispatch.Material_weight_In_KGs_' => $row[self::COL_WEIGHT] !== '' ? (string) $this->parseNumber($row[self::COL_WEIGHT]) : NULL,
      'Camp_Vehicle_Dispatch.Vehicle_Category:name' => $row[self::COL_VEHICLE_CATEGORY] !== '' ? $this->vehicleCategories[$row[self::COL_VEHICLE_CATEGORY]] : NULL,
      // Institution section, from the sheet as Goonj asked (#234): institution
      // name and address, POC email and mobile.
      'Camp_Institution_Data.Name_of_the_institution' => $this->singleLine($row[self::COL_INSTITUTION_NAME]),
      'Camp_Institution_Data.Address' => $this->singleLine($row[self::COL_ADDRESS]),
      'Camp_Institution_Data.Email' => $this->singleLine($row[self::COL_EMAIL]),
      'Camp_Institution_Data.Contact_Number' => $this->singleLine($row[self::COL_MOBILE]),
      // Fixed values Goonj asked for (coloredcow-admin/goonj-crm#234), same
      // text as the earlier imports.
      'Camp_Vehicle_Dispatch.Number_of_Bags_loaded_in_vehicle' => 'imported data',
      'Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office' => 'imported data',
      'Camp_Vehicle_Dispatch.How_much_vehicle_is_filled:name' => 'Full',
      // Marked as already emailed.
      'Acknowledgement_For_Logistics.Ack_Email_Sent' => TRUE,
    ];

    $create = EckEntity::create('Collection_Source_Vehicle_Dispatch', FALSE);
    foreach ($values as $field => $value) {
      if ($value !== NULL && $value !== '') {
        $create->addValue($field, $value);
      }
    }
    return (int) $create->execute()->single()['id'];
  }

  /**
   * Creates the "Organize Institution Collection Camp" activities.
   *
   * Same activities the live flow creates when a camp is authorized: one on
   * the institution POC and one on the organisation. The camp code in the
   * subject is the link to the camp.
   */
  private function createOrganizeActivities(array $row, string $code, ?int $pocId, int $organisationId): array {
    $ids = [];
    foreach (array_unique(array_filter([$pocId, $organisationId])) as $contactId) {
      $ids[] = (int) Activity::create(FALSE)
        ->addValue('subject', $code)
        ->addValue('activity_type_id:name', 'Organize Institution Collection Camp')
        // The live flow asks for "Authorized", which is not an activity
        // status, so every live Organize activity is saved as Completed.
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_date_time', $this->parseDate($row[self::COL_CREATED]))
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->execute()->single()['id'];
    }
    return $ids;
  }

  /**
   * Key of a row's organisation in the organisation lookup.
   */
  private function organisationKey(int $line, array $row): string {
    return isset($this->orgForLines[$line]) ? "line:$line" : $row[self::COL_INSTITUTION_NAME];
  }

  /**
   * Finds the organisation by its name (the older one when two have it).
   */
  private function findOrganisation(string $name): array {
    $organisations = Contact::get(FALSE)
      ->addSelect('id', 'display_name')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('organization_name', '=', $name)
      ->addWhere('is_deleted', '=', FALSE)
      ->addOrderBy('id')
      ->execute();
    if (!$organisations->count()) {
      return ['id' => NULL, 'name' => '', 'note' => "no organisation named '" . $this->singleLine($name) . "'"];
    }
    $first = $organisations->first();
    $note = $organisations->count() > 1
      ? $organisations->count() . ' organisations have this name: ' . implode(',', $organisations->column('id')) . '; the older one is used'
      : 'found by name';
    return ['id' => (int) $first['id'], 'name' => $first['display_name'], 'note' => $note];
  }

  /**
   * Checks an organisation given with ORG_FOR_LINES.
   */
  private function findOrganisationById(int $id): array {
    $organisation = Contact::get(FALSE)
      ->addSelect('id', 'display_name')
      ->addWhere('id', '=', $id)
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()->first();
    if (!$organisation) {
      return ['id' => NULL, 'name' => '', 'note' => "ORG_FOR_LINES contact $id is not an organisation"];
    }
    return ['id' => (int) $organisation['id'], 'name' => $organisation['display_name'], 'note' => 'given with ORG_FOR_LINES'];
  }

  /**
   * Matches the institution POC to an existing contact.
   *
   * Among the contacts that have the sheet email (any of their emails):
   * 1. first name + email,
   * 2. else email + mobile,
   * 3. else email only.
   * When the sheet has no email, or an email that no contact has and that is
   * not a valid address (the contact import saved those contacts with a
   * corrected email or none): first name + mobile.
   * When a step finds several contacts, the first one (lowest contact id) is
   * used.
   */
  private function matchPoc(array $row): array {
    $firstName = $this->normaliseName($row[self::COL_FIRST_NAME]);
    $email = strtolower($row[self::COL_EMAIL]);
    $mobile = $this->lastTenDigits($row[self::COL_MOBILE]);

    if ($firstName === '' && $this->normaliseName($row[self::COL_LAST_NAME]) === '' && $email === '' && $mobile === '') {
      return ['id' => NULL, 'method' => 'NO_POC_IN_SHEET', 'note' => 'No POC details in the sheet; imported without POC'];
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
        return $this->matchByFirstNameAndMobile($firstName, $mobile, "Email in the sheet is not a valid address ($email)");
      }
      return ['id' => NULL, 'method' => 'NOT_FOUND', 'note' => 'No contact has this email'];
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
   * Matches a POC without a usable email by first name + mobile.
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
   * Finds the staff contact for a "Camp attended by" email.
   *
   * Several contacts can share a staff email. The one with a CRM login wins;
   * without one, the one with an active "Employee of" relationship; without
   * one, the one whose first name is the first word of the sheet's "Camp
   * attended by (Name)".
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
   * Key of a row's "Camp attended by" in the staff lookup.
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
   * Returns a field's options keyed by the sheet text, as => option name.
   *
   * The sheet text is matched to the option's name first, then to its label:
   * labels can be changed in the admin UI and differ per site.
   */
  private function optionsByLabel(string $group, string $field): array {
    $optionGroupId = CustomField::get(FALSE)
      ->addSelect('option_group_id')
      ->addWhere('custom_group_id:name', '=', $group)
      ->addWhere('name', '=', $field)
      ->execute()->single()['option_group_id'];
    $all = OptionValue::get(FALSE)
      ->addSelect('name', 'label')
      ->addWhere('option_group_id', '=', $optionGroupId)
      ->addWhere('is_active', '=', TRUE)
      ->execute();
    $options = [];
    foreach ($all as $option) {
      $options[$option['label']] = $option['name'];
    }
    // Names win over labels.
    foreach ($all as $option) {
      $options[$option['name']] = $option['name'];
    }
    return $options;
  }

  /**
   * Returns the id of the camp this sheet row already created, if any.
   */
  private function findExistingCamp(array $row, string $code): ?int {
    $get = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id')
      ->addWhere('subtype:name', '=', 'Institution_Collection_Camp')
      ->addWhere('title', '=', $code)
      ->addWhere('Institution_Collection_Camp_Intent.Collections_will_start_on_Date_', '=', $this->parseDate($row[self::COL_START]))
      ->addWhere('Institution_Collection_Camp_Intent.Collection_Camp_Address', '=', $this->singleLine($row[self::COL_LOCATION]));
    // A row without a camp code (title "Collection Camp") can share the title,
    // start date and place with another camp. The sheet's Created Date tells
    // them apart.
    if (strpos($code, '/') === FALSE) {
      $get->addWhere('created_date', '=', $this->parseDate($row[self::COL_CREATED]));
    }
    $camp = $get->execute()->first();
    return $camp ? (int) $camp['id'] : NULL;
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

(new GoonjLegacyInstitutionCampImport())->run();
