import {  } from '@playwright/test';

exports.CollectionCampRecordsPage = class CollectionCampRecordsPage  {
    constructor(page) {
      this.page = page;
    }
  

    // Method to click on the Individuals tab
    async clickIndividualsTab (){
        // Click on the Individuals tab
        await this.page.click('li[data-name="Individuals"] > a');
        // Wait for the submenu to be visible
        await this.page.waitForSelector('ul[id^="sm-17403334627340344-42"]', { state: 'visible' });
    };

    // Method to click on Collection Camps
    async clickIndividualCollectionCamps(){
        await this.clickIndividualsTab(); // Open Individuals tab first
        await this.page.click('li[data-name="Collection Camps"] > a');
        await this.page.waitForNavigation();
        // Optionally, return to Individuals tab or perform additional actions here
    };

    // Method to click on Dropping Center
    async clickDroppingCenter(){
        await clickIndividualsTab(); // Open Individuals tab first
        await page.click('li[data-name="Dropping Center"] > a');
        await page.waitForNavigation();
        // Optionally, return to Individuals tab or perform additional actions here
    };

    // Method to click on Goonj Activities
    async clickGoonjActivities(){
        await clickIndividualsTab(); // Open Individuals tab first
        await page.click('li[data-name="Goonj Activities"] > a');
        await page.waitForNavigation();
        // Optionally, return to Individuals tab or perform additional actions here
    };

async  verifyCollectionCampRecord(organizer, location, state) {
  // Fill in the filters based on user inputs
  await page.fill('input[name="organizer"]', organizer); // Input field for organizer (e.g., "Collection Camp")
  await page.fill('input[name="location"]', location);  // Input field for location
  await page.fill('input[name="state"]', state);        // Input field for state
 
  // Wait for the table rows to load after applying the filters
  await page.waitForSelector('tr.ng-scope');

  // Locate the rows that match the conditions and check the values directly
  const rows = await page.locator('tr.ng-scope');  // Locate all table rows that are part of the collection

  const matchingRow = rows.locator(`td:nth-child(3) >> text=${location}`);
  
  // Check if the row matching the location is found
  const locationCell = await matchingRow.textContent();
  expect(locationCell).toContain(location);

  // Now check for the state and organizer in the same row by targeting the specific columns
  const stateCell = await matchingRow.locator('td:nth-child(4)').textContent();  // State column (adjust nth-child based on table structure)
  expect(stateCell).toContain(state);

  const organizerCell = await matchingRow.locator('td:nth-child(5)').textContent(); // Organizer column (adjust nth-child based on table structure)
  expect(organizerCell).toContain(organizer);

  }



  async selectCampaignFromSearch(page, campaignSearchTerm) {
    // Locate and click on the dropdown to open it
    const dropdown = await page.locator('#s2id_collection-camp-intent-details-campaign-28 .select2-choice');
    await dropdown.click();
  
    // Wait for the search box to appear and input the campaign search term
    const searchInput = await this.page.locator('#s2id_autogen14_search');
    await searchInput.fill(campaignSearchTerm);
  
    // Wait for search results to appear (optional: you can specify a timeout)
    await this.page.waitForSelector('.select2-results .select2-result-label', { timeout: 5000 });
  
    // Press Enter to select the first matching result
    await searchInput.press('Enter');
  
    console.log(`${campaignSearchTerm} selected by pressing Enter in the Campaign dropdown.`);
  }

async clickReviewLinCollectionCamp(initiator, state) {
  // Locate the row based on the contents of two specific columns (name and state)
  const row = await this.page.locator(`tr:has(td:has-text("${initiator}")):has(td:has-text("${state}"))`);

  // Locate the Review link inside the last column of the found row
  const reviewLink = row.locator('a:has-text("Review")');
  await reviewLink.click();
}

async selectRadioButton(page, fieldName, value) {
    // Find the radio button input elements by their name attribute
    const radioButton = await page.locator(`input[name="${fieldName}"][value="${value}"]`);
    
    // Check if the radio button exists and is not selected
    const isChecked = await radioButton.isChecked();
    
    if (!isChecked) {
      // Click the radio button to select it
      await radioButton.click();
      console.log(`Radio button with value ${value} selected.`);
    } else {
      console.log(`Radio button with value ${value} is already selected.`);
    }
  }

async selectCampaignForCollectionCamp(campaignName){
  const searchInput = await this.page.locator('#s2id_autogen64_search');

  // Type the campaign name into the search input
  await searchInput.fill(campaignName);

  // Wait for the filtered options to appear
  await this.page.waitForSelector('.select2-results li:has-text("' + campaignName + '")');

  // Click on the desired campaign option
  await this.page.click('.select2-results li:has-text("' + campaignName + '")');
}
 }



  