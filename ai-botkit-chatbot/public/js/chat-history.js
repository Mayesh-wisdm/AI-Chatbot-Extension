/**
 * AI BotKit Chat History Module
 *
 * Provides chat history functionality for logged-in users.
 * Handles conversation listing, switching, filtering, and management.
 *
 * @package AI_BotKit
 * @since   2.0.0
 *
 * Implements: FR-201 to FR-209 (Chat History Feature)
 */

(function($) {
    'use strict';

    /**
     * Chat History Manager
     *
     * @class
     */
    const ChatHistoryManager = {
        /**
         * Configuration options
         */
        config: {
            ajaxUrl: '',
            nonce: '',
            i18n: {},
            isLoggedIn: false,
            currentPage: 1,
            perPage: 10,
            totalPages: 1,
            currentFilter: {
                startDate: '',
                endDate: '',
                chatbotId: null,
                isFavorite: null
            }
        },

        /**
         * State tracking
         */
        state: {
            isLoading: false,
            conversations: [],
            currentConversationId: null,
            historyPanelOpen: false
        },

        /**
         * DOM element selectors
         */
        selectors: {
            historyPanel: '.ai-botkit-history-panel',
            historyToggle: '.ai-botkit-history-toggle',
            historyClose: '.ai-botkit-history-close',
            conversationList: '.ai-botkit-conversation-list',
            conversationItem: '.ai-botkit-conversation-item',
            filterForm: '.ai-botkit-history-filter-form',
            filterStartDate: '#ai-botkit-filter-start-date',
            filterEndDate: '#ai-botkit-filter-end-date',
            filterChatbot: '#ai-botkit-filter-chatbot',
            filterFavorite: '#ai-botkit-filter-favorite',
            loadMoreBtn: '.ai-botkit-load-more',
            loadingIndicator: '.ai-botkit-history-loading',
            emptyState: '.ai-botkit-history-empty',
            searchInput: '.ai-botkit-history-search'
        },

        /**
         * Initialize the chat history manager
         *
         * @param {Object} options Configuration options
         */
        init: function(options) {
            // Merge configuration
            this.config = $.extend(true, this.config, options);

            // Only initialize if user is logged in
            if (!this.config.isLoggedIn) {
                return;
            }

            // Bind event handlers
            this.bindEvents();

            // Initial load on first panel open
            this.setupInitialLoad();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            const self = this;

            // Toggle history panel
            $(document).on('click', this.selectors.historyToggle, function(e) {
                e.preventDefault();
                self.toggleHistoryPanel();
            });

            // Close history panel
            $(document).on('click', this.selectors.historyClose, function(e) {
                e.preventDefault();
                self.closeHistoryPanel();
            });

            // Click on conversation item
            $(document).on('click', this.selectors.conversationItem, function(e) {
                if ($(e.target).closest('.ai-botkit-conversation-actions').length) {
                    return; // Let action buttons handle their own events
                }
                e.preventDefault();
                const conversationId = $(this).data('conversation-id');
                self.switchConversation(conversationId);
            });

            // Toggle favorite
            $(document).on('click', '.ai-botkit-favorite-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const conversationId = $(this).closest(self.selectors.conversationItem).data('conversation-id');
                self.toggleFavorite(conversationId, $(this));
            });

            // Delete conversation
            $(document).on('click', '.ai-botkit-delete-conversation-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const conversationId = $(this).closest(self.selectors.conversationItem).data('conversation-id');
                self.confirmDeleteConversation(conversationId);
            });

            // Archive conversation
            $(document).on('click', '.ai-botkit-archive-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const conversationId = $(this).closest(self.selectors.conversationItem).data('conversation-id');
                self.archiveConversation(conversationId);
            });

            // Filter form submission
            $(document).on('submit', this.selectors.filterForm, function(e) {
                e.preventDefault();
                self.applyFilters();
            });

            // Clear filters
            $(document).on('click', '.ai-botkit-clear-filters', function(e) {
                e.preventDefault();
                self.clearFilters();
            });

            // Load more conversations
            $(document).on('click', this.selectors.loadMoreBtn, function(e) {
                e.preventDefault();
                self.loadMoreConversations();
            });

            // Quick filter buttons
            $(document).on('click', '.ai-botkit-quick-filter', function(e) {
                e.preventDefault();
                const filter = $(this).data('filter');
                self.applyQuickFilter(filter);
            });

            // Close panel when clicking outside
            $(document).on('click', function(e) {
                if (self.state.historyPanelOpen &&
                    !$(e.target).closest(self.selectors.historyPanel).length &&
                    !$(e.target).closest(self.selectors.historyToggle).length) {
                    self.closeHistoryPanel();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.state.historyPanelOpen) {
                    self.closeHistoryPanel();
                }
            });
        },

        /**
         * Setup initial load when panel first opens
         */
        setupInitialLoad: function() {
            // Don't auto-load, wait for panel to open
        },

        /**
         * Toggle the history panel
         */
        toggleHistoryPanel: function() {
            if (this.state.historyPanelOpen) {
                this.closeHistoryPanel();
            } else {
                this.openHistoryPanel();
            }
        },

        /**
         * Open the history panel
         */
        openHistoryPanel: function() {
            const $panel = $(this.selectors.historyPanel);

            $panel.addClass('is-open');
            this.state.historyPanelOpen = true;

            // Load conversations if not already loaded
            if (this.state.conversations.length === 0) {
                this.loadConversations();
            }

            // Announce to screen readers
            $panel.attr('aria-hidden', 'false');

            /**
             * Fires when the history panel is opened.
             *
             * @since 2.0.0
             */
            $(document).trigger('ai_botkit_history_panel_opened');
        },

        /**
         * Close the history panel
         */
        closeHistoryPanel: function() {
            const $panel = $(this.selectors.historyPanel);

            $panel.removeClass('is-open');
            this.state.historyPanelOpen = false;

            // Announce to screen readers
            $panel.attr('aria-hidden', 'true');

            /**
             * Fires when the history panel is closed.
             *
             * @since 2.0.0
             */
            $(document).trigger('ai_botkit_history_panel_closed');
        },

        /**
         * Load conversations from server
         *
         * @param {boolean} append Whether to append to existing list
         */
        loadConversations: function(append) {
            const self = this;

            if (this.state.isLoading) {
                return;
            }

            this.state.isLoading = true;
            this.showLoading();

            const data = {
                action: 'ai_botkit_list_conversations',
                nonce: this.config.nonce,
                page: this.config.currentPage,
                per_page: this.config.perPage
            };

            // Add chatbot filter if set
            if (this.config.currentFilter.chatbotId) {
                data.chatbot_id = this.config.currentFilter.chatbotId;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (append) {
                            self.state.conversations = self.state.conversations.concat(response.data.conversations);
                        } else {
                            self.state.conversations = response.data.conversations;
                        }
                        self.config.totalPages = response.data.pages;
                        self.renderConversationList(append);
                    } else {
                        self.showError(response.data.message || self.config.i18n.loadError);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError || 'Failed to load conversations.');
                    console.error('Chat history load error:', error);
                },
                complete: function() {
                    self.state.isLoading = false;
                    self.hideLoading();
                }
            });
        },

        /**
         * Render the conversation list
         *
         * @param {boolean} append Whether to append to existing list
         */
        renderConversationList: function(append) {
            const $list = $(this.selectors.conversationList);
            const $emptyState = $(this.selectors.emptyState);

            if (!append) {
                $list.empty();
            }

            if (this.state.conversations.length === 0) {
                $emptyState.show();
                $(this.selectors.loadMoreBtn).hide();
                return;
            }

            $emptyState.hide();

            const conversationsToRender = append ?
                this.state.conversations.slice(-this.config.perPage) :
                this.state.conversations;

            conversationsToRender.forEach(function(conversation) {
                const $item = this.createConversationItem(conversation);
                $list.append($item);
            }, this);

            // Show/hide load more button
            if (this.config.currentPage < this.config.totalPages) {
                $(this.selectors.loadMoreBtn).show();
            } else {
                $(this.selectors.loadMoreBtn).hide();
            }

            // Mark current conversation as active
            this.markActiveConversation();
        },

        /**
         * Create a conversation list item element
         *
         * @param {Object} conversation Conversation data
         * @return {jQuery} jQuery element
         */
        createConversationItem: function(conversation) {
            const isActive = conversation.id === this.state.currentConversationId;
            const favoriteIcon = conversation.is_favorite ? 'ti-star-filled' : 'ti-star';
            const favoriteClass = conversation.is_favorite ? 'is-favorite' : '';

            const $item = $(`
                <div class="ai-botkit-conversation-item ${isActive ? 'is-active' : ''} ${favoriteClass}"
                     data-conversation-id="${conversation.id}"
                     role="button"
                     tabindex="0"
                     aria-label="${this.escapeHtml(conversation.title)}">
                    <div class="ai-botkit-conversation-content">
                        <div class="ai-botkit-conversation-header">
                            <span class="ai-botkit-conversation-title">${this.escapeHtml(conversation.title)}</span>
                            <span class="ai-botkit-conversation-date">${this.escapeHtml(conversation.formatted_date)}</span>
                        </div>
                        <div class="ai-botkit-conversation-preview">${this.escapeHtml(conversation.preview)}</div>
                        <div class="ai-botkit-conversation-meta">
                            <span class="ai-botkit-conversation-bot">${this.escapeHtml(conversation.chatbot_name)}</span>
                            <span class="ai-botkit-conversation-count">${conversation.message_count} ${this.config.i18n.messages || 'messages'}</span>
                        </div>
                    </div>
                    <div class="ai-botkit-conversation-actions">
                        <button type="button" class="ai-botkit-favorite-btn ${favoriteClass}"
                                title="${this.config.i18n.toggleFavorite || 'Toggle favorite'}"
                                aria-label="${this.config.i18n.toggleFavorite || 'Toggle favorite'}">
                            <i class="ti ${favoriteIcon}"></i>
                        </button>
                        <button type="button" class="ai-botkit-archive-btn"
                                title="${this.config.i18n.archive || 'Archive'}"
                                aria-label="${this.config.i18n.archive || 'Archive'}">
                            <i class="ti ti-archive"></i>
                        </button>
                        <button type="button" class="ai-botkit-delete-conversation-btn"
                                title="${this.config.i18n.delete || 'Delete'}"
                                aria-label="${this.config.i18n.delete || 'Delete'}">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                </div>
            `);

            return $item;
        },

        /**
         * Mark the active conversation in the list
         */
        markActiveConversation: function() {
            $(this.selectors.conversationItem).removeClass('is-active');

            if (this.state.currentConversationId) {
                $(this.selectors.conversationItem +
                  '[data-conversation-id="' + this.state.currentConversationId + '"]')
                    .addClass('is-active');
            }
        },

        /**
         * Switch to a different conversation
         *
         * @param {number} conversationId Conversation ID
         */
        switchConversation: function(conversationId) {
            const self = this;

            if (this.state.isLoading || conversationId === this.state.currentConversationId) {
                return;
            }

            this.state.isLoading = true;
            this.showLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_switch_conversation',
                    nonce: this.config.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        self.state.currentConversationId = conversationId;
                        self.markActiveConversation();
                        self.loadConversationMessages(response.data);
                        self.closeHistoryPanel();

                        /**
                         * Fires when a conversation is switched.
                         *
                         * @since 2.0.0
                         *
                         * @param {number} conversationId The switched conversation ID.
                         * @param {Object} data           The conversation data.
                         */
                        $(document).trigger('ai_botkit_conversation_switched', [conversationId, response.data]);
                    } else {
                        self.showError(response.data.message || self.config.i18n.switchError);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError || 'Failed to load conversation.');
                    console.error('Switch conversation error:', error);
                },
                complete: function() {
                    self.state.isLoading = false;
                    self.hideLoading();
                }
            });
        },

        /**
         * Load conversation messages into the chat interface
         *
         * @param {Object} conversationData Conversation data with messages
         */
        loadConversationMessages: function(conversationData) {
            const $messagesContainer = $('.ai-botkit-chat-messages');

            // Clear current messages
            $messagesContainer.empty();

            // Render each message
            if (conversationData.messages && conversationData.messages.length > 0) {
                conversationData.messages.forEach(function(message) {
                    this.appendMessage(message.role, message.content, $messagesContainer);
                }, this);
            }

            // Update the current conversation ID in the main chat
            if (typeof window.ai_botkit !== 'undefined' && window.ai_botkit.setConversationId) {
                window.ai_botkit.setConversationId(conversationData.session_id);
            }

            // Update chat header if needed
            if (conversationData.chatbot_name) {
                $('.ai-botkit-chat-header h3').text(conversationData.chatbot_name);
            }

            // Scroll to bottom
            $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
        },

        /**
         * Append a message to the chat container
         *
         * @param {string} role    Message role (user/assistant)
         * @param {string} content Message content
         * @param {jQuery} $container Container element
         */
        appendMessage: function(role, content, $container) {
            const $template = $container.closest('.ai-botkit-chat')
                .find('template[id$="-message-template"]');

            if ($template.length) {
                const $message = $($template.html());
                $message.addClass(role);
                $message.find('.ai-botkit-message-text').html(content);
                $container.append($message);
            } else {
                // Fallback if template not found
                const $message = $(`
                    <div class="ai-botkit-message ${role}">
                        <div class="ai-botkit-message-content">
                            <div class="ai-botkit-message-text">${content}</div>
                        </div>
                    </div>
                `);
                $container.append($message);
            }
        },

        /**
         * Toggle favorite status for a conversation
         *
         * @param {number} conversationId Conversation ID
         * @param {jQuery} $button        Button element
         */
        toggleFavorite: function(conversationId, $button) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_toggle_favorite',
                    nonce: this.config.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        const isFavorite = response.data.is_favorite;
                        const $item = $button.closest(self.selectors.conversationItem);
                        const $icon = $button.find('i');

                        $item.toggleClass('is-favorite', isFavorite);
                        $button.toggleClass('is-favorite', isFavorite);
                        $icon.toggleClass('ti-star-filled', isFavorite);
                        $icon.toggleClass('ti-star', !isFavorite);

                        // Update state
                        const conversation = self.state.conversations.find(c => c.id === conversationId);
                        if (conversation) {
                            conversation.is_favorite = isFavorite;
                        }

                        /**
                         * Fires when favorite status is toggled.
                         *
                         * @since 2.0.0
                         */
                        $(document).trigger('ai_botkit_favorite_toggled', [conversationId, isFavorite]);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError);
                    console.error('Toggle favorite error:', error);
                }
            });
        },

        /**
         * Confirm and delete a conversation
         *
         * @param {number} conversationId Conversation ID
         */
        confirmDeleteConversation: function(conversationId) {
            const self = this;

            // Use SweetAlert if available, otherwise use confirm
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: this.config.i18n.deleteTitle || 'Delete Conversation?',
                    text: this.config.i18n.deleteConfirm || 'Are you sure you want to delete this conversation? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d63638',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: this.config.i18n.deleteButton || 'Yes, delete it!',
                    cancelButtonText: this.config.i18n.cancelButton || 'Cancel',
                    customClass: {
                        popup: 'ai-botkit-swal-popup',
                        confirmButton: 'ai-botkit-swal-confirm-danger',
                        cancelButton: 'ai-botkit-swal-cancel'
                    }
                }).then(function(result) {
                    if (result.isConfirmed) {
                        self.deleteConversation(conversationId);
                    }
                });
            } else {
                // Fallback to standard confirm
                if (confirm(this.config.i18n.deleteConfirm || 'Are you sure you want to delete this conversation?')) {
                    this.deleteConversation(conversationId);
                }
            }
        },

        /**
         * Delete a conversation
         *
         * @param {number} conversationId Conversation ID
         */
        deleteConversation: function(conversationId) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_delete_conversation',
                    nonce: this.config.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove from state
                        self.state.conversations = self.state.conversations.filter(
                            c => c.id !== conversationId
                        );

                        // Remove from DOM with animation
                        const $item = $(self.selectors.conversationItem +
                            '[data-conversation-id="' + conversationId + '"]');
                        $item.slideUp(200, function() {
                            $(this).remove();

                            // Check if list is empty
                            if (self.state.conversations.length === 0) {
                                $(self.selectors.emptyState).show();
                            }
                        });

                        // If this was the active conversation, clear the chat
                        if (conversationId === self.state.currentConversationId) {
                            self.state.currentConversationId = null;
                            $(document).trigger('ai_botkit_start_new_conversation');
                        }

                        /**
                         * Fires when a conversation is deleted.
                         *
                         * @since 2.0.0
                         */
                        $(document).trigger('ai_botkit_conversation_deleted', [conversationId]);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError);
                    console.error('Delete conversation error:', error);
                }
            });
        },

        /**
         * Archive a conversation
         *
         * @param {number} conversationId Conversation ID
         */
        archiveConversation: function(conversationId) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_archive_conversation',
                    nonce: this.config.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove from visible list (unless showing archived)
                        self.state.conversations = self.state.conversations.filter(
                            c => c.id !== conversationId
                        );

                        // Remove from DOM with animation
                        const $item = $(self.selectors.conversationItem +
                            '[data-conversation-id="' + conversationId + '"]');
                        $item.slideUp(200, function() {
                            $(this).remove();

                            if (self.state.conversations.length === 0) {
                                $(self.selectors.emptyState).show();
                            }
                        });

                        /**
                         * Fires when a conversation is archived.
                         *
                         * @since 2.0.0
                         */
                        $(document).trigger('ai_botkit_conversation_archived', [conversationId]);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError);
                    console.error('Archive conversation error:', error);
                }
            });
        },

        /**
         * Apply filter criteria
         */
        applyFilters: function() {
            const $form = $(this.selectors.filterForm);

            this.config.currentFilter.startDate = $(this.selectors.filterStartDate).val();
            this.config.currentFilter.endDate = $(this.selectors.filterEndDate).val();
            this.config.currentFilter.chatbotId = $(this.selectors.filterChatbot).val() || null;
            this.config.currentFilter.isFavorite = $(this.selectors.filterFavorite).val();

            if (this.config.currentFilter.isFavorite === '') {
                this.config.currentFilter.isFavorite = null;
            } else {
                this.config.currentFilter.isFavorite = this.config.currentFilter.isFavorite === 'true';
            }

            this.config.currentPage = 1;
            this.filterConversations();
        },

        /**
         * Filter conversations with current criteria
         */
        filterConversations: function() {
            const self = this;

            if (this.state.isLoading) {
                return;
            }

            this.state.isLoading = true;
            this.showLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_filter_history',
                    nonce: this.config.nonce,
                    start_date: this.config.currentFilter.startDate,
                    end_date: this.config.currentFilter.endDate,
                    chatbot_id: this.config.currentFilter.chatbotId,
                    is_favorite: this.config.currentFilter.isFavorite,
                    page: this.config.currentPage,
                    per_page: this.config.perPage
                },
                success: function(response) {
                    if (response.success) {
                        self.state.conversations = response.data.conversations;
                        self.config.totalPages = response.data.pages;
                        self.renderConversationList(false);
                    } else {
                        self.showError(response.data.message || self.config.i18n.filterError);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError(self.config.i18n.networkError || 'Failed to filter conversations.');
                    console.error('Filter conversations error:', error);
                },
                complete: function() {
                    self.state.isLoading = false;
                    self.hideLoading();
                }
            });
        },

        /**
         * Clear all filters
         */
        clearFilters: function() {
            $(this.selectors.filterStartDate).val('');
            $(this.selectors.filterEndDate).val('');
            $(this.selectors.filterChatbot).val('');
            $(this.selectors.filterFavorite).val('');

            this.config.currentFilter = {
                startDate: '',
                endDate: '',
                chatbotId: null,
                isFavorite: null
            };

            this.config.currentPage = 1;
            this.loadConversations();
        },

        /**
         * Apply a quick filter preset
         *
         * @param {string} filter Filter type (today, week, favorites)
         */
        applyQuickFilter: function(filter) {
            const today = new Date();
            let startDate = '';
            let isFavorite = null;

            switch (filter) {
                case 'today':
                    startDate = this.formatDate(today);
                    break;

                case 'week':
                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    startDate = this.formatDate(weekAgo);
                    break;

                case 'favorites':
                    isFavorite = true;
                    break;

                default:
                    break;
            }

            this.config.currentFilter.startDate = startDate;
            this.config.currentFilter.endDate = this.formatDate(today);
            this.config.currentFilter.isFavorite = isFavorite;
            this.config.currentPage = 1;

            // Update filter form inputs
            $(this.selectors.filterStartDate).val(startDate);
            $(this.selectors.filterEndDate).val(this.formatDate(today));
            $(this.selectors.filterFavorite).val(isFavorite ? 'true' : '');

            this.filterConversations();

            // Mark active quick filter
            $('.ai-botkit-quick-filter').removeClass('is-active');
            $('.ai-botkit-quick-filter[data-filter="' + filter + '"]').addClass('is-active');
        },

        /**
         * Load more conversations (pagination)
         */
        loadMoreConversations: function() {
            if (this.config.currentPage >= this.config.totalPages) {
                return;
            }

            this.config.currentPage++;

            if (this.hasActiveFilters()) {
                // Re-run filter with next page
                const self = this;
                this.state.isLoading = true;
                this.showLoading();

                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_botkit_filter_history',
                        nonce: this.config.nonce,
                        start_date: this.config.currentFilter.startDate,
                        end_date: this.config.currentFilter.endDate,
                        chatbot_id: this.config.currentFilter.chatbotId,
                        is_favorite: this.config.currentFilter.isFavorite,
                        page: this.config.currentPage,
                        per_page: this.config.perPage
                    },
                    success: function(response) {
                        if (response.success) {
                            self.state.conversations = self.state.conversations.concat(response.data.conversations);
                            self.renderConversationList(true);
                        }
                    },
                    complete: function() {
                        self.state.isLoading = false;
                        self.hideLoading();
                    }
                });
            } else {
                this.loadConversations(true);
            }
        },

        /**
         * Check if any filters are active
         *
         * @return {boolean}
         */
        hasActiveFilters: function() {
            return this.config.currentFilter.startDate ||
                   this.config.currentFilter.endDate ||
                   this.config.currentFilter.chatbotId ||
                   this.config.currentFilter.isFavorite !== null;
        },

        /**
         * Set the current conversation ID
         *
         * @param {number} conversationId Conversation ID
         */
        setCurrentConversation: function(conversationId) {
            this.state.currentConversationId = conversationId;
            this.markActiveConversation();
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            $(this.selectors.loadingIndicator).show();
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $(this.selectors.loadingIndicator).hide();
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: this.config.i18n.errorTitle || 'Error',
                    text: message,
                    customClass: {
                        popup: 'ai-botkit-swal-popup'
                    }
                });
            } else {
                alert(message);
            }
        },

        /**
         * Escape HTML for safe rendering
         *
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            if (!text) return '';

            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Format date as YYYY-MM-DD
         *
         * @param {Date} date Date object
         * @return {string} Formatted date
         */
        formatDate: function(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        /**
         * Refresh the conversation list
         */
        refresh: function() {
            this.config.currentPage = 1;
            this.state.conversations = [];
            this.loadConversations();
        }
    };

    // Expose to global scope for integration
    window.AI_BotKit_ChatHistory = ChatHistoryManager;

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize if config is available
        if (typeof ai_botkitChat !== 'undefined') {
            ChatHistoryManager.init({
                ajaxUrl: ai_botkitChat.ajaxUrl,
                nonce: ai_botkitChat.nonce,
                isLoggedIn: ai_botkitChat.isLoggedIn || false,
                i18n: ai_botkitChat.i18n || {}
            });
        }
    });

})(jQuery);
