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

Two releases have been backported so far:

| Security release | Published | Advisories taken |
|---|---|---|
| [CiviCRM Security Release (6.15.3, 6.10-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6153-610-esr) | 2026-06-17 | CIVI-SA-2026-18 … 34 (17) |
| [CiviCRM Security Release (6.17.2, 6.16.5, 6.10-ESR)](https://civicrm.org/blog/dev-team/civicrm-security-release-6172-6165-610-esr) | 2026-08-05 | CIVI-SA-2026-35, 36, 37 (3) |

Applied in [ColoredCow/goonj PR #1762](https://github.com/ColoredCow/goonj/pull/1762).
Tracking issue: [ColoredCow/goonj-crm#369](https://github.com/ColoredCow/goonj-crm/issues/369).

---

## Status: all 20 advisories closed

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

---

## When these backports become redundant

**All of them go away the moment CiviCRM is upgraded to 6.17.2 or later**, because every one
of these fixes is already in that release. Nothing here needs to be carried forward past
that upgrade.

But there is a catch that must not be forgotten:

> A WordPress CiviCRM upgrade **replaces the entire `wp-content/plugins/civicrm` directory**.
> That silently deletes all 86 backported files. Nothing warns you.

So at the next upgrade (planned for **November 2026**):

1. Confirm the target version is **6.17.2 or higher** — anything lower reopens some of these advisories.
2. Do **not** re-apply anything. The upgrade already contains all of it.
3. Add a line at the top of the advisory tables below recording that they are now superseded —
   which version we upgraded to and on what date. **Do not delete the tables.** They are the
   record of what this install was carrying, and they are needed if anyone ever has to audit
   the period before the upgrade.
4. Remove the manual `vendor/phlib` and autoloader entries noted at the end of this file — the
   upgrade brings its own copies, so ours are no longer needed.

If for any reason the upgrade lands on a version **below 6.17.2**, the backports have to be
re-applied. In that case, re-read the two release posts linked above.

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
