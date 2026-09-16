/**
 * Template for a QA check. Copy to tests/qa/<area>/<CASE-ID>.js.
 * Generated from a case in docs/qa/cases/ — never invent a case here.
 */

'use strict';

const civi = require('../lib/civi');

module.exports = {
  // Must match the case ID in docs/qa/cases/.
  id: 'MON-000',
  title: 'One line, same wording as the case',
  origin: 'goonj-crm#000 | register',

  // Used by --area and by CI to pick checks for a change.
  area: [],

  // Globs. If a changed file matches, this check runs at development time.
  paths: [],

  // How this check was proven to fail when the bug is present.
  // 'commit <sha>' | 'inverted assertion' | null if never proven.
  verifiedBy: null,

  async run({ assert }) {
    const tag = civi.fixtureTag(this.id);
    const created = [];

    try {
      // 1. Build your own fixtures. Never rely on existing records.
      // 2. Exercise the behaviour.
      // 3. Assert on data, with exact expected values.
      assert.ok(false, 'not implemented');
    } finally {
      // Always clean up, even when the check fails.
      for (const { entity, id } of created.reverse()) {
        await civi.del(entity, { id }).catch(() => {});
      }
    }
  },
};
