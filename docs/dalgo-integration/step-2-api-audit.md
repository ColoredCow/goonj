# Dalgo Integration — Step 2: API Audit

This document captures the results of testing CiviCRM's REST API on the staging environment (`https://staging-crm.goonj.org`) for all entities identified in Step 1.

## REST API Access

### Endpoint

```
POST https://staging-crm.goonj.org/civicrm/ajax/api4/{Entity}/{action}
```

Required headers:
```
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

### Authentication

- **`authx` extension is active.** Both `Authorization: Bearer <api_key>` and `X-Civi-Auth: Bearer <api_key>` return `HTTP 401 Invalid credential` with a bad key (confirms the auth mechanism works — just needs a valid key).
- **Without auth:** ECK entity base fields and Event records are accessible. Contact, Contribution, and custom fields require authentication.
- **For Dalgo's Airbyte connector:** Generate an API key for an admin CiviCRM contact. The connector will use `Authorization: Bearer <api_key>` on every request.

### API Key Generation (run on staging server)

```bash
# Find admin contact ID
cv api4 Contact.get +w 'email=<admin-email>' +s id

# Set an API key
cv api4 Contact.update +w 'id=<contact-id>' +v 'api_key=<random-secret>'
```

---

## Entity API Audit

### 1. Collection Camp (Individual / Institution)

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Collection_Camp.get` |
| **Subtype filter** | `where: [["subtype:name","=","Collection_Camp"]]` (Individual) or `"Institution_Collection_Camp"` (Institution) |
| **Base fields** | `id`, `title`, `subtype`, `created_id`, `modified_id`, `created_date`, `modified_date` |
| **Custom field groups** | `Collection_Camp_Intent_Details` (26 fields), `Collection_Camp_QR_Code` (2), `Collection_Camp_Core_Details` (6), `Logistics_Coordination` (14), `Camp_Outcome` (20), `Volunteer_Camp_Feedback` (11), `Dropping_Centre` (19), `Dropping_Center_Outcome` (5), `Institution_Dropping_Center_Intent` (16), `Institution_Collection_Camp_Intent` (18), `Institution_collection_camp_Review` (11), `Institution_Collection_Camp_Logistics` (11), `Institution_Dropping_Center_Review` (5), `Institution_Dropping_Center_Logistics` (2), `Goonj_Activities` (24), `Goonj_Activities_Outcome` (12), `Institution_Goonj_Activities` (23), `Institution_Goonj_Activities_Outcome` (12), `Institution_Goonj_Activities_Logistics` (5), `Core_Contribution_Details` (5) |
| **Incremental sync** | `created_date`: Yes, `modified_date`: Yes. Sorting by `modified_date` confirmed working. |
| **Pagination** | `limit` + `offset` confirmed working |
| **PII fields to exclude** | `Collection_Camp_Intent_Details.Name`, `Collection_Camp_Intent_Details.Contact_Number`, `Dropping_Centre.Name`, `Dropping_Centre.Contact_Number` |
| **FK fields** | `Collection_Camp_Core_Details.Contact_Id` → Contact ID of the organizer |
| **Notes** | All subtypes (Collection_Camp, Dropping_Center, Institution_Collection_Camp, Institution_Dropping_Center, Goonj_Activities, Institution_Goonj_Activities) share this single ECK entity type. Filter by `subtype:name`. |

### 2. Dropping Center (Individual / Institution)

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Collection_Camp.get` (same entity as Collection Camp) |
| **Subtype filter** | `where: [["subtype:name","=","Dropping_Center"]]` or `"Institution_Dropping_Center"` |
| **Custom field groups** | `Dropping_Centre` (main fields), `Dropping_Center_Outcome`, `Institution_Dropping_Center_Intent`, `Institution_Dropping_Center_Review`, `Institution_Dropping_Center_Logistics` |
| **Companion entity** | `Eck_Dropping_Center_Meta` for monthly status/visit/donation tracking |
| **Incremental sync** | Yes (`created_date`, `modified_date`) |
| **Pagination** | Yes |

### 3. Dropping Center Meta

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Dropping_Center_Meta.get` |
| **Custom field groups** | `Dropping_Center_Meta` (FK fields), `Visit` (date, visited_by, feedback), `Donation` (box numbers, amounts, dates), `Logistics` (materials, dates), `Status` (status, closing reason, dates) |
| **FK fields** | `Dropping_Center_Meta.Dropping_Center` → `Eck_Collection_Camp.id` (Individual DC), `Dropping_Center_Meta.Institution_Dropping_Center` → `Eck_Collection_Camp.id` (Institution DC) |
| **Incremental sync** | Yes |
| **Pagination** | Yes |

