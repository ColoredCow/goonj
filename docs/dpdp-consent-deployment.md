# DPDP Consent — Deployment Steps

Everything below is done **by hand in the CiviCRM / WordPress admin**, in this order, on
**staging first, then production**. The code resolves every field by **name**, never by id,
so machine names must be typed exactly as written — a different name and the hooks stop
working silently.

Related: [ColoredCow/goonj-crm#386](https://github.com/ColoredCow/goonj-crm/issues/386).

---

## 1. Contact-level field group

`Administer → Customize Data and Screens → Custom Fields → Add Set`

| Setting | Value |
|---|---|
| Used For | **Contact** |
| Set Name | `DPDP_Consent` |
| Style | Inline |

Then add these four fields to that set:

| Field Label | Machine name | Data Type | Input Type | Option label (the text people read) |
|---|---|---|---|---|
| Consent Given | `Consent_Given` | Alphanumeric | Checkbox | one option, value `1` — **the consent sentence goes here** |
| Age 18+ Declared | `Age_18_Declared` | Alphanumeric | Checkbox | one option, value `1` — `I confirm I am 18 years or older` |
| Consent Date | `Consent_Date` | Date | Select Date | date only, no time |
| Consent Proof | `Consent_Proof` | File | File | — |

**Leave "Required" unticked on the field itself.** Required is set per form/profile instead,
otherwise the back-office contact screen becomes unusable.

**Never put the long wording in the Field Label** — it becomes the machine name. Wording
always goes on the **option label**.

---

## 2. Consent field on the two entity groups

Same shape each time: **Alphanumeric / Checkbox / one option, value `1` / not required**.

| Add to this existing group | New field | Covers |
|---|---|---|
| `Collection_Camp_Core_Details` (title "Core Details") | `Consent_Given` | the 3 individual intent forms — camp, dropping centre, Goonj activities |
| `Material_Contribution` | `Consent_Given` | all material contribution forms |

These two groups already hold the submitter (`Contact_Id` on Core Details, the activity's
own source contact), which is how the tick gets copied onto the right person.

---

## 3. Profiles — monetary pages

`Administer → Customize Data and Screens → Profiles`

| Profile | Field to add | Required | Position |
|---|---|---|---|
| **Individual Information** | `Consent_Given` | **Ticked** | last |
| **MS Individual Contribution** | `Consent_Given` | **Ticked** | last |

Adding it to *Individual Information* covers every active contribution page and every future
campaign page in one go.

**Monetary is consent only** — no age checkbox, no consent proof. That was Goonj's decision:
a minor's contribution would need guardian consent and relationship proof, which is out of
scope for now.

---

## 4. WordPress pages

| What | Step |
|---|---|
| Privacy policy | Publish the page, then `Settings → Privacy → select it`. The code reads the WordPress setting, not a slug, because WordPress's own draft squats on `/privacy-policy/`. |
| Consent notice | Publish a page whose slug is exactly **`consent-notice`**, wording in a Custom HTML block. **Check the permalink** — a duplicate slug becomes `consent-notice-2` and the "What we collect and why" toggle silently never appears. |

One notice page serves every form, so the wording is changed in one place.

---

## 5. Add the field to the forms — **Goonj team**

> **This step is Goonj team work, not ColoredCow's.** It is form configuration in
> FormBuilder and the profile screens, which the Goonj team already does day to day. It sits
> in the Goonj hours column of the estimate.

In FormBuilder, drop a **second fieldset for the same entity at the bottom of the form**
(the Add palette offers "Fieldset for &lt;entity&gt;"), put only the consent field in it, leave
the title blank. Two fieldsets for one entity is fine — Afform keys data by entity name, so
both write the same record.

The row label is hidden by CSS on every form, so there is no need to untick "show label".

### Which field to add

| Form group | Field to add |
|---|---|
| Collection camp / dropping centre / Goonj activities intent | **Core Details → Consent Given** |
| Material contribution forms | **Material Contribution → Consent Given** |
| Everything else below | **DPDP Consent → Consent Given** on the Individual |
| Event online registration | add to the **12 event profiles** |

### Checklist — 26 forms

| # | Form | Page | Done |
|---|---|---|---|
| 1 | afformCollectionCampIntentDetails | /collection-camp/intent | ✅ |
| 2 | afformDroppingCenterDetailForm | /dropping-center/intent | ☐ |
| 3 | afformGoonjActivitiesIndividualIntentForm | /goonj-activities/intent | ☐ |
| 4 | afformInstitutionCollectionCampIntent | /institution-collection-camp-intent | ☐ |
| 5 | afformInstitutionDroppingCenterIntent | /institution-dropping-center-intent | ☐ |
| 6 | afformInstitutionGoonjActivitiesIntent | /institution-goonj-activities-intent | ☐ |
| 7 | afformInstitutionGoonjActivitiesIntentDisasterExhibition | /institution-goonj-activities-intent-disaster-exhibition | ☐ |
| 8 | afformUrbanPlannedVisitIntentForm | /urban-planned-visit | ☐ |
| 9 | afformGoonjEngagementIntentForm | /engagement-with-goonj | ☐ |
| 10 | afformMaterialContribution | /material-contribution | ☐ |
| 11 | afformMaterialContributionForPU | /material-contribution/details | ☐ |
| 12 | afformMaterialContributionIndividualRegistration | /material-contribution/individual-registration | ☐ |
| 13 | afformDroppingCenterMaterialContribution | /dropping-center/material-contribution | ☐ |
| 14 | afformInstitutionCollectionCampMaterialContribution | /collection-camp-material-contribution | ☐ |
| 15 | afformInstitutionDroppingCenterMaterialContribution | /dropping-center-material-contribution | ☐ |
| 16 | afformEventsMaterialContribution | /events-material-contribution | ☐ |
| 17 | afformOfficeVisitDetails | /office-visit/details | ☐ |
| 18 | afformOfficeVisitIndividualRegistration | /office-visit/individual-registration | ☐ |
| 19 | afformIndividualRegistration1 | /volunteer-registration/form | ☐ |
| 20 | afformVolunteerFormWithDetails | /volunteer-registration/form-with-details | ☐ |
| 21 | afformVolunteerFormIndividual | /volunteer-form | ☐ |
| 22 | afformIndividualWithVolunteerRegistration | /individual-registration-with-volunteer-option | ☐ |
| 23 | afformIndividualSignupWithVolunteering | /individual-signup-with-volunteering | ☐ |
| 24 | afformVolunteerWithCollectionCampIntentDetails | /collection-camp/volunteer-with-intent | ☐ |
| 25 | afformInstitutionRegistration | /institute-registration-sign-up | ☐ |
| 26 | afformSubscriptionForm | /subscription-form | ☐ |

**Not in scope:** outcome, feedback, dispatch and acknowledgement forms (31 of them). They
only reach the person who created the camp, whose consent is already held.

---

## 6. Deploy the code

Normal deploy, then:

```bash
cv flush
```

If the CiviCRM container was cached per host, also remove the compiled container so the web
user picks up the new hooks:

```bash
find wp-content/uploads/civicrm -name "CachedCiviContainer*" -delete
```

---

## 7. Checks after deploy

| Check | Expected |
|---|---|
| Open any contribution page | Consent row is the last field, with `*`, and shows "What we collect and why · Privacy Policy" |
| Click "What we collect and why" | Notice panel opens with the wording |
| Click "Privacy Policy" | Opens in an overlay, the form behind it is not lost |
| Submit a contribution page with consent unticked | Blocked, "This field is required" against the consent row |
| Open any check-user page | Notice line above Continue, **no checkbox** |
| Fill the check-user step as a contact who already consented | Next form does **not** ask for consent again |
| Submit a collection camp intent form with consent ticked | On that contact: Consent Given ✓, Age 18+ Declared ✓, Consent Date = today |
| Submit a second form as the same person | Consent Date **unchanged** — first consent wins |
| Back office: open a contact, tick Consent Given only, save | Consent saved, date stamped, **Age 18+ Declared stays empty** |
| Phone width | No sideways scroll; toggle and policy link stack |

---

## Still needed from Goonj

| Item | Who |
|---|---|
| Final consent wording (goes on the option label) | Anant |
| Age checkbox wording + the under-18 "write to mail@goonj.org" message | Anant |
| Third-party declaration line for the intent forms | Anant |
| Privacy policy document and its URL | Anant |
