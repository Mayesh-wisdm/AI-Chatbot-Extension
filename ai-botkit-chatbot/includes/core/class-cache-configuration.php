<?php
namespace AI_BotKit\Core;

/**
 * Cache Configuration Class
 * 
 * Manages cache configuration settings and expiration times
 * for different types of cached data.
 */
class Cache_Configuration {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Default cache expiration times (in seconds)
     */
    private $default_expirations = [
        'post_type_counts' => 300,        // 5 minutes
        'public_post_types' => 600,       // 10 minutes
        'document_stats' => 300,          // 5 minutes
        'chatbot_stats' => 300,           // 5 minutes
        'migration_status' => 120,        // 2 minutes
        'pinecone_stats' => 60,           // 1 minute
        'content_types' => 300,           // 5 minutes
        'knowledge_base_data' => 120,     // 2 minutes
        'chatbot_data' => 300,            // 5 minutes
        'ajax_responses' => 120,          // 2 minutes
        'admin_interface_data' => 180,    // 3 minutes
        'settings_data' => 600,           // 10 minutes
    ];
    
    /**
     * Cache groups
     */
    private $cache_groups = [
        'ai_botkit_main' => 'Main plugin cache',
        'ai_botkit_ajax' => 'AJAX response cache',
        'ai_botkit_migration' => 'Migration data cache',
        'ai_botkit_stats' => 'Statistics cache',
        'ai_botkit_admin' => 'Admin interface cache'
    ];
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Private constructor for singleton pattern
    }
    
    /**
     * Get singleton instance
     * 
     * @return Cache_Configuration
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get cache settings
     * 
     * @return array Cache settings
     */
    public function get_cache_settings() {
        return [
            'expirations' => $this->get_all_expirations(),
            'groups' => $this->cache_groups,
            'enabled' => $this->is_caching_enabled(),
            'compression' => $this->is_compression_enabled(),
            'persistent' => $this->is_persistent_cache_enabled()
        ];
    }
    
    /**
     * Get expiration time for specific cache type
     * 
     * @param string $cache_type Cache type
     * @return int Expiration time in seconds
     */
    public function get_expiration($cache_type) {
        $custom_expirations = get_option('ai_botkit_cache_expirations', []);
        
        if (isset($custom_expirations[$cache_type])) {
            return (int) $custom_expirations[$cache_type];
        }
        
        return $this->default_expirations[$cache_type] ?? 300; // Default 5 minutes
    }
    
    /**
     * Get all expiration times
     * 
     * @return array All expiration times
     */
    public function get_all_expirations() {
        $custom_expirations = get_option('ai_botkit_cache_expirations', []);
        
        return array_merge($this->default_expirations, $custom_expirations);
    }
    
    /**
     * Set custom expiration time for cache type
     * 
     * @param string $cache_type Cache type
     * @param int $expiration Expiration time in seconds
     * @return bool True on success, false on failure
     */
    public function set_expiration($cache_type, $expiration) {
        $custom_expirations = get_option('ai_botkit_cache_expirations', []);
        $custom_expirations[$cache_type] = (int) $expiration;
        
        return update_option('ai_botkit_cache_expirations', $custom_expirations);
    }
    
    /**
     * Reset expiration times to defaults
     * 
     * @return bool True on success, false on failure
     */
    public function reset_expirations() {
        return delete_option('ai_botkit_cache_expirations');
    }
    
    /**
     * Check if caching is enabled
     * 
     * @return bool True if caching is enabled
     */
    public function is_caching_enabled() {
        return get_option('ai_botkit_cache_enabled', true);
    }
    
    /**
     * Enable or disable caching
     * 
     * @param bool $enabled Whether to enable caching
     * @return bool True on success, false on failure
     */
    public function set_caching_enabled($enabled) {
        return update_option('ai_botkit_cache_enabled', (bool) $enabled);
    }
    
    /**
     * Check if compression is enabled
     * 
     * @return bool True if compression is enabled
     */
    public function is_compression_enabled() {
        return get_option('ai_botkit_cache_compression', false);
    }
    
    /**
     * Enable or disable compression
     * 
     * @param bool $enabled Whether to enable compression
     * @return bool True on success, false on failure
     */
    public function set_compression_enabled($enabled) {
        return update_option('ai_botkit_cache_compression', (bool) $enabled);
    }
    
    /**
     * Check if persistent cache is enabled
     * 
     * @return bool True if persistent cache is enabled
     */
    public function is_persistent_cache_enabled() {
        return get_option('ai_botkit_persistent_cache', true);
    }
    
    /**
     * Enable or disable persistent cache
     * 
     * @param bool $enabled Whether to enable persistent cache
     * @return bool True on success, false on failure
     */
    public function set_persistent_cache_enabled($enabled) {
        return update_option('ai_botkit_persistent_cache', (bool) $enabled);
    }
    
    /**
     * Get cache group for specific cache type
     * 
     * @param string $cache_type Cache type
     * @return string Cache group
     */
    public function get_cache_group($cache_type) {
        $group_mapping = [
            'post_type_counts' => 'ai_botkit_main',
            'public_post_types' => 'ai_botkit_main',
            'document_stats' => 'ai_botkit_stats',
            'chatbot_stats' => 'ai_botkit_stats',
            'migration_status' => 'ai_botkit_migration',
            'pinecone_stats' => 'ai_botkit_migration',
            'content_types' => 'ai_botkit_migration',
            'knowledge_base_data' => 'ai_botkit_ajax',
            'chatbot_data' => 'ai_botkit_ajax',
            'ajax_responses' => 'ai_botkit_ajax',
            'admin_interface_data' => 'ai_botkit_admin',
            'settings_data' => 'ai_botkit_admin'
        ];
        
        return $group_mapping[$cache_type] ?? 'ai_botkit_main';
    }
    
    /**
     * Get cache key prefix for specific cache type
     * 
     * @param string $cache_type Cache type
     * @return string Cache key prefix
     */
    public function get_cache_prefix($cache_type) {
        $prefix_mapping = [
            'post_type_counts' => 'ptc_',
            'public_post_types' => 'ppt_',
            'document_stats' => 'ds_',
            'chatbot_stats' => 'cs_',
            'migration_status' => 'ms_',
            'pinecone_stats' => 'ps_',
            'content_types' => 'ct_',
            'knowledge_base_data' => 'kbd_',
            'chatbot_data' => 'cd_',
            'ajax_responses' => 'ar_',
            'admin_interface_data' => 'aid_',
            'settings_data' => 'sd_'
        ];
        
        return $prefix_mapping[$cache_type] ?? 'ai_botkit_';
    }
    
    /**
     * Get cache performance settings
     * 
     * @return array Performance settings
     */
    public function get_performance_settings() {
        return [
            'max_cache_size' => get_option('ai_botkit_max_cache_size', 50), // MB
            'cache_cleanup_interval' => get_option('ai_botkit_cache_cleanup_interval', 3600), // seconds
            'cache_warming_enabled' => get_option('ai_botkit_cache_warming_enabled', true),
            'cache_monitoring_enabled' => get_option('ai_botkit_cache_monitoring_enabled', true),
            'cache_debug_enabled' => get_option('ai_botkit_cache_debug_enabled', false)
        ];
    }
    
    /**
     * Set cache performance setting
     * 
     * @param string $setting Setting name
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function set_performance_setting($setting, $value) {
        $option_name = 'ai_botkit_' . $setting;
        return update_option($option_name, $value);
    }
    
    /**
     * Get cache invalidation settings
     * 
     * @return array Invalidation settings
     */
    public function get_invalidation_settings() {
        return [
            'auto_invalidate_on_post_save' => get_option('ai_botkit_auto_invalidate_on_post_save', true),
            'auto_invalidate_on_post_delete' => get_option('ai_botkit_auto_invalidate_on_post_delete', true),
            'auto_invalidate_on_settings_change' => get_option('ai_botkit_auto_invalidate_on_settings_change', true),
            'invalidation_delay' => get_option('ai_botkit_invalidation_delay', 0) // seconds
        ];
    }
    
    /**
     * Set cache invalidation setting
     * 
     * @param string $setting Setting name
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function set_invalidation_setting($setting, $value) {
        $option_name = 'ai_botkit_' . $setting;
        return update_option($option_name, $value);
    }
    
    /**
     * Get cache statistics settings
     * 
     * @return array Statistics settings
     */
    public function get_statistics_settings() {
        return [
            'track_hit_ratio' => get_option('ai_botkit_track_hit_ratio', true),
            'track_memory_usage' => get_option('ai_botkit_track_memory_usage', true),
            'track_response_times' => get_option('ai_botkit_track_response_times', true),
            'statistics_retention_days' => get_option('ai_botkit_statistics_retention_days', 30)
        ];
    }
    
    /**
     * Set cache statistics setting
     * 
     * @param string $setting Setting name
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function set_statistics_setting($setting, $value) {
        $option_name = 'ai_botkit_' . $setting;
        return update_option($option_name, $value);
    }
    
    /**
     * Get all cache configuration
     * 
     * @return array Complete cache configuration
     */
    public function get_all_configuration() {
        return [
            'settings' => $this->get_cache_settings(),
            'expirations' => $this->get_all_expirations(),
            'performance' => $this->get_performance_settings(),
            'invalidation' => $this->get_invalidation_settings(),
            'statistics' => $this->get_statistics_settings()
        ];
    }
    
    /**
     * Reset all cache configuration to defaults
     * 
     * @return bool True on success, false on failure
     */
    public function reset_all_configuration() {
        $options_to_delete = [
            'ai_botkit_cache_expirations',
            'ai_botkit_cache_enabled',
            'ai_botkit_cache_compression',
            'ai_botkit_persistent_cache',
            'ai_botkit_max_cache_size',
            'ai_botkit_cache_cleanup_interval',
            'ai_botkit_cache_warming_enabled',
            'ai_botkit_cache_monitoring_enabled',
            'ai_botkit_cache_debug_enabled',
            'ai_botkit_auto_invalidate_on_post_save',
            'ai_botkit_auto_invalidate_on_post_delete',
            'ai_botkit_auto_invalidate_on_settings_change',
            'ai_botkit_invalidation_delay',
            'ai_botkit_track_hit_ratio',
            'ai_botkit_track_memory_usage',
            'ai_botkit_track_response_times',
            'ai_botkit_statistics_retention_days'
        ];
        
        $success = true;
        foreach ($options_to_delete as $option) {
            if (!delete_option($option)) {
                $success = false;
            }
        }
        
        return $success;
    }
}
