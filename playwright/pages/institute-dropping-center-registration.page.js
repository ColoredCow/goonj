import { expect } from '@playwright/test';

exports.InstituteDroppingCenterPage = class InstituteDroppingCenterPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    // Locators for the input fields within the provided HTML structure
    // this.youWishToRegisterAsSelect = page.locator('div[class="af-container"] af-field[name="Collection_Camp_Intent_Details.You_wish_to_register_as"] .select2-container');
    this.organizationNameField = page.locator( 'input[id^="institution-dropping-center-intent-initiator-organization-name-1"]');
    this.locationAreaOfCampField = page.locator('input[id^="institution-dropping-center-intent-dropping-center-address-"]');
    this.landMarkAreaOrNearbyArea = page.locator('input[id^="institution-dropping-center-intent-landmark-or-near-by-area-"]');
    this.cityField = page.locator('input[id^="institution-dropping-center-intent-district-city-"]');
    // this.stateSelect = page.locator('af-field[name="Collection_Camp_Intent_Details.State"] .select2-container');
    this.pinCodeField = page.locator('input[id^="institution-dropping-center-intent-postal-code-"]');
    this.startDateForDroppingCenter = page.locator('af-field[name="Institution_Dropping_Center_Intent.When_do_you_wish_to_open_center_Date_"] input[aria-label="Select Date"]'); //Date input
    this.daysAndTimings = page.locator('input[id^="institution-dropping-center-intent-timing-"]');
    this.firstNameField = page.locator('input[id^="first-name-12"]');
    this.lastNameField = page.locator('input[id^="last-name-13"]');
    this.phoneNumberField  = page.locator('input[id^="phone-14"]')
    this.contactEmailField = page.locator('input[id^="email-15"]');
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

  async enterLandMarkAreaOrNearbyArea(area){
    await this.locationAreaOfCampField.fill(area);
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
    await this.startDateForDroppingCenter.fill(date);
  }

  async enterDaysAndTiming(daysAndTimings) {
    await this.daysAndTimings.fill(daysAndTimings);
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
    await this.page.locator(`input[name="institution-dropping-center-intent-will-your-dropping-center-be-open-for-general-public-as-well-out-9"][value="${value}"]`).click();
  }

  async selectDonationBoxMonetaryContribution(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '2'; 
    await this.page.locator(`input[name="institution-dropping-center-intent-can-we-keep-donation-box-in-center-10"][value="${value}"]`).click();
  }
  async selectPermissionLetter(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="institution-dropping-center-intent-some-volunteers-require-permission-letters-from-goonj-to-get-per-11"][value="${value}"]`).click();
}


  async clickSubmitButton() {
    await this.page.getByRole('button', { name: /submit/i }).click({ force: true });
  }

  getAppendedUrl(stringToAppend) {
    return this.url + stringToAppend;
  }

  async clickContinueButton(){
    await this.page.locator('div.login-submit a.button.button-primary').click()
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
