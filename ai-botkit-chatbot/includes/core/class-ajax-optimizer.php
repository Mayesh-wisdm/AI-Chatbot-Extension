<?php
namespace AI_BotKit\Core;

/**
 * AJAX Optimizer Class
 * 
 * Optimizes AJAX performance with response caching, request optimization,
 * and performance monitoring for improved admin interface responsiveness.
 */
class AJAX_Optimizer {
    
    /**
     * AJAX cache manager
     */
    private $cache_manager;
    
    /**
     * AJAX performance monitor
     */
    private $monitor;
    
    /**
     * AJAX request optimizer
     */
    private $request_optimizer;
    
    /**
     * AJAX response compressor
     */
    private $compressor;
    
    /**
     * Initialize the AJAX optimizer
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Unified_Performance_Monitor();
        $this->request_optimizer = new AJAX_Request_Optimizer();
        $this->compressor = new AJAX_Response_Compressor();
    }
    
    /**
     * Optimize AJAX request
     * 
     * @param string $action AJAX action
     * @param array $params Request parameters
     * @param callable $callback Callback to generate response
     * @return array Optimized response
     */
    public function optimize_request($action, $params, $callback) {
        $start_time = microtime(true);
        
        try {
            // Optimize request parameters
            $optimized_params = $this->request_optimizer->optimize_request_params($params);
            
            // Get cached response or generate new one
            $response = $this->cache_manager->get_cached_response(
                $action,
                $optimized_params,
                $callback
            );
            
            // Compress response if needed
            $compressed_response = $this->compressor->compress_response($response);
            
            $end_time = microtime(true);
            $response_time = $end_time - $start_time;
            
            // Record performance metrics
            $this->monitor->record_request($action, $response_time, 200);
            
            return $compressed_response;
            
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $response_time = $end_time - $start_time;
            
            // Record error metrics
            $this->monitor->record_request($action, $response_time, 500);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => $response_time
            ];
        }
    }
    
    /**
     * Get AJAX performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        return [
            'response_times' => $this->monitor->get_response_times(),
            'cache_hit_ratio' => $this->cache_manager->get_cache_hit_ratio(),
            'error_rate' => $this->monitor->get_error_rate(),
            'total_requests' => $this->monitor->get_total_requests(),
            'average_response_time' => $this->monitor->get_average_response_time(),
            'compression_ratio' => $this->compressor->get_compression_ratio(),
            'optimization_effectiveness' => $this->calculate_optimization_effectiveness()
        ];
    }
    
    /**
     * Calculate optimization effectiveness
     * 
     * @return float Optimization effectiveness percentage
     */
    private function calculate_optimization_effectiveness() {
        $cache_hit_ratio = $this->cache_manager->get_cache_hit_ratio();
        $compression_ratio = $this->compressor->get_compression_ratio();
        $error_rate = $this->monitor->get_error_rate();
        
        // Calculate effectiveness based on cache hits, compression, and low error rate
        $effectiveness = ($cache_hit_ratio * 0.4) + ($compression_ratio * 0.3) + ((1 - $error_rate) * 0.3);
        
        return round($effectiveness * 100, 2);
    }
    
    /**
     * Get AJAX optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = [];
        $metrics = $this->get_performance_metrics();
        
        // Cache hit ratio recommendations
        if ($metrics['cache_hit_ratio'] < 0.7) {
            $recommendations[] = [
                'type' => 'cache_hit_ratio',
                'priority' => 'high',
                'message' => 'AJAX cache hit ratio is low (' . round($metrics['cache_hit_ratio'] * 100, 1) . '%). Consider increasing cache expiration times.',
                'action' => 'increase_cache_expiration'
            ];
        }
        
        // Response time recommendations
        if ($metrics['average_response_time'] > 0.5) {
            $recommendations[] = [
                'type' => 'response_time',
                'priority' => 'medium',
                'message' => 'Average AJAX response time is high (' . round($metrics['average_response_time'] * 1000, 1) . 'ms). Consider optimizing queries.',
                'action' => 'optimize_queries'
            ];
        }
        
        // Error rate recommendations
        if ($metrics['error_rate'] > 0.05) {
            $recommendations[] = [
                'type' => 'error_rate',
                'priority' => 'high',
                'message' => 'AJAX error rate is high (' . round($metrics['error_rate'] * 100, 1) . '%). Review error handling.',
                'action' => 'review_error_handling'
            ];
        }
        
        // Compression recommendations
        if ($metrics['compression_ratio'] < 0.3) {
            $recommendations[] = [
                'type' => 'compression',
                'priority' => 'low',
                'message' => 'Response compression could be improved. Consider enabling compression for large responses.',
                'action' => 'enable_compression'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get AJAX optimization status
     * 
     * @return array Optimization status
     */
    public function get_optimization_status() {
        return [
            'caching_enabled' => true,
            'compression_enabled' => true,
            'monitoring_enabled' => true,
            'request_optimization' => true,
            'error_handling' => true,
            'last_optimization' => current_time('mysql')
        ];
    }
    
    /**
     * Clear AJAX optimization cache
     */
    public function clear_optimization_cache() {
        $this->cache_manager->clear_all_caches();
        $this->monitor->clear_performance_data();
    }
    
    /**
     * Get AJAX optimization summary
     * 
     * @return array Optimization summary
     */
    public function get_optimization_summary() {
        $metrics = $this->get_performance_metrics();
        $recommendations = $this->get_optimization_recommendations();
        
        return [
            'overall_score' => $this->calculate_overall_score($metrics),
            'metrics' => $metrics,
            'recommendations' => $recommendations,
            'high_priority_issues' => count(array_filter($recommendations, function($rec) {
                return $rec['priority'] === 'high';
            })),
            'optimization_status' => $this->get_optimization_status()
        ];
    }
    
    /**
     * Calculate overall optimization score
     * 
     * @param array $metrics Performance metrics
     * @return int Overall score (0-100)
     */
    private function calculate_overall_score($metrics) {
        $score = 0;
        
        // Cache hit ratio score (30% weight)
        $cache_score = $metrics['cache_hit_ratio'] * 100;
        $score += $cache_score * 0.3;
        
        // Response time score (25% weight)
        $response_score = max(0, 100 - ($metrics['average_response_time'] * 200));
        $score += $response_score * 0.25;
        
        // Error rate score (25% weight)
        $error_score = max(0, 100 - ($metrics['error_rate'] * 1000));
        $score += $error_score * 0.25;
        
        // Compression score (20% weight)
        $compression_score = $metrics['compression_ratio'] * 100;
        $score += $compression_score * 0.2;
        
        return round($score);
    }
}
