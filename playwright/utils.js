import { expect } from '@playwright/test';
import { faker } from '@faker-js/faker/locale/en_IN'; // Import the Indian locale directly
import { VolunteerRegistrationPage } from '../playwright/pages/volunteer-registration.page';
import { SearchContactsPage } from '../playwright/pages/search-contact.page';
import { AdminHomePage } from '../playwright/pages/admin-home.page';
import { DroppingCenterPage } from '../playwright/pages/dropping-center-registration.page';
import { CollectionCampPage } from '../playwright/pages/collection-camp-registration.page';
import { InstituteRegistrationPage } from '../playwright/pages/institute-registration.page';
import { InstituteCollectionCampPage } from '../playwright/pages/institute-collection-camp-registration.page';
import { InstituteDroppingCenterPage } from '../playwright/pages/institute-dropping-center-registration.page';
import { InstituteActivityIntentPage } from '../playwright/pages/institute-activity-registration.page';
import { IndividualMonetaryContributionPage } from '../playwright/pages/individual-monetary-contribution.page.js';
import { InductedVolunteerPage } from '../playwright/pages/inducted-volunteer.page';

// Helper function to generate an Indian mobile number
const generateIndianMobileNumber = () => {
  const prefix = faker.helpers.arrayElement(['7', '8', '9']); // Indian mobile numbers start with 7, 8, or 9
  const number = faker.number.int({ min: 1000000000, max: 9999999999 }).toString().slice(1);
  return `${prefix}${number}`;
};

// Generate user details using Faker with Indian locale
export const userDetails = {
  nameInitial: faker.helpers.arrayElement(['Mr.', 'Dr.', 'Mr']),
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  email: faker.internet.email(),
  country: 'India',
  mobileNumber: generateIndianMobileNumber(), // Generate Indian mobile number
  gender: faker.helpers.arrayElement(['Male', 'Female', 'Other']),
  streetAddress: faker.location.streetAddress(),
  cityName: faker.location.city('Delhi'),
  postalCode: faker.location.zipCode('######'), // Indian postal code format
  state: faker.helpers.arrayElement(['Haryana', 'Delhi', 'Uttar Pradesh']),
  activityInterested: faker.helpers.arrayElement(['Organise fundraising activities to support Goonj’s initiatives.', 'Hold Chuppi Todo Baithak’s to generate awareness on Menstruation']), 
  voluntarySkills: faker.helpers.arrayElement(['Marketing', 'Content Writing']), 
  // otherSkills: faker.helpers.arrayElement(['Research', 'Content Writing']),
  volunteerMotivation: faker.helpers.arrayElement(['Learn new skills', 'Use my skills']),
  volunteerHours: faker.helpers.arrayElement(['2 to 6 hours daily', '2 to 6 hours weekly', '2 to 6 hours monthly']),
  profession: faker.helpers.arrayElement(['Homemaker', 'Government Employee']),
  contactMethod: faker.helpers.arrayElement(['Whatsapp', 'Mail', 'Both']),
  referralSource: faker.helpers.arrayElement(['Newspaper', 'Website', 'Social media']),
  healthIssues: 'None',
  comments: faker.helpers.arrayElement(['Nice initiative', 'None']),
};

  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1); // Set the start date to tomorrow
  const dayAfterTomorrow = new Date(tomorrow);
  dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 1); // Set the end date to the day after tomorrow
  // Format the dates as DD/MM/YYYY
  const formattedStartDate = `${String(tomorrow.getDate()).padStart(2, '0')}/${String(tomorrow.getMonth() + 1).padStart(2, '0')}/${tomorrow.getFullYear()}`;
  const formattedEndDate = `${String(dayAfterTomorrow.getDate()).padStart(2, '0')}/${String(dayAfterTomorrow.getMonth() + 1).padStart(2, '0')}/${dayAfterTomorrow.getFullYear()}`;

export const collectionCampUserDetails = {
  registerAsIndividual: 'An individual.',
  address: faker.location.streetAddress(),
  cityName: 'Delhi',
  state: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  startDate: formattedStartDate,
  startTime: '10:00',
  endDate: formattedEndDate,
  endTime: '10:00',
  volunteerName: 'Kama Gupta-Asan',
  contactNumber: '9376289162',
};

