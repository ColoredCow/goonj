import { expect } from '@playwright/test';

exports.InstituteRegistrationsRecordsPage = class InstituteRegistrationsRecordsPage   {
    constructor(page) {
      this.page = page;
    }
  
    async registeredInstituteStatus(page, instituteName, pocName, expectedStatus) {
         // Find the correct row by checking if it contains both the Institute & POC
    const row = page.locator('tr', {
        has: page.locator('td a', { hasText: instituteName }),
        has: page.locator('td a', { hasText: pocName })
    });

    await expect(row).toBeVisible({ timeout: 5000 });

    // Debug: Print all row data to find the correct column index
    const cells = await row.locator('td').allTextContents();
    console.log("Row Data:", cells);

    // Locate the specific column that contains authorization status (adjust nth-child index)
    const statusCell = row.locator('td:nth-child(5) span'); // Change '5' if needed

    // Ensure the status cell is visible
    await expect(statusCell.first()).toBeVisible();

    // Get the authorization status text
    const statusText = await statusCell.first().textContent();
    console.log(`Authorization Status: ${statusText.trim()}`);

    // Verify the expected authorization status
    await expect(statusCell.first()).toHaveText(expectedStatus);
};
   

}