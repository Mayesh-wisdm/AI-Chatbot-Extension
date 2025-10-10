<?php
namespace AI_BotKit\Core;

/**
 * Admin Interface Optimizer Class
 * 
 * Optimizes admin interface performance with Knowledge Base and My Bots
 * tab optimizations, caching, and performance monitoring.
 */
class Admin_Interface_Optimizer {
    
    /**
     * Knowledge Base interface optimizer
     */
    private $kb_optimizer;
    
    /**
     * My Bots interface optimizer
     */
    private $my_bots_optimizer;
    
    /**
     * Admin interface cache manager
     */
    private $cache_manager;
    
    /**
     * Admin interface performance monitor
     */
    private $monitor;
    
    /**
     * Initialize the admin interface optimizer
     */
    public function __construct() {
        $this->kb_optimizer = new Knowledge_Base_Interface_Optimizer();
        $this->my_bots_optimizer = new My_Bots_Interface_Optimizer();
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Unified_Performance_Monitor();
    }
    
    /**
     * Get optimized admin interface data
     * 
     * @return array Optimized admin interface data
     */
    public function get_optimized_admin_interface_data() {
        $cache_key = 'admin_interface_data';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $data = $this->generate_optimized_admin_interface_data();
        $this->cache_manager->set($cache_key, $data, 300); // 5 minutes cache
        
        return $data;
    }
    
