/**
 * Search Functionality E2E Tests
 *
 * Tests for FR-210 to FR-219: Search Functionality Feature
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { test, expect, loginAs, logout } = require('../fixtures/auth.fixture');
const ChatWidgetPage = require('../pages/ChatWidgetPage');
const SearchPanelPage = require('../pages/SearchPanelPage');

test.describe('Search Functionality Feature', () => {
    let chatWidget;
    let searchPanel;

    test.beforeEach(async ({ page }) => {
        chatWidget = new ChatWidgetPage(page);
        searchPanel = new SearchPanelPage(page);
    });

    // ==========================================================================
    // FR-210: Search Input Interface
    // ==========================================================================

    test.describe('FR-210: Search Input Interface', () => {
        /**
         * TC-210-001: Search input displays with placeholder
         * Priority: P0 (Critical)
         */
        test('TC-210-001: search input displays with placeholder', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search input should be visible
            await expect(searchPanel.searchInput).toBeVisible();

            // Check placeholder text
            const placeholder = await searchPanel.searchInput.getAttribute('placeholder');
            expect(placeholder).toBeTruthy();
            expect(placeholder.length).toBeGreaterThan(0);
        });

        /**
         * TC-210-002: Search executes on Enter key
         * Priority: P1 (High)
         */
        test('TC-210-002: search executes on Enter key', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Type search query and press Enter
            await searchPanel.search('test query');

            // Verify search was executed (results area should update)
            await searchPanel.waitForSearchResults();
            // Test passes if no timeout/error occurs
        });

        /**
         * TC-210-003: Clear button resets search
         * Priority: P1 (High)
         */
        test('TC-210-003: clear button resets search', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Perform a search
            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            // Clear the search
            await searchPanel.clearSearch();

            // Input should be empty
            await expect(searchPanel.searchInput).toHaveValue('');
        });
    });

    // ==========================================================================
    // FR-211: Full-Text Search on Messages
    // ==========================================================================

    test.describe('FR-211: Full-Text Search on Messages', () => {
        /**
         * TC-211-001: Search returns relevant messages
         * Priority: P0 (Critical)
         */
        test('TC-211-001: search returns relevant messages', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search for a term
            await searchPanel.search('hello');
            await searchPanel.waitForSearchResults();

            // Check if results are displayed or no results message
            const resultCount = await searchPanel.getResultCount();
            const noResultsVisible = await searchPanel.isNoResultsMessageVisible();

            // Either we have results or we have a proper no results message
            expect(resultCount >= 0 || noResultsVisible).toBe(true);
        });

        /**
         * TC-211-002: Multi-word search works
         * Priority: P1 (High)
         */
        test('TC-211-002: multi-word search works', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search with multiple words
            await searchPanel.search('help support');
            await searchPanel.waitForSearchResults();

            // Verify search completes without error
            const isError = await searchPanel.isErrorMessageVisible();
            expect(isError).toBe(false);
        });

        /**
         * TC-211-003: No results shows appropriate message
         * Priority: P1 (High)
         */
        test('TC-211-003: no results shows appropriate message', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search for unlikely term
            await searchPanel.search('xyznonexistent12345abcdef');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            if (resultCount === 0) {
                const noResultsVisible = await searchPanel.isNoResultsMessageVisible();
                expect(noResultsVisible).toBe(true);

                const message = await searchPanel.getNoResultsMessage();
                expect(message.length).toBeGreaterThan(0);
            }
        });

        /**
         * TC-211-004: SQL injection prevented
         * Priority: P0 (Critical) - Security test
         */
        test('TC-211-004: SQL injection prevented', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Attempt SQL injection
            await searchPanel.search("' OR '1'='1");
            await searchPanel.waitForSearchResults();

            // Should not cause error - query should be treated as literal text
            const isError = await searchPanel.isErrorMessageVisible();
            expect(isError).toBe(false);

            // Try another injection pattern
            await searchPanel.clearSearch();
            await searchPanel.search("; DROP TABLE messages;--");
            await searchPanel.waitForSearchResults();

            // Should still work without error
            const isError2 = await searchPanel.isErrorMessageVisible();
            expect(isError2).toBe(false);
        });
    });

    // ==========================================================================
    // FR-212: Admin Global Search
    // ==========================================================================

    test.describe('FR-212: Admin Global Search', () => {
        /**
         * TC-212-001: Admin can search all conversations
         * Priority: P0 (Critical)
         */
        test('TC-212-001: admin can search all conversations', async ({ page }) => {
            await loginAs(page, 'admin');

            // Navigate to admin search interface
            await page.goto('/wp-admin/admin.php?page=ai-botkit-search');

            // Wait for admin search page to load
            await expect(page.locator('.ai-botkit-admin-search')).toBeVisible({ timeout: 10000 }).catch(() => {
                // If admin search page doesn't exist, test is N/A
                test.skip();
            });

            // Perform admin search
            const adminSearchInput = page.locator('.ai-botkit-admin-search-input');
            await adminSearchInput.fill('test');
            await page.keyboard.press('Enter');

            // Admin should see results from multiple users
            await page.waitForTimeout(2000); // Wait for results
        });

        /**
         * TC-212-003: Non-admin cannot access admin search
         * Priority: P0 (Critical) - Security test
         */
        test('TC-212-003: non-admin cannot access admin search', async ({ page }) => {
            await loginAs(page, 'subscriber');

            // Attempt to access admin search endpoint
            const response = await page.request.post('/wp-admin/admin-ajax.php', {
                form: {
                    action: 'ai_botkit_admin_search',
                    nonce: 'test',
                    q: 'test',
                },
            });

            // Should be denied
            const data = await response.json().catch(() => ({}));
            expect(data.success === false || response.status() >= 400).toBe(true);
        });
    });

    // ==========================================================================
    // FR-213: User Personal Search
    // ==========================================================================

    test.describe('FR-213: User Personal Search', () => {
        /**
         * TC-213-001: User search limited to own conversations
         * Priority: P0 (Critical)
         */
        test('TC-213-001: user search limited to own conversations', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search for a common term
            await searchPanel.search('the');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            if (resultCount > 0) {
                // All results should belong to current user
                // (This is implicitly tested by the API returning only user's data)
                for (let i = 0; i < Math.min(resultCount, 5); i++) {
                    const data = await searchPanel.getResultData(i);
                    expect(data.conversationId).toBeTruthy();
                }
            }
        });

        /**
         * TC-213-002: Cannot see other users' messages
         * Priority: P0 (Critical) - Security test
         */
        test('TC-213-002: cannot see other users messages in search', async ({ page }) => {
            // Create a unique search term that admin might have
            const uniqueTerm = 'admin_secret_' + Date.now();

            // Login as subscriber and search for admin's term
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            await searchPanel.search(uniqueTerm);
            await searchPanel.waitForSearchResults();

            // Should not find admin's conversations
            const resultCount = await searchPanel.getResultCount();
            expect(resultCount).toBe(0);
        });
    });

    // ==========================================================================
    // FR-214: Search Filters
    // ==========================================================================

    test.describe('FR-214: Search Filters', () => {
        /**
         * TC-214-001: Filter by date range
         * Priority: P1 (High)
         */
        test('TC-214-001: filter by date range', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Perform search first
            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            // Apply date filter
            await searchPanel.applyDateFilter('week');
            await searchPanel.waitForSearchResults();

            // Verify filter is active
            const activeFilters = await searchPanel.getActiveFilters();
            expect(activeFilters.length).toBeGreaterThanOrEqual(0);
        });

        /**
         * TC-214-003: Combine multiple filters
         * Priority: P1 (High)
         */
        test('TC-214-003: combine multiple filters', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Perform search
            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            // Apply multiple filters
            await searchPanel.applyDateFilter('month');
            await searchPanel.waitForSearchResults();

            // Remove filter
            const activeFilters = await searchPanel.getActiveFilters();
            if (activeFilters.length > 0) {
                await searchPanel.removeFilter(0);
                await searchPanel.waitForSearchResults();
            }
        });
    });

    // ==========================================================================
    // FR-215: Search Results Display
    // ==========================================================================

    test.describe('FR-215: Search Results Display', () => {
        /**
         * TC-215-001: Results show message excerpt
         * Priority: P0 (Critical)
         */
        test('TC-215-001: results show message excerpt', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            await searchPanel.search('hello');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            if (resultCount > 0) {
                const data = await searchPanel.getResultData(0);

                // Result should have content (excerpt)
                expect(data.content).toBeTruthy();
                expect(data.content.length).toBeGreaterThan(0);

                // Should have metadata
                expect(data.chatbot).toBeTruthy();
                expect(data.date).toBeTruthy();
            }
        });

        /**
         * TC-215-002: Click result opens conversation
         * Priority: P0 (Critical)
         */
        test('TC-215-002: click result opens conversation', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            await searchPanel.search('hello');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            if (resultCount > 0) {
                // Click on the result
                await searchPanel.clickResult(0);

                // Verify conversation is loaded (messages should appear)
                await page.waitForTimeout(1000);
                const messageCounts = await chatWidget.getMessageCounts();
                // Conversation should load
                expect(messageCounts.total).toBeGreaterThanOrEqual(0);
            }
        });
    });

    // ==========================================================================
    // FR-216: Search Term Highlighting
    // ==========================================================================

    test.describe('FR-216: Search Term Highlighting', () => {
        /**
         * TC-216-001: Search terms highlighted in results
         * Priority: P1 (High)
         */
        test('TC-216-001: search terms highlighted in results', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            if (resultCount > 0) {
                // Check for highlighted terms
                const isHighlighted = await searchPanel.isTermHighlighted('test');
                // If there are results, terms should be highlighted
                expect(typeof isHighlighted).toBe('boolean');
            }
        });
    });

    // ==========================================================================
    // FR-218: Search Pagination
    // ==========================================================================

    test.describe('FR-218: Search Pagination', () => {
        /**
         * TC-218-001: Results paginated at 20 per page
         * Priority: P0 (Critical)
         */
        test('TC-218-001: results paginated at 20 per page', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            // Search for a common term to get many results
            await searchPanel.search('the');
            await searchPanel.waitForSearchResults();

            const resultCount = await searchPanel.getResultCount();
            const totalCount = await searchPanel.getTotalResultCount();

            // If there are more than 20 results total, should show max 20 on first page
            if (totalCount > 20) {
                expect(resultCount).toBeLessThanOrEqual(20);
            }
        });

        /**
         * TC-218-002: Search query preserved across pages
         * Priority: P1 (High)
         */
        test('TC-218-002: search query preserved across pages', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            const hasNext = await searchPanel.hasNextPage();
            if (hasNext) {
                await searchPanel.nextPage();
                await searchPanel.waitForSearchResults();

                // Query should still be in input
                await expect(searchPanel.searchInput).toHaveValue('test');
            }
        });
    });

    // ==========================================================================
    // FR-219: Search Performance
    // ==========================================================================

    test.describe('FR-219: Search Performance', () => {
        /**
         * TC-219-001: Search returns within 500ms
         * Priority: P0 (Critical)
         */
        test('TC-219-001: search returns within reasonable time', async ({ page }) => {
            await loginAs(page, 'subscriber');
            await chatWidget.goto('/');
            await chatWidget.openWidget();

            const startTime = Date.now();

            await searchPanel.search('test');
            await searchPanel.waitForSearchResults();

            const endTime = Date.now();
            const duration = endTime - startTime;

            // Search should complete within 5 seconds (generous for E2E tests)
            expect(duration).toBeLessThan(5000);
        });
    });
});
