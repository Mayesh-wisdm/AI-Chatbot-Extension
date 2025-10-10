<?php
namespace AI_BotKit\Core;

/**
 * Unified Performance Monitor
 * 
 * Consolidates all performance monitoring functionality from multiple
 * performance monitors into a single, comprehensive system.
 */
class Unified_Performance_Monitor {
    
    /**
     * Performance statistics
     */
    private $stats = [];
    
    /**
     * Module performance data
     */
    private $module_stats = [];
    
    /**
     * Initialize the unified performance monitor
     */
    public function __construct() {
        $this->load_statistics();
    }
    
    /**
     * Load performance statistics
     */
    private function load_statistics() {
        $this->stats = get_option('ai_botkit_unified_performance_stats', [
            'total_operations' => 0,
            'total_operation_time' => 0,
            'total_items_processed' => 0,
            'operation_times' => [],
            'module_performance' => [],
            'performance_trends' => [],
            'last_updated' => null
        ]);
        
        $this->module_stats = get_option('ai_botkit_module_performance_stats', [
            'database' => [],
            'caching' => [],
            'wordpress_content' => [],
            'ajax' => [],
            'migration' => [],
            'admin_interface' => []
        ]);
    }
    
    /**
     * Record performance event
     * 
     * @param string $event Event type
     * @param string $status Event status
     * @param array $data Additional event data
     */
    public function record_event($event, $status, $data = []) {
        $event_data = [
            'event' => $event,
            'status' => $status,
            'timestamp' => time(),
            'data' => $data
        ];
        
        $this->stats['total_operations']++;
        $this->stats['operation_times'][] = $event_data;
        
        // Keep only last 1000 events
        if (count($this->stats['operation_times']) > 1000) {
            $this->stats['operation_times'] = array_slice($this->stats['operation_times'], -1000);
        }
        
        $this->stats['last_updated'] = current_time('mysql');
        $this->save_statistics();
    }
    
    /**
     * Record module operation
     * 
     * @param string $module Module name
     * @param string $operation Operation type
     * @param float $operation_time Operation time in seconds
     * @param int $items_processed Number of items processed
     */
    public function record_module_operation($module, $operation, $operation_time, $items_processed) {
        if (!isset($this->module_stats[$module])) {
            $this->module_stats[$module] = [
                'total_operations' => 0,
                'total_operation_time' => 0,
                'total_items_processed' => 0,
                'operation_times' => [],
                'last_updated' => null
            ];
        }
        
        $this->module_stats[$module]['total_operations']++;
        $this->module_stats[$module]['total_operation_time'] += $operation_time;
        $this->module_stats[$module]['total_items_processed'] += $items_processed;
        
        $this->module_stats[$module]['operation_times'][] = [
            'operation' => $operation,
            'time' => $operation_time,
            'items_processed' => $items_processed,
            'timestamp' => time()
        ];
        
        // Keep only last 500 operations per module
        if (count($this->module_stats[$module]['operation_times']) > 500) {
            $this->module_stats[$module]['operation_times'] = array_slice($this->module_stats[$module]['operation_times'], -500);
        }
        
        $this->module_stats[$module]['last_updated'] = current_time('mysql');
        $this->save_module_statistics();
    }
    
    /**
     * Save performance statistics
     */
    private function save_statistics() {
        update_option('ai_botkit_unified_performance_stats', $this->stats);
    }
    
    /**
     * Save module statistics
     */
    private function save_module_statistics() {
        update_option('ai_botkit_module_performance_stats', $this->module_stats);
    }
    
