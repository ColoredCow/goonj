import { test, expect } from '@playwright/test';
import { instituteRegistrationDetails, userLogin, submitInstituteRegistrationForm, urbanOpsUserLogin } from '../utils.js';
import { AdminHomePage } from '../pages/admin-home.page';
import { InstituteRegistrationsRecordsPage } from '../pages/institute-registration-records.page.js'

test('submit the institute registration form and confirm by urban ops admin', async ({ page }) => {
  // const status = 'New Signups'
  const adminHomePage = new AdminHomePage(page);
  const instituteRegistrationsRecordsPage = new InstituteRegistrationsRecordsPage(page);
  const instituteName = instituteRegistrationDetails.organizationName
  const pocName = instituteRegistrationDetails.fullName()
  const authorizationStatus = 'In Review'
  await submitInstituteRegistrationForm(page, instituteRegistrationDetails);
  await page.waitForTimeout(2000)
  await urbanOpsUserLogin(page)
  await page.waitForTimeout(3000)
  await adminHomePage.clickInstitutesTab()
  await page.waitForTimeout(3000)
  // await instituteRegistrationsRecordsPage.registeredInstituteStatus(page, instituteName, pocName, authorizationStatus)
});