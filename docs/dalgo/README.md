# Goonj CRM — Dalgo API Integration

API access to Goonj CRM data for the Dalgo data platform.

## Getting Started

1. **Get your API key** from the Goonj team (shared via secure one-time link)
2. **Import the Postman collection** — download [`Goonj-Collection-Camp-API.postman_collection.json`](Goonj-Collection-Camp-API.postman_collection.json) and import into Postman
3. **Create a Postman environment** with these variables:

   | Variable | Value |
   |----------|-------|
   | `BASE_URL` | `https://crm.goonj.org` |
   | `API_KEY` | *(your API key)* |

4. **Select the environment** from the top-right dropdown in Postman
5. **Run "Get Total Count"** — if you see `countMatched` in the response, you're connected

## Authentication

Every request requires these headers:

| Header | Value |
|--------|-------|
| `X-Civi-Auth` | `Bearer <API_KEY>` |
| `X-Requested-With` | `XMLHttpRequest` |
| `Content-Type` | `application/x-www-form-urlencoded; charset=UTF-8` |

## Request Format

All requests are `POST` with a `params` body field containing JSON:

```
params={"select":["*","custom.*"],"where":[...],"orderBy":{"id":"ASC"},"limit":25,"offset":0}
```

> **Note:** The body format is `raw` text (not JSON). The value of `params` is JSON, but the body itself is form-encoded.

## Pagination

Use `limit` and `offset`:

- Page 1: `"limit":25,"offset":0`
- Page 2: `"limit":25,"offset":25`
- Page 3: `"limit":25,"offset":50`
- Stop when `values` returns an empty array

To get the total count without fetching data: `"select":["row_count"],"limit":0` — check `countMatched` in the response.

## Incremental Sync

Filter by `modified_date` to fetch only records changed since your last sync:

```
"where":[["subtype:name","=","Collection_Camp"],["modified_date",">=","2026-03-01"]]
```

Replace the date with your last sync timestamp.

## Field Discovery

Use the **Get Field Definitions** request in the Postman collection. It returns the full schema for every field — name, label, data type, and which custom group it belongs to. This is always up-to-date with the live system.

---

## Entities

### Collection Camp

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Collection_Camp"]`

Collection drives organized by volunteers across India. Each record tracks the full lifecycle: intent/registration, logistics coordination, camp outcome, and volunteer feedback.

**Postman requests:** Get Total Count, Get All Data, Filter by Modified Date, Get Field Definitions

### Institution Collection Camp

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Institution_Collection_Camp"]`

Collection drives organized by institutions (schools, corporates, etc.). Same entity, different subtype.

*TBA — same API pattern as Collection Camp, change the subtype filter.*

### Dropping Center

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Dropping_Center"]`

Permanent material drop-off points.

*TBA*

### Institution Dropping Center

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Institution_Dropping_Center"]`

Institutional drop-off points.

*TBA*

### Goonj Activities

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Goonj_Activities"]`

Goonj-organized community engagement activities.

*TBA*

### Institution Goonj Activities

**Entity:** `Eck_Collection_Camp` | **Subtype filter:** `["subtype:name","=","Institution_Goonj_Activities"]`

Institution-organized activities in partnership with Goonj.

*TBA*
