<?php
namespace AI_BotKit\Core;

/**
 * Content Processing Monitor Class
 * 
 * Monitors content processing performance and provides
 * optimization recommendations.
 */
class Content_Processing_Monitor {
    
    /**
     * Processing statistics
     */
    private $stats = [];
    
    /**
     * Initialize the content processing monitor
     */
    public function __construct() {
        $this->load_statistics();
    }
    
    /**
     * Load processing statistics
     */
    private function load_statistics() {
        $this->stats = get_option('ai_botkit_content_processing_stats', [
            'total_processing_time' => 0,
            'total_items_processed' => 0,
            'total_errors' => 0,
            'processing_times' => [],
            'memory_usage' => [],
            'last_updated' => null
        ]);
    }
    
    /**
     * Record processing metrics
     * 
     * @param float $processing_time Processing time in seconds
     * @param int $items_processed Number of items processed
     * @param int $errors Number of errors
     */
    public function record_processing_metrics($processing_time, $items_processed, $errors) {
        $this->stats['total_processing_time'] += $processing_time;
        $this->stats['total_items_processed'] += $items_processed;
        $this->stats['total_errors'] += $errors;
        
        $this->stats['processing_times'][] = [
            'time' => $processing_time,
            'items' => $items_processed,
            'timestamp' => time()
        ];
        
        $this->stats['memory_usage'][] = [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => time()
        ];
        
        // Keep only last 100 entries
        if (count($this->stats['processing_times']) > 100) {
            $this->stats['processing_times'] = array_slice($this->stats['processing_times'], -100);
        }
        
        if (count($this->stats['memory_usage']) > 100) {
            $this->stats['memory_usage'] = array_slice($this->stats['memory_usage'], -100);
        }
        
        $this->stats['last_updated'] = current_time('mysql');
        $this->save_statistics();
    }
    
    /**
     * Save processing statistics
     */
    private function save_statistics() {
        update_option('ai_botkit_content_processing_stats', $this->stats);
    }
    
    /**
     * Get processing metrics
     * 
     * @return array Processing metrics
     */
    public function get_processing_metrics() {
        $total_processing_time = $this->stats['total_processing_time'];
        $total_items_processed = $this->stats['total_items_processed'];
        $total_errors = $this->stats['total_errors'];
        
        $average_processing_time = $total_items_processed > 0 ? 
            $total_processing_time / $total_items_processed : 0;
        
        $error_rate = $total_items_processed > 0 ? 
            ($total_errors / $total_items_processed) * 100 : 0;
        
        return [
            'total_processing_time' => $total_processing_time,
            'total_items_processed' => $total_items_processed,
            'total_errors' => $total_errors,
            'average_processing_time' => $average_processing_time,
            'error_rate' => $error_rate,
            'processing_times' => $this->stats['processing_times'],
            'memory_usage' => $this->stats['memory_usage'],
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Get processing performance summary
     * 
     * @return array Performance summary
     */
    public function get_performance_summary() {
        $metrics = $this->get_processing_metrics();
        
        return [
            'performance_score' => $this->calculate_performance_score($metrics),
            'processing_efficiency' => $this->calculate_processing_efficiency($metrics),
            'error_rate' => $metrics['error_rate'],
            'average_processing_time' => $metrics['average_processing_time'],
            'recommendations' => $this->get_performance_recommendations($metrics)
        ];
    }
    
    /**
     * Calculate performance score
     * 
     * @param array $metrics Processing metrics
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score($metrics) {
        $score = 100;
        
        // Deduct points for high error rate
        if ($metrics['error_rate'] > 10) {
            $score -= 30;
        } elseif ($metrics['error_rate'] > 5) {
            $score -= 20;
        } elseif ($metrics['error_rate'] > 1) {
            $score -= 10;
        }
        
        // Deduct points for slow processing
        if ($metrics['average_processing_time'] > 2.0) {
            $score -= 30;
        } elseif ($metrics['average_processing_time'] > 1.0) {
            $score -= 20;
        } elseif ($metrics['average_processing_time'] > 0.5) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Calculate processing efficiency
     * 
     * @param array $metrics Processing metrics
     * @return float Processing efficiency (0-1)
     */
    private function calculate_processing_efficiency($metrics) {
        if ($metrics['total_items_processed'] === 0) {
            return 0;
        }
        
        $successful_items = $metrics['total_items_processed'] - $metrics['total_errors'];
        return $successful_items / $metrics['total_items_processed'];
    }
    
    /**
     * Get performance recommendations
     * 
     * @param array $metrics Processing metrics
     * @return array Performance recommendations
     */
    private function get_performance_recommendations($metrics) {
        $recommendations = [];
        
        // Error rate recommendations
        if ($metrics['error_rate'] > 10) {
            $recommendations[] = [
                'type' => 'error_rate',
                'priority' => 'high',
                'message' => 'High error rate detected. Review processing logic.',
                'error_rate' => $metrics['error_rate'],
                'action' => 'review_processing_logic'
            ];
        }
        
        // Processing time recommendations
        if ($metrics['average_processing_time'] > 2.0) {
            $recommendations[] = [
                'type' => 'processing_time',
                'priority' => 'high',
                'message' => 'Slow processing detected. Consider optimization.',
                'average_time' => $metrics['average_processing_time'],
                'action' => 'optimize_processing'
            ];
        }
        
        // Memory usage recommendations
        $current_memory = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_memory_limit_to_bytes($memory_limit);
        
        if ($current_memory > ($memory_limit_bytes * 0.8)) {
            $recommendations[] = [
                'type' => 'memory_usage',
                'priority' => 'medium',
                'message' => 'High memory usage detected.',
                'current_usage' => $current_memory,
                'memory_limit' => $memory_limit_bytes,
                'action' => 'optimize_memory_usage'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Convert memory limit string to bytes
     * 
     * @param string $limit Memory limit string
     * @return int Memory limit in bytes
     */
    private function convert_memory_limit_to_bytes($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
    
    /**
     * Clear processing statistics
     */
    public function clear_processing_statistics() {
        $this->stats = [
            'total_processing_time' => 0,
            'total_items_processed' => 0,
            'total_errors' => 0,
            'processing_times' => [],
            'memory_usage' => [],
            'last_updated' => null
        ];
        
        $this->save_statistics();
    }
    
    /**
     * Export processing data
     * 
     * @return array Exported processing data
     */
    public function export_processing_data() {
        return [
            'statistics' => $this->stats,
            'metrics' => $this->get_processing_metrics(),
            'performance_summary' => $this->get_performance_summary(),
            'export_timestamp' => current_time('mysql')
        ];
    }
}
