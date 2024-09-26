import { expect } from '@playwright/test';
import { faker } from '@faker-js/faker/locale/en_IN'; // Import the Indian locale directly
import { VolunteerRegistrationPage } from '../playwright/pages/volunteer-registration.page';
import { SearchContactsPage } from '../playwright/pages/search-contact.page';

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
//   state: faker.location.state(), //form was not having certain states in dropdwon
  state: 'Haryana',
  activityInterested: faker.helpers.arrayElement(['Organise fundraising activities to support Goonj’s initiatives.', 'Hold Chuppi Todo Baithak’s to generate awareness on Menstruation']), 
  voluntarySkills: faker.helpers.arrayElement(['Marketing', 'Content Writing']), 
  // otherSkills: faker.helpers.arrayElement(['Research', 'Content Writing']),
  volunteerMotivation: faker.helpers.arrayElement(['Learn new skills', 'Use my skills']),
  volunteerHours: faker.helpers.arrayElement(['2 to 6 hours daily', '2 to 6 hours weekly', '2 to 6 hours monthly']),
  profession: faker.helpers.arrayElement(['Homemaker', 'Government Employee']),
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
  await page.goto(volunteerUrl);
  await verifyUserExist(page, userDetails);
  // await page.waitForTimeout(10000);
  await volunteerRegistrationPage.enterFirstName(userDetails.firstName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterLastName(userDetails.lastName);
  await page.waitForTimeout(200);
  // await volunteerRegistrationPage.enterEmail(userDetails.email); //email autofill 
  // await page.waitForTimeout(200);
  // await volunteerRegistrationPage.selectCountry(userDetails.country); //country is autoseleced as india
  // await volunteerRegistrationPage.enterMobileNumber(userDetails.mobileNumber); //mobile  autofill 
  // await page.waitForTimeout(200);
  await volunteerRegistrationPage.selectGender(userDetails.gender);
  await volunteerRegistrationPage.enterStreetAddress(userDetails.streetAddress);
  await volunteerRegistrationPage.selectState(userDetails.state);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterCityName(userDetails.cityName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterPostalCode(userDetails.postalCode);
  await volunteerRegistrationPage.selectProfession(userDetails.profession);
  await volunteerRegistrationPage.selectActivityInterested(userDetails.activityInterested);
  await volunteerRegistrationPage.selectVolunteerMotivation(userDetails.volunteerMotivation);
  await volunteerRegistrationPage.selectVoluntarySkills(userDetails.voluntarySkills);
  // await volunteerRegistrationPage.enterOtherSkills(userDetails.otherSkills);
  await volunteerRegistrationPage.selectVolunteerHours(userDetails.volunteerHours);
  await page.waitForTimeout(400);
  await volunteerRegistrationPage.clickSubmitButton();
  await page.waitForTimeout(2000); // added wait as page was taking time to load
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