### 4. Dispatch

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Collection_Source_Vehicle_Dispatch.get` |
| **Custom field groups** | `Camp_Vehicle_Dispatch` (27 fields), `Acknowledgement_For_Logistics` (7 fields — Dispatch Ack), `Camp_Institution_Data` (4 fields) |
| **FK fields** | `Camp_Vehicle_Dispatch.Collection_Camp` → `Eck_Collection_Camp.id`, `Camp_Vehicle_Dispatch.Dropping_Center` → `Eck_Collection_Camp.id`, `Camp_Vehicle_Dispatch.Institution_Collection_Camp` → `Eck_Collection_Camp.id`, `Camp_Vehicle_Dispatch.Institution_Dropping_Center` → `Eck_Collection_Camp.id`, `Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent` → Processing Unit (OptionValue) |
| **Incremental sync** | Yes |
| **Pagination** | Yes |
| **Notes** | Dispatch Acknowledgement is NOT a separate entity — it's the `Acknowledgement_For_Logistics` custom field group on this same entity. |

### 5. Feedback

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Collection_Source_Feedback.get` |
| **Custom field group** | `Collection_Source_Feedback` (12 fields: camp code, address, ratings, difficulties, images) |
| **FK fields** | `Collection_Source_Feedback.Collection_Camp_Code` → `Eck_Collection_Camp.id`, `Collection_Source_Feedback.Filled_By` → `Contact.id` |
| **Incremental sync** | Yes |
| **Pagination** | Yes |
| **Record count (staging)** | 24 |

