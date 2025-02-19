import { expect } from '@playwright/test';

exports.CollectionCampPage = class CollectionCampPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;

    // Locators for the input fields within the provided HTML structure
    // this.youWishToRegisterAsSelect = page.locator('div[class="af-container"] af-field[name="Collection_Camp_Intent_Details.You_wish_to_register_as"] .select2-container');
    this.locationAreaOfCampField = page.locator('#collection-camp-intent-details-location-area-of-camp-2');
    this.cityField = page.locator('#collection-camp-intent-details-city-3');
    // this.stateSelect = page.locator('af-field[name="Collection_Camp_Intent_Details.State"] .select2-container');
    this.pinCodeField = page.locator('af-field[name="Collection_Camp_Intent_Details.Pin_Code"] input[type="text"]');
    this.startDateInput = page.locator('af-field[name="Collection_Camp_Intent_Details.Start_Date"] input[aria-label="Select Date"]'); //Date input
    this.startTimeInput = page.locator('af-field[name="Collection_Camp_Intent_Details.Start_Date"] input[aria-label="Time"]');
    this.endDateInput = page.locator('af-field[name="Collection_Camp_Intent_Details.End_Date"] input[aria-label="Select Date"]'); //Date input
    this.endTimeInput = page.locator('af-field[name="Collection_Camp_Intent_Details.End_Date"] input[aria-label="Time"]');
    this.volunteerNameField = page.locator('af-field[name="Collection_Camp_Intent_Details.Name"] input[type="text"]');
    this.volunteerContactNumberField = page.locator('af-field[name="Collection_Camp_Intent_Details.Contact_Number"] input[type="text"]');
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

  async enterVolunteerName(name) {
    await this.volunteerNameField.fill(name);
  }

  async enterVolunteerContactNumber(number) {
    await this.volunteerContactNumberField.fill(number);
  }

  async selectPermissionLetter(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="collection-camp-intent-details-do-you-require-permission-letters-from-goonj-to-get-permission-f-8"][value="${value}"]`).click();
}

  async selectPublicCollection(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '1'; // Default to 'Yes'
    await this.page.locator(`input[name="collection-camp-intent-details-will-your-collection-drive-be-open-for-general-public-9"][value="${value}"]`).click();
  }

  async selectEngagingActivity(option) {
    const valueMap = { 'Yes': '1', 'No': '2' };
    const value = valueMap[option] || '2'; 
    await this.page.locator(`input[name="collection-camp-intent-details-do-you-want-to-plan-a-creative-and-engaging-activity-where-resid-10"][value="${value}"]`).click();
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
