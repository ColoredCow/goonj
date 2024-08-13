// @ts-check
const { defineConfig, devices } = require('@playwright/test');
/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// require('dotenv').config({ path: path.resolve(__dirname, '.env') });
// @ts-ignore
require('dotenv').config({path: '.env'});
/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './play-wright/e2e',
  // Set default timeout for the test case
  timeout: 60 * 1000, // Update timeout as 60 seconds
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 0 : 0, // update retry to 0
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 12 : 6,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  // reporter: 'html',
  reporter: [
    ['html', { outputFolder: './playwright-report', open: 'never' }],
    ['json', { outputFile: './playwright-report/results.json' }],
    // @ts-ignore
    // ['monocart-reporter', { 
    //   outputFolder: 'test-results', // Directory to save the report
    //   reportName: 'Playwright Test Report', // Custom report name
    //   format: 'html', // Report format
    //   theme: 'dark', // Optional: set theme
    //   discordWebhookUrl: 'https://discord.com/api/webhooks/1272525921995198534/Hh_3ccFsH5I8KGLNZiKUiJk12gLiLsyfks5qk0O0D6H9XKSSTM7vsGPDV6Rkx1paZEvH' // Discord webhook URL
    //   }
    // ]
    // ['monocart-reporter', { 
    //   name: "Playwright Test Report",
    //   outputFile: './test-results/report.html'
    // }]
    ['@estruyf/github-actions-reporter', {
      title: 'Playwright test reports',
      useDetails: true,
      showError: true,
      includeResults: ['pass', 'skipped', 'fail', 'flaky'],
    }]
   
  ],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
      // baseURL: process.env.BASE_URL || 'https://goonj.test/wp-admin',
      // baseURL: 'http://127.0.0.1:3000',
  
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
      trace: 'on-first-retry',
      video: 'on'
  },

  /* Configure projects for major browsers */
  projects: [
    //disabled browser as test case failing on multiple browsers, due to latest volunteer details not available 
    // not available 
    // {
    //   name: 'chromium',
    //   use: { ...devices['Desktop Chrome'] },
    // },
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },

    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },

    /* Test against mobile viewports. */
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },

    /* Test against branded browsers. */
    // {
    //   name: 'Microsoft Edge',
    //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
    // },
    // {
    //   name: 'Google Chrome',
    //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    // },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'npm run start',
  //   url: 'http://127.0.0.1:3000',
  //   reuseExistingServer: !process.env.CI,
  // },
});

