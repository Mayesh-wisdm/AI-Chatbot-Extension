jQuery(document).ready(function($) {
    // Chat instance configuration
    let chatConfig = {};
    let isProcessing = false;
    let currentConversationId = null;

    initChat(ai_botkitChat.chatId, ai_botkitChat.botID);

    // Initialize chat functionality
    function initChat(chatId, botID) {
        chatConfig = ai_botkitChat;
        currentConversationId = chatId;
        currentBotID = botID;
        chatConfig.isWidget = $(`#${chatId}`).data('widget') === 'true';

        const chatContainer = $(`#${chatId}`);
        
        // Initialize components
        setupAutoResize(chatContainer);
        bindEventListeners(chatContainer);
        // loadConversations();
    }

    // // Load existing conversations
    // function loadConversations() {
    //     if (!chatConfig.isWidget) {
    //         $.ajax({
    //             url: chatConfig.ajaxUrl,
    //             type: 'POST',
    //             data: {
    //                 action: 'ai_botkit_get_conversations',
    //                 _ajax_nonce: chatConfig.nonce
    //             },
    //             success: function(response) {
    //                 if (response.success) {
    //                     updateConversationList(response.data);
    //                 }
    //             }
    //         });
    //     }
    // }

    // Bind event listeners
    function bindEventListeners(chatContainer) {
        const form = chatContainer.find('.ai-botkit-chat');
        const input = form.find('#ai-botkit-chat-input');
        const sendButton = form.find('.ai-botkit-send-button');

        // Send message on form submit
        sendButton.on('click', function(e) {
            e.preventDefault();
            const message = input.val().trim();
            if (message && !isProcessing) {
                sendMessage(chatContainer, message);
            }
        });

        // Send message on Enter (but allow Shift+Enter for new lines)
        input.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const message = input.val().trim();
                if (message && !isProcessing) {
                    sendMessage(chatContainer, message);
                }
            }
        });

        // Clear chat functionality
        $('.ai-botkit-clear').on('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Clear Conversation?',
                    text: 'Are you sure you want to clear this conversation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, clear it!',
                    cancelButtonText: 'Cancel',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    backdrop: true,
                    customClass: {
                        popup: 'ai-botkit-swal-popup',
                        backdrop: 'ai-botkit-swal-backdrop'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        clearConversation(chatContainer);
                    }
                });
            } else {
                // Fallback to regular confirm if SweetAlert not available
                if (confirm('Are you sure you want to clear this conversation?')) {
                    clearConversation(chatContainer);
                }
            }
        });

        // Start new conversation
        chatContainer.find('.ai-botkit-new').on('click', function() {
            startNewConversation(chatContainer);
        });

        // Select conversation
        chatContainer.find('.ai-botkit-conversations').on('click', '.conversation', function() {
            loadConversation(chatContainer, $(this).data('id'));
        });
    }

    // Auto-resize textarea
    function setupAutoResize(chatContainer) {
        const textarea = chatContainer.find('.ai-botkit-input');
        textarea.on('input', function() {
            // this.style.height = 'auto';
            // this.style.height = (this.scrollHeight) + 'px';
        });
    }

    // Send message
    function sendMessage(chatContainer, message) {
        const input = chatContainer.find('.ai-botkit-input');
        const sendButton = chatContainer.find('.ai-botkit-send-button');
        
        // Disable input and button
        isProcessing = true;
        input.prop('disabled', true);
        sendButton.prop('disabled', true);
        
        // Append user message
        appendMessage(chatContainer, 'user', message);
        
        // Clear input
        input.val('').trigger('input');
        
        // Show typing indicator
        const typingTemplate = chatContainer.find('template[id$="-typing-template"]').html();
        chatContainer.find('.ai-botkit-chat-messages').append(typingTemplate);
        
        // Scroll to bottom
        scrollToBottom(chatContainer);

        // Send message to server
        $.ajax({
            url: chatConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_chat_message',
                message: message,
                conversation_id: currentConversationId,
                bot_id: currentBotID,
                nonce: chatConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove typing indicator
                    chatContainer.find('.ai-botkit-typing').parent().remove();
                    
                    // Handle streaming response
                    if (response.data.streaming) {
                        handleStreamResponse(chatContainer, response.data.response_id);
                    } else {
                        appendMessage(chatContainer, 'assistant', response.data.response, response.data.context);
                    }
                    
                    // Update conversations list
                    if (!chatConfig.isWidget) {
                        // loadConversations();
                    }
                } else {
                    handleError(chatContainer, response.data.message);
                }
            },
            error: function(xhr, status, error) {
                handleAjaxError(chatContainer, xhr, status, error);
            },
            complete: function() {
                isProcessing = false;
                input.prop('disabled', false);
                sendButton.prop('disabled', false);
                chatContainer.find('.ai-botkit-typing').parent().remove();
            }
        });
    }

    // Handle streaming response
    function handleStreamResponse(chatContainer, responseId) {
        let content = '';
        let sources = [];
        
        function pollResponse() {
            $.ajax({
                url: chatConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_stream_response',
                    response_id: responseId,
                    _ajax_nonce: chatConfig.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Append new content
                        if (response.data.content) {
                            content += response.data.content;
                            
                            // Update or create message
                            const messageElement = chatContainer.find('.ai-botkit-message.assistant:last');
                            if (messageElement.length) {
                                messageElement.find('.ai-botkit-message-text').html(content);
                            } else {
                                appendMessage(chatContainer, 'assistant', content);
                            }
                            
                            // Update sources if available
                            if (response.data.sources) {
                                sources = response.data.sources;
                                updateMessageSources(chatContainer, sources);
                            }
                            
                            // Scroll to bottom
                            scrollToBottom(chatContainer);
                        }
                        
                        // Continue polling if not done
                        if (!response.data.done) {
                            setTimeout(pollResponse, 1000);
                        }
                    } else {
                        handleError(chatContainer, response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(chatContainer, xhr, status, error);
                }
            });
        }
        
        // Start polling
        pollResponse();
    }

    // Append message to chat
    function appendMessage(chatContainer, role, content, sources = []) {
        const template = chatContainer.find('template[id$="-message-template"]').html();
        const message = $(template);
        
        message.addClass(role);
        message.find('.ai-botkit-message-text').html(content);
        
        // if (sources && sources.length > 0) {
        //     updateMessageSources(message, sources);
        // }
        
        chatContainer.find('.ai-botkit-chat-messages').append(message);
        scrollToBottom(chatContainer);
    }

    // Update message sources
    function updateMessageSources(container, sources) {
        const sourcesContainer = container.find('.ai-botkit-message-sources');
        sourcesContainer.empty();
        
        if (sources && sources.length > 0) {
            const sourcesList = $('<div class="sources-list"></div>');
            sources.forEach(source => {
                sourcesList.append(
                    `<a href="${source.url}" class="ai-botkit-source-link" target="_blank">
                        ${source.title}
                    </a>`
                );
            });
            sourcesContainer.append(sourcesList);
        }
    }

    // Start new conversation
    function startNewConversation(chatContainer) {
        currentConversationId = 'chat_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
        chatContainer.find('.ai-botkit-chat-messages').empty();
        appendMessage(chatContainer, 'assistant', chatConfig.i18n.welcomeMessage);
    }

    // Load conversation
    function loadConversation(chatContainer, conversationId) {
        $.ajax({
            url: chatConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_get_history',
                conversation_id: conversationId,
                bot_id: currentBotID,
                _ajax_nonce: chatConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    chatContainer.find('.ai-botkit-chat-messages').empty();
                    
                    response.data.messages.forEach(message => {
                        appendMessage(chatContainer, message.role, message.content, message.sources);
                    });
                    
                    scrollToBottom(chatContainer);
                } else {
                    handleError(chatContainer, response.data.message);
                }
            },
            error: function(xhr, status, error) {
                handleAjaxError(chatContainer, xhr, status, error);
            }
        });
    }

    // Clear conversation
    function clearConversation(chatContainer) {
        if (currentConversationId) {
            $.ajax({
                url: chatConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_clear_conversation',
                    conversation_id: currentConversationId,
                    bot_id: currentBotID,
                    _ajax_nonce: chatConfig.nonce
                },
                success: function(response) {
                    if (response.success) {
                        startNewConversation(chatContainer);
                        if (!chatConfig.isWidget) {
                            // loadConversations();
                        }
                    } else {
                        handleError(chatContainer, response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(chatContainer, xhr, status, error);
                }
            });
        } else {
            startNewConversation(chatContainer);
        }
    }

    // Update conversation list
    function updateConversationList(conversations) {
        const list = $('.ai-botkit-conversations');
        if (list.length) {
            list.empty();
            conversations.forEach(conversation => {
                list.append(
                    `<div class="conversation" data-id="${conversation.id}">
                        <div class="preview">${conversation.preview}</div>
                        <div class="date">${conversation.date}</div>
                    </div>`
                );
            });
        }
    }

    // Handle AJAX errors
    function handleAjaxError(chatContainer, xhr, status, error) {
        // Remove typing indicator
        chatContainer.find('.ai-botkit-typing').parent().remove();
        
        let errorMessage = "Something went wrong. Please try again.";
        
        // Check for rate limiting errors
        if (xhr.status === 429) {
            try {
                const response = JSON.parse(xhr.responseText);
                errorMessage = response.data.message || "Rate limit exceeded. Please try again later.";
                
                // Disable the input form if rate limited
                const input = chatContainer.find('.ai-botkit-input');
                const sendButton = chatContainer.find('.ai-botkit-send-button');
                
                input.prop('disabled', true);
                sendButton.prop('disabled', true);
                
                // Add rate limit warning class
                chatContainer.find('.ai-botkit-input-container').addClass('rate-limited');
                
                // Add visual indicator
                if (!chatContainer.find('.rate-limit-warning').length) {
                    chatContainer.find('.ai-botkit-input-container').prepend(
                        '<div class="rate-limit-warning">' + errorMessage + '</div>'
                    );
                }
            } catch (e) {
                console.error("Error parsing rate limit response:", e);
            }
        }
        
        appendMessage(chatContainer, 'system', '<div class="ai-botkit-error">' + errorMessage + '</div>');
        scrollToBottom(chatContainer);
    }

    // Handle error response
    function handleError(chatContainer, message) {
        // Remove typing indicator
        chatContainer.find('.ai-botkit-typing').parent().remove();
        
        // Check if this is a rate limit error
        if (message && message.toLowerCase().includes('rate limit')) {
            // Disable the input form if rate limited
            const input = chatContainer.find('.ai-botkit-input');
            const sendButton = chatContainer.find('.ai-botkit-send-button');
            
            input.prop('disabled', true);
            sendButton.prop('disabled', true);
            
            // Add rate limit warning class
            chatContainer.find('.ai-botkit-input-container').addClass('rate-limited');
            
            // Add visual indicator
            if (!chatContainer.find('.rate-limit-warning').length) {
                chatContainer.find('.ai-botkit-input-container').prepend(
                    '<div class="rate-limit-warning">' + message + '</div>'
                );
            }
        }
        
        appendMessage(chatContainer, 'system', '<div class="ai-botkit-error">' + message + '</div>');
        scrollToBottom(chatContainer);
    }

    // Utility functions
    function scrollToBottom(chatContainer) {
        const messages = chatContainer.find('.ai-botkit-chat-messages');
        messages.scrollTop(messages[0].scrollHeight);
    }

    // Expose initialization function
    window.ai_botkit = {
        initChat: initChat
    };

    const widgetId = 'ai-botkit-' + ai_botkitChat.chatId;
    const button = document.getElementById(widgetId + '-button');
    const widget = document.getElementById(widgetId + '-chat');

    // Toggle widget visibility with smooth transition
    button.addEventListener('click', function() {
        const isVisible = !widget.classList.contains('minimized');
        
        if (!isVisible) {
            widget.style.display = 'block';
            // Force reflow
            widget.offsetHeight;
            widget.classList.remove('minimized');
        } else {
            widget.classList.add('minimized');
        }
        
        widget.setAttribute('aria-hidden', isVisible);
        button.setAttribute('aria-expanded', !isVisible);
        // button.style.display = isVisible ? 'flex' : 'none';
        
        // Hide widget after animation completes if minimizing
        if (isVisible) {
            setTimeout(() => {
                if (widget.classList.contains('minimized')) {
                    widget.style.display = 'none';
                }
            }, 300);
        }
    });

    // Close widget when clicking outside with smooth transition
    document.addEventListener('click', function(event) {
        const isVisible = !widget.classList.contains('minimized');
        if (isVisible && !widget.contains(event.target) && !button.contains(event.target)) {
            widget.classList.add('minimized');
            widget.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
            // button.style.display = 'flex';
            
            setTimeout(() => {
                widget.style.display = 'none';
            }, 300);
        }
    });

    // Handle minimize button with smooth transition
    const minimizeButton = widget.querySelector('.ai-botkit-minimize');
    if (minimizeButton) {
        minimizeButton.addEventListener('click', function() {
            widget.classList.add('minimized');
            widget.setAttribute('aria-hidden', 'true');
            button.setAttribute('aria-expanded', 'false');
            // button.style.display = 'flex';
            
            setTimeout(() => {
                widget.style.display = 'none';
            }, 300);
        });
    }

    // Handle feedback
    $('.ai-botkit-chat-messages').on('click', '.ai-botkit-message-feedback-up-button', function(e) {
        e.preventDefault();
        const $button = $(this); // store reference to clicked button
        const messageContent = $(this).closest('.ai-botkit-message').find('.ai-botkit-message-text').text();

        $.ajax({
            url: chatConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_feedback',
                chat_id: currentConversationId,
                message: messageContent,
                feedback: 'up',
                nonce: chatConfig.nonce
            },
            success: function(response) {
                // change the button to a thumbs up icon
                $button.addClass('ti-thumb-up-filled');
                $button.removeClass('ti-thumb-up');
            }
        });
    });
    $('.ai-botkit-chat-messages').on('click', '.ai-botkit-message-feedback-down-button', function(e) {
        e.preventDefault();
        const $button = $(this); // store reference to clicked button
        const messageContent = $(this).closest('.ai-botkit-message').find('.ai-botkit-message-text').text();

        $.ajax({
            url: chatConfig.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_feedback',
                chat_id: currentConversationId,
                message: messageContent,
                feedback: 'down',
                nonce: chatConfig.nonce
            },
            success: function(response) {
                // change the button to a thumbs down icon
                $button.addClass('ti-thumb-down-filled');
                $button.removeClass('ti-thumb-down');
            }
        });
    });
}); 
