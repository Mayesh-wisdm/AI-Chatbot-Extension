(function( $ ) {
	'use strict';

	/**
	 * WDM AI BotKit Extension Admin JavaScript
	 *
	 * @link       https://wisdmlabs.com
	 * @since      1.0.0
	 * @package    Wdm_Ai_Botkit_Extension
	 */

	// Toast notification system
	var WdmToast = {
		container: null,
		
		init: function() {
			this.createContainer();
		},
		
		createContainer: function() {
			if (!this.container) {
				this.container = $('<div class="wdm-toast-container"></div>');
				$('body').append(this.container);
			}
		},
		
		show: function(message, type, duration) {
			var self = this;
			type = type || 'info';
			duration = duration || 5000;
			
			var icons = {
				success: 'dashicons-yes-alt',
				error: 'dashicons-no-alt',
				warning: 'dashicons-warning',
				info: 'dashicons-info'
			};
			
			var toast = $('<div class="wdm-toast ' + type + '">' +
				'<div class="toast-content">' +
					'<span class="toast-icon dashicons ' + icons[type] + '"></span>' +
					'<span class="toast-message">' + message + '</span>' +
					'<button class="toast-close dashicons dashicons-no-alt"></button>' +
				'</div>' +
				'<div class="toast-progress">' +
					'<div class="toast-progress-bar"></div>' +
				'</div>' +
			'</div>');
			
			this.container.append(toast);
			
			// Show animation
			setTimeout(function() {
				toast.addClass('show');
			}, 100);
			
			// Progress bar animation
			var progressBar = toast.find('.toast-progress-bar');
			progressBar.css('width', '100%');
			progressBar.css('transition', 'width ' + (duration / 1000) + 's linear');
			setTimeout(function() {
				progressBar.css('width', '0%');
			}, 100);
			
			// Auto hide
			setTimeout(function() {
				self.hide(toast);
			}, duration);
			
			// Close button
			toast.find('.toast-close').on('click', function() {
				self.hide(toast);
			});
			
			return toast;
		},
		
		hide: function(toast) {
			toast.removeClass('show');
			setTimeout(function() {
				toast.remove();
			}, 300);
		},
		
		success: function(message, duration) {
			return this.show(message, 'success', duration);
		},
		
		error: function(message, duration) {
			return this.show(message, 'error', duration);
		},
		
		warning: function(message, duration) {
			return this.show(message, 'warning', duration);
		},
		
		info: function(message, duration) {
			return this.show(message, 'info', duration);
		}
	};

	// License form handling
	var WdmLicenseForm = {
		init: function() {
			this.bindEvents();
		},
		
		bindEvents: function() {
			$(document).on('submit', '#wdm-extension-license-form', function(e) {
				e.preventDefault();
				WdmLicenseForm.handleSubmit($(this));
			});
		},
		
		handleSubmit: function(form) {
			var submitButton = form.find('input[type="submit"]');
			var originalText = submitButton.val();
			
			// Disable button and show loading
			submitButton.prop('disabled', true).val('Processing...');
			
			// Get form data
			var formData = new FormData(form[0]);
			formData.append('action', 'wdm_ai_botkit_extension_license_action');
			formData.append('nonce', wdm_ai_botkit_extension_ajax.nonce);
			
			// Determine action from button text
			var action = 'activate';
			if (submitButton.val().toLowerCase().includes('deactivate')) {
				action = 'deactivate';
			}
			formData.append('license_action', action);
			
			$.ajax({
				url: wdm_ai_botkit_extension_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						WdmToast.success(response.data.message || 'License action completed successfully');
						
						// Update status display if provided
						if (response.data.status_display) {
							WdmLicenseForm.updateStatusDisplay(response.data.status_display);
						}
						
						// Update form button
						WdmLicenseForm.updateFormButton(form, response.data.status_display);
					} else {
						WdmToast.error(response.data.message || 'An error occurred');
					}
				},
				error: function() {
					WdmToast.error('An error occurred while processing the license');
				},
				complete: function() {
					// Re-enable button
					submitButton.prop('disabled', false).val(originalText);
				}
			});
		},
		
		updateStatusDisplay: function(statusDisplay) {
			var statusElement = $('#extension-license-status');
			if (statusElement.length) {
				statusElement.removeClass('valid invalid warning error')
					.addClass(statusDisplay.class)
					.html('<span class="dashicons dashicons-' + 
						(statusDisplay.status === 'valid' ? 'yes-alt' : 'no-alt') + 
						'"></span>' + statusDisplay.message);
			}
		},
		
		updateFormButton: function(form, statusDisplay) {
			var submitButton = form.find('input[type="submit"]');
			
			if (statusDisplay && statusDisplay.status === 'valid') {
				submitButton.val('Deactivate License')
					.removeClass('button-primary')
					.addClass('button-secondary')
					.attr('onclick', 'return confirm("Are you sure you want to deactivate the license?")');
			} else {
				submitButton.val('Activate License')
					.removeClass('button-secondary')
					.addClass('button-primary')
					.removeAttr('onclick');
			}
		}
	};

	// Initialize when DOM is ready
	$(function() {
		WdmToast.init();
		WdmLicenseForm.init();
	});

	// Make toast system globally available
	window.WdmToast = WdmToast;

	// License status check functionality
	$(document).ready(function() {
		$('#check-license-status').on('click', function() {
			var $btn = $(this);
			var nonce = $('#wdm_extension_license_nonce').val();
			
			// Store original button content
			var originalHtml = $btn.html();
			
			// Disable button and show loading state
			$btn.prop('disabled', true);
			$btn.html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i> Checking...');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wdm_ai_botkit_extension_check_license',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						var message = 'License status checked successfully. Status: ' + data.remote_status;
						
						if (data.status_changed) {
							message += ' (Status changed from ' + data.current_status + ' to ' + data.new_status + ')';
							WdmToast.success(message, 5000);
							
							// Reload page with cache busting to show updated status
							setTimeout(function() {
								location.href = location.href + (location.href.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();
							}, 1500);
						} else {
							message += ' (No change detected)';
							WdmToast.info(message, 8000);
						}
					} else {
						WdmToast.error('Failed to check license status: ' + (response.data ? response.data.message : 'Unknown error'));
					}
				},
				error: function() {
					WdmToast.error('Error checking license status');
				},
				complete: function() {
					// Restore button
					$btn.prop('disabled', false);
					$btn.html(originalHtml);
				}
			});
		});
		
		// LearnDash Sync functionality
		$('#learndash-sync-btn').on('click', function() {
			var $btn = $(this);
			var $progress = $('#sync-progress');
			var $results = $('#sync-results');
			var nonce = $btn.data('nonce');
			
			// Store original button content
			var originalHtml = $btn.html();
			
			// Disable button and show loading state
			$btn.prop('disabled', true);
			$btn.html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i> Processing...');
			$progress.show();
			$results.hide();
			
			// Start sync process
			startLearndashSync(nonce, originalHtml);
		});
		
		function startLearndashSync(nonce, originalHtml) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'learndash_sync_courses',
					sync_action: 'start',
					bot_id: 0, // Will auto-detect first bot if not specified
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						updateProgress(0, response.data.total_courses, 'Starting content upgrade...');
						processSyncBatch(nonce, response.data.total_courses, originalHtml);
					} else {
						showError(response.data.message || 'Failed to start content upgrade', originalHtml);
					}
				},
				error: function() {
					showError('Network error occurred', originalHtml);
				}
			});
		}
		
		function processSyncBatch(nonce, totalCourses, originalHtml) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'learndash_sync_courses',
					sync_action: 'process',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						var progress = Math.round((data.current_index / data.total_courses) * 100);
						
						updateProgress(data.current_index, data.total_courses, data.message);
						
						if (data.is_complete) {
							showResults(data);
							$('#learndash-sync-btn').prop('disabled', false).html(originalHtml);
						} else {
							// Continue processing
							setTimeout(function() {
								processSyncBatch(nonce, totalCourses, originalHtml);
							}, 1000);
						}
					} else {
						showError(response.data.message || 'Sync failed', originalHtml);
					}
				},
				error: function() {
					showError('Network error occurred during sync', originalHtml);
				}
			});
		}
		
		function updateProgress(current, total, message) {
			var progress = Math.round((current / total) * 100);
			$('.ai-botkit-progress-fill').css('width', progress + '%');
			$('#sync-status').text(message);
			$('#sync-count').text(current + ' / ' + total);
		}
		
		function showResults(data) {
			var $results = $('#sync-results');
			var $content = $('#sync-results-content');
			
			var html = '<div class="ai-botkit-sync-summary">';
			html += '<p><strong>Sync Completed Successfully!</strong></p>';
			html += '<ul>';
			html += '<li>Total courses processed: ' + data.total_processed + '</li>';
			html += '<li>Total courses found: ' + data.total_courses + '</li>';
			
			if (data.errors && data.errors.length > 0) {
				html += '<li>Errors: ' + data.errors.length + '</li>';
			}
			
			html += '</ul>';
			
			if (data.errors && data.errors.length > 0) {
				html += '<div class="ai-botkit-sync-errors">';
				html += '<h5>Errors encountered:</h5>';
				html += '<ul>';
				data.errors.forEach(function(error) {
					html += '<li>Course ID ' + error.course_id + ': ' + error.error + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}
			
			html += '</div>';
			
			$content.html(html);
			$results.show();
			$('#sync-progress').hide();
		}
		
		function showError(message, originalHtml) {
			$('#sync-progress').hide();
			$('#learndash-sync-btn').prop('disabled', false).html(originalHtml);
			
			var $results = $('#sync-results');
			var $content = $('#sync-results-content');
			
			$content.html('<div class="notice notice-error"><p>' + message + '</p></div>');
			$results.show();
		}
	});

})( jQuery );
