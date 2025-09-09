/**
 * AI BotKit Admin Scripts
 * 
 * This file contains all the JavaScript functionality for the admin interface
 * including chatbot management, API testing, and analytics visualization.
 */
jQuery(document).ready(function($) {
    'use strict';

    // Toast notification system
    var AiBotkitToast = {
        container: null,
        
        init: function() {
            this.container = $('<div class="ai-botkit-toast-container"></div>');
            $('body').append(this.container);
        },
        
        show: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 5000;
            
            var icons = {
                success: 'dashicons dashicons-yes-alt',
                error: 'dashicons dashicons-dismiss',
                warning: 'dashicons dashicons-warning',
                info: 'dashicons dashicons-info'
            };
            
            var toast = $('<div class="ai-botkit-toast ' + type + '">' +
                '<div class="toast-content">' +
                '<span class="toast-icon ' + icons[type] + '"></span>' +
                '<span class="toast-message">' + message + '</span>' +
                '<button class="toast-close dashicons dashicons-no-alt"></button>' +
                '</div>' +
                '<div class="toast-progress">' +
                '<div class="toast-progress-bar"></div>' +
                '</div>' +
                '</div>');
            
            this.container.append(toast);
            
            // Trigger animation
            setTimeout(function() {
                toast.addClass('show');
            }, 10);
            
            // Auto-hide
            var self = this;
            setTimeout(function() {
                self.hide(toast);
            }, duration);
            
            // Manual close
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
    
    // Initialize toast system
    AiBotkitToast.init();
    
    // Make toast system globally available
    window.AiBotkitToast = AiBotkitToast;

    // side bar
    const links = document.querySelectorAll('.ai-botkit-sidebar-link');
    const path = window.location.pathname;

    links.forEach(link => {
        if (link.getAttribute('href') === path) {
        link.style.backgroundColor = '#008858';
        link.style.color = '#fff';
        }
    });

    $('#ai-botkit-create-bot-btn').click(function() {
        $('.ai-botkit-wizard-container').show();
        $('.ai-botkit-dashboard-wrapper').hide();
        $('.ai-botkit-sidebar-wrapper').hide();
        // loadAvailableDocuments();

    });

    $('#ai-botkit-chatbot-wizard-back').click(function() {
        // remove create=1 from url
        var url = window.location.href;
        url = url.replace('create=1', '');
        window.location.href = url;
    });

    // hide sidebar when create new chatbot is on
    if ( $('.ai-botkit-wizard-container').is(':visible') ) {
        $('.ai-botkit-sidebar-wrapper').hide();
    } else {
        $('.ai-botkit-sidebar-wrapper').show();
    }

    /**
     * Load available documents for selection
     */
    const loadAvailableDocuments = function(chatbotId = 0) {
        $.ajax({
            url: ai_botkitAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_get_available_documents',
                _ajax_nonce: ai_botkitAdmin.nonce,
                chatbot_id: chatbotId
            },
            success: function(response) {
                if (response.success) {
                    $('.ai-botkit-training-data tbody').empty();
                    $('#ai-botkit-imports').val('');
                    // Show documents in modal
                    response.data.documents.forEach(function(doc) {
                        const itemActions = `<span class="ai-botkit-remove-training-item"><i class="ti ti-trash"></i></span>`;
                        let itemStatus = '';
                        if( doc.status === 'processing' ) {
                            itemStatus = `<span class="ai-botkit-badge ai-botkit-badge-info">Processing</span>`;
                        } else if( doc.status === 'completed' ) {
                            itemStatus = `<span class="ai-botkit-badge ai-botkit-badge-success">Completed</span>`;
                        } else if( doc.status === 'failed' ) {
                            itemStatus = `<span class="ai-botkit-badge ai-botkit-badge-error">Failed</span>`;
                        }
                        const itemHtml = `
                        <tr data-id="${doc.id}" data-type="${doc.source_type}">
                            <td>${doc.title}</td>
                            <td>${doc.source_type}</td>
                            <td>${itemStatus}</td>
                            <td>${itemActions}</td>
                        </tr>
                        `;
                        $(`.ai-botkit-training-data-${doc.source_type} tbody`).append(itemHtml);
                        $('.ai-botkit-training-data-' + doc.source_type).css('display', 'flex');
                        $('.ai-botkit-no-training-docs').hide();

                        var imports = $('#ai-botkit-imports').val();
                        imports = imports ? JSON.parse(imports) : []; // if empty, set as []
                        imports.push(Number(doc.id));
                        $('#ai-botkit-imports').val(JSON.stringify(imports));
                    });
                }
            }
        });
    }

    $('#ai-botkit-add-from-kb').click(function(e) {
        e.preventDefault();
        let imports = $('#ai-botkit-imports').val();
        imports = imports ? JSON.parse(imports) : []; // if empty, set as []
        // get checked items
        const kbItems = $('#ai-botkit-existing-kb-table-body tr');
        kbItems.each(function() {
            if ($(this).find('.ai-botkit-checkbox').prop('checked')) {
                const itemId = $(this).find('.ai-botkit-checkbox').data("id");
                imports.push(itemId);
                addItemToTrainingData($(this));
                // update the checkbox
                $(this).find('.ai-botkit-checkbox').prop('checked', false);
            }
        });
        $('#ai-botkit-imports').val(JSON.stringify(imports));
    });

    const addItemToTrainingData = function(item) {
        const itemType = item.data("type");
        const itemId = item.find('.ai-botkit-checkbox').data("id");
        const itemName = item.find('td:nth-child(2)').text();
        const itemStatus = item.find('td:nth-child(4)').html();
        const itemActions = `<span class="ai-botkit-remove-training-item"><i class="ti ti-trash"></i></span>`;

        const itemHtml = `
        <tr data-id="${itemId}" data-type="${itemType}">
            <td>${itemName}</td>
            <td>${itemType}</td>
            <td>${itemStatus}</td>
            <td>${itemActions}</td>
        </tr>
        `;
        $(`.ai-botkit-training-data-${itemType} tbody`).append(itemHtml);
        $('.ai-botkit-training-data-' + itemType).css('display', 'flex');
        $('.ai-botkit-no-training-docs').hide();
    }
    
    $('.ai-botkit-training-data').on('click', '.ai-botkit-remove-training-item', function(e) {
        e.preventDefault();
        const itemId = $(this).closest('tr').data("id");
        const itemType = $(this).closest('tr').data("type");
        let imports = $('#ai-botkit-imports').val();
        imports = imports ? JSON.parse(imports) : []; // if empty, set as []
        imports = imports.filter(id => id !== itemId);
        $('#ai-botkit-imports').val(JSON.stringify(imports));
        $(this).closest('tr').remove();
        // check if the closest table has no items
        if ($(this).closest('.ai-botkit-training-data-' + itemType + ' tbody tr').length === 0) {
            $(this).closest('.ai-botkit-training-data-' + itemType).css('display', 'none');
        }
        // if all the items are removed, show the no training docs message
        if ($('.ai-botkit-training-data-post tbody tr').length === 0 && $('.ai-botkit-training-data-url tbody tr').length === 0 && $('.ai-botkit-training-data-file tbody tr').length === 0) {
            $('.ai-botkit-training-data').hide();
            $('.ai-botkit-no-training-docs').show();
        }
    });

    // lnowledgebase search
    $('#ai-botkit-kb-search').on('input', function() {
        const searchValue = $(this).val().toLowerCase();
        // get all kb-list items
        const kbListItems = $('#ai-botkit-existing-kb-table-body tr');
        kbListItems.each(function() {
            const item = $(this);
            const itemText = item.find('td:nth-child(2)').text().toLowerCase();
            if (itemText.includes(searchValue)) {
                item.show();
            } else {
                item.hide();
            }
        });
    });

    // $('#ai-botkit-add-data-btn').click(function(e) {
    //     $('.ai-botkit-add-data-items').css('display', 'flex');
    //     $('.ai-botkit-add-data-btn').css('display', 'none');
    // });

    let currentStep = 0;
    let totalSteps =5;
    $('.ai-botkit-tab').click(function() {
        const tabId = $(this).data('tab');
        
        // Handle wizard tabs
        if ($(this).data('step') !== undefined) {
            const step = parseInt($(this).data('step'));
            currentStep = step;

            $('.ai-botkit-tab').removeClass('active');
            $(this).addClass('active');

            // hide all step contents
            $('.ai-botkit-step-content').hide();
            // show the step content
            $(`.ai-botkit-step-content[data-step="${step}"]`).show();

            updateProgress();

            if (currentStep === totalSteps - 1) {
                $('#ai-botkit-save-btn').removeAttr('disabled');
                $('#ai-botkit-next-btn').hide();
            } else {
                $('#ai-botkit-next-btn').show();
            }
            return;
        }
        
        // Handle main navigation tabs
        if (tabId) {
            // Hide all tabs and remove active class
            $('.ai-botkit-tab-content').hide();
            $('.ai-botkit-tab').removeClass('active');
            
            // Show selected tab and add active class
            $(`#${tabId}`).show();
            $(this).addClass('active');
            
            // Reinitialize chat functionality if we're on the home tab
            if (tabId === 'home') {
                // Reinitialize chat input handlers
                $('.ai-botkit-input-form').off('submit').on('submit', function(e) {
                    e.preventDefault();
                    const message = $('.ai-botkit-input').val().trim();
                    if (message && !window.isProcessing) {
                        window.sendMessage(message);
                    }
                });
                
                $('.ai-botkit-input').off('keydown').on('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        const message = $(this).val().trim();
                        if (message && !window.isProcessing) {
                            window.sendMessage(message);
                        }
                    }
                });
            }
        }
    });

    $('#ai-botkit-prev-btn').click(function() {
        if (currentStep > 0) {
            currentStep--;
            $('.ai-botkit-tab').removeClass('active');
            $(`.ai-botkit-tab[data-step="${currentStep}"]`).addClass('active');
            $('.ai-botkit-step-content').hide();
            $(`.ai-botkit-step-content[data-step="${currentStep}"]`).show();
            updateProgress();
        }
        if (currentStep === totalSteps - 1) {
            // $('.ai-botkit-save-chatbot-container').show();
            $('#ai-botkit-save-btn').removeAttr('disabled');
            $('#ai-botkit-next-btn').hide();
        } else {
            // $('.ai-botkit-save-chatbot-container').hide();
            $('#ai-botkit-next-btn').show();
        }
    });

    $('#ai-botkit-next-btn').click(function() {
        if (currentStep < totalSteps - 1) {
            currentStep++;
            $('.ai-botkit-tab').removeClass('active');
            $(`.ai-botkit-tab[data-step="${currentStep}"]`).addClass('active');
            $('.ai-botkit-step-content').hide();
            $(`.ai-botkit-step-content[data-step="${currentStep}"]`).show();
            updateProgress();
        }
        if (currentStep === totalSteps - 1) {
            // $('.ai-botkit-save-chatbot-container').show();
            $('#ai-botkit-save-btn').removeAttr('disabled');
            $('#ai-botkit-next-btn').hide();
        } else {
            // $('.ai-botkit-save-chatbot-container').hide();
            $('#ai-botkit-next-btn').show();
        }
    });

    function updateProgress() {
        const progress = (currentStep / (totalSteps - 1)) * 100;
        $('.ai-botkit-progress-fill').css('width', `${progress}%`);
        $('#ai-botkit-step-indicator').text(`Step ${currentStep + 1} of ${totalSteps}`);
        $('#ai-botkit-completion-indicator').text(`${progress.toFixed(0)}% completed`);
    }

    $('#ai-botkit-hamburger-menu').click(function(e) {
        e.stopPropagation()
        $('.ai-botkit-sidebar-wrapper').toggleClass('open');
    });

    // close sidebar when clicking outside
    $(document).on('click', function(e) {
        if ( $('.ai-botkit-sidebar-wrapper').hasClass('open') ) {
            if ( !$(e.target).closest('.ai-botkit-sidebar-wrapper').length ) {
                $('.ai-botkit-sidebar-wrapper').removeClass('open');
            }
        }
    });

    // activation modal
    $('#ai-botkit-cancel-activation').click(function() {
        $('#ai-botkit-activation-modal').fadeOut();
    });

    window.addEventListener('message', (e) => {
        if (e.origin === 'https://tally.so' && e.data.includes('Tally.FormSubmitted')) {
            $('#ai-botkit-activation-modal').fadeOut();
        }
    });

    // Copy Shortcode
    $(".ai-botkit-copy-shortcode").on("click", function (e) {
        e.preventDefault();
        const chatbotId = $('#saved_chatbot_id').val();
        const shortcode = `[ai_botkit_chat id="${chatbotId}"]`;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode).then(function () {
                $('.ai-botkit-shortcode-wrapper').hide();
            }).catch(function (err) {
                console.error("Failed to copy:", err);
            });
        } else {
            // Fallback if Clipboard API is not supported
            const tempInput = $("<input>");
            $("body").append(tempInput);
            tempInput.val(shortcode).select();
            document.execCommand("copy");
            tempInput.remove();
            $('.ai-botkit-shortcode-wrapper').hide();
            console.warn("Used fallback copy method.");
        }
    });
    $(".ai-botkit-copy-widget-code").on("click", function (e) {
        e.preventDefault();
        const chatbotId = $('#saved_chatbot_id').val();
        const shortcode = `[ai_botkit_widget id="${chatbotId}"]`;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode).then(function () {
                $('.ai-botkit-shortcode-wrapper').hide();
            }).catch(function (err) {
                console.error("Failed to copy:", err);
            });
        } else {
            // Fallback if Clipboard API is not supported
            const tempInput = $("<input>");
            $("body").append(tempInput);
            tempInput.val(shortcode).select();
            document.execCommand("copy");
            tempInput.remove();
            $('.ai-botkit-shortcode-wrapper').hide();
            console.warn("Used fallback copy method.");
        }
    });

    $('.ai-botkit-copy-code').on("click", function () {
        $(this).closest('.ai-botkit-copy-code-wrapper').find('.ai-botkit-shortcode-wrapper').show();
    });

    // Tabs switching
    $(".ai-botkit-training-tab").on("click", function (e) {
        e.preventDefault();
        const tabName = $(this).data("tab");
    
        $(".ai-botkit-training-tab").removeClass("active");
        $(this).addClass("active");
    
        const kbListItems = $('#ai-botkit-existing-kb-table-body tr');
        kbListItems.each(function() {
            const item = $(this);
            const itemType = item.data("type");
            if (itemType === tabName || tabName === 'all') {
                item.show();
            } else {
                item.hide();
            }
        });
    });
    
    // File Uploads
    $("#ai-botkit-pdf-upload").on("change", function (e) {
        const files = Array.from(e.target.files);
        const $fileList = $("#ai-botkit-file-list").empty();

        const formData = new FormData();
        formData.append('action', 'ai_botkit_upload_file');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('file', files[0]);

        const loadingHtml = $('#ai-botkit-document-uploading').html();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#ai-botkit-document-uploading').removeClass('hidden');
                $('#ai-botkit-document-upload-box').addClass('hidden');
            },
            success: function(response) {
                if (response.success) {
                    const itemActions = `<span class="ai-botkit-remove-training-item"><i class="ti ti-trash"></i></span>`;

                    const itemHtml = `
                    <tr data-id="${response.data.document_id}" data-type="url">
                        <td>${files[0].name}</td>
                        <td>File</td>
                        <td><span class="ai-botkit-badge ai-botkit-badge-info">Processing</span></td>
                        <td>${itemActions}</td>
                    </tr>
                    `;

                    $(`.ai-botkit-training-data-file tbody`).append(itemHtml);
                    $('.ai-botkit-training-data-file').css('display', 'flex');
                    $('.ai-botkit-no-training-docs').hide();
                    $('#ai-botkit-document-uploaded').removeClass('hidden');
                    $('#ai-botkit-document-uploading').addClass('hidden');
                    $('#ai-botkit-document-upload-box').addClass('hidden');

                    var imports = $('#ai-botkit-imports').val();
                    imports = imports ? JSON.parse(imports) : []; // if empty, set as []
                    imports.push(response.data.document_id);
                    $('#ai-botkit-imports').val(JSON.stringify(imports));
                } else {
                    $('#ai-botkit-document-uploading').addClass('error');
                    $('#ai-botkit-document-uploading').removeClass('hidden');
                    $('#ai-botkit-document-uploading').html('<p>'+response.data.message+'</p>');
                }
            },
            error: function() {
                $('#ai-botkit-document-uploading').addClass('error');
                $('#ai-botkit-document-uploading').removeClass('hidden');
                $('#ai-botkit-document-uploading').html('<p>Error uploading file</p>');
            },
            complete: function() {
                
                setTimeout(function() {
                    $('#ai-botkit-add-training-document-modal').fadeOut();
                    $('#ai-botkit-document-uploading').addClass('hidden');
                    $('#ai-botkit-document-uploading').removeClass('error');
                    $('#ai-botkit-document-uploading').html(loadingHtml);
                    $('#ai-botkit-document-upload-box').removeClass('hidden');
                    $('#ai-botkit-document-uploaded').addClass('hidden');
                }, 2000);
            }
        });
    });
    
    // URL Management
    $("#ai-botkit-add-url").on("click", function (e) {
        e.preventDefault();
        const $input = $("#ai-botkit-url-input");
        const url = $input.val().trim();

        const formData = new FormData();
        formData.append('action', 'ai_botkit_import_url');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('url', url);

        const button = $(this);
        const buttonHtml = button.html();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                button.html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>');
            },
            success: function(response) {
                if (response.success) {
                    const itemActions = `<span class="ai-botkit-remove-training-item"><i class="ti ti-trash"></i></span>`;

                    const itemHtml = `
                    <tr data-id="${response.data.document_id}" data-type="url">
                        <td>${url}</td>
                        <td>URL</td>
                        <td><span class="ai-botkit-badge ai-botkit-badge-info">Processing</span></td>
                        <td>${itemActions}</td>
                    </tr>
                    `;
                    
                    $(`.ai-botkit-training-data-url tbody`).append(itemHtml);
                    $('.ai-botkit-training-data-url').css('display', 'flex');
                    $('.ai-botkit-no-training-docs').hide();
                    $input.val("");

                    var imports = $('#ai-botkit-imports').val();
                    imports = imports ? JSON.parse(imports) : []; // if empty, set as []
                    imports.push(response.data.document_id);
                    $('#ai-botkit-imports').val(JSON.stringify(imports));
                } else {
                    $('#ai-botkit-url-error-message').text(response.data.message);
                    $('#ai-botkit-url-error-message').addClass('show');
                }
            },
            error: function() {
                $('#ai-botkit-url-error-message').text('Error adding URL');
                $('#ai-botkit-url-error-message').addClass('show');
            },
            complete: function() {
                button.html(buttonHtml);
                setTimeout(function() {
                    $('#ai-botkit-url-error-message').removeClass('show');
                    $('#ai-botkit-url-input').val('');
                    $('#ai-botkit-add-training-url-modal').fadeOut();
                }, 2000);
            }
        });
    });
    
    $("#ai-botkit-url-list").on("click", ".ai-botkit-remove-url", function () {
        $(this).closest(".ai-botkit-url-item").remove();
    });
    
    const $wpContainer = $("#ai-botkit-wp-types");

    
    $("#ai-botkit-select-all").on("click", function (e) {
        e.preventDefault();
        $wpContainer.find('input[type="checkbox"]').prop("checked", true);
    });
    
    $("#ai-botkit-deselect-all").on("click", function (e) {
        e.preventDefault();
        $wpContainer.find('input[type="checkbox"]').prop("checked", false);
    });
    
    $("#ai-botkit-preview-wp").on("click", function (e) {
        e.preventDefault();
        const selected = $wpContainer
        .find('input[type="checkbox"]:checked')
        .map(function () {
            return $(this).val();
        })
        .get();
    
        var formData = new FormData();
        formData.append('action', 'ai_botkit_preview_content');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('date_from', $('#ai-botkit-start-date').val() ? $('#ai-botkit-start-date').val() : '');
        formData.append('date_to', $('#ai-botkit-end-date').val() ? $('#ai-botkit-end-date').val() : '');
        formData.append('search', $('#ai-botkit-search').val() ? $('#ai-botkit-search').val() : '');
        selected.forEach(function (id) {
            formData.append('post_types[]', id);
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#ai-botkit-wp-list').html('<div class="spinner is-active"></div>');
            },
            success: function(response) {
                if (response.success) {
                    $('#ai-botkit-wp-list').html(response.data.html);
                    $('#ai-botkit-import-wp').show();
                } else {
                    $('#ai-botkit-wp-list').html(
                        '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                    );  
                }
            },
            error: function() {
                $('#ai-botkit-wp-list').html(
                    '<div class="notice notice-error"><p><?php esc_html_e("Error loading preview.", "ai-botkit"); ?></p></div>'
                );
            }
        });
    });

    $("#ai-botkit-import-wp").on("click", function (e) {
        e.preventDefault();
        const selected = $('.ai-botkit-wp-data-import:checked')
        .map(function () {
            return $(this).val();
        })
        .get();

        const formData = new FormData();
        formData.append('action', 'ai_botkit_import_wp_content');
        formData.append('nonce', ai_botkitAdmin.nonce);
        selected.forEach(function (id) {
            formData.append('post_ids[]', id);
        });

        const button = $(this);
        const buttonHtml = button.html();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                button.html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>');
            },
            success: function(response) {
                if (response.success) {
                    button.html('<i style="font-size: 1.25rem;" class="ti ti-circle-check"></i>');
                } else {
                    $('#ai-botkit-wp-error-message').html(response.data.message);
                    $('#ai-botkit-wp-error-message').show();
                }
            },
            error: function() {
                $('#ai-botkit-wp-error-message').html('Error importing content');
                $('#ai-botkit-wp-error-message').show();
            },
            complete: function() {
                setTimeout(function() {
                    button.html(buttonHtml);
                    $('#ai-botkit-wp-error-message').removeClass('show');
                    $('#ai-botkit-wp-error-message').html('');
                    $('#ai-botkit-wordpress-modal').fadeOut();
                    window.location.reload();
                }, 2000);
            }
        });
    });

    const defaultColor = "#008858";
    let selectedColor = defaultColor;
  
    // Set initial preview color
    $(`.ai-botkit-color-circle[data-color="${selectedColor}"]`).addClass("selected");
  
    // Handle predefined color selection
    $(".ai-botkit-color-circle[data-color]").on("click", function () {
      const color = $(this).data("color");
      selectedColor = color;
  
      $(".ai-botkit-color-circle").removeClass("selected");
      $(".ai-botkit-color-preview").removeClass("selected");
      $(this).addClass("selected");
  
      $("#primary_color").val(color);
    //   $(".bot-msg").css("background", color);
      $(".ai-botkit-chat-form button").css("background", color);
      $(".ai-botkit-chat-bubble").css("background", color);
      $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + color + ' 0%, ' + color + ' 100%)');
      $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(180deg, ' + color + ' 0%, ' + color + ' 100%)');
      updateColorCircle('#user_msg_bg_color', color);

      // theme
      const theme = $("#chatbot_theme").val();
      if(theme === "theme-3" || theme === "theme-4") {
        updateColorCircle('#header_bg_color', color);
      } else {
        updateColorCircle('#header_bg_color', '#FFFFFF');
      }
      if(theme === "theme-2" || theme === "theme-4") {
        const primaryColorWithOpacity = color + '1A';
        updateColorCircle('#chat_bg_color', primaryColorWithOpacity);
      } else {
        updateColorCircle('#chat_bg_color', '#ffffff');
      }
    });
  
    // Handle custom color picker
    $("#ai-botkit-color-picker").on("input", function () {
      const color = $(this).val();
      selectedColor = color;
        
      $(".ai-botkit-color-picker-icon").hide();
      $(".ai-botkit-color-circle").removeClass("selected");
      $("#ai-botkit-color-preview").css("background-color", color);
      $(".ai-botkit-color-preview").addClass("selected");
      $("#primary_color").val(color);
    //   $(".bot-msg").css("background", color);
      $(".ai-botkit-chat-form button").css("background", color);
      $(".ai-botkit-chat-bubble").css("background", color);
      $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + color + ' 0%, ' + color + ' 100%)');
      $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(180deg, ' + color + ' 0%, ' + color + ' 100%)');
      updateColorCircle('#user_msg_bg_color', color);

      // theme
      const theme = $("#chatbot_theme").val();
      if(theme === "theme-3" || theme === "theme-4") {
        updateColorCircle('#header_bg_color', color);
      } else {
        updateColorCircle('#header_bg_color', '#FFFFFF');
      }
      if(theme === "theme-2" || theme === "theme-4") {
        const primaryColorWithOpacity = color + '1A';
        updateColorCircle('#chat_bg_color', primaryColorWithOpacity);
      } else {
        updateColorCircle('#chat_bg_color', '#ffffff');
      }
    });

    $(document).on("input", ".ai-botkit-color-picker", function () {
        const $picker = $(this);
        const color = $picker.val();
    
        // Update the preview color circle
        $picker.siblings(".ai-botkit-color-circle").css("background-color", color);
    
        // Update the displayed hex value
        $picker.closest(".ai-botkit-gradient-color-preview").find(".ai-botkit-color-picker-value").text(color);
    
        // Update the corresponding hidden input
        const target = $picker.data("target");
        if (target) $(target).val(color);

        // update the preview
        const targetElement = $(target).data('target');
        const key = $(target).data('key');
        
        if(key === 'background-gradient') {
            const color1 = $('#gradient_color_1').val();
            const color2 = $('#gradient_color_2').val();
            $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $(".ai-botkit-chat-form button").css("background", 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
        } else {
            const newtargetElement = $(`${targetElement}`);
            // if target is a single element, update the style
            if (newtargetElement.length === 1) {
                newtargetElement.css(key, color);
            } else {
                // if target is a collection of elements, update the style for each element
                newtargetElement.each(function() {
                    $(this).css(key, color);
                });
            }
        }
    });

    $(".ai-botkit-bot-theme-item").on("click", function() {
        const theme = $(this).data("theme");
        $("#chatbot_theme").val(theme);
        $(".ai-botkit-bot-theme-item").removeClass("active");
        $(this).addClass("active");
        if(theme === "theme-2" || theme === "theme-4" ) {
            const image = $(this).data("image");
            const primaryColor = $('#primary_color').val();
            // primary color with 10% opacity
            
            $('.ai-botkit-chat-body').css('background-image', 'url(' + image + ')');
            $('.ai-botkit-chat-body').css('background-size', 'cover');
            $('.ai-botkit-chat-body').css('background-position', 'center');
            $('.ai-botkit-chat-body').css('background-repeat', 'no-repeat');
            const primaryColorWithOpacity = primaryColor + '1A';
            updateColorCircle('#chat_bg_color', primaryColorWithOpacity);
        } else {
            $('.ai-botkit-chat-body').css('background-image', 'none');
            updateColorCircle('#chat_bg_color', '#ffffff');
        }
        if(theme === "theme-3" || theme === "theme-4") {
            const color = $('#primary_color').val();
            $('.ai-botkit-chat-header').css('background-color', color);
            updateColorCircle('#header_bg_color', color);
            updateColorCircle('#header_font_color', '#FFFFFF');
            updateColorCircle('#header_icon_color', '#FFFFFF');
        } else {
            $('.ai-botkit-chat-header').css('background-color', '#FFFFFF');
            updateColorCircle('#header_bg_color', '#FFFFFF');
            updateColorCircle('#header_font_color', '#333333');
            updateColorCircle('#header_icon_color', '#888888');
        }
    });
    
    
    

    $("#ai-botkit-chatbot-hide-preview").on("click", function (e) {
        $('.ai-botkit-chat-page').toggle();
        // check if i have ti-eye-off class
        if($(this).find('i').hasClass('ti-eye-off')) {
            $(this).html('<i class="ti ti-eye"></i>' + ai_botkitAdmin.i18n.hidePreview);
        } else {
            $(this).html('<i class="ti ti-eye-off"></i>' + ai_botkitAdmin.i18n.showPreview);
        }
    });

    // save chatbot
    $("#ai-botkit-save-btn").on("click", function (e) {
        e.preventDefault();
        
        // Temporarily disable required attributes on form fields to allow form submission
        $('#ai-botkit-chatbot-form input[required]').prop('required', false);
        
        const formData = new FormData($('#ai-botkit-chatbot-form')[0]);
        formData.append('action', 'ai_botkit_save_chatbot');
        formData.append('nonce', ai_botkitAdmin.nonce);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $(".ai-botkit-save-chatbot-status").show();
                $(".ai-botkit-save-chatbot-status").html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>');
            },
            success: function(response) {
                $("#ai-botkit-save-btn").html('Save');
                
                $(".ai-botkit-save-chatbot-status").html(ai_botkitAdmin.i18n.successChatbotSaved);
                $(".ai-botkit-save-chatbot-status").addClass('success');

                $('#saved_chatbot_id').val(response.data.chatbot_id);

                $('#chatbot_active_widget').val('[ai-botkit-widget id="' + response.data.chatbot_id + '"]');
                $('#chatbot_active_shortcode').val('[ai-botkit-chat id="' + response.data.chatbot_id + '"]');

                $(".ai-botkit-step-content").hide();
                $(`.ai-botkit-step-content[data-step="5"]`).show();
                $('.ai-botkit-tab').removeClass("active");
                $('.ai-botkit-tab[data-step="5"]').addClass("active");
                $('.ai-botkit-tab[data-step="5"]').removeClass("hidden");

                
                
            },
            error: function() {
                $("#ai-botkit-save-btn").html('Save');
                $(".ai-botkit-save-chatbot-status").html(ai_botkitAdmin.i18n.errorChatbotSaved);
                $(".ai-botkit-save-chatbot-status").addClass('error');
                
                // Re-enable required attributes if there was an error
                $('#ai-botkit-chatbot-form input[required]').prop('required', true);
            },
            complete: function() {
                $("#ai-botkit-save-btn").html('Save');
            }
        });
        
    });

    // Avatar Upload Handler
    $(".ai-botkit-upload-bot-icon").on("change", function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const type = $(this).data('type');

        const formData = new FormData();
        formData.append('action', 'ai_botkit_upload_avatar');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('avatar', file);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $("#ai-botkit-loading-" + type).removeClass("hidden");
                $("#ai-botkit-" + type + "-label").hide();
            },
            success: function(response) {
                if (response.success) {
                    $(".ai-botkit-" + type + "-icon-preview").removeClass("hidden");
                    $(".ai-botkit-" + type + "-icon-preview img").attr("src", response.data.url);
                    $(".ai-botkit-bot-" + type + "-icon").removeClass("active");
                    $(".ai-botkit-" + type + "-icon-preview").addClass("active");
                    $("#ai-botkit-remove-" + type).removeClass("hidden");
                    $("#ai-botkit-" + type + "-value").val(response.data.url);
                    if(type === "avatar") {
                        $('.ai-botkit-chat-avatar img').attr('src', response.data.url);
                    } else {
                        $('.ai-botkit-chat-bubble img').attr('src', response.data.url);
                    }
                    AiBotkitToast.success('Avatar uploaded successfully!');
                } else {
                    AiBotkitToast.error(response.data.message || 'Failed to upload avatar');
                }
            },
            error: function() {
                AiBotkitToast.error('Error uploading avatar');
                $("#ai-botkit-" + type + "-label").show();
            },
            complete: function() {
                $("#ai-botkit-loading-" + type).addClass("hidden");
            }
        });
    });

    // Remove Avatar Handler
    $(".ai-botkit-remove-bot-icon").on("click", function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        $(".ai-botkit-" + type + "-icon-preview").addClass("hidden");
        // get first icon and set it as active
        $(".ai-botkit-bot-" + type + "-icon").first().addClass("active");
        $("#ai-botkit-" + type + "-label").show();
        $(this).addClass("hidden");
        $("input[name='" + type + "']").remove();
        if(type === "avatar") {
            const avatar = $(".ai-botkit-bot-" + type + "-icon").first().data('icon');
            $("#ai-botkit-avatar-value").val(avatar);
            $('.ai-botkit-chat-avatar img').attr('src', avatar);
        } else {
            const widget = $(".ai-botkit-bot-" + type + "-icon").first().data('icon');
            $("#ai-botkit-widget-value").val(widget);
            $('.ai-botkit-chat-bubble img').attr('src', widget);
        }
    });

    // Background Image Upload Handler
    // Avatar Upload Handler
    $("#chat_bg_image_placeholder").on("change", function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'ai_botkit_upload_background_image');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('background_image', file);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $("#ai-botkit-loading-image").removeClass("hidden");
            },
            success: function(response) {
                if (response.success) {
                    $(".ai-botkit-background-image-preview").show();
                    $(".ai-botkit-background-image-preview img").attr("src", response.data.url);

                    $("<input>").attr({
                        type: 'hidden',
                        name: 'background_image',
                        value: response.data.url
                    }).appendTo("#ai-botkit-chatbot-form");
                    $('.ai-botkit-chat-body').css('background-image', 'url(' + response.data.url + ')');
                    $('.ai-botkit-chat-body').css('background-size', 'cover');
                    $('.ai-botkit-chat-body').css('background-position', 'center');
                    $('.ai-botkit-chat-body').css('background-repeat', 'no-repeat');
                } else {
                    AiBotkitToast.error(response.data.message || 'Failed to upload background image');
                }
            },
            error: function() {
                AiBotkitToast.error('Error uploading background image');
            },
            complete: function() {
                $("#ai-botkit-remove-image").css('display', 'flex');
                $("#ai-botkit-loading-image").addClass("hidden");
            }
        });
    });

    $("#ai-botkit-remove-image").on("click", function(e) {
        e.preventDefault();
        $(".ai-botkit-background-image-preview").hide();
        $(".ai-botkit-background-image-remove").css('display', 'none');
        $("input[name='background_image']").remove();
        $('.ai-botkit-chat-body').css('background-image', 'none');
    });

    // Remove Avatar Handler
    $("#ai-botkit-remove-avatar").on("click", function(e) {
        e.preventDefault();
        $("#ai-botkit-avatar-preview").show();
        $("#ai-botkit-avatar-image").addClass("hidden");
        $("#ai-botkit-avatar-image img").attr("src", "");
        $("input[name='avatar']").remove();
        $("#ai-botkit-avatar-input").val("");
    });

    // update changes in preview
    $("#chatbot_name").on("change", function() {
        const name = $(this).val();
        $("#ai-botkit-bot-name").text(name);
    });

    $(".ai-botkit-appearance-radio-group label").on("click", function() {
        const location = $(this).find('input').val();
        if(location === "bottom-left") {
            $('.ai-botkit-chat-widget').addClass("bottom-left");
            $('.ai-botkit-chat-bubble').addClass("bottom-left");
            $('.ai-botkit-chat-widget').removeClass("bottom-right");
            $('.ai-botkit-chat-bubble').removeClass("bottom-right");
        } else {
            $('.ai-botkit-chat-widget').removeClass("bottom-left");
            $('.ai-botkit-chat-bubble').removeClass("bottom-left");
            $('.ai-botkit-chat-widget').addClass("bottom-right");
            $('.ai-botkit-chat-bubble').addClass("bottom-right");
        }
    });

    $("#chatbot_greeting").on("input", function() {
        const greeting = $(this).val();
        $(".ai-botkit-chat-msg p").text(greeting);
    });

    $(".ai-botkit-delete-bot").on("click", function(e) {
        e.preventDefault();
        const chatbotId = $(this).data("chatbot-id");
        $("#ai-botkit-confirm-delete-chatbot-modal").fadeIn();
        $("#ai-botkit-confirm-delete-chatbot").data("chatbot-id", chatbotId);
    });

    $("#ai-botkit-cancel-delete-chatbot").on("click", function(e) {
        e.preventDefault();
        $("#ai-botkit-confirm-delete-chatbot-modal").fadeOut();
        $("#ai-botkit-confirm-delete-chatbot").data("chatbot-id", null);
    });

    $("#ai-botkit-confirm-delete-chatbot").on("click", function(e) {
        e.preventDefault();
        const chatbotId = $(this).data("chatbot-id");
        
        const formData = new FormData();
        formData.append('action', 'ai_botkit_delete_chatbot');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('chatbot_id', chatbotId);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    AiBotkitToast.success('Chatbot deleted successfully!');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    AiBotkitToast.error(response.data.message || 'Failed to delete chatbot');
                }
            },
            error: function() {
                AiBotkitToast.error('Error deleting chatbot');
            }
        });
    });

    $(".ai-botkit-edit-bot").on("click", function(e) {
        e.preventDefault();
        const chatbotId = $(this).data("chatbot-id");
        
        const formData = new FormData();
        formData.append('action', 'ai_botkit_get_chatbot');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('chatbot_id', chatbotId);
        // $(this).prop('disabled', true);
        $(this).html('<i style="font-size: 0.875rem;" class="ti ti-loader-2 ai-botkit-loading-icon"></i>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('.ai-botkit-wizard-container').show();
                $('.ai-botkit-dashboard-wrapper').hide();
                $('.ai-botkit-progress-wrapper').hide();
                $('.ai-botkit-sidebar-wrapper').hide();
                $('#ai-botkit-save-btn').removeAttr('disabled');
                $(".ai-botkit-edit-bot").html('<i class="ti ti-edit"></i>');
                loadAvailableDocuments(chatbotId);

                response.data.model_config = JSON.parse(response.data.model_config);
                response.data.style = JSON.parse(response.data.style);
                response.data.messages_template = JSON.parse(response.data.messages_template);

                // remove hidden from 6th step
                $('.ai-botkit-tab[data-step="5"]').removeClass('hidden');

                $('#ai-botkit-chatbot-id').val(response.data.id);
                $('#chatbot_name').val(response.data.name);
                $('.ai-botkit-chatbot-wizard-title').text(response.data.name);
                $('#chatbot_active').prop('checked', response.data.active == 1);
                $('#chatbot_active').parent().parent().hide();
                $('#chatbot_active_publish').prop('checked', response.data.active == 1);
                if( response.data.active == 1 ) {
                    $('.ai-bot-kit-show-if-publish').show();
                } else {
                    $('.ai-bot-kit-show-if-publish').hide();
                }
                $('#saved_chatbot_id').val(response.data.id);
                if( response.data.id == ai_botkitAdmin.site_wide_chatbot_id ) {
                    $('#chatbot_active_sitewide').prop('checked', true);
                    $('#ai-botkit-chatbot-wizard-sitewide').show();
                } else {
                    $('#chatbot_active_sitewide').prop('checked', false); 
                    $('#ai-botkit-chatbot-wizard-sitewide').hide();
                }
                $('#chatbot_active_widget').val('[ai-botkit-widget id="' + response.data.id + '"]');
                $('#chatbot_active_shortcode').val('[ai-botkit-chat id="' + response.data.id + '"]');
                $('#ai-botkit-chatbot-wizard-status').text(response.data.active == 1 ? 'Active' : 'Inactive');
                $('#ai-botkit-chatbot-wizard-status').addClass(response.data.active == 1 ? 'ai-botkit-status-active' : 'ai-botkit-status-inactive');
                $('#chatbot_feedback').prop('checked', response.data.feedback == 1);
                // select tone radio button
                // uncheck all tone radio buttons
                $('.ai-botkit-tone-radio').prop('checked', false);
                $(`input[name="tone"][value="${response.data.model_config.tone}"]`).prop('checked', true);
                $("#ai-botkit-avatar-image img").attr("src", response.data.avatar);
                $("<input>").attr({
                    type: 'hidden',
                    name: 'avatar',
                    value: response.data.avatar_id
                }).appendTo("#ai-botkit-chatbot-form");
                if(response.data.avatar) {
                    $('#ai-botkit-avatar-image').show();
                } else {
                    $('#ai-botkit-avatar-image').hide();
                }

                $('#chatbot_personality').val(response.data.messages_template.personality);
                $('#chatbot_greeting').val(response.data.messages_template.greeting);
                $('#chatbot_fallback').val(response.data.messages_template.fallback);

                // select model radio button
                $('.ai-botkit-model-radio').prop('checked', false);
                // remove selected option
                $('#ai_botkit_chat_model').find('option').removeAttr('selected');
                $('#ai_botkit_engine').find('option').removeAttr('selected');
                const engines = ai_botkitAdmin.engines;
                // Update chat models
                const $chatModelSelect = $('#ai_botkit_chat_model');
                $chatModelSelect.empty();
                Object.entries(engines[response.data.model_config.engine].chat_models).forEach(([id, name]) => {
                    $chatModelSelect.append($('<option>', {
                        value: id,
                        text: name
                    }));
                });
                $('#ai_botkit_engine').val(response.data.model_config.engine);
                $('#ai_botkit_chat_model').val(response.data.model_config.model);
                $('#context_length').val(response.data.model_config.context_length);
                $('#max_tokens').val(response.data.model_config.max_tokens);

                // styles
                $('#primary_color').val(response.data.style.primary_color);
                if($('.ai-botkit-color-circle[data-color="' + response.data.style.primary_color + '"]').length > 0) {
                    $('.ai-botkit-color-circle[data-color="' + response.data.style.primary_color + '"]').addClass('active');
                } else {
                    $(".ai-botkit-color-picker-icon").hide();
                    $(".ai-botkit-color-circle").removeClass("selected");
                    $("#ai-botkit-color-preview").css("background-color", response.data.style.primary_color);
                    $(".ai-botkit-color-preview").addClass("selected");
                    $("#primary_color").val(response.data.style.primary_color);
                    $("#ai-botkit-color-picker-value").text(response.data.style.primary_color);
                    $("#ai-botkit-color-picker-value").show();
              
                    // preview
                    // $(".bot-msg").css("background", response.data.style.primary_color);
                    $(".ai-botkit-chat-form button").css("background", response.data.style.primary_color);
                    $(".ai-botkit-chat-bubble").css("background", response.data.style.primary_color);
                }
                if(response.data.style.enable_gradient == 1) {
                    $('#enable_gradient').prop('checked', true);
                    $('.gradient-color-container').css('display', 'flex');
                    // update the preview
                    $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + response.data.style.gradient_color_1 + ', ' + response.data.style.gradient_color_2 + ')');
                    $(".ai-botkit-chat-form button").css("background", 'linear-gradient(to right, ' + response.data.style.gradient_color_1 + ', ' + response.data.style.gradient_color_2 + ')');
                    $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(to right, ' + response.data.style.gradient_color_1 + ', ' + response.data.style.gradient_color_2 + ')');
                    $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(to right, ' + response.data.style.gradient_color_1 + ', ' + response.data.style.gradient_color_2 + ')');
                } else {
                    $('#enable_gradient').prop('checked', false);
                    $('.gradient-color-container').css('display', 'none');
                    $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + response.data.style.primary_color + ', ' + response.data.style.primary_color + ')');
                    $(".ai-botkit-chat-form button").css("background", 'linear-gradient(to right, ' + response.data.style.primary_color + ', ' + response.data.style.primary_color + ')');
                    $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + response.data.style.primary_color + ' 0%, ' + response.data.style.primary_color + ' 100%)');
                    $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(180deg, ' + response.data.style.primary_color + ' 0%, ' + response.data.style.primary_color + ' 100%)');
                }

                // updateColorCircle('#primary_color', response.data.style.primary_color);
                updateColorCircle('#gradient_color_1', response.data.style.gradient_color_1);
                updateColorCircle('#gradient_color_2', response.data.style.gradient_color_2);
                updateColorCircle('#header_bg_color', response.data.style.header_bg_color);
                updateColorCircle('#header_font_color', response.data.style.header_color);
                updateColorCircle('#header_icon_color', response.data.style.header_icon_color);
                updateColorCircle('#chat_bg_color', response.data.style.body_bg_color);
                updateColorCircle('#ai_msg_bg_color', response.data.style.ai_msg_bg_color);
                updateColorCircle('#ai_msg_font_color', response.data.style.ai_msg_font_color);
                updateColorCircle('#user_msg_bg_color', response.data.style.user_msg_bg_color);
                updateColorCircle('#user_msg_font_color', response.data.style.user_msg_font_color);
                updateColorCircle('#initiate_msg_bg_color', response.data.style.initiate_msg_bg_color);
                updateColorCircle('#initiate_msg_border_color', response.data.style.initiate_msg_border_color);
                updateColorCircle('#initiate_msg_font_color', response.data.style.initiate_msg_font_color);
                
                $('#font_family').val(response.data.style.font_family);
                $('#font_size').val(response.data.style.font_size);

                $('#chat_width').val(response.data.style.width);
                $('#chat_max_height').val(response.data.style.max_height);
                $('#bubble_height').val(response.data.style.bubble_height);
                $('#bubble_width').val(response.data.style.bubble_width);

                // bot avatar
                if($('.ai-botkit-bot-avatar-icon[data-icon="' + response.data.style.avatar + '"]').length > 0) {
                    $('.ai-botkit-bot-avatar-icon').removeClass('active');
                    $('.ai-botkit-bot-avatar-icon[data-icon="' + response.data.style.avatar + '"]').addClass('active');
                } else {
                    $(".ai-botkit-avatar-icon-preview").removeClass("hidden");
                    $(".ai-botkit-avatar-icon-preview img").attr("src", response.data.style.avatar);
                    $(".ai-botkit-bot-avatar-icon").removeClass("active");
                    $(".ai-botkit-avatar-icon-preview").addClass("active");
                    $("#ai-botkit-remove-avatar").removeClass("hidden");
                    $("#ai-botkit-avatar-value").val(response.data.style.avatar);
                    $('#ai-botkit-avatar-label').hide();
                }
                $('.ai-botkit-chat-avatar img').attr('src', response.data.style.avatar);

                // widget icon
                if($('.ai-botkit-bot-widget-icon[data-icon="' + response.data.style.widget + '"]').length > 0) {
                    $('.ai-botkit-bot-widget-icon').removeClass('active');
                    $('.ai-botkit-bot-widget-icon[data-icon="' + response.data.style.widget + '"]').addClass('active');
                } else {
                    $(".ai-botkit-widget-icon-preview").removeClass("hidden");
                    $(".ai-botkit-widget-icon-preview img").attr("src", response.data.style.widget);
                    $(".ai-botkit-bot-widget-icon").removeClass("active");
                    $(".ai-botkit-widget-icon-preview").addClass("active");
                    $("#ai-botkit-remove-widget").removeClass("hidden");
                    $("#ai-botkit-widget-value").val(response.data.style.widget);
                    $('#ai-botkit-widget-label').hide();
                }
                $('.ai-botkit-chat-bubble img').attr('src', response.data.style.widget);

                // background image
                if(response.data.style.background_image) {
                    $(".ai-botkit-background-image-preview").show();
                    $(".ai-botkit-background-image-preview img").attr("src", response.data.style.background_image);

                    $("<input>").attr({
                        type: 'hidden',
                        name: 'background_image',
                        value: response.data.style.background_image
                    }).appendTo("#ai-botkit-chatbot-form");
                    $('.ai-botkit-chat-body').css('background-image', 'url(' + response.data.style.background_image + ')');
                    $('.ai-botkit-chat-body').css('background-size', 'cover');
                    $('.ai-botkit-chat-body').css('background-position', 'center');
                    $('.ai-botkit-chat-body').css('background-repeat', 'no-repeat');
                    $("#ai-botkit-remove-image").css('display', 'flex');
                } else {
                    $(".ai-botkit-background-image-preview").hide();
                }

                // theme
                $("#chatbot_theme").val(response.data.style.theme);
                $(".ai-botkit-bot-theme-item").removeClass("active");
                $(`.ai-botkit-bot-theme-item[data-theme="${response.data.style.theme}"]`).addClass("active");
                if(response.data.style.theme === "theme-2" || response.data.style.theme === "theme-4" ) {
                    if(response.data.style.background_image) {
                        const image = response.data.style.background_image;
                    } else {
                        const image = $(`.ai-botkit-bot-theme-item[data-theme="${response.data.style.theme}"] img`).attr('src');
                    }
                    $('.ai-botkit-chat-body').css('background-image', 'url(' + image + ')');
                    $('.ai-botkit-chat-body').css('background-size', 'cover');
                    $('.ai-botkit-chat-body').css('background-position', 'center');
                    $('.ai-botkit-chat-body').css('background-repeat', 'no-repeat');
                } else {
                    $('.ai-botkit-chat-body').css('background-image', 'none');
                }
                if(response.data.style.theme === "theme-3" || response.data.style.theme === "theme-4") {
                    const color = response.data.style.primary_color;
                    $('.ai-botkit-chat-header').css('background-color', color);
                } else {
                    $('.ai-botkit-chat-header').css('background-color', '#ffffff');
                }

                // select location radio button
                $('.ai-botkit-location-radio').prop('checked', false);
                $(`input[name="location"][value="${response.data.location}"]`).prop('checked', true);

                // select tone radio button
                $('.ai-botkit-tone-radio').prop('checked', false);
                $(`input[name="tone"][value="${response.data.tone}"]`).prop('checked', true);

                // chatbot preview
                $('#ai-botkit-bot-name').text(response.data.name);
                $('.ai-botkit-chat-msg p').text(response.data.greeting);
                
                if(response.data.location === "bottom-left") {
                    $('.ai-botkit-chat-widget').addClass("bottom-left");
                    $('.ai-botkit-chat-bubble').addClass("bottom-left");
                    $('.ai-botkit-chat-widget').removeClass("bottom-right");
                    $('.ai-botkit-chat-bubble').removeClass("bottom-right");
                } else {
                    $('.ai-botkit-chat-widget').addClass("bottom-right");
                    $('.ai-botkit-chat-bubble').addClass("bottom-right");
                    $('.ai-botkit-chat-widget').removeClass("bottom-left");
                    $('.ai-botkit-chat-bubble').removeClass("bottom-left");
                }
                // show knowledge base tab
                $(".ai-botkit-training-tab[data-tab='knowledge']").click();
            },
            error: function() {
                AiBotkitToast.error('Error getting chatbot');
            }
        });
    });
     
    function updateColorCircle(item, color){
        const $picker = $('.ai-botkit-color-picker[data-target="' + item + '"]');
        // Update the preview color circle
        $picker.siblings(".ai-botkit-color-circle").css("background-color", color);
    
        // Update the displayed hex value
        $picker.closest(".ai-botkit-gradient-color-preview").find(".ai-botkit-color-picker-value").text(color);
    
        // Update the corresponding hidden input
        const target = $picker.data("target");
        if (target) $(target).val(color);

        // update the preview
        const targetElement = $(target).data('target');
        const key = $(target).data('key');
        
        if(key === 'background-gradient') {

        } else {
            const newtargetElement = $(`${targetElement}`);
            // if target is a single element, update the style
            if (newtargetElement.length === 1) {
                newtargetElement.css(key, color);
            } else {
                // if target is a collection of elements, update the style for each element
                newtargetElement.each(function() {
                    $(this).css(key, color);
                });
            }
        }
    }

    // Settings page
    $(".ai-botkit-tab").click(function () {
        var tab = $(this).data("tab");
        // Temporarily disable required attributes on form fields to allow tab switching
        $('#ai-botkit-chatbot-form input[required]').prop('required', false);
    
        $(".ai-botkit-tab").removeClass("active");
        $(this).addClass("active");
        $(".ai-botkit-step-content").removeClass("active");
        $(`.ai-botkit-step-content[data-step="${tab}"]`).addClass("active");
        
        // Re-enable required attributes after tab switch
        setTimeout(function() {
            $('#ai-botkit-chatbot-form input[required]').prop('required', true);
        }, 100);
        
        updateProgress();
    });

    // Pinecone toggle functionality
    $('#ai_botkit_enable_pinecone').on('change', function() {
        const pineconeSettings = $('#pinecone-settings');
        if ($(this).is(':checked')) {
            pineconeSettings.show();
        } else {
            pineconeSettings.hide();
        }
    });

       // Engine selection handler
    $('#ai_botkit_engine').on('change', function() {
        const selectedEngine = $(this).val();
        const engines = ai_botkitAdmin.engines;
        
        // Show/hide API key fields
        $('.engine-settings').hide();
        $('.engine-' + selectedEngine).show();
        
        // Update chat models
        const $chatModelSelect = $('#ai_botkit_chat_model');
        $chatModelSelect.empty();
        Object.entries(engines[selectedEngine].chat_models).forEach(([id, name]) => {
            $chatModelSelect.append($('<option>', {
                value: id,
                text: name
            }));
        });
        
        // Update embedding models
        const $embeddingModelSelect = $('#ai_botkit_embedding_model');
        $embeddingModelSelect.empty();
        Object.entries(engines[selectedEngine].embedding_models).forEach(([id, name]) => {
            $embeddingModelSelect.append($('<option>', {
                value: id,
                text: name
            }));
        });
    });

    // API test handler
    $('.ai_botkit_test_api').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $spinner = $(this).parent().parent().find('.spinner');
        const $status = $(this).parent().parent().find('.ai-botkit-api-test-result');
        const provider = $('#ai_botkit_engine').val();
        const apiKey = $(`#ai_botkit_${provider}_api_key`).val();

        if (!apiKey) {
            $status.removeClass('success').addClass('error')
                .html(ai_botkitAdmin.i18n.noApiKey || 'API key is required');
            return;
        }

        $button.prop('disabled', true);
        $spinner.show();
        $status.show();
        $status.removeClass('success error').html(ai_botkitAdmin.i18n.processing || 'Testing...');
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_botkit_test_api_connection',
                nonce: ai_botkitAdmin.nonce,
                provider: provider,
                api_key: apiKey
            },
            beforeSend: function() {
                $status.html(ai_botkitAdmin.i18n.processing);
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('error').addClass('success')
                        .html(response.data.message);
                } else {
                    $status.removeClass('success').addClass('error')
                        .html(response.data.message);
                }
            },
            error: function() {
                $status.removeClass('success').addClass('error')
                    .html(ai_botkitAdmin.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                $spinner.hide();
            }
        });
    });
    
    let deleteTargetId = null;
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + " B";
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
        else return (bytes / 1048576).toFixed(1) + " MB";
    }
    
    function renderTable(items) {
        const tbody = $("#ai-botkit-table-body");
        tbody.empty();
    
        if (items.length === 0) {
        $("#ai-botkit-table-empty").show();
        return;
        } else {
        $("#ai-botkit-table-empty").hide();
        }
    
        items.forEach(item => {
        const tags = item.tags.length
            ? item.tags.map(tag => `<span class="ai-botkit-badge">${tag}</span>`).join(" ")
            : '<span style="color:#9ca3af; font-size:0.75rem;">No tags</span>';
    
        const usedBy = item.usedBy.length
            ? `<span class="ai-botkit-badge">${item.usedBy.length} bots</span>`
            : '<span style="color:#9ca3af; font-size:0.75rem;">Not used</span>';
    
        const sizeOrUrl = item.type === "pdf"
            ? formatFileSize(item.size)
            : `<a href="${item.url}" target="_blank">${item.url}</a>`;
    
        const typeIcon = item.type === "pdf"
            ? `<svg class="ai-botkit-icon-small" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h8m0-18v4a2 2 0 002 2h4m-6 14h6a2 2 0 002-2v-7l-6-6z"/></svg> PDF`
            : `<svg class="ai-botkit-icon-small" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 007.446 4.03l3.614-3.614a5 5 0 10-7.07-7.07L11.586 9M14 11h.01"/></svg> URL`;
    
        tbody.append(`
            <tr>
            <td>${item.name}</td>
            <td>${typeIcon}</td>
            <td>${formatDate(item.dateAdded)}</td>
            <td>${sizeOrUrl}</td>
            <td>${tags}</td>
            <td>${usedBy}</td>
            <td>
                <button class="ai-botkit-btn-outline ai-botkit-delete-btn" data-id="${item.id}">
                <svg class="ai-botkit-icon-small" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a2 2 0 012 2v2H7V5a2 2 0 012-2z"/></svg>
                </button>
            </td>
            </tr>
        `);
        });
    }
    
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    
    
    // Delete button click
    $(document).on("click", ".ai-botkit-delete-btn", function (e) {
        e.preventDefault();
        deleteTargetId = $(this).data("id");
        $("#ai-botkit-confirm-delete-modal").fadeIn();
    });
    
    $("#ai-botkit-cancel-delete").click(function () {
        $("#ai-botkit-confirm-delete-modal").fadeOut();
    });
    
    $("#ai-botkit-confirm-delete").click(function (e) {
        e.preventDefault();
        const button = $(this);
        if (deleteTargetId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_botkit_delete_document',
                nonce: ai_botkitAdmin.nonce,
                document_id: deleteTargetId
            },
            success: function(response) {
                if (response.success) {
                    $('button[data-id="' + deleteTargetId + '"]').closest('tr').remove();
                }
            },
            error: function() {
                AiBotkitToast.error('Error deleting document');
            }
        });
        
        $("#ai-botkit-confirm-delete-modal").fadeOut();
        }
    });
      
    // Knowledge Base
    $('#ai-botkit-add-document').click(function(e) {
		e.preventDefault();
		$('#ai-botkit-upload-modal').show();
	});

	$('.ai-botkit-modal-close').click(function() {
		$('#ai-botkit-upload-modal').hide();
	});
    // Open Modal
    $("#ai-botkit-add-url-btn").click(function () {
        $("#ai-botkit-add-url-modal").fadeIn();
    });

    // Close Modal
    $("#ai-botkit-cancel-url-btn").click(function () {
        $("#ai-botkit-add-url-modal").fadeOut();
    });

    // Open Modal
    $("#ai-botkit-wordpress-btn").click(function () {
        $("#ai-botkit-wordpress-modal").fadeIn();
    });

    // Close Modal
    $("#ai-botkit-cancel-wordpress").click(function () {
        $("#ai-botkit-wordpress-modal").fadeOut();
    });

    // Select All
    $("#ai-botkit-select-all").click(function () {
        $("#ai-botkit-content-types input[type='checkbox']").prop("checked", true);
    });

    // Deselect All
    $("#ai-botkit-deselect-all").click(function () {
        $("#ai-botkit-content-types input[type='checkbox']").prop("checked", false);
    });

    let selectedFiles = [];

    // Open Modal
    $("#ai-botkit-upload-btn").click(function () {
        $("#ai-botkit-upload-modal").fadeIn();
    });

    // Cancel Upload
    $("#ai-botkit-cancel-upload").click(function () {
        resetUploadForm();
        $("#ai-botkit-upload-modal").fadeOut();
    });

    // Handle File Selection
  $("#ai-botkit-file-upload").change(function (e) {
    const files = Array.from(e.target.files);

    selectedFiles = selectedFiles.concat(files);
    renderSelectedFiles();
  });

  // Remove File
  $(document).on("click", ".ai-botkit-remove-file", function () {
    const index = $(this).data("index");
    selectedFiles.splice(index, 1);
    renderSelectedFiles();
  });

  // Handle Upload
  $("#ai-botkit-submit-upload").on("change", function (e) {
    e.preventDefault();
    const files = Array.from(e.target.files);

    const formData = new FormData();
    formData.append('action', 'ai_botkit_upload_file');
    formData.append('nonce', ai_botkitAdmin.nonce);
    formData.append('file', files[0]);

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function() {
            $('#ai-botkit-document-uploading').removeClass('hidden');
            $('#ai-botkit-document-upload-box').addClass('hidden');
        },
        success: function(response) {
            if (response.success) {
                // Upload files to server
                $('#ai-botkit-document-uploaded').removeClass('hidden');
                $('#ai-botkit-document-uploading').addClass('hidden');
                $('#ai-botkit-document-upload-box').addClass('hidden');
                window.location.reload();
            } else {
                $('#ai-botkit-document-uploading').addClass('error');
                $('#ai-botkit-document-uploading').removeClass('hidden');
                $('#ai-botkit-document-uploading').html('<p>'+response.data.message+'</p>');
            }
        },
        error: function() {
            $('#ai-botkit-document-uploading').addClass('error');
            $('#ai-botkit-document-uploading').removeClass('hidden');
            $('#ai-botkit-document-uploading').html('<p>Error uploading file</p>');
        },
        complete: function() {
            $('#ai-botkit-document-uploading').addClass('hidden');
            $('#ai-botkit-document-uploading').removeClass('error');
            $('#ai-botkit-document-uploading').html(loadingHtml);
            $('#ai-botkit-document-upload-box').addClass('hidden');
            setTimeout(function() {
                $("#ai-botkit-upload-modal").fadeOut();
                window.location.reload();
            }, 2000);
        }
    });
  });

  function renderSelectedFiles() {
    const list = $("#ai-botkit-files-list");
    list.empty();

    if (selectedFiles.length === 0) {
      $("#ai-botkit-selected-files").hide();
      return;
    }

    $("#ai-botkit-selected-files").show();

    selectedFiles.forEach((file, index) => {
      list.append(`
        <div class="ai-botkit-selected-file">
          <div class="ai-botkit-selected-file-box">
            <i class="ti ti-file-text" style="font-size: 1.5rem;"></i>
            <span class="text-sm truncate">${file.name}</span>
          </div>
          <button class="ai-botkit-remove-file" data-index="${index}"><i class="ti ti-trash"></i></button>
        </div>
      `);
    });
  }

  function resetUploadForm() {
    selectedFiles = [];
    $("#ai-botkit-tags").val("");
    $("#ai-botkit-selected-files").hide();
    $("#ai-botkit-file-upload").val("");
  }

    $("#ai-botkit-submit-url-btn").click(function () {
        const $input = $("#ai-botkit-url");
        const url = $input.val().trim();

        const formData = new FormData();
        formData.append('action', 'ai_botkit_import_url');
        formData.append('nonce', ai_botkitAdmin.nonce);
        formData.append('url', url);
        formData.append('title', $('#ai-botkit-title').val());
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $(this).prop('disabled', true);
                $(this).html('Adding...');
            },
            success: function(response) {
                if (response.success) {
                    $input.val("");
                    $('#ai-botkit-title').val("");
                    window.location.reload();
                } else {
                    $(this).prop('disabled', false);
                    $(this).html('Add URL');
                }
            },
            error: function() {
                $(this).prop('disabled', false);
                $(this).html('Add URL');
            },
            complete: function() {
                $(this).prop('disabled', false);
                $(this).html('Add URL');
                $("#ai-botkit-add-url-modal").fadeOut();
            }
        });
    });

    $("#ai-botkit-add-training-wordpress").on("click", function (e) {
        e.preventDefault();
        const selected = $('.ai-botkit-wp-data-import:checked')
        .map(function () {
            return $(this).val();
        })
        .get();

        const formData = new FormData();
        formData.append('action', 'ai_botkit_import_wp_content');
        formData.append('nonce', ai_botkitAdmin.nonce);
        selected.forEach(function (id) {
            formData.append('post_ids[]', id);
        });

        const button = $(this);
        const buttonHtml = button.html();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                button.html('<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>');
            },
            success: function(response) {
                if (response.success) {
                    selected.forEach(function (id) {
                        const itemName = $(`.ai-botkit-wp-data-import[value="${id}"]`).parent().parent().find('td:nth-child(2)').text();
                        const itemActions = `<span class="ai-botkit-remove-training-item"><i class="ti ti-trash"></i></span>`;
                        const itemHtml = `
                        <tr data-id="${id}" data-type="post">
                            <td>${itemName}</td>
                            <td>Post</td>
                            <td><span class="ai-botkit-badge ai-botkit-badge-info">Processing</span></td>
                            <td>${itemActions}</td>
                        </tr>
                        `;
                        $(`.ai-botkit-training-data-post tbody`).append(itemHtml);
                        $('.ai-botkit-training-data-post').css('display', 'flex');
                        $('.ai-botkit-no-training-docs').hide();
                    });

                    var imports = $('#ai-botkit-imports').val();
                    imports = imports ? JSON.parse(imports) : []; // if empty, set as []
                    response.data.document_ids.forEach(function (id) {
                        imports.push(id);
                    });
                    $('#ai-botkit-imports').val(JSON.stringify(imports));
                } else {
                    $('#ai-botkit-wp-error-message').html(response.data.message);
                    $('#ai-botkit-wp-error-message').show();
                }
            },
            error: function() {
                $('#ai-botkit-wp-error-message').html('Error importing content');
                $('#ai-botkit-wp-error-message').show();
            },
            complete: function() {
                button.html(buttonHtml);
                setTimeout(function() {
                    $('#ai-botkit-wp-error-message').removeClass('show');
                    $('#ai-botkit-wp-error-message').html('');
                    $('#ai-botkit-add-training-wordpress-modal').fadeOut();
                }, 2000);
            }
        });
        
        
        
    });

    $('#ai_botkit_analytics_time_range').on('change', function() {
        // submit the form
        $('#ai_botkit_analytics_form').submit();
    });

    // check if ai_botkitAnalytics is defined
    if (typeof ai_botkitAnalytics !== 'undefined') {
        // Analytics
        const { Chart } = window;
        
        const timeSeriesData = ai_botkitAnalytics.timeSeriesData;
        const performanceData = ai_botkitAnalytics.performanceData;

        // Common chart options
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 10
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'MMM d'
                        }
                    },
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        maxTicksLimit: 8
                    }
                }
            }
        };

        // Helper function to create charts with error handling
        function createChart(canvasId, config) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                console.error(`Canvas element ${canvasId} not found`);
                return;
            }
            
            try {
                return new Chart(canvas, config);
            } catch (error) {
                console.error(`Error creating chart ${canvasId}:`, error);
            }
        }

        // Usage Chart
        createChart('usageChart', {
            type: 'line',
            data: {
                labels: timeSeriesData.map(d => d.time_period),
                datasets: [{
                    label: ai_botkitAnalytics.i18n.totalEvents,
                    data: timeSeriesData.map(d => d.total_events),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34,113,177,0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Response Time Chart
        createChart('responseTimeChart', {
            type: 'line',
            data: {
                labels: performanceData.map(d => d.date),
                datasets: [{
                    label: ai_botkitAnalytics.i18n.avgResponseTime,
                    data: performanceData.map(d => d.avg_processing_time),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34,113,177,0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Error Rate Chart
        createChart('errorChart', {
            type: 'line',
            data: {
                labels: performanceData.map(d => d.date),
                datasets: [{
                    label: ai_botkitAnalytics.i18n.errorRate,
                    data: performanceData.map(d => (d.total_requests > 0 ? (d.error_count / d.total_requests) * 100 : 0)),
                    borderColor: '#d63638',
                    backgroundColor: 'rgba(214,54,56,0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Token Usage Chart
        createChart('tokenChart', {
            type: 'line',
            data: {
                labels: timeSeriesData.map(d => d.time_period),
                datasets: [{
                    label: ai_botkitAnalytics.i18n.tokenUsage,
                    data: timeSeriesData.map(d => d.total_tokens),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34,113,177,0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });
    }

    // Style Settings Handlers
    // document.addEventListener('DOMContentLoaded', function() {
        // Handle collapsible sections
        const styleHeaders = $('.ai-botkit-style-header');
        styleHeaders.on('click', function() {
            // check if a div inside header has class ai-botkit-wp-count-container
            const countContainer = $(this).find('.ai-botkit-wp-count-container');
            if (countContainer.length > 0) {
                if(! $(this).hasClass('collapsed')) {
                    $(this).parent().parent().find('.ai-botkit-style-section').hide();
                    $(this).parent().show();
                    // set width of the section to 100%
                    $(this).parent().css('width', '100%');
                    $('.ai-botkit-wp-header').hide();
                    $('.ai-botkit-wp-header-back').show();
                    $('#ai-botkit-import-wp').hide();
                    $('#ai-botkit-add-training-wordpress').hide();
                    $('.ai-botkit-wp-header-post-title').text($(this).data('type'));
                } else {
                    $(this).parent().parent().find('.ai-botkit-style-section').show();
                    const width = $(this).parent().siblings().width();
                    // set width of the section to 50%
                    $(this).parent().css('width', width + 'px');
                    $('.ai-botkit-wp-header').show();
                    $('.ai-botkit-wp-header-back').hide();
                    $('#ai-botkit-import-wp').show();
                    $('#ai-botkit-add-training-wordpress').show();
                }
                $(this).toggleClass('collapsed');
                $(this).next().toggleClass('collapsed');
            } else {
                $(this).toggleClass('collapsed');
                $(this).next().toggleClass('collapsed');
            }
        });

        $('.ai-botkit-wp-header-back').on('click', function(e) {
            e.preventDefault();
            $('.ai-botkit-wp-header').show();
            $('.ai-botkit-wp-header-back').hide();
            $('#ai-botkit-add-training-wordpress').show();
            $('#ai-botkit-import-wp').show();
            const hiddenSections = $('.ai-botkit-style-header.collapsed');
            hiddenSections.parent().parent().find('.ai-botkit-style-section').show();
            const width = hiddenSections.parent().siblings().width();
            // set width of the section to 50%
            hiddenSections.parent().css('width', width + 'px');
            hiddenSections.toggleClass('collapsed');
            hiddenSections.next().toggleClass('collapsed');
        });

        // Sync color inputs with text values
        const colorGroups = document.querySelectorAll('.ai-botkit-color-group');
        colorGroups.forEach(group => {
            const colorInput = group.querySelector('input[type="color"]');
            const textInput = group.querySelector('.ai-botkit-color-value');

            colorInput.addEventListener('input', () => {
                textInput.value = colorInput.value.toUpperCase();
            });

            textInput.addEventListener('input', () => {
                const value = textInput.value;
                if (/^#[0-9A-F]{6}$/i.test(value)) {
                    colorInput.value = value;
                }
            });

            textInput.addEventListener('blur', () => {
                textInput.value = colorInput.value.toUpperCase();
            });
        });

        // Save all style settings
        function getStyleSettings() {
            return {
                // General Settings
                primaryColor: document.getElementById('primary_color').value,
                fontFamily: document.getElementById('font_family').value,
                fontSize: document.getElementById('font_size').value,
                
                // Header Settings
                headerBgColor: document.getElementById('header_bg_color').value,
                headerFontColor: document.getElementById('header_font_color').value,
                
                // Popup ChatBox Settings
                chatWidth: document.getElementById('chat_width').value,
                chatMaxHeight: document.getElementById('chat_max_height').value,
                
                // Chat Window Settings
                chatBgColor: document.getElementById('chat_bg_color').value,
                aiMsgBgColor: document.getElementById('ai_msg_bg_color').value,
                aiMsgFontColor: document.getElementById('ai_msg_font_color').value,
                userMsgBgColor: document.getElementById('user_msg_bg_color').value,
                userMsgFontColor: document.getElementById('user_msg_font_color').value,
                
                // Chat Widget Bubble Settings
                bubbleHeight: document.getElementById('bubble_height').value,
                bubbleWidth: document.getElementById('bubble_width').value,
                
                // Position
                position: document.querySelector('input[name="location"]:checked').value
            };
        }

        // Add style settings to the form data before submission
        const form = document.getElementById('ai-botkit-chatbot-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const styleSettings = getStyleSettings();
                
                // Add style settings to form data
                Object.entries(styleSettings).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `style_settings[${key}]`;
                    input.value = value;
                    form.appendChild(input);
                });
            });
        }

        $('.ai-botkit-style-input').on('change', function() {
            const target = $(this).data('target');
            const key = $(this).data('key');
            let value = $(this).val();
            
            // find the target element and update in the preview
            const targetElement = $(`${target}`);
            // if target is a single element, update the style
            if (key === 'height' || key === 'width' || key === 'max-height' || key === 'font-size') {
                value = value + 'px';
            }
            if (targetElement.length === 1) {
                targetElement.css(key, value);
            } else {
                // if target is a collection of elements, update the style for each element
                targetElement.each(function() {
                    $(this).css(key, value);
                });
            }
        });
    // });

    // Training Tab Navigation
    $(".ai-botkit-training-tab").on("click", function () {
        const tab = $(this).data("tab");
        
        // Temporarily disable required attributes on form fields to allow tab switching
        $('#ai-botkit-chatbot-form input[required]').prop('required', false);
        
        $(".ai-botkit-training-tab").removeClass("active");
        $(this).addClass("active");
        $(".ai-botkit-training-tab-content").removeClass("active");
        $(`.ai-botkit-training-tab-content[data-tab="${tab}"]`).addClass("active");
        
        // Re-enable required attributes after tab switch
        setTimeout(function() {
            $('#ai-botkit-chatbot-form input[required]').prop('required', true);
        }, 100);
    });

    $('#ai-botkit-add-training-document-btn').click(function() {
        $('#ai-botkit-add-training-document-modal').fadeIn();
    });

    $('#ai-botkit-cancel-training-document-btn').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-document-modal').fadeOut();
        $('#ai-botkit-upload-modal').fadeOut();
    });

    $('#ai-botkit-add-training-url-btn').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-url-modal').fadeIn();
    });

    $('#ai-botkit-cancel-training-url-btn').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-url-modal').fadeOut();
        $('#ai-botkit-add-url-modal').fadeOut();
    });

    $('#ai-botkit-add-training-wordpress-btn').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-wordpress-modal').fadeIn();
    });

    $('#ai-botkit-cancel-training-wordpress-btn').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-wordpress-modal').fadeOut();
        $('#ai-botkit-wordpress-modal').fadeOut();
    });

    $('#ai-botkit-cancel-training-wordpress-modal').click(function(e) {
        e.preventDefault();
        $('#ai-botkit-add-training-wordpress-modal').fadeOut();
        $('#ai-botkit-wordpress-modal').fadeOut();
        // uncheck all checkboxes   
        $('.ai-botkit-wp-data-import').prop('checked', false);
    });

    $('.ai-botkit-wp-search').on('keyup', function() {
        const search = $(this).val().toLowerCase();
        // fimd nearest parent with class ai-botkit-wp-types-modal
        const modal = $(this).parent().find('.ai-botkit-kb-content-scroll');
        const posts = modal.find('tr');
        posts.each(function() {
            const post = $(this);
            const postName = post.find('td:nth-child(2)').text().toLowerCase();
            if ( postName.includes(search) ) {
                post.show();
            } else {
                post.hide();
            }
        });
    });

    $('.ai-botkit-wp-checkbox').on('click', function(e) {
        e.stopPropagation(); // Stops the event from bubbling up to parent
        // check all checkboxes in the modal
        if ( $(this).prop('checked') ) {
            $('input[data-type="' + $(this).attr('id') + '"]').prop('checked', true);
            const count = $('input[data-type="' + $(this).attr('id') + '"]').length;
            $('.ai-botkit-wp-count-number[data-type="' + $(this).attr('id') + '"]').text(count);
            $('.ai-botkit-wp-count-number[data-type="' + $(this).attr('id') + '"]').parent().show();
        } else {
            $('input[data-type="' + $(this).attr('id') + '"]').prop('checked', false);
            $('.ai-botkit-wp-count-number[data-type="' + $(this).attr('id') + '"]').text(0);
            $('.ai-botkit-wp-count-number[data-type="' + $(this).attr('id') + '"]').parent().hide();
        }
    });

    $('.ai-botkit-wp-data-import').on('change', function() {
        const type = $(this).data('type');
        let count = $('.ai-botkit-wp-count-number[data-type="' + type + '"]').text();
        if ( $(this).prop('checked') ) {
            count = parseInt(count) + 1;
        } else {
            count = parseInt(count) - 1;
        }
        $('.ai-botkit-wp-count-number[data-type="' + type + '"]').text(count);
        if( count === 0 ) {
            $('.ai-botkit-wp-count-number[data-type="' + type + '"]').parent().hide();
        } else {
            $('.ai-botkit-wp-count-number[data-type="' + type + '"]').parent().show();
        }
    });

    // bot avtar selection
    $('.ai-botkit-bot-avatar-icon').on('click', function() {
        const icon = $(this).data('icon');
        $('.ai-botkit-bot-avatar-icon').removeClass('active');
        $(this).addClass('active');
        $('#ai-botkit-avatar-value').val(icon);
        // update the preview
        $('.ai-botkit-chat-avatar img').attr('src', icon);
        if( $('#enable_gradient').prop('checked') ) {
            const color1 = $('#gradient_color_1').val();
            const color2 = $('#gradient_color_2').val();
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
        } else {
            const primaryColor = $('#primary_color').val();
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + primaryColor + ' 0%, ' + primaryColor + ' 100%)');
        }
    });

    // widget selection
    $('.ai-botkit-bot-widget-icon').on('click', function() {
        const icon = $(this).data('icon');
        $('.ai-botkit-bot-widget-icon').removeClass('active');
        $(this).addClass('active');
        $('#ai-botkit-widget-value').val(icon);
        // update the preview
        $('.ai-botkit-chat-bubble img').attr('src', icon);
        if( $('#enable_gradient').prop('checked') ) {
            const color1 = $('#gradient_color_1').val();
            const color2 = $('#gradient_color_2').val();
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
        } else {
            const primaryColor = $('#primary_color').val();
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + primaryColor + ' 0%, ' + primaryColor + ' 100%)');
        }
    });

    // gradient color selection
    $('#enable_gradient').on('change', function() {
        if ($(this).prop('checked')) {
            $('.gradient-color-container').css('display', 'flex');
            // update the preview
            const color1 = $('#gradient_color_1').val();
            const color2 = $('#gradient_color_2').val();
            $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $(".ai-botkit-chat-form button").css("background", 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
            $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color2 + ')');
        } else {
            $('.gradient-color-container').css('display', 'none');
            const primaryColor = $('#primary_color').val();
            $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + primaryColor + ', ' + primaryColor + ')');
            $(".ai-botkit-chat-form button").css("background", 'linear-gradient(to right, ' + primaryColor + ', ' + primaryColor + ')');
            $('.ai-botkit-chat-avatar').css('background', 'linear-gradient(180deg, ' + primaryColor + ' 0%, ' + primaryColor + ' 100%)');
            $('.ai-botkit-bot-avatar-icon-image').css('background', 'linear-gradient(180deg, ' + primaryColor + ' 0%, ' + primaryColor + ' 100%)');
        }
    });
    
    $('#gradient_color_1').on('change', function() {
        const color = $(this).val();
        const color2 = $('#gradient_color_2').val();
        $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + color + ', ' + color2 + ')');
    });

    $('#gradient_color_2').on('change', function() {
        const color = $(this).val();
        const color1 = $('#gradient_color_1').val();
        $('.ai-botkit-chat-bubble').css('background', 'linear-gradient(to right, ' + color1 + ', ' + color + ')');
    });
    // live preview
    $('#ai-botkit-close-saved-chatbot-modal').on('click', function() {
        $('#ai-botkit-saved-chatbot-modal').fadeOut();
    });

    $('#chatbot_active_sitewide').on('change', function() {
        const chatbotId = $('#saved_chatbot_id').val();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_botkit_enable_chatbot_sitewide',
                enable_chatbot_sitewide: $(this).prop('checked') ? 1 : 0,
                chatbot_id: chatbotId,
                nonce: ai_botkitAdmin.nonce
            },
            success: function(response) {
                $(this).prop('checked', response.success);
                if( response.success ) {
                    $('#ai-botkit-chatbot-wizard-sitewide').show();
                } else {
                    $('#ai-botkit-chatbot-wizard-sitewide').hide();
                }
            },
            error: function(error) {
                $(this).prop('checked', false);
            }
        });
    });

    $('#chatbot_active_publish').on('change', function() {
        const chatbotId = $('#saved_chatbot_id').val();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_botkit_enable_chatbot',
                enable_chatbot: $(this).prop('checked') ? 1 : 0,
                chatbot_id: chatbotId,
                nonce: ai_botkitAdmin.nonce
            },
            success: function(response) {
                $(this).prop('checked', response.success);
                if( response.success ) {
                    $('.ai-bot-kit-show-if-publish').show();
                    $('#ai-botkit-chatbot-wizard-status').text('Active');
                    $('#ai-botkit-chatbot-wizard-status').addClass('ai-botkit-status-active');
                    $('#ai-botkit-chatbot-wizard-status').removeClass('ai-botkit-status-inactive');

                } else {
                    $('.ai-bot-kit-show-if-publish').hide();
                    $('#ai-botkit-chatbot-wizard-status').text('Inactive');
                    $('#ai-botkit-chatbot-wizard-status').removeClass('ai-botkit-status-active');
                    $('#ai-botkit-chatbot-wizard-status').addClass('ai-botkit-status-inactive');
                }
            },
            error: function(error) {
                $(this).prop('checked', false);
            }
        });
    });

    $('#chatbot_active').on('change', function() {
        $('#chatbot_active_publish').prop('checked', $(this).prop('checked'));
        if( $(this).prop('checked') ) {
            $('.ai-bot-kit-show-if-publish').show();
            $('#ai-botkit-chatbot-wizard-status').text('Active');
            $('#ai-botkit-chatbot-wizard-status').addClass('ai-botkit-status-active');
            $('#ai-botkit-chatbot-wizard-status').removeClass('ai-botkit-status-inactive');
        } else {
            $('.ai-bot-kit-show-if-publish').hide();
            $('#ai-botkit-chatbot-wizard-status').text('Inactive');
            $('#ai-botkit-chatbot-wizard-status').removeClass('ai-botkit-status-active');
            $('#ai-botkit-chatbot-wizard-status').addClass('ai-botkit-status-inactive');
        }
    });
    
});