<?php
namespace AI_BotKit\Core;

/**
 * Unified Cache Manager
 * 
 * Consolidates all cache management functionality from multiple
 * cache managers into a single, efficient system.
 */
class Unified_Cache_Manager {
    
    /**
     * Cache prefix
     */
    private $cache_prefix = 'ai_botkit_';
    
    /**
     * Cache groups
     */
    private $cache_groups = [
        'database' => 'ai_botkit_database',
        'ajax' => 'ai_botkit_ajax',
        'migration' => 'ai_botkit_migration',
        'admin_interface' => 'ai_botkit_admin_interface',
        'content' => 'ai_botkit_content',
        'performance' => 'ai_botkit_performance'
    ];
    
    /**
     * Default cache expiration times
     */
    private $default_expirations = [
        'database' => 300,        // 5 minutes
        'ajax' => 120,           // 2 minutes
        'migration' => 300,      // 5 minutes
        'admin_interface' => 300, // 5 minutes
        'content' => 600,        // 10 minutes
        'performance' => 900     // 15 minutes
    ];
    
    /**
     * Cache configuration
     */
    private $config;
    
    /**
     * Initialize the unified cache manager
     */
    public function __construct() {
        $this->config = Cache_Configuration::get_instance();
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached data or false
     */
    public function get($key, $group = 'default') {
        $cache_key = $this->cache_prefix . $key;
        $cache_group = $this->get_cache_group($group);
        
        return wp_cache_get($cache_key, $cache_group);
    }
    
    /**
     * Check if cache key exists
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool True if key exists, false otherwise
     */
    public function has($key, $group = 'default') {
        $cache_key = $this->cache_prefix . $key;
        $cache_group = $this->get_cache_group($group);
        
        return wp_cache_get($cache_key, $cache_group) !== false;
    }
    
    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int $expiration Cache expiration in seconds
     * @return bool Success status
     */
    public function set($key, $data, $group = 'default', $expiration = null) {
        $cache_key = $this->cache_prefix . $key;
        $cache_group = $this->get_cache_group($group);
        $expiration = $expiration ?: $this->get_default_expiration($group);
        
        return wp_cache_set($cache_key, $data, $cache_group, $expiration);
    }
    
    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success status
     */
    public function delete($key, $group = 'default') {
        $cache_key = $this->cache_prefix . $key;
        $cache_group = $this->get_cache_group($group);
        
        return wp_cache_delete($cache_key, $cache_group);
    }
    
    /**
     * Get cache group
     * 
     * @param string $group Group name
     * @return string Cache group
     */
    private function get_cache_group($group) {
        // Ensure group is a string
        if (!is_string($group)) {
            return 'ai_botkit_default';
        }
        
        return isset($this->cache_groups[$group]) ? $this->cache_groups[$group] : 'ai_botkit_default';
    }
    
    /**
     * Get default expiration for group
     * 
     * @param string $group Group name
     * @return int Expiration time
     */
    private function get_default_expiration($group) {
        // Ensure group is a string
        if (!is_string($group)) {
            return 300; // Default 5 minutes
        }
        
        return isset($this->default_expirations[$group]) ? $this->default_expirations[$group] : 300;
    }
    
    /**
     * Database cache methods
     */
    
    /**
     * Get cached database query result
     * 
     * @param string $query_hash Query hash
     * @return mixed Cached result or false
     */
    public function get_database_cache($query_hash) {
        return $this->get('db_' . $query_hash, 'database');
    }
    
    /**
     * Set cached database query result
     * 
     * @param string $query_hash Query hash
     * @param mixed $result Query result
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_database_cache($query_hash, $result, $expiration = null) {
        return $this->set('db_' . $query_hash, $result, 'database', $expiration);
    }
    
    /**
     * AJAX cache methods
     */
    
    /**
     * Get cached AJAX response
     * 
     * @param string $action AJAX action
     * @param array $params AJAX parameters
     * @return mixed Cached response or false
     */
    public function get_ajax_cache($action, $params = []) {
        $cache_key = $this->generate_ajax_cache_key($action, $params);
        return $this->get($cache_key, 'ajax');
    }
    
    /**
     * Set cached AJAX response
     * 
     * @param string $action AJAX action
     * @param array $params AJAX parameters
     * @param mixed $response AJAX response
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_ajax_cache($action, $params, $response, $expiration = null) {
        $cache_key = $this->generate_ajax_cache_key($action, $params);
        return $this->set($cache_key, $response, 'ajax', $expiration);
    }
    
    /**
     * Generate AJAX cache key
     * 
     * @param string $action AJAX action
     * @param array $params AJAX parameters
     * @return string Cache key
     */
    private function generate_ajax_cache_key($action, $params) {
        $essential_params = $this->filter_essential_params($params);
        return 'ajax_' . $action . '_' . md5(serialize($essential_params));
    }
    
    /**
     * Filter essential parameters for caching
     * 
     * @param array $params Parameters
     * @return array Filtered parameters
     */
    private function filter_essential_params($params) {
        $essential = ['type', 'page', 'search', 'status', 'per_page'];
        $filtered = [];
        
        foreach ($essential as $key) {
            if (isset($params[$key])) {
                $filtered[$key] = $params[$key];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Migration cache methods
     */
    
    /**
     * Get cached migration status
     * 
     * @return mixed Cached migration status or false
     */
    public function get_migration_cache() {
        return $this->get('migration_status', 'migration');
    }
    
    /**
     * Set cached migration status
     * 
     * @param mixed $status Migration status
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_migration_cache($status, $expiration = null) {
        return $this->set('migration_status', $status, 'migration', $expiration);
    }
    
    /**
     * Get cached content types
     * 
     * @return mixed Cached content types or false
     */
    public function get_content_types_cache() {
        return $this->get('content_types', 'migration');
    }
    
    /**
     * Set cached content types
     * 
     * @param mixed $content_types Content types
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_content_types_cache($content_types, $expiration = null) {
        return $this->set('content_types', $content_types, 'migration', $expiration);
    }
    
    /**
     * Admin interface cache methods
     */
    
    /**
     * Get cached admin interface data
     * 
     * @param string $type Data type
     * @param array $params Parameters
     * @return mixed Cached data or false
     */
    public function get_admin_interface_cache($type, $params = []) {
        $cache_key = $this->generate_admin_cache_key($type, $params);
        return $this->get($cache_key, 'admin_interface');
    }
    
    /**
     * Set cached admin interface data
     * 
     * @param string $type Data type
     * @param array $params Parameters
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_admin_interface_cache($type, $params, $data, $expiration = null) {
        $cache_key = $this->generate_admin_cache_key($type, $params);
        return $this->set($cache_key, $data, 'admin_interface', $expiration);
    }
    
    /**
     * Generate admin interface cache key
     * 
     * @param string $type Data type
     * @param array $params Parameters
     * @return string Cache key
     */
    private function generate_admin_cache_key($type, $params) {
        return 'admin_' . $type . '_' . md5(serialize($params));
    }
    
    /**
     * Content cache methods
     */
    
    /**
     * Get cached content data
     * 
     * @param string $type Content type
     * @param array $params Parameters
     * @return mixed Cached data or false
     */
    public function get_content_cache($type, $params = []) {
        $cache_key = $this->generate_content_cache_key($type, $params);
        return $this->get($cache_key, 'content');
    }
    
    /**
     * Set cached content data
     * 
     * @param string $type Content type
     * @param array $params Parameters
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_content_cache($type, $params, $data, $expiration = null) {
        $cache_key = $this->generate_content_cache_key($type, $params);
        return $this->set($cache_key, $data, 'content', $expiration);
    }
    
    /**
     * Generate content cache key
     * 
     * @param string $type Content type
     * @param array $params Parameters
     * @return string Cache key
     */
    private function generate_content_cache_key($type, $params) {
        return 'content_' . $type . '_' . md5(serialize($params));
    }
    
    /**
     * Performance cache methods
     */
    
    /**
     * Get cached performance data
     * 
     * @param string $type Performance data type
     * @return mixed Cached data or false
     */
    public function get_performance_cache($type) {
        return $this->get('perf_' . $type, 'performance');
    }
    
    /**
     * Set cached performance data
     * 
     * @param string $type Performance data type
     * @param mixed $data Performance data
     * @param int $expiration Expiration time
     * @return bool Success status
     */
    public function set_performance_cache($type, $data, $expiration = null) {
        return $this->set('perf_' . $type, $data, 'performance', $expiration);
    }
    
    /**
     * Cache invalidation methods
     */
    
    /**
     * Invalidate cache by group
     * 
     * @param string $group Cache group
     * @return bool Success status
     */
    public function invalidate_group($group) {
        global $wp_object_cache;
        
        $cache_group = $this->get_cache_group($group);
        
        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'delete_group')) {
            return $wp_object_cache->delete_group($cache_group);
        } else {
            return wp_cache_flush();
        }
    }
    
    /**
     * Invalidate all caches
     * 
     * @return bool Success status
     */
    public function clear_all_caches() {
        global $wp_object_cache;
        
        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'delete_group')) {
            $success = true;
            foreach ($this->cache_groups as $group) {
                $success = $success && $wp_object_cache->delete_group($group);
            }
            return $success;
        } else {
            return wp_cache_flush();
        }
    }
    
