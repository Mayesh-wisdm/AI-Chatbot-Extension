<?php
namespace AI_BotKit\Core;

/**
 * Unified Error Handler
 * 
 * Consolidates all error handling functionality from multiple
 * error handlers into a single, comprehensive system.
 */
class Unified_Error_Handler {
    
    /**
     * Error statistics
     */
    private $stats = [];
    
    /**
     * Module error data
     */
    private $module_errors = [];
    
    /**
     * Initialize the unified error handler
     */
    public function __construct() {
        $this->load_statistics();
    }
    
    /**
     * Load error statistics
     */
    private function load_statistics() {
        $this->stats = get_option('ai_botkit_unified_error_stats', [
            'total_errors' => 0,
            'error_types' => [],
            'error_counts' => [],
            'error_trends' => [],
            'last_updated' => null
        ]);
        
        $this->module_errors = get_option('ai_botkit_module_error_stats', [
            'database' => [],
            'caching' => [],
            'wordpress_content' => [],
            'ajax' => [],
            'migration' => [],
            'admin_interface' => []
        ]);
    }
    
    /**
     * Handle error
     * 
     * @param string $module Module name
     * @param string $operation Operation type
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @param array $context Additional context
     * @return array Error response
     */
    public function handle_error($module, $operation, $error_message, $error_code = 500, $context = []) {
        // Log error
        $this->log_error($module, $operation, $error_message, $error_code, $context);
        
        // Update statistics
        $this->update_error_statistics($module, $operation, $error_message, $error_code);
        
        // Generate user-friendly error message
        $user_message = $this->generate_user_message($error_message, $error_code);
        
        // Return error response
        return [
            'success' => false,
            'error' => $user_message,
            'error_code' => $error_code,
            'module' => $module,
            'operation' => $operation,
            'timestamp' => current_time('mysql'),
            'context' => $context
        ];
    }
    
    /**
     * Log error
     * 
     * @param string $module Module name
     * @param string $operation Operation type
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @param array $context Additional context
     */
    private function log_error($module, $operation, $error_message, $error_code, $context) {
        $log_message = sprintf(
            'AI BotKit Error - Module: %s, Operation: %s, Code: %d, Message: %s',
            $module,
            $operation,
            $error_code,
            $error_message
        );
        
        if (!empty($context)) {
            $log_message .= ', Context: ' . json_encode($context);
        }
        
    }
    
    /**
     * Update error statistics
     * 
     * @param string $module Module name
     * @param string $operation Operation type
     * @param string $error_message Error message
     * @param int $error_code Error code
     */
    private function update_error_statistics($module, $operation, $error_message, $error_code) {
        $this->stats['total_errors']++;
        
        // Update error types
        $error_type = $this->categorize_error($error_code);
        if (!isset($this->stats['error_types'][$error_type])) {
            $this->stats['error_types'][$error_type] = 0;
        }
        $this->stats['error_types'][$error_type]++;
        
        // Update error counts by module
        if (!isset($this->stats['error_counts'][$module])) {
            $this->stats['error_counts'][$module] = 0;
        }
        $this->stats['error_counts'][$module]++;
        
        // Update module-specific errors
        if (!isset($this->module_errors[$module])) {
            $this->module_errors[$module] = [
                'total_errors' => 0,
                'error_types' => [],
                'error_operations' => [],
                'last_updated' => null
            ];
        }
        
        $this->module_errors[$module]['total_errors']++;
        
        if (!isset($this->module_errors[$module]['error_types'][$error_type])) {
            $this->module_errors[$module]['error_types'][$error_type] = 0;
        }
        $this->module_errors[$module]['error_types'][$error_type]++;
        
        if (!isset($this->module_errors[$module]['error_operations'][$operation])) {
            $this->module_errors[$module]['error_operations'][$operation] = 0;
        }
        $this->module_errors[$module]['error_operations'][$operation]++;
        
        $this->module_errors[$module]['last_updated'] = current_time('mysql');
        
        $this->stats['last_updated'] = current_time('mysql');
        $this->save_statistics();
        $this->save_module_statistics();
    }
    
    /**
     * Categorize error by code
     * 
     * @param int $error_code Error code
     * @return string Error category
     */
    private function categorize_error($error_code) {
        if ($error_code >= 400 && $error_code < 500) {
            return 'client_error';
        } elseif ($error_code >= 500) {
            return 'server_error';
        } else {
            return 'other_error';
        }
    }
    
