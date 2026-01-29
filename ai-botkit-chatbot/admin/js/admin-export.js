/**
 * Admin Export Module
 *
 * Handles PDF export functionality for chat conversations in admin.
 * Part of Phase 2: Chat Transcripts Export (FR-240 to FR-249).
 *
 * @package AI_BotKit
 * @since   2.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Export Handler
     */
    window.AIBotKitAdminExport = {
        /**
         * Currently processing batch
         */
        currentBatch: null,

        /**
         * Batch status check interval
         */
        statusInterval: null,

        /**
         * Initialize the export module
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Single export button
            $(document).on('click', '.ai-botkit-export-single-pdf', this.handleSingleExport.bind(this));

            // Bulk export button
            $(document).on('click', '.ai-botkit-bulk-export-pdf', this.handleBulkExport.bind(this));

            // Export modal actions
            $(document).on('click', '.ai-botkit-export-modal-submit', this.handleModalSubmit.bind(this));
            $(document).on('click', '.ai-botkit-export-modal-close', this.closeModal.bind(this));

            // Progress modal cancel
            $(document).on('click', '.ai-botkit-export-cancel', this.cancelExport.bind(this));
        },

        /**
         * Handle single conversation export
         *
         * @param {Event} e Click event
         */
        handleSingleExport: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const conversationId = $btn.data('conversation-id');

            if (!conversationId) {
                this.showError('No conversation selected.');
                return;
            }

            this.exportSingle(conversationId, $btn);
        },

        /**
         * Export single conversation
         *
         * @param {number} conversationId Conversation ID
         * @param {jQuery} $btn Button element
         */
        exportSingle: function(conversationId, $btn) {
            const self = this;
            const originalHtml = $btn.html();

            // Show loading state
            $btn.prop('disabled', true)
                .html('<span class="spinner is-active" style="margin: 0;"></span>');

            // Create download form
            const $form = $('<form>', {
                method: 'POST',
                action: ajaxurl,
                target: '_blank',
                style: 'display: none;'
            });

            $form.append($('<input>', { type: 'hidden', name: 'action', value: 'ai_botkit_export_pdf' }));
            $form.append($('<input>', { type: 'hidden', name: 'nonce', value: aiBotKitAdmin.nonce }));
            $form.append($('<input>', { type: 'hidden', name: 'conversation_id', value: conversationId }));
            $form.append($('<input>', { type: 'hidden', name: 'include_metadata', value: 'true' }));
            $form.append($('<input>', { type: 'hidden', name: 'include_branding', value: 'true' }));
            $form.append($('<input>', { type: 'hidden', name: 'paper_size', value: 'a4' }));

            $('body').append($form);
            $form.submit();

            // Reset button state
            setTimeout(function() {
                $form.remove();
                $btn.prop('disabled', false).html(originalHtml);
            }, 1500);
        },

        /**
         * Handle bulk export
         *
         * @param {Event} e Click event
         */
        handleBulkExport: function(e) {
            e.preventDefault();

            // Get selected conversation IDs
            const conversationIds = [];
            $('.ai-botkit-conversation-checkbox:checked').each(function() {
                conversationIds.push($(this).val());
            });

            if (conversationIds.length === 0) {
                this.showError('Please select at least one conversation to export.');
                return;
            }

            this.showExportModal(conversationIds);
        },

        /**
         * Show export options modal
         *
         * @param {Array} conversationIds Array of conversation IDs
         */
        showExportModal: function(conversationIds) {
            const modalHtml = `
                <div class="ai-botkit-export-modal-overlay">
                    <div class="ai-botkit-export-modal">
                        <div class="ai-botkit-export-modal-header">
                            <h2>Export Conversations</h2>
                            <button type="button" class="ai-botkit-export-modal-close">&times;</button>
                        </div>
                        <div class="ai-botkit-export-modal-body">
                            <p>You are about to export <strong>${conversationIds.length}</strong> conversation(s) to PDF.</p>

                            <div class="ai-botkit-export-options">
                                <h3>Export Options</h3>
                                <label>
                                    <input type="checkbox" name="include_metadata" checked>
                                    Include timestamps and metadata
                                </label>
                                <label>
                                    <input type="checkbox" name="include_branding" checked>
                                    Include site branding
                                </label>
                                <div class="ai-botkit-export-option-row">
                                    <label for="paper_size">Paper Size:</label>
                                    <select name="paper_size" id="paper_size">
                                        <option value="a4" selected>A4</option>
                                        <option value="letter">Letter</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="ai-botkit-export-modal-footer">
                            <button type="button" class="button button-secondary ai-botkit-export-modal-close">Cancel</button>
                            <button type="button" class="button button-primary ai-botkit-export-modal-submit" data-conversation-ids="${conversationIds.join(',')}">
                                Export to PDF
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
        },

        /**
         * Handle modal submit
         *
         * @param {Event} e Click event
         */
        handleModalSubmit: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const conversationIds = $btn.data('conversation-ids').toString().split(',').map(Number);
            const $modal = $btn.closest('.ai-botkit-export-modal');

            const options = {
                include_metadata: $modal.find('[name="include_metadata"]').is(':checked'),
                include_branding: $modal.find('[name="include_branding"]').is(':checked'),
                paper_size: $modal.find('[name="paper_size"]').val()
            };

            this.closeModal();
            this.startBatchExport(conversationIds, options);
        },

        /**
         * Start batch export
         *
         * @param {Array} conversationIds Array of conversation IDs
         * @param {Object} options Export options
         */
        startBatchExport: function(conversationIds, options) {
            const self = this;

            // Show progress modal
            this.showProgressModal(conversationIds.length);

            // Start batch export
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_batch_export',
                    nonce: aiBotKitAdmin.nonce,
                    conversation_ids: conversationIds,
                    include_metadata: options.include_metadata,
                    include_branding: options.include_branding,
                    paper_size: options.paper_size
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'completed') {
                            // Immediate completion
                            self.handleExportComplete(response.data);
                        } else {
                            // Start polling for status
                            self.currentBatch = response.data.batch_id;
                            self.startStatusPolling();
                        }
                    } else {
                        self.handleExportError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.handleExportError('Export request failed: ' + error);
                }
            });
        },

        /**
         * Show progress modal
         *
         * @param {number} total Total conversations
         */
        showProgressModal: function(total) {
            const modalHtml = `
                <div class="ai-botkit-export-progress-overlay">
                    <div class="ai-botkit-export-progress-modal">
                        <h3>Exporting Conversations</h3>
                        <div class="ai-botkit-export-progress-bar">
                            <div class="ai-botkit-export-progress-fill" style="width: 0%;"></div>
                        </div>
                        <p class="ai-botkit-export-progress-text">Preparing export...</p>
                        <p class="ai-botkit-export-progress-count">0 / ${total}</p>
                        <button type="button" class="button ai-botkit-export-cancel">Cancel</button>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
        },

        /**
         * Update progress modal
         *
         * @param {Object} status Status data
         */
        updateProgress: function(status) {
            const percent = Math.round((status.progress / status.total) * 100);
            const $overlay = $('.ai-botkit-export-progress-overlay');

            $overlay.find('.ai-botkit-export-progress-fill').css('width', percent + '%');
            $overlay.find('.ai-botkit-export-progress-count').text(status.progress + ' / ' + status.total);

            if (status.failed > 0) {
                $overlay.find('.ai-botkit-export-progress-text').text(
                    'Processing... (' + status.failed + ' failed)'
                );
            }
        },

        /**
         * Start polling for batch status
         */
        startStatusPolling: function() {
            const self = this;

            this.statusInterval = setInterval(function() {
                self.checkBatchStatus();
            }, 2000);
        },

        /**
         * Check batch export status
         */
        checkBatchStatus: function() {
            const self = this;

            if (!this.currentBatch) {
                this.stopStatusPolling();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_export_status',
                    nonce: aiBotKitAdmin.nonce,
                    batch_id: this.currentBatch
                },
                success: function(response) {
                    if (response.success) {
                        self.updateProgress(response.data);

                        if (response.data.status === 'completed') {
                            self.handleExportComplete(response.data);
                        } else if (response.data.status === 'failed') {
                            self.handleExportError('Export failed. Some conversations could not be processed.');
                        }
                    } else {
                        self.handleExportError(response.data.message);
                    }
                },
                error: function() {
                    // Silently retry on error
                }
            });
        },

        /**
         * Handle export completion
         *
         * @param {Object} data Response data
         */
        handleExportComplete: function(data) {
            this.stopStatusPolling();
            this.closeProgressModal();

            if (data.download_url) {
                // Trigger download
                window.location.href = data.download_url;

                // Show success message
                this.showSuccess(
                    'Export completed! ' + data.completed + ' conversation(s) exported.' +
                    (data.failed > 0 ? ' ' + data.failed + ' failed.' : '')
                );
            } else {
                this.showSuccess(data.message || 'Export completed successfully.');
            }
        },

        /**
         * Handle export error
         *
         * @param {string} message Error message
         */
        handleExportError: function(message) {
            this.stopStatusPolling();
            this.closeProgressModal();
            this.showError(message);
        },

        /**
         * Cancel current export
         */
        cancelExport: function() {
            this.stopStatusPolling();
            this.closeProgressModal();
            this.currentBatch = null;
        },

        /**
         * Stop status polling
         */
        stopStatusPolling: function() {
            if (this.statusInterval) {
                clearInterval(this.statusInterval);
                this.statusInterval = null;
            }
        },

        /**
         * Close export modal
         */
        closeModal: function() {
            $('.ai-botkit-export-modal-overlay').remove();
        },

        /**
         * Close progress modal
         */
        closeProgressModal: function() {
            $('.ai-botkit-export-progress-overlay').remove();
        },

        /**
         * Show success message
         *
         * @param {string} message Success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show admin notice
         *
         * @param {string} message Notice message
         * @param {string} type    Notice type (success, error, warning, info)
         */
        showNotice: function(message, type) {
            const noticeClass = 'notice-' + (type || 'info');
            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible ai-botkit-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            // Insert at top of page content
            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        AIBotKitAdminExport.init();
    });

})(jQuery);
