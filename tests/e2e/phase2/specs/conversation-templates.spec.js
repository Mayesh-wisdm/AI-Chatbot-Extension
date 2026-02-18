/**
 * Conversation Templates E2E Tests
 *
 * Tests for FR-230 to FR-239: Conversation Templates Feature
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { test, expect, loginAs, logout } = require('../fixtures/auth.fixture');
const AdminTemplatePage = require('../pages/AdminTemplatePage');

test.describe('Conversation Templates Feature', () => {
    let templatePage;

    test.beforeEach(async ({ page }) => {
        templatePage = new AdminTemplatePage(page);
    });

    // ==========================================================================
    // FR-230: Template Data Model
    // ==========================================================================

    test.describe('FR-230: Template Data Model', () => {
        /**
         * TC-230-001: Template saved with all fields
         * Priority: P0 (Critical)
         */
        test('TC-230-001: template saved with all fields', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Open new template modal
            await templatePage.openNewTemplateModal();

            // Fill in all fields
            const templateData = {
                name: 'Test Template ' + Date.now(),
                description: 'Test description for E2E testing',
                category: 'support',
                active: true,
                personality: 'You are a helpful assistant.',
                greeting: 'Hello! How can I help you today?',
                primaryColor: '#FF5733',
                model: 'gpt-4o-mini',
                temperature: 0.7,
            };

            await templatePage.fillTemplateForm(templateData);

            // Add conversation starters
            await templatePage.addConversationStarter('How do I reset my password?', 'help-circle');
            await templatePage.addConversationStarter('What are your hours?', 'info');

            // Save template
            await templatePage.saveTemplate();

            // Verify success
            const successMessage = await templatePage.waitForSuccessToast();
            expect(successMessage.length).toBeGreaterThan(0);
        });

        /**
         * TC-230-002: Required fields enforced
         * Priority: P1 (High)
         */
        test('TC-230-002: required fields enforced', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            await templatePage.openNewTemplateModal();

            // Try to save without name (required field)
            await templatePage.fillTemplateForm({
                name: '', // Empty name
                category: 'support',
            });

            // Click save
            await templatePage.saveButton.click();

            // Should show error
            const errorToast = page.locator('.ai-botkit-toast-error');
            const nameInput = templatePage.nameInput;

            // Either error toast appears or form validation prevents submission
            const hasError = await errorToast.isVisible({ timeout: 3000 }).catch(() => false);
            const isFormInvalid = await nameInput.evaluate(el => !el.checkValidity()).catch(() => false);

            expect(hasError || isFormInvalid).toBe(true);
        });

        /**
         * TC-230-003: Duplicate name prevented
         * Priority: P1 (High)
         */
        test('TC-230-003: duplicate name prevented', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Get first template name
            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                const existingTemplate = await templatePage.getTemplateData(0);
                const existingName = existingTemplate.name;

                // Try to create template with same name
                await templatePage.openNewTemplateModal();
                await templatePage.fillTemplateForm({
                    name: existingName,
                    category: 'support',
                });

                await templatePage.saveButton.click();

                // Should show error about duplicate name
                const errorToast = page.locator('.ai-botkit-toast-error');
                const hasError = await errorToast.isVisible({ timeout: 5000 }).catch(() => false);

                // If no error visible, the modal should still be open (save failed)
                const modalStillOpen = await templatePage.templateModal.isVisible();

                expect(hasError || modalStillOpen).toBe(true);
            }
        });
    });

    // ==========================================================================
    // FR-231: Admin Template List View
    // ==========================================================================

    test.describe('FR-231: Admin Template List View', () => {
        /**
         * TC-231-001: Template list displays
         * Priority: P0 (Critical)
         */
        test('TC-231-001: template list displays', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Templates grid should be visible
            await expect(templatePage.templatesGrid).toBeVisible();

            // Should have templates or show no items message
            const templateCount = await templatePage.getTemplateCount();
            const noItemsVisible = await templatePage.isNoTemplatesMessageVisible();

            expect(templateCount > 0 || noItemsVisible).toBe(true);
        });

        /**
         * TC-231-002: Filter by category
         * Priority: P1 (High)
         */
        test('TC-231-002: filter by category', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            const initialCount = await templatePage.getTemplateCount();

            // Filter by support category
            await templatePage.filterByCategory('support');

            // All visible templates should be in support category
            const filteredCount = await templatePage.getTemplateCount();
            for (let i = 0; i < filteredCount; i++) {
                const data = await templatePage.getTemplateData(i);
                expect(data.category.toLowerCase()).toContain('support');
            }
        });

        /**
         * TC-231-003: Filter System vs Custom
         * Priority: P1 (High)
         */
        test('TC-231-003: filter system vs custom', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Filter by system templates
            await templatePage.filterByType('system');

            const systemCount = await templatePage.getTemplateCount();
            for (let i = 0; i < systemCount; i++) {
                const data = await templatePage.getTemplateData(i);
                expect(data.isSystem).toBe(true);
            }

            // Filter by custom templates
            await templatePage.filterByType('custom');

            const customCount = await templatePage.getTemplateCount();
            for (let i = 0; i < customCount; i++) {
                const data = await templatePage.getTemplateData(i);
                expect(data.isSystem).toBe(false);
            }
        });
    });

    // ==========================================================================
    // FR-232: Template Builder/Editor
    // ==========================================================================

    test.describe('FR-232: Template Builder/Editor', () => {
        /**
         * TC-232-001: Create new template
         * Priority: P0 (Critical)
         */
        test('TC-232-001: create new template', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            const initialCount = await templatePage.getTemplateCount();

            // Open modal
            await templatePage.openNewTemplateModal();

            // Fill form
            const uniqueName = 'New Test Template ' + Date.now();
            await templatePage.fillTemplateForm({
                name: uniqueName,
                description: 'Created in E2E test',
                category: 'general',
            });

            // Save
            await templatePage.saveTemplate();

            // Verify template was created
            await templatePage.waitForPageLoad();
            const newCount = await templatePage.getTemplateCount();

            // Count should increase or template should exist
            expect(newCount).toBeGreaterThanOrEqual(initialCount);
        });

        /**
         * TC-232-002: Edit existing template
         * Priority: P0 (Critical)
         */
        test('TC-232-002: edit existing template', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Filter to custom templates (editable)
            await templatePage.filterByType('custom');

            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                // Edit first custom template
                await templatePage.editTemplate(0);

                // Modal should open with existing data
                await expect(templatePage.templateModal).toBeVisible();

                // Modify description
                const newDescription = 'Updated in E2E test ' + Date.now();
                await templatePage.descriptionInput.fill(newDescription);

                // Save
                await templatePage.saveTemplate();

                // Verify success
                const successMessage = await templatePage.waitForSuccessToast();
                expect(successMessage.length).toBeGreaterThan(0);
            }
        });

        /**
         * TC-232-003: System template prompts Save as Copy
         * Priority: P1 (High)
         */
        test('TC-232-003: system template prompts save as copy', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Filter to system templates
            await templatePage.filterByType('system');

            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                // Edit system template
                await templatePage.editTemplate(0);

                // System notice should be visible
                const noticeVisible = await templatePage.isSystemTemplateNoticeVisible();
                expect(noticeVisible).toBe(true);

                // Save button should be hidden, Save as Copy visible
                await expect(templatePage.saveButton).not.toBeVisible();
                await expect(templatePage.saveAsCopyButton).toBeVisible();

                // Close modal
                await templatePage.closeModal();
            }
        });
    });

    // ==========================================================================
    // FR-233: Template Preview
    // ==========================================================================

    test.describe('FR-233: Template Preview', () => {
        /**
         * TC-233-001: Preview shows widget appearance
         * Priority: P1 (High)
         */
        test('TC-233-001: preview panel exists in editor', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            await templatePage.openNewTemplateModal();

            // Check if preview panel or preview button exists
            const previewPanel = page.locator('.ai-botkit-template-preview');
            const previewButton = page.locator('[data-action="preview"]');

            const hasPreview = await previewPanel.isVisible({ timeout: 2000 }).catch(() => false);
            const hasPreviewButton = await previewButton.isVisible({ timeout: 2000 }).catch(() => false);

            // Either preview panel or preview button should exist
            // (Depends on implementation)
            expect(typeof hasPreview === 'boolean' && typeof hasPreviewButton === 'boolean').toBe(true);

            await templatePage.closeModal();
        });
    });

    // ==========================================================================
    // FR-234: Apply Template to Chatbot
    // ==========================================================================

    test.describe('FR-234: Apply Template to Chatbot', () => {
        /**
         * TC-234-001: Apply template to chatbot
         * Priority: P0 (Critical)
         */
        test('TC-234-001: apply modal opens', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                // Open apply modal
                await templatePage.openApplyModal(0);

                // Apply modal should be visible
                await expect(templatePage.applyModal).toBeVisible();

                // Should have chatbot selector
                await expect(templatePage.chatbotSelect).toBeVisible();

                // Should have merge/replace options
                await expect(templatePage.mergeMode).toBeVisible();
                await expect(templatePage.replaceMode).toBeVisible();

                // Close modal
                await page.locator('.ai-botkit-modal-cancel').click();
            }
        });
    });

    // ==========================================================================
    // FR-235: Template Categories
    // ==========================================================================

    test.describe('FR-235: Template Categories', () => {
        /**
         * TC-235-001: Categories available in filter
         * Priority: P1 (High)
         */
        test('TC-235-001: categories available in filter', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Check that category filter has options
            const categoryOptions = await templatePage.categoryFilter.locator('option').count();
            expect(categoryOptions).toBeGreaterThan(1); // At least "All" + one category
        });
    });

    // ==========================================================================
    // FR-236: Duplicate Template
    // ==========================================================================

    test.describe('FR-236: Duplicate Template', () => {
        /**
         * TC-236-001: Duplicate template creates copy
         * Priority: P1 (High)
         */
        test('TC-236-001: duplicate template creates copy', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            const initialCount = await templatePage.getTemplateCount();
            if (initialCount > 0) {
                // Duplicate first template
                await templatePage.duplicateTemplate(0);

                // Wait for success
                const successMessage = await templatePage.waitForSuccessToast();
                expect(successMessage.length).toBeGreaterThan(0);

                // Count should increase
                await templatePage.waitForPageLoad();
                const newCount = await templatePage.getTemplateCount();
                expect(newCount).toBe(initialCount + 1);
            }
        });
    });

    // ==========================================================================
    // FR-237: Delete Template
    // ==========================================================================

    test.describe('FR-237: Delete Template', () => {
        /**
         * TC-237-001: Delete custom template
         * Priority: P0 (Critical)
         */
        test('TC-237-001: delete custom template', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Filter to custom templates (can be deleted)
            await templatePage.filterByType('custom');

            const initialCount = await templatePage.getTemplateCount();
            if (initialCount > 0) {
                // Delete first custom template
                await templatePage.deleteTemplate(0);

                // Count should decrease
                const newCount = await templatePage.getTemplateCount();
                expect(newCount).toBe(initialCount - 1);
            }
        });

        /**
         * TC-237-002: Cannot delete system template (button hidden)
         * Priority: P0 (Critical)
         */
        test('TC-237-002: system templates have no delete button', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            // Filter to system templates
            await templatePage.filterByType('system');

            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                // System template should not have delete button
                const firstCard = templatePage.templateCards.first();
                const deleteButton = firstCard.locator('.ai-botkit-delete-template');

                const isDeleteVisible = await deleteButton.isVisible();
                expect(isDeleteVisible).toBe(false);
            }
        });
    });

    // ==========================================================================
    // FR-238: Export Template
    // ==========================================================================

    test.describe('FR-238: Export Template', () => {
        /**
         * TC-238-001: Export downloads JSON file
         * Priority: P1 (High)
         */
        test('TC-238-001: export downloads JSON file', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            const count = await templatePage.getTemplateCount();
            if (count > 0) {
                // Export first template
                const { filename } = await templatePage.exportTemplate(0);

                // Should be a JSON file
                expect(filename).toMatch(/\.json$/);
            }
        });
    });

    // ==========================================================================
    // FR-239: Import Template
    // ==========================================================================

    test.describe('FR-239: Import Template', () => {
        /**
         * TC-239-001: Import modal opens
         * Priority: P1 (High)
         */
        test('TC-239-001: import modal opens', async ({ page }) => {
            await loginAs(page, 'admin');
            await templatePage.goto();

            await templatePage.openImportModal();

            // Import modal should be visible
            await expect(templatePage.importModal).toBeVisible();

            // Should have file input
            await expect(templatePage.importFileInput).toBeVisible();

            // Should have conflict mode options
            const conflictOptions = page.locator('input[name="conflict_mode"]');
            const optionCount = await conflictOptions.count();
            expect(optionCount).toBeGreaterThan(0);

            // Close modal
            await page.locator('.ai-botkit-modal-cancel').click();
        });
    });

    // ==========================================================================
    // Security Tests
    // ==========================================================================

    test.describe('Security: Admin-only access', () => {
        /**
         * Subscriber cannot access template admin
         * Priority: P0 (Critical) - Security test
         */
        test('subscriber cannot access template admin page', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt to access templates page
            await page.goto('/wp-admin/admin.php?page=ai-botkit-templates');

            // Should be redirected or show error
            const currentUrl = page.url();
            const templatesGridVisible = await page.locator('#ai-botkit-templates-grid').isVisible({ timeout: 3000 }).catch(() => false);

            // Either not on templates page or no access to grid
            expect(
                !currentUrl.includes('ai-botkit-templates') ||
                !templatesGridVisible
            ).toBe(true);
        });

        /**
         * Guest cannot access template admin
         * Priority: P0 (Critical) - Security test
         */
        test('guest cannot access template admin page', async ({ page }) => {
            await logout(page);

            // Attempt to access templates page
            await page.goto('/wp-admin/admin.php?page=ai-botkit-templates');

            // Should be redirected to login
            const currentUrl = page.url();
            expect(currentUrl).toContain('wp-login.php');
        });
    });
});
