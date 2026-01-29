/**
 * Playwright Configuration for AI BotKit Phase 2 E2E Tests
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { defineConfig, devices } = require('@playwright/test');

// Load environment variables from .env.test if it exists
require('dotenv').config({ path: '.env.test' });

module.exports = defineConfig({
    testDir: './specs',

    // Run tests in parallel
    fullyParallel: true,

    // Fail the build on CI if test.only is left in code
    forbidOnly: !!process.env.CI,

    // Retry failed tests on CI
    retries: process.env.CI ? 2 : 0,

    // Limit parallel workers on CI
    workers: process.env.CI ? 1 : undefined,

    // Reporter configuration
    reporter: [
        ['html', { outputFolder: 'reports/html' }],
        ['json', { outputFile: 'reports/test-results.json' }],
        ['list']
    ],

    // Global timeout for each test
    timeout: 30000,

    // Expect timeout
    expect: {
        timeout: 10000,
    },

    // Shared settings for all projects
    use: {
        // Base URL for the WordPress site
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',

        // Capture trace on failure
        trace: 'on-first-retry',

        // Capture screenshot on failure
        screenshot: 'only-on-failure',

        // Record video on failure
        video: 'on-first-retry',

        // Viewport size
        viewport: { width: 1280, height: 720 },

        // Action timeout
        actionTimeout: 10000,

        // Navigation timeout
        navigationTimeout: 15000,
    },

    // Configure projects for different browsers
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
        // Mobile viewport tests
        {
            name: 'mobile-chrome',
            use: { ...devices['Pixel 5'] },
        },
        {
            name: 'mobile-safari',
            use: { ...devices['iPhone 12'] },
        },
    ],

    // Web server configuration (optional - for local development)
    webServer: process.env.WP_BASE_URL ? undefined : {
        command: 'wp-env start',
        url: 'http://localhost:8080',
        reuseExistingServer: !process.env.CI,
        timeout: 120000,
    },

    // Output directory for test artifacts
    outputDir: 'test-results/',

    // Global setup/teardown
    globalSetup: require.resolve('./fixtures/global-setup.js'),
    globalTeardown: require.resolve('./fixtures/global-teardown.js'),
});
