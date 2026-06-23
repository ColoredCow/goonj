<?php

/**
 * Merge frozen-snapshot duplicate contacts via CiviCRM's own merge engine.
 *
 * IMPORTANT: run with `cv scr`, NOT plain php — it needs a bootstrapped CiviCRM
 * so the Contact.merge API (which moves contributions/activities/groups/tags/etc.)
 * is available.
 *
 *   cv scr bin/merge_duplicates.php          (run from the CiviCRM/WordPress web root)
 *
 * SCOPE  : only contacts present in the `merge_scope` table (the frozen list of
 *          duplicate contact ids you loaded via TablePlus — the CSV file itself is
 *          NOT needed on the server, only the table).
 *
 * KEEPER rule per duplicate group (same first_name + primary phone + primary email):
 *   1. the contact WITH a street address wins
 *   2. tie (both have / both don't) -> NEWEST modified_date wins
 *   3. final tie -> lowest contact id
 *   Every other member of the group is merged INTO the keeper (and lands in Trash,
 *   is_deleted=1 — recoverable, not hard-deleted).
 *
 * SAFETY:
 *   $DRY_RUN = true  -> logs what WOULD happen, merges nothing.
 *   $MODE 'aggressive' -> confirmed duplicates: keeper's value wins on a single-value
 *                         conflict. Multi-value data (extra emails/phones/activities/
 *                         groups/tags/contributions) is ALWAYS preserved on the keeper.
 *   $LIMIT_GROUPS    -> groups processed per run (batching).
 *   Log -> /tmp/merge_duplicates_log.csv  (one row per merge attempt)
 */

// --- Overridable via env vars (no need to edit this file on the server) ---
//   DRY_RUN=false  -> actually merge   (anything else / unset -> safe dry-run)
//   LIMIT_GROUPS=500  -> batch size    MODE=safe|aggressive    LOG=/path/to.csv
//   e.g. live run:  DRY_RUN=false cv scr bin/merge_duplicates.php
$envDry  = getenv('DRY_RUN');
$DRY_RUN = ($envDry === false)
  ? true                                                   // unset -> SAFE dry-run
  : !in_array(strtolower(trim($envDry)), ['false', '0', 'no', 'off'], true);
$LIMIT_GROUPS = (int) (getenv('LIMIT_GROUPS') ?: 99999);    // all groups in one go; lower if it times out.
$MODE         = getenv('MODE') ?: 'aggressive';             // confirmed duplicates -> keeper wins single-value conflicts.
$LOG          = getenv('LOG') ?: '/tmp/merge_duplicates_log.csv';

if (!function_exists('civicrm_api3')) {
  fwrite(STDERR, "CiviCRM not bootstrapped. Run with:  cv scr bin/merge_duplicates.php\n");
  exit(1);
}

// Bulk merges accumulate memory; default 128M dies after a few hundred. Lift limits.
@ini_set('memory_limit', '2048M');
@set_time_limit(0);

CRM_Core_DAO::executeQuery("SET SESSION sql_mode=''");

// keeper + the members that merge into it, restricted to merge_scope.
$sql = "
WITH per AS (
  SELECT c.id,
         LOWER(TRIM(IFNULL(c.first_name,''))) fn,
         IF(p.phone_numeric='0' OR p.phone_numeric IS NULL,'',p.phone_numeric) ph,
         LOWER(TRIM(IFNULL(e.email,''))) em,
         c.modified_date,
         CASE WHEN a.street_address IS NOT NULL AND TRIM(a.street_address)<>'' THEN 1 ELSE 0 END AS has_address
  FROM merge_scope ms
  JOIN civicrm_contact c ON c.id = ms.contact_id AND c.is_deleted = 0
  LEFT JOIN civicrm_email e ON e.id = (SELECT MIN(e2.id) FROM civicrm_email e2 WHERE e2.contact_id=c.id AND e2.is_primary=1)
  LEFT JOIN civicrm_phone p ON p.id = (SELECT MIN(p2.id) FROM civicrm_phone p2 WHERE p2.contact_id=c.id AND p2.is_primary=1)
  LEFT JOIN civicrm_address a ON a.id = (SELECT MIN(a2.id) FROM civicrm_address a2 WHERE a2.contact_id=c.id AND a2.is_primary=1)
),
ranked AS (
  SELECT id, fn, ph, em,
    ROW_NUMBER()    OVER (PARTITION BY fn,ph,em ORDER BY has_address DESC, modified_date DESC, id ASC) rn,
    FIRST_VALUE(id) OVER (PARTITION BY fn,ph,em ORDER BY has_address DESC, modified_date DESC, id ASC) keeper_id
  FROM per
)
SELECT keeper_id, id AS contact_id
FROM ranked
WHERE rn > 1
ORDER BY keeper_id, contact_id
";

$dao = CRM_Core_DAO::executeQuery($sql);
$pairs = [];
while ($dao->fetch()) {
  $pairs[(int) $dao->keeper_id][] = (int) $dao->contact_id;
}

$fh = fopen($LOG, 'a');
fputcsv($fh, ['time', 'keeper_id', 'remove_id', 'result', 'detail']);
$ts = date('Y-m-d H:i:s');

$groupsDone = 0;
$merged = 0;
$skipped = 0;
$errors = 0;

foreach ($pairs as $keeper => $removes) {
  if ($groupsDone >= $LIMIT_GROUPS) {
    break;
  }
  $groupsDone++;
  foreach ($removes as $remove) {
    if ($DRY_RUN) {
      fputcsv($fh, [$ts, $keeper, $remove, 'DRY_RUN', 'would merge']);
      continue;
    }
    try {
      $r = civicrm_api3('Contact', 'merge', [
        'to_keep_id'   => $keeper,
        'to_remove_id' => $remove,
        'mode'         => $MODE,
      ]);
      if (!empty($r['values']['merged'])) {
        $merged++;
        fputcsv($fh, [$ts, $keeper, $remove, 'MERGED', '']);
      }
      else {
        $skipped++;
        fputcsv($fh, [$ts, $keeper, $remove, 'SKIPPED_CONFLICT', json_encode($r['values'])]);
      }
    }
    catch (\Throwable $e) {
      // \Throwable (not just Exception) so a CiviCRM 6.13 TypeError on one pair
      // (e.g. null primary email -> updateContactName) logs + skips that pair
      // instead of killing the whole run.
      $errors++;
      fputcsv($fh, [$ts, $keeper, $remove, 'ERROR', $e->getMessage()]);
    }
  }
}
fclose($fh);

echo "==== Goonj duplicate merge ====\n";
echo ($DRY_RUN ? "[DRY RUN — nothing merged]\n" : "[LIVE merge — mode: {$MODE}]\n");
echo "groups processed : {$groupsDone}\n";
echo "merged           : {$merged}\n";
echo "skipped(conflict): {$skipped}\n";
echo "errors           : {$errors}\n";
echo "log              : {$LOG}\n";
