import { test, expect } from '@playwright/test';
import { instituteActivityIntentUserDetails, userLogin, submitInstituteActivityIntentForm, urbanOpsUserLogin } from '../utils.js';
import { AdminHomePage } from '../pages/admin-home.page';

test('Verifying institute activity intent form submission', async ({ page }) => {
  const adminHomePage = new AdminHomePage(page);
  const instituteName = instituteActivityIntentUserDetails.organizationName
  await submitInstituteActivityIntentForm(page, instituteActivityIntentUserDetails);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
});