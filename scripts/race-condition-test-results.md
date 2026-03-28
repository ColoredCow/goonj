# Race Condition Test Results — Issue #305

**Test script:** `scripts/simulate-concurrent-webhooks.sh`
**Target:** `https://staging-crm.goonj.org` (UAT)
**Processor ID:** 7
**Subscriptions:** `sub_SWb3vsfqGWbLeu`, `sub_SWb4MYSXJDKlnx`
**Code branch:** Before fix (PR #1614 not applied)

---

## Run 1 — 20 concurrent webhooks

**Time:** 2026-03-28 15:25:00 - 15:25:19 IST
**Concurrency:** 20

| Metric | Count |
|--------|-------|
| Total requests | 20 |
| Contributions created | 18 (IDs 11644-11663, with gaps) |
| Invoice numbers assigned | 18 (GNJCRM/25-26/3752 - 3769) |
| Receipts sent | 18 |
| **Failed payments** | **2** |
| Database deadlocks (retried) | 2 |

### Failures

1. **15:25:11** — `Failed to process recurring payment for subscription: sub_SWb3vsfqGWbLeu` — `DB Error: constraint violation`
2. **15:25:13** — `Failed to process recurring payment for subscription: sub_SWb3vsfqGWbLeu` — `DB Error: constraint violation`

### Deadlocks (auto-retried, succeeded)

1. **15:25:10** — `Retrying after Database deadlock encountered` on `civicrm_contribution_recur.id = 6058`
2. **15:25:12** — `Retrying after Database deadlock encountered` on `civicrm_contribution_recur.id = 6058`

---

## Run 2 — 20 concurrent webhooks

**Time:** 2026-03-28 15:30:44 - 15:31:04 IST
**Concurrency:** 20

| Metric | Count |
|--------|-------|
| Total requests | 20 |
| Contributions created | 20 (IDs 11664-11683) |
| Invoice numbers assigned | 20 (GNJCRM/25-26/3770 - 3789) |
| Receipts sent | 20 |
| **Failed payments** | **0** |
| Database deadlocks (retried) | 0 |

### Failures

None.

### Deadlocks

None.


---

## Run 3 — 20 concurrent webhooks

**Time:** 2026-03-28 15:38:53 - 15:39:09 IST
**Concurrency:** 20

| Metric | Count |
|--------|-------|
| Total requests | 20 |
| Contributions created | 19 |
| Invoice numbers assigned | 19 |
| Receipts sent | 19 |
| **Failed payments** | **1** |
| Database deadlocks (retried) | 1 |

### Failures

1. **15:38:55** — `Failed to process recurring payment for subscription: sub_SWb4MYSXJDKlnx` — `DB Error: constraint violation`

### Deadlocks (auto-retried, succeeded)

1. **15:38:54** — `Retrying after Database deadlock encountered` on `civicrm_contribution_recur.id = 6059`

---

## Run 4 — 20 concurrent webhooks

**Time:** 2026-03-28 15:41:10 - 15:41:29 IST
**Concurrency:** 20

| Metric | Count |
|--------|-------|
| Total requests | 20 |
| Contributions created | 19 |
| Invoice numbers assigned | 19 |
| Receipts sent | 19 |
| **Failed payments** | **1** |
| Database deadlocks (retried) | 1 |

### Failures

1. **15:41:10** — `Failed to process recurring payment for subscription: sub_SWb3vsfqGWbLeu` — `DB Error: constraint violation`

### Deadlocks (auto-retried, succeeded)

1. **15:41:10** — `Retrying after Database deadlock encountered` on `civicrm_contribution_recur.id = 6058`

---

## Run 5 — 20 concurrent webhooks

**Time:** 2026-03-28 15:42:03 - 15:42:21 IST
**Concurrency:** 20

| Metric | Count |
|--------|-------|
| Total requests | 20 |
| Contributions created | 20 |
| Invoice numbers assigned | 20 |
| Receipts sent | 20 |
| **Failed payments** | **0** |
| Database deadlocks (retried) | 0 |

### Failures

None.

### Deadlocks

None.

---

## Overall Summary

| | Run 1 | Run 2 | Run 3 | Run 4 | Run 5 | Total |
|---|---|---|---|---|---|---|
| Requests | 20 | 20 | 20 | 20 | 20 | 100 |
| Succeeded | 18 | 20 | 19 | 19 | 20 | 96 |
| Failed | 2 | 0 | 1 | 1 | 0 | 4 |
| Deadlocks | 2 | 0 | 1 | 1 | 0 | 4 |
| **Failure rate** | **10%** | **0%** | **5%** | **5%** | **0%** | **4%** |

## Conclusion

Across 100 concurrent webhook requests (5 batches of 20), **4 payments were lost** (4% failure rate) due to `DB Error: constraint violation` during concurrent `Contribution::create` calls. Additionally, 4 database deadlocks occurred on `civicrm_contribution_recur` updates, though these were auto-retried successfully by CiviCRM.

Key observations:
- Failures are **non-deterministic** — 3 out of 5 runs had failures, 2 were clean
- Every failure was accompanied by a deadlock on the same subscription's `contribution_recur` row
- Both subscriptions were affected: `sub_SWb3vsfqGWbLeu` (3 failures) and `sub_SWb4MYSXJDKlnx` (1 failure)
- Each failure = a real payment lost: Razorpay charged the donor but CiviCRM has no contribution record

This confirms the race condition described in issue #305 is reproducible with the script. The script can be re-run after applying PR #1614 to verify the fix.
