# QA checks

A loop where QA writes cases in English and Claude turns them into checks that run on
every change.

```
docs/qa/cases/*.md   →   tests/qa/**/*.js   →   dev-time hook + CI on PR
   you write these      Claude generates          they run automatically
```

**You never write test code.** The case file is the source of truth; the checks are
derived from it. If a check disagrees with its case, the case wins.

Scope today: the microsite 80(G) certificate validation (goonj-crm#371). Each case
states one behaviour the system must hold to, and links to the issue where it was
specified.

## Adding a case

1. Write it in `docs/qa/cases/monetary.md` using the next free ID and the
   Given / When / Then shape. Record where the failure came from in `Origin`.
2. Ask Claude: *generate the check for MON-0xx*. It follows
   `.claude/skills/qa-check/SKILL.md`.
3. Claude proves the check fails when the bug is present, then sets `verifiedBy`.
4. Set the case `Status: ready`.

A check with an empty `verifiedBy` is reported as `[unverified]` and does not gate
anything. That is deliberate — a check that cannot fail is worse than no check, because
CI reports green.

## Running

```sh
npm run qa                     # everything
npm run qa -- --id MON-012     # one case
npm run qa -- --area receipts  # one area
npm run qa -- --changed        # only areas touched by the current diff
```

Each check declares `paths`. `--changed` matches those globs against the diff, so only
relevant checks run — that is what makes it cheap enough for every change.

## When it runs by itself

**At development time.** `.claude/settings.local.json` is personal and gitignored, so
add this yourself to have the relevant checks run whenever Claude finishes a turn. If
nothing relevant changed, nothing runs.

```json
{
  "hooks": {
    "Stop": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "cd \"$CLAUDE_PROJECT_DIR\" && [ -f .env ] && npm run --silent qa -- --changed || true"
          }
        ]
      }
    ]
  }
}
```

**On the pull request.** `.github/workflows/qa-checks.yml` runs the same command for
PRs touching `civirazorpay/`, `goonjcustom/`, or these directories.

## Setup

```sh
npm ci
cp .env.example .env
```

Fill in `CIVI_BASE_URL` and `CIVI_API_KEY`. Point at **staging** — `tests/qa/lib/civi.js`
refuses to run against `crm.goonj.org`.

CI needs two new secrets: `CIVI_QA_API_KEY` and `CIVI_WEBHOOK_PATH`.

## Known gaps

- `CIVI_WEBHOOK_PATH` is not filled in. The Razorpay receiver path needs confirming in
  `CRM/Core/Civirazorpay/Payment/Razorpay.php` before MON-002 and MON-003 can run.
- No check has been executed yet, so every one is `[unverified]`.
- Checks write fixture data to whatever they point at. On shared staging that leaves
  QA-tagged contacts behind if a run is interrupted; they carry a `qa:` source tag.

## Why these assert on data, not screens

The failures this suite exists for — a receipt-number race, a webhook deadlock, missing
settlement fields, a wrong date — are invisible from the UI. A check that calls the
CiviCRM API and asserts the row sees them directly, runs in seconds, and does not flake.
That is also why it can run on every change; a browser suite could not.

## What this does not cover

- **Requirements nobody wrote.** No check here can tell you a requirement was never
  specified; the requirement process does.
- **Performance.** Search Kit and form slowness need monitoring thresholds, not
  pass/fail checks.
- **Whether a business rule is right.** These checks only confirm the system does what
  the case says it should.
