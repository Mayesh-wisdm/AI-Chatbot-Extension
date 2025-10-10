<?php
namespace AI_BotKit\Core;

/**
 * Performance Configuration Manager
 * 
 * Manages configuration settings for all performance optimization modules
 * and provides centralized configuration management.
 */
class Performance_Configuration_Manager {
    
    /**
     * Configuration options
     */
    private $config_options = [];
    
    /**
     * Default configuration
     */
    private $default_config = [
        'optimization_status' => 'inactive',
        'cache_enabled' => true,
        'performance_monitoring' => true,
        'error_handling' => true,
        'database_optimization' => true,
        'ajax_optimization' => true,
        'migration_optimization' => true,
        'admin_interface_optimization' => true,
        'wordpress_content_optimization' => true,
        'cache_expiration' => [
            'database' => 300,
            'ajax' => 120,
            'migration' => 300,
            'admin_interface' => 300,
            'content' => 600,
            'performance' => 900
        ],
        'performance_thresholds' => [
            'operation_time' => 2.0,
            'ajax_response_time' => 1.0,
            'interface_load_time' => 2.0,
            'cache_hit_ratio' => 0.7
        ],
        'monitoring_settings' => [
            'enable_monitoring' => true,
            'monitoring_interval' => 300,
            'max_operation_history' => 1000,
            'performance_alerts' => true
        ],
        'error_handling_settings' => [
            'enable_error_logging' => true,
            'user_friendly_messages' => true,
            'error_statistics' => true,
            'max_error_history' => 1000
        ]
    ];
    
    /**
     * Initialize the performance configuration manager
     */
    public function __construct() {
        $this->load_configuration();
    }
    
    /**
     * Load configuration from database
     */
    private function load_configuration() {
        $saved_config = get_option('ai_botkit_performance_config', []);
        $this->config_options = array_merge($this->default_config, $saved_config);
    }
    
    /**
     * Save configuration to database
     */
    private function save_configuration() {
        update_option('ai_botkit_performance_config', $this->config_options);
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config_options;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool Success status
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config_options;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
        $this->save_configuration();
        
        return true;
    }
    
    /**
     * Get optimization status
     * 
     * @return string Optimization status
     */
    public function get_optimization_status() {
        return $this->get('optimization_status', 'inactive');
    }
    
    /**
     * Set optimization status
     * 
     * @param string $status Optimization status
     * @return bool Success status
     */
    public function set_optimization_status($status) {
        return $this->set('optimization_status', $status);
    }
    
    /**
     * Check if optimization is active
     * 
     * @return bool Is optimization active
     */
    public function is_optimization_active() {
        return $this->get_optimization_status() === 'active';
    }
    
    /**
     * Get cache configuration
     * 
     * @return array Cache configuration
     */
    public function get_cache_config() {
        return [
            'cache_enabled' => $this->get('cache_enabled', true),
            'cache_expiration' => $this->get('cache_expiration', $this->default_config['cache_expiration'])
        ];
    }
    
    /**
     * Set cache configuration
     * 
     * @param array $config Cache configuration
     * @return bool Success status
     */
    public function set_cache_config($config) {
        if (isset($config['cache_enabled'])) {
            $this->set('cache_enabled', $config['cache_enabled']);
        }
        
        if (isset($config['cache_expiration'])) {
            $this->set('cache_expiration', $config['cache_expiration']);
        }
        
        return true;
    }
    
    /**
     * Get performance thresholds
     * 
     * @return array Performance thresholds
     */
    public function get_performance_thresholds() {
        return $this->get('performance_thresholds', $this->default_config['performance_thresholds']);
    }
    
    /**
     * Set performance thresholds
     * 
     * @param array $thresholds Performance thresholds
     * @return bool Success status
     */
    public function set_performance_thresholds($thresholds) {
        return $this->set('performance_thresholds', $thresholds);
    }
    
    /**
     * Get monitoring settings
     * 
     * @return array Monitoring settings
     */
    public function get_monitoring_settings() {
        return $this->get('monitoring_settings', $this->default_config['monitoring_settings']);
    }
    
    /**
     * Set monitoring settings
     * 
     * @param array $settings Monitoring settings
     * @return bool Success status
     */
    public function set_monitoring_settings($settings) {
        return $this->set('monitoring_settings', $settings);
    }
    
    /**
     * Get error handling settings
     * 
     * @return array Error handling settings
     */
    public function get_error_handling_settings() {
        return $this->get('error_handling_settings', $this->default_config['error_handling_settings']);
    }
    
    /**
     * Set error handling settings
     * 
     * @param array $settings Error handling settings
     * @return bool Success status
     */
    public function set_error_handling_settings($settings) {
        return $this->set('error_handling_settings', $settings);
    }
    
