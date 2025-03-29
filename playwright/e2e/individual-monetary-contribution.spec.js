import { test, expect } from '@playwright/test';
import { monetaryContributionUserDetails, userLogin, submitMonetaryContributionForm, urbanOpsUserLogin } from '../utils.js';
import { AdminHomePage } from '../pages/admin-home.page';

test('Verifying monetary contribution submission', async ({ page }) => {
  const adminHomePage = new AdminHomePage(page);
  await submitMonetaryContributionForm(page, monetaryContributionUserDetails);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
});