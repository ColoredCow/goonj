<?php

use Civi\Api4\Contact;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

function usage(): void {
  $script = basename(__FILE__);
  fwrite(STDOUT, <<<TXT
Seed fake Individual contacts (for load-testing bulk email).

Run via cv so CiviCRM is bootstrapped:
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/path/to/wp --user=tarun -- --count=10000 --email-domain=example.invalid

Options (after the --):
  --count=N          Number of contacts to create (default: 1000)
  --start=N          Starting index for names/emails (default: 1)
  --batch=N          Batch size (default: 500)
  --email-domain=D   Email domain to use (default: example.invalid)
  --group-id=ID      If set, add all contacts to this CiviCRM group
  --source=TEXT      Contact source (default: Fake Load Test)
  --dry-run          Print what would happen; do not write
  --help             Show this help

Examples:
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/Users/tarunjoshi/Projects/goonj --user=tarun -- --count=10000 --batch=500 --email-domain=example.invalid
  php /usr/local/bin/cv php:script wp-content/civi-extensions/goonjcustom/cli/$script --cwd=/Users/tarunjoshi/Projects/goonj --user=tarun -- --count=2000 --group-id=12 --email-domain=mailpit.test

TXT);
}

function parseArgs(array $argv): array {
  $args = [
    'count' => 1000,
    'start' => 1,
    'batch' => 500,
    'email_domain' => 'example.invalid',
    'group_id' => null,
    'source' => 'Fake Load Test',
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
      case 'count':
      case 'start':
      case 'batch':
        $args[$key] = (int) $value;
        break;

      case 'email-domain':
        $args['email_domain'] = (string) $value;
        break;

      case 'group-id':
        $args['group_id'] = $value === '' ? null : (int) $value;
        break;

      case 'source':
        $args['source'] = (string) $value;
        break;
    }
  }

  $args['count'] = max(0, (int) $args['count']);
  $args['start'] = max(1, (int) $args['start']);
  $args['batch'] = max(1, (int) $args['batch']);

  return $args;
}

function requireCiviBootstrap(): void {
  if (!class_exists(\Civi::class) || !class_exists(Contact::class) || !function_exists('civicrm_api3')) {
    fwrite(STDERR, "CiviCRM not bootstrapped. Run this via `cv php:script ...` (see --help).\n");
    exit(2);
  }
}

function makeEmail(int $i, string $domain): string {
  // Keep it deterministic and unique.
  return "fake{$i}@{$domain}";
}

function seedFakeContacts(array $opts): void {
  requireCiviBootstrap();

  $count = $opts['count'];
  $start = $opts['start'];
  $batchSize = $opts['batch'];
  $emailDomain = $opts['email_domain'];
  $groupId = $opts['group_id'];
  $source = $opts['source'];
  $dryRun = $opts['dry_run'];

  fwrite(STDOUT, "Seeding {$count} contacts (start={$start}, batch={$batchSize}, domain={$emailDomain})\n");
  if ($groupId) {
    fwrite(STDOUT, "Adding to group_id={$groupId}\n");
  }
  if ($dryRun) {
    fwrite(STDOUT, "Dry-run enabled (no writes).\n");
  }

  $createdTotal = 0;
  $batchStart = $start;
  $lastIndex = $start + $count - 1;

  while ($batchStart <= $lastIndex) {
    $batchEnd = min($lastIndex, $batchStart + $batchSize - 1);
    $records = [];

    for ($i = $batchStart; $i <= $batchEnd; $i++) {
      $firstName = "Fake{$i}";
      $lastName = "User";
      $records[] = [
        'contact_type' => 'Individual',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'display_name' => "{$firstName} {$lastName}",
        'source' => $source,
        'preferred_language' => 'en_US',
        // Ensure they are emailable unless you explicitly want opt-outs.
        'do_not_email' => 0,
        'is_opt_out' => 0,
      ];
    }

    if ($dryRun) {
      $created = array_map(fn($r) => $r['display_name'], $records);
      fwrite(STDOUT, "Would create " . count($created) . " contacts: {$created[0]} ... " . end($created) . "\n");
      $createdTotal += count($records);
      $batchStart = $batchEnd + 1;
      continue;
    }

    $contactIds = [];
    foreach ($records as $offset => $record) {
      $i = $batchStart + $offset;

      $contact = civicrm_api3('Contact', 'create', $record);
      if (!empty($contact['id'])) {
        $contactId = (int) $contact['id'];
        $contactIds[] = $contactId;

        civicrm_api3('Email', 'create', [
          'contact_id' => $contactId,
          'email' => makeEmail($i, $emailDomain),
          'is_primary' => 1,
          // If "Home" doesn't exist, CiviCRM will pick default; keeping numeric avoids API4 syntax.
          // 'location_type_id' => 1,
        ]);

        if ($groupId) {
          civicrm_api3('GroupContact', 'create', [
            'group_id' => (int) $groupId,
            'contact_id' => $contactId,
            'status' => 'Added',
          ]);
        }
      }
    }

    $createdTotal += count($contactIds);
    fwrite(STDOUT, "Created {$createdTotal}/{$count} (last batch: {$batchStart}-{$batchEnd})\n");

    $batchStart = $batchEnd + 1;
  }

  fwrite(STDOUT, "Done. Total created: {$createdTotal}\n");
}

$opts = parseArgs(array_slice($argv, 1));
if ($opts['help']) {
  usage();
  exit(0);
}

seedFakeContacts($opts);
