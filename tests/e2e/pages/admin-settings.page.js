/**
 * Admin Settings Page Object
 *
 * Page Object Model for the AI BotKit settings admin page.
 * Provides methods for interacting with all settings elements.
 *
 * @phase 1
 */

class AdminSettingsPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page;
    this.baseUrl = process.env.WP_SITE_URL || 'http://localhost:8080';

    // Selectors
    this.selectors = {
      // Form elements
      saveButton: 'input[type="submit"], button:has-text("Save"), #submit',
      successMessage: '.notice-success, .updated',
      errorMessage: '.notice-error, .error',

      // OpenAI settings
      openaiApiKey: 'input[name*="openai"][name*="key"], input[id*="openai_api_key"]',
      openaiModel: 'select[name*="openai"][name*="model"], select[id*="openai_model"]',

      // Anthropic settings
      anthropicApiKey: 'input[name*="anthropic"][name*="key"], input[id*="anthropic_api_key"]',

      // Google settings
      googleApiKey: 'input[name*="google"][name*="key"], input[id*="google_api_key"]',

      // Together AI settings
      togetherApiKey: 'input[name*="together"][name*="key"], input[id*="together_api_key"]',

      // VoyageAI settings
      voyageApiKey: 'input[name*="voyage"][name*="key"], input[id*="voyageai_api_key"]',

      // Pinecone settings
      pineconeApiKey: 'input[name*="pinecone"][name*="key"], input[id*="pinecone_api_key"]',
      pineconeHost: 'input[name*="pinecone"][name*="host"], input[id*="pinecone_host"]',
      enablePinecone: 'input[name*="enable_pinecone"], input[type="checkbox"][id*="pinecone"]',

      // Generation settings
      temperature: 'input[name*="temperature"], input[id*="temperature"]',
      maxTokens: 'input[name*="max_tokens"], input[name*="tokens"], input[id*="max_tokens"]',
      streamResponses: 'input[name*="stream"], input[type="checkbox"][name*="stream"]',

      // Rate limiting
      tokenLimit: 'input[name*="token_limit"], input[name*="token_bucket"]',
      messageLimit: 'input[name*="message_limit"], input[name*="max_requests"]',

      // Test buttons
      testApiButton: 'button:has-text("Test"), [class*="test-api"]',
      testPineconeButton: 'button:has-text("Test Pinecone"), [data-action*="pinecone"]',
    };
  }

  /**
   * Navigate to the settings page
   */
  async goto() {
    await this.page.goto(`${this.baseUrl}/wp-admin/admin.php?page=ai-botkit-settings`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get response after navigation
   * @returns {Promise<Response|null>}
   */
  async gotoAndGetResponse() {
    return await this.page.goto(`${this.baseUrl}/wp-admin/admin.php?page=ai-botkit-settings`);
  }

  // ==========================================================================
  // API Key Methods
  // ==========================================================================

  /**
   * Set OpenAI API key
   * @param {string} key
   */
  async setOpenAIKey(key) {
    const input = this.page.locator(this.selectors.openaiApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  /**
   * Get OpenAI API key (masked)
   * @returns {Promise<string>}
   */
  async getOpenAIKey() {
    const input = this.page.locator(this.selectors.openaiApiKey).first();
    return await input.inputValue();
  }

  /**
   * Set Anthropic API key
   * @param {string} key
   */
  async setAnthropicKey(key) {
    const input = this.page.locator(this.selectors.anthropicApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  /**
   * Set Google API key
   * @param {string} key
   */
  async setGoogleKey(key) {
    const input = this.page.locator(this.selectors.googleApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  /**
   * Set Together AI API key
   * @param {string} key
   */
  async setTogetherKey(key) {
    const input = this.page.locator(this.selectors.togetherApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  /**
   * Set VoyageAI API key
   * @param {string} key
   */
  async setVoyageKey(key) {
    const input = this.page.locator(this.selectors.voyageApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  // ==========================================================================
  // Model Selection Methods
  // ==========================================================================

  /**
   * Select OpenAI model
   * @param {string} model
   */
  async selectOpenAIModel(model) {
    const select = this.page.locator(this.selectors.openaiModel).first();
    if (await select.isVisible()) {
      await select.selectOption(model);
    }
  }

  /**
   * Get selected OpenAI model
   * @returns {Promise<string>}
   */
  async getSelectedOpenAIModel() {
    const select = this.page.locator(this.selectors.openaiModel).first();
    return await select.inputValue();
  }

  // ==========================================================================
  // Generation Settings Methods
  // ==========================================================================

  /**
   * Set temperature value
   * @param {string|number} value
   */
  async setTemperature(value) {
    const input = this.page.locator(this.selectors.temperature).first();
    if (await input.isVisible()) {
      await input.fill(String(value));
    }
  }

  /**
   * Get temperature value
   * @returns {Promise<string>}
   */
  async getTemperature() {
    const input = this.page.locator(this.selectors.temperature).first();
    return await input.inputValue();
  }

  /**
   * Set max tokens value
   * @param {string|number} value
   */
  async setMaxTokens(value) {
    const input = this.page.locator(this.selectors.maxTokens).first();
    if (await input.isVisible()) {
      await input.fill(String(value));
    }
  }

  /**
   * Get max tokens value
   * @returns {Promise<string>}
   */
  async getMaxTokens() {
    const input = this.page.locator(this.selectors.maxTokens).first();
    return await input.inputValue();
  }

  /**
   * Enable/disable streaming responses
   * @param {boolean} enabled
   */
  async setStreamResponses(enabled) {
    const checkbox = this.page.locator(this.selectors.streamResponses).first();
    if (await checkbox.isVisible()) {
      if (enabled) {
        await checkbox.check();
      } else {
        await checkbox.uncheck();
      }
    }
  }

  /**
   * Check if streaming is enabled
   * @returns {Promise<boolean>}
   */
  async isStreamResponsesEnabled() {
    const checkbox = this.page.locator(this.selectors.streamResponses).first();
    return await checkbox.isChecked();
  }

  // ==========================================================================
  // Rate Limiting Methods
  // ==========================================================================

  /**
   * Set token limit
   * @param {string|number} value
   */
  async setTokenLimit(value) {
    const input = this.page.locator(this.selectors.tokenLimit).first();
    if (await input.isVisible()) {
      await input.fill(String(value));
    }
  }

  /**
   * Get token limit
   * @returns {Promise<string>}
   */
  async getTokenLimit() {
    const input = this.page.locator(this.selectors.tokenLimit).first();
    return await input.inputValue();
  }

  /**
   * Set message limit
   * @param {string|number} value
   */
  async setMessageLimit(value) {
    const input = this.page.locator(this.selectors.messageLimit).first();
    if (await input.isVisible()) {
      await input.fill(String(value));
    }
  }

  /**
   * Get message limit
   * @returns {Promise<string>}
   */
  async getMessageLimit() {
    const input = this.page.locator(this.selectors.messageLimit).first();
    return await input.inputValue();
  }

  // ==========================================================================
  // Pinecone Settings Methods
  // ==========================================================================

  /**
   * Set Pinecone API key
   * @param {string} key
   */
  async setPineconeKey(key) {
    const input = this.page.locator(this.selectors.pineconeApiKey).first();
    if (await input.isVisible()) {
      await input.fill(key);
    }
  }

  /**
   * Set Pinecone host
   * @param {string} host
   */
  async setPineconeHost(host) {
    const input = this.page.locator(this.selectors.pineconeHost).first();
    if (await input.isVisible()) {
      await input.fill(host);
    }
  }

  /**
   * Enable/disable Pinecone
   * @param {boolean} enabled
   */
  async setEnablePinecone(enabled) {
    const checkbox = this.page.locator(this.selectors.enablePinecone).first();
    if (await checkbox.isVisible()) {
      if (enabled) {
        await checkbox.check();
      } else {
        await checkbox.uncheck();
      }
    }
  }

  /**
   * Check if Pinecone is enabled
   * @returns {Promise<boolean>}
   */
  async isPineconeEnabled() {
    const checkbox = this.page.locator(this.selectors.enablePinecone).first();
    return await checkbox.isChecked();
  }

  // ==========================================================================
  // Form Actions
  // ==========================================================================

  /**
   * Save settings
   */
  async save() {
    const saveButton = this.page.locator(this.selectors.saveButton);
    await saveButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Check if success message is visible
   * @returns {Promise<boolean>}
   */
  async hasSuccessMessage() {
    const successMessage = this.page.locator(this.selectors.successMessage);
    return await successMessage.isVisible();
  }

  /**
   * Check if error message is visible
   * @returns {Promise<boolean>}
   */
  async hasErrorMessage() {
    const errorMessage = this.page.locator(this.selectors.errorMessage);
    return await errorMessage.isVisible();
  }

  /**
   * Get success message text
   * @returns {Promise<string>}
   */
  async getSuccessMessageText() {
    const successMessage = this.page.locator(this.selectors.successMessage);
    return await successMessage.textContent();
  }

  /**
   * Get error message text
   * @returns {Promise<string>}
   */
  async getErrorMessageText() {
    const errorMessage = this.page.locator(this.selectors.errorMessage);
    return await errorMessage.textContent();
  }

  // ==========================================================================
  // API Testing Methods
  // ==========================================================================

  /**
   * Click test API connection button
   * @param {string} provider - Provider name (optional)
   */
  async clickTestApiButton(provider = null) {
    let selector = this.selectors.testApiButton;
    if (provider) {
      selector = `button:has-text("Test ${provider}"), [data-provider="${provider}"] button`;
    }
    const button = this.page.locator(selector).first();
    if (await button.isVisible()) {
      await button.click();
    }
  }

  /**
   * Click test Pinecone connection button
   */
  async clickTestPineconeButton() {
    const button = this.page.locator(this.selectors.testPineconeButton).first();
    if (await button.isVisible()) {
      await button.click();
    }
  }

  // ==========================================================================
  // Utility Methods
  // ==========================================================================

  /**
   * Get locator for success message
   * @returns {Locator}
   */
  get successMessage() {
    return this.page.locator(this.selectors.successMessage);
  }

  /**
   * Get locator for error message
   * @returns {Locator}
   */
  get errorMessage() {
    return this.page.locator(this.selectors.errorMessage);
  }

  /**
   * Check if page is loaded
   * @returns {Promise<boolean>}
   */
  async isLoaded() {
    const saveButton = this.page.locator(this.selectors.saveButton);
    return await saveButton.isVisible();
  }

  /**
   * Get all form values as object
   * @returns {Promise<object>}
   */
  async getAllSettings() {
    return {
      openaiModel: await this.getSelectedOpenAIModel().catch(() => null),
      temperature: await this.getTemperature().catch(() => null),
      maxTokens: await this.getMaxTokens().catch(() => null),
      streamResponses: await this.isStreamResponsesEnabled().catch(() => null),
      tokenLimit: await this.getTokenLimit().catch(() => null),
      messageLimit: await this.getMessageLimit().catch(() => null),
      pineconeEnabled: await this.isPineconeEnabled().catch(() => null),
    };
  }
}

module.exports = { AdminSettingsPage };
