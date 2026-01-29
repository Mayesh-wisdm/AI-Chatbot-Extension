/**
 * Chat Widget Page Object
 *
 * Page Object Model for the AI BotKit chat widget on frontend pages.
 * Provides methods for interacting with the chat interface.
 *
 * @phase 1
 */

class ChatWidgetPage {
  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(page) {
    this.page = page;
    this.baseUrl = process.env.WP_SITE_URL || 'http://localhost:8080';

    // Selectors
    this.selectors = {
      // Widget container
      widgetContainer: '[id*="ai-botkit"], .ai-botkit-chat',
      widgetButton: '[id*="ai-botkit"][id*="button"]',
      widgetChat: '[id*="ai-botkit"][id*="chat"]',

      // Chat components
      chatMessages: '.ai-botkit-chat-messages',
      message: '.ai-botkit-message',
      userMessage: '.ai-botkit-message.user',
      assistantMessage: '.ai-botkit-message.assistant, .ai-botkit-message:not(.user):not(.system)',
      systemMessage: '.ai-botkit-message.system',
      messageText: '.ai-botkit-message-text',

      // Input elements
      chatInput: '.ai-botkit-input, #ai-botkit-chat-input',
      sendButton: '.ai-botkit-send-button',

      // Controls
      clearButton: '.ai-botkit-clear',
      newButton: '.ai-botkit-new',
      minimizeButton: '.ai-botkit-minimize',

      // Status indicators
      typingIndicator: '.ai-botkit-typing',
      errorMessage: '.ai-botkit-error',
      rateLimitWarning: '.rate-limit-warning',

      // Feedback
      thumbsUpButton: '.ai-botkit-message-feedback-up-button, .ti-thumb-up',
      thumbsDownButton: '.ai-botkit-message-feedback-down-button, .ti-thumb-down',

      // Sources
      sourceLinks: '.ai-botkit-source-link, .sources-list a',
    };
  }

  /**
   * Navigate to a page with the chat widget
   * @param {string} path - Page path (default: /test-chatbot-page/)
   */
  async goto(path = '/test-chatbot-page/') {
    await this.page.goto(`${this.baseUrl}${path}`);
    await this.page.waitForSelector(this.selectors.widgetContainer, { timeout: 10000 });
  }

  /**
   * Navigate to specific post with chatbot
   * @param {number} postId - WordPress post ID
   */
  async gotoPost(postId) {
    await this.page.goto(`${this.baseUrl}/?p=${postId}`);
    await this.page.waitForSelector(this.selectors.widgetContainer, { timeout: 10000 });
  }

  // ==========================================================================
  // Widget State Methods
  // ==========================================================================

  /**
   * Check if widget is visible
   * @returns {Promise<boolean>}
   */
  async isWidgetVisible() {
    const widget = this.page.locator(this.selectors.widgetContainer);
    return await widget.isVisible();
  }

  /**
   * Check if widget is minimized
   * @returns {Promise<boolean>}
   */
  async isMinimized() {
    const widget = this.page.locator(this.selectors.widgetChat);
    const classes = await widget.getAttribute('class');
    return classes?.includes('minimized') || !(await widget.isVisible());
  }

  /**
   * Open the widget (click toggle button)
   */
  async open() {
    const button = this.page.locator(this.selectors.widgetButton);
    if (await button.isVisible()) {
      await button.click();
      await this.page.waitForTimeout(500); // Wait for animation
    }
  }

  /**
   * Close/minimize the widget
   */
  async close() {
    const minimizeButton = this.page.locator(this.selectors.minimizeButton);
    if (await minimizeButton.isVisible()) {
      await minimizeButton.click();
      await this.page.waitForTimeout(500); // Wait for animation
    }
  }

  // ==========================================================================
  // Input Methods
  // ==========================================================================

  /**
   * Get the chat input locator
   * @returns {Locator}
   */
  get input() {
    return this.page.locator(this.selectors.chatInput);
  }

  /**
   * Get the send button locator
   * @returns {Locator}
   */
  get sendButton() {
    return this.page.locator(this.selectors.sendButton);
  }

  /**
   * Type a message in the input field
   * @param {string} message
   */
  async typeMessage(message) {
    await this.input.fill(message);
  }

  /**
   * Get current input value
   * @returns {Promise<string>}
   */
  async getInputValue() {
    return await this.input.inputValue();
  }

  /**
   * Click the send button
   */
  async clickSend() {
    await this.sendButton.click();
  }

  /**
   * Send a message (type and click send)
   * @param {string} message
   */
  async sendMessage(message) {
    await this.typeMessage(message);
    await this.clickSend();
  }

  /**
   * Send message using Enter key
   * @param {string} message
   */
  async sendMessageWithEnter(message) {
    await this.typeMessage(message);
    await this.input.press('Enter');
  }

