/**
 * Chat Widget Page Object
 *
 * Page object for interacting with the AI BotKit chat widget.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { expect } = require('@playwright/test');

class ChatWidgetPage {
    /**
     * Constructor
     *
     * @param {Page} page Playwright page object
     */
    constructor(page) {
        this.page = page;

        // Widget selectors
        this.widgetButton = page.locator('[id^="ai-botkit-"][id$="-button"]');
        this.widgetContainer = page.locator('[id^="ai-botkit-"][id$="-chat"]');
        this.chatMessages = page.locator('.ai-botkit-chat-messages');
        this.chatInput = page.locator('#ai-botkit-chat-input');
        this.sendButton = page.locator('.ai-botkit-send-button');
        this.clearButton = page.locator('.ai-botkit-clear');
        this.minimizeButton = page.locator('.ai-botkit-minimize');
        this.newConversationButton = page.locator('.ai-botkit-new');
        this.typingIndicator = page.locator('.ai-botkit-typing');

        // Message selectors
        this.userMessages = page.locator('.ai-botkit-message.user');
        this.assistantMessages = page.locator('.ai-botkit-message.assistant');
        this.systemMessages = page.locator('.ai-botkit-message.system');
        this.errorMessages = page.locator('.ai-botkit-error');

        // Feedback selectors
        this.thumbsUpButton = page.locator('.ai-botkit-message-feedback-up-button');
        this.thumbsDownButton = page.locator('.ai-botkit-message-feedback-down-button');

        // Media selectors
        this.mediaButton = page.locator('.ai-botkit-media-btn');
        this.mediaInput = page.locator('.ai-botkit-media-input');
        this.mediaPreview = page.locator('.ai-botkit-media-preview-container');
    }

    /**
     * Navigate to a page with the chat widget
     *
     * @param {string} path Page path (default: homepage)
     */
    async goto(path = '/') {
        await this.page.goto(path);
        await this.waitForWidgetLoad();
    }

    /**
     * Wait for widget to load
     */
    async waitForWidgetLoad() {
        await expect(this.widgetButton).toBeVisible({ timeout: 15000 });
    }

    /**
     * Open the chat widget
     */
    async openWidget() {
        await this.widgetButton.click();
        await expect(this.widgetContainer).toBeVisible({ timeout: 5000 });
        await expect(this.widgetContainer).not.toHaveClass(/minimized/);
    }

    /**
     * Close/minimize the chat widget
     */
    async closeWidget() {
        await this.minimizeButton.click();
        await expect(this.widgetContainer).toHaveClass(/minimized/, { timeout: 5000 });
    }

    /**
     * Check if widget is open
     *
     * @returns {Promise<boolean>}
     */
    async isWidgetOpen() {
        const isVisible = await this.widgetContainer.isVisible();
        if (!isVisible) return false;

        const hasMinimizedClass = await this.widgetContainer.evaluate(
            el => el.classList.contains('minimized')
        );
        return !hasMinimizedClass;
    }

    /**
     * Send a message in the chat
     *
     * @param {string} message Message text
     */
    async sendMessage(message) {
        await expect(this.chatInput).toBeVisible();
        await expect(this.chatInput).toBeEnabled();

        await this.chatInput.fill(message);
        await this.sendButton.click();

        // Wait for message to appear
        await expect(this.userMessages.last()).toContainText(message, { timeout: 5000 });
    }

    /**
     * Wait for bot response
     *
     * @param {number} timeout Timeout in milliseconds
     */
    async waitForBotResponse(timeout = 30000) {
        // Wait for typing indicator to appear
        await expect(this.typingIndicator).toBeVisible({ timeout: 5000 }).catch(() => {
            // Typing indicator may not appear for fast responses
        });

        // Wait for typing indicator to disappear
        await expect(this.typingIndicator).not.toBeVisible({ timeout });

        // Verify a new assistant message appeared
        const messageCount = await this.assistantMessages.count();
        expect(messageCount).toBeGreaterThan(0);
    }

    /**
     * Get the last bot response text
     *
     * @returns {Promise<string>}
     */
    async getLastBotResponse() {
        const lastMessage = this.assistantMessages.last();
        await expect(lastMessage).toBeVisible();
        return await lastMessage.locator('.ai-botkit-message-text').textContent();
    }

    /**
     * Get the count of messages
     *
     * @returns {Promise<{user: number, assistant: number, total: number}>}
     */
    async getMessageCounts() {
        const userCount = await this.userMessages.count();
        const assistantCount = await this.assistantMessages.count();
        return {
            user: userCount,
            assistant: assistantCount,
            total: userCount + assistantCount,
        };
    }

    /**
     * Clear the conversation
     */
    async clearConversation() {
        await this.clearButton.click();

        // Handle SweetAlert confirmation if present
        const swalConfirm = this.page.locator('.swal2-confirm');
        if (await swalConfirm.isVisible({ timeout: 2000 }).catch(() => false)) {
            await swalConfirm.click();
        }

        // Wait for messages to be cleared
        await expect(this.userMessages).toHaveCount(0, { timeout: 5000 });
    }

    /**
     * Start a new conversation
     */
    async startNewConversation() {
        await this.newConversationButton.click();
        await expect(this.userMessages).toHaveCount(0, { timeout: 5000 });
    }

    /**
     * Give thumbs up feedback to the last bot message
     */
    async giveFeedbackThumbsUp() {
        const lastAssistantMessage = this.assistantMessages.last();
        const thumbsUp = lastAssistantMessage.locator('.ai-botkit-message-feedback-up-button');
        await thumbsUp.click();
        await expect(thumbsUp).toHaveClass(/ti-thumb-up-filled/, { timeout: 5000 });
    }

    /**
     * Give thumbs down feedback to the last bot message
     */
    async giveFeedbackThumbsDown() {
        const lastAssistantMessage = this.assistantMessages.last();
        const thumbsDown = lastAssistantMessage.locator('.ai-botkit-message-feedback-down-button');
        await thumbsDown.click();
        await expect(thumbsDown).toHaveClass(/ti-thumb-down-filled/, { timeout: 5000 });
    }

    /**
     * Upload a file via the media button
     *
     * @param {string} filePath Path to the file
     */
    async uploadFile(filePath) {
        // Set up file chooser handler
        const fileChooserPromise = this.page.waitForEvent('filechooser');
        await this.mediaButton.click();
        const fileChooser = await fileChooserPromise;
        await fileChooser.setFiles(filePath);

        // Wait for preview to appear
        await expect(this.mediaPreview).toBeVisible({ timeout: 10000 });
    }

    /**
     * Check if input is disabled (e.g., during processing)
     *
     * @returns {Promise<boolean>}
     */
    async isInputDisabled() {
        return await this.chatInput.isDisabled();
    }

    /**
     * Check if rate limit warning is displayed
     *
     * @returns {Promise<boolean>}
     */
    async hasRateLimitWarning() {
        const warning = this.page.locator('.rate-limit-warning');
        return await warning.isVisible();
    }

    /**
     * Scroll to the bottom of the chat messages
     */
    async scrollToBottom() {
        await this.chatMessages.evaluate(el => {
            el.scrollTop = el.scrollHeight;
        });
    }

    /**
     * Scroll to the top of the chat messages
     */
    async scrollToTop() {
        await this.chatMessages.evaluate(el => {
            el.scrollTop = 0;
        });
    }

    /**
     * Get the scroll position of the messages container
     *
     * @returns {Promise<{scrollTop: number, scrollHeight: number, clientHeight: number}>}
     */
    async getScrollPosition() {
        return await this.chatMessages.evaluate(el => ({
            scrollTop: el.scrollTop,
            scrollHeight: el.scrollHeight,
            clientHeight: el.clientHeight,
        }));
    }
}

module.exports = ChatWidgetPage;
