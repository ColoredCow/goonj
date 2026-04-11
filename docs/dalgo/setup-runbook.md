# Dalgo CiviCRM Integration — Setup Runbook

> Follow this runbook on every environment (staging, production).
> All steps verified on staging first.

---

## Step 1: Create WordPress User

**Where:** WordPress Admin → Users → Add New User
(`/wp-admin/user-new.php`)

| Field | Value |
|---|---|
| Username | `dalgo-integration` |
| Email | `dalgo@goonj.org` *(use actual Dalgo team email on production)* |
| First Name | `Dalgo` |
| Last Name | `Integration` |
| Role | `Dalgo API Readonly` *(pre-existing custom role — see Step 2)* |
| Send User Notification | Unchecked *(avoid exposing credentials via email)* |

Generate a strong password and **store it securely** (do not share via plain text).

**Staging password:** `)c%K!*t!NQPjXLmwnUi9#Fmz` *(store in team password manager — do not share via Slack/email)*

---

## Step 2: WordPress Role — `Dalgo API Readonly`

This role already exists on staging. Before going live on production, verify it exists there too.

**To check:** WordPress Admin → Users → User Role Editor

The role should have:
- Minimal WordPress capabilities (no admin access)
- CiviCRM access sufficient to call APIv4 read endpoints

> **TODO:** Document exact capabilities assigned to this role (check User Role Editor on staging).

---

## Step 3: Code Change — Exempt Dalgo Role from State ACL Filter

The `goonjcustom` extension applies a `hook_civicrm_selectWhereClause` ACL on `Eck_Collection_Camp` that restricts records by the states controlled by the user's chapter-team group memberships. Users with no group memberships see zero records.

**File:** `wp-content/civi-extensions/goonjcustom/Civi/CollectionBaseService.php`
**Function:** `aclCollectionCamp()` (~line 840)

Add `'dalgo_api_readonly'` to the `$restrictedRoles` bypass list:

```php
// Before
$restrictedRoles = ['admin', 'urban_ops_admin', 'ho_account', 'project_team_ho', 's2s_ho_team', 'njpc_ho_team', 'project_ho_and_accounts'];

// After
$restrictedRoles = ['admin', 'urban_ops_admin', 'ho_account', 'project_team_ho', 's2s_ho_team', 'njpc_ho_team', 'project_ho_and_accounts', 'dalgo_api_readonly'];
```

This gives the Dalgo API user global read access to all Collection Camp records, bypassing the state-level filter.

**Deploy:** merge to `dev` branch → auto-deploys to staging, then deploy to production.

---

## Step 4: Grant CiviCRM Permissions

After creating the WordPress user, CiviCRM automatically creates a linked contact.

**Verify the linked contact was created:**
1. CiviCRM → Search → Find the contact by email (`dalgo@goonj.org`)
2. Note the Contact ID

**CiviCRM ACL requirements for the API to return data:**
- The user must have `access CiviCRM` permission
- The user must have `access AJAX API` permission
- The user must have read access to the `Eck_Collection_Camp` custom entity

> **TODO:** Confirm exact ACL/permission set needed by testing in Step 5.
> If records return empty despite a valid API key, it's an ACL issue — grant read on the relevant custom groups.

---

## Step 4: Generate CiviCRM API Key

**Where:** CiviCRM → find the Dalgo contact → API Key tab

Or via APIv4 (run this as an admin):

```bash
# Find the contact ID for the dalgo user first
curl -sS -X POST "https://<CRM_BASE>/civicrm/ajax/api4/Contact/get" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Authorization: Bearer <ADMIN_API_KEY>" \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  --data-urlencode 'params={"select":["id","display_name"],"where":[["email_primary.email","=","dalgo@goonj.org"]],"limit":1}'

# Then generate and set the API key (replace CONTACT_ID)
curl -sS -X POST "https://<CRM_BASE>/civicrm/ajax/api4/Contact/update" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Authorization: Bearer <ADMIN_API_KEY>" \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  --data-urlencode 'params={"where":[["id","=",CONTACT_ID]],"values":{"api_key":"<GENERATED_KEY>"}}'
```

**Key generation:** Use a cryptographically random 64-character hex string.

```bash
# Generate key locally
openssl rand -hex 32
```

**Store the key securely.** Share with Dalgo team via a secure channel (not Slack plain text, not email).

**Staging API key:** `d4686f28de0fae9aa35249a73baef165e929922659d047ad49c49229c169d934`
**Staging Contact ID:** `364203`

---

## Step 5: Test the API Endpoint

**Verify the key works and returns Collection Camp data:**

```bash
curl -sS -X POST "https://<CRM_BASE>/civicrm/ajax/api4/Eck_Collection_Camp/get" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "Authorization: Bearer <DALGO_API_KEY>" \
  -H "Content-Type: application/x-www-form-urlencoded; charset=UTF-8" \
  --data-urlencode 'params={
    "select": ["id", "title", "created_date", "modified_date", "subtype:name"],
    "orderBy": {"id": "ASC"},
    "limit": 5
  }'
```

**Expected:** JSON response with `values` array containing camp records.

**If `values` is empty:** ACL issue — the Dalgo user lacks read access on the entity. Grant permissions and retry.

---

## Environment Checklist

| Step | Staging | Production |
|---|---|---|
| WordPress user created | ✅ | ☐ |
| Role `Dalgo API Readonly` verified | ✅ | ☐ |
| CiviCRM linked contact confirmed | ✅ (ID: 364203) | ☐ |
| Code change deployed (`dalgo_api_readonly` ACL bypass) | ⏳ PR open | ☐ |
| CiviCRM ACL permissions set | ✅ (via code change) | ☐ |
| API key generated and stored securely | ✅ | ☐ |
| Test API call returns data | ⏳ pending deploy | ☐ |
| API key shared with Dalgo via secure channel | ☐ | ☐ |
