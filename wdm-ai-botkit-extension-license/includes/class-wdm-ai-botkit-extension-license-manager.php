<?php

/**
 * License Manager for WDM AI BotKit Extension
 *
 * Handles license validation, activation, and integration with the main AI BotKit plugin.
 * This class ensures the extension only works with a properly licensed AI BotKit installation.
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wdm_Ai_Botkit_Extension_License_Manager {

    /**
     * License option keys
     */
    private $extension_license_key = 'wdm_ai_botkit_extension_license_key';
    private $extension_license_status = 'wdm_ai_botkit_extension_license_status';
    
    /**
     * Plugin identifiers
     */
    private $plugin_slug = 'wdm-ai-botkit-extension';
    private $plugin_name = 'WDM AI BotKit Extension';
    private $store_url;
    private $item_id;
    private $text_domain = 'wdm-ai-botkit-extension';

    /**
     * Constructor
     */
    public function __construct() {
        // Set store URL and item ID based on current site URL
        $this->set_store_configuration();
        add_action('admin_notices', [$this, 'show_license_notices']);
        add_action('plugins_loaded', [$this, 'check_dependencies']);
        
        // Only schedule license validation if we have a valid license
        $this->maybe_schedule_license_check();
        
        add_action('wdm_ai_botkit_extension_license_check_event', [$this, 'maybe_validate_license']);
    }

    /**
     * Set store configuration based on current site URL
     */
    private function set_store_configuration() {
        $current_url = home_url();
        
        // Check if current site URL contains 'wisdmlabs' or 'local'
        if (strpos($current_url, 'wisdmlabs.net') !== false || strpos($current_url, 'local') !== false) {
            // Development/local environment
            $this->store_url = 'https://wordpress-1496509-5716381.cloudwaysapps.com/';
            $this->item_id = 483904; // Extension product ID for dev environment
        } else {
            // Production environment
            $this->store_url = 'https://dev1.edwiser.org/';
            $this->item_id = 573656; // Extension product ID for production
        }
        
    }

    // Register custom interval for 60 seconds
    public static function add_custom_cron_interval($schedules) {
        $schedules['wdm_ai_botkit_extension_60s'] = array(
            'interval' => 60, // 60 seconds
            'display'  => __('Every 60 Seconds', 'wdm-ai-botkit-extension')
        );
        return $schedules;
    }

    /**
     * Get extension license key
     */
    public function get_extension_license_key() {
        return get_option($this->extension_license_key, '');
    }

    /**
     * Get extension license status
     */
    public function get_extension_license_status() {
        return get_option($this->extension_license_status, 'inactive');
    }

    /**
     * Check if extension is fully licensed
     */
    public function is_extension_licensed() {
        return $this->get_extension_license_status() === 'valid';
    }

    /**
     * Activate extension license
     */
    public function activate_extension_license($license_key) {
        $response = $this->remote_request('activate_license', $license_key);
        if ($response && $response['success']) {
            // Check the actual license status from the response
            $license_status = 'valid'; // Default to valid if activation succeeded
            if (isset($response['license'])) {
                $license_status = $response['license'];
            } elseif (isset($response['license_status'])) {
                $license_status = $response['license_status'];
            } elseif (isset($response['status'])) {
                $license_status = $response['status'];
            }
            
            $old_status = $this->get_extension_license_status();
            update_option($this->extension_license_key, $license_key);
            update_option($this->extension_license_status, $license_status);
            
            // Trigger content transformation event
            do_action('wdm_ai_botkit_extension_license_status_changed', $old_status, $license_status);
            
            // Only schedule license validation if the license is actually valid
            if ($license_status === 'valid') {
                $this->schedule_license_check();
            }
            
            return [
                'success' => true,
                'message' => __('Extension license activated successfully.', $this->text_domain)
            ];
        } else {
            update_option($this->extension_license_status, 'invalid');
            return [
                'success' => false,
                'message' => __('Invalid license key or activation failed.', $this->text_domain)
            ];
        }
    }

    /**
     * Deactivate extension license
     */
    public function deactivate_extension_license($license_key) {
        $response = $this->remote_request('deactivate_license', $license_key);
        if ($response && $response['success']) {
            $old_status = $this->get_extension_license_status();
            update_option($this->extension_license_status, 'inactive');
            
            // Trigger content transformation event
            do_action('wdm_ai_botkit_extension_license_status_changed', $old_status, 'inactive');
            
            // Clear license validation schedule and cache after deactivation
            $this->clear_license_check_schedule();
            $this->clear_extension_cache();
            
            return [
                'success' => true,
                'message' => __('Extension license deactivated successfully.', $this->text_domain)
            ];
        }
        return [
            'success' => false,
            'message' => __('License deactivation failed.', $this->text_domain)
        ];
    }

    /**
     * Check if we should schedule license validation
     */
    private function maybe_schedule_license_check() {
        // Only schedule if we have a valid license and no schedule exists
        $current_status = $this->get_extension_license_status();
        if ($current_status === 'valid' && !wp_next_scheduled('wdm_ai_botkit_extension_license_check_event')) {
            $this->schedule_license_check();
        } elseif ($current_status !== 'valid') {
            // Clear any existing schedule if license is not valid
            $this->clear_license_check_schedule();
        }
    }

    /**
     * Schedule license validation
     */
    private function schedule_license_check() {
        // Clear any existing schedule first
        $this->clear_license_check_schedule();
        
        // Schedule new validation (60 seconds for testing, change to 86400 for production)
        wp_schedule_event(time(), 'wdm_ai_botkit_extension_60s', 'wdm_ai_botkit_extension_license_check_event');
    }

    /**
     * Clear license validation schedule
     */
    private function clear_license_check_schedule() {
        $timestamp = wp_next_scheduled('wdm_ai_botkit_extension_license_check_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wdm_ai_botkit_extension_license_check_event');
        }
    }

    /**
     * Check extension license status
     */
    public function check_extension_license($license_key) {
        $response = $this->remote_request('check_license', $license_key);
        if ($response) {
            // Check for license status regardless of success field
            // Some stores return success=false but still provide license status
            if (isset($response['license'])) {
                return $response['license'];
            } elseif (isset($response['license_status'])) {
                return $response['license_status'];
            } elseif (isset($response['status'])) {
                return $response['status'];
            }
            
            // If we have a response but no license status, log it
            if (!$response['success']) {
                
                // Check for consecutive validation failures
                $failure_count = get_option('wdm_ai_botkit_extension_validation_failures', 0);
                $failure_count++;
                update_option('wdm_ai_botkit_extension_validation_failures', $failure_count);
                
                // If we have too many consecutive failures, consider force deactivating
                if ($failure_count >= 5) {
                    // Could implement automatic force deactivation here if needed
                }
            }
        }
        
        return false;
    }

    /**
     * Make remote request to license server
     */
    private function remote_request($action, $license_key) {
        $params = [
            'edd_action' => $action,
            'license'    => $license_key,
            'item_id'    => $this->item_id,
            'url'        => home_url(),
        ];
        
        $response = wp_remote_post($this->store_url, [
            'timeout' => 15,
            'body'    => $params,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data;
    }

    /**
     * Show license notices - only when there are actual issues
     */
    public function show_license_notices() {
        // Only disable notices on the specific AI BotKit extension-license tab page
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
        
        // Disable notices only on the extension-license tab page
        if ($current_page === 'ai-botkit' && $current_tab === 'extension-license') {
            return;
        }
        
        // Check main plugin dependency first
        if (!self::is_ai_botkit_active()) {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__('WDM AI BotKit Extension requires AI BotKit plugin to be installed and activated.', $this->text_domain) . 
                 '</p></div>';
            return;
        }

        // Check extension license - only show notices if there are issues
        $status = $this->get_extension_license_status();
        if ($status === 'invalid') {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__('Your WDM AI BotKit Extension license is invalid or expired. Please enter a valid license key.', $this->text_domain) . 
                 '</p></div>';
        } elseif ($status === 'inactive' || $status === 'deactivated') {
            echo '<div class="notice notice-warning"><p>' . 
                 esc_html__('Your WDM AI BotKit Extension license is not activated. Please activate your license key.', $this->text_domain) . 
                 '</p></div>';
        }
        // Don't show anything if status is 'valid' - that's what the settings page is for
    }

    /**
     * Check dependencies on plugin load
     */
    public function check_dependencies() {
        if (!self::is_ai_botkit_active()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     esc_html__('WDM AI BotKit Extension requires AI BotKit plugin to be installed and activated.', $this->text_domain) . 
                     '</p></div>';
            });
        }
    }

    /**
     * Check if AI BotKit plugin is active (static method for reuse)
     *
     * @since    1.0.0
     * @return   bool
     */
    public static function is_ai_botkit_active() {
        // Check if plugin is active by name
        if (is_plugin_active('ai-botkit/ai-botkit.php')) {
            return true;
        }
        
        // Also check by plugin name in case folder structure changes
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'ai-botkit') !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        $notice_class = 'notice-' . $type;
        echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . 
             esc_html($message) . '</p></div>';
    }

    /**
     * Periodic license validation (runs every 60 seconds for testing)
     * Only updates status if it actually changed and license is valid
     */
    public function maybe_validate_license() {
        // Only run validation if we have a valid license
        $current_status = $this->get_extension_license_status();
        if ($current_status !== 'valid') {
            return; // Don't validate if license is not valid
        }
        
        $last_check = get_option('wdm_ai_botkit_extension_license_last_check', 0);
        $current_time = time();
        // 60 seconds for testing, change to 86400 for production
        if ($current_time - $last_check > 60) {
            $license_key = $this->get_extension_license_key();
            if (!empty($license_key)) {
                $remote_status = $this->check_extension_license($license_key);
                
                // Only update if remote validation succeeded and status actually changed
                if ($remote_status !== false && $current_status !== $remote_status) {
                    update_option($this->extension_license_status, $remote_status);
                    
                    // Reset validation failure count on successful validation
                    delete_option('wdm_ai_botkit_extension_validation_failures');
                    
                    // Trigger content transformation event
                    do_action('wdm_ai_botkit_extension_license_status_changed', $current_status, $remote_status);
                    
                    // If license became invalid, clear the schedule and cache
                    if ($remote_status !== 'valid') {
                        $this->clear_license_check_schedule();
                        $this->clear_extension_cache();
                    }
                }
                
                update_option('wdm_ai_botkit_extension_license_last_check', $current_time);
            }
        }
    }
    
    /**
     * Clear extension cache when license becomes invalid
     */
    public function clear_extension_cache() {
        // Clear any cached data that might be stored
        delete_transient('wdm_ai_botkit_extension_user_courses_cache');
        delete_transient('wdm_ai_botkit_extension_content_cache');
        
        // Clear any other cached data
        delete_option('wdm_ai_botkit_extension_upgrade_available');
        
    }
    
    /**
     * Runtime license check for filter methods
     */
    public function is_extension_licensed_runtime() {
        // Quick check without remote validation
        return $this->is_extension_licensed();
    }
    
    /**
     * Force license validation (for testing purposes)
     */
    public function force_license_validation() {
        $license_key = $this->get_extension_license_key();
        if (!empty($license_key)) {
            $remote_status = $this->check_extension_license($license_key);
            
            if ($remote_status !== false) {
                $current_status = $this->get_extension_license_status();
                
                // Update status if it changed
                if ($current_status !== $remote_status) {
                    update_option($this->extension_license_status, $remote_status);
                    
                    // Force WordPress to clear any object cache
                    wp_cache_flush();
                    
                    // Reset validation failure count on successful validation
                    delete_option('wdm_ai_botkit_extension_validation_failures');
                    
                    // Trigger content transformation event
                    do_action('wdm_ai_botkit_extension_license_status_changed', $current_status, $remote_status);
                    
                    // If license became invalid, clear the schedule and cache
                    if ($remote_status !== 'valid') {
                        $this->clear_license_check_schedule();
                        $this->clear_extension_cache();
                    }
                    
                }
            }
            
            return $remote_status;
        }
        return false;
    }
    
    /**
     * Force immediate deactivation (bypasses remote validation)
     * Used for critical cases or when remote validation fails
     */
    public function force_immediate_deactivation($reason = 'manual') {
        $old_status = $this->get_extension_license_status();
        
        // Update status to inactive
        update_option($this->extension_license_status, 'inactive');
        
        // Clear schedule and cache
        $this->clear_license_check_schedule();
        $this->clear_extension_cache();
        
        // Trigger content transformation event
        do_action('wdm_ai_botkit_extension_license_status_changed', $old_status, 'inactive');
        
        // Log the forced deactivation
        
        return [
            'success' => true,
            'message' => __('Extension license force deactivated successfully.', $this->text_domain)
        ];
    }
    
    /**
     * Get license status for display
     */
    public function get_license_status_display() {
        $status = $this->get_extension_license_status();
        switch ($status) {
            case 'valid':
                return [
                    'status' => 'valid',
                    'message' => __('Extension license is valid', $this->text_domain),
                    'class' => 'valid'
                ];
            case 'invalid':
                return [
                    'status' => 'invalid',
                    'message' => __('Extension license is invalid', $this->text_domain),
                    'class' => 'invalid'
                ];
            case 'deactivated':
                return [
                    'status' => 'inactive',
                    'message' => __('Extension license is deactivated', $this->text_domain),
                    'class' => 'warning'
                ];
            default:
                return [
                    'status' => 'inactive',
                    'message' => __('Extension license not activated', $this->text_domain),
                    'class' => 'warning'
                ];
        }
    }
} 

// Register the custom cron interval
add_filter('cron_schedules', ['Wdm_Ai_Botkit_Extension_License_Manager', 'add_custom_cron_interval']); 
