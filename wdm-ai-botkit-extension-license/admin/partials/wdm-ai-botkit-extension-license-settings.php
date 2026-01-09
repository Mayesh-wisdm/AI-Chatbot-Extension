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

// Get license manager instance with cache busting
try {
    // Clear any WordPress object cache to ensure fresh data
    wp_cache_flush();
    
    $license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
    $license_status = $license_manager->get_license_status_display();
    $license_key = $license_manager->get_extension_license_key();
    
    // Log current status for debugging
} catch (Exception $e) {
    echo '<div class="notice notice-error"><p>Error initializing license manager: ' . esc_html($e->getMessage()) . '</p></div>';
    return;
}
?>

<div class="ai-botkit-extension-license-container">
    <div class="ai-botkit-extension-license-content">
        <div class="ai-botkit-license-status-section">
            <h2><?php _e('License Status', 'wdm-knowvault-extension'); ?></h2>
            
            <!-- Extension License Status -->
            <div class="ai-botkit-license-status-item">
                <h3><?php _e('Extension License', 'wdm-knowvault-extension'); ?></h3>
                <div id="extension-license-status" class="ai-botkit-license-status <?php echo esc_attr($license_status['class']); ?>">
                    <span class="dashicons dashicons-<?php echo $license_status['status'] === 'valid' ? 'yes-alt' : 'no-alt'; ?>"></span>
                    <?php echo esc_html($license_status['message']); ?>
                </div>
            </div>
        </div>
        
        <!-- License Key Management -->
        <div class="ai-botkit-license-key-section">
            <h2><?php _e('License Key Management', 'wdm-knowvault-extension'); ?></h2>
            
            <form id="wdm-extension-license-form" method="post" action="">
                <?php wp_nonce_field('wdm_ai_botkit_extension_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wdm_ai_botkit_extension_license_key"><?php _e('License Key', 'wdm-knowvault-extension'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="wdm_ai_botkit_extension_license_key" 
                                   name="wdm_ai_botkit_extension_license_key" 
                                   value="<?php echo esc_attr($license_key); ?>" 
                                   class="regular-text" 
                                   placeholder="<?php _e('Enter your license key', 'wdm-knowvault-extension'); ?>" />
                            <p class="description">
                                <?php _e('Enter your WDM KnowVault Extension for LearnDash license key to activate premium features.', 'wdm-knowvault-extension'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php if ($license_manager->get_extension_license_status() === 'valid'): ?>
                        <input type="submit" 
                               name="wdm_ai_botkit_extension_license_action" 
                               value="<?php _e('Deactivate License', 'wdm-knowvault-extension'); ?>" 
                               class="button button-secondary" 
                               onclick="return confirm('<?php _e('Are you sure you want to deactivate the license?', 'wdm-knowvault-extension'); ?>')" />
                    <?php else: ?>
                        <input type="submit" 
                               name="wdm_ai_botkit_extension_license_action" 
                               value="<?php _e('Activate License', 'wdm-knowvault-extension'); ?>" 
                               class="button button-primary" />
                    <?php endif; ?>
                    
                    <button type="button" 
                            id="check-license-status" 
                            class="button button-secondary" 
                            style="margin-left: 10px;">
                        <?php _e('Check License Status', 'wdm-knowvault-extension'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- LearnDash Sync Section -->
        <?php if ($license_manager->get_extension_license_status() === 'valid'): ?>
        <?php 
        // Check if content transformer is available
        $content_transformer_available = class_exists('Wdm_Ai_Botkit_Extension_Content_Transformer');
        $transformation_status = $content_transformer_available ? (new Wdm_Ai_Botkit_Extension_Content_Transformer())->get_transformation_status() : null;
        $upgrade_available = $transformation_status && $transformation_status['upgrade_available'];
        $upgrade_completed = $transformation_status && $transformation_status['upgrade_completed'];
        ?>
        <div class="ai-botkit-sync-section">
            <h2><?php _e('LearnDash Course Sync', 'wdm-knowvault-extension'); ?></h2>
            
            <?php if ($upgrade_available): ?>
                <div class="ai-botkit-upgrade-notice">
                    <p class="description">
                        <strong><?php _e('Content Upgrade Available!', 'wdm-knowvault-extension'); ?></strong><br>
                        <?php _e('Your LearnDash content sync was disabled when the license expired. Click below to re-enable comprehensive content sync.', 'wdm-knowvault-extension'); ?>
                    </p>
                </div>
            <?php elseif ($upgrade_completed): ?>
                <div class="ai-botkit-upgrade-completed">
                    <p class="description">
                        <strong><?php _e('Content Upgrade Completed!', 'wdm-knowvault-extension'); ?></strong><br>
                        <?php _e('Your LearnDash courses now have comprehensive content (lessons, topics, quizzes).', 'wdm-knowvault-extension'); ?>
                    </p>
                </div>
            <?php else: ?>
                <p class="description">
                    <?php _e('Upgrade LearnDash courses that are already in your chatbot\'s knowledge base with comprehensive content (lessons, topics, quizzes) instead of basic post data.', 'wdm-knowvault-extension'); ?>
                </p>
            <?php endif; ?>
            
            <div class="ai-botkit-sync-controls">
                <button type="button" 
                        id="learndash-sync-btn" 
                        class="button button-primary"
                        data-nonce="<?php echo wp_create_nonce('learndash_sync_courses'); ?>">
                    <span class="dashicons dashicons-<?php echo $upgrade_available ? 'upload' : 'update'; ?>"></span>
                    <?php 
                    if ($upgrade_available) {
                        _e('Upgrade LearnDash Content', 'wdm-knowvault-extension');
                    } else {
                        _e('Upgrade LearnDash Content in Knowledge Base', 'wdm-knowvault-extension');
                    }
                    ?>
                </button>
                
                <div id="sync-progress" class="ai-botkit-sync-progress" style="display: none;">
                    <div class="ai-botkit-progress-bar">
                        <div class="ai-botkit-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="ai-botkit-progress-text">
                        <span id="sync-status"><?php _e('Preparing content upgrade...', 'wdm-knowvault-extension'); ?></span>
                        <span id="sync-count">0 / 0</span>
                    </div>
                </div>
            </div>
            
            <div id="sync-results" class="ai-botkit-sync-results" style="display: none;">
                <h4><?php _e('Content Upgrade Results', 'wdm-knowvault-extension'); ?></h4>
                <div id="sync-results-content"></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Feature Information -->
        <div class="ai-botkit-features-section">
            <h2><?php _e('Extension Features', 'wdm-knowvault-extension'); ?></h2>
            
            <div class="ai-botkit-features-list">
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-groups"></span>
                    <h4><?php _e('User Course Awareness', 'wdm-knowvault-extension'); ?></h4>
                    <p><?php _e('Provides AI BotKit with information about user\'s enrolled LearnDash courses for personalized responses.', 'wdm-knowvault-extension'); ?></p>
                </div>
                
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-book"></span>
                    <h4><?php _e('LearnDash Content Integration', 'wdm-knowvault-extension'); ?></h4>
                    <p><?php _e('Enhances AI responses with detailed course, lesson, topic, and quiz content from LearnDash.', 'wdm-knowvault-extension'); ?></p>
                </div>
                
                <div class="ai-botkit-feature-item">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h4><?php _e('Personalized Learning Experience', 'wdm-knowvault-extension'); ?></h4>
                    <p><?php _e('Delivers context-aware responses based on user\'s learning progress and course enrollment.', 'wdm-knowvault-extension'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Support Information - Hidden for now -->
        <!-- 
        <div class="ai-botkit-support-section">
            <h2><?php _e('Support & Documentation', 'wdm-knowvault-extension'); ?></h2>
            
            <div class="ai-botkit-support-links">
                <a href="https://wisdmlabs.com/support/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-sos"></span>
                    <?php _e('Get Support', 'wdm-knowvault-extension'); ?>
                </a>
                
                <a href="https://wisdmlabs.com/docs/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php _e('Documentation', 'wdm-knowvault-extension'); ?>
                </a>
                
                <a href="https://wisdmlabs.com/contact/" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e('Contact Us', 'wdm-knowvault-extension'); ?>
                </a>
            </div>
        </div>
        -->
    </div>
</div> 
