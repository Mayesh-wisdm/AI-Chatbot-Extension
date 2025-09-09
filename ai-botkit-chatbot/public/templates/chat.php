<?php
if (!defined('WPINC')) die;

$is_widget = isset($args['widget']) && $args['widget'];
$container_class = $is_widget ? 'ai-botkit-widget-container' : 'ai-botkit-chat-container';

// Ensure required style properties exist with defaults
if (!isset($styles) || !is_array($styles)) {
    $styles = array();
}
$styles = array_merge(array(
    'avatar' => AI_BOTKIT_PLUGIN_URL . 'public/images/bot.png'
), $styles);
?>

<div class="<?php echo esc_attr($container_class); ?>" id="<?php echo esc_attr($chat_id); ?>" data-widget="<?php echo $is_widget ? 'true' : 'false'; ?>">
    <div class="ai-botkit-chat">
        <!-- Chat Header -->
        <div class="ai-botkit-chat-header">
            <div class="ai-botkit-chat-header-left">
                <div class="ai-botkit-chat-avatar">
                    <img src="<?php echo esc_url($styles['avatar']); ?>" alt="Chatbot avatar" class="ai-botkit-avatar-img" />
                </div>
                <h3><?php echo esc_html($chatbot_data['name']); ?></h3>
            </div>
            <div class="ai-botkit-chat-actions">
                <button type="button" class="ai-botkit-clear" aria-label="<?php esc_attr_e('Clear chat', 'ai-botkit-for-lead-generation'); ?>">
                    <i class="ti ti-refresh"></i>
                </button>
                <?php if ($is_widget): ?>
                    <button type="button" class="ai-botkit-minimize" aria-label="<?php esc_attr_e('Minimize chat', 'ai-botkit-for-lead-generation'); ?>">
                    <i class="ti ti-x"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="ai-botkit-chat-messages" aria-live="polite">
            <!-- Welcome Message -->
            <div class="ai-botkit-message assistant">
                <div class="ai-botkit-message-content">
                    <div class="ai-botkit-message-text">
                        <?php echo wp_kses_post($chatbot_data['greeting'] ?? __('Hello! How can I help you today?', 'ai-botkit-for-lead-generation')); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="ai-botkit-chat-input">
            <input 
                class="ai-botkit-input"
                id="ai-botkit-chat-input"
                placeholder="<?php esc_attr_e('Type your message...', 'ai-botkit-for-lead-generation'); ?>"
                required
                aria-label="<?php esc_attr_e('Message input', 'ai-botkit-for-lead-generation'); ?>"
            >
            <button
                class="ai-botkit-send-button"
                aria-label="<?php esc_attr_e('Send message', 'ai-botkit-for-lead-generation'); ?>"
            >
                <i class="ti ti-send"></i>
            </button>
        </div>
    </div>
        
    <!-- Message Template -->
    <template id="<?php echo esc_attr($chat_id); ?>-message-template">
        <div class="ai-botkit-message">
            <div class="ai-botkit-message-content">
                <div class="ai-botkit-message-text"></div>
                <?php if ( 1 == $chatbot_data['feedback'] ) { ?>
                <div class="ai-botkit-message-feedback">
                        <i class="ti ti-thumb-up ai-botkit-message-feedback-up-button"></i>
                        <i class="ti ti-thumb-down ai-botkit-message-feedback-down-button"></i>
                    </div>
                <?php } ?>
            </div>
        </div>
    </template>

    <!-- Loading Template -->
    <template id="<?php echo esc_attr($chat_id); ?>-typing-template">
        <div class="ai-botkit-message assistant">
            <div class="ai-botkit-typing">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </template>
</div>