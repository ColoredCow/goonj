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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

Returns records modified after a given date. Use for incremental syncs.

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | ISO date string, e.g. `2026-03-01`. Replace with your last sync timestamp. |
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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | Replace with your last sync timestamp |
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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | Replace with your last sync timestamp |
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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | Replace with your last sync timestamp |
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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | Replace with your last sync timestamp |
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
| `orderBy` | Yes | Sort field and direction (default: `{"id":"ASC"}`) |

### Filter: Modified since date

| Parameter | Configurable | Description |
|-----------|-------------|-------------|
| `modified_date` | Yes | Replace with your last sync timestamp |
| `limit` | Yes | Number of records per page (default: 25) |
| `offset` | Yes | Starting position for pagination (default: 0) |
