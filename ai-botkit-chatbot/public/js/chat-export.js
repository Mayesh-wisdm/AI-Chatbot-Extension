/**
 * Chat Export Module
 *
 * Handles PDF export functionality for chat conversations.
 * Part of Phase 2: Chat Transcripts Export (FR-240 to FR-249).
 *
 * @package AI_BotKit
 * @since   2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Chat Export Handler
     */
    window.AIBotKitExport = {
        /**
         * Configuration
         */
        config: {
            ajaxUrl: typeof ai_botkitChat !== 'undefined' ? ai_botkitChat.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : ''),
            nonce: typeof ai_botkitChat !== 'undefined' ? ai_botkitChat.nonce : '',
            isAdmin: typeof ai_botkitChat === 'undefined',
        },

        /**
         * Initialize the export module
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Export button in chat history panel (public)
            $(document).on('click', '.ai-botkit-export-pdf-btn', this.handleExportClick.bind(this));

            // Download transcript link in chat panel
            $(document).on('click', '.ai-botkit-download-transcript', this.handleExportClick.bind(this));
        },

        /**
         * Handle export button click
         *
         * @param {Event} e Click event
         */
        handleExportClick: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const conversationId = $btn.data('conversation-id');

            if (!conversationId) {
                this.showError('No conversation selected.');
                return;
            }

            this.exportToPdf(conversationId, $btn);
        },

        /**
         * Export conversation to PDF
         *
         * @param {number} conversationId Conversation ID
         * @param {jQuery} $btn Export button element
         */
        exportToPdf: function (conversationId, $btn) {
            const self = this;

            // Show loading state
            const originalText = $btn.text();
            const originalHtml = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="ai-botkit-spinner"></span> Exporting...');

            // Get export options (can be customized via data attributes)
            const options = {
                action: this.config.isAdmin ? 'ai_botkit_export_pdf' : 'ai_botkit_export_my_pdf',
                nonce: this.config.nonce,
                conversation_id: conversationId,
                include_metadata: $btn.data('include-metadata') !== false,
                include_branding: $btn.data('include-branding') !== false,
                paper_size: $btn.data('paper-size') || 'a4'
            };

            // For public (non-admin), we need to trigger a download differently
            // since stream_pdf sends a file directly
            this.downloadPdf(options, $btn, originalHtml);
        },

        /**
         * Download PDF via form submission
         *
         * @param {Object} options Export options
         * @param {jQuery} $btn Button element
         * @param {string} originalHtml Original button HTML
         */
        downloadPdf: function (options, $btn, originalHtml) {
            const self = this;

            // Create a hidden form to trigger the download
            const $form = $('<form>', {
                method: 'POST',
                action: this.config.ajaxUrl,
                target: '_blank',
                style: 'display: none;'
            });

            // Add form fields
            Object.keys(options).forEach(function (key) {
                $form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: options[key]
                }));
            });

            // Append to body and submit
            $('body').append($form);
            $form.submit();

            // Clean up
            setTimeout(function () {
                $form.remove();
                $btn.prop('disabled', false).html(originalHtml);
            }, 1000);
        },

        /**
         * Show success message
         *
         * @param {string} message Success message
         */
        showSuccess: function (message) {
            this.showNotification(message, 'success');
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function (message) {
            this.showNotification(message, 'error');
        },

        /**
         * Show notification
         *
         * @param {string} message Notification message
         * @param {string} type    Notification type (success, error, info)
         */
        showNotification: function (message, type) {
            // Try to use the chat notification system if available
            if (window.AIBotKitChat && typeof window.AIBotKitChat.showNotification === 'function') {
                window.AIBotKitChat.showNotification(message, type);
                return;
            }

            // Fallback to alert
            if (type === 'error') {
                alert('Error: ' + message);
            } else {
                alert(message);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        AIBotKitExport.init();
    });

})(jQuery);
