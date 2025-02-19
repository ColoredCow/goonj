import { expect } from '@playwright/test';
import { faker } from '@faker-js/faker/locale/en_IN'; // Import the Indian locale directly
import { VolunteerRegistrationPage } from '../playwright/pages/volunteer-registration.page';
import { SearchContactsPage } from '../playwright/pages/search-contact.page';
import { AdminHomePage } from '../playwright/pages/admin-home.page';
import { CollectionCampPage } from '../playwright/pages/collection-camp-registration.page'
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
  cityName: faker.location.city(),
  postalCode: faker.location.zipCode('######'), // Indian postal code format
  state: faker.helpers.arrayElement(['Haryana', 'Delhi', 'Uttar Pradesh', 'Tamil Nadu']),
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

  const startDate = new Date(); 

  // Generate an end date within the next 2 days
  const endDate = faker.date.soon({ days: 2, refDate: startDate }); 
  // Format the dates as DD/MM/YYYY
  const formattedStartDate = `${String(startDate.getDate()).padStart(2, '0')}/${String(startDate.getMonth() + 1).padStart(2, '0')}/${startDate.getFullYear()}`;
  const formattedEndDate = `${String(endDate.getDate()).padStart(2, '0')}/${String(endDate.getMonth() + 1).padStart(2, '0')}/${endDate.getFullYear()}`;

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
  await collectionCampPage.enterStartTime('10:00'); 
  await collectionCampPage.enterEndDate(collectionCampUserDetails.endDate);  //  MM/DD/YYYY Format (Check your date format)
  await collectionCampPage.enterEndTime('14:00'); 
  await collectionCampPage.selectPermissionLetter('1');  
  await collectionCampPage.selectPublicCollection('1');  
  await collectionCampPage.selectEngagingActivity('2'); 
  await page.waitForTimeout(2000)
  await collectionCampPage.clickSubmitButton();
  await page.waitForTimeout(4000)
  await collectionCampPage.verifyUrlAfterFormSubmission(registrationConfirmationText);  // Replace with your success URL
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

