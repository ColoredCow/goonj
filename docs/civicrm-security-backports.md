# CiviCRM Security Backports

Goonj CRM runs **CiviCRM 6.13.3**. Rather than upgrading CiviCRM every time a security
release is published, we take the security fixes from those releases and apply them to our
version by hand. The reasoning and the agreed process are in
[ColoredCow/goonj-crm#382](https://github.com/ColoredCow/goonj-crm/issues/382).

This file exists so that anyone — not only the developer who did the work — can see which
security advisories are closed on this install, and when each backport can be thrown away.

**Keep this file updated every time a security release is backported.**

---

## Where the fixes come from

CiviCRM publishes every security release here:
**https://civicrm.org/blog/tags/security-releases**

Each release post lists the advisories it fixes. We read that list, check which advisories
affect our version, and take only those fixes.

Four releases have been backported so far:

| Security release | Published | Advisories taken |
|---|---|---|
| [CiviCRM Security Release (6.15.3, 6.10-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6153-610-esr) | 2026-06-17 | CIVI-SA-2026-18 … 34 (17) |
| [CiviCRM Security Release (6.17.2, 6.16.5, 6.10-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6172-6165-610-esr) | 2026-08-05 | CIVI-SA-2026-35, 36, 37 (3) |
| [CiviCRM Security Release (6.19.3, 6.16-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6193-616-esr) | 2026-10-28 | CIVI-SA-2026-38 … 52 (15) |
| [CiviCRM Security Release (6.20.1, 6.16-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6201-616-esr) | 2026-11-18 | CIVI-SA-2026-53 … 81 (29) |

CIVI-SA-2026-18 … 37 were applied in [ColoredCow/goonj PR #1762](https://github.com/ColoredCow/goonj/pull/1762).
Tracking issue: [ColoredCow/goonj-crm#369](https://github.com/ColoredCow/goonj-crm/issues/369).

CIVI-SA-2026-38 … 52 were taken from the public commits between the 6.19.2 and 6.19.3 tags
(none of these advisories links a patch, and the 6.16-ESR repository is not public).
Tracking issue: [ColoredCow/goonj-crm#401](https://github.com/ColoredCow/goonj-crm/issues/401).

CIVI-SA-2026-53 … 81 were taken from the public commits between the 6.20.0 and 6.20.1 tags
(again no advisory links a patch).
Tracking issue: [ColoredCow/goonj-crm#404](https://github.com/ColoredCow/goonj-crm/issues/404).

---

## Status: all 64 advisories closed

### From the 6.15.3 release (17 June 2026)

| Advisory | Issue | Severity |
|---|---|---|
| CIVI-SA-2026-18 | Stored XSS in Job Name | Moderately Critical |
| CIVI-SA-2026-19 | Stored XSS in Grant Type | Moderately Critical |
| CIVI-SA-2026-20 | Stored XSS in Website URL | Moderately Critical |
| CIVI-SA-2026-21 | Stored XSS in Event Template Title | Moderately Critical |
| CIVI-SA-2026-22 | Stored XSS in Membership Type | Moderately Critical |
| CIVI-SA-2026-23 | Stored XSS in Price Field label | Moderately Critical |
| **CIVI-SA-2026-24** | **RCE via File API** | **Critical** |
| CIVI-SA-2026-25 | Stored XSS in Tag Name | Moderately Critical |
| **CIVI-SA-2026-26** | **Unauthorized access to files via APIv3** | **Critical** |
| CIVI-SA-2026-27 | Stored XSS in Participant Status | Moderately Critical |
| **CIVI-SA-2026-28** | **Escalation via Extension Download API** | **Critical** |
| CIVI-SA-2026-29 | Multiple Stored XSS in Mailings | Moderately Critical |
| CIVI-SA-2026-30 | Stored XSS in File Attachments | Moderately Critical |
| **CIVI-SA-2026-31** | **SQL injection in GroupContact Create APIv3** | **Critical** |
| CIVI-SA-2026-32 | Stored XSS in Profile Help | Moderately Critical |
| **CIVI-SA-2026-33** | **SQL injection in OrderBy Parameters** | **Critical** |
| **CIVI-SA-2026-34** | **SQL injection in Financial Batch AJAX** | **Critical** |

### From the 6.17.2 / 6.16.5 release (5 August 2026)

| Advisory | Issue | Severity |
|---|---|---|
| **CIVI-SA-2026-35** | **Permission Bypass in APIv4** | **Highly Critical** |
| CIVI-SA-2026-36 | Information disclosure in APIv4 | Moderately Critical |
| **CIVI-SA-2026-37** | **Additional Permission Bypass in APIv4** | **Highly Critical** |

All 20 were verified present in the code and tested on a local environment before release.
Per-commit detail is in PR #1762 — this file deliberately does not repeat it.

### From the 6.19.3 / 6.16-ESR release (28 October 2026)

| Advisory | Issue | Severity |
|---|---|---|
| CIVI-SA-2026-38 | APIv4 - Information Disclosure via Joined Writes | Moderately Critical |
| CIVI-SA-2026-39 | Standalone - Cookie Security Downgrade *(Standalone only)* | Moderately Critical |
| CIVI-SA-2026-40 | Groups - Access Bypass for Reserved Groups | Moderately Critical |
| **CIVI-SA-2026-41** | **APIv4 - Access Bypass via EntitySet API** | **Critical** |
| **CIVI-SA-2026-42** | **Standalone - Privilege Escalation via User API** *(Standalone only)* | **Critical** |
| CIVI-SA-2026-43 | APIv4 - ACL Bypass via Join | Less Critical |
| **CIVI-SA-2026-44** | **APIv3 - SQLI in RLIKE Operator** | **Critical** |
| **CIVI-SA-2026-45** | **Backend Record Stealing** | **Critical** |
| **CIVI-SA-2026-46** | **Frontend Record Stealing** | **Critical** |
| CIVI-SA-2026-47 | CiviEvent - Session Overwrite via Confirmation Screen | Moderately Critical |
| CIVI-SA-2026-48 | API - Access Bypass for Certain Billing Details | Moderately Critical |
| **CIVI-SA-2026-49** | **AJAX - Multiple Vulnerabilities** | **Critical** |
| CIVI-SA-2026-50 | Standalone - MFA Bypass *(Standalone only)* | Less Critical |
| CIVI-SA-2026-51 | CiviContribute - ACL Bypass in Financial Entities | Moderately Critical |
| CIVI-SA-2026-52 | Permissions - Erroneous Short-Circuit | Moderately Critical |

The three Standalone-only advisories cannot be exploited on WordPress; they were applied anyway
to keep core in line with the fixed release. 80 commits were taken: 69 applied as published and
11 were placed by hand where the surrounding 6.13.3 code differs from 6.19 (the security lines
themselves are upstream's). The release's non-security "Bugs resolved" fixes and its test-only
commits were deliberately left out. Per-commit detail is in the PR.

The CKEditor5 and Summernote extension fixes announced in the same release do not apply to us —
neither extension is installed.

**One deliberate difference from upstream:** `civicrm/ajax/attachment` is kept as
`<is_ajax>false</is_ajax>` in `CRM/Core/xml/Menu/Misc.xml`. CIVI-SA-2026-49 marks it JavaScript-only,
but CiviCRM's own attachment uploader (used by CiviMail and Mosaico) does not send the header that
check needs, so every mailing attachment upload failed. The route keeps its own anti-forgery token
check. This is the same fix upstream made for `civicrm/ajax/admin/search` in `4bbf60027`. At the
November upgrade, check whether the target version has fixed the uploader before dropping it.

### From the 6.20.1 / 6.16-ESR release (18 November 2026)

| Advisory | Issue | Severity |
|---|---|---|
| CIVI-SA-2026-53 | CiviReport - SQLI via Filters | Moderately Critical |
| CIVI-SA-2026-54 | CiviContribute - Access Bypass in Batch Entry Form | Moderately Critical |
| CIVI-SA-2026-55 | ACLs - Access Bypass Due to Cache Collision | Moderately Critical |
| CIVI-SA-2026-56 | Contacts - Access Bypass in Bulk Delete | Moderately Critical |
| CIVI-SA-2026-57 | APIv4 - Access Bypass via Workflow Message | Moderately Critical |
| CIVI-SA-2026-58 | Form Builder - Access Bypass in Saved Search Picker | Moderately Critical |
| CIVI-SA-2026-59 | SQL Injection - Search Filters | Moderately Critical |
| CIVI-SA-2026-60 | Information Disclosure - Profile Default Entity ID Mismatch | Moderately Critical |
| CIVI-SA-2026-61 | Access Bypass - Random Segment Custom Search *(extension not installed)* | Moderately Critical |
| CIVI-SA-2026-62 | SQL Injection - Group Filters and Campaign Filters | Moderately Critical |
| **CIVI-SA-2026-63** | **Form Builder - Cross-Site Scripting in Afform Tokens** | **Critical** |
| CIVI-SA-2026-64 | Form Builder - Access Bypass in Contact Deduplication | Moderately Critical |
| CIVI-SA-2026-65 | Form Builder - Access Bypass in Form Prefill | Moderately Critical |
| **CIVI-SA-2026-66** | **Form Builder - Access Bypass in Afform Page Tokens** | **Critical** |
| CIVI-SA-2026-67 | Form Builder - Access Bypass in Afform Prefill Join Lookup | Moderately Critical |
| CIVI-SA-2026-68 | Form Builder - Data Integrity in Contact Deduplication | Moderately Critical |
| CIVI-SA-2026-69 | Form Builder - Refactoring Afform Submit Entity Actions | Less Critical |
| CIVI-SA-2026-70 | Form Builder - Access Bypass in Afform Submission IDs | Moderately Critical |
| CIVI-SA-2026-71 | Form Builder - Access Bypass in Afform Autocomplete | Moderately Critical |
| CIVI-SA-2026-72 | Form Builder - Access Bypass in Group Subscriptions | Moderately Critical |
| **CIVI-SA-2026-73** | **Form Builder - Access Bypass in Afform File Upload Fields** | **Critical** |
| CIVI-SA-2026-74 | Form Builder - Access Bypass in Afform Parent Form Presets | Moderately Critical |
| CIVI-SA-2026-75 | Form Builder - Cross-Site Scripting in Gear Menu | Moderately Critical |
| CIVI-SA-2026-76 | Form Builder - Access Bypass in Afform Submission Processing | Moderately Critical |
| CIVI-SA-2026-77 | Search Kit - Access Bypass in Dual Search Display Placement | Moderately Critical |
| CIVI-SA-2026-78 | Search Kit - Cross-Site Scripting in Tag Merge Alert *(not applicable, see below)* | Moderately Critical |
| **CIVI-SA-2026-79** | **APIv4 - Access Bypass for Contact Facade Entities** | **Critical** |
| CIVI-SA-2026-80 | CiviContribute - Access Bypass in Contribution Invoices | Moderately Critical |
| **CIVI-SA-2026-81** | **Form Builder - Incorrect Relationship Permission Handling on Resubmission** | **Critical** |

Several fixes in this release were committed twice upstream and then tidied up by later
"cherry-pick fix" commits, so the change was taken as the net difference between the 6.20.0 and
6.20.1 tags for the 54 non-test files it touches. 41 files applied as published and 10 were placed
by hand. Three files do not exist in 6.13.3 and were skipped:

- `ext/civi_contribute/Civi/Checkout/Afform.php` and `.../Contribute/Service/CreateContribution.php`
  belong to the Form Builder checkout service, which 6.13.3 does not have.
- `ext/search_kit/ang/crmSearchTasks/crmSearchTaskTagMerge.ctrl.js` is the "Merge Tags" action. It
  does not exist in 6.13.3, so CIVI-SA-2026-78 does not apply to us.

Where the 6.13.3 code differs from 6.20, these upstream lines were adapted or left out:

- `CRM/Api4/Page/AJAX.php`: the new `parseRequestParams()` (needed by CIVI-SA-2026-66) calls
  `sendMalformedJsonResponse()`, which upstream added in a non-security commit (`5c8dc8a8f0`).
  That 9-line helper was brought in with it.
- `CRM/Contribute/Form/Task/Invoice.php`: 6.13.3 keeps the selected ids in `$this->_contributionIds`,
  not `getIDs()`.
- `AbstractProcessor::preprocessSubmittedValues()`: the reshaping of the form-level "extra" entity,
  the `extras` clean-up and `getHiddenDefaultValues()` are left out. None of the three exist in 6.13.3.
  The file-id checks themselves (CIVI-SA-2026-73) are applied in full.
- `AfformSubmitEvent::__construct()` keeps 6.13.3's `string $entityType` and only gains the new
  `$entityActions` argument.

The release's non-security "Bugs" fixes and its test-only commits were left out. Per-commit detail
is in the PR.

**One Goonj code fix came with it.** `goonjcustom/Civi/InstitutionService.php` assigned the
institution's coordinator with `Organization::update('Organization', FALSE)`. The first argument
is the permission flag, so the string turned permission checks on. Before CIVI-SA-2026-79 those
checks did nothing for organizations; after it, staff who cannot edit the new institution (for
example the `urban_ops_and_accounts_chapter_team` role) failed the check during Institution
Registration, which left the coordinator blank and skipped the institution's phone and email.
The call is now `Organization::update(FALSE)`, like the office assignment just above it.

---

## When these backports become redundant

**All of them go away the moment CiviCRM is upgraded to 6.20.1 or later (or 6.16.9 ESR)**,
because every one of these fixes is already in that release. Nothing here needs to be carried
forward past that upgrade.

But there is a catch that must not be forgotten:

> A WordPress CiviCRM upgrade **replaces the entire `wp-content/plugins/civicrm` directory**.
> That silently deletes every backported file. Nothing warns you.

So at the next upgrade (planned for **November 2026**):

1. Confirm the target version is **6.20.1 or higher** — anything lower reopens some of these advisories.
2. Do **not** re-apply anything. The upgrade already contains all of it.
3. Add a line at the top of the advisory tables below recording that they are now superseded —
   which version we upgraded to and on what date. **Do not delete the tables.** They are the
   record of what this install was carrying, and they are needed if anyone ever has to audit
   the period before the upgrade.
4. Remove the manual `vendor/phlib` and autoloader entries noted at the end of this file — the
   upgrade brings its own copies, so ours are no longer needed.

If for any reason the upgrade lands on a version **below 6.20.1**, the backports have to be
re-applied. In that case, re-read the release posts linked above.

---

## Deploying a backport: flush caches

CIVI-SA-2026-49 protects AJAX routes through a new `is_ajax` flag on each menu route, and
CIVI-SA-2026-45 registers its ownership checks in the service container. Both only take effect
after CiviCRM rebuilds its menu and container. Our deploy is a `git reset --hard`, which does
neither, so **run `cv flush` on every server (web and worker) after deploying** — otherwise the
code looks deployed but these protections are not active.

The same applies to CIVI-SA-2026-53 … 81: CIVI-SA-2026-79 adds its contact checks in the service
container (`Civi/Core/Container.php`) and CIVI-SA-2026-58 adds a new hook on the Form Builder
search parameter sets. Both need `cv flush` on web and worker, and a `civicrm-coworker` restart.

---

## Two things here that did not come from a CiviCRM commit

Everything else in the backport is taken directly from CiviCRM's published commits and
patches. These two are different, and matter if anyone touches the vendor directory:

**1. `vendor/phlib/xss-sanitizer/` (21 files)**
CIVI-SA-2026-29 replaced CiviCRM's HTML sanitiser with a third-party library. The library is
part of the shipped 6.15.3 release but is not in any commit — the release build downloads it.
We installed it manually. **Without it, sending any mailing fails with a fatal error.**

**2. `vendor/composer/autoload_psr4.php` and `autoload_static.php`**
These register the library above. They are generated by `composer install` at release-build
time, so again no commit contains them. We added the entries by hand.

**Warning:** `composer.lock` does not list this library. If anyone runs `composer install` or
`composer update` inside `wp-content/plugins/civicrm/civicrm`, these entries can be lost and
mailings will break. Do not run composer in that directory.

---

## Next time a security release is published

1. Open https://civicrm.org/blog/tags/security-releases and read the newest post.
2. For each advisory it lists, open the advisory page and check **Affected Versions** — if our
   version is at or below the version named there, it applies to us.
3. Where the advisory offers a patch, use it. Where it does not, take the fix from CiviCRM's
   public repository — the method is written up in
   [ColoredCow/goonj-crm#382](https://github.com/ColoredCow/goonj-crm/issues/382).
4. Test, deploy, and **add a row to this file**.
