# CHANGELOG

## Version 2.26.1 (2026-03-27)

* Changed send e-mail to a contact reference so that it can send to multiple contact reference fields.

## Version 2.26.0 (2026-02-11)

* Changed send e-mail to a contact reference so that it can send to multiple contact reference fields.

## Version 2.25.0 (2026-02-11)

* [#33](https://lab.civicrm.org/extensions/emailapi/-/issues/33) Fixed Send E-mail to a contact reference fails.
* Improved configuration for the action 'Send E-mail to a contact reference'. Restrict the selection to allowed entities.
* [!78](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/78) Support mosaicomsgtpl for messagetemplate link.

## Version 2.24.0 (2025-12-03)

* [!75](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/75) Switch to user language when sending messages for proper tokens.
* [!74](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/74) Fix deprecation notice - mark $queue as nullable.

## Version 2.23.0 (2025-07-24)

* [!73](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/73) Fix sending to alternative address.
* [!72](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/72) Remove unused functions and outdated README tied to pre-token processor days.
* Move insert actions to upgrade because it causes performance issues when called via managed.

## Version 2.22.0 (2024-04-30)

* [!70](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/70) Pass additional params to the message template so that its available in hook.
* Use actions.json for actions.
* Use URL formatter function to make sure URLs are not escaped in WordPress.

## Version 2.21.0 (2025-04-04)
**Requires CiviRules 3.18.0 or higher.**

* Extract helpText and use updated civirules classes where possible.

## Version 2.20.0 (2025-03-21)

* Revert https://lab.civicrm.org/extensions/emailapi/-/commit/88ed67eec0586fc99b66a380aef44cadbe958c64 which requires unreleased CiviRules.

## Version 2.19.0 (2025-03-17)

* PHP syntax fix.
* Civix upgrade.

## Version 2.18.0 (2025-02-04)

* Add function to safely handle unserialize of rule action_params (prevents crash on create action in some situations).
* Switch some internal functions to API4.
* Extra, clearer logging.

## Version 2.17.0 (2025-01-14)

* Extract extra data when the contribution is present in the token (such as the related participant and the related membership).

## Version 2.16.0 (2024-11-08)

* Replace legacy exceptions.
* Update to EntityFrameworkV2.

## Version 2.15 (2024-09-05)

* Fixed notice in Send Email API
* Added event tokens to the form processor action
* Fixed issue in Send to contact reference field

## Version 2.14 (2024-03-09)

* PHP8.2 + Smarty3+ compatibility.

## Version 2.13

*  Fix sending a email to alternative email address. #31 See !61

## Version 2.12

* Add administer CiviRules
* CiviCRM 5.69 Compatibility

## Version 2.11

* Allow sending to a custom field's value
* Fixed minor issues

## Version 2.10

* Fixed issue with no sending of emails when `from_email` and `from_name` are not provided but `from_email_option` is provided.

## Version 2.9

* [!52](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/52) Remove deprecated preferred_mail_format
* Fix testReplaceTokens, add case tokens to test, fix passing through activity_id, case_id etc. for token replacement
* [!53](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/53) Prevent type error on send email

## Version 2.8

* [!44](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/44) Bug in from_email_option.
* [!32](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/32) Pass through ID of email Activity with mail params.
* [!34](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/34) Support contribution tokens on CiviRules 2.23+.
* [!42](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/42) From email improvements.
* [!40](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/40) Add action "Send to contact reference".
* [!39](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/39) Don't overwrite contact ID when trigger is contact-based.
* [!31](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/31) Link to the 'Edit MessageTemplate' in action description.
* [!41](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/41) Add composer's package name.
* [!46](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/46) Add code that accidently got removed to disable smarty via API param
* [!49](https://lab.civicrm.org/extensions/emailapi/-/merge_requests/49) Fix entityname processing such that ContributionRecur tokens work.

## Version 2.7

* Implemented a much simpler solution of the token processor (see #21 and !43)

## Version 2.6

* Fixed issue with contact tokens (#21)

## Version 2.5

* Removed token processor functionality and reverted to 'old' way of token replacement after too many and too long issues with tokens.

## Version 2.4

* Fixed issue with Case tokens.

## Version 2.3

* Fixed issue with Event and Participant Tokens.

## Version 2.2

* Fixed issue with Send to Related contact action.
* Fixed issue with Send to role on case action.

## Version 2.1

* Fixed #15: E-mail does not file on case
* Fixed compatibility issue with CiviRules version 2.23 and token replacements.
