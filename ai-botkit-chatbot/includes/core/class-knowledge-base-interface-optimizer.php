<?php
namespace AI_BotKit\Core;

/**
 * Knowledge Base Interface Optimizer Class
 * 
 * Optimizes Knowledge Base tab performance with caching,
 * query optimization, and performance monitoring.
 */
class Knowledge_Base_Interface_Optimizer {
    
    /**
     * Admin interface cache manager
     */
    private $cache_manager;
    
    /**
     * Admin interface performance monitor
     */
    private $monitor;
    
    /**
     * Initialize the Knowledge Base interface optimizer
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Unified_Performance_Monitor();
    }
    
    /**
     * Get optimized Knowledge Base data
     * 
     * @param array $params Parameters for data retrieval
     * @return array Optimized Knowledge Base data
     */
    public function get_optimized_knowledge_base_data($params = []) {
        $cache_key = 'knowledge_base_data_' . md5(serialize($params));
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $data = $this->generate_optimized_knowledge_base_data($params);
        $this->cache_manager->set($cache_key, $data, 300); // 5 minutes cache
        
        return $data;
    }
    
    /**
     * Generate optimized Knowledge Base data
     * 
     * @param array $params Parameters for data generation
     * @return array Optimized Knowledge Base data
     */
    private function generate_optimized_knowledge_base_data($params) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Extract parameters
        $type = $params['type'] ?? 'all';
        $page = $params['page'] ?? 1;
        $per_page = $params['per_page'] ?? 20;
        $search = $params['search'] ?? '';
        
        // Build optimized query
        $where_conditions = [];
        $query_params = [];
        
        if ($type !== 'all') {
            $where_conditions[] = "source_type = %s";
            $query_params[] = $type;
        }
        
        if (!empty($search)) {
            $where_conditions[] = "(title LIKE %s OR content LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count with optimized query
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents $where_clause";
        if (!empty($query_params)) {
            $count_query = $wpdb->prepare($count_query, $query_params);
        }
        $total_documents = $wpdb->get_var($count_query);
        
        // Get documents with pagination
        $offset = ($page - 1) * $per_page;
        $documents_query = "SELECT 
            id, title, source_type, source_id, status, created_at, updated_at,
            CASE 
                WHEN source_type = 'post' THEN (SELECT post_title FROM {$wpdb->posts} WHERE ID = source_id)
                WHEN source_type = 'file' THEN title
                WHEN source_type = 'url' THEN title
                ELSE title
            END as display_title
            FROM {$wpdb->prefix}ai_botkit_documents 
            $where_clause
            ORDER BY updated_at DESC
            LIMIT %d OFFSET %d";
        
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        if (!empty($query_params)) {
            $documents_query = $wpdb->prepare($documents_query, $query_params);
        }
        
        $documents = $wpdb->get_results($documents_query, ARRAY_A);
        
        // Get document statistics by type
        $stats_query = "SELECT 
            source_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}ai_botkit_documents
            GROUP BY source_type
            ORDER BY count DESC";
        
        $stats = $wpdb->get_results($stats_query, ARRAY_A);
        
        $end_time = microtime(true);
        $load_time = $end_time - $start_time;
        
        // Record performance metrics
        $this->monitor->record_interface_operation('knowledge_base_load', $load_time, count($documents));
        
        // Update load count
        $this->increment_knowledge_base_loads();
        
        return [
            'documents' => $documents ?: [],
            'total_documents' => (int) $total_documents,
            'total_pages' => ceil($total_documents / $per_page),
            'current_page' => $page,
            'per_page' => $per_page,
            'statistics' => $stats ?: [],
            'load_time' => $load_time,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get Knowledge Base statistics
     * 
     * @return array Knowledge Base statistics
     */
    public function get_knowledge_base_statistics() {
        $cache_key = 'knowledge_base_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_knowledge_base_statistics();
        $this->cache_manager->set($cache_key, $stats, 600); // 10 minutes cache
        
        return $stats;
    }
    
    /**
     * Generate Knowledge Base statistics
     * 
     * @return array Knowledge Base statistics
     */
    private function generate_knowledge_base_statistics() {
        global $wpdb;
        
        // Get overall statistics
        $overall_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_documents,
                SUM(CASE WHEN source_type = 'post' THEN 1 ELSE 0 END) as post_documents,
                SUM(CASE WHEN source_type = 'file' THEN 1 ELSE 0 END) as file_documents,
                SUM(CASE WHEN source_type = 'url' THEN 1 ELSE 0 END) as url_documents
            FROM {$wpdb->prefix}ai_botkit_documents",
            ARRAY_A
        );
        
        // Get statistics by type
        $type_stats = $wpdb->get_results(
            "SELECT 
                source_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$wpdb->prefix}ai_botkit_documents
            GROUP BY source_type
            ORDER BY count DESC",
            ARRAY_A
        );
        
        // Get recent documents
        $recent_documents = $wpdb->get_results(
            "SELECT id, title, source_type, status, created_at
            FROM {$wpdb->prefix}ai_botkit_documents
            ORDER BY created_at DESC
            LIMIT 10",
            ARRAY_A
        );
        
        return [
            'overall' => $overall_stats ?: [
                'total_documents' => 0,
                'processed_documents' => 0,
                'pending_documents' => 0,
                'failed_documents' => 0,
                'post_documents' => 0,
                'file_documents' => 0,
                'url_documents' => 0
            ],
            'by_type' => $type_stats ?: [],
            'recent_documents' => $recent_documents ?: [],
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get Knowledge Base performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_knowledge_base_performance_metrics() {
        return [
            'average_load_time' => $this->monitor->get_average_knowledge_base_load_time(),
            'total_loads' => $this->get_knowledge_base_loads(),
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
        $load_time = $this->monitor->get_average_knowledge_base_load_time();
        $cache_hit_ratio = $this->cache_manager->get_cache_hit_ratio();
        
        // Calculate effectiveness based on performance improvements
        $time_effectiveness = max(0, 1 - ($load_time / 1.0)); // Target: < 1 second
        $cache_effectiveness = $cache_hit_ratio;
        
        $effectiveness = ($time_effectiveness * 0.6) + ($cache_effectiveness * 0.4);
        
        return round($effectiveness * 100, 2);
    }
    
    /**
     * Get Knowledge Base optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_knowledge_base_optimization_recommendations() {
        $recommendations = [];
        $metrics = $this->get_knowledge_base_performance_metrics();
        
        // Load time recommendations
        if ($metrics['average_load_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'load_time',
                'priority' => 'high',
                'message' => 'Knowledge Base load time is slow (' . round($metrics['average_load_time'] * 1000, 1) . 'ms). Consider optimizing queries.',
                'action' => 'optimize_knowledge_base_queries'
            ];
        }
        
        // Cache hit ratio recommendations
        if ($metrics['cache_hit_ratio'] < 0.7) {
            $recommendations[] = [
                'type' => 'cache_hit_ratio',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is low (' . round($metrics['cache_hit_ratio'] * 100, 1) . '%). Consider increasing cache expiration times.',
                'action' => 'increase_knowledge_base_cache_expiration'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Increment Knowledge Base loads
     */
    private function increment_knowledge_base_loads() {
        $current_loads = get_option('ai_botkit_knowledge_base_loads', 0);
        update_option('ai_botkit_knowledge_base_loads', $current_loads + 1);
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
     * Clear Knowledge Base optimization cache
     */
    public function clear_knowledge_base_cache() {
        $this->cache_manager->delete('knowledge_base_data_*');
        $this->cache_manager->delete('knowledge_base_statistics');
    }
}
