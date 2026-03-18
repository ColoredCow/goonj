## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

**[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 1.3.15 (2026-02-19)

* [!16](https://lab.civicrm.org/extensions/ses/-/merge_requests/16) Fix missing 's' from variable.
* [!15](https://lab.civicrm.org/extensions/ses/-/merge_requests/15) Add detailed config docs.

## Release 1.3.14 (2025-12-03)

* [!14](https://lab.civicrm.org/extensions/ses/-/merge_requests/14) Deal with possibility of the reuturnPath key being set in the bounce account and always try to put complaint email address contacts on hold even if no verp items found.
* [!13](https://lab.civicrm.org/extensions/ses/-/merge_requests/13) Resolve #8 in similar way to #6 given that the format of the complaint email address may be a full name and email not just the email address.

## Release 1.3.13 (2025-08-29)

* Add check for raw message delivery on subscription (it needs to be disabled otherwise signature verification will fail). 
* Don't fatal error if you try to load webhook page via browser (return empty doc instead).

## Releaes 1.3.12 (2025-08-25)

* Improve docs, add extra logging on error.

## Release 1.3.11 (2025-08-12)

* [!12](https://lab.civicrm.org/extensions/ses/-/merge_requests/12) Fix SES throttling handling.

## Release 1.3.10 (2025-08-07)

* [!10](https://lab.civicrm.org/extensions/ses/-/merge_requests/10) Tweak delivery retry logic as per AWS recommendations.
* [!11](https://lab.civicrm.org/extensions/ses/-/merge_requests/11) Fix typo.

## Release 1.3.9 (2025-08-02)

* [!9](https://lab.civicrm.org/extensions/ses/-/merge_requests/9) Allow Extension to install when CiviMail Extension/Component has been disabled.

## Release 1.3.8 (2025-07-22)

* Fix menu permission (Amazon SES menu item did not appear on Standalone).

## Release 1.3.7 (2025-06-10)

* [#6](https://lab.civicrm.org/extensions/ses/-/issues/6) Fix for the fix.

## Release 1.3.6 (2025-06-10)

* [#6](https://lab.civicrm.org/extensions/ses/-/issues/6) Filter for Email Address in Webhook. If the email address contains displayname we need to remove that before matching on email in CiviCRM.

## Release 1.3.5 (2025-03-17)

* [!5](https://lab.civicrm.org/extensions/ses/-/merge_requests/5) Retry and delay delivery when SES sending rate exceeded.

## Release 1.3.4 (2025-02-26)

* [!4](https://lab.civicrm.org/extensions/ses/-/merge_requests/4) Use singleton SesClient class for performance improvements.

## Release 1.3.3 (2025-02-10)

* Improve log message when transactional email info could not be added.
* Fix email comparison for bounces. Add in testEmail context for transactional.

## Release 1.3.2 (2025-02-10)

* Add "SES" mail protocol so that you can set the bounce processing mailbox to protocol=SES. It does not actually
make any difference but it means you can select a "correct" option instead of a fake option like "IMAP".
* Improve some log messages.

## Release 1.3.1 (2025-02-07)

* Multiple fixes for transactional email bounces and bug fixes.

## Release 1.3.0 (2025-01-30)

* Record bounces for transactional mail.
* Fix sending Bcc email when sending via SES API.

## Release 1.2.8 (2024-11-01)

* Fix "Send test email" crash in CiviCRM 5.78+

## Release 1.2.7 (2024-02-09)

* Use setting_admin mixin for settings.

## Release 1.2.6 (2024-02-09)
**Do not use - use 1.2.7 instead** *There was a bug in the mgd file which caused a crash on cache clear*.

* Remove code to handle multi-domain in navigation menu managed entity. It was causing problems with classloader on some sites.

## Release 1.2.5 (2024-01-29)

* Return expected PEAR_Error on failure.

## Release 1.2.4 (2024-01-20)

* Fix CIVICRM_MAIL_LOG.

## Release 1.2.3 (2024-01-11)

* Fix PHP notice.

## Release 1.2.2 (2023-01-10)

* Fallback to mail() if SES not configured for API sending.

## Release 1.2.1 (2023-01-10)

* Fix issues with composer dependencies causing 500 error on some sites.

## Release 1.2 (2023-01-08)

* Support SES API for sending email.
