import { expect } from '@playwright/test';

exports.VolunteerRegistrationPage =  class VolunteerRegistrationPage {
  constructor(page) {
    this.page = page;
    this.url = process.env.BASE_URL_USER_SITE;
    this.firstNameField = page.locator('input#first-name-2')
    this.lastNameField = page.locator('input#last-name-3');
    this.emailField = page.locator('input#email-4');
    this.mobileNumberField = page.locator('input#phone-6');
    this.streetAddress = page.locator('input#street-address-10');
    this.cityName = page.locator('input#city-14');
    this.postalCode = page.locator('input#postal-code-15');
    this.profession = page.locator('input#volunteer-fields-profession-21')
    this.otherSkills = page.locator('input#volunteer-fields-others-skills-19')
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
    const optionSelector = `.select2-result-label:text("${option}")`;
    await this.page.click(optionSelector);
    await this.page.keyboard.press('Tab');
  }

  // Need to add the selector passed on selectDropdownOption to class IndividualRegistrationPage
  async selectTitle(title) {
    await this.selectDropdownOption('#select2-chosen-2', '#s2id_autogen2_search', title);
  }

  async selectCountry(country) {
    await this.selectDropdownOption('#select2-chosen-4', '#s2id_autogen4_search', country);
  }

  async selectGender(gender) {
    await this.selectDropdownOption('#select2-chosen-3', '#s2id_autogen3_search', gender);
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
}