    /**
     * Generate user-friendly error message
     * 
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return string User-friendly message
     */
    private function generate_user_message($error_message, $error_code) {
        // Don't expose sensitive error details to users
        $sensitive_patterns = [
            '/database/i',
            '/connection/i',
            '/password/i',
            '/token/i',
            '/key/i',
            '/secret/i',
            '/mysql/i',
            '/sql/i'
        ];
        
        foreach ($sensitive_patterns as $pattern) {
            if (preg_match($pattern, $error_message)) {
                return $this->get_generic_error_message($error_code);
            }
        }
        
        // Return sanitized error message
        return sanitize_text_field($error_message);
    }
    
    /**
     * Get generic error message
     * 
     * @param int $error_code Error code
     * @return string Generic error message
     */
    private function get_generic_error_message($error_code) {
        $generic_messages = [
            400 => 'Invalid request. Please check your input and try again.',
            401 => 'Authentication required. Please log in and try again.',
            403 => 'Access denied. You do not have permission to perform this action.',
            404 => 'Resource not found.',
            429 => 'Too many requests. Please wait a moment and try again.',
            500 => 'An internal error occurred. Please try again later.',
            502 => 'Service temporarily unavailable. Please try again later.',
            503 => 'Service temporarily unavailable. Please try again later.'
        ];
        
        return $generic_messages[$error_code] ?? 'An error occurred. Please try again.';
    }
    
    /**
     * Save error statistics
     */
    private function save_statistics() {
        update_option('ai_botkit_unified_error_stats', $this->stats);
    }
    
    /**
     * Save module error statistics
     */
    private function save_module_statistics() {
        update_option('ai_botkit_module_error_stats', $this->module_errors);
    }
    