### 6. Institution Visit (GCOC)

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Institution_Visit.get` |
| **Subtype filter** | `where: [["subtype:name","=","Urban_Visit"]]` |
| **Custom field groups** | `Urban_Planned_Visit` (38 fields), `Visit_Feedback` (6 fields) |
| **FK fields** | `Urban_Planned_Visit.Institution` → `Contact.id` (Organization), `Urban_Planned_Visit.Select_Individual` → `Contact.id`, `Urban_Planned_Visit.Institution_POC` → `Contact.id`, `Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_` → OptionValue |
| **Incremental sync** | Yes |
| **Pagination** | Yes |
| **Record count (staging)** | 35 |

### 7. Camp Activity

| Check | Result |
|-------|--------|
| **API endpoint** | `Eck_Collection_Camp_Activity.get` |
| **Custom field group** | `Collection_Camp_Activity` (10 fields) |
| **FK fields** | `Collection_Camp_Activity.Collection_Camp_Id` → `Eck_Collection_Camp.id`, `Collection_Camp_Activity.Organizing_Person` → `Contact.id`, `Collection_Camp_Activity.Attending_Goonj_PoC` → `Contact.id` |
| **Incremental sync** | Yes |
| **Pagination** | Yes |

### 8. Event

| Check | Result |
|-------|--------|
| **API endpoint** | `Event.get` |
| **Base fields** | 70+ native CiviCRM fields (see full list below) |
| **Custom field groups** | `Goonj_Events` (5 fields), `Goonj_Events_Outcome` (16 fields), `Event_QR` (2), `Goonj_Events_Feedback` (2), `Rural_Planned_Visit` (10), `Rural_Planned_Visit_Outcome` (13), `Rural_Planned_Visit_Logistics` (1) |
| **Incremental sync** | `created_date`: Yes. **`modified_date`: NO — CiviCRM Events lack a native `modified_date`.** |
| **Pagination** | Yes |
| **PII concern** | None on Event itself (PII is on Participant records) |
| **BLOCKER** | No `modified_date` means incremental sync requires a workaround. Options: (1) Add a `hook_civicrm_post` to stamp a custom `modified_date` field, (2) Use full-refresh sync for Events only, (3) Track changes via Activity log. |

### 9. Activity

| Check | Result |
|-------|--------|
| **API endpoint** | `Activity.get` |
| **Base fields** | `id`, `source_record_id`, `activity_type_id`, `subject`, `activity_date_time`, `status_id`, `created_date`, `modified_date`, etc. |
| **Custom field groups** | `Induction_Fields` (6), `Material_Contribution` (17), `Office_Visit` (10), `Volunteering_Activity` (5), `Coordinator` (2), `Institution_Material_Contribution` (12), `Event_Feedbacks` (6), `Rural_Planned_Visit_Feedback` (9), `Events_Feedback` (5), `Meeting_Activity` (11) |
| **FK fields** | `Material_Contribution.Collection_Camp` → `Eck_Collection_Camp.id`, `Material_Contribution.Dropping_Center` → `Eck_Collection_Camp.id`, `Material_Contribution.Event` → `Event.id`, `Volunteering_Activity.Collection_Camp` → `Eck_Collection_Camp.id`, `Volunteering_Activity.Urban_Planned_Visit` → `Eck_Institution_Visit.id`, `source_contact_id` → `Contact.id`, `target_contact_id` → `Contact.id[]`, `assignee_contact_id` → `Contact.id[]` |
| **Incremental sync** | Yes (`created_date`, `modified_date`) |
| **Pagination** | Yes |
| **Notes** | Activities are polymorphic — filter by `activity_type_id:name` to get specific types (e.g., `Material Contribution`, `Induction`, `Volunteering Activity`). Requires auth. |

### 10. Contact (Volunteers)

| Check | Result |
|-------|--------|
| **API endpoint** | `Contact.get` |
| **Subtype filter** | `where: [["contact_sub_type","CONTAINS","Volunteer"]]` |
| **Base fields** | `id`, `display_name`, `contact_type`, `contact_sub_type`, `first_name`, `last_name`, `created_date`, `modified_date`, etc. |
| **Custom field groups** | `Individual_fields` (15), `Contact_QR_Code` (1), `Source_Tracking` (1), `Review` (8), `Volunteer_fields` (2+) |
| **Incremental sync** | Yes (`created_date`, `modified_date`) |
| **Pagination** | Yes |
| **PII fields to EXCLUDE** | `email` (via Email entity), `phone` (via Phone entity), `street_address` (via Address entity), `first_name`, `last_name` — select only: `id`, `display_name`, `contact_sub_type`, `Volunteer_fields.*`, `Individual_fields.Status`, `created_date`, `modified_date` |
| **Auth required** | Yes — returns empty without auth. |
| **Count** | Not testable without auth. Needs server-side `cv api4 Contact.getCount +w 'contact_sub_type:name=Volunteer'` |

### 11. Contribution (GCOC Monetary)

| Check | Result |
|-------|--------|
| **API endpoint** | `Contribution.get` |
| **Base fields** | `id`, `contact_id`, `total_amount`, `receive_date`, `contribution_status_id`, `payment_instrument_id`, `currency`, `source`, etc. |
| **Custom field groups** | `Cheque_Number` (2), `Wire_Transfer` (2), `Contribution_Details` (12 — includes `Source`, `PU_Source`, `Events` FK) |
| **FK fields** | `contact_id` → `Contact.id`, `Contribution_Details.Source` → OptionValue (PU Office), `Contribution_Details.Events` → `Event.id` |
| **Incremental sync** | **`modified_date`: NO. `created_date`: NO.** Has `receive_date`, `receipt_date`, `thankyou_date`, `cancel_date` as date proxies. |
| **Pagination** | Yes |
| **Auth required** | Yes — returns 403 without auth. |
| **BLOCKER** | No `created_date` or `modified_date`. For incremental sync, options: (1) Use `receive_date` as creation proxy, (2) Query by `id > last_synced_id`, (3) Add timestamps via a hook. |

### 12. Mailing

| Check | Result |
|-------|--------|
| **API endpoint** | `Mailing.get` |
| **Base fields** | `id`, `name`, `subject`, `from_name`, `from_email`, `status`, `created_date`, `modified_date`, `scheduled_date`, `start_date`, `end_date`, etc. |
| **Stats fields** | `stats_intended_recipients`, `stats_successful`, `stats_opens_total`, `stats_opens_unique`, `stats_clicks_total`, `stats_bounces`, `stats_unsubscribes`, etc. |
| **Incremental sync** | Yes (`created_date`, `modified_date`) |
| **Pagination** | Yes |

---

## Incremental Sync Summary

| Entity | `created_date` | `modified_date` | Incremental Ready |
|--------|---------------|-----------------|-------------------|
| Eck_Collection_Camp | Yes | Yes | **Yes** |
| Eck_Collection_Source_Vehicle_Dispatch | Yes | Yes | **Yes** |
| Eck_Collection_Source_Feedback | Yes | Yes | **Yes** |
| Eck_Institution_Visit | Yes | Yes | **Yes** |
| Eck_Collection_Camp_Activity | Yes | Yes | **Yes** |
| Eck_Dropping_Center_Meta | Yes | Yes | **Yes** |
| Event | Yes | **No** | **No — needs fix** |
| Activity | Yes | Yes | **Yes** |
| Contact | Yes | Yes | **Yes** |
| Contribution | **No** | **No** | **No — needs fix** |
| Mailing | Yes | Yes | **Yes** |

### Blockers Requiring Code Fixes

1. **Event: No `modified_date`** — CiviCRM's `civicrm_event` table doesn't have a `modified_date` column. Fix: Add a `hook_civicrm_post` in `goonjcustom` to stamp a custom field on Event update. Alternatively, accept full-refresh sync for Events (lower volume entity).

2. **Contribution: No `created_date` or `modified_date`** — CiviCRM's `civicrm_contribution` table has `receive_date` but not proper audit timestamps. Fix: Use `receive_date` as a creation proxy and `id > last_synced_id` for incremental, or add a post-hook to stamp custom timestamps.

---

## Entity Relationship Map (Foreign Keys)

```
Collection Camp (Eck_Collection_Camp, subtype: Collection_Camp)
  │
  ├── Dispatch (Eck_Collection_Source_Vehicle_Dispatch)
  │     FK: Camp_Vehicle_Dispatch.Collection_Camp → Eck_Collection_Camp.id
  │     └── Dispatch Ack (same entity, Acknowledgement_For_Logistics.* fields)
  │
  ├── Feedback (Eck_Collection_Source_Feedback)
  │     FK: Collection_Source_Feedback.Collection_Camp_Code → Eck_Collection_Camp.id
  │
  ├── Camp Activity (Eck_Collection_Camp_Activity)
  │     FK: Collection_Camp_Activity.Collection_Camp_Id → Eck_Collection_Camp.id
  │
  ├── Logistics (same entity, Logistics_Coordination.* fields)
  │
  ├── Outcome (same entity, Camp_Outcome.* fields)
  │
  ├── Volunteer Feedback (same entity, Volunteer_Camp_Feedback.* fields)
  │
  ├── Material Contribution (Activity, type: Material Contribution)
  │     FK: Material_Contribution.Collection_Camp → Eck_Collection_Camp.id
  │
  ├── Volunteering Activity (Activity, type: Volunteering Activity)
  │     FK: Volunteering_Activity.Collection_Camp → Eck_Collection_Camp.id
  │
  └── Organizer Contact
        FK: Collection_Camp_Core_Details.Contact_Id → Contact.id


