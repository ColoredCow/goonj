import { expect } from '@playwright/test';
import { faker } from '@faker-js/faker/locale/en_IN'; // Import the Indian locale directly
import { VolunteerRegistrationPage } from '../play-wright/pages/volunteer-registration.page';
import { SearchContactsPage } from '../play-wright/pages/search-contact.page';

// Helper function to generate an Indian mobile number
const generateIndianMobileNumber = () => {
  const prefix = faker.helpers.arrayElement(['7', '8', '9']); // Indian mobile numbers start with 7, 8, or 9
  const number = faker.number.int({ min: 1000000000, max: 9999999999 }).toString().slice(1);
  return `+91 ${prefix}${number}`;
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
  activityInterested: faker.helpers.arrayElement(['Raise funds', 'Explore CSR']), 
  voluntarySkills: faker.helpers.arrayElement(['Marketing', 'Content Writing']), 
  otherSkills: faker.helpers.arrayElement(['Research', 'Content Writing']),
  volunteerMotivation: faker.helpers.arrayElement(['Learn new skills', 'Use my skills']),
  volunteerHours: faker.helpers.arrayElement(['2 to 6 hours daily', '2 to 6 hours weekly', '2 to 6 hours monthly']),
  profession: faker.person.jobTitle(),
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

export async function submitVolunteerRegistrationForm(page, userDetails) {
  const volunteerRegistrationPage = new VolunteerRegistrationPage(page);
  const volunteerUrl = volunteerRegistrationPage.getAppendedUrl('/volunteer-registration/');
  await page.goto(volunteerUrl);
  await page.waitForURL(volunteerUrl);
  await volunteerRegistrationPage.selectTitle(userDetails.nameInitial);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterFirstName(userDetails.firstName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterLastName(userDetails.lastName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterEmail(userDetails.email);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.selectCountry(userDetails.country);
  await volunteerRegistrationPage.enterMobileNumber(userDetails.mobileNumber);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.selectGender(userDetails.gender);
  await volunteerRegistrationPage.enterStreetAddress(userDetails.streetAddress);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterCityName(userDetails.cityName);
  await page.waitForTimeout(200);
  await volunteerRegistrationPage.enterPostalCode(userDetails.postalCode);
  await volunteerRegistrationPage.selectState(userDetails.state);
  await volunteerRegistrationPage.selectActivityInterested(userDetails.activityInterested);
  await volunteerRegistrationPage.selectVolunteerMotivation(userDetails.volunteerMotivation);
  await volunteerRegistrationPage.selectVoluntarySkills(userDetails.voluntarySkills);
  await volunteerRegistrationPage.enterOtherSkills(userDetails.otherSkills);
  await volunteerRegistrationPage.selectVolunteerHours(userDetails.volunteerHours);
  await volunteerRegistrationPage.enterProfession(userDetails.profession);
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
  // Verify the contact is found in the table
  const contactRowSelector = `table tbody tr:has(span[title="${userDetails.email}"])`;
  await page.waitForTimeout(1000);
  expect(contactRowSelector).toContain(userDetails.email);
  await page.waitForTimeout(1000);
}