    /**
     * Generate optimized admin interface data
     * 
     * @return array Optimized admin interface data
     */
    private function generate_optimized_admin_interface_data() {
        $start_time = microtime(true);
        
        // Get optimized Knowledge Base data
        $kb_data = $this->kb_optimizer->get_optimized_knowledge_base_data();
        
        // Get optimized My Bots data
        $my_bots_data = $this->my_bots_optimizer->get_optimized_my_bots_data();
        
        // Get interface statistics
        $interface_stats = $this->get_interface_statistics();
        
        $end_time = microtime(true);
        $load_time = $end_time - $start_time;
        
        // Record performance metrics
        $this->monitor->record_interface_operation('admin_interface_load', $load_time, 1);
        
        return [
            'knowledge_base' => $kb_data,
            'my_bots' => $my_bots_data,
            'interface_statistics' => $interface_stats,
            'load_time' => $load_time,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get interface statistics
     * 
     * @return array Interface statistics
     */
    public function get_interface_statistics() {
        $cache_key = 'interface_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_interface_statistics();
        $this->cache_manager->set($cache_key, $stats, 600); // 10 minutes cache
        
        return $stats;
    }
    
    /**
     * Generate interface statistics
     * 
     * @return array Interface statistics
     */
    private function generate_interface_statistics() {
        global $wpdb;
        
        // Get Knowledge Base statistics
        $kb_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_documents
            FROM {$wpdb->prefix}ai_botkit_documents",
            ARRAY_A
        );
        
        // Get My Bots statistics
        $bots_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_chatbots,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_chatbots,
                SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_chatbots
            FROM {$wpdb->prefix}ai_botkit_chatbots",
            ARRAY_A
        );
        
        return [
            'knowledge_base' => $kb_stats ?: [
                'total_documents' => 0,
                'processed_documents' => 0,
                'pending_documents' => 0,
                'failed_documents' => 0
            ],
            'my_bots' => $bots_stats ?: [
                'total_chatbots' => 0,
                'active_chatbots' => 0,
                'inactive_chatbots' => 0
            ],
            'total_interface_loads' => $this->get_total_interface_loads(),
            'knowledge_base_loads' => $this->get_knowledge_base_loads(),
            'my_bots_loads' => $this->get_my_bots_loads(),
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get total interface loads
     * 
     * @return int Total interface loads
     */
    private function get_total_interface_loads() {
        return get_option('ai_botkit_total_interface_loads', 0);
    }
    
    /**
     * Get Knowledge Base loads
     * 
     * @return int Knowledge Base loads
     */
    private function get_knowledge_base_loads() {
        return get_option('ai_botkit_knowledge_base_loads', 0);
    }
    
    /**
     * Get My Bots loads
     * 
     * @return int My Bots loads
     */
    private function get_my_bots_loads() {
        return get_option('ai_botkit_my_bots_loads', 0);
    }
    
    /**
     * Get performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        return [
            'interface_load_time' => $this->monitor->get_average_interface_load_time(),
            'knowledge_base_load_time' => $this->monitor->get_average_knowledge_base_load_time(),
            'my_bots_load_time' => $this->monitor->get_average_my_bots_load_time(),
            'cache_hit_ratio' => $this->cache_manager->get_cache_hit_ratio(),
            'ajax_response_time' => $this->monitor->get_average_ajax_response_time(),
            'total_operations' => $this->monitor->get_total_operations(),
            'average_operation_time' => $this->monitor->get_average_operation_time(),
            'optimization_effectiveness' => $this->calculate_optimization_effectiveness()
        ];
    }
    
    /**
     * Calculate optimization effectiveness
     * 
     * @return float Optimization effectiveness percentage
     */
    private function calculate_optimization_effectiveness() {
        $interface_load_time = $this->monitor->get_average_interface_load_time();
        $cache_hit_ratio = $this->cache_manager->get_cache_hit_ratio();
        $ajax_response_time = $this->monitor->get_average_ajax_response_time();
        
        // Calculate effectiveness based on performance improvements
        $load_time_effectiveness = max(0, 1 - ($interface_load_time / 2.0)); // Target: < 2 seconds
        $cache_effectiveness = $cache_hit_ratio;
        $ajax_effectiveness = max(0, 1 - ($ajax_response_time / 1.0)); // Target: < 1 second
        
        $effectiveness = ($load_time_effectiveness * 0.4) + ($cache_effectiveness * 0.4) + ($ajax_effectiveness * 0.2);
        
        return round($effectiveness * 100, 2);
    }
    
    /**
     * Get optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = [];
        $metrics = $this->get_performance_metrics();
        
        // Interface load time recommendations
        if ($metrics['interface_load_time'] > 2.0) {
            $recommendations[] = [
                'type' => 'interface_load_time',
                'priority' => 'high',
                'message' => 'Admin interface load time is slow (' . round($metrics['interface_load_time'] * 1000, 1) . 'ms). Consider optimizing queries.',
                'action' => 'optimize_interface_queries'
            ];
        }
        
        // Knowledge Base load time recommendations
        if ($metrics['knowledge_base_load_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'knowledge_base_load_time',
                'priority' => 'medium',
                'message' => 'Knowledge Base load time is slow (' . round($metrics['knowledge_base_load_time'] * 1000, 1) . 'ms). Consider caching.',
                'action' => 'optimize_knowledge_base_caching'
            ];
        }
        
        // My Bots load time recommendations
        if ($metrics['my_bots_load_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'my_bots_load_time',
                'priority' => 'medium',
                'message' => 'My Bots load time is slow (' . round($metrics['my_bots_load_time'] * 1000, 1) . 'ms). Consider optimizing queries.',
                'action' => 'optimize_my_bots_queries'
            ];
        }
        
        // Cache hit ratio recommendations
        if ($metrics['cache_hit_ratio'] < 0.7) {
            $recommendations[] = [
                'type' => 'cache_hit_ratio',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is low (' . round($metrics['cache_hit_ratio'] * 100, 1) . '%). Consider increasing cache expiration times.',
                'action' => 'increase_cache_expiration'
            ];
        }
        
        // AJAX response time recommendations
        if ($metrics['ajax_response_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'ajax_response_time',
                'priority' => 'medium',
                'message' => 'AJAX response time is slow (' . round($metrics['ajax_response_time'] * 1000, 1) . 'ms). Consider optimizing AJAX calls.',
                'action' => 'optimize_ajax_calls'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get optimization status
     * 
     * @return array Optimization status
     */
    public function get_optimization_status() {
        return [
            'knowledge_base_optimized' => true,
            'my_bots_optimized' => true,
            'caching_enabled' => true,
            'performance_monitoring' => true,
            'ajax_optimization' => true,
            'error_handling' => true,
            'last_optimization' => current_time('mysql')
        ];
    }
    
    /**
     * Clear admin interface optimization cache
     */
    public function clear_optimization_cache() {
        $this->cache_manager->clear_all_caches();
        $this->monitor->clear_performance_data();
    }
    
    /**
     * Get admin interface summary
     * 
     * @return array Admin interface summary
     */
    public function get_admin_interface_summary() {
        $interface_data = $this->get_optimized_admin_interface_data();
        $performance_metrics = $this->get_performance_metrics();
        $optimization_recommendations = $this->get_optimization_recommendations();
        
        return [
            'interface_data' => $interface_data,
            'performance_metrics' => $performance_metrics,
            'optimization_recommendations' => $optimization_recommendations,
            'optimization_status' => $this->get_optimization_status(),
            'last_updated' => current_time('mysql')
        ];
    }
}
