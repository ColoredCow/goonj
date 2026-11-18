<?php

/**
 * @file
 * Imports legacy individual collection camps from the old system.
 *
 * For every sheet row the script creates:
 * - one Collection Camp (camp details, logistics and outcome details),
 * - one Vehicle Dispatch linked to the camp (when the row has dispatch data),
 * - one "Organize Collection Camp" activity on the organiser (when the
 *   organiser is found).
 *
 * The organiser contacts must already exist (they are imported first with the
 * CiviCRM contact import). The "Contact ID" column of the sheet belongs to the
 * old system and is not used.
 *
 * Goonj's answers on coloredcow-admin/goonj-crm#234:
 * - camps with no organiser details are imported without an organiser;
 * - dispatch date and acknowledgement (contribution) date = camp End Date;
 * - "Number of vehicles" is not imported;
 * - Coordinating Urban POC = the "Camp attended by" person;
 * - a camp code used twice gets an extra 0 at the start of the number on
 *   the second camp (2021/MHR/Coll/066 -> 2021/MHR/Coll/0066);
 * - an organiser whose wrong email was removed is matched by first name +
 *   mobile;
 * - fixed values on every camp: "You wish to register as" = An individual,
 *   and "Self Managed By Camp Organiser" = No when "Camp attended by" is
 *   filled;
 * - fixed values on every dispatch: "Number of Bags loaded in vehicle" and
 *   "No. of bags received at PU/Office" = "imported data" (as in the earlier
 *   imports), "How much vehicle is filled" = Full.
 * Other values that are not in the sheet are not filled in.
 *
 * No email may go out for these old camps:
 * - every "email sent" / "reminder sent" field that a scheduled job checks is
 *   saved on the camp and the dispatch, so the jobs treat these camps as
 *   already emailed;
 * - the camp is created already Authorized in one save, so the hooks that act
 *   when an existing camp becomes Authorized (authorization email, new camp
 *   code, QR, Organize activity) do not run;
 * - the organiser is set in a second save: when the initiator is set while
 *   creating the camp, a hook creates an Induction activity for the organiser,
 *   and the induction jobs then email them;
 * - the dispatch is created without "To which PU/Center": setting it while
 *   creating the dispatch emails the Material Management Team.
 *
 * Each row is saved in one transaction, so a row is either fully saved or not
 * saved at all.
 *
 * Every row that is not imported (organiser not found, a value that cannot be
 * matched, an error while saving) is written to an issues sheet: the sheet's
 * own columns plus "Import status" and "Reason". After fixing them, the issues
 * sheet can be given back to this script as CSV.
 *
 * Runs as a dry run by default: nothing is saved, and every row is listed in a
 * CSV report with the organiser match and what would be created. Rows already
 * imported are skipped, so the script can be run again safely.
 *
 * Usage:
 *   Dry run:
 *     CSV=/path/to/sheet.csv cv scr wp-content/civi-extensions/goonjcustom/cli/import-legacy-individual-collection-camps.php
 *   Save only the first 2 camps (to show the client first):
 *     LIMIT=2 APPLY=1 CSV=... cv scr ...
 *   Save all (the 2 above are skipped as already imported):
 *     APPLY=1 CSV=... cv scr ...
 *   Only some sheet lines (line numbers as in the spreadsheet, data starts on 3):
 *     ROWS=3-12 CSV=... cv scr ...
 *   Rows with no organiser details at all (name, email and mobile blank) are
 *   imported without an organiser, as Goonj asked in
 *   coloredcow-admin/goonj-crm#234. Rows whose organiser has details but is
 *   not found go to the issues sheet; to import those without an organiser
 *   too:
 *     ALLOW_NO_INITIATOR=1 APPLY=1 CSV=... cv scr ...
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
 * Imports one legacy sheet of individual collection camps.
 */
class GoonjLegacyIndividualCampImport {

  /**
   * Sheet columns, in order, as on the second header row.
   */
  const COLUMNS = [
    'First Name',
    'Last Name',
    'Mobile',
    'Email',
    'State',
    'City',
    'Contact ID',
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
  ];

  /**
   * Columns the issues sheet adds after the sheet columns.
   */
  const ISSUE_COLUMNS = ['Import status', 'Reason'];

