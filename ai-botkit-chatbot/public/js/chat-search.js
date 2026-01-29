/**
 * AI BotKit Chat Search Module
 *
 * Handles search functionality for chat history including:
 * - Debounced search input
 * - Search results display with highlighting
 * - Filter controls (date range, chatbot, role)
 * - Pagination
 * - Navigation to search results
 *
 * @package AI_BotKit
 * @since   2.0.0
 *
 * Implements: FR-210 to FR-219 (Search Functionality Feature)
 */

(function(window, document) {
    'use strict';

    /**
     * Search Handler Class
     *
     * Manages the search UI and interactions.
     */
    class AIBotKitSearch {
        /**
         * Constructor
         *
         * @param {Object} options Configuration options.
         */
        constructor(options = {}) {
            this.options = {
                container: '.ai-botkit-search-container',
                inputSelector: '.ai-botkit-search-input',
                resultsSelector: '.ai-botkit-search-results',
                filterSelector: '.ai-botkit-search-filters',
                debounceDelay: 300,
                minQueryLength: 2,
                perPage: 20,
                ajaxAction: 'ai_botkit_search_messages',
                suggestionsAction: 'ai_botkit_search_suggestions',
                nonce: '',
                ajaxUrl: '',
                isAdmin: false,
                ...options
            };

            this.currentPage = 1;
            this.currentQuery = '';
            this.currentFilters = {};
            this.isLoading = false;
            this.debounceTimer = null;
            this.searchAbortController = null;

            this.init();
        }

        /**
         * Initialize the search module.
         */
        init() {
            this.container = document.querySelector(this.options.container);
            if (!this.container) {
                return;
            }

            this.setupElements();
            this.bindEvents();
            this.setupAccessibility();
        }

        /**
         * Setup DOM element references.
         */
        setupElements() {
            this.inputElement = this.container.querySelector(this.options.inputSelector);
            this.resultsElement = this.container.querySelector(this.options.resultsSelector);
            this.filterElement = this.container.querySelector(this.options.filterSelector);
            this.suggestionsElement = this.container.querySelector('.ai-botkit-search-suggestions');
            this.loadingElement = this.container.querySelector('.ai-botkit-search-loading');
            this.clearButton = this.container.querySelector('.ai-botkit-search-clear');
            this.paginationElement = this.container.querySelector('.ai-botkit-search-pagination');
        }

        /**
         * Bind event listeners.
         */
        bindEvents() {
            // Search input events.
            if (this.inputElement) {
                this.inputElement.addEventListener('input', this.handleInput.bind(this));
                this.inputElement.addEventListener('keydown', this.handleKeydown.bind(this));
                this.inputElement.addEventListener('focus', this.handleFocus.bind(this));
                this.inputElement.addEventListener('blur', this.handleBlur.bind(this));
            }

            // Clear button.
            if (this.clearButton) {
                this.clearButton.addEventListener('click', this.handleClear.bind(this));
            }

            // Filter changes.
            if (this.filterElement) {
                this.filterElement.addEventListener('change', this.handleFilterChange.bind(this));
            }

            // Results click handling.
            if (this.resultsElement) {
                this.resultsElement.addEventListener('click', this.handleResultClick.bind(this));
            }

            // Pagination.
            if (this.paginationElement) {
                this.paginationElement.addEventListener('click', this.handlePaginationClick.bind(this));
            }

            // Close suggestions on outside click.
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.hideSuggestions();
                }
            });
        }

        /**
         * Setup accessibility attributes.
         */
        setupAccessibility() {
            if (this.inputElement) {
                this.inputElement.setAttribute('role', 'searchbox');
                this.inputElement.setAttribute('aria-label', this.getTranslation('searchLabel', 'Search conversations'));
                this.inputElement.setAttribute('aria-autocomplete', 'list');
                this.inputElement.setAttribute('aria-controls', 'ai-botkit-search-suggestions');
            }

            if (this.resultsElement) {
                this.resultsElement.setAttribute('role', 'region');
                this.resultsElement.setAttribute('aria-live', 'polite');
                this.resultsElement.setAttribute('aria-label', this.getTranslation('resultsLabel', 'Search results'));
            }
        }

        /**
         * Handle input event with debouncing.
         *
         * @param {Event} event Input event.
         */
        handleInput(event) {
            const query = event.target.value.trim();

            // Clear existing timer.
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            // Update clear button visibility.
            this.toggleClearButton(query.length > 0);

            // Check minimum length.
            if (query.length < this.options.minQueryLength) {
                this.hideSuggestions();
                if (query.length === 0) {
                    this.clearResults();
                }
                return;
            }

            // Debounce the search.
            this.debounceTimer = setTimeout(() => {
                this.fetchSuggestions(query);
            }, this.options.debounceDelay);
        }

        /**
         * Handle keydown event.
         *
         * @param {KeyboardEvent} event Keydown event.
         */
        handleKeydown(event) {
            switch (event.key) {
                case 'Enter':
                    event.preventDefault();
                    this.hideSuggestions();
                    this.executeSearch();
                    break;
                case 'Escape':
                    this.hideSuggestions();
                    break;
                case 'ArrowDown':
                    event.preventDefault();
                    this.navigateSuggestions(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.navigateSuggestions(-1);
                    break;
            }
        }

        /**
         * Handle input focus.
         */
        handleFocus() {
            const query = this.inputElement.value.trim();
            if (query.length >= this.options.minQueryLength) {
                this.fetchSuggestions(query);
            }
        }

        /**
         * Handle input blur.
         */
        handleBlur() {
            // Delay to allow suggestion click.
            setTimeout(() => {
                this.hideSuggestions();
            }, 200);
        }

        /**
         * Handle clear button click.
         */
        handleClear() {
            this.inputElement.value = '';
            this.currentQuery = '';
            this.currentPage = 1;
            this.toggleClearButton(false);
            this.clearResults();
            this.hideSuggestions();
            this.inputElement.focus();

            // Trigger event for other modules.
            this.dispatchEvent('searchCleared');
        }

        /**
         * Handle filter change.
         *
         * @param {Event} event Change event.
         */
        handleFilterChange(event) {
            const target = event.target;
            const filterName = target.dataset.filter;
            const filterValue = target.value;

            if (filterName) {
                if (filterValue) {
                    this.currentFilters[filterName] = filterValue;
                } else {
                    delete this.currentFilters[filterName];
                }
            }

            // Reset to first page and re-search.
            this.currentPage = 1;
            if (this.currentQuery) {
                this.executeSearch();
            }

            // Update active filter display.
            this.updateActiveFilters();
        }

        /**
         * Handle result item click.
         *
         * @param {Event} event Click event.
         */
        handleResultClick(event) {
            const resultItem = event.target.closest('.ai-botkit-search-result-item');
            if (!resultItem) {
                return;
            }

            const conversationId = resultItem.dataset.conversationId;
            const messageId = resultItem.dataset.messageId;

            if (conversationId) {
                this.navigateToResult(conversationId, messageId);
            }
        }

        /**
         * Handle pagination click.
         *
         * @param {Event} event Click event.
         */
        handlePaginationClick(event) {
            const pageButton = event.target.closest('[data-page]');
            if (!pageButton || pageButton.disabled) {
                return;
            }

            event.preventDefault();
            const page = parseInt(pageButton.dataset.page, 10);
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.executeSearch();
            }
        }

        /**
         * Fetch search suggestions.
         *
         * @param {string} query Partial query.
         */
        async fetchSuggestions(query) {
            if (!this.suggestionsElement) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', this.options.suggestionsAction);
                formData.append('nonce', this.options.nonce);
                formData.append('q', query);

                const response = await fetch(this.options.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success && data.data.suggestions.length > 0) {
                    this.showSuggestions(data.data.suggestions);
                } else {
                    this.hideSuggestions();
                }
            } catch (error) {
                console.error('Failed to fetch suggestions:', error);
                this.hideSuggestions();
            }
        }

        /**
         * Execute the search.
         */
        async executeSearch() {
            const query = this.inputElement.value.trim();

            if (query.length < this.options.minQueryLength) {
                return;
            }

            // Abort previous request.
            if (this.searchAbortController) {
                this.searchAbortController.abort();
            }
            this.searchAbortController = new AbortController();

            this.currentQuery = query;
            this.setLoading(true);

            try {
                const formData = new FormData();
                formData.append('action', this.options.ajaxAction);
                formData.append('nonce', this.options.nonce);
                formData.append('q', query);
                formData.append('page', this.currentPage);
                formData.append('per_page', this.options.perPage);

                // Add filters.
                Object.keys(this.currentFilters).forEach(key => {
                    formData.append(`filters[${key}]`, this.currentFilters[key]);
                });

                const response = await fetch(this.options.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    signal: this.searchAbortController.signal
                });

                const data = await response.json();

                if (data.success) {
                    this.renderResults(data.data);
                    this.dispatchEvent('searchCompleted', data.data);
                } else {
                    this.renderError(data.data.message || this.getTranslation('searchError', 'Search failed'));
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Search failed:', error);
                    this.renderError(this.getTranslation('searchError', 'Search failed'));
                }
            } finally {
                this.setLoading(false);
            }
        }

        /**
         * Render search results.
         *
         * @param {Object} data Search response data.
         */
        renderResults(data) {
            if (!this.resultsElement) {
                return;
            }

            const { results, total, pages, current_page, search_time, query } = data;

            // Clear previous results.
            this.resultsElement.innerHTML = '';

            // Build results header.
            const header = document.createElement('div');
            header.className = 'ai-botkit-search-header';
            header.innerHTML = `
                <span class="ai-botkit-search-count">
                    ${this.getTranslation('resultsFound', 'Found {count} results').replace('{count}', total)}
                </span>
                <span class="ai-botkit-search-time">
                    ${this.getTranslation('searchTime', 'in {time}s').replace('{time}', search_time)}
                </span>
            `;
            this.resultsElement.appendChild(header);

            // Handle no results.
            if (results.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'ai-botkit-search-no-results';
                noResults.innerHTML = `
                    <div class="ai-botkit-search-no-results-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <p>${this.getTranslation('noResults', 'No results found for "{query}"').replace('{query}', this.escapeHtml(query))}</p>
                    <p class="ai-botkit-search-tips">
                        ${this.getTranslation('searchTips', 'Try using different keywords or check your spelling.')}
                    </p>
                `;
                this.resultsElement.appendChild(noResults);
                return;
            }

            // Build results list.
            const resultsList = document.createElement('div');
            resultsList.className = 'ai-botkit-search-results-list';
            resultsList.setAttribute('role', 'list');

            results.forEach((result, index) => {
                const item = this.createResultItem(result, index);
                resultsList.appendChild(item);
            });

            this.resultsElement.appendChild(resultsList);

            // Build pagination.
            if (pages > 1) {
                this.renderPagination(current_page, pages, total);
            }

            // Announce to screen readers.
            this.announceResults(total, query);
        }

        /**
         * Create a single result item element.
         *
         * @param {Object} result Result data.
         * @param {number} index Result index.
         * @returns {HTMLElement} Result item element.
         */
        createResultItem(result, index) {
            const item = document.createElement('div');
            item.className = 'ai-botkit-search-result-item';
            item.setAttribute('role', 'listitem');
            item.setAttribute('tabindex', '0');
            item.dataset.conversationId = result.conversation_id;
            item.dataset.messageId = result.message_id;

            const roleIcon = result.role === 'user' ? 'user' : 'bot';
            const roleLabel = result.role === 'user'
                ? this.getTranslation('userMessage', 'User')
                : this.getTranslation('botMessage', 'Bot');

            item.innerHTML = `
                <div class="ai-botkit-search-result-header">
                    <span class="ai-botkit-search-result-role ai-botkit-search-result-role-${result.role}">
                        ${roleLabel}
                    </span>
                    <span class="ai-botkit-search-result-chatbot">${this.escapeHtml(result.chatbot_name)}</span>
                    <span class="ai-botkit-search-result-date">${result.formatted_date}</span>
                    ${result.user_name ? `<span class="ai-botkit-search-result-user">${this.escapeHtml(result.user_name)}</span>` : ''}
                </div>
                <div class="ai-botkit-search-result-content">
                    ${result.content_highlighted}
                </div>
                <div class="ai-botkit-search-result-footer">
                    <span class="ai-botkit-search-result-relevance" title="${this.getTranslation('relevanceScore', 'Relevance score')}">
                        ${this.formatRelevance(result.relevance_score)}
                    </span>
                    <button class="ai-botkit-search-view-btn" type="button">
                        ${this.getTranslation('viewConversation', 'View Conversation')}
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            `;

            // Add keyboard navigation.
            item.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.navigateToResult(result.conversation_id, result.message_id);
                }
            });

            return item;
        }

        /**
         * Render pagination controls.
         *
         * @param {number} currentPage Current page.
         * @param {number} totalPages Total pages.
         * @param {number} totalResults Total results.
         */
        renderPagination(currentPage, totalPages, totalResults) {
            if (!this.paginationElement) {
                // Create pagination element if not exists.
                this.paginationElement = document.createElement('div');
                this.paginationElement.className = 'ai-botkit-search-pagination';
                this.resultsElement.appendChild(this.paginationElement);
            }

            this.paginationElement.innerHTML = '';
            this.paginationElement.setAttribute('role', 'navigation');
            this.paginationElement.setAttribute('aria-label', this.getTranslation('pagination', 'Search results pagination'));

            // Previous button.
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'ai-botkit-search-page-btn ai-botkit-search-page-prev';
            prevBtn.dataset.page = currentPage - 1;
            prevBtn.disabled = currentPage === 1;
            prevBtn.innerHTML = '&laquo; ' + this.getTranslation('previous', 'Previous');
            this.paginationElement.appendChild(prevBtn);

            // Page info.
            const pageInfo = document.createElement('span');
            pageInfo.className = 'ai-botkit-search-page-info';
            pageInfo.textContent = this.getTranslation('pageInfo', 'Page {current} of {total}')
                .replace('{current}', currentPage)
                .replace('{total}', totalPages);
            this.paginationElement.appendChild(pageInfo);

            // Next button.
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'ai-botkit-search-page-btn ai-botkit-search-page-next';
            nextBtn.dataset.page = currentPage + 1;
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.innerHTML = this.getTranslation('next', 'Next') + ' &raquo;';
            this.paginationElement.appendChild(nextBtn);

            this.resultsElement.appendChild(this.paginationElement);
        }

        /**
         * Render error message.
         *
         * @param {string} message Error message.
         */
        renderError(message) {
            if (!this.resultsElement) {
                return;
            }

            this.resultsElement.innerHTML = `
                <div class="ai-botkit-search-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }

        /**
         * Clear search results.
         */
        clearResults() {
            if (this.resultsElement) {
                this.resultsElement.innerHTML = '';
            }
            if (this.paginationElement) {
                this.paginationElement.innerHTML = '';
            }
        }

        /**
         * Show suggestions dropdown.
         *
         * @param {Array} suggestions Suggestion strings.
         */
        showSuggestions(suggestions) {
            if (!this.suggestionsElement) {
                return;
            }

            this.suggestionsElement.innerHTML = '';
            this.suggestionsElement.classList.add('active');

            suggestions.forEach((suggestion, index) => {
                const item = document.createElement('div');
                item.className = 'ai-botkit-search-suggestion-item';
                item.setAttribute('role', 'option');
                item.setAttribute('tabindex', '-1');
                item.dataset.index = index;
                item.textContent = suggestion;

                item.addEventListener('click', () => {
                    this.inputElement.value = suggestion;
                    this.hideSuggestions();
                    this.executeSearch();
                });

                this.suggestionsElement.appendChild(item);
            });
        }

        /**
         * Hide suggestions dropdown.
         */
        hideSuggestions() {
            if (this.suggestionsElement) {
                this.suggestionsElement.classList.remove('active');
                this.suggestionsElement.innerHTML = '';
            }
        }

        /**
         * Navigate through suggestions.
         *
         * @param {number} direction 1 for down, -1 for up.
         */
        navigateSuggestions(direction) {
            if (!this.suggestionsElement || !this.suggestionsElement.classList.contains('active')) {
                return;
            }

            const items = this.suggestionsElement.querySelectorAll('.ai-botkit-search-suggestion-item');
            if (items.length === 0) {
                return;
            }

            const currentActive = this.suggestionsElement.querySelector('.ai-botkit-search-suggestion-item.active');
            let nextIndex = 0;

            if (currentActive) {
                currentActive.classList.remove('active');
                const currentIndex = parseInt(currentActive.dataset.index, 10);
                nextIndex = currentIndex + direction;

                if (nextIndex < 0) {
                    nextIndex = items.length - 1;
                } else if (nextIndex >= items.length) {
                    nextIndex = 0;
                }
            } else if (direction === -1) {
                nextIndex = items.length - 1;
            }

            items[nextIndex].classList.add('active');
            this.inputElement.value = items[nextIndex].textContent;
        }

        /**
         * Navigate to a search result.
         *
         * @param {number} conversationId Conversation ID.
         * @param {number} messageId Message ID.
         */
        navigateToResult(conversationId, messageId) {
            // Dispatch event for chat module to handle navigation.
            this.dispatchEvent('navigateToResult', {
                conversationId: parseInt(conversationId, 10),
                messageId: parseInt(messageId, 10),
                query: this.currentQuery
            });

            // Close search panel if in modal mode.
            const panel = this.container.closest('.ai-botkit-search-panel');
            if (panel && panel.classList.contains('modal')) {
                panel.classList.remove('open');
            }
        }

        /**
         * Update active filters display.
         */
        updateActiveFilters() {
            const activeFiltersContainer = this.container.querySelector('.ai-botkit-search-active-filters');
            if (!activeFiltersContainer) {
                return;
            }

            activeFiltersContainer.innerHTML = '';

            Object.keys(this.currentFilters).forEach(key => {
                const value = this.currentFilters[key];
                const chip = document.createElement('span');
                chip.className = 'ai-botkit-search-filter-chip';
                chip.innerHTML = `
                    ${this.escapeHtml(key)}: ${this.escapeHtml(value)}
                    <button type="button" class="ai-botkit-search-filter-remove" data-filter="${key}">
                        &times;
                    </button>
                `;

                chip.querySelector('.ai-botkit-search-filter-remove').addEventListener('click', () => {
                    delete this.currentFilters[key];
                    // Reset the filter control.
                    const control = this.filterElement.querySelector(`[data-filter="${key}"]`);
                    if (control) {
                        control.value = '';
                    }
                    this.updateActiveFilters();
                    if (this.currentQuery) {
                        this.executeSearch();
                    }
                });

                activeFiltersContainer.appendChild(chip);
            });
        }

        /**
         * Toggle clear button visibility.
         *
         * @param {boolean} show Whether to show the button.
         */
        toggleClearButton(show) {
            if (this.clearButton) {
                this.clearButton.style.display = show ? 'block' : 'none';
            }
        }

        /**
         * Set loading state.
         *
         * @param {boolean} loading Whether loading is in progress.
         */
        setLoading(loading) {
            this.isLoading = loading;
            this.container.classList.toggle('loading', loading);

            if (this.loadingElement) {
                this.loadingElement.style.display = loading ? 'block' : 'none';
            }

            if (this.inputElement) {
                this.inputElement.disabled = loading;
            }
        }

        /**
         * Format relevance score for display.
         *
         * @param {number} score Relevance score.
         * @returns {string} Formatted score.
         */
        formatRelevance(score) {
            const percentage = Math.min(100, Math.round(score * 10));
            return `${percentage}%`;
        }

        /**
         * Announce results to screen readers.
         *
         * @param {number} count Result count.
         * @param {string} query Search query.
         */
        announceResults(count, query) {
            const announcement = count === 0
                ? this.getTranslation('noResultsAnnouncement', 'No results found for {query}').replace('{query}', query)
                : this.getTranslation('resultsAnnouncement', 'Found {count} results for {query}')
                    .replace('{count}', count)
                    .replace('{query}', query);

            // Create or update live region.
            let liveRegion = document.getElementById('ai-botkit-search-live-region');
            if (!liveRegion) {
                liveRegion = document.createElement('div');
                liveRegion.id = 'ai-botkit-search-live-region';
                liveRegion.className = 'screen-reader-text';
                liveRegion.setAttribute('aria-live', 'polite');
                document.body.appendChild(liveRegion);
            }

            liveRegion.textContent = announcement;
        }

        /**
         * Dispatch custom event.
         *
         * @param {string} name Event name.
         * @param {Object} detail Event detail data.
         */
        dispatchEvent(name, detail = {}) {
            const event = new CustomEvent(`aiBotKitSearch:${name}`, {
                bubbles: true,
                detail: detail
            });
            this.container.dispatchEvent(event);
        }

        /**
         * Get translation string.
         *
         * @param {string} key Translation key.
         * @param {string} fallback Fallback string.
         * @returns {string} Translated string.
         */
        getTranslation(key, fallback) {
            if (window.aiBotKitSearchL10n && window.aiBotKitSearchL10n[key]) {
                return window.aiBotKitSearchL10n[key];
            }
            return fallback;
        }

        /**
         * Escape HTML entities.
         *
         * @param {string} text Text to escape.
         * @returns {string} Escaped text.
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Destroy the search instance.
         */
        destroy() {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            if (this.searchAbortController) {
                this.searchAbortController.abort();
            }
            // Additional cleanup as needed.
        }
    }

    // Export to global scope.
    window.AIBotKitSearch = AIBotKitSearch;

    // Auto-initialize on DOM ready.
    document.addEventListener('DOMContentLoaded', function() {
        // Check if configuration is available.
        if (window.aiBotKitSearchConfig) {
            window.aiBotKitSearchInstance = new AIBotKitSearch(window.aiBotKitSearchConfig);
        }
    });

})(window, document);
