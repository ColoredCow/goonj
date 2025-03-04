import { expect } from '@playwright/test';

exports.InstituteRegistrationPage =  class InstituteRegistrationPage{
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    this.organizationNameField = page.locator('#organization-name-2')
    this.instituteLegalNameField = page.locator('input[id^="institute-registration-legal-name-of-institute-"]')
    this.instituteBranchNameField = page.locator('input[id^="institute-registration-branch-"]')
    this.instituteDepartmentNameField = page.locator('input[id^="institute-registration-department-branch-"]')
    this.streetAddressField = page.locator('input[id^="street-address-"]');
    this.cityNameField  = page.locator('input[id^="city-"]')
    this.postalCodeField  = page.locator('input[id^="postal-code-"]')
    this.contactNumberField = page.locator('input#phone-13');
    this.instituteEmailField = page.locator('input[id^="email-17"]');
    this.instituteExtensionField = page.locator('input[id^="institute-registration-extension"]');
    this.firstNameField = page.locator('input[id^="first-name-"]');
    this.lastNameField = page.locator('input[id^="last-name-"]');
    this.contactEmailField = page.locator('input[id^="email-23"]');
    this.phoneNumberField  = page.locator('input[id^="phone-24"]')
    this.designationField  = page.locator('input[id^="individual-fields-designation-"]')
  }

  async enterOrganizationName(organizationName)
  {
    await this.organizationNameField.fill(organizationName)
  }

  async enterInstituteLegalName(legalName)
  {
    await this.instituteLegalNameField.fill(legalName)
  }

  async enterInstituteBranchName(branchName)
  {
    await this.instituteBranchNameField.fill(branchName)
  }

  async enterInstituteDepartmentName(departmentName)
  {
    await this.instituteDepartmentNameField.fill(departmentName)
  }
  
  async enterStreetAddress(streetAddress)
  {
    await this.streetAddressField.fill(streetAddress)
  }
  
  async enterPostalCode(postalCode)
  {
    await this.postalCodeField.fill(postalCode)
  }

  async enterCityName(cityName)
  {
    await this.cityNameField.fill(cityName)
  }

  async enterContactNumber(contactNumber)
  {
    await this.contactNumberField.fill(contactNumber)
  }

  async enterInstituteEmail(email)
  {
    await this.instituteEmailField.fill(email)
  }

  async enterInstituteExtension(extension)
  {
    await this.instituteExtensionField.fill(extension)
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

  async enterDesignationField(designation){
    await this.designationField.fill(designation);
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
  
  async selectInstituteType(type) {
    await this.selectDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', type);
  }

  async selectState(state) {
    await this.selectDropdownOption('#select2-chosen-1', '#s2id_autogen1_search', state);
  }

  async selectCountryAndClear(country) {
    await this.selectAndClearDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', country);
  }

  async selectInstituteTypeAndClear(type) {
    await this.selectAndClearDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', type);
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

  async selectProfession(profession) {
    await this.selectDropdownOption('#select2-chosen-5', '#s2id_autogen5_search', profession);
  }

  async selectRadioButton(buttonOption) {
    // Find the label with the specific text and click the associated radio button
    const labelSelector = `label:has-text("${buttonOption}")`;
    await this.page.click(`${labelSelector} input[type="radio"]`);
  }

  async handleDialogMessage(expectedMessage) {
    this.page.on('dialog', async (dialog) => {
    expect(dialog.message()).toContain(expectedMessage);
    await dialog.accept();
    });
  }

  async clickSubmitButton() {
    await this.page.getByRole('button', { name: /submit/i }).click({force: true});
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