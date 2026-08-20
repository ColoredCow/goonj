/**
 * Thin CiviCRM APIv4 client for QA checks.
 *
 * Everything environment-specific lives in this file. If the endpoint or auth
 * header differs on your install, fix it here once — not in the checks.
 *
 * Required env (see .env.example):
 *   CIVI_BASE_URL   e.g. https://staging-crm.goonj.org
 *   CIVI_API_KEY    API key of a user with the permissions the checks need
 * Optional:
 *   CIVI_API_PATH   default /civicrm/ajax/api4
 *   CIVI_WEBHOOK_PATH  Razorpay webhook receiver — CONFIRM before first use
 */

'use strict';

const BASE = (process.env.CIVI_BASE_URL || '').replace(/\/$/, '');
const KEY = process.env.CIVI_API_KEY || '';
const API_PATH = process.env.CIVI_API_PATH || '/civicrm/ajax/api4';

// Not yet confirmed against a running site. Verify the real receiver path in
// CRM/Core/Civirazorpay/Payment/Razorpay.php before trusting webhook checks.
const WEBHOOK_PATH = process.env.CIVI_WEBHOOK_PATH || '';

/** Refuse to touch production, whatever anyone sets. */
const FORBIDDEN = [/(^|\/\/)crm\.goonj\.org/i];

function assertSafeTarget() {
  if (!BASE) {
    throw new Error('CIVI_BASE_URL is not set. Copy .env.example to .env and fill it in.');
  }
  for (const pattern of FORBIDDEN) {
    if (pattern.test(BASE)) {
      throw new Error(`Refusing to run QA checks against production (${BASE}).`);
    }
  }
  if (!KEY) {
    throw new Error('CIVI_API_KEY is not set.');
  }
}

/**
 * Call CiviCRM APIv4. Returns the array of values.
 */
async function api4(entity, action, params = {}) {
  assertSafeTarget();
  const url = `${BASE}${API_PATH}/${entity}/${action}`;
  const body = new URLSearchParams({ params: JSON.stringify(params) });

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'X-Civi-Auth': `Bearer ${KEY}`,
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  });

  const text = await res.text();
  if (!res.ok) {
    throw new Error(`APIv4 ${entity}.${action} failed: HTTP ${res.status}\n${text.slice(0, 500)}`);
  }

  let json;
  try {
    json = JSON.parse(text);
  } catch {
    // A login page or an HTML error means auth or the endpoint path is wrong.
    throw new Error(
      `APIv4 ${entity}.${action} returned non-JSON. Check CIVI_API_PATH and CIVI_API_KEY.\n` +
      text.slice(0, 300)
    );
  }

  if (json.error_message) {
    throw new Error(`APIv4 ${entity}.${action} error: ${json.error_message}`);
  }
  return json.values || [];
}

const get = (entity, params) => api4(entity, 'get', params);
const create = (entity, values) => api4(entity, 'create', { values });
const update = (entity, where, values) => api4(entity, 'update', { where, values });
const del = (entity, where) => api4(entity, 'delete', { where });

async function getOne(entity, params) {
  const rows = await get(entity, { ...params, limit: 1 });
  return rows[0] || null;
}

/**
 * POST a Razorpay-shaped webhook payload at the receiver.
 * Used by replay and concurrency checks.
 */
async function postWebhook(payload, headers = {}) {
  assertSafeTarget();
  if (!WEBHOOK_PATH) {
    throw new Error(
      'CIVI_WEBHOOK_PATH is not set. Confirm the Razorpay webhook receiver path in ' +
      'CRM/Core/Civirazorpay/Payment/Razorpay.php, then set it in .env.'
    );
  }
  const res = await fetch(`${BASE}${WEBHOOK_PATH}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers },
    body: JSON.stringify(payload),
  });
  return { status: res.status, body: await res.text() };
}

/** Unique marker so fixtures are always identifiable and never collide. */
function fixtureTag(caseId) {
  return `qa:${caseId}:${Date.now()}:${Math.floor(Math.random() * 1e6)}`;
}

module.exports = {
  api4, get, getOne, create, update, del, postWebhook, fixtureTag,
  BASE, WEBHOOK_PATH,
};
