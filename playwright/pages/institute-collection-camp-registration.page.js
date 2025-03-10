import { expect } from '@playwright/test';

exports.InstituteCollectionCampPage = class InstituteCollectionCampPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;

    // Locators for the input fields within the provided HTML structure
    // this.youWishToRegisterAsSelect = page.locator('div[class="af-container"] af-field[name="Collection_Camp_Intent_Details.You_wish_to_register_as"] .select2-container');
    this.organizationNameField = page.locator( 'input[id^="institution-collection-camp-intent-initiator-organization-name-"]')
    this.locationAreaOfCampField = page.locator('input[id^="institution-collection-camp-intent-collection-camp-address-"]');
    this.cityField = page.locator('input[id^="institution-collection-camp-intent-district-city-"]');
    // this.stateSelect = page.locator('af-field[name="Collection_Camp_Intent_Details.State"] .select2-container');
    this.pinCodeField = page.locator('af-field[name="Institution_Collection_Camp_Intent.Postal_Code"] input[type="text"]');
    this.startDateInput = page.locator('af-field[name="Institution_Collection_Camp_Intent.Collections_will_start_on_Date_"] input[aria-label="Select Date"]'); //Date input
    this.startTimeInput = page.locator('af-field[name="Institution_Collection_Camp_Intent.Collections_will_start_on_Date_"] input[aria-label="Time"]');
    this.endDateInput = page.locator('af-field[name="Institution_Collection_Camp_Intent.Collections_will_end_on_Date_"] input[aria-label="Select Date"]'); //Date input
    this.endTimeInput = page.locator('af-field[name="Institution_Collection_Camp_Intent.Collections_will_end_on_Date_"] input[aria-label="Time"]');
    this.firstNameField = page.locator('input[id^="first-name-11"]');
    this.lastNameField = page.locator('input[id^="last-name-12"]');
    this.contactEmailField = page.locator('input[id^="email-14"]');
    this.phoneNumberField  = page.locator('input[id^="phone-13"]')
  }
  async selectDropdownOption(dropdownSelector, optionText) {
    // Click the dropdown to open the list
    await this.page.locator(dropdownSelector).click();

    // Find the correct visible results container
    const activeDropdown = this.page.locator('.select2-results:visible');

    // Wait for it to be visible
    await activeDropdown.waitFor({ state: 'visible' });

    // Type the option
    await this.page.keyboard.type(optionText);
    await this.page.keyboard.press('Enter')

    // Wait for a short duration
    await this.page.waitForTimeout(1000);
}

async selectStateDropdownOption(dropdownSelector, inputField, option) {
    await this.page.click(dropdownSelector);
    await this.page.waitForTimeout(1000)
    await this.page.fill(inputField, option);
    await this.page.waitForTimeout(2000)
    const optionSelector = `.select2-result-label:text("${option}")`;
    await this.page.click(optionSelector);
    await this.page.keyboard.press('Tab');
  }

  async  selectRadioOption(option) {
    // Determine the value based on the option provided
    const value = option.toLowerCase() === 'yes' ? '1' : '2';

    // Click the appropriate radio button
    await this.page.locator(`input[type="radio"][value="${value}"]`).click();
    console.log(`Clicked "${option}" radio button`);

    // Wait for a moment to observe the change
    await this.page.waitForTimeout(2000);
}

  async selectYouWishToRegisterAs(option) {
    await this.selectDropdownOption('#select2-chosen-1', option);
  }

  async enterOrganizationName(organizationName)
  {
    await this.organizationNameField.fill(organizationName)
  }

  async enterLocationAreaOfCamp(location) {
    await this.locationAreaOfCampField.fill(location);
  }

  async enterCity(city) {
    await this.cityField.fill(city);
  }

  async selectState(state) {
    await  this.selectStateDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', state);
  }

  async enterPinCode(pinCode) {
    await this.pinCodeField.fill(pinCode);
  }

  async enterStartDate(date) {
    await this.startDateInput.fill(date);
  }

  async enterStartTime(time) {
    await this.startTimeInput.fill(time);
  }

  async enterEndDate(date) {
    await this.endDateInput.fill(date);
  }

  async enterEndTime(time) {
    await this.endTimeInput.fill(time);
  }

  async enterFirstName(firstName) {
    await this.firstNameField.fill(firstName);
    }
async enterLastName(lastName) {
    await this.lastNameField.fill(lastName);
    }

    async enterContactEmail(email) {
  await this.contactEmailField.fill(email);
    }

async enterPhoneNumber(phoneNumber) {
  await this.phoneNumberField.fill(phoneNumber);
}

  async selectPublicCollection(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="institution-collection-camp-intent-will-your-collection-drive-be-open-for-general-public-as-well-9"][value="${value}"]`).click();
  }

  async selectEngagingActivity(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '2'; 
    await this.page.locator(`input[name="institution-collection-camp-intent-do-you-want-to-plan-a-creative-and-engaging-activity-that-go-bey-10"][value="${value}"]`).click();
  }

  async clickSubmitButton() {
    await this.page.getByRole('button', { name: /submit/i }).click({ force: true });
  }

  getAppendedUrl(stringToAppend) {
    return this.url + stringToAppend;
  }

  async verifyUrlAfterFormSubmission(expectedText) {
    try {
      const currentUrl = this.page.url();
      expect(currentUrl).toContain(expectedText,
        `Expected URL to contain "${expectedText}" but got "${currentUrl}"`);
    } catch (error) {
      throw new Error(`URL verification failed: ${error.message}`);
    }
  }
};
