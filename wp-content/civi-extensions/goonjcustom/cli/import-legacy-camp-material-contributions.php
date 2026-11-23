<?php

/**
 * @file
 * Imports legacy camp material contributions from the old system.
 *
 * For every sheet row the script creates one "Material Contribution" activity
 * (Completed) for the contributor, linked to the camp the contribution was
 * made at, like a contribution entered at a live camp:
 * - source contact = the contributor;
 * - "Collection Camp" (individual camps) or "Institution Collection Camp"
 *   (institution camps) = the camp;
 * - "Contribution Date" and the activity date = the sheet's contribution
 *   date; "Goonj Office" = the sheet's office.
 * Contacts are never created or changed.
 *
 * The contributor is the contact with the sheet's "Contact ID" as external
 * identifier (the old system's id, saved on every migrated contact). When no
 * such contact exists, the contributor is matched by email (first name +
 * email -> email + mobile -> email), then by first name + mobile.
 *
 * The camp is found by the sheet's code. The camp imports gave a code used
 * twice an extra 0 at the start of the number, so the camps with the code or
 * with one or two extra 0s are candidates: the one whose dates include the
 * contribution date is used; when none does, the camp with the exact code.
 *
 * No email may go out for these old contributions:
 * - the "Material Contribution Receipt" Scheduled Reminder only picks
 *   contributions whose activity date is within the last day (CiviCRM's
 *   reminder rule); the activity date is the old contribution date, and the
 *   script refuses a date in the last 7 days;
 * - the receipt PDF and the contributor counts are made by the live
 *   contribution forms, not by API saves.
 *
 * A row that is an exact copy of an earlier row (same contributor, camp and
 * date) is not imported twice. Every row that is not imported (contributor or
 * camp not found, a bad date, an error while saving) is written to an issues
 * sheet: the sheet's own columns plus "Import status" and "Reason".
 *
 * Runs as a dry run by default: nothing is saved, and every row is listed in a
 * CSV report with the matches and what would be created. Contributions
 * already imported are skipped, so the script can be run again safely.
 *
 * Usage:
 *   Dry run:
 *     CSV=/path/to/sheet.csv cv scr wp-content/civi-extensions/goonjcustom/cli/import-legacy-camp-material-contributions.php
 *   Save only the first 2 contributions (to show the client first):
 *     LIMIT=2 APPLY=1 CSV=... cv scr ...
 *   Save all (contributions already saved are skipped):
 *     APPLY=1 CSV=... cv scr ...
 *   Only some sheet lines (line numbers as in the spreadsheet, data starts on 3):
 *     ROWS=3-12 CSV=... cv scr ...
 *   Rows whose contribution date is not a date (00-01-1900): use the sheet's
 *   Create Date instead:
 *     DATE_FALLBACK=create CSV=... cv scr ...
 *   Report (every row) and issues sheet (rows not imported) locations,
 *   default: next to the CSV:
 *     REPORT=/path/to/report.csv ISSUES=/path/to/issues.csv
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Phone;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 * Imports one legacy sheet of camp material contributions.
 */
class GoonjLegacyCampMaterialContributionImport {

  /**
   * Sheet columns, in order, as on the second header row.
   */
  const COLUMNS = [
    'Contact ID',
    'Create Date (YYYY-MM-DD HH;MM;SS)',
    'First Name',
    'Last Name',
    'Email Id',
    'Phone',
    'State',
    'City',
    'Event Type',
    'Code/ Title',
    'Contribution date (YYYY-MM-DD)',
    'Goonj Office',
  ];

  /**
   * Columns added to the issues sheet.
   */
  const ISSUE_COLUMNS = ['Import status', 'Reason'];

  const COL_CONTACT_ID = 0;
  const COL_CREATED = 1;
  const COL_FIRST_NAME = 2;
  const COL_EMAIL = 4;
  const COL_PHONE = 5;
  const COL_EVENT_TYPE = 8;
  const COL_CODE = 9;
  const COL_DATE = 10;
  const COL_OFFICE = 11;

  /**
   * The first sheet line with data (two header rows).
   */
  const FIRST_DATA_LINE = 3;

  /**
   * Contributions dated in the last few days are refused (receipt reminder).
   */
  const MIN_AGE_DAYS = 7;

  /**
   * Sheet path.
   */
  private string $csvPath;

