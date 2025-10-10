<?php
namespace AI_BotKit\Core;

/**
 * My Bots Interface Optimizer Class
 * 
 * Optimizes My Bots tab performance with caching,
 * query optimization, and performance monitoring.
 */
class My_Bots_Interface_Optimizer {
    
    /**
     * Admin interface cache manager
     */
    private $cache_manager;
    
    /**
     * Admin interface performance monitor
     */
    private $monitor;
    
    /**
     * Initialize the My Bots interface optimizer
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Unified_Performance_Monitor();
    }
    
    /**
     * Get optimized My Bots data
     * 
     * @param array $params Parameters for data retrieval
     * @return array Optimized My Bots data
     */
    public function get_optimized_my_bots_data($params = []) {
        $cache_key = 'my_bots_data_' . md5(serialize($params));
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $data = $this->generate_optimized_my_bots_data($params);
        $this->cache_manager->set($cache_key, $data, 300); // 5 minutes cache
        
        return $data;
    }
    
    /**
     * Generate optimized My Bots data
     * 
     * @param array $params Parameters for data generation
     * @return array Optimized My Bots data
     */
    private function generate_optimized_my_bots_data($params) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Extract parameters
        $status = $params['status'] ?? 'all';
        $page = $params['page'] ?? 1;
        $per_page = $params['per_page'] ?? 20;
        $search = $params['search'] ?? '';
        
        // Build optimized query
        $where_conditions = [];
        $query_params = [];
        
        if ($status !== 'all') {
            $where_conditions[] = "active = %d";
            $query_params[] = ($status === 'active') ? 1 : 0;
        }
        