export async function submitCollectionCampRegistrationForm(page, userEmailAddress, userMobileNumber, collectionCampUserDetails) {
  const collectionCampPage  = new CollectionCampPage(page);
  const collectionCampUrl = collectionCampPage.getAppendedUrl('/collection-camp/');
  const registrationConfirmationText = '/success'
  await page.goto(collectionCampUrl);
  await page.locator('div.login-submit a.button.button-primary').click();
  await userFormLogin(page, userEmailAddress, userMobileNumber)
  await page.waitForTimeout(3000)
  await collectionCampPage.selectYouWishToRegisterAs('An individual');
  await collectionCampPage.enterLocationAreaOfCamp(collectionCampUserDetails.address);
  await collectionCampPage.enterCity(collectionCampUserDetails.cityName);
  await collectionCampPage.selectState('Delhi')
  await collectionCampPage.enterPinCode(collectionCampUserDetails.postalCode);
  await collectionCampPage.enterStartDate(collectionCampUserDetails.startDate);  //  MM/DD/YYYY Format (Check your date format)
  await collectionCampPage.enterStartTime(collectionCampUserDetails.startTime); 
  await collectionCampPage.enterEndDate(collectionCampUserDetails.endDate);  //  MM/DD/YYYY Format (Check your date format)
  await collectionCampPage.enterEndTime(collectionCampUserDetails.endTime); 
  await collectionCampPage.selectPermissionLetter('1');  
  await collectionCampPage.selectPublicCollection('1');  
  await collectionCampPage.selectEngagingActivity('2'); 
  await page.waitForTimeout(2000)
  await collectionCampPage.clickSubmitButton();
  await page.waitForTimeout(4000)
  await collectionCampPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};

export const instituteCollectionCampUserDetails = {
  registerAs: 'Corporate',
  organizationName: faker.company.name(),
  address: faker.location.streetAddress(),
  cityName: 'Delhi',
  state: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  startDate: formattedStartDate,
  startTime: '10:00',
  endDate: formattedEndDate,
  endTime: '10:00',
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  fullName: function () {
    return `${this.firstName} ${this.lastName}`;
  },
  contactEmail: faker.internet.email(),
  contactPhoneNumber: generateIndianMobileNumber(),
};

export async function submitInstituteCollectionCampRegistrationForm(page, userEmailAddress, userMobileNumber, collectionCampUserDetails) {
  const instituteCollectionCampPage = new InstituteCollectionCampPage(page);
  const instituteCollectionCampUrl = instituteCollectionCampPage.getAppendedUrl('/institution-collection-camp-intent');
  const registrationConfirmationText = '/success'
  await page.goto(instituteCollectionCampUrl);
  // await userFormLogin(page, userEmailAddress, userMobileNumber)
  await page.waitForTimeout(3000)
  await instituteCollectionCampPage.selectYouWishToRegisterAs(instituteCollectionCampUserDetails.registerAs);
  await instituteCollectionCampPage.enterOrganizationName(instituteCollectionCampUserDetails.organizationName);
  await instituteCollectionCampPage.enterLocationAreaOfCamp(instituteCollectionCampUserDetails.address);
  await instituteCollectionCampPage.enterCity(instituteCollectionCampUserDetails.cityName);
  await instituteCollectionCampPage.selectState(instituteCollectionCampUserDetails.state);
  await instituteCollectionCampPage.enterPinCode(instituteCollectionCampUserDetails.postalCode);
  await instituteCollectionCampPage.enterStartDate(instituteCollectionCampUserDetails.startDate);  //  MM/DD/YYYY Format (Check your date format)
  await instituteCollectionCampPage.enterStartTime(instituteCollectionCampUserDetails.startTime); 
  await instituteCollectionCampPage.enterEndDate(instituteCollectionCampUserDetails.endDate);  //  MM/DD/YYYY Format (Check your date format)
  await instituteCollectionCampPage.enterEndTime(instituteCollectionCampUserDetails.endTime);   
  await instituteCollectionCampPage.selectPublicCollection('1');  
  await instituteCollectionCampPage.selectEngagingActivity('2'); 
  await page.waitForTimeout(2000)
  await instituteCollectionCampPage.enterFirstName(instituteRegistrationDetails.firstName);
  await page.waitForTimeout(200);
  await instituteCollectionCampPage.enterLastName(instituteRegistrationDetails.lastName);
  await instituteCollectionCampPage.enterPhoneNumber(instituteRegistrationDetails.contactPhoneNumber);
  await instituteCollectionCampPage.enterContactEmail(instituteRegistrationDetails.contactEmail);
  await instituteCollectionCampPage.clickSubmitButton();
  await page.waitForTimeout(4000)
  await instituteCollectionCampPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};

