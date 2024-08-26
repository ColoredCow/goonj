import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact  } from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';

test('Add a volunteer to Lead Volunteer group', async ({ page }) => {
  const volunteerProfilePage = new VolunteerProfilePage(page);
  const individualContactType = 'Individual'
  const volunteerContactType = '- Volunteer'
  let userEmailAddress = userDetails.email.toLowerCase()
  await submitVolunteerRegistrationForm(page, userDetails);
  await page.waitForTimeout(2000)
  await userLogin(page);
  await searchAndVerifyContact(page, userDetails, individualContactType)
  await volunteerProfilePage.volunteerProfileTabs('activities');
  await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Scheduled', 'save')
  await page.waitForTimeout(3000)
  await volunteerProfilePage.updateInductionForm('Induction', 'Scheduled', 'Edit', 'Completed', 'save')
  await page.click('a:has-text("Volunteers")');
  await page.waitForTimeout(3000)
  await volunteerProfilePage.clickVolunteerSuboption('Active')
  await page.waitForTimeout(8000)
  await page.fill('#contact-email-contact-id-01-email-0', userEmailAddress)
  await page.press('#contact-email-contact-id-01-email-0', 'Enter')
  await page.waitForTimeout(2000)
  const emailSelector = 'td[data-field-name=""] span.ng-binding.ng-scope';
  const emailAddress = await page.$$eval(emailSelector, nodes =>
    nodes.map(n => n.innerText.trim())
  );
  // const userEmailAddress = userDetails.email.toLowerCase()
  expect(emailAddress).toContain(userEmailAddress)
  await searchAndVerifyContact(page, userDetails, volunteerContactType)
  await volunteerProfilePage.volunteerProfileTabs('groups');
  await page.waitForTimeout(1000)
  await volunteerProfilePage.selectAddToGroupOption('Lead Volunteers');
  await page.waitForTimeout(1000)
  await volunteerProfilePage.clickAddButton()
  await page.click('table#option11 a:has-text("Lead Volunteers")');
  await page.click('#searchForm summary:has-text("Find Contacts within this Group")');
  await searchAndVerifyContact(page, userDetails, volunteerContactType)
  const leadVolunteerRowSelector = `table tbody tr:has(span[title="${userEmailAddress}"])`;
  await page.waitForTimeout(1000)
  expect(leadVolunteerRowSelector).toContain(userEmailAddress)
});
