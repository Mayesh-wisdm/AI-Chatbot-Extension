/**
 * Authentication Fixture for AI BotKit E2E Tests
 *
 * Provides authentication helpers for logging in as different user roles.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { test as base, expect } = require('@playwright/test');

// Test user credentials (should be set via environment variables)
const TEST_USERS = {
    admin: {
        username: process.env.WP_ADMIN_USER || 'admin',
        password: process.env.WP_ADMIN_PASS || 'password',
    },
    subscriber: {
        username: process.env.WP_SUBSCRIBER_USER || 'subscriber',
        password: process.env.WP_SUBSCRIBER_PASS || 'password',
    },
    editor: {
        username: process.env.WP_EDITOR_USER || 'editor',
        password: process.env.WP_EDITOR_PASS || 'password',
    },
};

/**
 * Extended test fixture with authentication helpers
 */
const test = base.extend({
    /**
     * Login as admin user
     */
    authenticatedAdminPage: async ({ page }, use) => {
        await loginAs(page, 'admin');
        await use(page);
    },

    /**
     * Login as subscriber user
     */
    authenticatedSubscriberPage: async ({ page }, use) => {
        await loginAs(page, 'subscriber');
        await use(page);
    },

    /**
     * Login as editor user
     */
    authenticatedEditorPage: async ({ page }, use) => {
        await loginAs(page, 'editor');
        await use(page);
    },

    /**
     * Guest page (no authentication)
     */
    guestPage: async ({ page }, use) => {
        // Ensure we're logged out
        await logout(page);
        await use(page);
    },
});

/**
 * Login as a specific user role
 *
 * @param {Page} page Playwright page object
 * @param {string} role User role (admin, subscriber, editor)
 */
async function loginAs(page, role) {
    const user = TEST_USERS[role];
    if (!user) {
        throw new Error(`Unknown user role: ${role}`);
    }

    await page.goto('/wp-login.php');

    // Wait for login form
    await expect(page.locator('#loginform')).toBeVisible({ timeout: 10000 });

    // Fill login credentials
    await page.locator('#user_login').fill(user.username);
    await page.locator('#user_pass').fill(user.password);

    // Submit login form
    await page.locator('#wp-submit').click();

    // Wait for redirect to admin or dashboard
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    // Verify login succeeded by checking for admin bar or dashboard element
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
}

/**
 * Logout the current user
 *
 * @param {Page} page Playwright page object
 */
async function logout(page) {
    // Navigate to logout URL
    await page.goto('/wp-login.php?action=logout');

    // Handle logout confirmation if present
    const logoutLink = page.locator('a[href*="action=logout"]').first();
    if (await logoutLink.isVisible({ timeout: 2000 }).catch(() => false)) {
        await logoutLink.click();
    }

    // Wait for logout to complete
    await page.waitForURL(/wp-login\.php/, { timeout: 10000 }).catch(() => {
        // Already logged out
    });
}

/**
 * Check if user is logged in
 *
 * @param {Page} page Playwright page object
 * @returns {Promise<boolean>}
 */
async function isLoggedIn(page) {
    const adminBar = page.locator('#wpadminbar');
    return await adminBar.isVisible({ timeout: 2000 }).catch(() => false);
}

/**
 * Get current user info from WordPress
 *
 * @param {Page} page Playwright page object
 * @returns {Promise<Object|null>}
 */
async function getCurrentUser(page) {
    return await page.evaluate(() => {
        if (typeof wp !== 'undefined' && wp.api && wp.api.models && wp.api.models.User) {
            return wp.api.loadPromise.done(() => {
                const currentUser = new wp.api.models.User({ id: 'me' });
                return currentUser.fetch().then(() => currentUser.toJSON());
            });
        }
        return null;
    });
}

module.exports = {
    test,
    expect,
    loginAs,
    logout,
    isLoggedIn,
    getCurrentUser,
    TEST_USERS,
};