Dropping Center (Eck_Collection_Camp, subtype: Dropping_Center)
  │
  ├── Dispatch
  │     FK: Camp_Vehicle_Dispatch.Dropping_Center → Eck_Collection_Camp.id
  │
  ├── Dropping Center Meta (Eck_Dropping_Center_Meta)
  │     FK: Dropping_Center_Meta.Dropping_Center → Eck_Collection_Camp.id
  │     (monthly status tracking: Visit, Donation, Logistics, Status)
  │
  ├── Material Contribution (Activity)
  │     FK: Material_Contribution.Dropping_Center → Eck_Collection_Camp.id
  │
  └── Outcome (same entity, Dropping_Center_Outcome.* fields)


Institution Collection Camp (Eck_Collection_Camp, subtype: Institution_Collection_Camp)
  │
  ├── Dispatch
  │     FK: Camp_Vehicle_Dispatch.Institution_Collection_Camp → Eck_Collection_Camp.id
  │
  └── (same sub-entities as Collection Camp, via Institution_* custom field groups)


Institution Dropping Center (Eck_Collection_Camp, subtype: Institution_Dropping_Center)
  │
  ├── Dispatch
  │     FK: Camp_Vehicle_Dispatch.Institution_Dropping_Center → Eck_Collection_Camp.id
  │
  ├── Dropping Center Meta
  │     FK: Dropping_Center_Meta.Institution_Dropping_Center → Eck_Collection_Camp.id
  │
  └── (same sub-entities as Dropping Center, via Institution_* custom field groups)


