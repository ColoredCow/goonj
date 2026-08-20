/**
 * TC-371-24 — City renders as free text under the Contribute parent page.
 *
 * The City field on microsite contribution pages must be a plain text input, so a
 * contributor can type any city. If it renders as a dropdown, anyone whose city is
 * not in the list cannot contribute.
 *
 * Needs no credentials — the page is public.
 *
 * See goonj-crm#371.
 */

'use strict';

const page = require('../lib/page');

const PATH = '/contribute/rahat-flood-campaign-supported-by-exl/';
const FIELD = 'city-Primary';

module.exports = {
  id: 'TC-371-24',
  title: 'City renders as free text, not a dropdown',
  origin: 'goonj-crm#371',
  area: ['page-setup', 'donor-facing', 'microsite'],
  paths: [
    'wp-content/themes/goonj-crm/**',
    'wp-content/civi-extensions/goonjcustom/**',
    'wp-content/plugins/goonj-blocks/**',
  ],
  verifiedBy: 'broken-markup fixture — see proveFails()',

  async run({ assert }) {
    const html = await page.fetchPage(PATH);
    const city = page.findField(html, FIELD);

    assert.ok(city, `the ${FIELD} field is present on ${PATH}`);
    assert.eq(city.tag, 'input', `${FIELD} renders as an input`);
    assert.eq(city.type, 'text', `${FIELD} is a text input`);
  },

  /**
   * Proof the check can fail: the same assertion logic against markup where City
   * is a dropdown. Run with `node tests/qa/microsite/TC-371-24.js`.
   */
  proveFails() {
    const brokenHtml = `
      <form>
        <label for="city-Primary">City</label>
        <select id="city-Primary" name="city-Primary">
          <option value="">- select City -</option>
          <option value="1">New Delhi</option>
        </select>
      </form>`;
    const city = page.findField(brokenHtml, FIELD);
    return { tag: city && city.tag, type: city && city.type, wouldPass: !!city && city.tag === 'input' };
  },
};

if (require.main === module) {
  console.log('proveFails():', JSON.stringify(module.exports.proveFails()));
}
