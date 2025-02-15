import { expect } from '@playwright/test';

exports.InductedVolunteerPage = class InductedVolunteerPage  {
    constructor(page) {
      this.page = page;
    }

    async checkEmailExists(email) {
        const emailLocator = this.page.locator(`tr >> text="${email}"`);
    
        // Expect the email to be present in the table
        await expect(emailLocator).toHaveCount(1, { message: `Expected email '${email}' to exist, but it was not found.` });
    
        console.log(`Email '${email}' exists in the table.`);
    }
};
