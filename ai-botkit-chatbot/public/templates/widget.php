<?php
if (!defined('WPINC')) die;

// Get widget settings
$widget_settings = get_option('ai_botkit_widget_settings', [
    'position' => 'right',
    'offset_x' => 20,
    'offset_y' => 20,
    'title' => __('AI Assistant', 'knowvault'),
    'welcome_message' => __('Hello! How can I help you today?', 'knowvault'),
    'placeholder' => __('Type your message...', 'knowvault'),
    'button_text' => __('Chat with AI', 'knowvault'),
]);

$styles = json_decode($chatbot_data['style'], true);

// Ensure required style properties exist with defaults
if (!$styles || !is_array($styles)) {
    $styles = array();
}
$styles = array_merge(array(
    'location' => 'bottom-right',
    'widget' => AI_BOTKIT_PLUGIN_URL . 'public/images/bot.png'
), $styles);

// Generate unique ID for this widget instance
$widget_id = uniqid('ai-botkit-widget-');
?>

<!-- Widget Button -->
<button 
    id="ai-botkit-<?php echo esc_attr($chat_id); ?>-button"
    class="ai-botkit-widget-button ai-botkit-<?php echo esc_attr( $styles['location']); ?>"
    aria-label="<?php echo esc_attr($widget_settings['button_text']); ?>"
    aria-expanded="false"
    aria-controls="ai-botkit-<?php echo esc_attr($chat_id); ?>-chat"
>
    <span class="ai-botkit-widget-button-icon">
        <img src="<?php echo esc_url($styles['widget']); ?>" alt="Chatbot avatar" class="ai-botkit-avatar-img" />
    </span>
    <!-- <span class="ai-botkit-widget-button-text">
        <?php echo esc_html($widget_settings['button_text']); ?>
    </span> -->
</button>

<!-- Widget Container -->
<div 
    id="ai-botkit-<?php echo esc_attr($chat_id); ?>-chat"
    class="ai-botkit-widget minimized ai-botkit-<?php echo esc_attr( $styles['location']); ?>"
    aria-hidden="true"
>
    <?php
    // Include the main chat template with widget flag
    include AI_BOTKIT_PUBLIC_DIR . 'templates/chat.php';
    ?>
</div>