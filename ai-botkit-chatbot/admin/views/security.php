<?php
defined('ABSPATH') || exit;

// Ensure user has permissions
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-botkit-for-lead-generation'));
}

// Save banned keywords if form is submitted
if (isset($_POST['submit_banned_keywords'])) {
    check_admin_referer('ai_botkit_security_settings');
    
    // Get and sanitize the banned keywords
    $banned_keywords = isset($_POST['ai_botkit_banned_keywords']) ? sanitize_text_field($_POST['ai_botkit_banned_keywords']) : '';
    
    // Process the keywords: split by comma, trim whitespace, convert to lowercase
    $keywords_array = array_map(function($word) {
        return strtolower(trim($word));
    }, explode(',', $banned_keywords));
    
    // Filter out empty values
    $keywords_array = array_filter($keywords_array);
    
    // Save as JSON in wp_options
    update_option('ai_botkit_banned_keywords', wp_json_encode($keywords_array));
    
    add_settings_error(
        'ai_botkit_security_messages',
        'ai_botkit_security_message',
        __('Security Settings Saved', 'ai-botkit-for-lead-generation'),
        'updated'
    );
}

// Save blocked IPs if form is submitted
if (isset($_POST['submit_blocked_ips'])) {
    check_admin_referer('ai_botkit_security_settings');
    
    // Get and sanitize the blocked IPs
    $blocked_ips = isset($_POST['ai_botkit_blocked_ips']) ? sanitize_text_field($_POST['ai_botkit_blocked_ips']) : '';
    
    // Process the IPs: split by comma, trim whitespace
    $ips_array = array_map(function($ip) {
        return trim($ip);
    }, explode(',', $blocked_ips));
    
    // Filter out empty values
    $ips_array = array_filter($ips_array);
    
    // Save as JSON in wp_options
    update_option('ai_botkit_blocked_ips', wp_json_encode($ips_array));
    
    add_settings_error(
        'ai_botkit_security_messages',
        'ai_botkit_security_message',
        __('Security Settings Saved', 'ai-botkit-for-lead-generation'),
        'updated'
    );
}

// Get current banned keywords
$banned_keywords_json = get_option('ai_botkit_banned_keywords', '[]');
$banned_keywords_array = json_decode($banned_keywords_json, true);
$banned_keywords_string = implode(', ', $banned_keywords_array);

// Get current blocked IPs
$blocked_ips_json = get_option('ai_botkit_blocked_ips', '[]');
$blocked_ips_array = json_decode($blocked_ips_json, true);
$blocked_ips_string = implode(', ', $blocked_ips_array);
?>

<div class="ai-botkit-settings-container">
    <h2 class="ai-botkit-settings-title"><?php esc_html_e('Security Settings', 'ai-botkit-for-lead-generation'); ?></h2>
    
    <?php settings_errors('ai_botkit_security_messages'); ?>
    
    <form method="post" action="">
        <div class="ai-botkit-tabs-content">
            <?php wp_nonce_field('ai_botkit_security_settings'); ?>

            <!-- Banned Keywords Section -->
            <div class="ai-botkit-tab-pane ai-botkit-card">
                <div class="ai-botkit-card-header">
                    <h3><?php esc_html_e('Add Banned Keywords', 'ai-botkit-for-lead-generation'); ?></h3>
                    <p><?php esc_html_e('Add words that should be blocked from chat messages', 'ai-botkit-for-lead-generation'); ?></p>
                </div>

                <div class="ai-botkit-card-body">
                    <div class="ai-botkit-form-group">
                        <label for="ai_botkit_banned_keywords" class="ai-botkit-label">
                            <?php esc_html_e('Banned Keywords', 'ai-botkit-for-lead-generation'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="ai_botkit_banned_keywords" 
                            name="ai_botkit_banned_keywords" 
                            class="regular-text" 
                            placeholder="<?php esc_attr_e('badword1, badword2, badword3, ...', 'ai-botkit-for-lead-generation'); ?>" 
                            value="<?php echo esc_attr($banned_keywords_string); ?>" 
                        />
                        <p class="ai-botkit-hint">
                            <?php esc_html_e('Enter comma-separated words that should be blocked. The chatbot will not respond to messages containing these words.', 'ai-botkit-for-lead-generation'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="ai-botkit-card-footer">
                    <input type="submit" class="ai-botkit-btn" name="submit_banned_keywords" value="<?php esc_html_e('Save Banned Keywords', 'ai-botkit-for-lead-generation'); ?>">
                </div>
            </div>

            <!-- Blocked IP Addresses Section -->
            <div class="ai-botkit-tab-pane ai-botkit-card">
                <div class="ai-botkit-card-header">
                    <h3><?php esc_html_e('Blocked IP Addresses', 'ai-botkit-for-lead-generation'); ?></h3>
                    <p><?php esc_html_e('Block access to chatbot from specific IP addresses', 'ai-botkit-for-lead-generation'); ?></p>
                </div>

                <div class="ai-botkit-card-body">
                    <div class="ai-botkit-form-group">
                        <label for="ai_botkit_blocked_ips" class="ai-botkit-label">
                            <?php esc_html_e('Blocked IP Addresses', 'ai-botkit-for-lead-generation'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="ai_botkit_blocked_ips" 
                            name="ai_botkit_blocked_ips" 
                            class="regular-text" 
                            placeholder="<?php esc_attr_e('192.168.1.1, 203.0.113.25, ...', 'ai-botkit-for-lead-generation'); ?>" 
                            value="<?php echo esc_attr($blocked_ips_string); ?>" 
                        />
                        <p class="ai-botkit-hint">
                            <?php esc_html_e('Enter comma-separated IP addresses that should be blocked from accessing the chatbot.', 'ai-botkit-for-lead-generation'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="ai-botkit-card-footer">
                    <input type="submit" class="ai-botkit-btn" name="submit_blocked_ips" value="<?php esc_html_e('Save Blocked IPs', 'ai-botkit-for-lead-generation'); ?>">
                </div>
            </div>
        </div>
    </form>
</div> 