  const COL_FIRST_NAME = 0;
  const COL_LAST_NAME = 1;
  const COL_MOBILE = 2;
  const COL_EMAIL = 3;
  const COL_CODE = 8;
  const COL_OFFICE = 9;
  const COL_TYPE = 10;
  const COL_CREATED = 11;
  const COL_START = 12;
  const COL_END = 13;
  const COL_LOCATION = 14;
  const COL_CAMP_STATUS = 15;
  const COL_CAMP_STATE = 16;
  const COL_CAMP_CITY = 17;
  const COL_COUNTRY = 18;
  const COL_CAMPAIGN_ID = 20;
  const COL_CAMP_TYPE = 21;
  const COL_WEIGHT = 22;
  const COL_VEHICLES = 23;
  const COL_VEHICLE_CATEGORY = 24;
  const COL_ATTENDED_BY_EMAIL = 25;
  const COL_ATTENDED_BY_NAME = 26;
  const COL_SUPPORT_PERSON = 27;
  const COL_PICKUP_INFO = 28;
  const COL_CASH = 29;
  const COL_PRODUCT_SALE = 30;
  const COL_UNIQUE_EFFORTS = 31;
  const COL_DIFFICULTY = 32;
  const COL_RATE = 33;
  const COL_REMARKS = 34;

  /**
   * First data line of the sheet (line 1 and 2 are headers).
   */
  const FIRST_DATA_LINE = 3;

  private string $csvPath;
  private bool $apply;
  private bool $allowNoInitiator;
  private ?int $limit = NULL;
  private ?int $fromLine = NULL;
  private ?int $toLine = NULL;
  private string $reportPath;
  private string $issuesPath;

  /**
   * The sheet's first header row (column groups), copied to the issues sheet.
   */
  private array $groupHeader = [];

  /**
   * Values of a row that cannot be imported, keyed by sheet line.
   */
  private array $rowErrors = [];

  /**
   * Sheet codes changed to make them unique, keyed by sheet line.
   */
  private array $changedCodes = [];

  /**
   * Lookups resolved before any row is saved.
   */
  private array $offices = [];
  private array $staff = [];
  private array $states = [];
  private array $campaigns = [];
  private array $vehicleCategories = [];
  private array $rates = [];
  private array $campTypes = [];
  private array $campStatuses = [];

