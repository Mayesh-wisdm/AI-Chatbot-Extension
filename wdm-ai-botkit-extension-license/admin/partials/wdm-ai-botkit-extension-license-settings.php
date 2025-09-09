<?php
/**
 * License Settings Page for WDM AI BotKit Extension
 * Integrated with AI BotKit admin interface
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/admin/partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get license manager instance
try {
    $license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
    $license_status = $license_manager->get_license_status_display();
    $license_key = $license_manager->get_extension_license_key();
} catch (Exception $e) {
    echo '<div class="notice notice-error"><p>Error initializing license manager: ' . esc_html($e->getMessage()) . '</p></div>';
    return;
}
?>

<div class="ai-botkit-extension-license-container">
    <div class="ai-botkit-dashboard-header">
        <h1 class="ai-botkit-dashboard-title"><?php _e('Extension License', 'wdm-ai-botkit-extension'); ?></h1>
    </div>
    
    <div class="ai-botkit-extension-license-content">
        <div class="ai-botkit-license-status-section">
            <h2><?php _e('License Status', 'wdm-ai-botkit-extension'); ?></h2>
            
            <!-- Extension License Status -->
            <div class="ai-botkit-license-status-item">
                <h3><?php _e('Extension License', 'wdm-ai-botkit-extension'); ?></h3>
                <div id="extension-license-status" class="ai-botkit-license-status <?php echo esc_attr($license_status['class']); ?>">
                    <span class="dashicons dashicons-<?php echo $license_status['status'] === 'valid' ? 'yes-alt' : 'no-alt'; ?>"></span>
                    <?php echo esc_html($license_status['message']); ?>
                </div>
            </div>
        </div>
        
        <!-- License Key Management -->
        <div class="ai-botkit-license-key-section">
            <h2><?php _e('License Key Management', 'wdm-ai-botkit-extension'); ?></h2>
            
            <form id="wdm-extension-license-form" method="post" action="">
                <?php wp_nonce_field('wdm_ai_botkit_extension_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wdm_ai_botkit_extension_license_key"><?php _e('License Key', 'wdm-ai-botkit-extension'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="wdm_ai_botkit_extension_license_key" 
                                   name="wdm_ai_botkit_extension_license_key" 
                                   value="<?php echo esc_attr($license_key); ?>" 
                                   class="regular-text" 
                                   placeholder="<?php _e('Enter your license key', 'wdm-ai-botkit-extension'); ?>" />
                            <p class="description">
                                <?php _e('Enter your WDM AI BotKit Extension license key to activate premium features.', 'wdm-ai-botkit-extension'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php if ($license_manager->get_extension_license_status() === 'valid'): ?>
                        <input type="submit" 
                               name="wdm_ai_botkit_extension_license_action" 
                               value="<?php _e('Deactivate License', 'wdm-ai-botkit-extension'); ?>" 
                               class="button button-secondary" 
                               onclick="return confirm('<?php _e('Are you sure you want to deactivate the license?', 'wdm-ai-botkit-extension'); ?>')" />
                    <?php else: ?>
                        <input type="submit" 
                               name="wdm_ai_botkit_extension_license_action" 
                               value="<?php _e('Activate License', 'wdm-ai-botkit-extension'); ?>" 
                               class="button button-primary" />
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <!-- Feature Information -->
        <div class="ai-botkit-features-section">
            <h2><?php _e('Extension Features', 'wdm-ai-botkit-extension'); ?></h2>
            
            <div class="ai-botkit-features-list">
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-groups"></span>
                    <h4><?php _e('User Course Awareness', 'wdm-ai-botkit-extension'); ?></h4>
                    <p><?php _e('Provides AI BotKit with information about user\'s enrolled LearnDash courses for personalized responses.', 'wdm-ai-botkit-extension'); ?></p>
                </div>
                
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-book"></span>
                    <h4><?php _e('LearnDash Content Integration', 'wdm-ai-botkit-extension'); ?></h4>
                    <p><?php _e('Enhances AI responses with detailed course, lesson, topic, and quiz content from LearnDash.', 'wdm-ai-botkit-extension'); ?></p>
                </div>
                
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h4><?php _e('Personalized Learning Experience', 'wdm-ai-botkit-extension'); ?></h4>
                    <p><?php _e('Delivers context-aware responses based on user\'s learning progress and course enrollment.', 'wdm-ai-botkit-extension'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Support Information -->
        <div class="ai-botkit-support-section">
            <h2><?php _e('Support & Documentation', 'wdm-ai-botkit-extension'); ?></h2>
            
            <div class="ai-botkit-support-links">
                <a href="https://wisdmlabs.com/support/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-sos"></span>
                    <?php _e('Get Support', 'wdm-ai-botkit-extension'); ?>
                </a>
                
                <a href="https://wisdmlabs.com/docs/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php _e('Documentation', 'wdm-ai-botkit-extension'); ?>
                </a>
                
                <a href="https://wisdmlabs.com/contact/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e('Contact Us', 'wdm-ai-botkit-extension'); ?>
                </a>
            </div>
        </div>
    </div>
</div> 