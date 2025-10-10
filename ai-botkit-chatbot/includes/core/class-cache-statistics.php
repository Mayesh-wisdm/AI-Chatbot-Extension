<?php
namespace AI_BotKit\Core;

/**
 * Cache Statistics Class
 * 
 * Tracks and provides detailed cache statistics including
 * hit ratios, performance metrics, and usage patterns.
 */
class Cache_Statistics {
    
    /**
     * Statistics data
     */
    private $stats = [];
    
    /**
     * Initialize cache statistics
     */
    public function __construct() {
        $this->load_statistics();
    }
    
    /**
     * Load statistics from database
     */
    private function load_statistics() {
        $this->stats = get_option('ai_botkit_cache_statistics', [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
            'operations_by_type' => [],
            'operations_by_hour' => [],
            'cache_size_history' => [],
            'memory_usage_history' => [],
            'response_times' => [],
            'last_reset' => null,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Record cache operation
     * 
     * @param string $operation Operation type (hit, miss, write, delete)
     * @param string $cache_type Cache type
     * @param float $response_time Response time in seconds
     */
    public function record_operation($operation, $cache_type = 'general', $response_time = 0) {
        // Update basic counters
        $this->stats[$operation]++;
        
        // Update operations by type
        if (!isset($this->stats['operations_by_type'][$cache_type])) {
            $this->stats['operations_by_type'][$cache_type] = [
                'hits' => 0,
                'misses' => 0,
                'writes' => 0,
                'deletes' => 0
            ];
        }
        $this->stats['operations_by_type'][$cache_type][$operation]++;
        
        // Update operations by hour
        $hour = date('Y-m-d H:00:00');
        if (!isset($this->stats['operations_by_hour'][$hour])) {
            $this->stats['operations_by_hour'][$hour] = [
                'hits' => 0,
                'misses' => 0,
                'writes' => 0,
                'deletes' => 0
            ];
        }
        $this->stats['operations_by_hour'][$hour][$operation]++;
        
        // Record response time
        if ($response_time > 0) {
            $this->stats['response_times'][] = [
                'time' => $response_time,
                'operation' => $operation,
                'cache_type' => $cache_type,
                'timestamp' => time()
            ];
            
            // Keep only last 1000 response times
            if (count($this->stats['response_times']) > 1000) {
                $this->stats['response_times'] = array_slice($this->stats['response_times'], -1000);
            }
        }
        
        // Record cache size
        $this->record_cache_size();
        
        // Record memory usage
        $this->record_memory_usage();
        
        $this->save_statistics();
    }
    
    /**
     * Record cache size
     */
    private function record_cache_size() {
        global $wp_object_cache;
        
        $cache_size = 0;
        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'get_stats')) {
            $stats = $wp_object_cache->get_stats();
            $cache_size = $stats['memory_usage'] ?? 0;
        }
        
        $this->stats['cache_size_history'][] = [
            'size' => $cache_size,
            'timestamp' => time()
        ];
        
        // Keep only last 100 cache size records
        if (count($this->stats['cache_size_history']) > 100) {
            $this->stats['cache_size_history'] = array_slice($this->stats['cache_size_history'], -100);
        }
    }
    
    /**
     * Record memory usage
     */
    private function record_memory_usage() {
        $memory_usage = memory_get_usage(true);
        
        $this->stats['memory_usage_history'][] = [
            'usage' => $memory_usage,
            'timestamp' => time()
        ];
        
        // Keep only last 100 memory usage records
        if (count($this->stats['memory_usage_history']) > 100) {
            $this->stats['memory_usage_history'] = array_slice($this->stats['memory_usage_history'], -100);
        }
    }
    
    /**
     * Save statistics to database
     */
    private function save_statistics() {
        update_option('ai_botkit_cache_statistics', $this->stats);
    }
    
    /**
     * Get comprehensive statistics
     * 
     * @return array Complete statistics
     */
    public function get_statistics() {
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['writes'],
            'deletes' => $this->stats['deletes'],
            'total_operations' => $this->stats['hits'] + $this->stats['misses'] + $this->stats['writes'] + $this->stats['deletes'],
            'hit_ratio' => $this->calculate_hit_ratio(),
            'miss_ratio' => $this->calculate_miss_ratio(),
            'operations_by_type' => $this->stats['operations_by_type'],
            'operations_by_hour' => $this->get_recent_operations_by_hour(),
            'cache_size_trend' => $this->get_cache_size_trend(),
            'memory_usage_trend' => $this->get_memory_usage_trend(),
            'average_response_time' => $this->get_average_response_time(),
            'response_times_by_operation' => $this->get_response_times_by_operation(),
            'last_reset' => $this->stats['last_reset'],
            'created_at' => $this->stats['created_at']
        ];
    }
    
