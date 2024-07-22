import {  } from '@playwright/test';

exports.SearchContactsPage = class SearchContactsPage {
    constructor(page) {
      this.page = page;
    }
  
    async selectContactTypeDropdown(dropdownSelector, optionText) {
      await this.page.click(`${dropdownSelector} .select2-choice`);
      const option = await this.page.locator(`.select2-result-label:has-text("${optionText}")`);
      await option.click();
    }
  
    async clickSearchLabel() {
      await this.page.click('[data-name="Search"]');
    }
  
    async clickFindContacts() {
      await this.page.click('[data-name="Find Contacts"] a', { force: true });
      await this.page.waitForTimeout(1000)

    }
  
    async inputUserNameOrEmail(userNameOrEmail) {
      await this.page.fill('input#sort_name', userNameOrEmail);
    }
  
    async selectContactType(contactType) {
      await this.selectContactTypeDropdown('#s2id_contact_type', contactType);
    }

    async clickSearchButton() {
        // Locate the submit button by its role and click it
        await this.page.getByRole('button', { name: /search/i }).click();
    }
      
  }