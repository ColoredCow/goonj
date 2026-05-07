# Goonj CRM — Dalgo API Integration

API access to Goonj CRM data for the Dalgo data platform.

## Getting Started

1. You should have received an API key via a secure one-time link. If not, check with your point of contact.
2. Import the Postman collection and environment files from the shared Google Drive folder.
3. Select the **Goonj CRM - Staging** environment (top-right dropdown in Postman).
4. Open the environment and paste your API key into the `API_KEY` variable.
5. Run **Collection Camp > Get Total Count** — if you see `countMatched` in the response, you're connected.

> **Two environments are provided.** Start with **Staging** for development and testing. Switch to **Production** only when your integration is verified and ready for live data. The API key is different per environment — both will be shared separately.

## Pagination

Use `limit` and `offset` in the params:
- Page 1: `"limit":25,"offset":0`
- Page 2: `"limit":25,"offset":25`
- Stop when `values` returns an empty array.

To get the total count without fetching data, use `"select":["row_count"],"limit":0` and check `countMatched` in the response.

## Incremental Sync

Filter by `modified_date` to fetch only records changed since your last sync. Replace the date with your last sync timestamp.

## Field Discovery

Use the **Get Field Definitions** request in the Postman collection. It returns the full schema — field name, label, data type, and custom group. Shared across all entities. Always up-to-date with the live system.

## Resolving IDs to readable values

Many fields in entity responses come back as numeric or string IDs (e.g. `Goonj_Office: 9`, `State: "1073"`, `Camp_Type: 1`). To turn these into readable values, look the ID up against the matching dim table under the **Lookups** folder in Postman.

For ad-hoc single-record lookups (e.g. fetching one State by ID), see `Lookups/Get a single record by ID (example)`. The same `where: id = ?` pattern works for any lookup entity.

### Subtype values

The top-level `subtype` field uses a small fixed map (no API lookup needed):

| value | label |
|---|---|
| 1 | Collection Camp |
| 2 | Dropping Center |
| 10 | Goonj Activities |
| 11 | Institution Collection Camp |
| 12 | Institution Dropping Center |
| 13 | Institution Goonj Activities |

### Field-to-lookup map

For every ID-shaped field that appears in entity responses, this table tells you which `Lookups/` sub-folder to query and how to join.

