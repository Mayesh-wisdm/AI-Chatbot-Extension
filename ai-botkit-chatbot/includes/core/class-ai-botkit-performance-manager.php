<?php
namespace AI_BotKit\Core;

/**
 * AI BotKit Performance Manager
 * 
 * Main orchestrator class that coordinates all performance optimization modules
 * and provides a unified interface for performance management.
 */
class AIBotKit_Performance_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Optimization modules
     */
    private $modules = [];
    
    /**
     * Unified cache manager
     */
    private $cache_manager;
    
    /**
     * Unified performance monitor
     */
    private $performance_monitor;
    
    /**
     * Unified error handler
     */
    private $error_handler;
    
    /**
     * Configuration manager
     */
    private $config_manager;
    
    /**
     * Initialize the performance manager
     */
    private function __construct() {
        $this->initialize_components();
        $this->load_optimization_modules();
        $this->setup_wordpress_hooks();
    }
    
    /**
     * Get singleton instance
     * 
     * @return AIBotKit_Performance_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize core components
     */
    private function initialize_components() {
        // Initialize unified components
        $this->cache_manager = new Unified_Cache_Manager();
        $this->performance_monitor = new Unified_Performance_Monitor();
        $this->error_handler = new Unified_Error_Handler();
        $this->config_manager = new Performance_Configuration_Manager();
    }
    
    /**
     * Load optimization modules
     */
    private function load_optimization_modules() {
        $this->modules = [
            'database' => new Database_Optimizer(),
            'caching' => $this->cache_manager, // Use unified cache manager
            'wordpress_content' => new Content_Optimizer(),
            'ajax' => new AJAX_Optimizer(),
            'migration' => new Migration_Optimizer(),
            'admin_interface' => new Admin_Interface_Optimizer()
        ];
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_wordpress_hooks() {
        // Activation hook
        add_action('ai_botkit_activate', [$this, 'activate_optimizations']);
        
        // Deactivation hook
        add_action('ai_botkit_deactivate', [$this, 'deactivate_optimizations']);
        
        // Admin init hook
        add_action('admin_init', [$this, 'admin_init_optimizations']);
        
        // AJAX hooks
        add_action('wp_ajax_ai_botkit_performance_status', [$this, 'handle_performance_status_ajax']);
        add_action('wp_ajax_ai_botkit_clear_optimization_cache', [$this, 'handle_clear_cache_ajax']);
        
        // Performance monitoring hooks
        add_action('ai_botkit_performance_monitor', [$this, 'monitor_performance']);
    }
    
    /**
     * Activate all optimizations
     */
    public function activate_optimizations() {
        try {
            // Activate database optimizations
            $this->modules['database']->activate();
            
            // Activate caching system
            $this->modules['caching']->activate();
            
            // Activate WordPress content optimizations
            $this->modules['wordpress_content']->activate();
            
            // Activate AJAX optimizations
            $this->modules['ajax']->activate();
            
            // Activate migration optimizations
            $this->modules['migration']->activate();
            
            // Activate admin interface optimizations
            $this->modules['admin_interface']->activate();
            
            // Record activation
            $this->performance_monitor->record_event('optimization_activation', 'success');
            
            // Update configuration
            $this->config_manager->set_optimization_status('active');
            
        } catch (\Exception $e) {
            $this->error_handler->handle_error('optimization_activation', $e->getMessage());
            $this->performance_monitor->record_event('optimization_activation', 'error');
        }
    }
    
    /**
     * Deactivate all optimizations
     */
    public function deactivate_optimizations() {
        try {
            // Deactivate all modules
            foreach ($this->modules as $module) {
                if (method_exists($module, 'deactivate')) {
                    $module->deactivate();
                }
            }
            
            // Clear all caches
            $this->cache_manager->clear_all_caches();
            
            // Record deactivation
            $this->performance_monitor->record_event('optimization_deactivation', 'success');
            
            // Update configuration
            $this->config_manager->set_optimization_status('inactive');
            
        } catch (\Exception $e) {
            $this->error_handler->handle_error('optimization_deactivation', $e->getMessage());
        }
    }
    
    /**
     * Admin init optimizations
     */
    public function admin_init_optimizations() {
        // Only run on AI BotKit admin pages
        if (!$this->is_ai_botkit_admin_page()) {
            return;
        }
        
        // Initialize admin-specific optimizations
        $this->modules['admin_interface']->admin_init();
    }
    
    /**
     * Get performance status
     * 
     * @return array Performance status
     */
    public function get_performance_status() {
        $status = [
            'overall_status' => $this->config_manager->get_optimization_status(),
            'modules' => [],
            'performance_metrics' => $this->performance_monitor->get_overall_metrics(),
            'cache_status' => $this->cache_manager->get_cache_status(),
            'error_status' => $this->error_handler->get_error_status(),
            'last_updated' => current_time('mysql')
        ];
        
        // Get status from each module
        foreach ($this->modules as $name => $module) {
            if (method_exists($module, 'get_optimization_status')) {
                $status['modules'][$name] = $module->get_optimization_status();
            }
        }
        
        return $status;
    }
    
    /**
     * Get performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        return [
            'overall_metrics' => $this->performance_monitor->get_overall_metrics(),
            'module_metrics' => $this->get_module_metrics(),
            'cache_metrics' => $this->cache_manager->get_cache_metrics(),
            'error_metrics' => $this->error_handler->get_error_metrics(),
            'optimization_effectiveness' => $this->calculate_overall_effectiveness()
        ];
    }
    
    /**
     * Get module-specific metrics
     * 
     * @return array Module metrics
     */
    private function get_module_metrics() {
        $metrics = [];
        
        foreach ($this->modules as $name => $module) {
            if (method_exists($module, 'get_performance_metrics')) {
                $metrics[$name] = $module->get_performance_metrics();
            }
        }
        
        return $metrics;
    }
    
    /**
     * Calculate overall optimization effectiveness
     * 
     * @return float Overall effectiveness percentage
     */
    private function calculate_overall_effectiveness() {
        $module_effectiveness = [];
        
        foreach ($this->modules as $name => $module) {
            if (method_exists($module, 'get_performance_metrics')) {
                $metrics = $module->get_performance_metrics();
                if (isset($metrics['optimization_effectiveness'])) {
                    $module_effectiveness[] = $metrics['optimization_effectiveness'];
                }
            }
        }
        
        if (empty($module_effectiveness)) {
            return 0;
        }
        
        return round(array_sum($module_effectiveness) / count($module_effectiveness), 2);
    }
    
    /**
     * Clear all optimization caches
     * 
     * @return bool Success status
     */
    public function clear_all_caches() {
        try {
            // Clear unified cache
            $this->cache_manager->clear_all_caches();
            
            // Clear module-specific caches
            foreach ($this->modules as $module) {
                if (method_exists($module, 'clear_optimization_cache')) {
                    $module->clear_optimization_cache();
                }
            }
            
            // Record cache clear
            $this->performance_monitor->record_event('cache_clear', 'success');
            
            return true;
            
        } catch (\Exception $e) {
            $this->error_handler->handle_error('cache_clear', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = [];
        
        // Get recommendations from each module
        foreach ($this->modules as $name => $module) {
            if (method_exists($module, 'get_optimization_recommendations')) {
                $module_recommendations = $module->get_optimization_recommendations();
                if (!empty($module_recommendations)) {
                    $recommendations[$name] = $module_recommendations;
                }
            }
        }
        
        // Add overall recommendations
        $recommendations['overall'] = $this->get_overall_recommendations();
        
        return $recommendations;
    }
    
    /**
     * Get overall optimization recommendations
     * 
     * @return array Overall recommendations
     */
    private function get_overall_recommendations() {
        $recommendations = [];
        $metrics = $this->get_performance_metrics();
        
        // Overall effectiveness recommendation
        if ($metrics['optimization_effectiveness'] < 80) {
            $recommendations[] = [
                'type' => 'overall_effectiveness',
                'priority' => 'medium',
                'message' => 'Overall optimization effectiveness is ' . $metrics['optimization_effectiveness'] . '%. Consider reviewing individual module performance.',
                'action' => 'review_module_performance'
            ];
        }
        
        // Cache performance recommendation
        $cache_metrics = $metrics['cache_metrics'];
        if ($cache_metrics['hit_ratio'] < 0.7) {
            $recommendations[] = [
                'type' => 'cache_performance',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is ' . round($cache_metrics['hit_ratio'] * 100, 1) . '%. Consider optimizing cache strategies.',
                'action' => 'optimize_cache_strategies'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Monitor performance
     */
    public function monitor_performance() {
        // Record performance monitoring event
        $this->performance_monitor->record_event('performance_monitoring', 'success');
        
        // Check for performance issues
        $metrics = $this->get_performance_metrics();
        
        // Log performance issues
        if ($metrics['optimization_effectiveness'] < 70) {
            $this->error_handler->handle_error('performance_monitoring', 'Low optimization effectiveness detected');
        }
    }
    
    /**
     * Handle performance status AJAX
     */
    public function handle_performance_status_ajax() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_botkit_performance_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get performance status
        $status = $this->get_performance_status();
        
        // Send response
        wp_send_json_success($status);
    }
    
    /**
     * Handle clear cache AJAX
     */
    public function handle_clear_cache_ajax() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ai_botkit_performance_nonce')) {
            wp_die('Security check failed');
        }
        
        // Clear caches
        $success = $this->clear_all_caches();
        
        // Send response
        wp_send_json_success(['cleared' => $success]);
    }
    
    /**
     * Check if current page is AI BotKit admin page
     * 
     * @return bool Is AI BotKit admin page
     */
    private function is_ai_botkit_admin_page() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'ai-botkit') !== false;
    }
    
    /**
     * Get module by name
     * 
     * @param string $name Module name
     * @return mixed Module instance or null
     */
    public function get_module($name) {
        return isset($this->modules[$name]) ? $this->modules[$name] : null;
    }
    
    /**
     * Get all modules
     * 
     * @return array All modules
     */
    public function get_all_modules() {
        return $this->modules;
    }
    
    /**
     * Get unified cache manager
     * 
     * @return Unified_Cache_Manager
     */
    public function get_cache_manager() {
        return $this->cache_manager;
    }
    
    /**
     * Get unified performance monitor
     * 
     * @return Unified_Performance_Monitor
     */
    public function get_performance_monitor() {
        return $this->performance_monitor;
    }
    
    /**
     * Get unified error handler
     * 
     * @return Unified_Error_Handler
     */
    public function get_error_handler() {
        return $this->error_handler;
    }
    
    /**
     * Get configuration manager
     * 
     * @return Performance_Configuration_Manager
     */
    public function get_config_manager() {
        return $this->config_manager;
    }
}
