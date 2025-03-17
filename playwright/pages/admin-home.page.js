import {  } from '@playwright/test';

exports.AdminHomePage = class AdminHomePage  {
    constructor(page) {
      this.page = page;
    }
    async clickVolunteerSubOption(optionName) {
        // Click on the "Volunteers" tab to reveal the submenu
        await this.page.click('li[data-name="Volunteers"] > a.has-submenu');
        // Wait for the submenu to be visible
        await this.page.waitForSelector('ul[role="group"][aria-hidden="false"]');
          // Click on the specified sub-option
        await this.page.click(`li[data-name="${optionName}"] > a`);
    }

     async clickInstitutesTab() { 
      await this.page.dblclick('li[data-name="Institute"] > a');
    }
}