export const droppingCenterUserDetails = {
  registerAsIndividual: 'An individual.',
  address: faker.location.streetAddress(),
  landmarkArea: 'dwarka',
  cityName: 'Delhi',
  state: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  startDate: formattedStartDate,
  daysAndTimings: 'Thursday 8-10am',
  volunteerName: 'Kama Gupta-Asan',
  contactNumber: '9376289162',
};

export async function submitDroppingCenterRegistrationForm(page, userEmailAddress, userMobileNumber, droppingCenterUserDetails) {
  const droppingCenterPage  = new DroppingCenterPage(page);
  const droppingCenterUrl = droppingCenterPage.getAppendedUrl('/dropping-center/');
  const registrationConfirmationText = '/success'
  await page.goto(droppingCenterUrl);
  await userFormLogin(page, userEmailAddress, userMobileNumber)
  // await droppingCenterPage.clickContinueButton()
  await page.waitForTimeout(3000)
  await droppingCenterPage.selectYouWishToRegisterAs('An individual');
  await droppingCenterPage.enterLocationAreaOfCamp(droppingCenterUserDetails.address);
  await droppingCenterPage.enterLandMarkAreaOrNearbyArea(droppingCenterUserDetails.landmarkArea);
  await droppingCenterPage.enterCity(droppingCenterUserDetails.cityName);
  await droppingCenterPage.selectState('Delhi')
  await droppingCenterPage.enterPinCode(droppingCenterUserDetails.postalCode);
  await droppingCenterPage.enterStartDate(droppingCenterUserDetails.startDate);  //  MM/DD/YYYY Format (Check your date format)
  await droppingCenterPage.enterDaysAndTiming(droppingCenterUserDetails.daysAndTimings);
  await droppingCenterPage.selectPermissionLetter('1');  
  await droppingCenterPage.selectPublicCollection('1');  
  await droppingCenterPage.selectDonationBoxMonetaryContribution('2'); 
  await page.waitForTimeout(2000)
  await droppingCenterPage.clickSubmitButton();
  await page.waitForTimeout(4000)
  await droppingCenterPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};

export const instituteDroppingCenterUserDetails = {
  registerAs: 'Corporate',
  organizationName: faker.company.name(),
  address: faker.location.streetAddress(),
  landmarkArea: 'dwarka',
  cityName: 'Delhi',
  state: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  startDate: formattedStartDate,
  daysAndTimings: 'Thursday 8-10am',
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  fullName: function () {
    return `${this.firstName} ${this.lastName}`;
  },
  contactEmail: faker.internet.email(),
  contactPhoneNumber: generateIndianMobileNumber(),
};