  /**
   * Check if input is enabled
   * @returns {Promise<boolean>}
   */
  async isInputEnabled() {
    return await this.input.isEnabled();
  }

  /**
   * Check if send button is enabled
   * @returns {Promise<boolean>}
   */
  async isSendButtonEnabled() {
    return await this.sendButton.isEnabled();
  }

  // ==========================================================================
  // Message Methods
  // ==========================================================================

  /**
   * Get all messages locator
   * @returns {Locator}
   */
  get messages() {
    return this.page.locator(this.selectors.message);
  }

  /**
   * Get user messages locator
   * @returns {Locator}
   */
  get userMessages() {
    return this.page.locator(this.selectors.userMessage);
  }

  /**
   * Get assistant messages locator
   * @returns {Locator}
   */
  get assistantMessages() {
    return this.page.locator(this.selectors.assistantMessage);
  }

  /**
   * Get count of all messages
   * @returns {Promise<number>}
   */
  async getMessageCount() {
    return await this.messages.count();
  }

  /**
   * Get count of user messages
   * @returns {Promise<number>}
   */
  async getUserMessageCount() {
    return await this.userMessages.count();
  }

  /**
   * Get count of assistant messages
   * @returns {Promise<number>}
   */
  async getAssistantMessageCount() {
    return await this.assistantMessages.count();
  }

  /**
   * Get the last message text
   * @returns {Promise<string>}
   */
  async getLastMessageText() {
    const lastMessage = this.messages.last();
    return await lastMessage.locator(this.selectors.messageText).textContent();
  }

  /**
   * Get the last user message text
   * @returns {Promise<string>}
   */
  async getLastUserMessageText() {
    const lastMessage = this.userMessages.last();
    return await lastMessage.locator(this.selectors.messageText).textContent();
  }

  /**
   * Get the last assistant message text
   * @returns {Promise<string>}
   */
  async getLastAssistantMessageText() {
    const lastMessage = this.assistantMessages.last();
    return await lastMessage.locator(this.selectors.messageText).textContent();
  }

  /**
   * Wait for a new message to appear
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForNewMessage(timeout = 30000) {
    const initialCount = await this.getMessageCount();
    await this.page.waitForFunction(
      (selector, count) =>
        document.querySelectorAll(selector).length > count,
      this.selectors.message,
      initialCount,
      { timeout }
    );
  }

  /**
   * Wait for assistant response
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForResponse(timeout = 30000) {
    const initialCount = await this.getAssistantMessageCount();
    await this.page.waitForFunction(
      (selector, count) =>
        document.querySelectorAll(selector).length > count,
      this.selectors.assistantMessage,
      initialCount,
      { timeout }
    ).catch(() => {
      // Timeout - response may not have arrived
    });
  }

  // ==========================================================================
  // Status Indicator Methods
  // ==========================================================================

  /**
   * Get typing indicator locator
   * @returns {Locator}
   */
  get typingIndicator() {
    return this.page.locator(this.selectors.typingIndicator);
  }

  /**
   * Check if typing indicator is visible
   * @returns {Promise<boolean>}
   */
  async isTyping() {
    return await this.typingIndicator.isVisible();
  }

  /**
   * Wait for typing indicator to appear
   * @param {number} timeout
   */
  async waitForTypingIndicator(timeout = 5000) {
    await this.typingIndicator.waitFor({ state: 'visible', timeout });
  }

  /**
   * Wait for typing indicator to disappear
   * @param {number} timeout
   */
  async waitForTypingToStop(timeout = 30000) {
    await this.typingIndicator.waitFor({ state: 'hidden', timeout });
  }

  /**
   * Check if there's an error message displayed
   * @returns {Promise<boolean>}
   */
  async hasError() {
    const error = this.page.locator(this.selectors.errorMessage);
    return await error.isVisible();
  }

  /**
   * Get error message text
   * @returns {Promise<string|null>}
   */
  async getErrorText() {
    const error = this.page.locator(this.selectors.errorMessage);
    if (await error.isVisible()) {
      return await error.textContent();
    }
    return null;
  }

  /**
   * Check if rate limited
   * @returns {Promise<boolean>}
   */
  async isRateLimited() {
    const warning = this.page.locator(this.selectors.rateLimitWarning);
    return await warning.isVisible();
  }

  // ==========================================================================
  // Action Methods
  // ==========================================================================

  /**
   * Clear the conversation
   * @param {boolean} confirmDialog - Whether to accept confirmation dialog
   */
  async clearConversation(confirmDialog = true) {
    const clearButton = this.page.locator(this.selectors.clearButton);
    if (await clearButton.isVisible()) {
      if (confirmDialog) {
        this.page.once('dialog', dialog => dialog.accept());
      } else {
        this.page.once('dialog', dialog => dialog.dismiss());
      }
      await clearButton.click();
      await this.page.waitForTimeout(1000);
    }
  }

