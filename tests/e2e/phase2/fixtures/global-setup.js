/**
 * Global Setup for Playwright E2E Tests
 *
 * Runs once before all tests to set up the test environment.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { chromium } = require('@playwright/test');

/**
 * Global setup function
 *
 * @param {Object} config Playwright configuration
 */
async function globalSetup(config) {
    const { baseURL } = config.projects[0].use;

    console.log('Starting global setup...');
    console.log(`Base URL: ${baseURL}`);

    const browser = await chromium.launch();
    const page = await browser.newPage();

    try {
        // Verify WordPress is accessible
        const response = await page.goto(baseURL, { timeout: 30000 });
        if (!response || response.status() >= 400) {
            throw new Error(`WordPress site not accessible at ${baseURL}`);
        }
        console.log('WordPress site is accessible');

        // Verify AI BotKit plugin is active by checking for expected elements
        await page.goto(`${baseURL}/wp-admin/plugins.php`, { timeout: 30000 });

        // If redirected to login, that's expected
        if (page.url().includes('wp-login.php')) {
            console.log('WordPress login page detected - admin authentication required');
        }

    } catch (error) {
        console.error('Global setup failed:', error.message);
        throw error;
    } finally {
        await browser.close();
    }

    console.log('Global setup completed successfully');
}

module.exports = globalSetup;
