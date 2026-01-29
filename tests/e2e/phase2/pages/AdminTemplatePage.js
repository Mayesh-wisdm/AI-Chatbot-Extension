/**
 * Admin Template Page Object
 *
 * Page object for interacting with the AI BotKit templates admin page.
 * Implements selectors and methods based on templates.js implementation.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { expect } = require('@playwright/test');

class AdminTemplatePage {
    /**
     * Constructor
     *
     * @param {Page} page Playwright page object
     */
    constructor(page) {
        this.page = page;

        // Main page elements
        this.templatesGrid = page.locator('#ai-botkit-templates-grid');
        this.templateCards = page.locator('.ai-botkit-template-card');
        this.addTemplateButton = page.locator('.ai-botkit-add-template-btn');
        this.importTemplateButton = page.locator('.ai-botkit-import-template-btn');

        // Filter controls
        this.categoryFilter = page.locator('#ai-botkit-filter-category');
        this.typeFilter = page.locator('#ai-botkit-filter-type');
        this.sortBy = page.locator('#ai-botkit-sort-by');

        // Template card elements
        this.templateName = page.locator('.ai-botkit-template-name');
        this.templateDescription = page.locator('.ai-botkit-template-description');
        this.templateCategory = page.locator('.ai-botkit-template-category');
        this.systemBadge = page.locator('.ai-botkit-system-badge');

        // Card action buttons
        this.editButtons = page.locator('.ai-botkit-edit-template');
        this.duplicateButtons = page.locator('.ai-botkit-duplicate-template');
        this.applyButtons = page.locator('.ai-botkit-apply-template');
        this.exportButtons = page.locator('.ai-botkit-export-template');
        this.deleteButtons = page.locator('.ai-botkit-delete-template');

        // Template modal
        this.templateModal = page.locator('#ai-botkit-template-modal');
        this.modalTitle = page.locator('#ai-botkit-modal-title');
        this.templateForm = page.locator('#ai-botkit-template-form');

        // Form fields
        this.nameInput = page.locator('#template-name');
        this.descriptionInput = page.locator('#template-description');
        this.categorySelect = page.locator('#template-category');
        this.activeCheckbox = page.locator('#template-active');

        // Messages tab
        this.personalityInput = page.locator('#template-personality');
        this.greetingInput = page.locator('#template-greeting');
        this.fallbackInput = page.locator('#template-fallback');

        // Style tab
        this.primaryColorInput = page.locator('#template-primary-color');
        this.headerBgColorInput = page.locator('#template-header-bg-color');
        this.headerColorInput = page.locator('#template-header-color');
        this.bodyBgColorInput = page.locator('#template-body-bg-color');
        this.aiMsgBgInput = page.locator('#template-ai-msg-bg');
        this.userMsgBgInput = page.locator('#template-user-msg-bg');
        this.fontFamilyInput = page.locator('#template-font-family');
        this.positionSelect = page.locator('#template-position');

        // Model config tab
        this.modelSelect = page.locator('#template-model');
        this.temperatureSlider = page.locator('#template-temperature');
        this.temperatureValue = page.locator('#temperature-value');
        this.maxTokensInput = page.locator('#template-max-tokens');
        this.contextLengthInput = page.locator('#template-context-length');
        this.toneSelect = page.locator('#template-tone');

        // Conversation starters
        this.startersList = page.locator('#conversation-starters-list');
        this.starterItems = page.locator('.ai-botkit-starter-item');
        this.addStarterButton = page.locator('#add-starter-btn');

        // Modal buttons
        this.saveButton = page.locator('#save-template-btn');
        this.saveAsCopyButton = page.locator('#save-as-copy-btn');
        this.modalCloseButton = page.locator('.ai-botkit-modal-close');
        this.modalCancelButton = page.locator('.ai-botkit-modal-cancel');

        // System template notice
        this.systemNotice = page.locator('#template-system-notice');

        // Apply modal
        this.applyModal = page.locator('#ai-botkit-apply-modal');
        this.chatbotSelect = page.locator('#apply-chatbot-select');
        this.mergeMode = page.locator('input[name="apply_mode"][value="merge"]');
        this.replaceMode = page.locator('input[name="apply_mode"][value="replace"]');
        this.applyConfirmButton = page.locator('#apply-template-btn');

        // Import modal
        this.importModal = page.locator('#ai-botkit-import-modal');
        this.importFileInput = page.locator('#import-file');
        this.importConflictMode = page.locator('input[name="conflict_mode"]');
        this.importConfirmButton = page.locator('#import-template-btn');

        // Delete modal
        this.deleteModal = page.locator('#ai-botkit-delete-modal');
        this.deleteConfirmButton = page.locator('#confirm-delete-btn');
        this.templateNameConfirm = page.locator('.ai-botkit-template-name-confirm');

        // Tab navigation
        this.tabButtons = page.locator('.ai-botkit-tab-btn');
        this.tabPanels = page.locator('.ai-botkit-tab-panel');

        // Toast notifications
        this.successToast = page.locator('.ai-botkit-toast-success');
        this.errorToast = page.locator('.ai-botkit-toast-error');

        // Loading states
        this.loadingIndicator = page.locator('.ai-botkit-loading');
        this.noItemsMessage = page.locator('.ai-botkit-no-items');
    }

    /**
     * Navigate to the templates admin page
     */
    async goto() {
        await this.page.goto('/wp-admin/admin.php?page=ai-botkit-templates');
        await this.waitForPageLoad();
    }

    /**
     * Wait for page to load
     */
    async waitForPageLoad() {
        await expect(this.templatesGrid).toBeVisible({ timeout: 15000 });
        await expect(this.loadingIndicator).not.toBeVisible({ timeout: 10000 });
    }

    /**
     * Get the number of template cards
     *
     * @returns {Promise<number>}
     */
    async getTemplateCount() {
        return await this.templateCards.count();
    }

    /**
     * Get template card data by index
     *
     * @param {number} index Zero-based index
     * @returns {Promise<Object>}
     */
    async getTemplateData(index) {
        const card = this.templateCards.nth(index);
        await expect(card).toBeVisible();

        const id = await card.getAttribute('data-id');
        const name = await card.locator('.ai-botkit-template-name').textContent();
        const description = await card.locator('.ai-botkit-template-description').textContent().catch(() => '');
        const category = await card.locator('.ai-botkit-template-category').textContent();
        const isSystem = await card.locator('.ai-botkit-system-badge').isVisible().catch(() => false);
        const usageCount = await card.locator('.ai-botkit-usage-count').textContent().catch(() => '0');

        return { id, name, description, category, isSystem, usageCount };
    }

    /**
     * Filter templates by category
     *
     * @param {string} category Category value
     */
    async filterByCategory(category) {
        await this.categoryFilter.selectOption(category);
        await this.waitForPageLoad();
    }

    /**
     * Filter templates by type (system/custom)
     *
     * @param {string} type 'system', 'custom', or '' for all
     */
    async filterByType(type) {
        await this.typeFilter.selectOption(type);
        await this.waitForPageLoad();
    }

    /**
     * Sort templates
     *
     * @param {string} sortOption Sort option value
     */
    async sortTemplates(sortOption) {
        await this.sortBy.selectOption(sortOption);
        await this.waitForPageLoad();
    }

    /**
     * Open new template modal
     */
    async openNewTemplateModal() {
        await this.addTemplateButton.click();
        await expect(this.templateModal).toBeVisible({ timeout: 5000 });
        await expect(this.modalTitle).toContainText('New Template');
    }

    /**
     * Open edit modal for a template
     *
     * @param {number} index Zero-based index
     */
    async editTemplate(index) {
        const card = this.templateCards.nth(index);
        const editButton = card.locator('.ai-botkit-edit-template');
        await editButton.click();
        await expect(this.templateModal).toBeVisible({ timeout: 5000 });
    }

    /**
     * Fill template form
     *
     * @param {Object} data Template data
     */
    async fillTemplateForm(data) {
        if (data.name) {
            await this.nameInput.fill(data.name);
        }
        if (data.description) {
            await this.descriptionInput.fill(data.description);
        }
        if (data.category) {
            await this.categorySelect.selectOption(data.category);
        }
        if (data.active !== undefined) {
            if (data.active) {
                await this.activeCheckbox.check();
            } else {
                await this.activeCheckbox.uncheck();
            }
        }
        if (data.personality) {
            await this.switchToTab('messages');
            await this.personalityInput.fill(data.personality);
        }
        if (data.greeting) {
            await this.switchToTab('messages');
            await this.greetingInput.fill(data.greeting);
        }
        if (data.primaryColor) {
            await this.switchToTab('style');
            await this.primaryColorInput.fill(data.primaryColor);
        }
        if (data.model) {
            await this.switchToTab('model');
            await this.modelSelect.selectOption(data.model);
        }
        if (data.temperature !== undefined) {
            await this.switchToTab('model');
            await this.temperatureSlider.fill(String(data.temperature));
        }
    }

    /**
     * Switch to a tab in the modal
     *
     * @param {string} tabName Tab name: 'general', 'messages', 'style', 'model', 'starters'
     */
    async switchToTab(tabName) {
        const tabButton = this.tabButtons.filter({ has: this.page.locator(`[data-tab="${tabName}"]`) });
        await tabButton.click();
        await expect(this.tabPanels.filter({ has: this.page.locator(`[data-panel="${tabName}"]`) }))
            .toHaveClass(/active/, { timeout: 2000 });
    }

    /**
     * Add a conversation starter
     *
     * @param {string} text Starter text
     * @param {string} icon Icon name (optional)
     */
    async addConversationStarter(text, icon = 'help-circle') {
        await this.switchToTab('starters');
        await this.addStarterButton.click();

        const lastStarter = this.starterItems.last();
        await lastStarter.locator('input[type="text"]').fill(text);
        if (icon) {
            await lastStarter.locator('select').selectOption(icon);
        }
    }

    /**
     * Remove a conversation starter
     *
     * @param {number} index Zero-based index
     */
    async removeConversationStarter(index) {
        const starter = this.starterItems.nth(index);
        const removeButton = starter.locator('.ai-botkit-remove-starter');
        await removeButton.click();
    }

    /**
     * Save the template
     */
    async saveTemplate() {
        await this.saveButton.click();
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        await expect(this.templateModal).not.toBeVisible({ timeout: 5000 });
    }

    /**
     * Save template as copy (for system templates)
     */
    async saveAsCopy() {
        await this.saveAsCopyButton.click();
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        await expect(this.templateModal).not.toBeVisible({ timeout: 5000 });
    }

    /**
     * Close the modal
     */
    async closeModal() {
        await this.modalCloseButton.click();
        await expect(this.templateModal).not.toBeVisible({ timeout: 5000 });
    }

    /**
     * Duplicate a template
     *
     * @param {number} index Zero-based index
     */
    async duplicateTemplate(index) {
        const card = this.templateCards.nth(index);
        const duplicateButton = card.locator('.ai-botkit-duplicate-template');
        await duplicateButton.click();
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
    }

    /**
     * Open apply template modal
     *
     * @param {number} index Zero-based index
     */
    async openApplyModal(index) {
        const card = this.templateCards.nth(index);
        const applyButton = card.locator('.ai-botkit-apply-template');
        await applyButton.click();
        await expect(this.applyModal).toBeVisible({ timeout: 5000 });
    }

    /**
     * Apply template to a chatbot
     *
     * @param {string} chatbotId Chatbot ID
     * @param {string} mode 'merge' or 'replace'
     */
    async applyTemplateToChatbot(chatbotId, mode = 'merge') {
        await this.chatbotSelect.selectOption(chatbotId);

        if (mode === 'merge') {
            await this.mergeMode.check();
        } else {
            await this.replaceMode.check();
        }

        await this.applyConfirmButton.click();
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        await expect(this.applyModal).not.toBeVisible({ timeout: 5000 });
    }

    /**
     * Export a template
     *
     * @param {number} index Zero-based index
     */
    async exportTemplate(index) {
        const card = this.templateCards.nth(index);
        const exportButton = card.locator('.ai-botkit-export-template');

        // Set up download handler
        const downloadPromise = this.page.waitForEvent('download');
        await exportButton.click();

        const download = await downloadPromise;
        const filename = download.suggestedFilename();

        // Verify it's a JSON file
        expect(filename).toMatch(/\.json$/);

        return { filename, download };
    }

    /**
     * Open import modal
     */
    async openImportModal() {
        await this.importTemplateButton.click();
        await expect(this.importModal).toBeVisible({ timeout: 5000 });
    }

    /**
     * Import a template from file
     *
     * @param {string} filePath Path to JSON file
     * @param {string} conflictMode 'error', 'skip', or 'overwrite'
     */
    async importTemplate(filePath, conflictMode = 'error') {
        await this.importFileInput.setInputFiles(filePath);
        await this.page.locator(`input[name="conflict_mode"][value="${conflictMode}"]`).check();
        await this.importConfirmButton.click();
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        await expect(this.importModal).not.toBeVisible({ timeout: 5000 });
    }

    /**
     * Delete a template
     *
     * @param {number} index Zero-based index
     */
    async deleteTemplate(index) {
        const card = this.templateCards.nth(index);
        const deleteButton = card.locator('.ai-botkit-delete-template');
        const templateId = await card.getAttribute('data-id');

        await deleteButton.click();
        await expect(this.deleteModal).toBeVisible({ timeout: 5000 });
        await this.deleteConfirmButton.click();

        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        await expect(this.deleteModal).not.toBeVisible({ timeout: 5000 });

        // Verify card is removed
        await expect(
            this.templateCards.filter({ has: this.page.locator(`[data-id="${templateId}"]`) })
        ).toHaveCount(0, { timeout: 5000 });
    }

    /**
     * Check if system template notice is visible in modal
     *
     * @returns {Promise<boolean>}
     */
    async isSystemTemplateNoticeVisible() {
        return await this.systemNotice.isVisible();
    }

    /**
     * Check if no templates message is visible
     *
     * @returns {Promise<boolean>}
     */
    async isNoTemplatesMessageVisible() {
        return await this.noItemsMessage.isVisible();
    }

    /**
     * Wait for success toast and get message
     *
     * @returns {Promise<string>}
     */
    async waitForSuccessToast() {
        await expect(this.successToast).toBeVisible({ timeout: 10000 });
        return await this.successToast.locator('.ai-botkit-toast-message').textContent();
    }

    /**
     * Wait for error toast and get message
     *
     * @returns {Promise<string>}
     */
    async waitForErrorToast() {
        await expect(this.errorToast).toBeVisible({ timeout: 10000 });
        return await this.errorToast.locator('.ai-botkit-toast-message').textContent();
    }
}

module.exports = AdminTemplatePage;
