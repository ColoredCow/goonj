# Dalgo Integration - Entity Classification

This document classifies CiviCRM entities in the Goonj CRM as **native** (built-in CiviCRM with APIv3/v4) or **custom** (defined by extensions via the ECK framework), to support integration with Dalgo.

## Architecture Overview

The Goonj CRM uses two mechanisms to model its domain entities:

1. **Native CiviCRM entities** - built-in entities accessed via `\Civi\Api4\{EntityName}` (e.g., `Event`, `Activity`, `Contact`, `Contribution`)
2. **ECK (Entity Construction Kit) entities** - custom entities created via the `de.systopia.eck` extension, accessed via API entity name `Eck_{EntityTypeName}`. All ECK entities are automatically API-exposed.

ECK entities share base fields defined in `CRM/Eck/DAO/Entity.php`: `id`, `title`, `subtype`, `created_id`, `modified_id`, `created_date`, `modified_date`.

## Summary Table

| # | Entity Name | Classification | API Entity Name | `created_date` | `modified_date` |
|---|-------------|---------------|----------------|----------------|-----------------|
| 1 | Collection Camp | Custom (ECK) | `Eck_Collection_Camp` | Yes | Yes |
| 2 | Dropping Center | Custom (ECK) | `Eck_Collection_Camp` | Yes | Yes |
| 3 | Events | Native | `Event` | Yes | No |
| 4 | Activities | Native | `Activity` | Yes | Yes |
| 5 | Volunteer Info | Native (Contact subtype) | `Contact` | Yes | Yes |
| 6 | GCOC | Mixed | `Eck_Institution_Visit`, `Contribution`, `Activity` | Varies | Varies |
| 7 | Mailing Info | Native | `Mailing` | Yes | Yes |
| 8 | Dispatch | Custom (ECK) | `Eck_Collection_Source_Vehicle_Dispatch` | Yes | Yes |
| 9 | Dispatch Ack | Custom field group on Dispatch | `Eck_Collection_Source_Vehicle_Dispatch` | Inherited | Inherited |
| 10 | Logistics | Custom field group on Collection Camp | `Eck_Collection_Camp` | Inherited | Inherited |
| 11 | Outcome | Custom field group on Collection Camp | `Eck_Collection_Camp` | Inherited | Inherited |
| 12 | Feedback | Custom (ECK) | `Eck_Collection_Source_Feedback` | Yes | Yes |

## Detailed Classification

### 1. Collection Camp (Individual/Institution)

- **Type:** Custom (ECK entity)
- **ECK Entity Type:** `Collection_Camp`
- **ECK Subtypes:** `Collection_Camp` (Individual), `Institution_Collection_Camp` (Institution)
- **API call:** `Eck_Collection_Camp.get` with `subtype:name = 'Collection_Camp'` or `'Institution_Collection_Camp'`
- **DB table:** `civicrm_eck_collection_camp`
- **Custom field groups:** `Collection_Camp_Core_Details`, `Collection_Camp_Intent_Details`, `Collection_Camp_QR_Code`, `Logistics_Coordination`, `Camp_Outcome`, `Volunteer_Camp_Feedback`
- **`created_date`:** Yes
- **`modified_date`:** Yes

Both Individual and Institution variants are subtypes of the same ECK entity type `Collection_Camp`.

### 2. Dropping Center (Individual/Institution)

- **Type:** Custom (ECK entity - subtype of `Collection_Camp`)
- **ECK Entity Type:** `Collection_Camp` (same entity type as Collection Camp)
- **ECK Subtypes:** `Dropping_Center` (Individual), `Institution_Dropping_Center` (Institution)
- **API call:** `Eck_Collection_Camp.get` with `subtype:name = 'Dropping_Center'` or `'Institution_Dropping_Center'`
- **DB table:** `civicrm_eck_collection_camp` (same table as Collection Camp)
- **Custom field groups:** `Dropping_Centre`, `Collection_Camp_Core_Details`, `Collection_Camp_QR_Code`
- **Companion entity:** `Eck_Dropping_Center_Meta` for monthly status tracking
- **`created_date`:** Yes
- **`modified_date`:** Yes

### 3. Events

- **Type:** Native CiviCRM
- **API call:** `Event.get`
- **DB table:** `civicrm_event`
- **`created_date`:** Yes
- **`modified_date`:** No (CiviCRM Events lack a native `modified_date`)

Used for Goonj-initiated events (Rural Planned Visits, Book Fairs, Chaupals, etc.) with event type codes defined in `config/eventCode.php`.

### 4. Activities

- **Type:** Native CiviCRM
- **API call:** `Activity.get`
- **DB table:** `civicrm_activity`
- **`created_date`:** Yes
- **`modified_date`:** Yes

Used for Induction, Volunteering, and Material Contribution tracking. Note: there is also a separate custom ECK entity `Eck_Collection_Camp_Activity` for camp-specific activity records.

### 5. Volunteer Information

- **Type:** Native CiviCRM (Contact with subtype)
- **API call:** `Contact.get` with `contact_sub_type:name = 'Volunteer'`
- **DB table:** `civicrm_contact`
- **Custom field group:** `Volunteer_fields`
- **`created_date`:** Yes
- **`modified_date`:** Yes

Volunteer status (registered, inducted, active, inactive) is tracked via custom fields and Induction activity status in `InductionService.php`.

### 6. GCOC (Material, Monetary, Visit tracking)

