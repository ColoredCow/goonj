import {  } from '@playwright/test';

exports.VolunteerProfilePage = class VolunteerProfilePage {
  constructor(page) {
        this.page = page;
  }
    
  async selectRecordActivityOption(option) {
    const activityOptions = {
      'Meeting': 'li.crm-activity-type_0 a',
      'Phone Call': 'li.crm-activity-type_1 a',
      'Send an Email': 'li.crm-activity-type_2 a',
      'Print/Merge Document': 'li.crm-activity-type_3 a',
      'Volunteer Sign Up': 'li.crm-activity-type_4 a',
      'Induction': 'li.crm-activity-type_5 a',
      'Event Feedback - Goonj': 'li.crm-activity-type_6 a',
      'Event Feedback': 'li.crm-activity-type_7 a'
    };
    const selector = activityOptions[option];
    await this.page.click(selector);
  }

  async clickDialogButton(buttonIdentifier) {
    const buttonSelectors = {
      save: '[data-identifier="_qf_Activity_upload"]',
      cancel: '[data-identifier="_qf_Activity_cancel"]'
    };
    await this.page.click(buttonSelectors[buttonIdentifier], { force: true });
  }

  async volunteerProfileTabs(tabName) {
    const tabSelectors = {
      summary: 'li#tab_summary a.ui-tabs-anchor',
      contributions: 'li#tab_contribute a.ui-tabs-anchor',
      memberships: 'li#tab_member a.ui-tabs-anchor',
      events: 'li#tab_participant a.ui-tabs-anchor',
      activities: 'li#tab_activity a.ui-tabs-anchor',
      relationships: 'li#tab_rel a.ui-tabs-anchor',
      groups: 'li#tab_group a.ui-tabs-anchor',
      tags: 'li#tab_tag a.ui-tabs-anchor',
      changeLog: 'li#tab_log a.ui-tabs-anchor',
    };
    await this.page.click(tabSelectors[tabName], { force: true });
  }

  async clickActivitiesActionButton(type, status, action) {
    const rowSelector = `tr[data-entity="activity"] td:has-text("${type}") ~ td:has-text("${status}")`;
    await this.page.click(`${rowSelector} ~ td a:has-text("${action}")`, { force: true });
  }

  async selectActivityStatusValue(value) {
    await this.page.click(`#s2id_status_id .select2-choice`);
    await this.page.waitForTimeout(1000);
    await this.page.click(`.select2-results li:has-text("${value}")`);
  }

  async clickVolunteerSuboption(option) {
    const suboption = `[data-name="${option}"]`;
    await this.page.click(suboption);
  }

  async clickVolunteerHeader() {
    const volunteerHeader = 'li[data-name="Volunteers"] > a';
    await this.page.click(volunteerHeader);
  }

  async clickAddButton() {
    // Locate the submit button by its role and click it
    await this.page.getByRole('button', { name: /Add/i }).click();
  }

  async selectAddToGroupOption(optionText) {
    await this.page.click('a.select2-choice.select2-default');
    await this.page.fill('.select2-drop-active .select2-input', optionText);
    await this.page.waitForTimeout(500);
    const optionSelector = `.select2-drop-active .select2-results li div:has-text("${optionText}")`;
    await this.page.click(optionSelector);
  }

  async updateInductionForm(activityName, currentStatus, action, newStatus, buttonText) {
    await this.page.waitForTimeout(2000)
    await this.clickActivitiesActionButton(activityName, currentStatus, action);
    await this.page.waitForTimeout(2000);
    await this.selectActivityStatusValue(newStatus);
    await this.page.waitForTimeout(2000);
    await this.clickDialogButton(buttonText);
    await this.page.waitForTimeout(3000)
  }
}