import { test, expect } from '@playwright/test';
import { monetaryContributionUserDetails, userLogin, submitMonetaryContributionForm, urbanOpsUserLogin } from '../utils.js';
import { AdminHomePage } from '../pages/admin-home.page';
import { IndividualMonetaryContributionPage } from '../pages/individual-monetary-contribution.page';


test.describe('Individual monetary contribution submission', () => {

test('Verifying monetary contribution submission for collection camp', async ({ page }) => {
  const individualMonetaryContributionPage  = new IndividualMonetaryContributionPage(page);
  const individualMonetaryContributionUrl = individualMonetaryContributionPage.getAppendedUrl('/contribute/?custom_554=970');
  await submitMonetaryContributionForm(page, monetaryContributionUserDetails, individualMonetaryContributionUrl);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
});


test('Verifying monetary contribution submission for dropping center', async ({ page }) => {
  const individualMonetaryContributionPage  = new IndividualMonetaryContributionPage(page);
  const individualMonetaryContributionUrl = individualMonetaryContributionPage.getAppendedUrl('/contribute/?custom_554=1128');
  await submitMonetaryContributionForm(page, monetaryContributionUserDetails, individualMonetaryContributionUrl);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
});
})