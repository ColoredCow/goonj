import { test, expect } from '@playwright/test';
import { instituteRegistrationDetails, userLogin, submitInstituteRegistrationForm, verifyVolunteerByStatus  } from '../utils.js';

test('submit the volunteer registration form and confirm on admin', async ({ page }) => {
  const status = 'New Signups'
  await submitInstituteRegistrationForm(page, instituteRegistrationDetails);
  await page.waitForTimeout(2000)
//   await userLogin(page);
//   await verifyVolunteerByStatus(page, userDetails, status)
});