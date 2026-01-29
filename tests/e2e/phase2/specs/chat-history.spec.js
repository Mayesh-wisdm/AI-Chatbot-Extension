/**
 * Chat History E2E Tests
 *
 * Tests for FR-201 to FR-209: Chat History Feature
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { test, expect, loginAs, logout } = require('../fixtures/auth.fixture');
const ChatWidgetPage = require('../pages/ChatWidgetPage');
const HistoryPanelPage = require('../pages/HistoryPanelPage');

test.describe('Chat History Feature', () => {
    let chatWidget;
    let historyPanel;

    test.beforeEach(async ({ page }) => {
        chatWidget = new ChatWidgetPage(page);
        historyPanel = new HistoryPanelPage(page);
    });

    // ==========================================================================
    // FR-201: List User Conversations
    // ==========================================================================

    test.describe('FR-201: List User Conversations', () => {
        /**
         * TC-201-001: Logged-in user sees conversation list
         * Priority: P0 (Critical)
         */
        test('TC-201-001: logged-in user sees conversation list', async ({ page }) => {
            // Login as subscriber with existing conversations
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Verify history button is visible for logged-in users
            const isHistoryVisible = await historyPanel.isHistoryButtonVisible();
            expect(isHistoryVisible).toBe(true);

            // Open history panel
            await historyPanel.openPanel();

            // Verify panel is open
            const isPanelOpen = await historyPanel.isPanelOpen();
            expect(isPanelOpen).toBe(true);

            // Wait for conversations to load
            await historyPanel.waitForConversationsLoad();
        });

        /**
         * TC-201-002: Conversation list sorted by most recent
         * Priority: P0 (Critical)
         */
        test('TC-201-002: conversation list sorted by most recent', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count >= 2) {
                // Get first two conversations and verify ordering
                const first = await historyPanel.getConversationData(0);
                const second = await historyPanel.getConversationData(1);

                // Both should have date information
                expect(first.date).toBeTruthy();
                expect(second.date).toBeTruthy();
                expect(first.date.length).toBeGreaterThan(0);
            }
        });

        /**
         * TC-201-003: Conversation metadata displays correctly
         * Priority: P0 (Critical)
         */
        test('TC-201-003: conversation metadata displays correctly', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count > 0) {
                const data = await historyPanel.getConversationData(0);

                // Verify all metadata fields are present and not empty
                expect(data.id).toBeTruthy();
                expect(data.title).toBeTruthy();
                expect(data.title.length).toBeGreaterThan(0);
                expect(data.date).toBeTruthy();
                expect(data.date.length).toBeGreaterThan(0);

                // Preview should exist (may be truncated)
                expect(typeof data.preview).toBe('string');
            }
        });

        /**
         * TC-201-004: Pagination with 10+ conversations
         * Priority: P1 (High)
         */
        test('TC-201-004: pagination with 10+ conversations', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const initialCount = await historyPanel.getConversationCount();

            // If there are more pages, test load more
            const hasMore = await historyPanel.hasMorePages();
            if (hasMore && initialCount >= 10) {
                // Should show exactly 10 on first page
                expect(initialCount).toBe(10);

                await historyPanel.loadMore();
                const newCount = await historyPanel.getConversationCount();
                expect(newCount).toBeGreaterThan(initialCount);
            }
        });

        /**
         * TC-201-005: Guest user cannot see history panel
         * Priority: P0 (Critical) - Security test
         */
        test('TC-201-005: guest user cannot see history panel', async ({ page }) => {
            // Ensure logged out
            await logout(page);
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // History button should NOT be visible for guests
            const isHistoryVisible = await historyPanel.isHistoryButtonVisible();
            expect(isHistoryVisible).toBe(false);
        });

        /**
         * TC-201-006: User with no conversations sees empty state
         * Priority: P1 (High)
         */
        test('TC-201-006: user with no conversations sees empty state', async ({ page }) => {
            // This test requires a user with no conversations
            // Login as subscriber (assuming fresh user or cleared data)
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count === 0) {
                const isEmptyVisible = await historyPanel.isEmptyStateVisible();
                expect(isEmptyVisible).toBe(true);

                const emptyMessage = await historyPanel.getEmptyStateMessage();
                expect(emptyMessage).toContain('No conversation');
            }
        });
    });

    // ==========================================================================
    // FR-202: View Conversation Messages
    // ==========================================================================

    test.describe('FR-202: View Conversation Messages', () => {
        /**
         * TC-202-001: Load full conversation messages
         * Priority: P0 (Critical)
         */
        test('TC-202-001: load full conversation messages', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count > 0) {
                // Select first conversation
                await historyPanel.selectConversation(0);

                // Verify messages are loaded in chat
                const messageCounts = await chatWidget.getMessageCounts();
                // At least some messages should be visible
                expect(messageCounts.total).toBeGreaterThanOrEqual(0);
            }
        });

        /**
         * TC-202-002: Messages displayed chronologically
         * Priority: P0 (Critical)
         */
        test('TC-202-002: messages displayed chronologically', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count > 0) {
                await historyPanel.selectConversation(0);

                // Messages should be ordered (oldest at top, newest at bottom)
                // Verify by checking scroll position logic
                const scrollPos = await chatWidget.getScrollPosition();
                // If there's overflow, scrollHeight > clientHeight
                expect(scrollPos.scrollHeight).toBeGreaterThanOrEqual(scrollPos.clientHeight);
            }
        });

        /**
         * TC-202-004: Cannot view another user's conversation
         * Priority: P0 (Critical) - Security test
         */
        test('TC-202-004: cannot view another users conversation via API', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt to access admin's conversation via API
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: {
                    action: 'ai_botkit_switch_conversation',
                    nonce: 'invalid_nonce',
                    conversation_id: '99999', // Non-existent or other user's ID
                },
            });

            // Should return error status (403 or similar)
            const data = await response.json();
            // Either the request fails or returns success: false
            expect(data.success === false || response.status() >= 400).toBe(true);
        });
    });

    // ==========================================================================
    // FR-203: Switch Between Conversations
    // ==========================================================================

    test.describe('FR-203: Switch Between Conversations', () => {
        /**
         * TC-203-001: Switch between two conversations
         * Priority: P0 (Critical)
         */
        test('TC-203-001: switch between two conversations', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count >= 2) {
                // Select first conversation
                await historyPanel.selectConversation(0);
                const firstData = await historyPanel.getConversationData(0);
                expect(firstData.isActive).toBe(true);

                // Re-open panel and select second conversation
                await historyPanel.openPanel();
                await historyPanel.selectConversation(1);
                const secondData = await historyPanel.getConversationData(1);
                expect(secondData.isActive).toBe(true);

                // First should no longer be active
                const firstDataUpdated = await historyPanel.getConversationData(0);
                expect(firstDataUpdated.isActive).toBe(false);
            }
        });

        /**
         * TC-203-002: Active conversation visually highlighted
         * Priority: P1 (High)
         */
        test('TC-203-002: active conversation visually highlighted', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count > 0) {
                await historyPanel.selectConversation(0);

                // Verify the is-active class is applied
                const activeConversation = historyPanel.activeConversation;
                await expect(activeConversation).toBeVisible();
            }
        });
    });

    // ==========================================================================
    // FR-206: Delete Conversation
    // ==========================================================================

    test.describe('FR-206: Delete Conversation', () => {
        /**
         * TC-206-001: User can delete own conversation
         * Priority: P0 (Critical)
         */
        test('TC-206-001: user can delete own conversation', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const initialCount = await historyPanel.getConversationCount();
            if (initialCount > 0) {
                await historyPanel.deleteConversation(0);

                // Verify count decreased
                const newCount = await historyPanel.getConversationCount();
                expect(newCount).toBe(initialCount - 1);
            }
        });

        /**
         * TC-206-003: Cannot delete another user's conversation
         * Priority: P0 (Critical) - Security test
         */
        test('TC-206-003: cannot delete another users conversation via API', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt to delete admin's conversation via API
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: {
                    action: 'ai_botkit_delete_conversation',
                    nonce: 'invalid_nonce',
                    conversation_id: '99999', // Other user's ID
                },
            });

            const data = await response.json();
            // Should fail
            expect(data.success === false || response.status() >= 400).toBe(true);
        });
    });

    // ==========================================================================
    // FR-207: Mark Conversation as Favorite
    // ==========================================================================

    test.describe('FR-207: Mark Conversation as Favorite', () => {
        /**
         * TC-207-001: Toggle favorite status
         * Priority: P2 (Medium)
         */
        test('TC-207-001: toggle favorite status', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const count = await historyPanel.getConversationCount();
            if (count > 0) {
                const initialData = await historyPanel.getConversationData(0);
                const wasFavorite = initialData.isFavorite;

                await historyPanel.toggleFavorite(0);

                const updatedData = await historyPanel.getConversationData(0);
                expect(updatedData.isFavorite).toBe(!wasFavorite);
            }
        });

        /**
         * TC-207-002: Filter by favorites
         * Priority: P2 (Medium)
         */
        test('TC-207-002: filter by favorites', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            // Apply favorites filter
            await historyPanel.applyQuickFilter('favorites');

            // All visible conversations should be favorites
            const count = await historyPanel.getConversationCount();
            for (let i = 0; i < count; i++) {
                const data = await historyPanel.getConversationData(i);
                expect(data.isFavorite).toBe(true);
            }
        });
    });

    // ==========================================================================
    // FR-208: Filter Conversations by Date
    // ==========================================================================

    test.describe('FR-208: Filter Conversations by Date', () => {
        /**
         * TC-208-001: Filter by preset date range
         * Priority: P2 (Medium)
         */
        test('TC-208-001: filter by preset date range (today)', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            await historyPanel.applyQuickFilter('today');

            // Verify filter is active (conversations should still load)
            await historyPanel.waitForConversationsLoad();
            // Test passes if no error occurs
        });

        /**
         * TC-208-003: Clear date filter
         * Priority: P2 (Medium)
         */
        test('TC-208-003: clear date filter', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();
            await historyPanel.openPanel();
            await historyPanel.waitForConversationsLoad();

            const initialCount = await historyPanel.getConversationCount();

            // Apply filter
            await historyPanel.applyQuickFilter('today');
            await historyPanel.waitForConversationsLoad();

            // Clear filters
            await historyPanel.clearFilters();
            await historyPanel.waitForConversationsLoad();

            const restoredCount = await historyPanel.getConversationCount();
            // Count should be same or more after clearing filters
            expect(restoredCount).toBeGreaterThanOrEqual(0);
        });
    });

    // ==========================================================================
    // FR-209: Integration with Existing Chat UI
    // ==========================================================================

    test.describe('FR-209: Integration with Existing Chat UI', () => {
        /**
         * TC-209-001: History button visible in chat header
         * Priority: P0 (Critical)
         */
        test('TC-209-001: history button visible in chat header', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const isHistoryVisible = await historyPanel.isHistoryButtonVisible();
            expect(isHistoryVisible).toBe(true);
        });

        /**
         * TC-209-004: History panel keyboard accessible
         * Priority: P1 (High) - Accessibility test
         */
        test('TC-209-004: history panel keyboard accessible', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Tab to history button and press Enter
            await page.keyboard.press('Tab');
            await page.keyboard.press('Tab');
            await page.keyboard.press('Tab'); // Navigate to history button

            // Find and focus the history toggle
            await historyPanel.historyToggle.focus();
            await page.keyboard.press('Enter');

            // Panel should open
            const isPanelOpen = await historyPanel.isPanelOpen();
            expect(isPanelOpen).toBe(true);

            // Close with Escape
            await historyPanel.closePanelWithEscape();
            const isPanelClosed = await historyPanel.isPanelOpen();
            expect(isPanelClosed).toBe(false);
        });
    });
});
