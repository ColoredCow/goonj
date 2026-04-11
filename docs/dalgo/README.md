# Goonj CRM — Dalgo API Integration

API access to Goonj CRM data for the Dalgo data platform.

## Getting Started

1. Get your API key from the Goonj team (shared via secure one-time link)
2. Import the Postman collection and environment files (shared via Google Drive — ask the Goonj team for access)
3. Select the **Goonj CRM - Staging** environment (top-right dropdown in Postman)
4. Open the environment and paste your API key into the `API_KEY` variable
5. Run **Collection Camp > Get Total Count** — if you see `countMatched` in the response, you're connected

> **Two environments are provided.** Start with **Staging** for development and testing. Switch to **Production** only when your integration is verified and ready for live data. The API key is different per environment — the Goonj team will provide both.

## Pagination

Use `limit` and `offset` in the params:
- Page 1: `"limit":25,"offset":0`
- Page 2: `"limit":25,"offset":25`
- Stop when `values` returns an empty array

To get the total count without fetching data, use `"select":["row_count"],"limit":0` and check `countMatched` in the response.

## Incremental Sync

Filter by `modified_date` to fetch only records changed since your last sync. Replace the date with your last sync timestamp.

## Field Discovery

Use the **Get Field Definitions** request in the Postman collection. It returns the full schema — field name, label, data type, and custom group. Always up-to-date with the live system.

---

## Collection Camp

Collection drives organized by volunteers across India. Tracks the full lifecycle: registration, logistics, outcome, and feedback.

1. **Get Total Count** — total number of collection camp records
2. **Get All Data (Page 1)** — all fields, paginated, ordered by ID
3. **Filter: Modified since date** — records changed after a given date (for incremental sync)
4. **Get Field Definitions** — full field schema for this entity

## Institution Collection Camp

Collection drives organized by institutions (schools, corporates, etc.).

*Coming soon — same API pattern, different subtype filter.*

## Dropping Center

Permanent material drop-off points run by volunteers.

*Coming soon*

## Institution Dropping Center

Institutional drop-off points.

*Coming soon*

## Goonj Activities

Community engagement activities organized by Goonj.

*Coming soon*

## Institution Goonj Activities

Activities organized by institutions in partnership with Goonj.

*Coming soon*
