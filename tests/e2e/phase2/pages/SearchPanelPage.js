/**
 * Search Panel Page Object
 *
 * Page object for interacting with the AI BotKit search functionality.
 * Implements selectors and methods based on chat-search.js implementation.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

const { expect } = require('@playwright/test');

class SearchPanelPage {
    /**
     * Constructor
     *
     * @param {Page} page Playwright page object
     */
    constructor(page) {
        this.page = page;

        // Search container selectors
        this.searchContainer = page.locator('.ai-botkit-search-container');
        this.searchInput = page.locator('.ai-botkit-search-input');
        this.searchResults = page.locator('.ai-botkit-search-results');
        this.searchFilters = page.locator('.ai-botkit-search-filters');

        // Results selectors
        this.resultsList = page.locator('.ai-botkit-search-results-list');
        this.resultItems = page.locator('.ai-botkit-search-result-item');
        this.resultCount = page.locator('.ai-botkit-search-count');
        this.searchTime = page.locator('.ai-botkit-search-time');
        this.noResultsMessage = page.locator('.ai-botkit-search-no-results');

        // Result item details
        this.resultHeader = page.locator('.ai-botkit-search-result-header');
        this.resultContent = page.locator('.ai-botkit-search-result-content');
        this.resultRole = page.locator('.ai-botkit-search-result-role');
        this.resultDate = page.locator('.ai-botkit-search-result-date');
        this.resultRelevance = page.locator('.ai-botkit-search-result-relevance');
        this.viewConversationButton = page.locator('.ai-botkit-search-view-btn');

        // Suggestions
        this.suggestionsDropdown = page.locator('.ai-botkit-search-suggestions');
        this.suggestionItems = page.locator('.ai-botkit-search-suggestion-item');

        // Controls
        this.clearButton = page.locator('.ai-botkit-search-clear');
        this.loadingIndicator = page.locator('.ai-botkit-search-loading');

        // Pagination
        this.pagination = page.locator('.ai-botkit-search-pagination');
        this.prevPageButton = page.locator('.ai-botkit-search-page-prev');
        this.nextPageButton = page.locator('.ai-botkit-search-page-next');
        this.pageInfo = page.locator('.ai-botkit-search-page-info');

        // Filters
        this.dateFilter = page.locator('[data-filter="date"]');
        this.chatbotFilter = page.locator('[data-filter="chatbot"]');
        this.roleFilter = page.locator('[data-filter="role"]');
        this.activeFiltersContainer = page.locator('.ai-botkit-search-active-filters');
        this.filterChips = page.locator('.ai-botkit-search-filter-chip');

        // Error display
        this.errorMessage = page.locator('.ai-botkit-search-error');
    }

    /**
     * Perform a search
     *
     * @param {string} query Search query
     */
    async search(query) {
        await expect(this.searchInput).toBeVisible();
        await this.searchInput.fill(query);
        await this.page.keyboard.press('Enter');
        await this.waitForSearchResults();
    }

    /**
     * Type in search input (for testing debounce/suggestions)
     *
     * @param {string} query Search query
     */
    async typeSearch(query) {
        await this.searchInput.fill(query);
    }

    /**
     * Wait for search results to load
     */
    async waitForSearchResults() {
        // Wait for loading to start and finish
        await expect(this.loadingIndicator).toBeVisible({ timeout: 2000 }).catch(() => {
            // Loading may be too fast to see
        });
        await expect(this.loadingIndicator).not.toBeVisible({ timeout: 15000 });
    }

    /**
     * Get the number of search results
     *
     * @returns {Promise<number>}
     */
    async getResultCount() {
        await this.waitForSearchResults();

        // Check if no results message is shown
        if (await this.noResultsMessage.isVisible().catch(() => false)) {
            return 0;
        }

        return await this.resultItems.count();
    }

    /**
     * Get the total results count from the header
     *
     * @returns {Promise<number>}
     */
    async getTotalResultCount() {
        const countText = await this.resultCount.textContent();
        const match = countText.match(/(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    /**
     * Get search time
     *
     * @returns {Promise<string>}
     */
    async getSearchTime() {
        return await this.searchTime.textContent();
    }

    /**
     * Get result data at index
     *
     * @param {number} index Zero-based index
     * @returns {Promise<Object>}
     */
    async getResultData(index) {
        const result = this.resultItems.nth(index);
        await expect(result).toBeVisible();

        const conversationId = await result.getAttribute('data-conversation-id');
        const messageId = await result.getAttribute('data-message-id');
        const role = await result.locator('.ai-botkit-search-result-role').textContent();
        const content = await result.locator('.ai-botkit-search-result-content').innerHTML();
        const date = await result.locator('.ai-botkit-search-result-date').textContent();
        const chatbot = await result.locator('.ai-botkit-search-result-chatbot').textContent();

        return { conversationId, messageId, role, content, date, chatbot };
    }

    /**
     * Click on a search result to view the conversation
     *
     * @param {number} index Zero-based index
     */
    async clickResult(index) {
        const result = this.resultItems.nth(index);
        await result.click();
    }

    /**
     * Click view conversation button on a result
     *
     * @param {number} index Zero-based index
     */
    async viewConversation(index) {
        const result = this.resultItems.nth(index);
        const viewButton = result.locator('.ai-botkit-search-view-btn');
        await viewButton.click();
    }

    /**
     * Clear the search
     */
    async clearSearch() {
        await this.clearButton.click();
        await expect(this.searchInput).toHaveValue('');
        await expect(this.searchResults).toBeEmpty().catch(() => {
            // Results may still be visible but empty
        });
    }

    /**
     * Check if suggestions are visible
     *
     * @returns {Promise<boolean>}
     */
    async areSuggestionsVisible() {
        return await this.suggestionsDropdown.evaluate(
            el => el.classList.contains('active')
        ).catch(() => false);
    }

    /**
     * Get suggestion items
     *
     * @returns {Promise<string[]>}
     */
    async getSuggestions() {
        if (!await this.areSuggestionsVisible()) {
            return [];
        }

        const count = await this.suggestionItems.count();
        const suggestions = [];
        for (let i = 0; i < count; i++) {
            suggestions.push(await this.suggestionItems.nth(i).textContent());
        }
        return suggestions;
    }

    /**
     * Select a suggestion
     *
     * @param {number} index Zero-based index
     */
    async selectSuggestion(index) {
        const suggestion = this.suggestionItems.nth(index);
        await suggestion.click();
        await this.waitForSearchResults();
    }

    /**
     * Navigate suggestions with keyboard
     *
     * @param {string} direction 'down' or 'up'
     */
    async navigateSuggestionsWithKeyboard(direction) {
        if (direction === 'down') {
            await this.page.keyboard.press('ArrowDown');
        } else {
            await this.page.keyboard.press('ArrowUp');
        }
    }

    /**
     * Apply date filter
     *
     * @param {string} value Date filter value (e.g., 'today', 'week', 'month')
     */
    async applyDateFilter(value) {
        await this.dateFilter.selectOption(value);
        await this.waitForSearchResults();
    }

    /**
     * Apply chatbot filter
     *
     * @param {string} chatbotId Chatbot ID
     */
    async applyChatbotFilter(chatbotId) {
        await this.chatbotFilter.selectOption(chatbotId);
        await this.waitForSearchResults();
    }

    /**
     * Apply role filter (user/bot messages)
     *
     * @param {string} role 'user' or 'assistant'
     */
    async applyRoleFilter(role) {
        await this.roleFilter.selectOption(role);
        await this.waitForSearchResults();
    }

    /**
     * Get active filter chips
     *
     * @returns {Promise<string[]>}
     */
    async getActiveFilters() {
        const count = await this.filterChips.count();
        const filters = [];
        for (let i = 0; i < count; i++) {
            filters.push(await this.filterChips.nth(i).textContent());
        }
        return filters;
    }

    /**
     * Remove a filter by clicking its chip
     *
     * @param {number} index Zero-based index
     */
    async removeFilter(index) {
        const removeButton = this.filterChips.nth(index).locator('.ai-botkit-search-filter-remove');
        await removeButton.click();
        await this.waitForSearchResults();
    }

    /**
     * Go to next page of results
     */
    async nextPage() {
        await expect(this.nextPageButton).toBeEnabled();
        await this.nextPageButton.click();
        await this.waitForSearchResults();
    }

    /**
     * Go to previous page of results
     */
    async prevPage() {
        await expect(this.prevPageButton).toBeEnabled();
        await this.prevPageButton.click();
        await this.waitForSearchResults();
    }

    /**
     * Check if there are more pages
     *
     * @returns {Promise<boolean>}
     */
    async hasNextPage() {
        return await this.nextPageButton.isEnabled();
    }

    /**
     * Check if there are previous pages
     *
     * @returns {Promise<boolean>}
     */
    async hasPrevPage() {
        return await this.prevPageButton.isEnabled();
    }

    /**
     * Get current page info
     *
     * @returns {Promise<{current: number, total: number}>}
     */
    async getPageInfo() {
        const text = await this.pageInfo.textContent();
        const match = text.match(/Page\s+(\d+)\s+of\s+(\d+)/i);
        if (match) {
            return {
                current: parseInt(match[1], 10),
                total: parseInt(match[2], 10),
            };
        }
        return { current: 1, total: 1 };
    }

    /**
     * Verify search term is highlighted in results
     *
     * @param {string} term Search term to check for highlighting
     * @returns {Promise<boolean>}
     */
    async isTermHighlighted(term) {
        const highlightedMarks = this.searchResults.locator('mark');
        const count = await highlightedMarks.count();

        for (let i = 0; i < count; i++) {
            const text = await highlightedMarks.nth(i).textContent();
            if (text.toLowerCase().includes(term.toLowerCase())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if no results message is displayed
     *
     * @returns {Promise<boolean>}
     */
    async isNoResultsMessageVisible() {
        return await this.noResultsMessage.isVisible();
    }

    /**
     * Get no results message text
     *
     * @returns {Promise<string>}
     */
    async getNoResultsMessage() {
        await expect(this.noResultsMessage).toBeVisible();
        return await this.noResultsMessage.textContent();
    }

    /**
     * Check if error message is displayed
     *
     * @returns {Promise<boolean>}
     */
    async isErrorMessageVisible() {
        return await this.errorMessage.isVisible();
    }
}

module.exports = SearchPanelPage;
