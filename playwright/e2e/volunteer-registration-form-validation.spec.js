import { test } from '@playwright/test';
import { userDetails, verifyUserExist } from '../utils.js';
import { VolunteerRegistrationPage} from '../pages/volunteer-registration.page.js'


test('check mandatory field validation for registration form fields', async ({ page }) => {
  const volunteerRegistrationPage = new VolunteerRegistrationPage(page);
  const volunteerPageUrl  = volunteerRegistrationPage.getAppendedUrl('/volunteer-registration/');
  await page.goto(volunteerPageUrl);
  await page.waitForURL(volunteerPageUrl);
  await verifyUserExist(page, userDetails);
  await volunteerRegistrationPage.handleDialogMessage('Please fill all required fields.'); // Registering dialog message 
  await volunteerRegistrationPage.fillAndClearField('enterFirstName', userDetails.firstName);
  await volunteerRegistrationPage.fillAndClearField('enterLastName', userDetails.lastName);
  await volunteerRegistrationPage.fillAndClearField('enterEmail', userDetails.email);
  await volunteerRegistrationPage.fillAndClearField('enterMobileNumber', userDetails.mobileNumber);
  await volunteerRegistrationPage.selectGenderAndClear(userDetails.gender);
  await volunteerRegistrationPage.fillAndClearField('enterStreetAddress', userDetails.streetAddress);
  await volunteerRegistrationPage.fillAndClearField('enterCityName', userDetails.cityName);
  await volunteerRegistrationPage.fillAndClearField('enterPostalCode', userDetails.postalCode);
  await volunteerRegistrationPage.selectProfessionAndClear(userDetails.profession);
  await volunteerRegistrationPage.selectStateAndClear(userDetails.state);
  await volunteerRegistrationPage.selectActivityInterestedAndClear(userDetails.activityInterested);
  await volunteerRegistrationPage.selectVolunteerMotivationAndClear(userDetails.volunteerMotivation);
  await volunteerRegistrationPage.selectVoluntarySkillsAndClear(userDetails.voluntarySkills);
  await volunteerRegistrationPage.selectVolunteerHoursAndClear(userDetails.volunteerHours)
  await volunteerRegistrationPage.selectContactMethodAndClear(userDetails.contactMethod)
  await volunteerRegistrationPage.selectReferralSourceAndClear(userDetails.referralSource)
  await volunteerRegistrationPage.fillAndClearField('enterHealthIssues', userDetails.mobileNumber);
  await volunteerRegistrationPage.fillAndClearField('enterComments', userDetails.comments);
});