  /**
   * Reads the settings from the environment.
   */
  public function __construct() {
    $this->csvPath = (string) getenv('CSV');
    $this->apply = getenv('APPLY') === '1';
    $this->allowNoInitiator = getenv('ALLOW_NO_INITIATOR') === '1';

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
      'sheet_line', 'camp_code', 'organiser', 'organiser_email', 'status',
      'organiser_contact_id', 'match', 'match_note', 'camp_id', 'dispatch_id',
      'activity_id', 'note',
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
      if (isset($this->changedCodes[$line])) {
        $result['note'] = trim("Code {$this->changedCodes[$line]} is used twice in the sheet, saved as {$row[self::COL_CODE]}. " . ($result['note'] ?? ''));
      }
      $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
      if (in_array($result['status'], ['CREATED', 'DRY_RUN_OK'], TRUE)) {
        $done++;
      }

      fputcsv($report, [
        $line,
        $row[self::COL_CODE],
        trim($row[self::COL_FIRST_NAME] . ' ' . $row[self::COL_LAST_NAME]),
        $row[self::COL_EMAIL],
        $result['status'],
        $result['contact_id'] ?? '',
        $result['match'] ?? '',
        $result['match_note'] ?? '',
        $result['camp_id'] ?? '',
        $result['dispatch_id'] ?? '',
        $result['activity_id'] ?? '',
        $result['note'] ?? '',
      ], ',', '"', '\\');

      if ($result['status'] === 'CREATED') {
        echo "Line $line: {$row[self::COL_CODE]} => camp {$result['camp_id']}\n";
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
   * extra 0 at the start of the number: 2021/MHR/Coll/066 becomes
   * 2021/MHR/Coll/0066 (Goonj's answer on coloredcow-admin/goonj-crm#234).
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
        $parts = explode('/', $code);
        $number = array_pop($parts);
        do {
          $number = '0' . $number;
          $final = implode('/', array_merge($parts, [$number]));
        } while (isset($used[$final]) || isset($sheetCodes[$final]));
        $rows[$line][self::COL_CODE] = $final;
        $this->changedCodes[$line] = $code;
      }
      $used[$final] = TRUE;
    }
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
    $this->campTypes = $this->optionsByLabel('Collection_Camp_Intent_Details', 'Camp_Type');
    $this->campStatuses = $this->optionsByLabel('Collection_Camp_Intent_Details', 'Camp_Status');

    foreach ($rows as $line => $row) {
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
    echo "\n";

    if ($this->rowErrors) {
      echo count($this->rowErrors) . " row(s) have values that cannot be imported; they go to the issues sheet.\n\n";
    }
  }

  /**
   * Processes one sheet row.
   */
  private function processRow(int $line, array $row): array {
    if (!empty($this->rowErrors[$line])) {
      return ['status' => 'SKIPPED_BAD_DATA', 'note' => implode('; ', array_unique($this->rowErrors[$line]))];
    }

    $match = $this->matchOrganiser($row);
    $result = [
      'contact_id' => $match['id'],
      'match' => $match['method'],
      'match_note' => $match['note'],
    ];

    $existing = $this->findExistingCamp($row);
    if ($existing) {
      return $result + ['status' => 'SKIPPED_ALREADY_IMPORTED', 'camp_id' => $existing];
    }

    // A camp code must not be given to a second camp in the CRM.
    $code = $row[self::COL_CODE];
    if (strpos($code, '/') !== FALSE) {
      $sameCode = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $code)
        ->execute()->first();
      if ($sameCode) {
        return $result + ['status' => 'SKIPPED_CODE_IN_USE', 'note' => "Camp code $code is already used by camp {$sameCode['id']} in the CRM"];
      }
    }

    if (!$match['id'] && $match['method'] !== 'NO_ORGANISER_IN_SHEET' && !$this->allowNoInitiator) {
      return $result + ['status' => 'SKIPPED_' . $match['method'], 'note' => 'Organiser not matched; set ALLOW_NO_INITIATOR=1 to import without initiator'];
    }

    $hasDispatch = $row[self::COL_WEIGHT] !== '' || $row[self::COL_VEHICLE_CATEGORY] !== '';

    if (!$this->apply) {
      return $result + [
        'status' => 'DRY_RUN_OK',
        'note' => ($hasDispatch ? 'camp + dispatch' : 'camp only (no dispatch data)') . ($match['id'] ? ' + activity' : ' (no initiator)'),
      ];
    }

    $transaction = new \CRM_Core_Transaction();
    try {
      $campId = $this->createCamp($row);
      $this->setSheetValuesAfterHooks($campId, $row, $match['id']);
      $dispatchId = $hasDispatch ? $this->createDispatch($campId, $row) : NULL;
      $activityId = $match['id'] ? $this->createOrganizeActivity($row, $match['id']) : NULL;
      $transaction->commit();

      return $result + [
        'status' => 'CREATED',
        'camp_id' => $campId,
        'dispatch_id' => $dispatchId,
        'activity_id' => $activityId,
      ];
    }
    catch (\Throwable $e) {
      $transaction->rollback()->commit();
      return $result + ['status' => 'ERROR', 'note' => $e->getMessage()];
    }
  }

