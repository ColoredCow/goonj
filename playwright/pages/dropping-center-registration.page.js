import { expect } from '@playwright/test';

exports.DroppingCenterPage = class DroppingCenterPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    // Locators for the input fields within the provided HTML structure
    // this.youWishToRegisterAsSelect = page.locator('div[class="af-container"] af-field[name="Collection_Camp_Intent_Details.You_wish_to_register_as"] .select2-container');
    this.locationAreaOfCampField = page.locator('#dropping-centre-where-do-you-wish-to-open-dropping-center-address-2');
    this.landMarkAreaOrNearbyArea = page.locator('#dropping-centre-landmark-or-near-by-area-3');
    this.cityField = page.locator('#dropping-centre-district-city-4');
    // this.stateSelect = page.locator('af-field[name="Collection_Camp_Intent_Details.State"] .select2-container');
    this.pinCodeField = page.locator('#dropping-centre-postal-code-6');
    this.startDateForDroppingCenter = page.locator('#dp1740510055343'); //Date input
    this.droppingCenterTiming = page.locator('dropping-centre-timing-8');
    this.volunteerNameField = page.locator('#dropping-centre-name-12');
    this.volunteerContactNumberField = page.locator('#dropping-centre-contact-number-13');
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
    await this.startDateForDroppingCenter.fill(date);
  }

  async enterVolunteerName(name) {
    await this.volunteerNameField.fill(name);
  }

  async enterVolunteerContactNumber(number) {
    await this.volunteerContactNumberField.fill(number);
  }

  async selectDonationBoxMonetaryContribution(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '2'; 
    await this.page.locator(`input[name="dropping-centre-can-we-keep-donation-box-in-center-for-monetary-contribution-9"][value="${value}"]`).click();
  }
  async selectPermissionLetter(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="dropping-centre-some-volunteers-require-permission-letters-from-goonj-to-get-per-10"][value="${value}"]`).click();
}

  async selectPublicCollection(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="dropping-centre-will-your-dropping-center-be-open-for-general-public-as-well-out-11"][value="${value}"]`).click();
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
