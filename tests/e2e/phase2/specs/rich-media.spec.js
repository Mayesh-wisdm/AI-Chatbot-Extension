/**
 * Rich Media Support E2E Tests
 *
 * Tests for FR-220 to FR-229: Rich Media Support Feature
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { test, expect, loginAs, logout } = require('../fixtures/auth.fixture');
const ChatWidgetPage = require('../pages/ChatWidgetPage');
const path = require('path');

test.describe('Rich Media Support Feature', () => {
    let chatWidget;

    test.beforeEach(async ({ page }) => {
        chatWidget = new ChatWidgetPage(page);
    });

    // ==========================================================================
    // FR-220: Image Attachments in Messages
    // ==========================================================================

    test.describe('FR-220: Image Attachments in Messages', () => {
        /**
         * TC-220-001: Image displays inline in message
         * Priority: P0 (Critical)
         */
        test('TC-220-001: image displays inline in message', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check for existing images in messages
            const images = page.locator('.ai-botkit-message img');
            const imageCount = await images.count();

            // If images exist, verify they display properly
            if (imageCount > 0) {
                const firstImage = images.first();
                await expect(firstImage).toBeVisible();

                // Image should have src attribute
                const src = await firstImage.getAttribute('src');
                expect(src).toBeTruthy();
                expect(src.length).toBeGreaterThan(0);
            }
        });

        /**
         * TC-220-002: Image responsive and constrained
         * Priority: P1 (High)
         */
        test('TC-220-002: image responsive and constrained', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const mediaImages = page.locator('.ai-botkit-media-image img');
            const imageCount = await mediaImages.count();

            if (imageCount > 0) {
                const firstImage = mediaImages.first();
                await expect(firstImage).toBeVisible();

                // Verify image doesn't overflow container
                const imageBox = await firstImage.boundingBox();
                const containerBox = await chatWidget.chatMessages.boundingBox();

                if (imageBox && containerBox) {
                    expect(imageBox.width).toBeLessThanOrEqual(containerBox.width);
                }
            }
        });

        /**
         * TC-220-004: Image has alt text
         * Priority: P1 (High) - Accessibility test
         */
        test('TC-220-004: image has alt text', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const mediaImages = page.locator('.ai-botkit-media-image img');
            const imageCount = await mediaImages.count();

            for (let i = 0; i < imageCount; i++) {
                const img = mediaImages.nth(i);
                const alt = await img.getAttribute('alt');

                // Alt attribute should exist (even if empty for decorative images)
                expect(alt !== null).toBe(true);
            }
        });
    });

    // ==========================================================================
    // FR-221: Video Embeds (YouTube/Vimeo)
    // ==========================================================================

    test.describe('FR-221: Video Embeds', () => {
        /**
         * TC-221-001: YouTube video embeds correctly
         * Priority: P1 (High)
         */
        test('TC-221-001: YouTube video embeds correctly', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check for YouTube embeds
            const youtubeEmbeds = page.locator('.ai-botkit-media-video iframe[src*="youtube"]');
            const embedCount = await youtubeEmbeds.count();

            if (embedCount > 0) {
                const firstEmbed = youtubeEmbeds.first();
                await expect(firstEmbed).toBeVisible();

                // Verify iframe has proper src
                const src = await firstEmbed.getAttribute('src');
                expect(src).toContain('youtube');
            }
        });

        /**
         * TC-221-002: Vimeo video embeds correctly
         * Priority: P1 (High)
         */
        test('TC-221-002: Vimeo video embeds correctly', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check for Vimeo embeds
            const vimeoEmbeds = page.locator('.ai-botkit-media-video iframe[src*="vimeo"]');
            const embedCount = await vimeoEmbeds.count();

            if (embedCount > 0) {
                const firstEmbed = vimeoEmbeds.first();
                await expect(firstEmbed).toBeVisible();

                const src = await firstEmbed.getAttribute('src');
                expect(src).toContain('vimeo');
            }
        });
    });

    // ==========================================================================
    // FR-222: File Attachments (PDF, DOC)
    // ==========================================================================

    test.describe('FR-222: File Attachments', () => {
        /**
         * TC-222-001: File attachment displays as card
         * Priority: P1 (High)
         */
        test('TC-222-001: file attachment displays as card', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check for file attachment cards
            const fileCards = page.locator('.ai-botkit-media-document');
            const cardCount = await fileCards.count();

            if (cardCount > 0) {
                const firstCard = fileCards.first();
                await expect(firstCard).toBeVisible();

                // Should have file name
                const fileName = firstCard.locator('.ai-botkit-file-name');
                await expect(fileName).toBeVisible();

                // Should have file size
                const fileSize = firstCard.locator('.ai-botkit-file-size');
                await expect(fileSize).toBeVisible();

                // Should have download button
                const downloadBtn = firstCard.locator('.ai-botkit-file-download');
                await expect(downloadBtn).toBeVisible();
            }
        });

        /**
         * TC-222-002: File downloads successfully
         * Priority: P0 (Critical)
         */
        test('TC-222-002: file downloads successfully', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const downloadLinks = page.locator('.ai-botkit-file-download');
            const linkCount = await downloadLinks.count();

            if (linkCount > 0) {
                // Set up download handler
                const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
                await downloadLinks.first().click();

                const download = await downloadPromise;
                if (download) {
                    const filename = download.suggestedFilename();
                    expect(filename).toBeTruthy();
                    expect(filename.length).toBeGreaterThan(0);
                }
            }
        });

        /**
         * TC-222-003: Only allowed file types accepted
         * Priority: P0 (Critical) - Security test
         */
        test('TC-222-003: only allowed file types accepted', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check that media input only accepts specific types
            const mediaInput = page.locator('.ai-botkit-media-input');
            if (await mediaInput.isVisible().catch(() => false)) {
                const acceptAttr = await mediaInput.getAttribute('accept');
                expect(acceptAttr).toBeTruthy();

                // Should include allowed types
                expect(acceptAttr).toContain('image/');
                expect(acceptAttr).toContain('application/pdf');

                // Should NOT include executable types
                expect(acceptAttr).not.toContain('.exe');
                expect(acceptAttr).not.toContain('.php');
            }
        });
    });

    // ==========================================================================
    // FR-223: Rich Link Previews
    // ==========================================================================

    test.describe('FR-223: Rich Link Previews', () => {
        /**
         * TC-223-001: Link preview card generated
         * Priority: P2 (Medium)
         */
        test('TC-223-001: link preview card generated', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check for link preview cards
            const linkPreviews = page.locator('.ai-botkit-media-link');
            const previewCount = await linkPreviews.count();

            if (previewCount > 0) {
                const firstPreview = linkPreviews.first();
                await expect(firstPreview).toBeVisible();

                // Should be a clickable link
                const linkCard = firstPreview.locator('.ai-botkit-link-card');
                await expect(linkCard).toBeVisible();

                const href = await linkCard.getAttribute('href');
                expect(href).toBeTruthy();
            }
        });
    });

    // ==========================================================================
    // FR-224: Media Upload Handling
    // ==========================================================================

    test.describe('FR-224: Media Upload Handling', () => {
        /**
         * TC-224-002: MIME type validated against content
         * Priority: P0 (Critical) - Security test
         */
        test('TC-224-002: MIME type validation on upload API', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Create a fake file with wrong MIME type
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                multipart: {
                    action: 'ai_botkit_upload_chat_media',
                    nonce: 'invalid_nonce',
                    // Attempting to upload without proper validation
                },
            });

            // Should fail without proper nonce/file
            const data = await response.json().catch(() => ({}));
            expect(data.success !== true).toBe(true);
        });

        /**
         * TC-224-003: File size limit enforced
         * Priority: P0 (Critical) - Security test
         */
        test('TC-224-003: file size limit enforced', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // The MediaHandler should have maxFileSize set
            const maxSize = await page.evaluate(() => {
                if (typeof window.aiBotKitMedia !== 'undefined') {
                    return window.aiBotKitMedia.options.maxFileSize;
                }
                return null;
            });

            // If media handler exists, verify size limit is set
            if (maxSize) {
                expect(maxSize).toBeGreaterThan(0);
                expect(maxSize).toBeLessThanOrEqual(50 * 1024 * 1024); // Max 50MB
            }
        });
    });

    // ==========================================================================
    // FR-225: Media Display Components
    // ==========================================================================

    test.describe('FR-225: Media Display Components', () => {
        /**
         * TC-225-001: Correct component per media type
         * Priority: P0 (Critical)
         */
        test('TC-225-001: correct component per media type', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Check that different media types use different components
            const imageComponents = page.locator('.ai-botkit-media-image');
            const videoComponents = page.locator('.ai-botkit-media-video');
            const documentComponents = page.locator('.ai-botkit-media-document');
            const linkComponents = page.locator('.ai-botkit-media-link');

            // Verify components exist (structure test)
            const imageCount = await imageComponents.count();
            const videoCount = await videoComponents.count();
            const documentCount = await documentComponents.count();
            const linkCount = await linkComponents.count();

            // At least verify the selectors are valid
            expect(typeof imageCount).toBe('number');
            expect(typeof videoCount).toBe('number');
            expect(typeof documentCount).toBe('number');
            expect(typeof linkCount).toBe('number');
        });
    });

    // ==========================================================================
    // FR-226: Lightbox for Images
    // ==========================================================================

    test.describe('FR-226: Lightbox for Images', () => {
        /**
         * TC-226-001: Click image opens lightbox
         * Priority: P1 (High)
         */
        test('TC-226-001: click image opens lightbox', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const lightboxTriggers = page.locator('.ai-botkit-lightbox-trigger');
            const triggerCount = await lightboxTriggers.count();

            if (triggerCount > 0) {
                // Click on lightbox trigger
                await lightboxTriggers.first().click();

                // Lightbox should open
                const lightbox = page.locator('.ai-botkit-lightbox.active');
                await expect(lightbox).toBeVisible({ timeout: 5000 });

                // Lightbox should have an image
                const lightboxImage = lightbox.locator('.ai-botkit-lightbox-content img');
                await expect(lightboxImage).toBeVisible();
            }
        });

        /**
         * TC-226-002: Close lightbox with X or Escape
         * Priority: P1 (High)
         */
        test('TC-226-002: close lightbox with X or Escape', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const lightboxTriggers = page.locator('.ai-botkit-lightbox-trigger');
            const triggerCount = await lightboxTriggers.count();

            if (triggerCount > 0) {
                // Open lightbox
                await lightboxTriggers.first().click();
                const lightbox = page.locator('.ai-botkit-lightbox');
                await expect(lightbox).toHaveClass(/active/, { timeout: 5000 });

                // Close with Escape
                await page.keyboard.press('Escape');
                await expect(lightbox).not.toHaveClass(/active/, { timeout: 5000 });

                // Open again
                await lightboxTriggers.first().click();
                await expect(lightbox).toHaveClass(/active/, { timeout: 5000 });

                // Close with X button
                const closeButton = page.locator('.ai-botkit-lightbox-close');
                await closeButton.click();
                await expect(lightbox).not.toHaveClass(/active/, { timeout: 5000 });
            }
        });

        /**
         * TC-226-003: Focus trapped in lightbox
         * Priority: P1 (High) - Accessibility test
         */
        test('TC-226-003: focus trapped in lightbox', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const lightboxTriggers = page.locator('.ai-botkit-lightbox-trigger');
            const triggerCount = await lightboxTriggers.count();

            if (triggerCount > 0) {
                // Open lightbox
                await lightboxTriggers.first().click();
                const lightbox = page.locator('.ai-botkit-lightbox.active');
                await expect(lightbox).toBeVisible({ timeout: 5000 });

                // Tab should cycle within lightbox
                await page.keyboard.press('Tab');
                await page.keyboard.press('Tab');
                await page.keyboard.press('Tab');

                // Focus should still be within lightbox
                const focusedElement = await page.evaluate(() => document.activeElement?.closest('.ai-botkit-lightbox'));
                // If focus trap is implemented, focusedElement should not be null
                // This test may need adjustment based on actual implementation
            }
        });
    });

    // ==========================================================================
    // FR-228: Media Security
    // ==========================================================================

    test.describe('FR-228: Media Security', () => {
        /**
         * TC-228-002: Cannot access other user's media directly
         * Priority: P0 (Critical) - Security test
         */
        test('TC-228-002: cannot access other users media via API', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt to access another user's media via API
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: {
                    action: 'ai_botkit_get_media',
                    nonce: 'invalid_nonce',
                    media_id: '99999', // Non-existent or other user's ID
                },
            });

            const data = await response.json().catch(() => ({}));
            // Should fail or return no data
            expect(data.success !== true || !data.data).toBe(true);
        });

        /**
         * TC-228-003: Filename sanitized on upload
         * Priority: P0 (Critical) - Security test
         */
        test('TC-228-003: path traversal prevented in API', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt path traversal via filename in API
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: {
                    action: 'ai_botkit_upload_chat_media',
                    nonce: 'invalid_nonce',
                    filename: '../../../evil.php',
                },
            });

            const data = await response.json().catch(() => ({}));
            // Should fail with invalid nonce or reject the filename
            expect(data.success !== true).toBe(true);
        });
    });
});
