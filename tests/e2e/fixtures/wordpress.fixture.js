/**
 * WordPress Test Fixtures
 *
 * Reusable fixtures for E2E testing with WordPress and AI BotKit.
 * These fixtures provide common operations like authentication,
 * navigation, and test data management.
 *
 * @phase 1
 */

const { test: base, expect } = require('@playwright/test');

// Configuration from environment or defaults
const CONFIG = {
  SITE_URL: process.env.WP_SITE_URL || 'http://localhost:8080',
  ADMIN_USER: process.env.WP_ADMIN_USER || 'admin',
  ADMIN_PASS: process.env.WP_ADMIN_PASS || 'password',
  EDITOR_USER: process.env.WP_EDITOR_USER || 'editor',
  EDITOR_PASS: process.env.WP_EDITOR_PASS || 'password',
  AUTHOR_USER: process.env.WP_AUTHOR_USER || 'author',
  AUTHOR_PASS: process.env.WP_AUTHOR_PASS || 'password',
  SUBSCRIBER_USER: process.env.WP_SUBSCRIBER_USER || 'subscriber',
  SUBSCRIBER_PASS: process.env.WP_SUBSCRIBER_PASS || 'password',
};

/**
 * Authentication helper class
 */
class AuthHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Login as a specific WordPress user
   * @param {string} username - WordPress username
   * @param {string} password - WordPress password
   */
  async login(username, password) {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-login.php`);
    await this.page.fill('#user_login', username);
    await this.page.fill('#user_pass', password);
    await this.page.click('#wp-submit');
    await this.page.waitForURL('**/wp-admin/**');
  }

  /**
   * Login as administrator
   */
  async loginAsAdmin() {
    await this.login(CONFIG.ADMIN_USER, CONFIG.ADMIN_PASS);
  }

  /**
   * Login as editor
   */
  async loginAsEditor() {
    await this.login(CONFIG.EDITOR_USER, CONFIG.EDITOR_PASS);
  }

  /**
   * Login as author
   */
  async loginAsAuthor() {
    await this.login(CONFIG.AUTHOR_USER, CONFIG.AUTHOR_PASS);
  }

  /**
   * Login as subscriber
   */
  async loginAsSubscriber() {
    await this.login(CONFIG.SUBSCRIBER_USER, CONFIG.SUBSCRIBER_PASS);
  }

  /**
   * Logout current user
   */
  async logout() {
    await this.page.context().clearCookies();
  }

  /**
   * Check if user is logged in
   * @returns {Promise<boolean>}
   */
  async isLoggedIn() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/`);
    return !this.page.url().includes('wp-login.php');
  }

  /**
   * Get current user role via admin bar
   * @returns {Promise<string|null>}
   */
  async getCurrentUserRole() {
    const adminBar = this.page.locator('#wpadminbar');
    if (await adminBar.isVisible()) {
      const userInfo = await this.page.locator('#wp-admin-bar-my-account').textContent();
      return userInfo;
    }
    return null;
  }
}

/**
 * WordPress admin navigation helper
 */
class AdminNavigation {
  constructor(page) {
    this.page = page;
  }