GCOC is a business concept spanning multiple entities, not a single entity.

| Sub-entity | Type | API call | `created_date` | `modified_date` |
|------------|------|----------|----------------|-----------------|
| Visit tracking | Custom (ECK) | `Eck_Institution_Visit.get` with `subtype:name = 'Urban_Visit'` | Yes | Yes |
| Material contribution | Native | `Activity.get` (with material contribution activity type) | Yes | Yes |
| Monetary contribution | Native | `Contribution.get` | No (`receive_date` instead) | No |

Event code mapping: `'Urban Visit' => 'GCOC'` in `config/eventCode.php`.

### 7. Mailing Information

- **Type:** Native CiviCRM
- **API call:** `Mailing.get`
- **DB table:** `civicrm_mailing`
- **`created_date`:** Yes
- **`modified_date`:** Yes

Note: The codebase primarily uses `\CRM_Utils_Mail::send()` for transactional emails (logistics notifications, feedback reminders), which is separate from the CiviMail `Mailing` entity.

### 8. Dispatch

- **Type:** Custom (ECK entity)
- **ECK Entity Type:** `Collection_Source_Vehicle_Dispatch`
- **API call:** `Eck_Collection_Source_Vehicle_Dispatch.get`
- **DB table:** `civicrm_eck_collection_source_vehicle_dispatch`
- **Custom field group:** `Camp_Vehicle_Dispatch` (Collection Camp, Date/Time, Bags, Weight, Vehicle Category, PU Center, etc.)
- **`created_date`:** Yes
- **`modified_date`:** Yes

### 9. Dispatch Acknowledgement

- **Type:** Not a separate entity - custom field group on the Dispatch entity
- **API call:** `Eck_Collection_Source_Vehicle_Dispatch.get` (same as Dispatch, reading `Acknowledgement_For_Logistics.*` fields)
- **Custom field group:** `Acknowledgement_For_Logistics` (fields: `No_of_bags_received_at_PU_Office`, `Ack_Email_Sent`, `Verified_By`)
- **`created_date`:** Inherited from parent Dispatch entity
- **`modified_date`:** Inherited from parent Dispatch entity

### 10. Logistics

- **Type:** Not a separate entity - custom field group on `Collection_Camp`
- **API call:** `Eck_Collection_Camp.get` (reading `Logistics_Coordination.*` fields)
- **Custom field group:** `Logistics_Coordination` (fields: `Camp_to_be_attended_by`, `Email_Sent`, `Self_Managed_By_Camp_Organiser`, `Feedback_Email_Sent`)
- **`created_date`:** Inherited from parent Collection Camp entity
- **`modified_date`:** Inherited from parent Collection Camp entity

### 11. Outcome

- **Type:** Not a separate entity - custom field group on `Collection_Camp`
- **API call:** `Eck_Collection_Camp.get` (reading `Camp_Outcome.*` fields)
- **Custom field group:** `Camp_Outcome` (fields: `Rate_the_camp`, `Camp_Status_Completion_Date`, `Filled_By`, `Last_Reminder_Sent`)
- **`created_date`:** Inherited from parent Collection Camp entity
- **`modified_date`:** Inherited from parent Collection Camp entity

### 12. Feedback

- **Type:** Custom (ECK entity)
- **ECK Entity Type:** `Collection_Source_Feedback`
- **API call:** `Eck_Collection_Source_Feedback.get`
- **DB table:** `civicrm_eck_collection_source_feedback`
- **Custom field group:** `Collection_Source_Feedback` (camp code, address, ratings, difficulties, images)
- **`created_date`:** Yes
- **`modified_date`:** Yes

## All ECK Entity Types

| ECK Entity Type | API Entity Name | DB Table |
|----------------|----------------|----------|
| `Collection_Camp` | `Eck_Collection_Camp` | `civicrm_eck_collection_camp` |
| `Collection_Camp_Activity` | `Eck_Collection_Camp_Activity` | `civicrm_eck_collection_camp_activity` |
| `Collection_Source_Feedback` | `Eck_Collection_Source_Feedback` | `civicrm_eck_collection_source_feedback` |
| `Collection_Source_Vehicle_Dispatch` | `Eck_Collection_Source_Vehicle_Dispatch` | `civicrm_eck_collection_source_vehicle_dispatch` |
| `Dropping_Center_Meta` | `Eck_Dropping_Center_Meta` | `civicrm_eck_dropping_center_meta` |
| `Institution_Visit` | `Eck_Institution_Visit` | `civicrm_eck_institution_visit` |

## All Collection_Camp Subtypes

| Subtype | Description |
|---------|-------------|
| `Collection_Camp` | Individual Collection Camp |
| `Dropping_Center` | Individual Dropping Center |
| `Institution_Collection_Camp` | Institutional Collection Camp |
| `Institution_Dropping_Center` | Institutional Dropping Center |
| `Goonj_Activities` | Individual Goonj Activities |
| `Institution_Goonj_Activities` | Institutional Goonj Activities |

## Verification Commands

```bash
# List all registered ECK entity types
cv api4 EckEntityType.get +s name

# Confirm API exposure and list all fields for an ECK entity
cv api4 Eck_<EntityType>.getFields

# Example: List Collection Camp fields
cv api4 Eck_Collection_Camp.getFields

# Example: Get Collection Camps with subtype filter
cv api4 Eck_Collection_Camp.get +w subtype:name=Collection_Camp +l 5
```
