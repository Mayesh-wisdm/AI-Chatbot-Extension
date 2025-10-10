jQuery(document).ready(function($) {
    // Config
    const restApiEndpoint = aiBotKitAdminHelp.restApiEndpoint;
    const botId = aiBotKitAdminHelp.botId;
    const nonce = aiBotKitAdminHelp.nonce || '';
    const isExternalEndpoint = aiBotKitAdminHelp.isExternalEndpoint || false;
    
    // Generate conversation ID using site URL and random 4-digit number
    // Get site URL from window.location.hostname
    const siteUrl = window.location.hostname;
    // Generate random 4-digit number
    const randomNum = Math.floor(1000 + Math.random() * 9000);
    // Create conversation ID
    const conversationId = siteUrl + '-' + randomNum;
    
    // DOM elements
    const helpButton = $('#ai-botkit-admin-help-button');
    const widgetContainer = $('.ai-botkit-widget');
    const chatContainer = $('.ai-botkit-chat-container');
    const inputForm = $('.ai-botkit-input-form');
    const chatInput = $('.ai-botkit-doc-bot-input');
    const chatMessages = $('.ai-botkit-chat-messages');
    const sendButton = $('.ai-botkit-send-button');
    
    // Initialize
    window.isProcessing = false;
    
    // Load chat history from localStorage
    function loadChatHistory() {
        const history = localStorage.getItem('ai_botkit_chat_history');
        if (history) {
            try {
                const messages = JSON.parse(history);
                chatMessages.empty();
                messages.forEach(msg => {
                    appendMessage(msg.role, msg.content, false);
                });
            } catch (e) {
                console.error('Error loading chat history:', e);
                clearConversation();
            }
        } else {
            clearConversation();
        }
    }
    
    // Save chat history to localStorage
    function saveChatHistory() {
        const messages = [];
        chatMessages.find('.ai-botkit-message').each(function() {
            const role = $(this).hasClass('user') ? 'user' : 'assistant';
            const content = $(this).find('.ai-botkit-message-text').html();
            messages.push({ role, content });
        });
        localStorage.setItem('ai_botkit_chat_history', JSON.stringify(messages));
    }
    
    // Initialize chat functionality
    function initializeChat() {
        // Remove existing handlers to prevent duplicates
        inputForm.off('submit');
        chatInput.off('keydown');
        sendButton.off('click');
        
        // Add submit handler
        inputForm.on('submit', function(e) {
            e.preventDefault();
            const message = chatInput.val().trim();
            if (message && !window.isProcessing) {
                window.sendMessage(message);
            }
        });
        
        // Add keydown handler
        chatInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const message = $(this).val().trim();
                if (message && !window.isProcessing) {
                    window.sendMessage(message);
                }
            }
        });
        
        // Add click handler to send button
        sendButton.on('click', function(e) {
            e.preventDefault();
            const message = chatInput.val().trim();
            if (message && !window.isProcessing) {
                window.sendMessage(message);
            }
        });
    }
    
    // Toggle widget visibility when floating help button is clicked
    helpButton.on('click', function() {
        const isVisible = widgetContainer.attr('aria-hidden') === 'true';
        
        if (isVisible) {
            widgetContainer.attr('aria-hidden', 'false');
            helpButton.attr('aria-expanded', 'true');
            $('.ai-botkit-help-widget-container').hide();
            // Load chat history when widget becomes visible
            loadChatHistory();
            // Initialize chat when widget becomes visible
            initializeChat();
        } else {
            widgetContainer.attr('aria-hidden', 'true');
            helpButton.attr('aria-expanded', 'false');
        }
    });
    
    // Use delegated event handler for Ask Assistant button to ensure it works regardless of when elements are loaded
    $(document).on('click', '#ai-botkit-ask-assistant', function(e) {
        e.preventDefault();
        helpButton.click();
    });
    
    // Close widget when clicking outside
    $(document).on('click', function(event) {
        const isVisible = widgetContainer.attr('aria-hidden') === 'false';
        if (isVisible && 
            !widgetContainer[0].contains(event.target) && 
            !helpButton[0].contains(event.target) &&
            !$(event.target).closest('#ai-botkit-ask-assistant').length) {
            
            widgetContainer.attr('aria-hidden', 'true');
            helpButton.attr('aria-expanded', 'false');
        }
    });
    
    // Handle close button
    $('.ai-botkit-close').on('click', function() {
        widgetContainer.attr('aria-hidden', 'true');
        helpButton.attr('aria-expanded', 'false');
    });
    
    // Clear conversation
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
    
    // Send message to REST API
    window.sendMessage = function(message) {
        const input = chatInput;
        const sendButton = $('.ai-botkit-send-button');
        
        // Disable input and button
        window.isProcessing = true;
        input.prop('disabled', true);
        sendButton.prop('disabled', true);
        
        // Append user message
        appendMessage('user', message);
        
        // Clear input
        input.val('').trigger('input');
        
        // Show typing indicator
        const typingIndicator = $('<div class="ai-botkit-message assistant"><div class="ai-botkit-typing"><span></span><span></span><span></span></div></div>');
        chatMessages.append(typingIndicator);
        
        // Scroll to bottom
        scrollToBottom();
        
        // Prepare AJAX options
        const ajaxOptions = {
            url: restApiEndpoint,
            method: 'POST',
            data: JSON.stringify({
                message: message,
                conversation_id: conversationId,
                bot_id: botId,
                context: ''
            }),
            contentType: 'application/json',
            success: function(response) {
                // Hide typing indicator
                $('.ai-botkit-typing').parent().remove();
                
                // Show bot response
                appendMessage('assistant', response.response);
            },
            error: function(xhr, status, error) {
                // Handle error
                $('.ai-botkit-typing').parent().remove();
                appendMessage('assistant', 'Sorry, there was an error processing your request.');
                console.error('Error:', xhr, status, error);
            },
            complete: function() {
                window.isProcessing = false;
                input.prop('disabled', false);
                sendButton.prop('disabled', false);
            }
        };
        
        // Only add nonce header for non-external endpoints
        if (!isExternalEndpoint && nonce) {
            ajaxOptions.headers = {
                'X-WP-Nonce': nonce
            };
        }
        
        // Call REST API
        $.ajax(ajaxOptions);
    };
    
    // Append message to chat
    function appendMessage(role, content, saveToHistory = true) {
        const messageTemplate = $('<div class="ai-botkit-message"><div class="ai-botkit-message-content"><div class="ai-botkit-message-text"></div></div></div>');
        
        messageTemplate.addClass(role);
        messageTemplate.find('.ai-botkit-message-text').html(content);
        
        chatMessages.append(messageTemplate);
        scrollToBottom();
        
        // Save to history if needed
        if (saveToHistory) {
            saveChatHistory();
        }
    }
    
    // Clear conversation
    function clearConversation() {
        chatMessages.empty();
        
        // Add welcome message back
        const welcomeMessage = $('<div class="ai-botkit-message assistant"><div class="ai-botkit-message-content"><div class="ai-botkit-message-text">Hi there! I\'m your AI BotKit Assistant. How can I help you today?</div></div></div>');
        chatMessages.append(welcomeMessage);
    }
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }
    
    // Initialize chat on page load
    initializeChat();
    // Load chat history on page load
    loadChatHistory();

    // help widget
    $('.ai-botkit-help-widget-close').on('click', function() {
        $('.ai-botkit-help-widget-container').hide();
    });

    // on document load show the help widget and hide after 3 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.ai-botkit-help-widget-container').show();
        }, 3000);
    });
}); 
