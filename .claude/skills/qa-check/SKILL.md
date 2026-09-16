---
name: qa-check
description: Generate and run Goonj QA checks from the plain-English cases in docs/qa/cases/. Use when asked to write a check for a case ID, regenerate checks, or run the QA suite for changed code.
---

# Goonj QA checks

Cases are written in plain English by QA in `docs/qa/cases/`. You turn them into
executable checks under `tests/qa/`. **The case file is the source of truth. Never
change a case to make a check pass.**

## Generating a check

1. Read the case from `docs/qa/cases/`. Use its ID as the filename: `MON-012.js`.
2. Copy `tests/qa/_template.js` and fill it in.
3. Assert against **data**, via the CiviCRM API — never against screen text. The
   failures this suite exists for are invisible from the UI.
4. Create your own fixtures inside the check. **Never rely on a record that already
   exists.** The old Playwright suite broke exactly this way: it depended on "the
   latest volunteer", so two browsers had to be switched off.
5. Clean up everything you created, in a `finally` block, so a failure still tidies up.
6. Keep one case per file. If a case needs two very different setups, split the case
   in the case file first and ask QA to confirm.
7. Set `status: ready` in the case file only after the check has actually run.

## Proving a check is worth having

A check that cannot fail is worse than no check, because CI reports green.

Before marking a case `ready`, demonstrate the check fails when the bug is present.
In order of preference:

1. Check out the commit before the fix, run the check, watch it fail.
2. If that is impractical, temporarily invert the condition it asserts and confirm the
   failure message clearly names the problem.

Record which method you used in the check's `verifiedBy` field. A check with an empty
`verifiedBy` is not trusted and does not gate anything.

## Writing assertions

- Assert the specific value, not that something is truthy. `receive_date` equals the
  charge date — not "receive_date exists".
- One clear failure message per assertion, naming the case ID and what differed.
- Never `sleep`. Poll with a timeout if you must wait for a cron or a queue.
- Money is compared as an exact decimal string, never a float.

## Running

    npm run qa                    # everything
    npm run qa -- --id MON-012    # one case
    npm run qa -- --area receipts # one area
    npm run qa -- --changed       # only areas touched by the current git diff

`--changed` maps changed paths to areas using the `paths` field in each check. This is
what runs at development time and on pull requests.

## Money-path review

When a diff touches `wp-content/civi-extensions/civirazorpay/`, contribution or receipt
code in `goonjcustom/`, or any `hook_civicrm_post` handler, review it against every
rule below and say which historical case it could reintroduce:

- **Replay safety** — if this webhook or job is delivered twice, is the result the same
  as running it once? (goonj-crm#306, #355)
- **Failure signalling** — on failure, does the handler return a non-2xx so the sender
  retries, rather than swallowing it? (goonj-crm#355)
- **Recovery** — if the event is lost entirely, is there another path that recovers it,
  or a job that notices the gap? A job that only ever looks at a single window makes a
  missed write permanent. (goonj-crm#345)
- **Locks and transactions** — does it hold a lock or an open transaction while calling
  CiviCRM APIs, which can re-enter contribution save internals? (goonj-crm#305, #306)
- **Dates** — does anything that completes a contribution set `receive_date` to the
  real charge date? (goonj-crm#355)
- **Receipts** — can this change affect receipt numbering, content, or attachment?
  Numbering must stay unique and gapless under concurrency.
- **Local disk** — does it write files to local disk? Web and worker servers do not
  share disk unless the path is on EFS. (goonj-crm#352)
- **Which server** — does this run on web, worker, or both, and has it been checked on
  both?
- **Detection** — if this breaks silently in production, what surfaces it? If the
  answer is "a donor complains", say so explicitly in the review.

## Environment

Checks run against whatever `CIVI_BASE_URL` points at — staging today, a local site
once one exists. Never point them at production.
