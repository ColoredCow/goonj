import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, userLogout, submitInstituteCollectionCampRegistrationForm, instituteCollectionCampUserDetails, urbanOpsUserLogin} from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';
import { InductedVolunteerPage } from '../pages/inducted-volunteer.page';
import { InstituteCollectionCampPage } from '../pages/institute-collection-camp-registration.page'; // Adjust the path
import { CollectionCampRecordsPage  } from '../pages/collection-camp-records.page'; // Adjust the path


test.describe('Institute collection camp registration', () => {
  let volunteerProfilePage;
  let inductedVolunteerPage;
  let instituteCollectionCampPage;
  let collectionCampRecordsPage;
  const contactType = 'Individual';
  test.beforeEach(async ({ page }) => {
    volunteerProfilePage = new VolunteerProfilePage(page);
    inductedVolunteerPage  = new InductedVolunteerPage(page);
    instituteCollectionCampPage = new InstituteCollectionCampPage(page);
    await page.waitForTimeout(2000);
  });

  test('Verifying collection camp is created after form submission', async ({ page }) => {
    await submitInstituteCollectionCampRegistrationForm(page, instituteCollectionCampUserDetails)
    await page.waitForTimeout(3000)
    await urbanOpsUserLogin(page)
    await page.waitForTimeout(3000)
});
    
});