  /**
   * Save (TRUE) or dry run.
   */
  private bool $apply;

  /**
   * Use the Create Date when the contribution date is not a date.
   */
  private bool $createDateFallback;

  /**
   * Stop after this many contributions.
   */
  private ?int $limit = NULL;

  /**
   * First sheet line to process (ROWS).
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
   * The sheet's first header row (column groups), copied to the issues sheet.
   */
  private array $groupHeader = [];

  /**
   * Goonj offices by name.
   */
  private array $offices = [];

  /**
   * Camp candidates by sheet code.
   */
  private array $camps = [];

  /**
   * Contacts by old-system id (external identifier).
   */
  private array $byExternalId = [];

  /**
   * First line of each contributor + camp + date already seen in this run.
   */
  private array $seen = [];

  /**
   * Reads the settings from the environment.
   */
  public function __construct() {
    $this->csvPath = (string) getenv('CSV');
    $this->apply = getenv('APPLY') === '1';
    $this->createDateFallback = getenv('DATE_FALLBACK') === 'create';

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
      echo "LIMIT: stop after {$this->limit} contribution(s)\n";
    }

    $rows = $this->readSheet();
    echo 'Rows selected: ' . count($rows) . "\n\n";

    $this->resolveLookups($rows);

    $report = fopen($this->reportPath, 'w');
    $issues = fopen($this->issuesPath, 'w');
    if (!$report || !$issues) {
      $this->fail('Cannot write the report or the issues sheet next to the CSV.');
    }
    fputcsv($report, [
      'sheet_line', 'contact_id_in_sheet', 'email', 'camp_code', 'contribution_date',
      'status', 'contributor_contact_id', 'match', 'match_note', 'camp_id',
      'camp_title', 'activity_id', 'note',
    ], ',', '"', '\\');
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
        $row[self::COL_CONTACT_ID],
        $row[self::COL_EMAIL],
        $row[self::COL_CODE],
        $result['date'] ?? '',
        $result['status'],
        $result['contact_id'] ?? '',
        $result['match'] ?? '',
        $result['match_note'] ?? '',
        $result['camp_id'] ?? '',
        $result['camp_title'] ?? '',
        $result['activity_id'] ?? '',
        $result['note'] ?? '',
      ], ',', '"', '\\');

      if ($result['status'] === 'CREATED' && ($done <= 20 || $done % 500 === 0)) {
        echo "Line $line: {$row[self::COL_CODE]} => activity {$result['activity_id']} (created so far: $done)\n";
      }
      if (!in_array($result['status'], ['CREATED', 'DRY_RUN_OK', 'SKIPPED_ALREADY_IMPORTED', 'SKIPPED_SAME_AS_EARLIER_ROW'], TRUE)) {
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

    echo "\nSummary:\n";
    ksort($counts);
    foreach ($counts as $status => $count) {
      echo sprintf("  %-30s %d\n", $status, $count);
    }
    echo "\nRows not imported: $issueCount, listed with the reason in {$this->issuesPath}\n";
  }

  /**
   * Reads and checks the sheet. Returns the selected rows keyed by sheet line.
   *
   * The sheet can have empty columns after the named ones; they are ignored.
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
    if (array_slice($header, 0, count(self::COLUMNS)) !== self::COLUMNS) {
      $this->fail("The sheet columns are not the expected ones.\nExpected: " . implode(' | ', self::COLUMNS) . "\nFound:    " . implode(' | ', array_slice($header, 0, count(self::COLUMNS))));
    }
    $this->groupHeader = $groupHeader;

    $rows = [];
    $line = self::FIRST_DATA_LINE - 1;
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
      $line++;
      if (count($row) < count(self::COLUMNS)) {
        $this->fail("Sheet line $line has " . count($row) . ' columns, expected at least ' . count(self::COLUMNS));
      }
      if ($this->fromLine === NULL || ($line >= $this->fromLine && $line <= $this->toLine)) {
        $rows[$line] = array_map('trim', array_slice($row, 0, count(self::COLUMNS)));
      }
    }
    fclose($handle);
    return $rows;
  }

  /**
   * Resolves the offices, the camps and the contacts by old-system id.
   */
  private function resolveLookups(array $rows): void {
    foreach ($rows as $row) {
      $office = $row[self::COL_OFFICE];
      if (!array_key_exists($office, $this->offices)) {
        $offices = Contact::get(FALSE)
          ->addSelect('id')
          ->addWhere('contact_type', '=', 'Organization')
          ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
          ->addWhere('organization_name', '=', $office)
          ->addWhere('is_deleted', '=', FALSE)
          ->execute();
        $this->offices[$office] = $offices->count() === 1 ? (int) $offices->first()['id'] : NULL;
      }
      $code = $row[self::COL_CODE];
      if (!array_key_exists($code, $this->camps)) {
        $this->camps[$code] = $this->findCampCandidates($code);
      }
    }

    // Contacts by old-system id, a thousand at a time.
    $ids = array_values(array_unique(array_filter(array_column($rows, self::COL_CONTACT_ID))));
    foreach (array_chunk($ids, 1000) as $chunk) {
      $contacts = Contact::get(FALSE)
        ->addSelect('id', 'external_identifier')
        ->addWhere('external_identifier', 'IN', $chunk)
        ->addWhere('is_deleted', '=', FALSE)
        ->execute();
      foreach ($contacts as $contact) {
        $this->byExternalId[$contact['external_identifier']] = (int) $contact['id'];
      }
    }

    echo "Lookups:\n";
    foreach ($this->offices as $name => $id) {
      echo "  Office '$name' => " . ($id ?: 'NOT FOUND') . "\n";
    }
    $missing = array_keys(array_filter($this->camps, fn($c) => !$c));
    echo '  Camps: ' . (count($this->camps) - count($missing)) . ' of ' . count($this->camps) . ' codes found' . ($missing ? '; not found: ' . implode(', ', $missing) : '') . "\n";
    echo '  Contributors with a contact for their Contact ID: ' . count($this->byExternalId) . ' of ' . count($ids) . "\n\n";
  }

  /**
   * Processes one sheet row.
   */
  private function processRow(int $line, array $row): array {
    $result = ['note' => ''];
    $errors = [];

    if ($row[self::COL_EVENT_TYPE] !== 'Collection Camp') {
      $errors[] = "Event Type is '{$row[self::COL_EVENT_TYPE]}', this script only imports 'Collection Camp'";
    }
    $date = $this->parseDate($row[self::COL_DATE]);
    if (!$date && $this->createDateFallback) {
      $date = $this->parseDate(substr($row[self::COL_CREATED], 0, 10));
      if ($date) {
        $result['note'] = "Contribution date '{$row[self::COL_DATE]}' is not a date; the Create Date is used.";
      }
    }
    if (!$date) {
      $errors[] = "Contribution date is not a date: '{$row[self::COL_DATE]}'";
    }
    elseif ($date > date('Y-m-d', strtotime('-' . self::MIN_AGE_DAYS . ' days'))) {
      $errors[] = "Contribution date $date is in the last " . self::MIN_AGE_DAYS . ' days: the receipt reminder would email it';
    }
    $result['date'] = $date;
    if (empty($this->offices[$row[self::COL_OFFICE]])) {
      $errors[] = "Goonj office not found: '{$row[self::COL_OFFICE]}'";
    }
    $camp = $date ? $this->pickCamp($row[self::COL_CODE], $date) : NULL;
    if (!$camp) {
      $errors[] = "No camp with the code '{$row[self::COL_CODE]}'";
    }
    else {
      $result += ['camp_id' => $camp['id'], 'camp_title' => $camp['title']];
      if ($camp['note']) {
        $result['note'] = trim($result['note'] . ' ' . $camp['note']);
      }
    }

    $match = $this->matchContributor($row);
    $result += ['contact_id' => $match['id'], 'match' => $match['method'], 'match_note' => $match['note']];
    if (!$match['id']) {
      $errors[] = 'Contributor not found';
    }

    if ($errors) {
      return ['status' => 'SKIPPED_BAD_DATA', 'note' => trim($result['note'] . ' ' . implode('; ', $errors))] + $result;
    }

    $key = $match['id'] . '|' . $camp['id'] . '|' . $date;
    if (isset($this->seen[$key])) {
      return ['status' => 'SKIPPED_SAME_AS_EARLIER_ROW', 'note' => "Same contributor, camp and date as line {$this->seen[$key]}"] + $result;
    }
    $this->seen[$key] = $line;

    $existing = $this->findExistingContribution($match['id'], $camp, $date);
    if ($existing) {
      return ['status' => 'SKIPPED_ALREADY_IMPORTED', 'activity_id' => $existing] + $result;
    }

    if (!$this->apply) {
      return ['status' => 'DRY_RUN_OK'] + $result;
    }

    try {
      $activityId = (int) Activity::create(FALSE)
        ->addValue('activity_type_id:name', 'Material Contribution')
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_date_time', $date . ' 00:00:00')
        ->addValue('source_contact_id', $match['id'])
        ->addValue($camp['field'], $camp['id'])
        ->addValue('Material_Contribution.Contribution_Date', $date . ' 00:00:00')
        ->addValue('Material_Contribution.Goonj_Office', $this->offices[$row[self::COL_OFFICE]])
        ->execute()->single()['id'];
      return ['status' => 'CREATED', 'activity_id' => $activityId] + $result;
    }
    catch (\Throwable $e) {
      return ['status' => 'ERROR', 'note' => trim($result['note'] . ' ' . $e->getMessage())] + $result;
    }
  }

  /**
   * The camps with the code, or with one or two extra 0s in the number.
   */
  private function findCampCandidates(string $code): array {
    $titles = [$code];
    if (substr_count($code, '/') >= 3) {
      $parts = explode('/', $code);
      $number = array_pop($parts);
      foreach (['0', '00'] as $zeros) {
        $titles[] = implode('/', array_merge($parts, [$zeros . $number]));
      }
    }
    $camps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id', 'title', 'subtype:name',
        'Collection_Camp_Intent_Details.Start_Date', 'Collection_Camp_Intent_Details.End_Date',
        'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_', 'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_')
      ->addWhere('title', 'IN', $titles)
      ->addWhere('subtype:name', 'IN', ['Collection_Camp', 'Institution_Collection_Camp'])
      ->execute();
    $candidates = [];
    foreach ($camps as $camp) {
      $institution = $camp['subtype:name'] === 'Institution_Collection_Camp';
      $start = $institution ? $camp['Institution_Collection_Camp_Intent.Collections_will_start_on_Date_'] : $camp['Collection_Camp_Intent_Details.Start_Date'];
      $end = $institution ? $camp['Institution_Collection_Camp_Intent.Collections_will_end_on_Date_'] : $camp['Collection_Camp_Intent_Details.End_Date'];
      $candidates[] = [
        'id' => (int) $camp['id'],
        'title' => $camp['title'],
        'exact' => $camp['title'] === $code,
        'field' => $institution ? 'Material_Contribution.Institution_Collection_Camp' : 'Material_Contribution.Collection_Camp',
        'start' => $start ? substr($start, 0, 10) : NULL,
        'end' => $end ? substr($end, 0, 10) : NULL,
      ];
    }
    return $candidates;
  }

  /**
   * Picks the camp for a contribution date among the code's candidates.
   */
  private function pickCamp(string $code, string $date): ?array {
    $candidates = $this->camps[$code] ?? [];
    if (!$candidates) {
      return NULL;
    }
    $inDates = array_values(array_filter($candidates, fn($c) => $c['start'] && $c['end'] && $c['start'] <= $date && $date <= $c['end']));
    if (count($inDates) === 1) {
      $camp = $inDates[0];
      $camp['note'] = $camp['exact'] ? '' : "Camp {$camp['title']} (the code with an extra 0) is the one on the contribution date.";
      return $camp;
    }
    $exact = array_values(array_filter($candidates, fn($c) => $c['exact']));
    if (count($exact) !== 1) {
      return NULL;
    }
    $camp = $exact[0];
    $camp['note'] = ($camp['start'] && $camp['end'] && !($camp['start'] <= $date && $date <= $camp['end']))
      ? "Contribution date is outside the camp dates ({$camp['start']} to {$camp['end']})."
      : '';
    return $camp;
  }

  /**
   * Matches the contributor to an existing contact.
   *
   * 1. The contact with the sheet's Contact ID as external identifier.
   * 2. Among the individual contacts with the sheet email: first name +
   *    email, else email + mobile, else email only.
   * 3. Without an email: first name + mobile.
   * When a step finds several contacts, the first one (lowest id) is used.
   */
  private function matchContributor(array $row): array {
    $externalId = $row[self::COL_CONTACT_ID];
    $email = strtolower($row[self::COL_EMAIL]);
    $firstName = $this->normaliseName($row[self::COL_FIRST_NAME]);
    $mobile = $this->lastTenDigits($row[self::COL_PHONE]);

    $others = $email !== '' ? $this->contactsWithEmail($email) : [];
    if ($externalId !== '' && isset($this->byExternalId[$externalId])) {
      $id = $this->byExternalId[$externalId];
      $other = array_values(array_diff($others, [$id]));
      return ['id' => $id, 'method' => 'contact_id', 'note' => $other ? 'other contacts with this email: ' . implode(',', $other) : ''];
    }

    if ($others) {
      $note = count($others) > 1 ? count($others) . ' contacts have this email: ' . implode(',', $others) : '';
      if ($byName = $this->filterByFirstName($others, $firstName)) {
        return ['id' => $byName[0], 'method' => 'first_name+email', 'note' => $note];
      }
      if ($byMobile = $this->filterByMobile($others, $mobile)) {
        return ['id' => $byMobile[0], 'method' => 'email+mobile', 'note' => $note];
      }
      return ['id' => $others[0], 'method' => 'email', 'note' => $note];
    }

    if ($firstName !== '' && $mobile !== '') {
      $ids = Phone::get(FALSE)
        ->addSelect('contact_id')
        ->addJoin('Contact AS contact', 'INNER', ['contact_id', '=', 'contact.id'])
        ->addWhere('phone_numeric', 'IN', [$mobile, '91' . $mobile, '0' . $mobile])
        ->addWhere('contact.is_deleted', '=', FALSE)
        ->addWhere('contact.contact_type', '=', 'Individual')
        ->execute()->column('contact_id');
      $ids = array_values(array_unique(array_map('intval', $ids)));
      sort($ids);
      if ($byName = $this->filterByFirstName($ids, $firstName)) {
        return ['id' => $byName[0], 'method' => 'first_name+mobile', 'note' => ''];
      }
    }
    return ['id' => NULL, 'method' => 'NOT_FOUND', 'note' => 'No contact with this Contact ID, email, or first name + mobile'];
  }

  /**
   * The individual contacts that have the email, lowest id first.
   */
  private function contactsWithEmail(string $email): array {
    $ids = Email::get(FALSE)
      ->addSelect('contact_id')
      ->addJoin('Contact AS contact', 'INNER', ['contact_id', '=', 'contact.id'])
      ->addWhere('email', '=', $email)
      ->addWhere('contact.is_deleted', '=', FALSE)
      ->addWhere('contact.contact_type', '=', 'Individual')
      ->execute()->column('contact_id');
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);
    return $ids;
  }

  /**
   * Keeps the contacts whose first name matches (lowest id first).
   */
  private function filterByFirstName(array $ids, string $firstName): array {
    if (!$ids || $firstName === '') {
      return [];
    }
    $matched = [];
    foreach (Contact::get(FALSE)->addSelect('id', 'first_name')->addWhere('id', 'IN', $ids)->addOrderBy('id')->execute() as $contact) {
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
    if (!$ids || $mobile === '') {
      return [];
    }
    $matched = [];
    foreach (Phone::get(FALSE)->addSelect('contact_id', 'phone_numeric')->addWhere('contact_id', 'IN', $ids)->execute() as $phone) {
      if ($this->lastTenDigits((string) $phone['phone_numeric']) === $mobile) {
        $matched[(int) $phone['contact_id']] = (int) $phone['contact_id'];
      }
    }
    sort($matched);
    return array_values($matched);
  }

  /**
   * The contribution this row saved on an earlier run, if any.
   */
  private function findExistingContribution(int $contactId, array $camp, string $date): ?int {
    $activity = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('activity_type_id:name', '=', 'Material Contribution')
      ->addWhere('source_contact_id', '=', $contactId)
      ->addWhere($camp['field'], '=', $camp['id'])
      ->addWhere('Material_Contribution.Contribution_Date', '=', $date . ' 00:00:00')
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()->first();
    return $activity ? (int) $activity['id'] : NULL;
  }

  /**
   * Parses a 'YYYY-MM-DD' date.
   */
  private function parseDate(string $value): ?string {
    $date = \DateTime::createFromFormat('!Y-m-d', $value);
    $errors = \DateTime::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y') < 2000) {
      return NULL;
    }
    return $date->format('Y-m-d');
  }

  /**
   * Lower-cases a name and collapses its spaces.
   */
  private function normaliseName(string $value): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
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

(new GoonjLegacyCampMaterialContributionImport())->run();
