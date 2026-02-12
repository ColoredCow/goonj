<?php

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

function usage(): void {
  $script = basename(__FILE__);
  fwrite(STDOUT, <<<TXT
Bulk-assign contacts to a CiviCRM group using a single SQL statement.

Run via cv so CiviCRM is bootstrapped:
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/path/to/wp --user=tarun -- --group-id=126 --source="Fake Load Test"

Filters (choose one or combine):
  --source=TEXT          Match civicrm_contact.source
  --email-domain=DOMAIN  Match primary/any email ending with @DOMAIN (e.g. example.invalid)

Required:
  --group-id=ID          Target group ID

Other:
  --status=Added|Removed|Pending   Membership status to set (default: Added)
  --dry-run              Print SQL only; do not write
  --help                 Show this help

Examples:
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/Users/tarunjoshi/Projects/goonj --user=tarun -- --group-id=126 --email-domain=example.invalid
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/Users/tarunjoshi/Projects/goonj --user=tarun -- --group-id=126 --source="Data Migration"

TXT);
}

function requireCiviBootstrap(): void {
  if (!class_exists(\Civi::class) || !class_exists(\CRM_Core_DAO::class)) {
    fwrite(STDERR, "CiviCRM not bootstrapped. Run this via `cv php:script ...` (see --help).\n");
    exit(2);
  }
}

function parseArgs(array $argv): array {
  $args = [
    'group_id' => null,
    'source' => null,
    'email_domain' => null,
    'status' => 'Added',
    'dry_run' => false,
    'help' => false,
  ];

  foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') {
      $args['help'] = true;
      continue;
    }
    if ($arg === '--dry-run') {
      $args['dry_run'] = true;
      continue;
    }
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
      continue;
    }

    [$key, $value] = explode('=', substr($arg, 2), 2);
    switch ($key) {
      case 'group-id':
        $args['group_id'] = (int) $value;
        break;
      case 'source':
        $args['source'] = (string) $value;
        break;
      case 'email-domain':
        $args['email_domain'] = (string) $value;
        break;
      case 'status':
        $args['status'] = (string) $value;
        break;
    }
  }

  return $args;
}

function main(array $opts): void {
  requireCiviBootstrap();

  if (empty($opts['group_id'])) {
    fwrite(STDERR, "Missing required --group-id.\n\n");
    usage();
    exit(2);
  }

  $status = $opts['status'] ?: 'Added';
  if (!in_array($status, ['Added', 'Removed', 'Pending'], true)) {
    fwrite(STDERR, "Invalid --status. Use Added, Removed, or Pending.\n");
    exit(2);
  }

  $where = [];
  $params = [
    1 => [(int) $opts['group_id'], 'Integer'],
    2 => [$status, 'String'],
  ];

  $from = 'civicrm_contact c';

  if (!empty($opts['email_domain'])) {
    $from .= ' INNER JOIN civicrm_email e ON e.contact_id = c.id';
    $where[] = 'e.email LIKE %3';
    $params[3] = ['%@' . $opts['email_domain'], 'String'];
  }

  if (!empty($opts['source'])) {
    $where[] = 'c.source = %4';
    $params[4] = [$opts['source'], 'String'];
  }

  if (empty($where)) {
    fwrite(STDERR, "Refusing to run without a filter. Provide --source and/or --email-domain.\n");
    exit(2);
  }

  $whereSql = implode(' AND ', $where);

  // Use ON DUPLICATE KEY UPDATE so existing memberships get re-added.
  $sql = "
    INSERT INTO civicrm_group_contact (group_id, contact_id, status)
    SELECT %1 AS group_id, c.id AS contact_id, %2 AS status
    FROM {$from}
    WHERE {$whereSql}
    ON DUPLICATE KEY UPDATE status = VALUES(status)
  ";

  $sql = preg_replace('/\\s+/', ' ', trim($sql));

  if (!empty($opts['dry_run'])) {
    fwrite(STDOUT, $sql . "\n");
    exit(0);
  }

  $dao = \CRM_Core_DAO::executeQuery($sql, $params);
  $affected = method_exists($dao, 'affectedRows') ? (int) $dao->affectedRows() : 0;

  fwrite(STDOUT, "Done. SQL affected rows: {$affected}\n");
  fwrite(STDOUT, "If this is a Smart Group, run the 'Update Smart Groups Cache' scheduled job.\n");
}

$opts = parseArgs(array_slice($argv, 1));
if (!empty($opts['help'])) {
  usage();
  exit(0);
}

main($opts);

