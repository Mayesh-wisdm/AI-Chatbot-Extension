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
            <h2><?php _e('LearnDash Course Sync', 'wdm-ai-botkit-extension'); ?></h2>
            
            <?php if ($upgrade_available): ?>
                <div class="ai-botkit-upgrade-notice">
                    <p class="description">
                        <strong><?php _e('Content Upgrade Available!', 'wdm-ai-botkit-extension'); ?></strong><br>
                        <?php _e('Your LearnDash content was downgraded when the license expired. Click below to upgrade back to comprehensive content.', 'wdm-ai-botkit-extension'); ?>
                    </p>
                </div>
            <?php elseif ($upgrade_completed): ?>
                <div class="ai-botkit-upgrade-completed">
                    <p class="description">
                        <strong><?php _e('Content Upgrade Completed!', 'wdm-ai-botkit-extension'); ?></strong><br>
                        <?php _e('Your LearnDash courses now have comprehensive content (lessons, topics, quizzes).', 'wdm-ai-botkit-extension'); ?>
                    </p>
                </div>
            <?php else: ?>
                <p class="description">
                    <?php _e('Upgrade LearnDash courses that are already in your chatbot\'s knowledge base with comprehensive content (lessons, topics, quizzes) instead of basic post data.', 'wdm-ai-botkit-extension'); ?>
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
                        _e('Upgrade LearnDash Content', 'wdm-ai-botkit-extension');
                    } else {
                        _e('Upgrade LearnDash Content in Knowledge Base', 'wdm-ai-botkit-extension');
                    }
                    ?>
                </button>
                
                <div id="sync-progress" class="ai-botkit-sync-progress" style="display: none;">
                    <div class="ai-botkit-progress-bar">
                        <div class="ai-botkit-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="ai-botkit-progress-text">
                        <span id="sync-status"><?php _e('Preparing content upgrade...', 'wdm-ai-botkit-extension'); ?></span>
                        <span id="sync-count">0 / 0</span>
                    </div>
                </div>
            </div>
            
            <div id="sync-results" class="ai-botkit-sync-results" style="display: none;">
                <h4><?php _e('Content Upgrade Results', 'wdm-ai-botkit-extension'); ?></h4>
                <div id="sync-results-content"></div>
            </div>
        </div>
        <?php endif; ?>
        
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // LearnDash Sync functionality
    $('#learndash-sync-btn').on('click', function() {
        var $btn = $(this);
        var $progress = $('#sync-progress');
        var $results = $('#sync-results');
        var nonce = $btn.data('nonce');
        
        // Disable button and show progress
        $btn.prop('disabled', true);
        $progress.show();
        $results.hide();
        
        // Start sync process
        startLearndashSync(nonce);
    });
    
    function startLearndashSync(nonce) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'learndash_sync_courses',
                sync_action: 'start',
                bot_id: 0, // Will auto-detect first bot if not specified
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    updateProgress(0, response.data.total_courses, 'Starting content upgrade...');
                    processSyncBatch(nonce, response.data.total_courses);
                } else {
                    showError(response.data.message || 'Failed to start content upgrade');
                }
            },
            error: function() {
                showError('Network error occurred');
            }
        });
    }
    
    function processSyncBatch(nonce, totalCourses) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'learndash_sync_courses',
                sync_action: 'process',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var progress = Math.round((data.current_index / data.total_courses) * 100);
                    
                    updateProgress(data.current_index, data.total_courses, data.message);
                    
                    if (data.is_complete) {
                        showResults(data);
                        $('#learndash-sync-btn').prop('disabled', false);
                    } else {
                        // Continue processing
                        setTimeout(function() {
                            processSyncBatch(nonce, totalCourses);
                        }, 1000);
                    }
                } else {
                    showError(response.data.message || 'Sync failed');
                }
            },
            error: function() {
                showError('Network error occurred during sync');
            }
        });
    }
    
    function updateProgress(current, total, message) {
        var progress = Math.round((current / total) * 100);
        $('.ai-botkit-progress-fill').css('width', progress + '%');
        $('#sync-status').text(message);
        $('#sync-count').text(current + ' / ' + total);
    }
    
    function showResults(data) {
        var $results = $('#sync-results');
        var $content = $('#sync-results-content');
        
        var html = '<div class="ai-botkit-sync-summary">';
        html += '<p><strong>Sync Completed Successfully!</strong></p>';
        html += '<ul>';
        html += '<li>Total courses processed: ' + data.total_processed + '</li>';
        html += '<li>Total courses found: ' + data.total_courses + '</li>';
        
        if (data.errors && data.errors.length > 0) {
            html += '<li>Errors: ' + data.errors.length + '</li>';
        }
        
        html += '</ul>';
        
        if (data.errors && data.errors.length > 0) {
            html += '<div class="ai-botkit-sync-errors">';
            html += '<h5>Errors encountered:</h5>';
            html += '<ul>';
            data.errors.forEach(function(error) {
                html += '<li>Course ID ' + error.course_id + ': ' + error.error + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $content.html(html);
        $results.show();
        $('#sync-progress').hide();
    }
    
    function showError(message) {
        $('#sync-progress').hide();
        $('#learndash-sync-btn').prop('disabled', false);
        
        var $results = $('#sync-results');
        var $content = $('#sync-results-content');
        
        $content.html('<div class="notice notice-error"><p>' + message + '</p></div>');
        $results.show();
    }
});
</script>

<style>
.ai-botkit-sync-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ai-botkit-sync-controls {
    margin: 15px 0;
}

.ai-botkit-sync-progress {
    margin: 15px 0;
}

.ai-botkit-progress-bar {
    background: #e5e7eb;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.ai-botkit-progress-fill {
    background: #008858;
    height: 100%;
    transition: width 0.3s ease;
}

.ai-botkit-progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #6b7280;
}

.ai-botkit-sync-results {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 15px;
    margin-top: 15px;
}

.ai-botkit-sync-summary {
    margin-bottom: 15px;
}

.ai-botkit-sync-errors {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}

.ai-botkit-sync-errors h5 {
    color: #dc2626;
    margin: 0 0 10px 0;
}

.ai-botkit-sync-errors ul {
    margin: 0;
    padding-left: 20px;
}

.ai-botkit-sync-errors li {
    color: #dc2626;
    font-size: 13px;
}

.ai-botkit-upgrade-notice {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.ai-botkit-upgrade-notice .description {
    margin: 0;
    color: #92400e;
}

.ai-botkit-upgrade-completed {
    background: #d1fae5;
    border: 1px solid #10b981;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.ai-botkit-upgrade-completed .description {
    margin: 0;
    color: #065f46;
}
</style> 