    /**
     * Calculate hit ratio
     * 
     * @return float Hit ratio (0-1)
     */
    public function calculate_hit_ratio() {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        return $total_requests > 0 ? $this->stats['hits'] / $total_requests : 0;
    }
    
    /**
     * Calculate miss ratio
     * 
     * @return float Miss ratio (0-1)
     */
    public function calculate_miss_ratio() {
        return 1 - $this->calculate_hit_ratio();
    }
    
    /**
     * Get recent operations by hour
     * 
     * @param int $hours Number of hours to include
     * @return array Operations by hour
     */
    public function get_recent_operations_by_hour($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $recent_operations = [];
        
        foreach ($this->stats['operations_by_hour'] as $hour => $operations) {
            $hour_timestamp = strtotime($hour);
            if ($hour_timestamp >= $cutoff_time) {
                $recent_operations[$hour] = $operations;
            }
        }
        
        return $recent_operations;
    }
    
    /**
     * Get cache size trend
     * 
     * @param int $hours Number of hours to include
     * @return array Cache size trend
     */
    public function get_cache_size_trend($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $trend = [];
        
        foreach ($this->stats['cache_size_history'] as $record) {
            if ($record['timestamp'] >= $cutoff_time) {
                $hour = date('Y-m-d H:00:00', $record['timestamp']);
                if (!isset($trend[$hour])) {
                    $trend[$hour] = [];
                }
                $trend[$hour][] = $record['size'];
            }
        }
        
        // Calculate average for each hour
        foreach ($trend as $hour => $sizes) {
            $trend[$hour] = array_sum($sizes) / count($sizes);
        }
        
        return $trend;
    }
    
    /**
     * Get memory usage trend
     * 
     * @param int $hours Number of hours to include
     * @return array Memory usage trend
     */
    public function get_memory_usage_trend($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $trend = [];
        
        foreach ($this->stats['memory_usage_history'] as $record) {
            if ($record['timestamp'] >= $cutoff_time) {
                $hour = date('Y-m-d H:00:00', $record['timestamp']);
                if (!isset($trend[$hour])) {
                    $trend[$hour] = [];
                }
                $trend[$hour][] = $record['usage'];
            }
        }
        
        // Calculate average for each hour
        foreach ($trend as $hour => $usages) {
            $trend[$hour] = array_sum($usages) / count($usages);
        }
        
        return $trend;
    }
    
    /**
     * Get average response time
     * 
     * @return float Average response time in seconds
     */
    public function get_average_response_time() {
        if (empty($this->stats['response_times'])) {
            return 0;
        }
        
        $total_time = 0;
        foreach ($this->stats['response_times'] as $time) {
            $total_time += $time['time'];
        }
        
        return $total_time / count($this->stats['response_times']);
    }
    
    /**
     * Get response times by operation
     * 
     * @return array Response times grouped by operation
     */
    public function get_response_times_by_operation() {
        $operation_times = [];
        
        foreach ($this->stats['response_times'] as $time) {
            $operation = $time['operation'];
            if (!isset($operation_times[$operation])) {
                $operation_times[$operation] = [];
            }
            $operation_times[$operation][] = $time['time'];
        }
        
        // Calculate averages
        foreach ($operation_times as $operation => $times) {
            $operation_times[$operation] = [
                'count' => count($times),
                'average' => array_sum($times) / count($times),
                'min' => min($times),
                'max' => max($times)
            ];
        }
        
        return $operation_times;
    }
    
