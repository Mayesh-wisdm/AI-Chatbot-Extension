jQuery(document).ready(function ($) {
    // Chat instance configuration
    let chatConfig = {};
    let isProcessing = false;
    let currentConversationId = null;

    /**
     * Sanitize HTML content to prevent XSS attacks
     * Allows safe formatting tags while removing dangerous elements
     *
     * @param {string} html - HTML string to sanitize
     * @return {string} Sanitized HTML
     */
    function sanitizeHtml(html) {
        if (!html || typeof html !== 'string') {
            return '';
        }

        // Create a temporary element to parse HTML
        const temp = document.createElement('div');
        temp.innerHTML = html;

        // Remove script tags
        const scripts = temp.querySelectorAll('script');
        scripts.forEach(function (script) { script.remove(); });

        // Remove iframe tags
        const iframes = temp.querySelectorAll('iframe');
        iframes.forEach(function (iframe) { iframe.remove(); });

        // Remove object/embed tags
        const objects = temp.querySelectorAll('object, embed');
        objects.forEach(function (obj) { obj.remove(); });

        // Remove event handlers from all elements
        const allElements = temp.querySelectorAll('*');
        allElements.forEach(function (el) {
            // Remove all on* attributes (onclick, onerror, etc.)
            Array.from(el.attributes).forEach(function (attr) {
                if (attr.name.startsWith('on') || attr.name === 'href' && attr.value.toLowerCase().startsWith('javascript:')) {
                    el.removeAttribute(attr.name);
                }
            });
        });

        // Remove style tags (can contain expressions in old IE)
        const styles = temp.querySelectorAll('style');
        styles.forEach(function (style) { style.remove(); });

        return temp.innerHTML;
    }

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
        sendButton.on('click', function (e) {
            e.preventDefault();
            const message = input.val().trim();
            if (message && !isProcessing) {
                sendMessage(chatContainer, message);
            }
        });

        // Send message on Enter (but allow Shift+Enter for new lines)
        input.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const message = input.val().trim();
                if (message && !isProcessing) {
                    sendMessage(chatContainer, message);
                }
            }
        });

        // Clear chat functionality
        $('.ai-botkit-clear').on('click', function () {
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

        // Start new conversation (in-chat button if present)
        chatContainer.find('.ai-botkit-new').on('click', function () {
            startNewConversation(chatContainer);
        });

        // Listen for conversation switched from history panel (so new messages save to loaded conversation)
        $(document).on('ai_botkit_conversation_switched', function (e, conversationId, data) {
            if (conversationId != null && conversationId !== undefined) {
                currentConversationId = String(conversationId);
            }
        });

        // Listen for start new conversation (e.g. from history panel "New conversation" button)
        $(document).on('ai_botkit_start_new_conversation', function () {
            const chatContainer = $('#' + chatConfig.chatId);
            if (chatContainer.length) {
                startNewConversation(chatContainer);
            }
        });

        // Select conversation
        chatContainer.find('.ai-botkit-conversations').on('click', '.conversation', function () {
            loadConversation(chatContainer, $(this).data('id'));
        });
    }

    // Auto-resize textarea
    function setupAutoResize(chatContainer) {
        const textarea = chatContainer.find('.ai-botkit-input');
        textarea.on('input', function () {
            // this.style.height = 'auto';
            // this.style.height = (this.scrollHeight) + 'px';
        });
    }

    // Send message
    function sendMessage(chatContainer, message) {
        const input = chatContainer.find('.ai-botkit-input');
        const sendButton = chatContainer.find('.ai-botkit-send-button');

        // DON'T remove recommendation cards - they should persist in chat history
        // chatContainer.find('.ai-botkit-suggestions-wrapper').remove();

        // Disable input and button
        isProcessing = true;
        input.prop('disabled', true);
        sendButton.prop('disabled', true);

        // Collect pending media (uploaded files) to send with message
        let attachmentData = [];
        if (window.aiBotKitMedia && typeof window.aiBotKitMedia.getPendingMedia === 'function') {
            attachmentData = window.aiBotKitMedia.getPendingMedia();
            if (window.aiBotKitMedia.clearPreviews) window.aiBotKitMedia.clearPreviews();
        }

        // Append user message with attachments shown in the bubble
        appendMessage(chatContainer, 'user', message, [], attachmentData);

        // Clear input
        input.val('').trigger('input');

        // Show typing indicator
        const typingTemplate = chatContainer.find('template[id$="-typing-template"]').html();
        chatContainer.find('.ai-botkit-chat-messages').append(typingTemplate);

        // Scroll to bottom
        scrollToBottom(chatContainer);

        // Build request data including attachments
        const requestData = {
            action: 'ai_botkit_chat_message',
            message: message,
            conversation_id: currentConversationId,
            bot_id: currentBotID,
            nonce: chatConfig.nonce
        };
        if (attachmentData.length > 0) {
            requestData.attachment_ids = attachmentData.map(function (m) { return m.id; });
            requestData.attachments = JSON.stringify(attachmentData);
        }

        // Send message to server
        $.ajax({
            url: chatConfig.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function (response) {
                if (response.success) {
                    // Remove typing indicator
                    chatContainer.find('.ai-botkit-typing').parent().remove();

                    // Handle streaming response
                    if (response.data.streaming) {
                        handleStreamResponse(chatContainer, response.data.response_id, message);
                    } else {
                        appendMessage(chatContainer, 'assistant', response.data.response, response.data.context);
                        showRecommendationCards(chatContainer, message, response.data.response);
                    }

                    // Update conversations list
                    if (!chatConfig.isWidget) {
                        // loadConversations();
                    }
                } else {
                    handleError(chatContainer, response.data.message);
                }
            },
            error: function (xhr, status, error) {
                handleAjaxError(chatContainer, xhr, status, error);
            },
            complete: function () {
                isProcessing = false;
                input.prop('disabled', false);
                sendButton.prop('disabled', false);
                chatContainer.find('.ai-botkit-typing').parent().remove();
            }
        });
    }

    // Handle streaming response
    function handleStreamResponse(chatContainer, responseId, lastUserMessage) {
        let content = '';
        let sources = [];
        let pollCount = 0;
        const maxPolls = 120; // Max 2 minutes of polling (120 * 1 second)

        function pollResponse() {
            pollCount++;

            // Prevent infinite polling
            if (pollCount > maxPolls) {
                handleError(chatContainer, chatConfig.i18n?.responseTimeout || 'Response timed out. Please try again.');
                return;
            }

            $.ajax({
                url: chatConfig.ajaxUrl,
                type: 'POST',
                timeout: 30000, // 30 second timeout per request
                data: {
                    action: 'ai_botkit_stream_response',
                    response_id: responseId,
                    _ajax_nonce: chatConfig.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Append new content
                        if (response.data.content) {
                            content += response.data.content;

                            // Update or create message
                            const messageElement = chatContainer.find('.ai-botkit-message.assistant:last');
                            if (messageElement.length) {
                                messageElement.find('.ai-botkit-message-text').html(sanitizeHtml(content));
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

                        // When streaming is done, show recommendation cards
                        if (response.data.done && lastUserMessage && content) {
                            showRecommendationCards(chatContainer, lastUserMessage, content);
                        }

                        // Continue polling if not done
                        if (!response.data.done) {
                            setTimeout(pollResponse, 1000);
                        }
                    } else {
                        handleError(chatContainer, response.data.message || chatConfig.i18n?.genericError || 'An error occurred.');
                    }
                },
                error: function (xhr, status, error) {
                    // On timeout or network error, retry a few times before giving up
                    if (status === 'timeout' && pollCount < 3) {
                        setTimeout(pollResponse, 2000); // Retry with longer delay
                    } else {
                        handleAjaxError(chatContainer, xhr, status, error);
                    }
                }
            });
        }

        // Start polling
        pollResponse();
    }

    // Show LMS/WooCommerce recommendation cards after assistant message
    function showRecommendationCards(chatContainer, userMessage, assistantResponse) {
        if (typeof window.AIBotKitSuggestions === 'undefined') {
            console.warn('[AI BotKit] AIBotKitSuggestions not available');
            return;
        }

        // Check if user message contains recommendation keywords BEFORE making ajax call
        // This prevents unnecessary API calls for normal chat messages
        const recommendationKeywords = [
            'recommend', 'suggest', 'suggestion', 'show me', 'find me',
            'looking for', 'need', 'want', 'help me find', 'what should i',
            'best', 'top', 'popular'
        ];

        const userMessageLower = (userMessage || '').toLowerCase();
        const hasRecommendationIntent = recommendationKeywords.some(keyword =>
            userMessageLower.includes(keyword)
        );

        if (!hasRecommendationIntent) {
            console.log('[AI BotKit] No recommendation keywords detected, skipping ajax call');
            return;
        }

        // Find the last assistant message to attach recommendations below it
        const $messages = chatContainer.find('.ai-botkit-chat-messages');
        const $lastAssistantMessage = $messages.find('.ai-botkit-message.assistant').last();

        if (!$lastAssistantMessage.length) {
            console.warn('[AI BotKit] No assistant message found to attach recommendations');
            return;
        }

        // Check if this message already has recommendations (don't duplicate)
        if ($lastAssistantMessage.next('.ai-botkit-suggestions-wrapper').length > 0) {
            console.log('[AI BotKit] Recommendations already exist for this message, skipping');
            return;
        }

        // Create new suggestions wrapper and attach it after the CURRENT assistant message
        const $wrapper = $('<div class="ai-botkit-suggestions-wrapper" aria-label="Recommendations"></div>');
        $lastAssistantMessage.after($wrapper);

        // Send ONLY the user message (not the assistant response)
        // The backend will extract just the user's question and ignore bot responses
        const conversationText = userMessage || '';
        console.log('[AI BotKit] Getting recommendations for user message:', conversationText.substring(0, 100));

        if (typeof window.AIBotKitSuggestions.getRecommendations === 'function') {
            window.AIBotKitSuggestions.getRecommendations(conversationText, function (recs) {
                console.log('[AI BotKit] Received recommendations:', recs);
                if (recs && recs.length > 0 && typeof window.AIBotKitSuggestions.renderSuggestionCards === 'function') {
                    window.AIBotKitSuggestions.renderSuggestionCards(recs, $wrapper);
                    console.log('[AI BotKit] Rendered', recs.length, 'recommendation cards');
                    scrollToBottom(chatContainer);
                } else {
                    // Remove wrapper if no recommendations (not explicitly requested)
                    $wrapper.remove();
                    console.log('[AI BotKit] No recommendations to render');
                }
            });
        } else {
            console.warn('[AI BotKit] getRecommendations function not available');
            $wrapper.remove();
        }
    }

    // Append message to chat
    // attachments: optional array of { id, url, type } for user messages
    function appendMessage(chatContainer, role, content, sources = [], attachments = []) {
        const template = chatContainer.find('template[id$="-message-template"]').html();
        const message = $(template);

        message.addClass(role);
        const $text = message.find('.ai-botkit-message-text');
        $text.html(sanitizeHtml(content));

        if (role === 'user' && attachments && attachments.length > 0) {
            const $attachments = $('<div class="ai-botkit-message-attachments"></div>');
            attachments.forEach(function (att) {
                const type = (att.type || 'file').toLowerCase();
                const url = att.url || '';
                const safeUrl = url.replace(/"/g, '&quot;').replace(/</g, '&lt;');
                if (type === 'image' && url) {
                    $attachments.append(
                        '<div class="ai-botkit-message-attachment ai-botkit-message-attachment-image">' +
                        '<a href="' + safeUrl + '" target="_blank" rel="noopener"><img src="' + safeUrl + '" alt="Attachment" loading="lazy" /></a>' +
                        '</div>'
                    );
                } else if (url) {
                    $attachments.append(
                        '<div class="ai-botkit-message-attachment">' +
                        '<a href="' + safeUrl + '" target="_blank" rel="noopener">' + (type === 'document' ? 'Document' : type) + '</a>' +
                        '</div>'
                    );
                }
            });
            $text.after($attachments);
        }

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
            success: function (response) {
                if (response.success) {
                    chatContainer.find('.ai-botkit-chat-messages').empty();
                    // Remove any existing suggestions wrappers
                    chatContainer.find('.ai-botkit-suggestions-wrapper').remove();

                    let lastUserMessage = '';
                    let lastAssistantMessage = '';

                    response.data.messages.forEach(message => {
                        appendMessage(chatContainer, message.role, message.content, message.sources);
                        if (message.role === 'user') {
                            lastUserMessage = message.content;
                        } else if (message.role === 'assistant') {
                            lastAssistantMessage = message.content;
                        }
                    });

                    // Regenerate recommendations for the last exchange if we have both user and assistant messages
                    if (lastUserMessage && lastAssistantMessage) {
                        showRecommendationCards(chatContainer, lastUserMessage, lastAssistantMessage);
                    }

                    scrollToBottom(chatContainer);
                } else {
                    handleError(chatContainer, response.data.message);
                }
            },
            error: function (xhr, status, error) {
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
                success: function (response) {
                    if (response.success) {
                        startNewConversation(chatContainer);
                        if (!chatConfig.isWidget) {
                            // loadConversations();
                        }
                    } else {
                        handleError(chatContainer, response.data.message);
                    }
                },
                error: function (xhr, status, error) {
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

    // Only attach widget toggle/close when floating widget exists (shortcode embed has no button/widget)
    if (widget && button) {
        // Toggle widget visibility with smooth transition
        button.addEventListener('click', function () {
            const isVisible = !widget.classList.contains('minimized');

            if (!isVisible) {
                widget.style.display = 'block';
                widget.offsetHeight;
                widget.classList.remove('minimized');
            } else {
                widget.classList.add('minimized');
            }

            widget.setAttribute('aria-hidden', isVisible);
            button.setAttribute('aria-expanded', !isVisible);

            if (isVisible) {
                setTimeout(function () {
                    if (widget.classList.contains('minimized')) {
                        widget.style.display = 'none';
                    }
                }, 300);
            }
        });

        // Close widget when clicking outside
        document.addEventListener('click', function (event) {
            const isVisible = !widget.classList.contains('minimized');
            if (isVisible && !widget.contains(event.target) && !button.contains(event.target)) {
                widget.classList.add('minimized');
                widget.setAttribute('aria-hidden', 'true');
                button.setAttribute('aria-expanded', 'false');
                setTimeout(function () {
                    widget.style.display = 'none';
                }, 300);
            }
        });

        // Handle minimize button
        var minimizeButton = widget.querySelector('.ai-botkit-minimize');
        if (minimizeButton) {
            minimizeButton.addEventListener('click', function () {
                widget.classList.add('minimized');
                widget.setAttribute('aria-hidden', 'true');
                button.setAttribute('aria-expanded', 'false');
                setTimeout(function () {
                    widget.style.display = 'none';
                }, 300);
            });
        }
    }

    // Handle feedback
    $('.ai-botkit-chat-messages').on('click', '.ai-botkit-message-feedback-up-button', function (e) {
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
            success: function (response) {
                // change the button to a thumbs up icon
                $button.addClass('ti-thumb-up-filled');
                $button.removeClass('ti-thumb-up');
            }
        });
    });
    $('.ai-botkit-chat-messages').on('click', '.ai-botkit-message-feedback-down-button', function (e) {
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
            success: function (response) {
                // change the button to a thumbs down icon
                $button.addClass('ti-thumb-down-filled');
                $button.removeClass('ti-thumb-down');
            }
        });
    });

    // Expose for chat-history: set conversation ID when loading from history so new messages save to that conversation
    window.ai_botkit = window.ai_botkit || {};
    window.ai_botkit.setConversationId = function (id) {
        currentConversationId = id != null && id !== undefined ? String(id) : null;
    };
    window.ai_botkit.startNewConversation = function () {
        $(document).trigger('ai_botkit_start_new_conversation');
    };
}); 