    /**
     * Get module configuration
     * 
     * @param string $module Module name
     * @return array Module configuration
     */
    public function get_module_config($module) {
        $module_configs = [
            'database' => [
                'enabled' => $this->get('database_optimization', true),
                'index_optimization' => true,
                'query_optimization' => true
            ],
            'caching' => [
                'enabled' => $this->get('cache_enabled', true),
                'expiration' => $this->get('cache_expiration.database', 300)
            ],
            'wordpress_content' => [
                'enabled' => $this->get('wordpress_content_optimization', true),
                'post_type_filtering' => true,
                'content_processing' => true
            ],
            'ajax' => [
                'enabled' => $this->get('ajax_optimization', true),
                'response_caching' => true,
                'compression' => true
            ],
            'migration' => [
                'enabled' => $this->get('migration_optimization', true),
                'batch_processing' => true,
                'progress_tracking' => true
            ],
            'admin_interface' => [
                'enabled' => $this->get('admin_interface_optimization', true),
                'tab_optimization' => true,
                'pagination_optimization' => true
            ]
        ];
        
        return isset($module_configs[$module]) ? $module_configs[$module] : [];
    }
    
    /**
     * Set module configuration
     * 
     * @param string $module Module name
     * @param array $config Module configuration
     * @return bool Success status
     */
    public function set_module_config($module, $config) {
        $config_key = $module . '_optimization';
        
        if (isset($config['enabled'])) {
            $this->set($config_key, $config['enabled']);
        }
        
        // Set module-specific configurations
        foreach ($config as $key => $value) {
            if ($key !== 'enabled') {
                $this->set($config_key . '.' . $key, $value);
            }
        }
        
        return true;
    }
    
    /**
     * Get all configuration
     * 
     * @return array All configuration
     */
    public function get_all_config() {
        return $this->config_options;
    }
    
    /**
     * Set all configuration
     * 
     * @param array $config Configuration array
     * @return bool Success status
     */
    public function set_all_config($config) {
        $this->config_options = array_merge($this->default_config, $config);
        $this->save_configuration();
        
        return true;
    }
    
    /**
     * Reset configuration to defaults
     * 
     * @return bool Success status
     */
    public function reset_to_defaults() {
        $this->config_options = $this->default_config;
        $this->save_configuration();
        
        return true;
    }
    
    /**
     * Get configuration summary
     * 
     * @return array Configuration summary
     */
    public function get_config_summary() {
        return [
            'optimization_status' => $this->get_optimization_status(),
            'modules_enabled' => $this->get_enabled_modules(),
            'cache_enabled' => $this->get('cache_enabled', true),
            'performance_monitoring' => $this->get('performance_monitoring', true),
            'error_handling' => $this->get('error_handling', true),
            'performance_thresholds' => $this->get_performance_thresholds(),
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get enabled modules
     * 
     * @return array Enabled modules
     */
    public function get_enabled_modules() {
        $modules = [
            'database' => $this->get('database_optimization', true),
            'caching' => $this->get('cache_enabled', true),
            'wordpress_content' => $this->get('wordpress_content_optimization', true),
            'ajax' => $this->get('ajax_optimization', true),
            'migration' => $this->get('migration_optimization', true),
            'admin_interface' => $this->get('admin_interface_optimization', true)
        ];
        
        return array_filter($modules, function($enabled) {
            return $enabled === true;
        });
    }
    
    /**
     * Validate configuration
     * 
     * @param array $config Configuration to validate
     * @return array Validation result
     */
    public function validate_config($config) {
        $errors = [];
        $warnings = [];
        
        // Validate optimization status
        if (isset($config['optimization_status']) && !in_array($config['optimization_status'], ['active', 'inactive'])) {
            $errors[] = 'Invalid optimization status. Must be "active" or "inactive".';
        }
        
        // Validate cache expiration values
        if (isset($config['cache_expiration'])) {
            foreach ($config['cache_expiration'] as $type => $expiration) {
                if (!is_numeric($expiration) || $expiration < 0) {
                    $errors[] = "Invalid cache expiration for {$type}. Must be a positive number.";
                }
            }
        }
        
        // Validate performance thresholds
        if (isset($config['performance_thresholds'])) {
            foreach ($config['performance_thresholds'] as $threshold => $value) {
                if (!is_numeric($value) || $value < 0) {
                    $errors[] = "Invalid performance threshold for {$threshold}. Must be a positive number.";
                }
            }
        }
        
        // Validate monitoring settings
        if (isset($config['monitoring_settings'])) {
            $monitoring = $config['monitoring_settings'];
            
            if (isset($monitoring['monitoring_interval']) && (!is_numeric($monitoring['monitoring_interval']) || $monitoring['monitoring_interval'] < 60)) {
                $warnings[] = 'Monitoring interval should be at least 60 seconds.';
            }
            
            if (isset($monitoring['max_operation_history']) && (!is_numeric($monitoring['max_operation_history']) || $monitoring['max_operation_history'] < 100)) {
                $warnings[] = 'Max operation history should be at least 100.';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Export configuration
     * 
     * @return array Exported configuration
     */
    public function export_config() {
        return [
            'configuration' => $this->config_options,
            'default_config' => $this->default_config,
            'config_summary' => $this->get_config_summary(),
            'export_timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Import configuration
     * 
     * @param array $config Configuration to import
     * @return array Import result
     */
    public function import_config($config) {
        $validation = $this->validate_config($config);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ];
        }
        
        $this->set_all_config($config);
        
        return [
            'success' => true,
            'warnings' => $validation['warnings'],
            'imported_config' => $this->get_all_config()
        ];
    }
}
