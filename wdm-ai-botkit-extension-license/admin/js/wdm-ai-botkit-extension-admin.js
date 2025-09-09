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

})( jQuery );
