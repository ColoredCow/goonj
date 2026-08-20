# ColoredCow Action Plan: CiviCRM API Audit for Dalgo

## Step 1: Categorize entities by API type

You built these extensions as CiviCRM plugins. So you already know which are native CiviCRM entities and which are Goonj-custom. Classify each:

**Likely native CiviCRM APIs (APIv3/v4 out of the box):**
- Events → `Event` API
- Activities → `Activity` API
- Volunteer Information → `Contact` API (filtered by contact type/group/tag)
- Mailing Information → `Mailing` + `MailingJob` + `MailingEventBounce/Delivered/Opened` APIs

**Goonj-custom entities (built as CiviCRM plugins — need verification):**
- Collection Camp (Individual/Institution)
- Dropping Center (Individual/Institution)
- Dispatch
- Dispatch Acknowledgement
- Logistics
- Outcome
- Feedback
- GCOC

For each custom entity — confirm whether CiviCRM auto-exposes an API (it usually does for entities registered via `hook_civicrm_entityTypes`), or if a custom API wrapper is needed.

## Step 2: For each of the 14 entities, produce this

Hit the Goonj CiviCRM instance and document:

| Check | What to capture |
|-------|----------------|
| **API endpoint** | Exact entity name and action (e.g., `CollectionCamp.get`) |
| **Fields returned** | Full field list from the API response — then cross-check against what the CSV asks for |
| **Missing fields** | Fields the CSV requires but the API doesn't return |
| **Filters available** | Can you filter by `is_active`, date range, geography, contact type? |
| **Incremental sync** | Does the response include `created_date` and `modified_date`? Can you sort by `modified_date`? |
| **Pagination** | Does `limit` + `offset` work? What's the max page size? |
| **PII concern** | For Collection Camp, Dropping Center, Volunteers — the CSV says "avoid personal information like email, phone, address". Confirm these fields can be excluded in the API call or need to be stripped. |
| **Aggregation** | For Contributors (Collection Camp, Dropping Center, Events) — does the API return counts, or do you get raw records that Dalgo will aggregate? |

## Step 3: Test incrementals specifically

Dalgo was explicit — they need incremental sync to work. For each entity:

1. Call the API with `sort=modified_date ASC` and `options.limit=10`
2. Confirm `modified_date` updates when a record is edited
3. Confirm `created_date` exists and is reliable
4. If either is missing on a custom entity — that's a code fix ColoredCow needs to make before handing off

## Step 4: Handle the Contacts/Volunteers problem

The CSV asks for Volunteer info — name, contact ID, status (registered, inducted, active, inactive). But there are 3 lakh contacts.

- Check if volunteers are in a specific CiviCRM **Group** or tagged with a **Tag** that separates them from general contacts
- Test the `Contact.get` API with that group/tag filter + `is_active` filter
- Report the count — how many volunteers come back? If it's manageable (say <50k), Dalgo can sync them. If it's still huge, flag it.

## Step 5: Map the entity relationships

This is the part Goonj didn't document but you already know from building the system. Write down:

```
Collection Camp
  ├── has Dispatch(es)
  │     └── has Dispatch Acknowledgement
  ├── has Logistics
  ├── has Outcome
  ├── has Feedback
  ├── has Volunteers (contacts)
  └── has Contributors (contacts, need count only)

Dropping Center
  └── (same sub-entity structure as Collection Camp?)

Event
  ├── has Participants (registered/attended)
  └── has Contributions (monetary)

GCOC
  └── (what links to what?)
```

Document the **foreign key** for each relationship — what field in Dispatch links back to Collection Camp? Is it `case_id`? `activity_id`? A custom field?

This is critical for Dalgo to build joins in the warehouse. You don't need Shivangi for this — you built the system.

## Step 6: Compile the deliverable

One document (or spreadsheet) with:

| Entity | API Endpoint | Fields Available | Fields Missing | Supports Incremental | Pagination | Related Entities & FK | Notes |
|--------|-------------|-----------------|----------------|---------------------|------------|----------------------|-------|
| | | | | | | | |

Plus the entity relationship map from Step 5.

## Step 7: Share with Dalgo

Hand off the document. At this point Dalgo has everything they need to build the Airbyte connector. The only open items should be:
- Any missing fields that need a code fix (with your timeline)
- Any entities where incremental sync needs to be added (with your timeline)

## Timeline

| Day | Work |
|-----|------|
| Day 1 | Steps 1–2: Categorize entities + hit the APIs, document fields |
| Day 2 | Steps 3–4: Test incrementals + figure out the contacts/volunteers filtering |
| Day 3 | Steps 5–6: Map relationships with foreign keys + compile the deliverable |
| Day 4 | Step 7: Share with Dalgo + fix any gaps found |

Four working days. No dependency on Goonj for any of this — you have the CiviCRM instance, you built the plugins, you have the CSV.