export async function submitInstituteDroppingCenterRegistrationForm(page, instituteDroppingCenterUserDetails) {
  const droppingCenterPage  = new DroppingCenterPage(page);
  const instituteDroppingCenterPage = new InstituteDroppingCenterPage(page)
  const instituteDroppingCenterUrl = droppingCenterPage.getAppendedUrl('/institution-dropping-center-intent/');
  const registrationConfirmationText = '/success'
  await page.goto(instituteDroppingCenterUrl);
  // await userFormLogin(page, userEmailAddress, userMobileNumber)
  // await droppingCenterPage.clickContinueButton()
  await page.waitForTimeout(3000)
  await instituteDroppingCenterPage.selectYouWishToRegisterAs(instituteDroppingCenterUserDetails.registerAs);
  await instituteDroppingCenterPage.enterOrganizationName(instituteDroppingCenterUserDetails.organizationName);
  await instituteDroppingCenterPage.enterLocationAreaOfCamp(instituteDroppingCenterUserDetails.address);
  await instituteDroppingCenterPage.enterLandMarkAreaOrNearbyArea(instituteDroppingCenterUserDetails.landmarkArea);
  await instituteDroppingCenterPage.enterCity(instituteDroppingCenterUserDetails.cityName);
  await instituteDroppingCenterPage.selectState('Delhi')
  await instituteDroppingCenterPage.enterPinCode(instituteDroppingCenterUserDetails.postalCode);
  await instituteDroppingCenterPage.enterStartDate(instituteDroppingCenterUserDetails.startDate);  //  MM/DD/YYYY Format (Check your date format)
  await instituteDroppingCenterPage.enterDaysAndTiming(instituteDroppingCenterUserDetails.daysAndTimings);
  await instituteDroppingCenterPage.selectPublicCollection('1');  
  await instituteDroppingCenterPage.selectDonationBoxMonetaryContribution('2'); 
  await instituteDroppingCenterPage.selectPermissionLetter('1');  
  await page.waitForTimeout(2000)
  await instituteDroppingCenterPage.enterFirstName(instituteDroppingCenterUserDetails.firstName);
  await page.waitForTimeout(200);
  await instituteDroppingCenterPage.enterLastName(instituteDroppingCenterUserDetails.lastName);
  await instituteDroppingCenterPage.enterPhoneNumber(instituteDroppingCenterUserDetails.contactPhoneNumber);
  await instituteDroppingCenterPage.enterContactEmail(instituteDroppingCenterUserDetails.contactEmail);
  await instituteDroppingCenterPage.clickSubmitButton();
  await page.waitForTimeout(4000)
  await instituteDroppingCenterPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};

export const instituteActivityIntentUserDetails = {
  registerAs: 'Corporate',
  organizationName: faker.company.name(),
  activityType: faker.helpers.arrayElement(['Book Fair', 'Knowing Goonj Session', 'Goonj Disaster Photo Exhibition']),
  address: faker.location.streetAddress(),
  cityName: 'Delhi',
  state: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  startDate: formattedStartDate,
  startTime: '10:00',
  endDate: formattedEndDate,
  endTime: '10:00',
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  fullName: function () {
    return `${this.firstName} ${this.lastName}`;
  },
  contactEmail: faker.internet.email(),
  contactPhoneNumber: generateIndianMobileNumber(),
};

export async function submitInstituteActivityIntentForm(page, instituteActivityIntentUserDetails) {
  const instituteActivityIntentPage = new InstituteActivityIntentPage(page);
  const instituteActivityIntentUrl = instituteActivityIntentPage.getAppendedUrl('/institution-goonj-activities-intent');
  const registrationConfirmationText = '/institution-goonj-activities-success'
  await page.goto(instituteActivityIntentUrl);
  // await userFormLogin(page, userEmailAddress, userMobileNumber)
  await page.waitForTimeout(3000)
  await instituteActivityIntentPage.selectYouWishToRegisterAs(instituteActivityIntentUserDetails.registerAs);
  await instituteActivityIntentPage.enterOrganizationName(instituteActivityIntentUserDetails.organizationName);
  await instituteActivityIntentPage.selectActivityType(instituteActivityIntentUserDetails.activityType)
  await instituteActivityIntentPage.enterAreaOfActivity(instituteActivityIntentUserDetails.address);
  await instituteActivityIntentPage.enterCity(instituteActivityIntentUserDetails.cityName);
  await instituteActivityIntentPage.selectState('Delhi')
  await instituteActivityIntentPage.enterPinCode(instituteActivityIntentUserDetails.postalCode);
  await instituteActivityIntentPage.enterStartDate(instituteActivityIntentUserDetails.startDate);  //  MM/DD/YYYY Format (Check your date format)
  await instituteActivityIntentPage.enterStartTime(instituteActivityIntentUserDetails.startTime); 
  await instituteActivityIntentPage.enterEndDate(instituteActivityIntentUserDetails.endDate);  //  MM/DD/YYYY Format (Check your date format)
  await instituteActivityIntentPage.enterEndTime(instituteActivityIntentUserDetails.endTime);   
  await page.waitForTimeout(2000)
  await instituteActivityIntentPage.enterFirstName(instituteRegistrationDetails.firstName);
  await page.waitForTimeout(200);
  await instituteActivityIntentPage.enterLastName(instituteRegistrationDetails.lastName);
  await instituteActivityIntentPage.enterPhoneNumber(instituteRegistrationDetails.contactPhoneNumber);
  await instituteActivityIntentPage.enterContactEmail(instituteRegistrationDetails.contactEmail);
  await instituteActivityIntentPage.clickSubmitButton();
  await page.waitForTimeout(5000)
  await instituteActivityIntentPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};


