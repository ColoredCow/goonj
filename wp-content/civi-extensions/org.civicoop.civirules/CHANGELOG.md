# CHANGELOG

## Version 3.14.0 (2024-11-07)

* Fixed issue with delayed actions. See !269
* Fixed bug for delayed actions in the Check Participant Status condition
* Fixed a bug for delayed actions in Check Participant Role condition
* Increase minimum CiviCRM version to 5.68. See #240

## Version 3.13.0 (2024-10-08)

*  Add Update Contribution Status action. See !267

## Version 3.12 (2024-08-27)

* add action to cancel latest membership of a type and status, also allow for group entity
* make sure we get the latest contribution by date for membership triggers

## Version 3.11 (2024-07-30)

* #228 Fix issue with trigger Untag contact not working anymore.

## Version 3.10 (2024-07-28)

* Fix 'Recur next scheduled date' trigger was not working (it was comparing a datetime field with a date so it would never match).
* [!265](https://lab.civicrm.org/extensions/civirules/-/merge_requests/265) Avoid php errors because count()'ing a boolean.
* [!263](https://lab.civicrm.org/extensions/civirules/-/merge_requests/263) Activity Type condition: cache triggering activity type.
* [!264](https://lab.civicrm.org/extensions/civirules/-/merge_requests/264) Improve performance when firing trigger for multiple contacts.

## Version 3.9 (2024-06-25)

* Add Action: Set Contact as Deceased.
* !261 Allow to use Contribution conditions for Membership End Date (Cron) trigger.
* #230 (!262) - Fix participant status comparison to use canonical field name

## Version 3.8 (2024-05-27)

* Fix "Membership is Renewed" trigger that was not working because of an invalid field name.
* Fix field names when comparing to 'pre' data - see !258.
* Fix issue with trigger parameters being overwritten to empty array.

## Version 3.7 (2024-05-21)

* Fixed deprecation notice. See !257
* Fixed type error on CRON and delayed execution in some circumstances (#224, #227).
* Fix cron trigger execution.

## Version 3.6 (2024-04-18)

API:

* Rename API3 CiviRuleAction to CiviRulesAction to match entity name
* Rename API3 CiviRuleTrigger -> CiviRulesTrigger to match entity name
* Rename API3 CiviRuleRule to CiviRulesRule to match entity name
* Rename API3 CiviRuleRuleCondition to CiviRulesRuleCondition to match entity name
* Rename API3 CiviRuleRuleAction to CiviRulesRuleAction to match entity name
* Rename API3 CiviRuleCondition to CiviRulesCondition to match entity name
* Rename API3 CiviRuleRuleTag to CiviRulesRuleTag to match entity name

Triggers:

* Use shared triggerTrigger parent function and triggerData property
* Add triggerData property to Trigger class
* Clean up setEntityID/setContactID for triggerData and add logging
* Fix Group Contact trigger causes crash. See !247
* Fix for Fatal error: Uncaught Error: Cannot assign null to property CRM_Civirules_TriggerData_TriggerData::$entity_id of type int. See !249 and #224
* Fix 'triggerData cannot be accessed before initialisation for membership add trigger'

Other:

* Smarty 3/4 fix for Membership Status/Type condition.
* Fix crash if membership add action does not have a valid membership_type_id.

## Version 3.5 (2024-04-15)

* New action: Update Membership Status. See !245
* Fix for issue #222. Error when saving Contact

## Version 3.4

* Fixed issue #220: The condition "contact custom field changed is one of" does not work when it is a date field.
* Fix crash when contact_id is array / undefined on post trigger (eg. for Case entity).

## Version 3.3 (2024-04-06)

* [#219](https://lab.civicrm.org/extensions/civirules/-/issues/219) Add duration field support for activity actions.
* Fix [#218](https://lab.civicrm.org/extensions/civirules/-/issues/218) Edit triggering activity action fails with assignee contact error.
* Fixed deprecation notices and type errors by !237 and !239
* Fix for checking conditions with a delayed action against data in the database. See #208 and !238
* Remove some legacy code.

## Version 3.2 (2024-03-19)

* Add 'Update Recurring Contribution Status' action and make it work with Membership trigger.
* Support API4 for generic API action class.
* Migrate 'Manage Tags' to searchkit and menu items to managed entities.

## Version 3.1 (2024-03-08)

* Fix editing custom fields on certain CiviCRM versions. See !228
* Compatibility with CiviCRM 5.72.
* Fix class entity names (fixes error on delete rule).
* Replace deprecated DAO add functions with writeRecord (fixes error on disable rule).
* Don't hardcode status for 'Daily trigger for case activity' (now you can use case status condition to trigger for cases with any status).
* Fix 'Set Case Status' action when used with 'Daily case activity trigger'.
* Update permissions hook format per https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_permission/#parameters.

## Version 3.0

* Added action rebuild smart group
* Searchkit / Formbuilder UI for rules overview.
* The condition "Case has tags/does not have tags" now can be selected for cases.
* "Is Client of the Case" now checks really if the contact is a client.
* [!225](https://lab.civicrm.org/extensions/civirules/-/merge_requests/225) Display membership status label instead of name in conditions.


## Version 2.51

*  Fix preData custom field collection on CiviCRM 5.67+  See !224

## Version 2.50

* Fixed issue #206
* Fixed `xth recurring contribution` condition not appearing.
* Add `administer CiviRules` permission
* Fixed issue #202 by !223

## Version 2.49

* Fixed deprecation warning in Upgrader class.

## Version 2.48

* Fixed preventing of delayed tasks never being executed by !199
* Add action 'Add related contact to group' by !201
* Add cleanup API for rule triggers/conditions/actions (to fix duplicates) by !200
* ConfigItems: fixed when CiviRules is enabled importing a configuration set throws an error (#196)
* Add `xth recurring contribution` condition. See !204
* Performance improvement by caching the custom groups in a cached php file container.
* Performance improvement by optimizing the customPreHook
* !203 Add condition: Recurring pays for Membership
* !206 Add action: Set Custom Field on an entiy with data from another Custom Field
* Fix action disable/delete relationship when contact is on the 'b' side of the relationship
* !202 Condition: User is logged in

## Version 2.47
* issue #163: also fix for activity

## Version 2.46
* Fix action AddToCase -> error on api3 getvalue with limit => 0, changed to 1
* Add Activity action: remember send_email "no" selection with !191
* Fix Activity trigger when triggered for all contacts with !192
* Added hours and minutes option to the Activity Scheduled Date trigger (#191)
* When trigger is scheduled before the activity date/time it triggers now _after_ the trigger time is reached (See #191)
* Add `Contribution Source` and `Contribution Is Pay Later` conditions  (See !194)
* Add `Contact is (not) of Type(s)` condition (See !195)
* Add condition `xth Contribution in Last Time Interval` (see !196)
* Fix #163: retrieve custom field if needed for activity add action when delayed date in custom field is used
* Add "inclusion operator" for condition ContactHasMembership  (See !197)


## Version 2.45
* add xmlMenu function to civirules.php (mixin issue with older CiviCRM versions)
* !190: Don't crash if contact ID does not exist (eg. was deleted) and code cleanup caseaddrole

## Version 2.44

* Fixes #189: action on activity AddToCase ignores deleted cases
* !180 Replace deprecated function getTimeRaw().
* Fixed #177: "Event reached date" trigger fires prematurely when setting a "before" offset.
* Fixed #178: "Event reached date" trigger fires daily on the same participants.
* Implemented integration with config item extension. For importing and exporting civirules configuration.
* Fix !178: Api4 tag remove/add action for non-admins.
* !179: Add an Activity Subject condition.
* !181: Show label for 'Field value comparison' description. Add description to SetCustomField action.
* !182: Support multiple membership types for "Contact has active membership of type".
* Fix #182 by !183: Domain conditional selection shows a maximum of 25 domains.
* Fix #183 by !184: Only show active/visible groups in group conditions / actions.
* Changed date format for comparison value to YmdHis to fix #186 by !185
* Always use ymd format for dates in field value comparisons  by !186
* #188 activity action: condition target/assignee on relationship contacts by !187
* !188 For CRON date triggers don't trigger on deleted(is_deleted=1) or disabled (is_active=0) cases,activities,events.

## Version 2.43

* Add option to negate the "Contact has Active Membership of Type" condition
* Allow 'any' event type and 'before/after X hours' for 'Event date reached' trigger
* Fix error where original activity data is incomplete after alterPreData - fixes #176

## Version 2.42

* Added action register participant, fixes #169
* Avoid duplicate Contact Subtypes by !164
* Fix ContactHasMembership condition !163
* Fix display of group type label instead of group type ID !165
* Escape if simple change of Membership State, not a Renewal !156
* Fix cron triggers based on event date !168
* Added action Create Case Activity

## Version 2.41

* Improved ContactHasMembership condition adding fields start_date, join_date, end_date to be part as condition's filters. By !159
* Set the custom value on the correct entity by !160
* Fixed activity Scheduled Date triggers for incorrect set of contacts with !161
* Added condition on recurring contribution frequency, fixes #168.


## Version 2.40

* Added operators 'Matches regular expression' and 'Does not match regular expression' to Field Value condition.
* Fix 'Contact has recurring contribution' condition
* Fixed issue with contribution recur cron trigger: added ID to the trigger data.

## Version 2.39

* Added configuration to the event trigger to use the logged in user as the contact for the trigger.

## Version 2.38
* Add cron trigger for next scheduled contribution date on recurring contribution, fixes #162

## Version 2.37

* Add an option to set activity details for 'add activity to contact' action
* Fixed #157: Base delay on date field in trigger: Field select doesn't filter by data type and excludes some valid fields by !153
* Fixed #158: add condition contact has been in group since
* Merged https://lab.civicrm.org/extensions/civirules/-/merge_requests/151 (code cleanup)

## Version 2.36

* Fixed issue with CiviRules and deleting entities.
* Added function to retrieve the operation (create, edit, delete) from the post trigger.

## Version 2.35

* Fixed #152 - Action "End Relationship" added
* CiviRules, field value comparison will fail when checking a value of '0' in custom field by !149

## Version 2.34

* Fixed #151: Activity is Deleted trigger does not work
* Added condition 'Recurring Contribution is (not) financial type'.
* Handle contact subtypes when retrieving preData/originalData for Contacts (affects customdata) by !148

## Version 2.33

* Added conditions for relationship has ended / is activated (issue https://lab.civicrm.org/extensions/civirules/-/issues/150)

## Version 2.32

* Added Trigger which is fired when an activity is tagged and for all related participants of the related event (when the activity is connected to an event).

## Version 2.31

* Fixed issue with Condition Activity Has Tag.
* Added configuration options to Create Activity from Event Action.

## Version 2.30

* Fixed typos with action Create Activity from Event.

## Version 2.29

* Add recent triggers to rule detail view by !134
* Added action to create activity from an event
* Added trigger which is fired when an activity is changed for all related participants of the related event (when the activity is connected to an event).

## Version 2.28

* improve update warning/error message (#147) by !147
* Fixed #149. Contact is tagged trigger and condition tag is works again.
* Fixed issue with removing tags at the action: Synchronize Tags with related contact

## Version 2.27

* Fixed issue !144. Contact is tagged and is untagged works again.
* Fixed issue #145. Contact has tag not saved. By !146
* Added action: Add Target Contact to activity so that additional contacts could be added to the activity.
* Added action: Synchronize Tags with related contact

## Version 2.26

* Fix Issue With Class Not Found Error by !145
* Added action to set a custom field with data from another custom field.

## Version 2.25

* Fix 139: retrieve campaign data because entity data is not empty for type and status conditions. Add generic condition class for campaign.
* Added action Create Pending Group Subscription #141

## Version 2.24

**CiviCRM version compatibility** >= 5.28.0

* Removed unit tests.
* Fix #132: GroupContact entity missing from Tag Conditions and Actions by !142
* Added comments in the code and error handling in `civirules.php`.
* Added action to update custom fields on a case. Needed to test #96.
* Fixed #96: Custom Data on case changed trigger also works with a condition field is changed. Requires `hook_civicrm_customPre`. Available since CiviCRM version 5.28
* Added condition Contact Custom Field Changed. Need to test #133.
* Fixed #133: Contact Custom Data changed trigger works now with pre data. Requires `hook_civicrm_customPre`. Available since CiviCRM version 5.28.
* Fixed #134: Made the GroupContact trigger compatible with all signatures.
* Fixed #135: When custom data on a contact changes then also fire the contact is changed trigger.
* Fixed regression bug from !96 and refactored cleaned up the code.
* Fixed activity trigger configuration. The activity assignee and activity target values weren't set correctly.
* Fixed #128: by adding a generic set custom field action. Based on !68
* Fixed #43: Conditions and action who check for Contact also work Individual, Household or Organization Changed triggers.
* Fix #116: Introduce actions Add Tag to / Remove Tag from Activity/Case/Contact/File + add method to check if API4 active in CRM_Civirules_Utils + add conditions Contact Has Tag, Case Has Tag, Activity Has Tag and File Has Tag
  (please note: when using action Add Tag to Activity the tags will be removed immediately because of a core issue! Check )
* Restore sending emails to trigger contact in Action Assign and email !144

## Version 2.23

* Fixed #116: Problems with HasTag Condition and Add/Remove Tag Actions by !120
* Fix HasTag Condition and Tag Added/Removed Actions for the Contact Tagged/Untagged Triggers with !123 fixes partially #120
* Fixed #126: Fatal Error in Has Tag Condition by !132
* Added `getEntity` to `TriggerData` base class !122 fixes partially #121
* Code cleanup !126
* Fix #124 Cannot use object of type CRM_Core_DAO_EntityTag  by !127
* Fix #120 error 500 with newer PHP versions  by !130
* Fix #123 Some triggers don't fire anymore (delete triggers, GroupContact create trigger)
* Fix #125 Set contact_id for membership edit trigger. Also fix inconsistencies with capitals/lowercase of entity name
* Fix #108 Hook function for `hook_civirules_alter_trigger_data` is never called by !99
* Extend 'Assign & Email' activity functionallity by !133
* Fix #129 trigger for Individual, Organization or Household by !135
* Fix #130 and simplify permissions by !136
* Fix routing after edit rule action 'Assign and email' not correct by !137
* Fix #64 Update Participant StatusChanged to generic, enable multiselect  by !138


## Version 2.22

* Fixed #112: call to undefined getObjectName() for HasTag condition when used with CRON trigger by !116
* Make sure entityID and contactID is always set when a rule is executed !117
* Fixed #119: DeDupe rules fail on Version 2.21+ by !119

## Version 2.21

* HasTag API4 compatibility with 5.28 (!110)
* Code cleanup engine.php (!111)
* Add debug flag to rule and log condition validation if enabled (!112)
* Retrieve campaign data if data not complete from getEntityData (!113)

## Version 2.20

* Activity Scheduled date trigger: Don't trigger for deleted activities !90
* Rename Case status condition class so it works !79
* Resolved #90 by passing through a list of named parameters
* Add 'is NOT one of' operator to 'Membership Type' condition and fix form validation on rule save (!66)
* Refactor 'contact in group' condition to not reference the `id` column in the group contact cache table !93
* Optimize trigger condition evaluation !94
* Refactored the menu code, making it resistant against order extension menu code that can make this menu disappear.
* Add pre/post eventID tracking to map original/current values for 'Old value/New value' comparisons - requires CiviCRM 5.34: [CiviCRM#19209](https://github.com/civicrm/civicrm-core/pull/19209).
* Added action to update a date value (!100)
* Added condition to compare a date value (!100)
* Add human readable summary of Update Date Value action (!101)
* Renamed condition "Contact does (not) have tag" to "Entity Has\Does (Not) Have Tag(s)". And made this work with entities that support tags in core: Contact, Activity, Case, File (!103)
* Added condition Contact has not an active membership of type.

## Version 2.19

* Add trigger for relationship start date !86
* Add trigger for relationship end date !88
* Added action to remove subtypes of a contact !88

## Version 2.18

* Add ability to send activity email for Add/Edit Activity Actions (!85)
* Fix calls to wrong API function in "UpdateNumericValue.php" (!84)
* Fix activityScheduledDate trigger so it does not re-trigger every day (!83)
* Ability to modify the activity from a rule triggered by an Activity trigger (!82)
* Add condition case custom field changed is one of (!96)

## Version 2.17

* Add Scheduled Reminder log trigger and conditions (!61)
* Added action to set a sepcific custom field. (!64)
* Added action to assign activity and send an e-mail (!72)
* Added action to update/set a date value (!73)
* Fixed condition Last Contribution (#86)
* Add Activity Scheduled Date Cron trigger (!77)
* Allow to trigger on case activities or non-case activities and filter by record type (!80)

## Version 2.16

* Show createdby/date in the list if rule has not been modified since it was created
* Improved error handling.
* Fixed #82 - Participant Role condition saves now the value instead of the id
* Fix generic status comparison to work with Campaign and other entities

## Version 2.15

* Fixed issue with participant status comparison.
* Fixed issue with campaign status comparison. (issue 79)
* Fixed issue with contact subtype in combination with other triggers than contact related (issue 80)
* Fixed issue with Field Value Comparison and comparing custom fields.

## Version 2.14.1

* Fixed regression bug in cron triggers.

## Version 2.14

* Fix Field Value Comparision
* Add trigger "Membership is Renewed" that is triggered after a membership is renewed (End date is increased by one term).
* Add 'Save and Done' button to a rule.
* Display Modified date/by instead of Created date/by in list of rules (you can still see Created when editing the rule).
* Change cancel button to close and always redirect to rules list.
* Membership End Date trigger:
  * Allows you to select multiple membership types.
  * Provide ContributionRecur entity so conditions based on the linked recurring contribution can be used.
* Make status condition generic, support matching multiple statuses and add support for the ContributionRecur entity.
* Add ContributionRecur status changed condition and make the parent class more generic to support more entities.
* 'Recurring Payment Processor is' changes:
  * Now works with Contribution entity.
  * Now works for test payment processors too.
* Fixed issue with setting original data on the edit field value comparison screen.
* Add condition "Contact has recurring contribution(s) with status" which checks if the contact has any recurring contributions with a specific status.
* Added logging of the triggered entity


## Version 2.13

* Fixed #58: added locking mechanism to cron triggers to prevent that a rule gets fired again whilst it is running.


## Version 2.12

* Added operator 'Does not contain string' to Field Value condition.
* Make sure we trigger rules after transaction has completed (see also issue #21)
* Added "No Bulk Mail" (is_opt_out) to privacy options action
* Fixed issue #61 (add condition campaign status is (not) one of)
* Added contribution soft credit trigger
* Added condition: "Soft Credit Type is (not) one of"
* Added condition: "Contact added by Contact (not) in Group(s)"
* Added action: "Update Numeric Value"
* Also check smartgroups for 'Contact in group' condition
* Fixed issue #67 ("Event Type is" condition doesn't work)
* Add condition "Recurring Contribution Payment Processor is" that checks which payment processor is linked to the recur
* Add condition "Compare old Membership Status to new Membership Status".
* Add help to rule conditions and update ContributionRecur has payment processor condition
* Fix crash on FieldValueComparison if not saved properly
* Membership Type condition changed so we can check for multiple types
* Added condition 'Membership End Date Changed'

## Version 2.11

* Added action to create relationships.
* Added action to create a membership
* Added action to set financial type of a contribution
* Added condition to check whether a contribution is a recurring contribution
* fixed issue #53 (https://lab.civicrm.org/extensions/civirules/issues/53)
* fixed issue #46 (the is empty condition on the field value comparison is broken)
* fixed issue #59 (added triggers for campaign and condition campaign type)

## Version 2.10

* Added clone butten to the edit rule screen, so you can copy and change only what needs changing (#29)
* Added configuration for the record type for Activity and Case Activity trigger.
* Fixed bug in Activity and Case Activity trigger with an empty contact id.
* Added action to set Case Role
* Added trigger for new UFMatch record (link with CMS user is added)
* Removed the Case Added trigger as it causes errors (check https://lab.civicrm.org/extensions/civirules/issues/45). To do stuff when a new case is added use the Case Activity Added trigger instead with activity type Open Case. During the upgrade all existing rules based on the Case Added trigger will be deleted! They need to be recreated manually with the Case Activity is added trigger with activity type Open Case.
* Added condition Compare old participant status to new participant status

## Version 2.9

* Adds new action: update participant status
* Refactored the way triggers, actions and conditions are inserted in the database upon installation (#24).
* Fixed the fatal error after copying a profile (#19).
* Fixed php warning in CRM_CivirulesConditions_Contact_AgeComparison
* Fixed Cancel button on Rule form returns to "random" page (now it returns to rule overview)
* Fixed uncorrect behavior of isConditionValid with empty value (now returns FALSE)
* Fixed issue 40 (https://lab.civicrm.org/extensions/civirules/issues/40) where the fresh install SQL scripts still create tables with CONSTRAINT ON DELETE RESTRICT rather than ON DELETE CASCADE. There is an upgrade action (2025) linked to this fix which will remove the current constraints on tables civirule_rule_action, civirule_rule_condition and civirule_rule_tag and replace them with CONSTRAINT ON DELETE CASCADE and ON UPDATE RESTRICT.
* Introduces the option to take child groups into consideration for the condition 'contact is (not) in group'.

## Version 2.8
* "Set Thank You Date for a Contribution" action now supports options for time as well as date.
* Added trigger for Event Date reached.
* Added option to compare with original value in Field Value Comparison condition
* Add a condition for contacts being within a specific domain. This is useful for multisite installations as it allows rules to only be executed on contacts that are within that domain's domain_group_id

## Version 2.7
* Changed the ON DELETE NO ACTION to ON DELETE CASCADE for the constraints for tables civirule_rule_action, civirule_rule_condition, civirule_rule_tag which fixes #8
* Fixed notices and warnings on isRuleOnQueue method
* Add "show disabled rules" checkbox on filter for Manage Rules

## Version 2.6
REQUIRES MENU REBUILD! (/civicrm/clearcache)

* Added a trigger for membership end date
* Replaced the Find Rules custom search with a Manage Rules form
