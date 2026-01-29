/**
 * Playwright Configuration for AI BotKit E2E Tests
 *
 * Configuration for running regression tests against WordPress with AI BotKit plugin.
 *
 * @see https://playwright.dev/docs/test-configuration
 */

const { defineConfig, devices } = require('@playwright/test');

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  // Test directory
  testDir: './regression',

  // Test file pattern
  testMatch: '**/*.spec.js',

  // Run tests in parallel
  fullyParallel: false, // Sequential for WordPress state consistency

  // Fail fast - stop on first failure during CI
  forbidOnly: !!process.env.CI,

  // Retry failed tests
  retries: process.env.CI ? 2 : 0,

  // Number of parallel workers
  workers: process.env.CI ? 1 : 1, // Single worker for WordPress consistency

  // Reporter configuration
  reporter: [
    ['html', { open: 'never', outputFolder: '../reports/html' }],
    ['json', { outputFile: '../reports/test-results.json' }],
    ['junit', { outputFile: '../reports/junit.xml' }],
    ['list'],
  ],

  // Global test timeout
  timeout: 60000,

  // Expect timeout
  expect: {
    timeout: 10000,
  },

  // Shared settings for all projects
  use: {
    // Base URL for all tests
    baseURL: process.env.WP_SITE_URL || 'http://localhost:8080',

    // Collect trace on failure
    trace: 'on-first-retry',

    // Take screenshot on failure
    screenshot: 'only-on-failure',

    // Record video on failure
    video: 'on-first-retry',

    // Browser context options
    contextOptions: {
      ignoreHTTPSErrors: true,
    },

    // Navigation timeout
    navigationTimeout: 30000,

    // Action timeout
    actionTimeout: 10000,
  },

  // Browser projects
  projects: [
    // Chrome - Primary browser
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 720 },
      },
    },

    // Firefox - Secondary browser
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 720 },
      },
    },

    // WebKit/Safari - Tertiary browser
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 1280, height: 720 },
      },
    },

    // Mobile Chrome
    {
      name: 'mobile-chrome',
      use: {
        ...devices['Pixel 5'],
      },
    },

    // Mobile Safari
    {
      name: 'mobile-safari',
      use: {
        ...devices['iPhone 12'],
      },
    },
  ],

  // Global setup - runs before all tests
  // globalSetup: require.resolve('./global-setup.js'),

  // Global teardown - runs after all tests
  // globalTeardown: require.resolve('./global-teardown.js'),

  // Output folder for test artifacts
  outputDir: '../reports/test-results',

  // Preserve output on failure
  preserveOutput: 'failures-only',
});
