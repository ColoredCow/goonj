import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, verifyUserByEmail } from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';

test.describe('Volunteer Induction Tests', () => {
  let volunteerProfilePage;
  const contactType = 'Individual';

  test.beforeEach(async ({ page }) => {
    volunteerProfilePage = new VolunteerProfilePage(page);
    await submitVolunteerRegistrationForm(page, userDetails);
    await page.waitForTimeout(2000);
    await userLogin(page);
  });

  test('schedule induction and update induction status as completed', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    await searchAndVerifyContact(page, userDetails, contactType);
    await volunteerProfilePage.volunteerProfileTabs('activities');
    await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Scheduled', 'save')
    await page.waitForTimeout(3000)
    await volunteerProfilePage.updateInductionForm('Induction', 'Scheduled', 'Edit', 'Completed', 'save')
    await page.click('a:has-text("Volunteers")');
    await page.waitForTimeout(3000)
    await volunteerProfilePage.clickVolunteerSuboption('Active')
    await page.waitForTimeout(7000)
    await page.fill('#contact-email-contact-id-01-email-0', userEmailAddress)
    await page.press('#contact-email-contact-id-01-email-0', 'Enter')
    await page.waitForTimeout(2000)
    const emailSelector = 'td[data-field-name=""] span.ng-binding.ng-scope';
    const emailAddress = await page.$$eval(emailSelector, nodes =>
      nodes.map(n => n.innerText.trim())
    );
    expect(emailAddress).toContain(userEmailAddress)
  });

  test('update induction status as cancelled', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    await searchAndVerifyContact(page, userDetails, contactType);
    await volunteerProfilePage.volunteerProfileTabs('activities');
    await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Cancelled', 'save')
    await page.waitForTimeout(3000)
    await page.click('a:has-text("Volunteers")');
    await page.waitForTimeout(3000)
    await volunteerProfilePage.clickVolunteerSuboption('Active')
    await page.waitForTimeout(7000)
    await page.fill('#contact-email-contact-id-01-email-0', userEmailAddress)
    await page.press('#contact-email-contact-id-01-email-0', 'Enter')
    await page.waitForTimeout(2000)
    const emailSelector = 'td[data-field-name=""] span.ng-binding.ng-scope';
    const emailAddress = await page.$$eval(emailSelector, nodes =>
      nodes.map(n => n.innerText.trim())
    );
    expect(emailAddress).not.toContain(userEmailAddress)
  });
});
