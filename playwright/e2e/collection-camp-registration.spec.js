import { test, expect } from '@playwright/test';
import { userDetails, userLogin, submitVolunteerRegistrationForm, searchAndVerifyContact, userLogout, userFormLogin } from '../utils.js';
import { VolunteerProfilePage } from '../pages/volunteer-profile.page';
import { InductedVolunteerPage } from '../pages/inducted-volunteer.page';
import { CollectionCampPage } from '../pages/collection-camp-registration.page'; // Adjust the path


test.describe('Collection camp registration', () => {
  let volunteerProfilePage;
  let inductedVolunteerPage
  let collectionCampPage
  const contactType = 'Individual';
  test.beforeEach(async ({ page }) => {
    volunteerProfilePage = new VolunteerProfilePage(page);
    inductedVolunteerPage  = new InductedVolunteerPage(page);
    collectionCampPage = new CollectionCampPage(page);
    // await submitVolunteerRegistrationForm(page, userDetails);
    // await page.waitForTimeout(2000);
    // await userLogin(page);
  });

  test('schedule induction and update induction status as completed', async ({ page }) => {
    let userEmailAddress = userDetails.email.toLowerCase()
    let userMobileNumber = userDetails.mobileNumber
    let userName = userDetails.firstName
    // const updatedStatus = 'Completed'
    // await searchAndVerifyContact(page, userDetails, contactType);
    // await volunteerProfilePage.volunteerProfileTabs('activities');
    // await volunteerProfilePage.updateInductionForm('Induction', 'To be scheduled', 'Edit', 'Scheduled', 'save')
    // await page.waitForTimeout(3000)
    // await volunteerProfilePage.updateInductionForm('Induction', 'Scheduled', 'Edit', updatedStatus, 'save')
    // await volunteerProfilePage.verifyInductionActivity(updatedStatus)
    // await page.click('a:has-text("Volunteers")');
    // await page.waitForTimeout(3000)
    // await volunteerProfilePage.clickVolunteerSuboption('Active')
    // await page.waitForTimeout(7000)
    // await inductedVolunteerPage.checkInductedVolunteerEmailExists(userEmailAddress)
    // await page.waitForTimeout(3000)
    // await userLogout(page)
    await page.goto(collectionCampPage.getAppendedUrl('/collection-camp/')); // Replace with your URL
    await page.locator('div.login-submit a.button.button-primary').click();
    await userFormLogin(page, 'brahmabrata_mehrotra@hotmail.com', '9376289162' )
    await page.waitForTimeout(3000)
  await collectionCampPage.selectYouWishToRegisterAs('An individual'); // Adjust option as needed
  await collectionCampPage.enterLocationAreaOfCamp('Some address');
  await collectionCampPage.enterCity('Some City');
  await collectionCampPage.selectState('Andhra Pradesh'); // Adjust option as needed
  await collectionCampPage.enterPinCode('123456');
  await collectionCampPage.enterStartDate('1/03/2025');  //  MM/DD/YYYY Format (Check your date format)
  await collectionCampPage.enterStartTime('10:00'); //Example Time
  await collectionCampPage.enterEndDate('3/03/2025');  //  MM/DD/YYYY Format (Check your date format)
  await collectionCampPage.enterEndTime('14:00'); //Example Time
  await collectionCampPage.selectPermissionLetter('1');  
  await collectionCampPage.selectPublicCollection('1');  
  await collectionCampPage.selectEngagingActivity('2'); 
  await collectionCampPage.enterVolunteerName('Volunteer Name');
  await collectionCampPage.enterVolunteerContactNumber('9876543210');

  await collectionCampPage.clickSubmitButton();
  await page.waitForTimeout(3000)
  await collectionCampPage.verifyUrlAfterFormSubmission('/success');  // Replace with your success URL
});
    
  });
