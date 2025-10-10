<?php

/**
 * Content Transformer for WDM AI BotKit Extension
 *
 * Handles automatic content transformation based on license status:
 * - Disables extension features and clears cache when license expires/deactivates
 * - Enables content upgrade and auto-sync when license is reactivated
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wdm_Ai_Botkit_Extension_Content_Transformer {

    /**
     * License Manager instance
     */
    private $license_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
        
        // Hook into license status changes
        add_action('wdm_ai_botkit_extension_license_status_changed', [$this, 'handle_license_status_change'], 10, 2);
    }

    /**
     * Handle license status changes
     */
    public function handle_license_status_change($old_status, $new_status) {
        
        // Define all invalid status types
        $invalid_statuses = ['expired', 'inactive', 'deactivated', 'revoked', 'suspended'];
        
        // License expired/deactivated - clear cache and disable features
        if ($old_status === 'valid' && in_array($new_status, $invalid_statuses)) {
            $this->handle_license_deactivation();
        }
        
        // License reactivated - automatically start sync
        elseif (in_array($old_status, $invalid_statuses) && $new_status === 'valid') {
            $this->enable_content_upgrade();
            $this->auto_start_learndash_sync();
        }
    }

    /**
     * Handle license deactivation (clear cache and disable features)
     */
    public function handle_license_deactivation() {
        
        try {
            // Clear all extension caches using the license manager method
            $this->license_manager->clear_extension_cache();
            
            // Store deactivation status
            update_option('wdm_ai_botkit_extension_license_deactivated', [
                'timestamp' => current_time('mysql'),
                'status' => 'deactivated'
            ]);
            
            
        } catch (Exception $e) {
        }
    }

    /**
     * Enable content upgrade option
     */
    public function enable_content_upgrade() {
        
        // Store upgrade availability
        update_option('wdm_ai_botkit_extension_upgrade_available', [
            'timestamp' => current_time('mysql'),
            'status' => 'available'
        ]);
        
        // Add admin notice
        add_action('admin_notices', [$this, 'show_upgrade_notice']);
    }

    /**
     * Show upgrade notice
     */
    public function show_upgrade_notice() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'ai-botkit') !== false) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>AI BotKit Extension:</strong> Your license has been reactivated! ';
            echo 'Your LearnDash content sync is now available. ';
            echo '<a href="' . admin_url('admin.php?page=ai-botkit&tab=extension-license') . '">Click here to sync comprehensive content</a>.</p>';
            echo '</div>';
        }
    }

    /**
     * Check if content upgrade is available
     */
    public function is_upgrade_available() {
        $upgrade_data = get_option('wdm_ai_botkit_extension_upgrade_available', false);
        return $upgrade_data && $upgrade_data['status'] === 'available';
    }

    /**
     * Mark upgrade as completed
     */
    public function mark_upgrade_completed() {
        update_option('wdm_ai_botkit_extension_upgrade_available', [
            'timestamp' => current_time('mysql'),
            'status' => 'completed'
        ]);
    }

    /**
     * Get content transformation status
     */
    public function get_transformation_status() {
        $deactivated_data = get_option('wdm_ai_botkit_extension_license_deactivated', false);
        $upgrade_data = get_option('wdm_ai_botkit_extension_upgrade_available', false);
        
        return [
            'deactivated' => $deactivated_data,
            'upgrade_available' => $upgrade_data && $upgrade_data['status'] === 'available',
            'upgrade_completed' => $upgrade_data && $upgrade_data['status'] === 'completed'
        ];
    }

    /**
     * Automatically start LearnDash sync when license is reactivated
     */
    private function auto_start_learndash_sync() {
        
        // Check if LearnDash is active
        if (!defined('LEARNDASH_VERSION')) {
            return;
        }
        
        // Check if AI BotKit is available
        if (!class_exists('AI_BotKit\Core\RAG_Engine')) {
            return;
        }
        
        // Schedule the sync to run in the background
        wp_schedule_single_event(time() + 10, 'wdm_ai_botkit_extension_auto_sync');
        
    }
}