| API Response Field | Lookup | Filter / Notes |
|---|---|---|
| `subtype` | Subtype | See `Subtype values` above. |
| `created_id` | Individual | Join on `Individual.id`. |
| `modified_id` | Individual | Join on `Individual.id`. |
| `Camp_Outcome.Filled_By` | Individual | Join on `Individual.id`. |
| `Collection_Camp_Core_Details.Contact_Id` | Individual | Join on `Individual.id`. |
| `Collection_Camp_Core_Details.Modifier` | Individual | Join on `Individual.id`. |
| `Collection_Camp_Intent_Details.Coordinating_Urban_POC` | Individual | Join on `Individual.id`. |
| `Dropping_Centre.Contact_Dispatch_Email` | Individual | Join on `Individual.id`. |
| `Dropping_Centre.Coordinating_Urban_POC` | Individual | Join on `Individual.id`. |
| `Goonj_Activities.Coordinating_Urban_Poc` | Individual | Join on `Individual.id`. |
| `Goonj_Activities_Outcome.Filled_By` | Individual | Join on `Individual.id`. |
| `Institution_Collection_Camp_Intent.Institution_POC` | Individual | Join on `Individual.id`. |
| `Institution_Collection_Camp_Logistics.Any_volunteer_or_Interns_attending_camp` | Individual | Join on `Individual.id`. |
| `Institution_Collection_Camp_Logistics.Camp_to_be_attended_by` | Individual | Join on `Individual.id`. |
| `Institution_Dropping_Center_Intent.Contact_Dispatch_Email` | Individual | Join on `Individual.id`. |
| `Institution_Dropping_Center_Intent.Institution_POC` | Individual | Join on `Individual.id`. |
| `Institution_Dropping_Center_Logistics.Camp_to_be_attended_by` | Individual | Join on `Individual.id`. |
| `Institution_Dropping_Center_Review.Coordinating_POC` | Individual | Join on `Individual.id`. |
| `Institution_Goonj_Activities.Coordinating_Urban_Poc` | Individual | Join on `Individual.id`. |
| `Institution_Goonj_Activities.Institution_POC` | Individual | Join on `Individual.id`. |
| `Institution_collection_camp_Review.Coordinating_POC` | Individual | Join on `Individual.id`. |
| `Logistics_Coordination.Any_volunteer_or_Interns_attending_camp` | Individual | Join on `Individual.id`. |
| `Logistics_Coordination.Camp_to_be_attended_by` | Individual | Join on `Individual.id`. |
| `Collection_Camp_Intent_Details.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Dropping_Centre.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Goonj_Activities.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Institution_Collection_Camp_Intent.Organization_Name` | Organization | Join on `Organization.id`. |
| `Institution_Dropping_Center_Intent.Organization_Name` | Organization | Join on `Organization.id`. |
| `Institution_Dropping_Center_Review.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Institution_Goonj_Activities.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Institution_Goonj_Activities.Organization_Name` | Organization | Join on `Organization.id`. |
| `Institution_collection_camp_Review.Goonj_Office` | Organization | Join on `Organization.id`. |
| `Collection_Camp_Intent_Details.State` | StateProvince | Join on `StateProvince.id`. |
| `Dropping_Centre.State` | StateProvince | Join on `StateProvince.id`. |
| `Goonj_Activities.State` | StateProvince | Join on `StateProvince.id`. |
| `Institution_Collection_Camp_Intent.State` | StateProvince | Join on `StateProvince.id`. |
| `Institution_Dropping_Center_Intent.State` | StateProvince | Join on `StateProvince.id`. |
| `Institution_Goonj_Activities.State` | StateProvince | Join on `StateProvince.id`. |
| `Institution_Goonj_Activities.State_2` | StateProvince | Join on `StateProvince.id`. |
| `Institution_Goonj_Activities.Country` | Country | Join on `Country.id`. |
| `Camp_Outcome.Camp_photo_Video` | File | Join on `File.id`; use `file_name` for display. |
| `Camp_Outcome.Image_2` | File | Join on `File.id`; use `file_name` for display. |
| `Camp_Outcome.Image_3` | File | Join on `File.id`; use `file_name` for display. |
| `Camp_Outcome.Image_4` | File | Join on `File.id`; use `file_name` for display. |
| `Camp_Outcome.Image_5` | File | Join on `File.id`; use `file_name` for display. |
| `Collection_Camp_Core_Details.Poster` | File | Join on `File.id`; use `file_name` for display. |
| `Collection_Camp_Intent_Details.Is_there_any_Logo_to_be_added_in_the_Leaflet_` | File | Join on `File.id`; use `file_name` for display. |
| `Collection_Camp_QR_Code.QR_Code` | File | Join on `File.id`; use `file_name` for display. |
| `Collection_Camp_QR_Code.QR_Code_For_Poster` | File | Join on `File.id`; use `file_name` for display. |
| `Institution_Goonj_Activities_Outcome.Image_2` | File | Join on `File.id`; use `file_name` for display. |
| `Institution_Goonj_Activities_Outcome.Image_3` | File | Join on `File.id`; use `file_name` for display. |
| `Institution_Goonj_Activities_Outcome.Image_4` | File | Join on `File.id`; use `file_name` for display. |
| `Institution_Goonj_Activities_Outcome.Photo_Byte_Max_4_` | File | Join on `File.id`; use `file_name` for display. |
| `Institution_collection_camp_Review.Is_there_any_Logo_to_be_added_in_the_Leaflet_` | File | Join on `File.id`; use `file_name` for display. |
| `Volunteer_Camp_Feedback.Image_2` | File | Join on `File.id`; use `file_name` for display. |
| `Volunteer_Camp_Feedback.Image_3` | File | Join on `File.id`; use `file_name` for display. |
| `Volunteer_Camp_Feedback.Image_4` | File | Join on `File.id`; use `file_name` for display. |
| `Volunteer_Camp_Feedback.Image_5` | File | Join on `File.id`; use `file_name` for display. |
| `Volunteer_Camp_Feedback.Photo_Byte_Max_4_` | File | Join on `File.id`; use `file_name` for display. |
| `Collection_Camp_Intent_Details.Campaign` | Campaign | Join on `Campaign.id`; use `title`. |
| `Institution_collection_camp_Review.Campaign` | Campaign | Join on `Campaign.id`; use `title`. |
| `Collection_Camp_Core_Details.Poster_Template` | MessageTemplate | Join on `MessageTemplate.id`; use `msg_title`. |
| `Collection_Camp_Intent_Details.Initiator_Induction_Id` | Induction | Use `Lookups/Induction/Get All Data` (already filtered to `activity_type:name = "Induction"`). Join on `Activity.id`. |
| `Camp_Outcome.Collection_Camp_Intent_Id` | Eck_Collection_Camp | Self-reference: this is the id of the parent Collection Camp Intent record. Use your existing `Eck_Collection_Camp` data; join on `id`. |
| `Camp_Outcome.Is_any_session_conducted_in_camp_` | OptionValue | `option_group_id.name = "Camp_Outcome_Is_any_session_conducted_in_camp_"`; join on `value`. |
| `Camp_Outcome.Rate_the_camp` | OptionValue | `option_group_id.name = "Camp_Outcome_Rate_the_camp"`; join on `value`. |
| `Camp_Outcome.What_Activities_were_conducted_as_part_of_the_Camp_Drive` | OptionValue | `option_group_id.name = "Camp_Outcome_What_Activities_were_conducted_as_part_of_the_"`; join on `value`. |
| `Collection_Camp_Core_Details.Status` | OptionValue | `option_group_id.name = "Collection_Camp_Core_Details_Status"`; join on `value`. |
| `Collection_Camp_Intent_Details.Are_the_details_confirmed_with_the_volunteer_` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_Are_the_details_confirmed_wi"`; join on `value`. |
| `Collection_Camp_Intent_Details.Associated_activities_` | OptionValue | `option_group_id.name = "Intent_Details_Associated_activities_"`; join on `value`. |
| `Collection_Camp_Intent_Details.Camp_Status` | OptionValue | `option_group_id.name = "Intent_Details_Camp_Status"`; join on `value`. |
| `Collection_Camp_Intent_Details.Camp_Type` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_Camp_Type"`; join on `value`. |
| `Collection_Camp_Intent_Details.Do_you_require_permission_letters_from_Goonj_to_get_permission_f` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_Do_you_require_permission_le"`; join on `value`. |
| `Collection_Camp_Intent_Details.Do_you_want_to_plan_a_creative_and_engaging_activity_where_resid` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_Do_you_want_to_plan_a_creati"`; join on `value`. |
| `Collection_Camp_Intent_Details.Here_are_some_activities_to_pick_from_but_feel_free_to_invent_yo` | OptionValue | `option_group_id.name = "Intent_Details_Here_are_some_activities_to_pick_from_but_fe"`; join on `value`. |
| `Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public` | OptionValue | `option_group_id.name = "Intent_Details_Will_your_collection_drive_be_open_for_gener"`; join on `value`. |
| `Collection_Camp_Intent_Details.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_You_wish_to_register_as"`; join on `value`. |
| `Dropping_Centre.Can_we_keep_donation_box_in_center_For_Monetary_Contribution_` | OptionValue | `option_group_id.name = "Dropping_Centre_Can_we_keep_donation_box_in_center_For_Mone"`; join on `value`. |
| `Dropping_Centre.Current_Status` | OptionValue | `option_group_id.name = "Dropping_Centre_Current_Status"`; join on `value`. |
| `Dropping_Centre.Reason_For_Unauthorize` | OptionValue | `option_group_id.name = "Dropping_Centre_Reason_For_Unauthorize"`; join on `value`. |
| `Dropping_Centre.Some_volunteers_require_permission_letters_from_Goonj_to_get_per` | OptionValue | `option_group_id.name = "Dropping_Centre_Some_volunteers_require_permission_letters_"`; join on `value`. |
| `Dropping_Centre.Will_your_dropping_center_be_open_for_general_public_as_well_out` | OptionValue | `option_group_id.name = "Dropping_Centre_Will_your_dropping_center_be_open_for_gener"`; join on `value`. |
| `Dropping_Centre.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Dropping_Centre_You_wish_to_register_as"`; join on `value`. |
| `Goonj_Activities.How_do_you_want_to_engage_with_Goonj_` | OptionValue | `option_group_id.name = "Goonj_Activities_How_do_you_want_to_engage_with_Goonj_"`; join on `value`. |
| `Goonj_Activities.Select_Attendee_feedback_form` | OptionValue | `option_group_id.name = "Goonj_Activities_Select_Attendee_feedback_form"`; join on `value`. |
| `Goonj_Activities.Select_Goonj_POC_Attendee_Outcome_Form` | OptionValue | `option_group_id.name = "Goonj_Activities_Select_Goonj_POC_Attendee_Outcome_Form"`; join on `value`. |
| `Goonj_Activities.Select_Volunteer_Feedback_Form` | OptionValue | `option_group_id.name = "Goonj_Activities_Select_Volunteer_Feedback_Form"`; join on `value`. |
| `Goonj_Activities.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Collection_Camp_Intent_Details_You_wish_to_register_as"`; join on `value`. |
| `Goonj_Activities_Outcome.Rate_the_activity` | OptionValue | `option_group_id.name = "Camp_Outcome_Rate_the_camp"`; join on `value`. |
| `Institution_Collection_Camp_Intent.Do_you_want_to_plan_a_creative_and_engaging_activity_that_go_bey` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_Do_you_want_to_plan_a_cr"`; join on `value`. |
| `Institution_Collection_Camp_Intent.Here_are_some_activities_to_pick_from_but_feel_free_to_invent_yo` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_Here_are_some_activities"`; join on `value`. |
| `Institution_Collection_Camp_Intent.Will_your_collection_drive_be_open_for_general_public_as_well` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_Will_your_collection_dri"`; join on `value`. |
| `Institution_Collection_Camp_Intent.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_You_wish_to_register_as"`; join on `value`. |
| `Institution_Collection_Camp_Logistics.Camp_Bag_Material` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Logistics_Camp_Bag_Material"`; join on `value`. |
| `Institution_Collection_Camp_Logistics.Goonj_Publications` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Logistics_Goonj_Publications"`; join on `value`. |
| `Institution_Dropping_Center_Intent.Can_we_keep_donation_box_in_center_` | OptionValue | `option_group_id.name = "Institution_Dropping_Center_Intent_Can_we_keep_donation_box"`; join on `value`. |
| `Institution_Dropping_Center_Intent.Current_Status` | OptionValue | `option_group_id.name = "Dropping_Centre_Current_Status"`; join on `value`. |
| `Institution_Dropping_Center_Intent.Some_volunteers_require_permission_letters_from_Goonj_to_get_per` | OptionValue | `option_group_id.name = "Institution_Dropping_Center_Intent_Some_volunteers_require_"`; join on `value`. |
| `Institution_Dropping_Center_Intent.Will_your_dropping_center_be_open_for_general_public_as_well_out` | OptionValue | `option_group_id.name = "Institution_Dropping_Center_Intent_Will_your_dropping_cente"`; join on `value`. |
| `Institution_Dropping_Center_Intent.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_You_wish_to_register_as"`; join on `value`. |
| `Institution_Dropping_Center_Review.Initiated_By` | OptionValue | `option_group_id.name = "Institution_Dropping_Center_Review_Initiated_By"`; join on `value`. |
| `Institution_Goonj_Activities.Have_You_Previously_Hosted_a_Goonj_Exhibition_Event_` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_Have_You_Previously_Hosted_a_G"`; join on `value`. |
| `Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_How_do_you_want_to_engage_with"`; join on `value`. |
| `Institution_Goonj_Activities.Initiated_By` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Review_Initiated_By"`; join on `value`. |
| `Institution_Goonj_Activities.Select_Attendee_feedback_form` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_Select_Attendee_feedback_form"`; join on `value`. |
| `Institution_Goonj_Activities.Select_Goonj_POC_Attendee_Outcome_Form` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_Select_Goonj_POC_Attendee_Outc"`; join on `value`. |
| `Institution_Goonj_Activities.Select_Institute_POC_Feedback_Form` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_Select_Institute_POC_Feedback_"`; join on `value`. |
| `Institution_Goonj_Activities.Space_Type` | OptionValue | `option_group_id.name = "Institution_Goonj_Activities_Space_Type"`; join on `value`. |
| `Institution_Goonj_Activities.You_wish_to_register_as` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Intent_You_wish_to_register_as"`; join on `value`. |
| `Institution_Goonj_Activities_Logistics.Goonj_Publications` | OptionValue | `option_group_id.name = "Logistics_Coordination_Goonj_Publications"`; join on `value`. |
| `Institution_Goonj_Activities_Outcome.Rate_the_activity` | OptionValue | `option_group_id.name = "Camp_Outcome_Rate_the_camp"`; join on `value`. |
| `Institution_collection_camp_Review.Are_the_details_confirmed_with_the_institute_POC` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Review_Are_the_details_confirme"`; join on `value`. |
| `Institution_collection_camp_Review.Camp_Status` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Review_Camp_Status"`; join on `value`. |
| `Institution_collection_camp_Review.Initiated_By` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Review_Initiated_By"`; join on `value`. |
| `Institution_collection_camp_Review.Is_the_camp_IHC_PCC_` | OptionValue | `option_group_id.name = "Institution_Collection_Camp_Review_Is_the_camp_IHC_PCC_"`; join on `value`. |
| `Logistics_Coordination.Books` | OptionValue | `option_group_id.name = "Logistics_Coordination_Books"`; join on `value`. |
| `Logistics_Coordination.Camp_Bag_Material` | OptionValue | `option_group_id.name = "Logistics_Coordination_Camp_Bag_Material"`; join on `value`. |
| `Logistics_Coordination.GBG_Bag` | OptionValue | `option_group_id.name = "Logistics_Coordination_GBG_Bag"`; join on `value`. |
| `Logistics_Coordination.Goonj_Publications` | OptionValue | `option_group_id.name = "Logistics_Coordination_Goonj_Publications"`; join on `value`. |
| `Volunteer_Camp_Feedback.Give_Rating_to_your_camp` | OptionValue | `option_group_id.name = "Volunteer_Camp_Feedback_Give_Rating_to_your_camp"`; join on `value`. |
| `Volunteer_Camp_Feedback.How_many_rating_do_you_give_to_our_goonj_member_who_attended_cam` | OptionValue | `option_group_id.name = "Volunteer_Camp_Feedback_How_many_rating_do_you_give_to_our_"`; join on `value`. |