        if (!empty($search)) {
            $where_conditions[] = "(name LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count with optimized query
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chatbots $where_clause";
        if (!empty($query_params)) {
            $count_query = $wpdb->prepare($count_query, $query_params);
        }
        $total_chatbots = $wpdb->get_var($count_query);
        
        // Get chatbots with pagination
        $offset = ($page - 1) * $per_page;
        $chatbots_query = "SELECT 
            id, name, description, active, created_at, updated_at,
            CASE 
                WHEN active = 1 THEN 'Active'
                ELSE 'Inactive'
            END as status_display,
            CASE 
                WHEN active = 1 THEN 'success'
                ELSE 'secondary'
            END as status_class
            FROM {$wpdb->prefix}ai_botkit_chatbots 
            $where_clause
            ORDER BY updated_at DESC
            LIMIT %d OFFSET %d";
        
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        if (!empty($query_params)) {
            $chatbots_query = $wpdb->prepare($chatbots_query, $query_params);
        }
        
        $chatbots = $wpdb->get_results($chatbots_query, ARRAY_A);
        
        // Get chatbot statistics
        $stats_query = "SELECT 
            COUNT(*) as total_chatbots,
            SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_chatbots,
            SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_chatbots
            FROM {$wpdb->prefix}ai_botkit_chatbots";
        
        $stats = $wpdb->get_row($stats_query, ARRAY_A);
        
        // Get recent chatbots
        $recent_chatbots = $wpdb->get_results(
            "SELECT id, name, active, created_at
            FROM {$wpdb->prefix}ai_botkit_chatbots
            ORDER BY created_at DESC
            LIMIT 5",
            ARRAY_A
        );
        
        $end_time = microtime(true);
        $load_time = $end_time - $start_time;
        
        // Record performance metrics
        $this->monitor->record_interface_operation('my_bots_load', $load_time, count($chatbots));
        
        // Update load count
        $this->increment_my_bots_loads();
        
        return [
            'chatbots' => $chatbots ?: [],
            'total_chatbots' => (int) $total_chatbots,
            'total_pages' => ceil($total_chatbots / $per_page),
            'current_page' => $page,
            'per_page' => $per_page,
            'statistics' => $stats ?: [
                'total_chatbots' => 0,
                'active_chatbots' => 0,
                'inactive_chatbots' => 0
            ],
            'recent_chatbots' => $recent_chatbots ?: [],
            'load_time' => $load_time,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get My Bots statistics
     * 
     * @return array My Bots statistics
     */
    public function get_my_bots_statistics() {
        $cache_key = 'my_bots_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_my_bots_statistics();
        $this->cache_manager->set($cache_key, $stats, 600); // 10 minutes cache
        
        return $stats;
    }
    
    /**
     * Generate My Bots statistics
     * 
     * @return array My Bots statistics
     */
    private function generate_my_bots_statistics() {
        global $wpdb;
        
        // Get overall statistics
        $overall_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_chatbots,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_chatbots,
                SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_chatbots
            FROM {$wpdb->prefix}ai_botkit_chatbots",
            ARRAY_A
        );
        
        // Get chatbot usage statistics
        $usage_stats = $wpdb->get_results(
            "SELECT 
                c.id,
                c.name,
                c.active,
                COUNT(conv.id) as conversation_count,
                MAX(conv.created_at) as last_conversation
            FROM {$wpdb->prefix}ai_botkit_chatbots c
            LEFT JOIN {$wpdb->prefix}ai_botkit_conversations conv ON c.id = conv.chatbot_id
            GROUP BY c.id, c.name, c.active
            ORDER BY conversation_count DESC
            LIMIT 10",
            ARRAY_A
        );
        
        // Get chatbot creation trends
        $creation_trends = $wpdb->get_results(
            "SELECT 
                DATE(created_at) as creation_date,
                COUNT(*) as chatbots_created
            FROM {$wpdb->prefix}ai_botkit_chatbots
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY creation_date DESC",
            ARRAY_A
        );
        
        return [
            'overall' => $overall_stats ?: [
                'total_chatbots' => 0,
                'active_chatbots' => 0,
                'inactive_chatbots' => 0
            ],
            'usage' => $usage_stats ?: [],
            'creation_trends' => $creation_trends ?: [],
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get My Bots performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_my_bots_performance_metrics() {
        return [
            'average_load_time' => $this->monitor->get_average_my_bots_load_time(),
            'total_loads' => $this->get_my_bots_loads(),
            'cache_hit_ratio' => $this->cache_manager->get_cache_hit_ratio(),
            'optimization_effectiveness' => $this->calculate_optimization_effectiveness()
        ];
    }
    
    /**
     * Calculate optimization effectiveness
     * 
     * @return float Optimization effectiveness percentage
     */
    private function calculate_optimization_effectiveness() {
        $load_time = $this->monitor->get_average_my_bots_load_time();
        $cache_hit_ratio = $this->cache_manager->get_cache_hit_ratio();
        
        // Calculate effectiveness based on performance improvements
        $time_effectiveness = max(0, 1 - ($load_time / 1.0)); // Target: < 1 second
        $cache_effectiveness = $cache_hit_ratio;
        
        $effectiveness = ($time_effectiveness * 0.6) + ($cache_effectiveness * 0.4);
        
        return round($effectiveness * 100, 2);
    }
    
    /**
     * Get My Bots optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_my_bots_optimization_recommendations() {
        $recommendations = [];
        $metrics = $this->get_my_bots_performance_metrics();
        
        // Load time recommendations
        if ($metrics['average_load_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'load_time',
                'priority' => 'high',
                'message' => 'My Bots load time is slow (' . round($metrics['average_load_time'] * 1000, 1) . 'ms). Consider optimizing queries.',
                'action' => 'optimize_my_bots_queries'
            ];
        }
        
        // Cache hit ratio recommendations
        if ($metrics['cache_hit_ratio'] < 0.7) {
            $recommendations[] = [
                'type' => 'cache_hit_ratio',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is low (' . round($metrics['cache_hit_ratio'] * 100, 1) . '%). Consider increasing cache expiration times.',
                'action' => 'increase_my_bots_cache_expiration'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Increment My Bots loads
     */
    private function increment_my_bots_loads() {
        $current_loads = get_option('ai_botkit_my_bots_loads', 0);
        update_option('ai_botkit_my_bots_loads', $current_loads + 1);
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
     * Clear My Bots optimization cache
     */
    public function clear_my_bots_cache() {
        $this->cache_manager->delete('my_bots_data_*');
        $this->cache_manager->delete('my_bots_statistics');
    }
}
