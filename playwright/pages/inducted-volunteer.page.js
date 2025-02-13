import { Page } from '@playwright/test';

exports.InductedVolunteerPage = class InductedVolunteerPage  {
    constructor(page) {
      this.page = page;
    }

    // Method to check if a row with the specified name exists
    async checkIfNameExists(name) {
        const row = await this.page.locator(`tr >> text="${name}"`);

        // Check if the row exists based on the name
        const isNamePresent = await row.count() > 0;

        if (isNamePresent) {
            console.log(`Row with Name: "${name}" exists.`);
            return true;
        } else {
            console.log(`Row with Name: "${name}" does NOT exist.`);
            return false;
        }
    }

    // Method to check if a row with the specified email exists
    async checkIfEmailExists(email) {
        const row = await this.page.locator(`tr >> text="${email}"`);

        // Check if the row exists based on the email
        const isEmailPresent = await row.count() > 0;

        if (isEmailPresent) {
            console.log(`Row with Email: "${email}" exists.`);
            return true;
        } else {
            console.log(`Row with Email: "${email}" does NOT exist.`);
            return false;
        }
    }
};
