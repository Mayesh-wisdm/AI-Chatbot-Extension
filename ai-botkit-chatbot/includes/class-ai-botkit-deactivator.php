<?php
namespace AI_BotKit\Core;

/**
 * Fired during plugin deactivation
 */
class Deactivator {
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled hooks
        self::clear_scheduled_hooks();
        
        // Clear transients
        self::clear_transients();
    }
    
    /**
     * Clear scheduled hooks
     */
    private static function clear_scheduled_hooks() {
        $hooks = [
            'ai_botkit_cleanup_temp_files',
            'ai_botkit_process_queue',
            'ai_botkit_health_check',
            'ai_botkit_backup',
            'ai_botkit_analytics_cleanup',
            'ai_botkit_wc_sync',
            'ai_botkit_wp_content_sync'
        ];

        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_ai_botkit_') . '%',
                $wpdb->esc_like('_transient_timeout_ai_botkit_') . '%'
            )
        );
    }
} 