    /**
     * Get statistics by cache type
     * 
     * @param string $cache_type Cache type
     * @return array Statistics for cache type
     */
    public function get_statistics_by_type($cache_type) {
        $type_stats = $this->stats['operations_by_type'][$cache_type] ?? [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0
        ];
        
        $total_requests = $type_stats['hits'] + $type_stats['misses'];
        
        return [
            'cache_type' => $cache_type,
            'hits' => $type_stats['hits'],
            'misses' => $type_stats['misses'],
            'writes' => $type_stats['writes'],
            'deletes' => $type_stats['deletes'],
            'total_requests' => $total_requests,
            'hit_ratio' => $total_requests > 0 ? $type_stats['hits'] / $total_requests : 0,
            'miss_ratio' => $total_requests > 0 ? $type_stats['misses'] / $total_requests : 0
        ];
    }
    
    /**
     * Get performance summary
     * 
     * @return array Performance summary
     */
    public function get_performance_summary() {
        $hit_ratio = $this->calculate_hit_ratio();
        $average_response_time = $this->get_average_response_time();
        
        $performance_score = $this->calculate_performance_score($hit_ratio, $average_response_time);
        
        return [
            'performance_score' => $performance_score,
            'hit_ratio' => $hit_ratio,
            'average_response_time' => $average_response_time,
            'total_operations' => $this->stats['hits'] + $this->stats['misses'] + $this->stats['writes'] + $this->stats['deletes'],
            'cache_efficiency' => $this->calculate_cache_efficiency(),
            'recommendations' => $this->get_performance_recommendations($hit_ratio, $average_response_time)
        ];
    }
    
    /**
     * Calculate performance score
     * 
     * @param float $hit_ratio Hit ratio
     * @param float $average_response_time Average response time
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score($hit_ratio, $average_response_time) {
        $hit_score = $hit_ratio * 100;
        $response_score = max(0, 100 - ($average_response_time * 1000));
        
        return round(($hit_score * 0.7) + ($response_score * 0.3));
    }
    
    /**
     * Calculate cache efficiency
     * 
     * @return float Cache efficiency (0-1)
     */
    private function calculate_cache_efficiency() {
        $total_operations = $this->stats['hits'] + $this->stats['misses'] + $this->stats['writes'] + $this->stats['deletes'];
        
        if ($total_operations === 0) {
            return 0;
        }
        
        $efficient_operations = $this->stats['hits'] + $this->stats['writes'];
        return $efficient_operations / $total_operations;
    }
    
    /**
     * Get performance recommendations
     * 
     * @param float $hit_ratio Hit ratio
     * @param float $average_response_time Average response time
     * @return array Performance recommendations
     */
    private function get_performance_recommendations($hit_ratio, $average_response_time) {
        $recommendations = [];
        
        if ($hit_ratio < 0.7) {
            $recommendations[] = 'Consider increasing cache expiration times to improve hit ratio';
        }
        
        if ($average_response_time > 0.1) {
            $recommendations[] = 'Consider optimizing cache operations to reduce response time';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Cache performance is optimal';
        }
        
        return $recommendations;
    }
    
    /**
     * Reset statistics
     */
    public function reset_statistics() {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
            'operations_by_type' => [],
            'operations_by_hour' => [],
            'cache_size_history' => [],
            'memory_usage_history' => [],
            'response_times' => [],
            'last_reset' => current_time('mysql'),
            'created_at' => $this->stats['created_at']
        ];
        
        $this->save_statistics();
    }
    
    /**
     * Export statistics
     * 
     * @return array Exported statistics
     */
    public function export_statistics() {
        return [
            'statistics' => $this->get_statistics(),
            'performance_summary' => $this->get_performance_summary(),
            'export_timestamp' => current_time('mysql')
        ];
    }
}
