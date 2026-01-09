<!-- AI BotKit Help Button -->
<button id="ai-botkit-admin-help-button" class="ai-botkit-admin-help-button" aria-expanded="false">
    <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot.png'); ?>" alt="AI BotKit">
</button>

<div class="ai-botkit-help-widget-container">
    <i class="ti ti-x ai-botkit-help-widget-close"></i>
    <p><?php esc_html_e('Hi, need help for your chatbot. I’m happy to help. Click here.', 'knowvault'); ?></p>
</div>

<!-- KnowVault Help Widget -->
<div class="ai-botkit-widget" aria-hidden="true">
    <div class="ai-botkit-widget-header">
        <div class="ai-botkit-widget-header-left">
            <h3 class="ai-botkit-widget-title">AI Assistant</h3>
        </div>
        <div class="ai-botkit-widget-header-right">
            <div class="ai-botkit-widget-actions">
                <button class="ai-botkit-widget-action ai-botkit-clear" title="Clear Chat">
                    <i class="ti ti-refresh"></i>
                </button>
                <button class="ai-botkit-widget-action ai-botkit-close" title="Close Chat">
                    <i class="ti ti-x"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="ai-botkit-chat-container">
        <div class="ai-botkit-chat-messages">
            <div class="ai-botkit-message assistant">
                <div class="ai-botkit-message-content">
                    <div class="ai-botkit-message-text">
                        <?php esc_html_e('Hi there! I\'m your KnowVault Assistant. How can I help you today?', 'knowvault'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <form class="ai-botkit-input-form">
        <div class="ai-botkit-input-wrapper">
            <textarea class="ai-botkit-doc-bot-input" placeholder="<?php esc_html_e('Type your message...', 'knowvault'); ?>" rows="1"></textarea>
            <button type="submit" class="ai-botkit-send-button">
                <i class="ti ti-send"></i>
            </button>
        </div>
    </form>
</div> 
