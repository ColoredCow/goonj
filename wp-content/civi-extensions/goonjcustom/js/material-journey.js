/**
 * PROTOTYPE: material-journey contribution page (step 6 of the Comms team doc).
 *
 * Loaded only on the "Material_Journey_Contribution" page (see
 * Civi\MaterialMonetaryAskService::setUpMaterialJourneyForm). Rows are found by
 * their core `editrow_` class or by the label-based `goonj-*` classes main.js
 * stamps, never by position.
 */
(function () {
  'use strict';

  var cfg = (window.CRM && CRM.vars && CRM.vars.goonjMaterialJourney) || {};

  function rowValue(row) {
    var input = row && row.querySelector('input[type="text"], input[type="email"], input:not([type])');
    return input ? input.value.trim() : '';
  }

  // Known donor: show name / email / mobile as one line; any empty field stays open.
  function collapseKnownDetails(form) {
    var notYou = form.querySelector('.crm-not-you-message');
    if (!notYou) {
      return;
    }
    var rows = {
      first: form.querySelector('.editrow_first_name-section'),
      last: form.querySelector('.editrow_last_name-section'),
      email: form.querySelector('[class*="editrow_email-"]'),
      phone: form.querySelector('[class*="editrow_phone-"]')
    };
    var collapsed = [];
    Object.keys(rows).forEach(function (key) {
      if (rows[key] && rowValue(rows[key])) {
        rows[key].classList.add('goonj-mj-collapsed');
        collapsed.push(rows[key]);
      }
    });
    if (!collapsed.length) {
      return;
    }

    var who = document.createElement('div');
    who.className = 'goonj-mj-who';
    var text = document.createElement('span');
    var name = document.createElement('b');
    name.textContent = (rowValue(rows.first) + ' ' + rowValue(rows.last)).trim();
    text.appendChild(name);
    [rowValue(rows.email), rowValue(rows.phone)].filter(Boolean).forEach(function (part) {
      text.appendChild(document.createTextNode(' · ' + part));
    });
    who.appendChild(text);

    var actions = document.createElement('span');
    var edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'goonj-mj-link';
    edit.textContent = 'Edit';
    edit.addEventListener('click', function () {
      collapsed.forEach(function (row) { row.classList.remove('goonj-mj-collapsed'); });
      who.remove();
    });
    actions.appendChild(edit);
    var link = notYou.querySelector('a');
    if (link) {
      var notYouLink = document.createElement('a');
      notYouLink.href = link.href;
      notYouLink.className = 'goonj-mj-link';
      notYouLink.textContent = 'Not you?';
      actions.appendChild(document.createTextNode(' · '));
      actions.appendChild(notYouLink);
    }
    who.appendChild(actions);

    var firstRow = collapsed[0];
    firstRow.parentNode.insertBefore(who, firstRow);
  }

  // Address rows move under the 80(G) tick and show only while it is ticked.
  // main.js setUp80gRequirements makes the address required while ticked.
  function addressOnlyFor80g(form) {
    var g80 = Array.prototype.find.call(form.querySelectorAll('.crm-section input[type="checkbox"]'), function (box) {
      var label = form.querySelector('label[for="' + box.id + '"]');
      return label && label.textContent.indexOf('80(G)') !== -1;
    });
    if (!g80) {
      return;
    }
    var g80Row = g80.closest('.crm-section');
    var rows = form.querySelectorAll(
      '[class*="editrow_street_address-"], [class*="editrow_state_province-"], [class*="editrow_city-"],' +
      '[class*="editrow_postal_code-"], [class*="editrow_country-"]'
    );
    var note = document.createElement('p');
    note.className = 'goonj-mj-note80 goonj-mj-addr';
    note.textContent = cfg.addressNote || '';

    var anchor = g80Row.nextSibling;
    g80Row.parentNode.insertBefore(note, anchor);
    Array.prototype.forEach.call(rows, function (row) {
      row.classList.add('goonj-mj-addr');
      g80Row.parentNode.insertBefore(row, anchor);
    });

    function sync() {
      form.querySelectorAll('.goonj-mj-addr').forEach(function (el) {
        el.classList.toggle('goonj-mj-show', g80.checked);
      });
    }
    g80.addEventListener('change', sync);
    sync();
  }

  // One tick: the consent checkbox also answers the "data is correct / Indian
  // citizen" confirmation, which stays stored on the contribution.
  function singleConsentTick(form) {
    var consent = form.querySelector('.goonj-consent-field input[type="checkbox"]');
    var confirmRow = form.querySelector('.goonj-confirm-field');
    var confirm = confirmRow && confirmRow.querySelector('input[type="radio"]');
    if (!consent || !confirm) {
      return;
    }
    confirmRow.classList.add('goonj-mj-hidden');
    var label = form.querySelector('label[for="' + consent.id + '"]');
    if (label && cfg.consentLabel) {
      label.textContent = cfg.consentLabel;
    }
    function sync() {
      confirm.checked = consent.checked;
    }
    consent.addEventListener('change', sync);
    sync();
  }

  function currentAmount(form) {
    var picked = form.querySelector('#priceset input[type="radio"]:checked');
    if (picked && picked.value !== '0') {
      return Number(picked.getAttribute('data-amount')) || 0;
    }
    var other = form.querySelector('#priceset input[type="text"]');
    return other ? Number(other.value) || 0 : 0;
  }

  // "₹500 sorts, packs and ships ___ kg of material" + amount on the button.
  function impactLine(form) {
    var priceset = form.querySelector('#priceset');
    if (!priceset) {
      return;
    }
    var line = document.createElement('div');
    line.className = 'goonj-mj-impact';
    priceset.appendChild(line);
    var button = form.querySelector('.crm-button_qf_Main_upload');

    function render() {
      var amount = currentAmount(form);
      var rupees = '₹' + amount.toLocaleString('en-IN');
      var kg = cfg.ratePerKg ? Math.round(amount / cfg.ratePerKg) : '___';
      line.textContent = amount ? rupees + ' sorts, packs and ships ' + kg + ' kg of material' : '';
      line.hidden = !amount;
      if (button) {
        button.lastChild.textContent = amount ? ' Contribute ' + rupees : ' Contribute';
      }
    }
    priceset.addEventListener('change', render);
    priceset.addEventListener('input', render);
    render();
  }

  // "₨ 200.00" -> "₹200"; the Other Amount box shows only for "Other Amount".
  function tidyAmounts(form) {
    var priceset = form.querySelector('#priceset');
    if (!priceset) {
      return;
    }
    priceset.querySelectorAll('.contribution_amount-content label').forEach(function (label) {
      var input = form.querySelector('#' + CSS.escape(label.getAttribute('for') || ''));
      var amount = input && Number(input.getAttribute('data-amount'));
      if (amount) {
        label.textContent = '₹' + amount.toLocaleString('en-IN');
      }
      else if (input && input.value === '0') {
        label.textContent = 'Other';
      }
    });
    var otherRow = priceset.querySelector('.other_amount-section');
    function sync() {
      var picked = priceset.querySelector('.contribution_amount-content input[type="radio"]:checked');
      if (otherRow) {
        otherRow.classList.toggle('goonj-mj-show', !!picked && picked.value === '0');
      }
    }
    priceset.addEventListener('change', sync);
    sync();
  }

  function init() {
    var form = document.querySelector('form.CRM_Contribute_Form_Contribution_Main.goonj-mj');
    if (!form) {
      return;
    }
    if (typeof window.goonjStampProfileRows === 'function') {
      window.goonjStampProfileRows();
    }
    tidyAmounts(form);
    collapseKnownDetails(form);
    addressOnlyFor80g(form);
    singleConsentTick(form);
    impactLine(form);
  }

  // After main.js has stamped the rows and wired consent / 80(G).
  window.addEventListener('load', init);
})();
