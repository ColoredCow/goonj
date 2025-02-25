import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, userLogout, submitCollectionCampRegistrationForm, collectionCampUserDetails, urbanOpsUserLogin} from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';
import { InductedVolunteerPage } from '../pages/inducted-volunteer.page';
import { CollectionCampPage } from '../pages/collection-camp-registration.page'; // Adjust the path
import { CollectionCampRecordsPage  } from '../pages/collection-camp-records.page'; // Adjust the path


test.describe('Collection camp registration', () => {
  let volunteerProfilePage;
  let inductedVolunteerPage
  let collectionCampPage
  let collectionCampRecordsPage
  const contactType = 'Individual';
  test.beforeEach(async ({ page }) => {
    volunteerProfilePage = new VolunteerProfilePage(page);
    inductedVolunteerPage  = new InductedVolunteerPage(page);
    collectionCampPage = new CollectionCampPage(page);
    collectionCampRecordsPage =  new CollectionCampRecordsPage(page);
    await submitVolunteerRegistrationForm(page, userDetails);
    await page.waitForTimeout(2000);
    await userLogin(page);
  });

  test('Verifying collection camp is created after form submission', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    let userMobileNumber = userDetails.mobileNumber
    const updatedStatus = 'Completed'
    await searchAndVerifyContact(page, userDetails, contactType);
    await volunteerProfilePage.volunteerProfileTabs('activities');
    await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Scheduled', 'save')
    await page.waitForTimeout(3000)
    await volunteerProfilePage.updateInductionForm('Induction', 'Scheduled', 'Edit', updatedStatus, 'save')
    await volunteerProfilePage.verifyInductionActivity(updatedStatus)
    await page.click('a:has-text("Volunteers")');
    await page.waitForTimeout(3000)
    await volunteerProfilePage.clickVolunteerSuboption('Active')
    await page.waitForTimeout(7000)
    await inductedVolunteerPage.checkInductedVolunteerEmailExists(userEmailAddress)
    await page.waitForTimeout(3000)
    await userLogout(page)
    await submitCollectionCampRegistrationForm(page, userEmailAddress, userMobileNumber, collectionCampUserDetails)
    await page.waitForTimeout(3000)
    await urbanOpsUserLogin(page)
    await page.waitForTimeout(3000)
    await collectionCampRecordsPage.clickReviewLinCollectionCamp(userDetails.firstName, collectionCampUserDetails.state)
    await collectionCampRecordsPage.selectCampaignForCollectionCamp('Rahat')
});
    
});
