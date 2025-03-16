import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, userLogout,submitInstituteDroppingCenterRegistrationForm, instituteDroppingCenterUserDetails, urbanOpsUserLogin} from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';
import { InductedVolunteerPage } from '../pages/inducted-volunteer.page';
import { InstituteDroppingCenterPage } from '../pages/institute-dropping-center-registration.page.js';


test.describe('Institute dropping center  registration', () => {
  let volunteerProfilePage;
  let inductedVolunteerPage
  let instituteDroppingCenterPage
  const contactType = 'Individual';
  test.beforeEach(async ({ page }) => {
    instituteDroppingCenterPage = new InstituteDroppingCenterPage(page);
    await page.waitForTimeout(2000);
  });

  test('Verifying institute dropping form submission', async ({ page }) => {
    await submitInstituteDroppingCenterRegistrationForm(page, instituteDroppingCenterUserDetails)
    await page.waitForTimeout(3000)
    await urbanOpsUserLogin(page)
    await page.waitForTimeout(3000)
});
    
});
