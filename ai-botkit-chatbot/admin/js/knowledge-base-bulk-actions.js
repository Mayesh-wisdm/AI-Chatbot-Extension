/**
 * Knowledge Base Bulk Actions Handler
 *
 * Handles bulk operations for documents in the knowledge base table.
 *
 * @since 2.0.4
 */

(function($) {
	'use strict';

	const BulkActions = {
		selectedIds: new Set(),

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Select all checkbox
			$('#ai-botkit-select-all').on('change', this.handleSelectAll.bind(this));

			// Individual checkboxes
			$(document).on('change', '.ai-botkit-document-checkbox', this.handleIndividualSelect.bind(this));

			// Bulk action dropdown
			$('#ai-botkit-bulk-action').on('change', this.handleBulkActionChange.bind(this));

			// Apply button
			$('#ai-botkit-apply-bulk-action').on('click', this.applyBulkAction.bind(this));

			// Clear selection button
			$('#ai-botkit-clear-selection').on('click', this.clearSelection.bind(this));

			// Clear selection when pagination changes
			$('#ai-botkit-prev-page, #ai-botkit-next-page').on('click', this.clearSelection.bind(this));
		},

		handleSelectAll: function(e) {
			const isChecked = $(e.target).is(':checked');
			$('.ai-botkit-document-checkbox').prop('checked', isChecked);

			if (isChecked) {
				$('.ai-botkit-document-checkbox').each((i, checkbox) => {
					this.selectedIds.add(parseInt($(checkbox).val()));
				});
			} else {
				this.selectedIds.clear();
			}

			this.updateUI();
		},

		handleIndividualSelect: function(e) {
			const checkbox = $(e.target);
			const id = parseInt(checkbox.val());

			if (checkbox.is(':checked')) {
				this.selectedIds.add(id);
			} else {
				this.selectedIds.delete(id);
				$('#ai-botkit-select-all').prop('checked', false);
			}

			this.updateUI();
		},

		handleBulkActionChange: function(e) {
			const action = $(e.target).val();

			// Show/hide bot selector for "add_to_bot" action
			if (action === 'add_to_bot') {
				$('#ai-botkit-bot-select-container').show();
			} else {
				$('#ai-botkit-bot-select-container').hide();
			}
		},

		updateUI: function() {
			const count = this.selectedIds.size;

			// Update count
			$('#ai-botkit-selected-count').text(`${count} selected`);

			// Show/hide toolbar
			if (count > 0) {
				$('.ai-botkit-bulk-actions-toolbar').slideDown(200);
			} else {
				$('.ai-botkit-bulk-actions-toolbar').slideUp(200);
				$('#ai-botkit-bulk-action').val('');
				$('#ai-botkit-bot-select-container').hide();
			}
		},

		clearSelection: function() {
			this.selectedIds.clear();
			$('.ai-botkit-document-checkbox, #ai-botkit-select-all').prop('checked', false);
			this.updateUI();
		},

		applyBulkAction: function() {
			const action = $('#ai-botkit-bulk-action').val();

			if (!action) {
				alert('Please select a bulk action.');
				return;
			}

			if (this.selectedIds.size === 0) {
				alert('Please select at least one document.');
				return;
			}

			// Validate bot selection for add_to_bot action
			if (action === 'add_to_bot') {
				const botId = $('#ai-botkit-target-bot').val();
				if (!botId) {
					alert('Please select a chatbot.');
					return;
				}
			}

			// Confirm destructive actions
			if (action === 'delete') {
				if (!confirm(`Are you sure you want to delete ${this.selectedIds.size} document(s)? This action cannot be undone.`)) {
					return;
				}
			}

			// Execute action
			switch(action) {
				case 'delete':
					this.bulkDelete();
					break;
				case 'reprocess':
					this.bulkReprocess();
					break;
				case 'add_to_bot':
					this.bulkAddToBot();
					break;
				case 'export':
					this.bulkExport();
					break;
			}
		},

		bulkDelete: function() {
			this.executeAction('delete', {
				document_ids: Array.from(this.selectedIds)
			}, 'Deleting documents...');
		},

		bulkReprocess: function() {
			this.executeAction('reprocess', {
				document_ids: Array.from(this.selectedIds)
			}, 'Reprocessing documents...');
		},

		bulkAddToBot: function() {
			const botId = $('#ai-botkit-target-bot').val();
			this.executeAction('add_to_bot', {
				document_ids: Array.from(this.selectedIds),
				chatbot_id: botId
			}, 'Adding documents to chatbot...');
		},

		bulkExport: function() {
			const documentIds = Array.from(this.selectedIds);

			// Create form and submit to trigger download
			const form = $('<form>', {
				method: 'POST',
				action: ajaxurl,
				target: '_blank'
			});

			form.append($('<input>', {
				type: 'hidden',
				name: 'action',
				value: 'ai_botkit_bulk_export'
			}));

			form.append($('<input>', {
				type: 'hidden',
				name: 'document_ids',
				value: JSON.stringify(documentIds)
			}));

			form.append($('<input>', {
				type: 'hidden',
				name: 'nonce',
				value: ai_botkit_admin.nonce
			}));

			$('body').append(form);
			form.submit();
			form.remove();

			console.log('[Bulk Export] Exporting', documentIds.length, 'documents');
		},

		executeAction: function(action, data, loadingMessage) {
			const $button = $('#ai-botkit-apply-bulk-action');
			const originalText = $button.text();

			// Disable button and show loading
			$button.prop('disabled', true).text(loadingMessage);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ai_botkit_bulk_' + action,
					nonce: ai_botkit_admin.nonce,
					...data
				},
				success: (response) => {
					if (response.success) {
						this.showNotification('success', response.data.message || 'Action completed successfully!');

						// Reload page after successful operation
						setTimeout(() => {
							window.location.reload();
						}, 1500);
					} else {
						this.showNotification('error', response.data.message || 'Action failed.');
						$button.prop('disabled', false).text(originalText);
					}
				},
				error: (xhr, status, error) => {
					console.error('[Bulk Action Error]', error);
					this.showNotification('error', 'An error occurred. Please try again.');
					$button.prop('disabled', false).text(originalText);
				}
			});
		},

		showNotification: function(type, message) {
			const className = type === 'success' ? 'notice-success' : 'notice-error';
			const $notice = $(`
				<div class="notice ${className} is-dismissible" style="margin: 20px 0;">
					<p>${message}</p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text">Dismiss this notice.</span>
					</button>
				</div>
			`);

			$('.ai-botkit-bulk-actions-toolbar').after($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(() => $notice.remove());
			}, 5000);

			// Handle manual dismiss
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeOut(() => $notice.remove());
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BulkActions.init();
	});

})(jQuery);