    /**
     * Get overall performance metrics
     * 
     * @return array Overall performance metrics
     */
    public function get_overall_metrics() {
        $total_operations = $this->stats['total_operations'];
        $total_operation_time = $this->stats['total_operation_time'];
        $total_items_processed = $this->stats['total_items_processed'];
        
        $average_operation_time = $total_operations > 0 ? $total_operation_time / $total_operations : 0;
        $average_items_per_operation = $total_operations > 0 ? $total_items_processed / $total_operations : 0;
        
        return [
            'total_operations' => $total_operations,
            'total_operation_time' => $total_operation_time,
            'total_items_processed' => $total_items_processed,
            'average_operation_time' => $average_operation_time,
            'average_items_per_operation' => $average_items_per_operation,
            'module_metrics' => $this->get_module_metrics(),
            'performance_trends' => $this->get_performance_trends(),
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Get module-specific metrics
     * 
     * @return array Module metrics
     */
    public function get_module_metrics() {
        $metrics = [];
        
        foreach ($this->module_stats as $module => $stats) {
            if (empty($stats)) {
                continue;
            }
            
            $total_operations = $stats['total_operations'];
            $total_operation_time = $stats['total_operation_time'];
            $total_items_processed = $stats['total_items_processed'];
            
            $metrics[$module] = [
                'total_operations' => $total_operations,
                'total_operation_time' => $total_operation_time,
                'total_items_processed' => $total_items_processed,
                'average_operation_time' => $total_operations > 0 ? $total_operation_time / $total_operations : 0,
                'average_items_per_operation' => $total_operations > 0 ? $total_items_processed / $total_operations : 0,
                'performance_score' => $this->calculate_module_performance_score($stats),
                'last_updated' => $stats['last_updated']
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Calculate module performance score
     * 
     * @param array $stats Module statistics
     * @return float Performance score (0-100)
     */
    private function calculate_module_performance_score($stats) {
        if (empty($stats['operation_times'])) {
            return 0;
        }
        
        $avg_time = $stats['total_operation_time'] / $stats['total_operations'];
        $avg_items = $stats['total_items_processed'] / $stats['total_operations'];
        
        // Calculate score based on time efficiency and throughput
        $time_score = max(0, 100 - ($avg_time * 50)); // Penalize slow operations
        $throughput_score = min(100, $avg_items * 10); // Reward high throughput
        
        return round(($time_score * 0.6) + ($throughput_score * 0.4), 2);
    }
    
    /**
     * Get performance trends
     * 
     * @param int $hours Hours to analyze
     * @return array Performance trends
     */
    public function get_performance_trends($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $recent_operations = array_filter($this->stats['operation_times'], function($operation) use ($cutoff_time) {
            return $operation['timestamp'] >= $cutoff_time;
        });
        
        $trends = [];
        foreach ($recent_operations as $operation) {
            $hour = date('Y-m-d H:00:00', $operation['timestamp']);
            if (!isset($trends[$hour])) {
                $trends[$hour] = [
                    'total_operations' => 0,
                    'successful_operations' => 0,
                    'failed_operations' => 0
                ];
            }
            
            $trends[$hour]['total_operations']++;
            if ($operation['status'] === 'success') {
                $trends[$hour]['successful_operations']++;
            } else {
                $trends[$hour]['failed_operations']++;
            }
        }
        
        // Calculate success rates
        foreach ($trends as $hour => $data) {
            $trends[$hour]['success_rate'] = $data['total_operations'] > 0 ? 
                ($data['successful_operations'] / $data['total_operations']) * 100 : 0;
        }
        
        return $trends;
    }
    
    /**
     * Get performance by operation type
     * 
     * @param string $operation_type Operation type
     * @return array Performance by operation type
     */
    public function get_performance_by_operation_type($operation_type) {
        $operation_requests = array_filter($this->stats['operation_times'], function($request) use ($operation_type) {
            return $request['event'] === $operation_type;
        });
        
        if (empty($operation_requests)) {
            return [
                'operation_type' => $operation_type,
                'total_operations' => 0,
                'success_rate' => 0,
                'average_time' => 0
            ];
        }
        
        $total_operations = count($operation_requests);
        $successful_operations = count(array_filter($operation_requests, function($op) {
            return $op['status'] === 'success';
        }));
        
        return [
            'operation_type' => $operation_type,
            'total_operations' => $total_operations,
            'success_rate' => ($successful_operations / $total_operations) * 100,
            'average_time' => 0 // Would need time data in events
        ];
    }
    
    /**
     * Get module performance by name
     * 
     * @param string $module_name Module name
     * @return array Module performance
     */
    public function get_module_performance($module_name) {
        if (!isset($this->module_stats[$module_name])) {
            return [
                'module_name' => $module_name,
                'total_operations' => 0,
                'average_operation_time' => 0,
                'total_items_processed' => 0,
                'performance_score' => 0
            ];
        }
        
        $stats = $this->module_stats[$module_name];
        $total_operations = $stats['total_operations'];
        
        return [
            'module_name' => $module_name,
            'total_operations' => $total_operations,
            'average_operation_time' => $total_operations > 0 ? $stats['total_operation_time'] / $total_operations : 0,
            'total_items_processed' => $stats['total_items_processed'],
            'performance_score' => $this->calculate_module_performance_score($stats),
            'last_updated' => $stats['last_updated']
        ];
    }
    
    /**
     * Get performance recommendations
     * 
     * @return array Performance recommendations
     */
    public function get_performance_recommendations() {
        $recommendations = [];
        $metrics = $this->get_overall_metrics();
        
        // Overall performance recommendations
        if ($metrics['average_operation_time'] > 2.0) {
            $recommendations[] = [
                'type' => 'overall_performance',
                'priority' => 'high',
                'message' => 'Average operation time is high (' . round($metrics['average_operation_time'] * 1000, 1) . 'ms). Consider optimizing operations.',
                'action' => 'optimize_overall_operations'
            ];
        }
        
        // Module-specific recommendations
        foreach ($metrics['module_metrics'] as $module => $module_metrics) {
            if ($module_metrics['performance_score'] < 70) {
                $recommendations[] = [
                    'type' => 'module_performance',
                    'priority' => 'medium',
                    'message' => ucfirst($module) . ' module performance score is low (' . $module_metrics['performance_score'] . '%). Consider optimizing this module.',
                    'action' => 'optimize_' . $module . '_module'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get performance summary
     * 
     * @return array Performance summary
     */
    public function get_performance_summary() {
        $metrics = $this->get_overall_metrics();
        $recommendations = $this->get_performance_recommendations();
        
        return [
            'overall_score' => $this->calculate_overall_score($metrics),
            'metrics' => $metrics,
            'recommendations' => $recommendations,
            'high_priority_issues' => count(array_filter($recommendations, function($rec) {
                return $rec['priority'] === 'high';
            })),
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Calculate overall performance score
     * 
     * @param array $metrics Performance metrics
     * @return int Overall score (0-100)
     */
    private function calculate_overall_score($metrics) {
        $score = 100;
        
        // Deduct points for slow operations
        if ($metrics['average_operation_time'] > 3.0) {
            $score -= 30;
        } elseif ($metrics['average_operation_time'] > 2.0) {
            $score -= 20;
        } elseif ($metrics['average_operation_time'] > 1.0) {
            $score -= 10;
        }
        
        // Deduct points for low module performance
        $low_performance_modules = 0;
        foreach ($metrics['module_metrics'] as $module_metrics) {
            if ($module_metrics['performance_score'] < 70) {
                $low_performance_modules++;
            }
        }
        
        if ($low_performance_modules > 0) {
            $score -= min(30, $low_performance_modules * 5);
        }
        
        return max(0, $score);
    }
    
    /**
     * Clear performance data
     */
    public function clear_performance_data() {
        $this->stats = [
            'total_operations' => 0,
            'total_operation_time' => 0,
            'total_items_processed' => 0,
            'operation_times' => [],
            'module_performance' => [],
            'performance_trends' => [],
            'last_updated' => null
        ];
        
        $this->module_stats = [
            'database' => [],
            'caching' => [],
            'wordpress_content' => [],
            'ajax' => [],
            'migration' => [],
            'admin_interface' => []
        ];
        
        $this->save_statistics();
        $this->save_module_statistics();
    }
    
    /**
     * Export performance data
     * 
     * @return array Exported performance data
     */
    public function export_performance_data() {
        return [
            'overall_statistics' => $this->stats,
            'module_statistics' => $this->module_stats,
            'overall_metrics' => $this->get_overall_metrics(),
            'performance_summary' => $this->get_performance_summary(),
            'export_timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Get performance dashboard data
     * 
     * @return array Dashboard data
     */
    public function get_dashboard_data() {
        return [
            'overall_metrics' => $this->get_overall_metrics(),
            'module_metrics' => $this->get_module_metrics(),
            'performance_trends' => $this->get_performance_trends(24),
            'recommendations' => $this->get_performance_recommendations(),
            'performance_summary' => $this->get_performance_summary(),
            'last_updated' => $this->stats['last_updated']
        ];
    }
}
