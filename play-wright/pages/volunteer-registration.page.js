import { expect } from '@playwright/test';

exports.VolunteerRegistrationPage =  class VolunteerRegistrationPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    this.firstNameField = page.locator('input[id^="first-name-"]');
    this.lastNameField = page.locator('input[id^="last-name-"]');
    this.emailField = page.locator('input[id^="email-"]');
    this.mobileNumberField = page.locator('input#phone-5');
    this.streetAddress = page.locator('input[id^="street-address-"]');
    this.cityName = page.locator('input[id^="city-"]')
    this.postalCode = page.locator('input[id^="postal-code-"]')
    this.profession = page.locator('input[id^="volunteer-fields-profession-"]')
    this.otherSkills = page.locator('input[id^="volunteer-fields-others-skills-"]')
  }
  
  async enterFirstName(firstName) {
      await this.firstNameField.fill(firstName);
  }
  async enterLastName(lastName) {
    await this.lastNameField.fill(lastName);
  }

  async enterEmail(email) {
    await this.emailField.fill(email);
  }

  async enterMobileNumber(mobileNumber) {
    await this.mobileNumberField.fill(mobileNumber);
  }

  async enterStreetAddress(streetAddress)
  {
    await this.streetAddress.fill(streetAddress)
  }

  async enterPostalCode(postalCode)
  {
    await this.postalCode.fill(postalCode)
  }

  async enterCityName(cityName)
  {
    await this.cityName.fill(cityName)
  }

  async enterProfession(profession)
  {
    await this.profession.fill(profession)
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

  // Need to add the selector passed on selectDropdownOption to class IndividualRegistrationPage
  async selectTitle(title) {
    await this.selectDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', title);
  }

  async selectCountry(country) {
    await this.selectDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', country);
  }

  // async selectCountry(nationality) {
  //   await this.selectDropdownOption('#select2-chosen-4', '#s2id_autogen4_search', nationality);
  // }

  async selectGender(gender) {
    await this.selectDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', gender);
  }

  async selectState(state) {
    await this.selectDropdownOption('#select2-chosen-1', '#s2id_autogen1_search', state);
  }

  async selectActivityInterested(activity) {
    await this.selectDropdownOption('#s2id_autogen5', '#s2id_autogen5', activity);
  }

  async selectVoluntarySkills(skill) {
    await this.selectDropdownOption('#s2id_autogen7', '#s2id_autogen7', skill);
  }

  async selectVolunteerMotivation(motivation) {
    await this.selectDropdownOption('#s2id_autogen6', '#s2id_autogen6', motivation);
  }

  async selectVolunteerHours(hours) {
    await this.selectDropdownOption('#select2-chosen-8', '#s2id_autogen8_search', hours);
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

  async selectTitleAndClear(title) {
    await this.selectAndClearDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', title);
  }

  async selectCountryAndClear(country) {
    await this.selectAndClearDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', country);
  }

  async selectGenderAndClear(gender) {
    await this.selectAndClearDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', gender);
  }

  async selectStateAndClear(state) {
    await this.selectAndClearDropdownOption('#select2-chosen-1', '#s2id_autogen1_search', state);
  }

  async selectActivityInterestedAndClear(activity) {
    await this.selectAndClearMultipleDropdownOption('#s2id_autogen4', '#s2id_autogen4', activity);
  }

  async selectVoluntarySkillsAndClear(skill) {
    await this.selectAndClearMultipleDropdownOption('#s2id_autogen6', '#s2id_autogen6', skill);
  }

  async selectVolunteerMotivationAndClear(motivation) {
    await this.selectAndClearMultipleDropdownOption('#s2id_autogen5', '#s2id_autogen5', motivation);
  }

  async selectVolunteerHoursAndClear(hours) {
    await this.selectAndClearDropdownOption('#select2-chosen-7', '#s2id_autogen7_search', hours);
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
 
  async enterOtherSkills(skills)
  {
    await this.otherSkills.fill(skills)
  }
  
  getAppendedUrl(stringToAppend) {
    return this.url + stringToAppend;
  }

  async fillAndClearField(fieldName, value, clearValue = '') {
    await this[fieldName](value);
    await this.clickSubmitButton();
    await this[fieldName](clearValue);
  }
}