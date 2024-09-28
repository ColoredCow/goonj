import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, verifyVolunteerByStatus  } from '../utils.js';

test('submit the volunteer registration form and confirm on admin', async ({ page }) => {
  const status = 'New Signups'
  await submitVolunteerRegistrationForm(page, userDetails);
  await page.waitForTimeout(2000)
  await userLogin(page);
  await verifyVolunteerByStatus(page, userDetails, status)
});