  /**
   * Start a new conversation
   */
  async startNewConversation() {
    const newButton = this.page.locator(this.selectors.newButton);
    if (await newButton.isVisible()) {
      await newButton.click();
      await this.page.waitForTimeout(500);
    }
  }

  // ==========================================================================
  // Feedback Methods
  // ==========================================================================

  /**
   * Click thumbs up on the last assistant message
   */
  async clickThumbsUp() {
    const lastAssistantMessage = this.assistantMessages.last();
    const thumbsUp = lastAssistantMessage.locator(this.selectors.thumbsUpButton);
    if (await thumbsUp.isVisible()) {
      await thumbsUp.click();
    }
  }

  /**
   * Click thumbs down on the last assistant message
   */
  async clickThumbsDown() {
    const lastAssistantMessage = this.assistantMessages.last();
    const thumbsDown = lastAssistantMessage.locator(this.selectors.thumbsDownButton);
    if (await thumbsDown.isVisible()) {
      await thumbsDown.click();
    }
  }

  /**
   * Check if thumbs up is selected on last message
   * @returns {Promise<boolean>}
   */
  async isThumbsUpSelected() {
    const lastAssistantMessage = this.assistantMessages.last();
    const thumbsUp = lastAssistantMessage.locator(this.selectors.thumbsUpButton);
    const classes = await thumbsUp.getAttribute('class');
    return classes?.includes('filled') || classes?.includes('active');
  }

  /**
   * Check if thumbs down is selected on last message
   * @returns {Promise<boolean>}
   */
  async isThumbsDownSelected() {
    const lastAssistantMessage = this.assistantMessages.last();
    const thumbsDown = lastAssistantMessage.locator(this.selectors.thumbsDownButton);
    const classes = await thumbsDown.getAttribute('class');
    return classes?.includes('filled') || classes?.includes('active');
  }

  // ==========================================================================
  // Source Methods
  // ==========================================================================

  /**
   * Get source links from the last assistant message
   * @returns {Promise<Array<{title: string, url: string}>>}
   */
  async getSourceLinks() {
    const lastAssistantMessage = this.assistantMessages.last();
    const links = lastAssistantMessage.locator(this.selectors.sourceLinks);
    const count = await links.count();

    const sources = [];
    for (let i = 0; i < count; i++) {
      const link = links.nth(i);
      sources.push({
        title: await link.textContent(),
        url: await link.getAttribute('href'),
      });
    }
    return sources;
  }

  /**
   * Check if last message has sources
   * @returns {Promise<boolean>}
   */
  async hasSourceLinks() {
    const lastAssistantMessage = this.assistantMessages.last();
    const links = lastAssistantMessage.locator(this.selectors.sourceLinks);
    return (await links.count()) > 0;
  }

  // ==========================================================================
  // Scroll Methods
  // ==========================================================================

  /**
   * Get messages container locator
   * @returns {Locator}
   */
  get messagesContainer() {
    return this.page.locator(this.selectors.chatMessages);
  }

  /**
   * Check if scrolled to bottom
   * @returns {Promise<boolean>}
   */
  async isScrolledToBottom() {
    return await this.messagesContainer.evaluate(el => {
      return el.scrollTop + el.clientHeight >= el.scrollHeight - 50;
    });
  }

  /**
   * Scroll to bottom of messages
   */
  async scrollToBottom() {
    await this.messagesContainer.evaluate(el => {
      el.scrollTop = el.scrollHeight;
    });
  }

  // ==========================================================================
  // Utility Methods
  // ==========================================================================

  /**
   * Complete chat interaction: send message and wait for response
   * @param {string} message
   * @param {number} timeout - Timeout for response
   * @returns {Promise<string>} - The assistant response text
   */
  async chat(message, timeout = 30000) {
    await this.sendMessage(message);
    await this.waitForResponse(timeout);
    return await this.getLastAssistantMessageText();
  }

  /**
   * Check if widget is ready for interaction
   * @returns {Promise<boolean>}
   */
  async isReady() {
    const isVisible = await this.isWidgetVisible();
    const isInputEnabled = await this.isInputEnabled();
    return isVisible && isInputEnabled;
  }

  /**
   * Get widget state summary
   * @returns {Promise<object>}
   */
  async getState() {
    return {
      isVisible: await this.isWidgetVisible(),
      isMinimized: await this.isMinimized(),
      isInputEnabled: await this.isInputEnabled(),
      isSendEnabled: await this.isSendButtonEnabled(),
      isTyping: await this.isTyping(),
      hasError: await this.hasError(),
      isRateLimited: await this.isRateLimited(),
      messageCount: await this.getMessageCount(),
      userMessageCount: await this.getUserMessageCount(),
      assistantMessageCount: await this.getAssistantMessageCount(),
    };
  }
}

module.exports = { ChatWidgetPage };
