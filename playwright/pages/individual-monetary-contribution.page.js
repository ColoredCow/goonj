import { expect } from '@playwright/test';

exports.IndividualMonetaryContributionPage =  class IndividualMonetaryContributionPage{
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    this.otherAmountField = page.locator('input[id^="price_3"]');
    this.emailField = page.locator('input[id^="email-5"]');
    this.mobileNumberField = page.locator('input[id^="phone-Primary-2"]');
    this.firstNameField = page.locator('input[id^="first_name"]');
    this.lastNameField = page.locator('input[id^="last_name"]');
    this.cityNameField  = page.locator('input[id^="city-Primary"]');
    this.postalCodeField  = page.locator('input[id^="postal_code-Primary"]');
    this.streetAddressField = page.locator('input[id^="street_address-Primary"]');
    this.pancardField = page.locator('[data-crm-custom="Contribution_Details:PAN_Card_Number"]');

  }

  async enterOtherAmountField(otherAmount)
  {
    await this.otherAmountField(otherAmount)
  }

  async enterContactEmail(email) {
    await this.emailField.fill(email);
  }

  async enterContactNumber(phoneNumber) {
    await this.mobileNumberField.fill(phoneNumber);
  }

  async enterFirstName(firstName) {
    await this.firstNameField.fill(firstName);
  }

  async enterLastName(lastName) {
    await this.lastNameField.fill(lastName);
  }

  async enterCityName(cityName)
  {
    await this.cityNameField.fill(cityName)
  }

  async enterPostalCode(postalCode)
  {
    await this.postalCodeField.fill(postalCode)
  }

  async enterStreetAddress(streetAddress)
  {
    await this.streetAddressField.fill(streetAddress)
  }

  async enterPancard(pancardNumber)
  {
    await this.pancardField.fill(pancardNumber)
  }

  
 
  async selectDropdownOption(dropdownSelector, inputField, option) {
    await this.page.click(dropdownSelector);
    await this.page.waitForTimeout(1000)
    await this.page.fill(inputField, option);
    await this.page.waitForTimeout(2000)
    const optionSelector = `.select2-result-label:text("${option}")`;
    await this.page.click(optionSelector);
    await this.page.keyboard.press('Tab');
  }

  async selectAndClearDropdownOption(dropdownSelector, inputField, option) {
    const closeIconSelector = `${dropdownSelector} + abbr.select2-search-choice-close`;
    await this.selectDropdownOption(dropdownSelector, inputField, option);
    await this.clickSubmitButton();
    await this.page.click(closeIconSelector);
  }

  async selectAndClearMultipleDropdownOption(dropdownSelector, inputField, option) {
    const closeIconSelector = `ul.select2-choices .select2-search-choice-close`
    await this.selectDropdownOption(dropdownSelector, inputField, option);
    await this.clickSubmitButton();
    await this.page.click(closeIconSelector);
  }

  async selectCountry(country) {
    await this.selectDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', country);
  }
  

  async selectState(state) {
    await this.selectDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', state);
  }

  async selectCountryAndClear(country) {
    await this.selectAndClearDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', country);
  }


  async selectStateAndClear(state) {
    await this.selectAndClearDropdownOption('#select2-chosen-1', '#s2id_autogen1_search', state);
  }


  async selectInstituteCategory(category) {
    // Wait for the dropdown options to appear
    await this.page.click('.select2-choice.select2-default');
    // Click on the option that matches the provided text
    await this.page.click(`li.select2-result > div:has-text("${category}")`);
  }


  async handleDialogMessage(expectedMessage) {
    this.page.on('dialog', async (dialog) => {
    expect(dialog.message()).toContain(expectedMessage);
    await dialog.accept();
    });
  }

  async clickContributeButton() {
    await this.page.click('button.crm-button_qf_Main_upload[type="submit"]');
  }

  async selectConfirmationCheckbox() {
    await this.page.click('[data-crm-custom="Contribution_Details:Confirm_that_the_data_entered_is_correct"]');
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

  async fillAndClearField(fieldName, value, clearValue = '') {
    await this[fieldName](value);
    await this.clickSubmitButton();
    await this[fieldName](clearValue);
  }
}