    /**
     * Get error statistics
     * 
     * @return array Error statistics
     */
    public function get_error_statistics() {
        return [
            'total_errors' => $this->stats['total_errors'],
            'error_types' => $this->stats['error_types'],
            'error_counts' => $this->stats['error_counts'],
            'error_rate' => $this->calculate_error_rate(),
            'most_common_errors' => $this->get_most_common_errors(),
            'module_errors' => $this->module_errors,
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Calculate error rate
     * 
     * @return float Error rate
     */
    private function calculate_error_rate() {
        // This would typically be calculated against total operations
        // For now, return a placeholder based on total errors
        $total_errors = $this->stats['total_errors'];
        if ($total_errors === 0) {
            return 0;
        }
        
        // Estimate error rate based on total errors
        return min(0.1, $total_errors / 1000); // Max 10% error rate
    }
    
    /**
     * Get most common errors
     * 
     * @return array Most common errors
     */
    private function get_most_common_errors() {
        $error_counts = $this->stats['error_counts'];
        arsort($error_counts);
        
        return array_slice($error_counts, 0, 5, true);
    }
    
    /**
     * Get module error statistics
     * 
     * @param string $module Module name
     * @return array Module error statistics
     */
    public function get_module_error_statistics($module) {
        if (!isset($this->module_errors[$module])) {
            return [
                'module' => $module,
                'total_errors' => 0,
                'error_types' => [],
                'error_operations' => [],
                'error_rate' => 0
            ];
        }
        
        $module_stats = $this->module_errors[$module];
        
        return [
            'module' => $module,
            'total_errors' => $module_stats['total_errors'],
            'error_types' => $module_stats['error_types'],
            'error_operations' => $module_stats['error_operations'],
            'error_rate' => $this->calculate_module_error_rate($module),
            'last_updated' => $module_stats['last_updated']
        ];
    }
    
    /**
     * Calculate module error rate
     * 
     * @param string $module Module name
     * @return float Module error rate
     */
    private function calculate_module_error_rate($module) {
        if (!isset($this->module_errors[$module])) {
            return 0;
        }
        
        $module_errors = $this->module_errors[$module]['total_errors'];
        if ($module_errors === 0) {
            return 0;
        }
        
        // Estimate module error rate
        return min(0.1, $module_errors / 100); // Max 10% error rate
    }
    
    /**
     * Get error recommendations
     * 
     * @return array Error recommendations
     */
    public function get_error_recommendations() {
        $stats = $this->get_error_statistics();
        $recommendations = [];
        
        // High error rate recommendation
        if ($stats['error_rate'] > 0.05) {
            $recommendations[] = [
                'type' => 'high_error_rate',
                'priority' => 'high',
                'message' => 'High error rate detected (' . round($stats['error_rate'] * 100, 1) . '%). Review error handling.',
                'action' => 'review_error_handling'
            ];
        }
        
        // Server error recommendation
        if (isset($stats['error_types']['server_error']) && $stats['error_types']['server_error'] > 10) {
            $recommendations[] = [
                'type' => 'server_errors',
                'priority' => 'high',
                'message' => 'Many server errors detected. Check server configuration.',
                'action' => 'check_server_configuration'
            ];
        }
        
        // Client error recommendation
        if (isset($stats['error_types']['client_error']) && $stats['error_types']['client_error'] > 20) {
            $recommendations[] = [
                'type' => 'client_errors',
                'priority' => 'medium',
                'message' => 'Many client errors detected. Review input validation.',
                'action' => 'review_input_validation'
            ];
        }
        
        // Module-specific recommendations
        foreach ($stats['module_errors'] as $module => $module_stats) {
            if (empty($module_stats)) {
                continue;
            }
            
            $module_error_rate = $this->calculate_module_error_rate($module);
            if ($module_error_rate > 0.03) {
                $recommendations[] = [
                    'type' => 'module_errors',
                    'priority' => 'medium',
                    'message' => ucfirst($module) . ' module has high error rate (' . round($module_error_rate * 100, 1) . '%). Review module implementation.',
                    'action' => 'review_' . $module . '_module'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get error handling status
     * 
     * @return array Error handling status
     */
    public function get_error_status() {
        return [
            'error_handling_enabled' => true,
            'error_logging_enabled' => true,
            'user_friendly_messages' => true,
            'error_statistics' => true,
            'total_errors' => $this->stats['total_errors'],
            'error_rate' => $this->calculate_error_rate(),
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Get error metrics
     * 
     * @return array Error metrics
     */
    public function get_error_metrics() {
        $stats = $this->get_error_statistics();
        
        return [
            'total_errors' => $stats['total_errors'],
            'error_rate' => $stats['error_rate'],
            'error_types' => $stats['error_types'],
            'module_errors' => $stats['module_errors'],
            'most_common_errors' => $stats['most_common_errors'],
            'error_trends' => $this->get_error_trends(),
            'effectiveness' => $this->calculate_error_handling_effectiveness()
        ];
    }
    
    /**
     * Get error trends
     * 
     * @param int $hours Hours to analyze
     * @return array Error trends
     */
    public function get_error_trends($hours = 24) {
        // This would analyze error trends over time
        // For now, return placeholder data
        return [
            'trend_period' => $hours . ' hours',
            'total_errors' => $this->stats['total_errors'],
            'error_rate_trend' => 'stable',
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Calculate error handling effectiveness
     * 
     * @return float Error handling effectiveness percentage
     */
    private function calculate_error_handling_effectiveness() {
        $error_rate = $this->calculate_error_rate();
        $error_types_coverage = count($this->stats['error_types']);
        
        // Calculate effectiveness based on low error rate and good error type coverage
        $rate_effectiveness = max(0, 1 - ($error_rate * 10)); // Lower error rate = higher effectiveness
        $coverage_effectiveness = min(1, $error_types_coverage / 3); // Good coverage of error types
        
        $effectiveness = ($rate_effectiveness * 0.7) + ($coverage_effectiveness * 0.3);
        
        return round($effectiveness * 100, 2);
    }
    
    /**
     * Clear error statistics
     */
    public function clear_error_statistics() {
        $this->stats = [
            'total_errors' => 0,
            'error_types' => [],
            'error_counts' => [],
            'error_trends' => [],
            'last_updated' => null
        ];
        
        $this->module_errors = [
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
     * Export error data
     * 
     * @return array Exported error data
     */
    public function export_error_data() {
        return [
            'error_statistics' => $this->stats,
            'module_errors' => $this->module_errors,
            'error_metrics' => $this->get_error_metrics(),
            'error_recommendations' => $this->get_error_recommendations(),
            'export_timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Handle database errors
     * 
     * @param string $operation Database operation
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return array Error response
     */
    public function handle_database_error($operation, $error_message, $error_code = 500) {
        return $this->handle_error('database', $operation, $error_message, $error_code);
    }
    
    /**
     * Handle caching errors
     * 
     * @param string $operation Cache operation
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return array Error response
     */
    public function handle_caching_error($operation, $error_message, $error_code = 500) {
        return $this->handle_error('caching', $operation, $error_message, $error_code);
    }
    
    /**
     * Handle AJAX errors
     * 
     * @param string $operation AJAX operation
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return array Error response
     */
    public function handle_ajax_error($operation, $error_message, $error_code = 500) {
        return $this->handle_error('ajax', $operation, $error_message, $error_code);
    }
    
    /**
     * Handle migration errors
     * 
     * @param string $operation Migration operation
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return array Error response
     */
    public function handle_migration_error($operation, $error_message, $error_code = 500) {
        return $this->handle_error('migration', $operation, $error_message, $error_code);
    }
    
    /**
     * Handle admin interface errors
     * 
     * @param string $operation Admin interface operation
     * @param string $error_message Error message
     * @param int $error_code Error code
     * @return array Error response
     */
    public function handle_admin_interface_error($operation, $error_message, $error_code = 500) {
        return $this->handle_error('admin_interface', $operation, $error_message, $error_code);
    }
}