Event (native CiviCRM)
  │
  ├── Participants → Participant.get (FK: event_id → Event.id)
  │
  ├── Contributions
  │     FK: Contribution_Details.Events → Event.id
  │
  ├── Material Contribution (Activity)
  │     FK: Material_Contribution.Event → Event.id
  │
  └── Outcome (same entity, Goonj_Events_Outcome.* fields)


GCOC / Institution Visit (Eck_Institution_Visit, subtype: Urban_Visit)
  │
  ├── Institution
  │     FK: Urban_Planned_Visit.Institution → Contact.id (Organization)
  │
  ├── Individual
  │     FK: Urban_Planned_Visit.Select_Individual → Contact.id
  │
  ├── Institution POC
  │     FK: Urban_Planned_Visit.Institution_POC → Contact.id
  │
  ├── Visit Feedback (same entity, Visit_Feedback.* fields)
  │
  ├── Volunteering Activity (Activity)
  │     FK: Volunteering_Activity.Urban_Planned_Visit → Eck_Institution_Visit.id
  │
  └── Processing Center
        FK: Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_ → OptionValue


Contact (Volunteer)
  │
  ├── Induction (Activity, type: Induction)
  │     FK: source_contact_id → Contact.id
  │
  ├── Volunteering Activity (Activity)
  │     FK: target_contact_id → Contact.id
  │
  └── Material Contribution (Activity)
        FK: target_contact_id → Contact.id
```

---

## Pagination

Confirmed working for all entities:
```json
{
  "select": ["id", "title", "modified_date"],
  "orderBy": {"modified_date": "ASC"},
  "limit": 100,
  "offset": 0
}
```

Increment `offset` by `limit` for each page. No observed max page size limit.

---

## PII Handling

For entities where Dalgo's CSV says "avoid personal information":

| Entity | PII Fields to Exclude from `select` |
|--------|--------------------------------------|
| Collection Camp | `Collection_Camp_Intent_Details.Name`, `Collection_Camp_Intent_Details.Contact_Number` |
| Dropping Center | `Dropping_Centre.Name`, `Dropping_Centre.Contact_Number` |
| Institution DC | `Camp_Institution_Data.Email`, `Camp_Institution_Data.Contact_Number` |
| Contact (Volunteer) | `first_name`, `last_name`, `email`, `phone`, `street_address` — use `id`, `display_name` (or anonymize), `contact_sub_type`, status fields only |

CiviCRM API4 supports explicit `select` — only requested fields are returned. PII can be excluded by not selecting those fields.

---

## Security Concern

**ECK entity base fields and Event records are accessible without authentication** via the AJAX endpoint with `X-Requested-With: XMLHttpRequest`. While custom fields (containing sensitive data) require auth, this is still an exposure risk. Consider:
1. Restricting anonymous CiviCRM API access via WordPress permissions
2. Or ensuring the staging/production server has IP-level restrictions

---

## Next Steps

1. **Generate API key** on staging for authenticated testing
2. **Verify custom field data** returns correctly with auth (especially FK fields)
3. **Test Contact/Volunteer count** with auth to determine volume
4. **Test Contribution** with auth
5. **Decide on Event/Contribution incremental sync** approach (code fix vs. full refresh)
6. **Share this document + Step 1** with Dalgo team