  /**
   * Creates the camp, already Authorized and Completed, in one save.
   *
   * Created in one save so the hooks that act when an existing camp becomes
   * Authorized (authorization email, new camp code, QR, Organize activity) do
   * not run. The organiser is not set here; see setSheetValuesAfterHooks().
   */
  private function createCamp(array $row): int {
    $endDate = $this->parseDate($row[self::COL_END]);
    $attendedById = $this->staff[$this->staffKey($row)]['id'] ?? NULL;

    $values = [
      'subtype:name' => 'Collection_Camp',
      'title' => $row[self::COL_CODE],
      'created_date' => $this->parseDate($row[self::COL_CREATED]),
      'Collection_Camp_Core_Details.Status:name' => 'Authorized',
      'Collection_Camp_Intent_Details.Camp_Status:name' => $this->campStatuses[$row[self::COL_CAMP_STATUS]],
      'Collection_Camp_Intent_Details.Location_Area_of_camp' => $this->singleLine($row[self::COL_LOCATION]),
      'Collection_Camp_Intent_Details.Start_Date' => $this->parseDate($row[self::COL_START]),
      'Collection_Camp_Intent_Details.End_Date' => $endDate,
      'Collection_Camp_Intent_Details.State' => $this->states[$row[self::COL_CAMP_STATE]],
      'Collection_Camp_Intent_Details.City' => $this->singleLine($row[self::COL_CAMP_CITY]),
      'Collection_Camp_Intent_Details.Campaign' => (int) $this->campaigns[$row[self::COL_CAMPAIGN_ID]]['id'],
      'Logistics_Coordination.Camp_to_be_attended_by' => $attendedById,
      // Fixed values Goonj asked for (coloredcow-admin/goonj-crm#234).
      'Collection_Camp_Intent_Details.You_wish_to_register_as:name' => 'An_individual',
      'Logistics_Coordination.Self_Managed_By_Camp_Organiser' => $attendedById ? FALSE : NULL,
      'Logistics_Coordination.Support_person_details' => $row[self::COL_SUPPORT_PERSON],
      'Logistics_Coordination.Pickup_vehicle_info' => $row[self::COL_PICKUP_INFO],
      'Camp_Outcome.Rate_the_camp:name' => $this->rates[$row[self::COL_RATE]],
      'Camp_Outcome.Product_Sale_Amount' => $this->parseNumber($row[self::COL_PRODUCT_SALE]),
      'Camp_Outcome.Any_unique_efforts_made_by_Volunteer' => $row[self::COL_UNIQUE_EFFORTS],
      'Camp_Outcome.Any_Difficulty_challenge_faced' => $row[self::COL_DIFFICULTY],
      'Camp_Outcome.Any_other_remarks_and_suggestions_for_Urban_Relation_Team' => $row[self::COL_REMARKS],
      'Core_Contribution_Details.Total_cash_cheque_monetary_contributions' => $this->parseNumber($row[self::COL_CASH]),

      // Marked as already emailed, so the scheduled jobs skip these camps.
      // "Collection Camp" job (logistics email).
      'Logistics_Coordination.Email_Sent' => TRUE,
      // "Volunteer Feedback Collection Camp" job.
      'Logistics_Coordination.Feedback_Email_Sent' => TRUE,
      // "Volunteer Feedback Reminder" job.
      'Logistics_Coordination.Feedback_Reminder_Sent' => TRUE,
      // "Collection Camp Outcome Reminder" job.
      'Camp_Outcome.Last_Reminder_Sent' => $endDate,
      'Camp_Outcome.Final_Reminder_Sent' => $endDate,
      // "Send camp outcome ack email after 5 days" job.
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
   * Second save: the organiser, and the sheet values a hook overwrote.
   *
   * While the camp is created, the setOfficeDetails hook replaces the office
   * with the state's office, empties the camp type and puts a random current
   * coordinator in "Coordinating Urban POC". This save puts the sheet values
   * back. The Coordinating Urban POC is the "Camp attended by" person, as
   * Goonj asked (coloredcow-admin/goonj-crm#234).
   *
   * The organiser is set here and not while creating the camp: on create, the
   * linkInductionWithCollectionCamp hook creates an Induction activity for the
   * organiser, and the induction jobs then email them. On this second save the
   * hook does nothing.
   */
  private function setSheetValuesAfterHooks(int $campId, array $row, ?int $initiatorId): void {
    $update = EckEntity::update('Collection_Camp', FALSE)
      ->addWhere('id', '=', $campId)
      ->addValue('Collection_Camp_Intent_Details.Goonj_Office', $this->offices[$row[self::COL_OFFICE]])
      ->addValue('Collection_Camp_Intent_Details.Camp_Type:name', $this->campTypes[$row[self::COL_CAMP_TYPE]])
      ->addValue('Collection_Camp_Intent_Details.Coordinating_Urban_POC', $this->staff[$this->staffKey($row)]['id'] ?? NULL);
    if ($initiatorId) {
      $update->addValue('Collection_Camp_Core_Details.Contact_Id', $initiatorId);
    }
    $update->execute();
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
      'title' => 'Camp Vehicle Dispatch',
      'Camp_Vehicle_Dispatch.Collection_Camp' => $campId,
      'Camp_Vehicle_Dispatch.Date_Time_of_Dispatch' => $endDate,
      'Acknowledgement_For_Logistics.Contribution_Date' => $endDate,
      'Camp_Vehicle_Dispatch.Material_weight_In_KGs_' => $row[self::COL_WEIGHT] !== '' ? (string) $this->parseNumber($row[self::COL_WEIGHT]) : NULL,
      'Camp_Vehicle_Dispatch.Vehicle_Category:name' => $row[self::COL_VEHICLE_CATEGORY] !== '' ? $this->vehicleCategories[$row[self::COL_VEHICLE_CATEGORY]] : NULL,
      'Camp_Vehicle_Dispatch.Goonj_Office_Name' => $row[self::COL_OFFICE],
      'Camp_Vehicle_Dispatch.Collection_Camp_Address' => $this->singleLine($row[self::COL_LOCATION]),
      // Fixed values Goonj asked for (coloredcow-admin/goonj-crm#234), same
      // text as the earlier imports.
      'Camp_Vehicle_Dispatch.Number_of_Bags_loaded_in_vehicle' => 'imported data',
      'Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office' => 'imported data',
      'Camp_Vehicle_Dispatch.How_much_vehicle_is_filled:name' => 'Full',
      // Marked as already emailed: acknowledgement email to the organiser.
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
   * Creates the "Organize Collection Camp" activity on the organiser.
   *
   * Same activity the live flow creates when a camp is authorized. The camp
   * code in the subject is the link to the camp (the live flow also writes a
   * Collection_Camp_Data.Collection_Camp_ID field, but that field does not
   * exist, so nothing is saved there).
   */
  private function createOrganizeActivity(array $row, int $contactId): int {
    return (int) Activity::create(FALSE)
      ->addValue('subject', $row[self::COL_CODE])
      ->addValue('activity_type_id:name', 'Organize Collection Camp')
      // The live flow asks for "Authorized", which is not an activity status,
      // so every live Organize activity is saved as Completed. Same here.
      ->addValue('status_id:name', 'Completed')
      ->addValue('activity_date_time', $this->parseDate($row[self::COL_CREATED]))
      ->addValue('source_contact_id', $contactId)
      ->addValue('target_contact_id', $contactId)
      ->execute()->single()['id'];
  }

  /**
   * Matches the organiser to an existing contact.
   *
   * Among the contacts that have the sheet email (any of their emails):
   * 1. first name + email,
   * 2. else email + mobile,
   * 3. else email only.
   * When the sheet has no email for the organiser, or an email that no
   * contact has and that is not a valid address (e.g. "name#gmail.com"; the
   * contact import saved those contacts with a corrected email or none):
   * first name + mobile.
   * When a step finds several contacts, the first one (lowest contact id) is
   * used.
   */
  private function matchOrganiser(array $row): array {
    $firstName = $this->normaliseName($row[self::COL_FIRST_NAME]);
    $email = strtolower($row[self::COL_EMAIL]);
    $mobile = $this->lastTenDigits($row[self::COL_MOBILE]);

    // Goonj asked to import camps without organiser details as camps without
    // an organiser (coloredcow-admin/goonj-crm#234).
    if ($firstName === '' && $this->normaliseName($row[self::COL_LAST_NAME]) === '' && $email === '' && $mobile === '') {
      return ['id' => NULL, 'method' => 'NO_ORGANISER_IN_SHEET', 'note' => 'No organiser details in the sheet; imported without organiser'];
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
      // An email that is not a valid address (e.g. "name#gmail.com") was
      // saved on the contact corrected, or not at all, by the contact import.
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
   * Finds the staff contact for a "Camp attended by" email.
   *
   * Several contacts can share a staff email (old copies, donation boxes,
   * test contacts, an organiser imported with a staff's email). The one with
   * a CRM login wins; without one, the one with an active "Employee of"
   * relationship; without one, the one whose first name is the first word of
   * the sheet's "Camp attended by (Name)".
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
   * labels can be changed in the admin UI and differ per site (on prod the
   * PCC camp type is labelled "PC").
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
  private function findExistingCamp(array $row): ?int {
    $get = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id')
      ->addWhere('subtype:name', '=', 'Collection_Camp')
      ->addWhere('title', '=', $row[self::COL_CODE])
      ->addWhere('Collection_Camp_Intent_Details.Start_Date', '=', $this->parseDate($row[self::COL_START]))
      ->addWhere('Collection_Camp_Intent_Details.Location_Area_of_camp', '=', $this->singleLine($row[self::COL_LOCATION]));
    // A row without a camp code (title "Collection Camp") can share the title,
    // start date and place with another camp: two organisers held camps at the
    // same society on the same day. The sheet's Created Date tells them apart.
    if (strpos($row[self::COL_CODE], '/') === FALSE) {
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

(new GoonjLegacyIndividualCampImport())->run();
