import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact  } from '../utils.js';

test('submit the volunteer registration form and confirm on admin', async ({ page }) => {
  // const searchContactsPage = new SearchContactsPage (page);
  const contactType = 'Individual'
  await submitVolunteerRegistrationForm(page, userDetails);
  await page.waitForTimeout(2000)
  await userLogin(page);
  await searchAndVerifyContact(page, userDetails, contactType)
});