  /**
   * Navigate to AI BotKit dashboard
   */
  async gotoDashboard() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to settings page
   */
  async gotoSettings() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-settings`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to chatbots page
   */
  async gotoChatbots() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-chatbots`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to knowledge base page
   */
  async gotoKnowledgeBase() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-knowledge-base`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to analytics page
   */
  async gotoAnalytics() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-analytics`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to security page
   */
  async gotoSecurity() {
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-security`);
    await this.page.waitForLoadState('networkidle');
  }
}

/**
 * AJAX helper for testing WordPress AJAX operations
 */
class AjaxHelper {
  constructor(page) {
    this.page = page;
    this.interceptedRequests = [];
  }

  /**
   * Start intercepting AJAX requests
   */
  startIntercepting() {
    this.page.on('request', request => {
      if (request.url().includes('admin-ajax.php')) {
        this.interceptedRequests.push({
          url: request.url(),
          method: request.method(),
          postData: request.postData(),
          timestamp: Date.now(),
        });
      }
    });
  }

  /**
   * Get intercepted requests matching action
   * @param {string} action - AJAX action name
   * @returns {Array}
   */
  getRequestsByAction(action) {
    return this.interceptedRequests.filter(req =>
      req.postData?.includes(`action=${action}`) ||
      req.postData?.includes(`"action":"${action}"`)
    );
  }

  /**
   * Clear intercepted requests
   */
  clearRequests() {
    this.interceptedRequests = [];
  }

  /**
   * Wait for AJAX request with specific action
   * @param {string} action - AJAX action name
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise<Request>}
   */
  async waitForRequest(action, timeout = 10000) {
    return this.page.waitForRequest(request =>
      request.url().includes('admin-ajax.php') &&
      request.postData()?.includes(action),
      { timeout }
    );
  }

  /**
   * Mock AJAX response
   * @param {string} action - AJAX action to mock
   * @param {object} response - Response data
   */
  async mockResponse(action, response) {
    await this.page.route('**/admin-ajax.php**', async route => {
      if (route.request().postData()?.includes(action)) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(response),
        });
      }
      return route.continue();
    });
  }
}

/**
 * Test data factory for creating test entities
 */
class TestDataFactory {
  constructor(page) {
    this.page = page;
  }

  /**
   * Create a test chatbot via AJAX
   * @param {object} data - Chatbot data
   * @returns {Promise<number>} - Chatbot ID
   */
  async createChatbot(data = {}) {
    const defaultData = {
      name: `Test Bot ${Date.now()}`,
      active: 1,
      ...data,
    };

    // Navigate to chatbots page
    await this.page.goto(`${CONFIG.SITE_URL}/wp-admin/admin.php?page=ai-botkit-chatbots`);

    // This would need to be implemented based on actual UI
    // For now, return a placeholder
    return 0;
  }

  /**
   * Generate unique test string
   * @param {string} prefix - Prefix for the string
   * @returns {string}
   */
  static uniqueString(prefix = 'test') {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Generate test message content
   * @returns {string}
   */
  static generateTestMessage() {
    const messages = [
      'Hello, can you help me?',
      'What services do you offer?',
      'How do I get started?',
      'Can you explain more about this?',
      'Thank you for your help!',
    ];
    return messages[Math.floor(Math.random() * messages.length)];
  }
}

/**
 * Chat widget helper for interacting with the chat interface
 */
class ChatWidgetHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Navigate to a page with the chat widget
   * @param {string} path - Page path
   */
  async navigateToChat(path = '/test-chatbot-page/') {
    await this.page.goto(`${CONFIG.SITE_URL}${path}`);
    await this.page.waitForSelector('.ai-botkit-chat, [id*="ai-botkit"]', { timeout: 10000 });
  }

  /**
   * Get the chat input element
   * @returns {Locator}
   */
  getInput() {
    return this.page.locator('.ai-botkit-input, #ai-botkit-chat-input');
  }

  /**
   * Get the send button element
   * @returns {Locator}
   */
  getSendButton() {
    return this.page.locator('.ai-botkit-send-button');
  }

  /**
   * Get all messages
   * @returns {Locator}
   */
  getMessages() {
    return this.page.locator('.ai-botkit-message');
  }

  /**
   * Get user messages
   * @returns {Locator}
   */
  getUserMessages() {
    return this.page.locator('.ai-botkit-message.user');
  }

  /**
   * Get assistant messages
   * @returns {Locator}
   */
  getAssistantMessages() {
    return this.page.locator('.ai-botkit-message.assistant, .ai-botkit-message:not(.user)');
  }

  /**
   * Send a message
   * @param {string} message - Message to send
   */
  async sendMessage(message) {
    const input = this.getInput();
    const sendButton = this.getSendButton();

    await input.fill(message);
    await sendButton.click();
  }

  /**
   * Wait for response
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForResponse(timeout = 30000) {
    const initialCount = await this.getAssistantMessages().count();
    await this.page.waitForFunction(
      (count) => document.querySelectorAll('.ai-botkit-message.assistant, .ai-botkit-message:not(.user)').length > count,
      initialCount,
      { timeout }
    ).catch(() => {
      // Timeout - response may not have arrived
    });
  }

  /**
   * Check if typing indicator is visible
   * @returns {Promise<boolean>}
   */
  async isTyping() {
    const typingIndicator = this.page.locator('.ai-botkit-typing');
    return typingIndicator.isVisible();
  }

  /**
   * Open widget (if minimized)
   */
  async openWidget() {
    const toggleButton = this.page.locator('[id*="ai-botkit"][id*="button"]');
    if (await toggleButton.isVisible()) {
      await toggleButton.click();
    }
  }

  /**
   * Close widget
   */
  async closeWidget() {
    const minimizeButton = this.page.locator('.ai-botkit-minimize');
    if (await minimizeButton.isVisible()) {
      await minimizeButton.click();
    }
  }

  /**
   * Clear conversation
   */
  async clearConversation() {
    const clearButton = this.page.locator('.ai-botkit-clear');
    if (await clearButton.isVisible()) {
      this.page.once('dialog', dialog => dialog.accept());
      await clearButton.click();
      await this.page.waitForTimeout(1000);
    }
  }
}

/**
 * Extended test fixture with all helpers
 */
const test = base.extend({
  // Authentication helper
  auth: async ({ page }, use) => {
    const auth = new AuthHelper(page);
    await use(auth);
  },

  // Admin navigation helper
  adminNav: async ({ page }, use) => {
    const nav = new AdminNavigation(page);
    await use(nav);
  },

  // AJAX helper
  ajax: async ({ page }, use) => {
    const ajax = new AjaxHelper(page);
    await use(ajax);
  },

  // Test data factory
  factory: async ({ page }, use) => {
    const factory = new TestDataFactory(page);
    await use(factory);
  },

  // Chat widget helper
  chat: async ({ page }, use) => {
    const chat = new ChatWidgetHelper(page);
    await use(chat);
  },

  // Pre-authenticated as admin
  authenticatedPage: async ({ page }, use) => {
    const auth = new AuthHelper(page);
    await auth.loginAsAdmin();
    await use(page);
  },
});

// Export everything
module.exports = {
  test,
  expect,
  CONFIG,
  AuthHelper,
  AdminNavigation,
  AjaxHelper,
  TestDataFactory,
  ChatWidgetHelper,
};