export async function userLogin(page) {
  const baseURL = process.env.BASE_URL_USER_SITE;
  const username = process.env.USERNAME;
  const password = process.env.PASSWORD;
  await page.goto(baseURL);
  await page.waitForURL(baseURL);
  await page.fill('#user_login', username); 
  await page.fill('#user_pass', password); 
  await page.click('#wp-submit');
};

export async function urbanOpsUserLogin(page) {
  const baseURL = process.env.BASE_URL_USER_SITE;
  const username = process.env.URBAN_OPS_USER;
  const password = process.env.URBAN_OPS_USER_PASSWORD;
  await page.goto(baseURL);
  await page.waitForURL(baseURL);
  await page.fill('#user_login', username); 
  await page.fill('#user_pass', password); 
  await page.click('#wp-submit');
};

export async function verifyUserExist(page, userDetails) {
  await page.fill('#email', userDetails.email);
  // Fill in the contact number
  await page.fill('#phone', userDetails.mobileNumber);
  // Click the submit button
  await page.click('input[type="submit"]', { force: true });
}

export async function submitVolunteerRegistrationForm(page, userDetails) {
  const volunteerRegistrationPage = new VolunteerRegistrationPage(page);
  const volunteerUrl = volunteerRegistrationPage.getAppendedUrl('/volunteer-registration');
  const registrationConfirmationText = 'registration-success'
  await page.goto(volunteerUrl);
  await verifyUserExist(page, userDetails);
  await volunteerRegistrationPage.enterFirstName(userDetails.firstName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterLastName(userDetails.lastName);
  // commenting below code as email, mobile number and country are autofill
  // await volunteerRegistrationPage.enterEmail(userDetails.email); 
  // await page.waitForTimeout(200);
  // await volunteerRegistrationPage.selectCountry(userDetails.country); 
  // await volunteerRegistrationPage.enterMobileNumber(userDetails.mobileNumber);
  // await page.waitForTimeout(200);
  await volunteerRegistrationPage.selectGender(userDetails.gender);
  await volunteerRegistrationPage.enterStreetAddress(userDetails.streetAddress);
  await volunteerRegistrationPage.selectState(userDetails.state);
  await volunteerRegistrationPage.enterCityName(userDetails.cityName);
  await volunteerRegistrationPage.enterPostalCode(userDetails.postalCode);
  await volunteerRegistrationPage.selectProfession(userDetails.profession);
  await volunteerRegistrationPage.selectActivityInterested(userDetails.activityInterested);
  await volunteerRegistrationPage.selectVolunteerMotivation(userDetails.volunteerMotivation);
  await volunteerRegistrationPage.selectVoluntarySkills(userDetails.voluntarySkills);
  await volunteerRegistrationPage.selectVolunteerHours(userDetails.volunteerHours);
  await volunteerRegistrationPage.selectContactMethod(userDetails.contactMethod);
  await volunteerRegistrationPage.selectReferralSource(userDetails.referralSource);
  await volunteerRegistrationPage.clickSubmitButton();
  await page.waitForTimeout(4000); // added wait as page was taking time to load
  await volunteerRegistrationPage.verifyUrlAfterFormSubmission(registrationConfirmationText)
};

export async function searchAndVerifyContact(page, userDetails, contactType) {
  const searchContactsPage = new SearchContactsPage(page);
  // Search for the newly registered volunteer
  await searchContactsPage.clickSearchLabel();
  await searchContactsPage.clickFindContacts();
  await searchContactsPage.inputUserNameOrEmail(userDetails.email);
  await searchContactsPage.selectContactType(contactType);
  await searchContactsPage.clickSearchButton();
  await page.waitForTimeout(2000); // added wait as page was taking time to load
  await page.locator('a.view-contact').click({force: true})
  await page.waitForTimeout(1000)
  const emailLocator = page.locator('div.crm-summary-row.profile-block-email-Primary .crm-content');
  await page.waitForTimeout(1000)
  const emailAddress = await emailLocator.innerText();
  const userEmailAddress = userDetails.email.toLowerCase()
  expect(emailAddress).toContain(userEmailAddress);
}


export async function verifyVolunteerByStatus(page, userDetails, status) {
  const emailInputField = '#contact-email-contact-id-01-email-1'
  const adminHomePage = new AdminHomePage(page);
  let userEmailAddress = userDetails.email.toLowerCase()
  let userContactNumber = userDetails.mobileNumber
  await adminHomePage.clickVolunteerSubOption(status)
  await page.fill(emailInputField, userEmailAddress)
  await page.press(emailInputField, 'Enter')
  await page.waitForTimeout(2000)
  const emailSelector = 'td[data-field-name=""] span.ng-binding.ng-scope';
  const userData = await page.$$eval(emailSelector, nodes =>
    nodes.map(n => n.innerText.trim())
  );
  expect(userData).toContain(userEmailAddress)
  expect(userData).toContain(userContactNumber)

}

export async function userLogout(page) {
  await page.locator('a[href="#toggle-position"]').click({ force: true });
  // Click the logout link
  await page.hover('#wp-admin-bar-my-account'); // Hover over the profile menu
  await page.waitForTimeout(500); // Wait for the dropdown to appear
  await page.click('#wp-admin-bar-logout a.ab-item'); // Click the logout button
}

export async function  userFormLogin(page, username, password) {
    await page.fill('#email', username); 
    await page.fill('#phone', password); 
    await page.click('[data-test="submitButton"]');
}

export const instituteRegistrationDetails = {
  instituteType: faker.helpers.arrayElement(['Educational Institute']),
  instituteCategory: faker.helpers.arrayElement(['School']),
  organizationName: faker.helpers.arrayElement(['Taj Hotels', 'Fortis Hospital']),
  legalName: 'Hospitality sector',
  branchName: 'delhi branch',
  departmentName: 'finance',
  streetAddress: faker.location.streetAddress(),
  country: 'India',
  state: faker.helpers.arrayElement(['Delhi']),
  cityName: faker.helpers.arrayElement(['Delhi']),
  postalCode: faker.location.zipCode('######'), // Indian postal code format
  instituteEmail: faker.internet.email(),
  instituteContactNumber: generateIndianMobileNumber(),
  instituteExtension: '3434',
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  fullName: function () {
    return `${this.firstName} ${this.lastName}`;
  },
  contactEmail: faker.internet.email(),
  contactPhoneNumber: generateIndianMobileNumber(), // Generate Indian mobile number
  contactDesignation: 'Executive',
};

export async function submitInstituteRegistrationForm(page, instituteRegistrationDetails) {
  const instituteRegistrationPage = new InstituteRegistrationPage(page);
  const instituteUrl = instituteRegistrationPage.getAppendedUrl('/institute-registration/sign-up/');
  const registrationConfirmationText = 'institute-registration/success/'
  await page.goto(instituteUrl);
  await instituteRegistrationPage.selectInstituteType(instituteRegistrationDetails.instituteType);
  await page.waitForTimeout(2000);
  await instituteRegistrationPage.selectInstituteCategory(instituteRegistrationDetails.instituteCategory);
  await instituteRegistrationPage.enterOrganizationName(instituteRegistrationDetails.organizationName);
  await instituteRegistrationPage.enterInstituteLegalName(instituteRegistrationDetails.legalName);
  await instituteRegistrationPage.enterInstituteBranchName(instituteRegistrationDetails.branchName);
  await instituteRegistrationPage.enterInstituteDepartmentName(instituteRegistrationDetails.departmentName);
  await instituteRegistrationPage.enterStreetAddress(instituteRegistrationDetails.streetAddress);
  await instituteRegistrationPage.selectCountry(instituteRegistrationDetails.country);
  await instituteRegistrationPage.selectState(instituteRegistrationDetails.state);
  await page.waitForTimeout(2000);
  await instituteRegistrationPage.enterCityName(instituteRegistrationDetails.cityName);
  await instituteRegistrationPage.enterPostalCode(instituteRegistrationDetails.postalCode);
  await instituteRegistrationPage.enterContactNumber(instituteRegistrationDetails.instituteContactNumber);
  await instituteRegistrationPage.enterInstituteEmail(instituteRegistrationDetails.instituteEmail)
  await instituteRegistrationPage.enterInstituteExtension(instituteRegistrationDetails.instituteExtension)
  await instituteRegistrationPage.enterFirstName(instituteRegistrationDetails.firstName);
  await page.waitForTimeout(200);
  await instituteRegistrationPage.enterLastName(instituteRegistrationDetails.lastName);
  await instituteRegistrationPage.enterContactEmail(instituteRegistrationDetails.contactEmail);
  await instituteRegistrationPage.enterPhoneNumber(instituteRegistrationDetails.contactPhoneNumber);
  await instituteRegistrationPage.enterDesignationField(instituteRegistrationDetails.contactDesignation);
  await instituteRegistrationPage.clickSubmitButton();
  await page.waitForTimeout(6000); // added wait as page was taking time to load
  await instituteRegistrationPage.verifyUrlAfterFormSubmission(registrationConfirmationText)
};


export const monetaryContributionUserDetails = {
  otherAmount: faker.helpers.arrayElement(['2000', '3000']),
  contactEmail: faker.internet.email(),
  contactPhoneNumber: generateIndianMobileNumber(),
  firstName: faker.person.firstName(),
  lastName: faker.person.lastName(),
  fullName: function () {
    return `${this.firstName} ${this.lastName}`;
  },
  cityName: 'Delhi',
  postalCode: faker.location.zipCode('110070'),
  address: faker.location.streetAddress(),
  pancardNumber: 'CENPS7490Q'
  
};

export async function submitMonetaryContributionForm(page, monetaryContributionUserDetails) {
  const individualMonetaryContributionPage  = new IndividualMonetaryContributionPage(page);
  const individualMonetaryContributionUrl = individualMonetaryContributionPage.getAppendedUrl('/contribute/?custom_554=970');
  const registrationConfirmationText = 'razorpay%2Fpayment&contribution'
  await page.goto(individualMonetaryContributionUrl);
  // await userFormLogin(page, userEmailAddress, userMobileNumber)
  // await droppingCenterPage.clickContinueButton()
  await page.waitForTimeout(3000)
  await individualMonetaryContributionPage.enterContactEmail(monetaryContributionUserDetails.contactEmail);
  await individualMonetaryContributionPage.enterContactNumber(monetaryContributionUserDetails.contactPhoneNumber);
  await individualMonetaryContributionPage.enterFirstName(monetaryContributionUserDetails.firstName);
  await page.waitForTimeout(200);
  await individualMonetaryContributionPage.enterLastName(monetaryContributionUserDetails.lastName);
  await individualMonetaryContributionPage.enterCityName(monetaryContributionUserDetails.cityName);
  await individualMonetaryContributionPage.selectState('Delhi')
  await page.waitForTimeout(200);
  await individualMonetaryContributionPage.enterPostalCode(monetaryContributionUserDetails.postalCode); 
  await individualMonetaryContributionPage.enterStreetAddress(monetaryContributionUserDetails.address); 
  await individualMonetaryContributionPage.enterPancard(monetaryContributionUserDetails.pancardNumber); 
  await individualMonetaryContributionPage.selectConfirmationCheckbox()
  await page.waitForTimeout(3000)
  await individualMonetaryContributionPage.clickContributeButton();
  await page.waitForTimeout(4000)
  await individualMonetaryContributionPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
};