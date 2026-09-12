/**
 * Fetch and inspect public pages. No authentication — for anything that a
 * contributor could see without logging in.
 *
 * Env:
 *   PAGE_BASE_URL   defaults to https://staging-crm.goonj.org
 */

'use strict';

const DEFAULT_BASE = 'https://staging-crm.goonj.org';
const BASE = (process.env.PAGE_BASE_URL || process.env.CIVI_BASE_URL || DEFAULT_BASE)
  .replace(/\/$/, '');

const FORBIDDEN = [/(^|\/\/)crm\.goonj\.org/i];

function assertSafeTarget() {
  for (const pattern of FORBIDDEN) {
    if (pattern.test(BASE)) {
      throw new Error(`Refusing to run QA checks against production (${BASE}).`);
    }
  }
}

/** GET a page and return its HTML. */
async function fetchPage(path) {
  assertSafeTarget();
  const url = `${BASE}${path}`;
  const res = await fetch(url, { headers: { 'User-Agent': 'goonj-qa-checks' } });
  if (!res.ok) {
    throw new Error(`GET ${url} returned HTTP ${res.status}`);
  }
  return res.text();
}

/**
 * Find a form field in raw HTML by its id or name.
 * Pure function — kept separate from fetching so it can be proven against
 * known-good and known-broken markup.
 *
 * Returns { tag, type, raw } or null.
 */
function findField(html, fieldName) {
  const escaped = fieldName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const pattern = new RegExp(
    `<(input|select|textarea)\\b[^>]*\\b(?:id|name)=["']${escaped}["'][^>]*>`,
    'i'
  );
  const match = html.match(pattern);
  if (!match) return null;
  const raw = match[0];
  const typeMatch = raw.match(/\btype=["']([^"']+)["']/i);
  return {
    tag: match[1].toLowerCase(),
    type: typeMatch ? typeMatch[1].toLowerCase() : null,
    raw,
  };
}

module.exports = { fetchPage, findField, BASE };