---

## Collection Camp

Collection drives organized by volunteers across India. Tracks the full lifecycle: registration, logistics, outcome, and feedback.

### Get Total Count

Returns the total number of Collection Camp records.

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Collection_Camp` |

### Get All Data

Returns all fields for Collection Camp records, paginated.

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

Returns records modified after a given date. Use for incremental syncs.

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string, e.g. `2026-03-01`. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |

---

## Institution Collection Camp

Collection drives organized by institutions (schools, corporates, etc.). Same fields as Collection Camp.

### Get Total Count

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Institution_Collection_Camp` |

### Get All Data

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |

---

## Dropping Center

Permanent material drop-off points run by volunteers.

### Get Total Count

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Dropping_Center` |

### Get All Data

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |

---

## Institution Dropping Center

Institutional drop-off points.

### Get Total Count

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Institution_Dropping_Center` |

### Get All Data

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |

---

## Goonj Activities

Community engagement activities organized by Goonj.

### Get Total Count

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Goonj_Activities` |

### Get All Data

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |

---

## Institution Goonj Activities

Activities organized by institutions in partnership with Goonj.

### Get Total Count

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `subtype:name` | No | Fixed to `Institution_Goonj_Activities` |

### Get All Data

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
| `orderBy` | Yes | Sort field and direction. Field: `id`, `created_date`, `modified_date`, `title`. Direction: `ASC`, `DESC`. Default: `{"id":"ASC"}` |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string. Operator: `>=`, `<=`, `>`, `<`, `=`. Replace with your last sync timestamp. |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
