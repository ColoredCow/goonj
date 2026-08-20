# Microsite 80(G) Certificate — QA Cases

Conditional validation on microsite contribution pages: selecting **"I would like to
receive 80(G) Certificate"** makes Address mandatory. Mobile Number stays optional by
design.

Origin: goonj-crm#371. Case IDs match the QA sheet one-for-one.

**Status** — `pending` (case written, no check yet), `draft` (check written but never
executed), `ready` (check has run and has been proven to fail when the behaviour is
broken), `blocked` (needs something we don't have).

**Manual** — result of the manual pass on staging, 10 Aug 2026. Where a case did not
hold, the detail is recorded on the tracked issue rather than here.

**Automation** — `api` (assertable over HTTP / APIv4 / MailDev, no browser),
`browser` (needs the rendered page), `manual` (stays a human step).

Environment: `staging-crm.goonj.org/contribute/rahat-flood-campaign-supported-by-exl/`
Outgoing mail: MailDev.

---

## Address validation

### TC-371-01 — 80(G) selected with Address blank is blocked
Automation: browser · Manual: pass · Status: pending

    Given the microsite contribution page with amount, first name, last name and email filled
    When "I would like to receive 80(G) Certificate" is ticked and Address is left blank
    And Contribute is clicked
    Then the form does not submit
    And an error appears beside Address reading "Address is required to issue the 80(G)
      Certificate. If you don't need the certificate, please uncheck this option"

### TC-371-02 — 80(G) selected with Address filled submits
Automation: api · Manual: pass · Status: pending

    Given all mandatory fields are filled
    When the 80(G) checkbox is ticked and a valid Address is entered
    And the form is submitted
    Then the form submits successfully and proceeds to payment
    And no address error is shown

### TC-371-03 — 80(G) not selected with Address blank submits
Automation: api · Manual: pass · Status: pending

    Given all mandatory fields are filled
    When the 80(G) checkbox is left unticked and Address is left blank
    And the form is submitted
    Then the form submits successfully
    And Address remains optional with no error

### TC-371-04 — Unticking 80(G) clears the address error
Automation: browser · Manual: pass · Status: pending

    Given 80(G) is ticked with Address blank and the error has been triggered
    When the 80(G) checkbox is unticked
    Then the error message clears and Address reverts to optional
    And the form can be submitted with Address still blank

### TC-371-05 — Whitespace-only address is treated as blank
Automation: browser · Manual: see goonj-crm#371 · Status: pending

    Given 80(G) is ticked
    When only spaces are entered in Address and the form is submitted
    Then the form is blocked with the same address-required error
    And whitespace is not accepted as a valid address

### TC-371-06 — Error message wording matches the approved copy
Automation: browser · Manual: pass · Status: pending

    Given the address error has been triggered
    When the on-screen text is compared with the approved wording
    Then it reads exactly "Address is required to issue the 80(G) Certificate. If you
      don't need the certificate, please uncheck this option"

### TC-371-07 — Error renders in the site's existing error style beside the field
Automation: browser · Manual: pass · Status: pending

    Given the address error has been triggered
    When placement and styling are inspected
    Then the error appears beside or below the Address field
    And uses the same styling as other CiviCRM field errors on the page
    And is not a browser-native alert or a page-top-only message

---

## Mobile stays optional

### TC-371-08 — 80(G) selected with Mobile blank still submits
Automation: api · Manual: pass · Status: pending

    Given all mandatory fields are filled and a valid Address is entered
    When 80(G) is ticked and Mobile Number is left blank
    And the form is submitted
    Then the form submits successfully
    And Mobile Number is not blocked — it stays optional by design

### TC-371-09 — 80(G) not selected with Mobile blank submits
Automation: api · Manual: pass · Status: pending

    Given all mandatory fields are filled
    When 80(G) is left unticked and Mobile Number is left blank
    And the form is submitted
    Then the form submits successfully with no mobile validation triggered

---

## Profile-level binding

### TC-371-10 — Validation applies to any page using the MS Individual profile
Automation: browser · Manual: pass · Status: pending

    Given a different contribution page that uses the MS Individual profile
    When 80(G) is ticked with Address left blank and the form is submitted
    Then the same address-required error appears
    And the behaviour is bound to the profile, not to one page ID

---

## Regression

### TC-371-11 — Standard individual contribution page is unaffected
Automation: browser · Manual: pass · Status: pending

    Given a standard (non-microsite) individual contribution page
    When a contribution is completed and submitted
    Then the page behaves exactly as it did before this change
    And Address remains mandatory there as it always was
    And no new or duplicated error messages appear

### TC-371-12 — Pages not using the MS Individual profile are unaffected
Automation: api · Manual: pass · Status: pending

    Given a contribution page using a different profile
    When 80(G) is ticked if present, Address left blank, and the form submitted
    Then no new address validation is applied
    And the page behaves as it did before the change

---

## Receipt and email

### TC-371-13 — Receipt shows NA when Address is blank
Automation: api · Manual: pass · Status: pending

    Given a contribution submitted with Address blank
    When the receipt email is opened in MailDev
    Then the Address field displays "NA"
    And it is not blank and shows no stray placeholder

### TC-371-14 — Receipt shows NA when Mobile Number is blank
Automation: api · Manual: pass · Status: pending

    Given a contribution submitted with Mobile Number blank
    When the receipt is opened in MailDev
    Then the Mobile Number field displays "NA"

### TC-371-15 — Receipt shows NA for both when Mobile and Address are blank
Automation: api · Manual: pass · Status: pending

    Given a contribution submitted with both Mobile and Address blank and 80(G) unticked
    When the receipt is opened
    Then both fields display "NA"
    And the receipt layout is not broken by the missing values

### TC-371-16 — Receipt shows real values when both fields are filled
Automation: api · Manual: pass · Status: pending

    Given a contribution submitted with Mobile and Address filled
    When the receipt is opened
    Then the receipt shows the actual submitted values
    And no "NA" appears anywhere

### TC-371-17 — 80(G) receipt carries Address and PAN
Automation: api · Manual: pass · Status: pending

    Given an 80(G) contribution has been submitted
    When the receipt email is opened in MailDev
    Then the receipt includes the PAN number and the full address as submitted

---

## PAN interaction

### TC-371-18 — PAN field appears when 80(G) is ticked
Automation: browser · Manual: pass · Status: pending

    Given the page is open with 80(G) unticked
    When the 80(G) checkbox is ticked
    Then the PAN Card Number field becomes visible
    And it is hidden again when unticked

### TC-371-19 — PAN input auto-uppercases
Automation: browser · Manual: pass · Status: pending

    Given a PAN is typed in lowercase into the PAN field
    When focus moves away
    Then the value is converted to uppercase

### TC-371-20 — Wrong PAN format is blocked
Automation: browser · Manual: pass · Status: pending

    Given a badly formatted PAN is entered
    When the form is submitted
    Then the form is blocked with "Invalid PAN card format. Correct format: ABCDE1234F
      (5 letters, 4 digits, 1 letter)."

### TC-371-21 — Well-formed but unverifiable PAN is blocked
Automation: browser · Manual: pass · Status: pending

    Given a correctly formatted but non-issued PAN is entered
    When the form is submitted
    Then the form is blocked with the PAN verification failure message
    And the message asks the contributor to re-check the PAN or untick 80(G)

### TC-371-22 — Blank PAN with 80(G) ticked
Automation: api · Manual: see goonj-crm#371 · Status: blocked

    Given 80(G) is ticked and Address is filled
    When PAN is left completely blank and the form is submitted
    Then submission should be blocked

> Blocked: whether a blank PAN should be rejected has been raised on goonj-crm#371 and
> is not yet confirmed. Do not treat this as a defect until the requirement is agreed.

### TC-371-23 — PAN not required when 80(G) is unticked
Automation: api · Manual: pass · Status: pending

    Given 80(G) is left unticked
    When the contribution is completed and submitted
    Then no PAN validation is applied and the contribution submits normally

---

## Page setup

### TC-371-24 — City renders as free text under the Contribute parent page
Automation: api · Manual: pass · Status: **ready**

    Given the microsite page is opened via the /contribute/ path
    When the City field is inspected
    Then City is a text input, not a dropdown

> Check: `tests/qa/microsite/TC-371-24.js`. Needs no credentials — the page is public,
> so the field is read straight from the HTML. Originally tagged `browser`; it turned
> out to need no browser at all.

---

## End to end

### TC-371-25 — Full contribution completes successfully with 80(G)
Automation: manual · Manual: pass · Status: blocked

    Given the form is completed with 80(G) ticked, a valid Address and a valid PAN
    When the form is submitted and the payment is completed
    Then the payment completes and the thank-you page is shown
    And the contribution is recorded in CiviCRM with the correct amount

> Blocked for automation: completing a real payment is a human step.

---

## Data persistence

### TC-371-26 — Address is saved on the contact record
Automation: api · Manual: pass · Status: pending

    Given a test contribution has been submitted with an Address
    When the contact created by that contribution is opened in CiviCRM
    Then the submitted address is stored against the contact exactly as entered
