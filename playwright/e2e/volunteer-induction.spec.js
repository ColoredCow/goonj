import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, verifyUserByEmail } from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';
import { InductedVolunteerPage } from '../pages/inducted-volunteer.page';

test.describe('Volunteer Induction Tests', () => {
  let volunteerProfilePage;
  let inductedVolunteerPage
  const contactType = 'Individual';

  test.beforeEach(async ({ page }) => {
    volunteerProfilePage = new VolunteerProfilePage(page);
    inductedVolunteerPage  = new InductedVolunteerPage(page);
    await submitVolunteerRegistrationForm(page, userDetails);
    await page.waitForTimeout(2000);
    await userLogin(page);
  });

  test('schedule induction and update induction status as completed', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    let userName = userDetails.firstName
    await searchAndVerifyContact(page, userDetails, contactType);
    await volunteerProfilePage.volunteerProfileTabs('activities');
    await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Scheduled', 'save')
    await page.waitForTimeout(3000)
    await volunteerProfilePage.updateInductionForm('Induction', 'Scheduled', 'Edit', 'Completed', 'save')
    await page.click('a:has-text("Volunteers")');
    await page.waitForTimeout(3000)
    await volunteerProfilePage.clickVolunteerSuboption('Active')
    await page.waitForTimeout(7000)
    await inductedVolunteerPage.checkIfNameExists(userName)
    await inductedVolunteerPage.checkIfEmailExists(userEmailAddress)
  });

  test('update induction status as Not visited', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    let userName = userDetails.firstName
    await searchAndVerifyContact(page, userDetails, contactType);
    await volunteerProfilePage.volunteerProfileTabs('activities');
    await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Not Visited', 'save')
    await page.waitForTimeout(3000)
    await page.click('a:has-text("Volunteers")');
    await page.waitForTimeout(3000)
    await volunteerProfilePage.clickVolunteerSuboption('Active')
    await page.waitForTimeout(7000)
    await inductedVolunteerPage.checkIfNameExists(userName)
    await inductedVolunteerPage.checkIfEmailExists(userEmailAddress)
    
  });
});
