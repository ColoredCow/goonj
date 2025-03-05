import { test, expect } from '@playwright/test';
import { instituteRegistrationDetails, userLogin, submitInstituteRegistrationForm,  urbanOpsUserLogin, verifyVolunteerByStatus  } from '../utils.js';

test('submit the volunteer registration form and confirm on admin', async ({ page }) => {
  const status = 'New Signups'
  await submitInstituteRegistrationForm(page, instituteRegistrationDetails);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
});