    /**
     * Invalidate database caches
     */
    public function invalidate_database_caches() {
        return $this->invalidate_group('database');
    }
    
    /**
     * Invalidate AJAX caches
     */
    public function invalidate_ajax_caches() {
        return $this->invalidate_group('ajax');
    }
    
    /**
     * Invalidate migration caches
     */
    public function invalidate_migration_caches() {
        return $this->invalidate_group('migration');
    }
    
    /**
     * Invalidate admin interface caches
     */
    public function invalidate_admin_interface_caches() {
        return $this->invalidate_group('admin_interface');
    }
    
    /**
     * Invalidate content caches
     */
    public function invalidate_content_caches() {
        return $this->invalidate_group('content');
    }
    
    /**
     * Invalidate performance caches
     */
    public function invalidate_performance_caches() {
        return $this->invalidate_group('performance');
    }
    
    /**
     * Cache statistics and monitoring
     */
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function get_cache_statistics() {
        global $wp_object_cache;
        
        $stats = [
            'total_cached_items' => 0,
            'cache_hit_ratio' => 0,
            'memory_usage' => 0,
            'groups' => [],
            'last_updated' => current_time('mysql')
        ];
        
        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'get_stats')) {
            $wp_stats = $wp_object_cache->get_stats();
            $stats['total_cached_items'] = $wp_stats['total_requests'] ?? 0;
            $stats['cache_hit_ratio'] = $wp_stats['hit_ratio'] ?? 0;
            $stats['memory_usage'] = $wp_stats['memory_usage'] ?? 0;
        }
        
        // Get group-specific statistics
        foreach ($this->cache_groups as $name => $group) {
            $stats['groups'][$name] = [
                'group_name' => $group,
                'expiration' => $this->default_expirations[$name] ?? 300
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get cache status
     * 
     * @return array Cache status
     */
    public function get_cache_status() {
        return [
            'cache_enabled' => true,
            'cache_groups' => count($this->cache_groups),
            'default_expirations' => $this->default_expirations,
            'statistics' => $this->get_cache_statistics(),
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get cache metrics
     * 
     * @return array Cache metrics
     */
    public function get_cache_metrics() {
        $stats = $this->get_cache_statistics();
        
        return [
            'hit_ratio' => $stats['cache_hit_ratio'],
            'total_items' => $stats['total_cached_items'],
            'memory_usage' => $stats['memory_usage'],
            'groups_count' => count($this->cache_groups),
            'effectiveness' => $this->calculate_cache_effectiveness($stats)
        ];
    }
    
    /**
     * Calculate cache effectiveness
     * 
     * @param array $stats Cache statistics
     * @return float Cache effectiveness percentage
     */
    private function calculate_cache_effectiveness($stats) {
        $hit_ratio = $stats['cache_hit_ratio'];
        $memory_efficiency = max(0, 1 - ($stats['memory_usage'] / 100000000)); // 100MB threshold
        
        return round(($hit_ratio * 0.7) + ($memory_efficiency * 0.3), 2);
    }
    
    /**
     * Warm caches
     * 
     * @return array Warming result
     */
    public function warm_caches() {
        $items_warmed = 0;
        $errors = [];
        
        try {
            // Warm database caches
            $this->warm_database_caches();
            $items_warmed++;
            
            // Warm AJAX caches
            $this->warm_ajax_caches();
            $items_warmed++;
            
            // Warm migration caches
            $this->warm_migration_caches();
            $items_warmed++;
            
            // Warm admin interface caches
            $this->warm_admin_interface_caches();
            $items_warmed++;
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'items_warmed' => $items_warmed,
            'errors' => $errors
        ];
    }
    
    /**
     * Warm database caches
     */
    private function warm_database_caches() {
        // Warm common database queries
        $this->set_database_cache('post_types', get_post_types(['public' => true]), 600);
        $this->set_database_cache('user_roles', wp_roles()->get_names(), 600);
    }
    
    /**
     * Warm AJAX caches
     */
    private function warm_ajax_caches() {
        // Warm common AJAX responses
        $this->set_ajax_cache('ai_botkit_get_knowledge_base_data', ['type' => 'all', 'page' => 1], ['documents' => [], 'total' => 0], 300);
        $this->set_ajax_cache('ai_botkit_get_my_bots_data', ['status' => 'all', 'page' => 1], ['chatbots' => [], 'total' => 0], 300);
    }
    
    /**
     * Warm migration caches
     */
    private function warm_migration_caches() {
        // Warm migration status
        $this->set_migration_cache(['status' => 'ready', 'last_updated' => current_time('mysql')], 60);
    }
    
    /**
     * Warm admin interface caches
     */
    private function warm_admin_interface_caches() {
        // Warm admin interface data
        $this->set_admin_interface_cache('knowledge_base', [], ['documents' => [], 'statistics' => []], 300);
        $this->set_admin_interface_cache('my_bots', [], ['chatbots' => [], 'statistics' => []], 300);
    }
    
    /**
     * Get cache statistics (alias for get_cache_metrics)
     * 
     * @return array Cache statistics
     */
    public function get_stats() {
        return $this->get_cache_metrics();
    }
}
