<?php
namespace AI_BotKit\Monitoring;

/**
 * Advanced Analytics System
 */
class Analytics {
    /**
     * Cache manager instance
     */
    private $cache_manager;

    /**
     * Database prefix
     */
    private $prefix;

    /**
     * Initialize analytics
     */
    public function __construct($cache_manager) {
        global $wpdb;
        $this->cache_manager = $cache_manager;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action('ai_botkit_chat_message', [$this, 'track_chat_interaction'], 10, 3);
        add_action('ai_botkit_document_processed', [$this, 'track_document_processing'], 10, 2);
        add_action('ai_botkit_error_occurred', [$this, 'track_error'], 10, 3);
        
        // Clear analytics cache when new data is tracked
        add_action('ai_botkit_chat_message', [$this, 'clear_analytics_cache'], 20);
        add_action('ai_botkit_document_processed', [$this, 'clear_analytics_cache'], 20);
        add_action('ai_botkit_error_occurred', [$this, 'clear_analytics_cache'], 20);
    }

    /**
     * Get dashboard analytics data
     */
    public function get_dashboard_data($filters = []): array {
        // Create a more specific cache key that includes all filter parameters
        $cache_key = 'analytics_dashboard_' . md5(json_encode($filters));
        
        // Reduce cache duration to 5 minutes for more responsive filter changes
        return $this->cache_manager->remember($cache_key, function() use ($filters) {
            return [
                'overview' => $this->get_overview_stats($filters),
                'time_series' => $this->get_time_series_data(
                    $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
                    $filters['end_date'] ?? gmdate('Y-m-d'),
                    $filters['interval'] ?? 'day'
                ),
                'top_queries' => $this->analyze_common_questions($filters),
                'error_rates' => $this->analyze_errors($filters),
                'performance' => $this->analyze_performance($filters)
            ];
        }, 300); // 5 minutes instead of 1 hour
    }

    /**
     * Get overview statistics
     */
    private function get_overview_stats($filters = []): array {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_interactions,
                COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.conversation_id'))) as total_conversations,
                COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.user_id'))) as total_users,
                COALESCE(AVG(CAST(JSON_EXTRACT(event_data, '$.processing_time') AS DECIMAL(10,6))), 0) as avg_response_time,
                COALESCE(SUM(CAST(JSON_EXTRACT(event_data, '$.token_usage') AS UNSIGNED)), 0) as total_tokens,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM {$this->prefix}analytics
            WHERE created_at BETWEEN %s AND %s",
            $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? gmdate('Y-m-d')
        ), ARRAY_A);


        // Ensure we always return an array with default values if query fails
        if (!$result) {
            return [
                'total_interactions' => 0,
                'total_conversations' => 0,
                'total_users' => 0,
                'avg_response_time' => 0,
                'total_tokens' => 0,
                'active_days' => 0
            ];
        }

        // Convert all values to integers/floats
        return array_map(function($value) {
            return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : 0;
        }, $result);
    }

    /**
     * Get time series data
     */
    private function get_time_series_data($start_date, $end_date, $interval = 'day'): array {
        global $wpdb;
        
        $format = $interval === 'hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(created_at, %s) as time_period,
                COUNT(*) as total_events,
                COUNT(DISTINCT JSON_EXTRACT(event_data, '$.user_id')) as unique_users,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.processing_time')), 0) as avg_processing_time,
                COALESCE(SUM(JSON_EXTRACT(event_data, '$.token_usage')), 0) as total_tokens,
                COUNT(DISTINCT JSON_EXTRACT(event_data, '$.conversation_id')) as conversations
            FROM {$this->prefix}analytics
            WHERE created_at BETWEEN %s AND %s
            GROUP BY time_period
            ORDER BY time_period ASC",
            $format,
            $start_date,
            $end_date
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Analyze common questions and patterns
     */
    private function analyze_common_questions(array $filters): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.query_type')), 'unknown') as query_type,
                COUNT(*) as frequency,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.response_quality')), 0) as avg_quality,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.processing_time')), 0) as avg_response_time,
                COUNT(DISTINCT JSON_EXTRACT(event_data, '$.user_id')) as unique_users
            FROM {$this->prefix}analytics
            WHERE event_type = 'chat_interaction'
            AND created_at BETWEEN %s AND %s
            GROUP BY query_type
            ORDER BY frequency DESC
            LIMIT 10",
            $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? gmdate('Y-m-d')
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Analyze errors and issues
     */
    private function analyze_errors(array $filters): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.error_type')), 'unknown') as error_type,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.component')), 'unknown') as component,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.severity')), 0) as avg_severity
            FROM {$this->prefix}analytics
            WHERE event_type = 'error'
            AND created_at BETWEEN %s AND %s
            GROUP BY error_type, component
            ORDER BY count DESC",
            $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? gmdate('Y-m-d')
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Analyze system performance
     */
    private function analyze_performance(array $filters): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.processing_time')), 0) as avg_processing_time,
                COALESCE(MAX(JSON_EXTRACT(event_data, '$.processing_time')), 0) as max_processing_time,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.token_usage')), 0) as avg_token_usage,
                COALESCE(AVG(JSON_EXTRACT(event_data, '$.context_chunks')), 0) as avg_context_chunks,
                COUNT(*) as total_requests,
                SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as error_count
            FROM {$this->prefix}analytics
            WHERE event_type IN ('chat_interaction', 'error')
            AND created_at BETWEEN %s AND %s
            GROUP BY date
            ORDER BY date ASC",
            $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? gmdate('Y-m-d')
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Track chat interactions
     */
    public function track_chat_interaction(string $message, array $response, array $metadata): void {
        global $wpdb;

        $data = [
            'event_type' => 'chat_interaction',
            'event_data' => wp_json_encode([
                'message_length' => strlen($message),
                'response_length' => strlen($response['response']),
                'processing_time' => $metadata['processing_time'] ?? 0,
                'token_usage' => $metadata['tokens'] ?? 0,
                'context_chunks' => $metadata['context_chunks'] ?? 0,
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
                'session_id' => $metadata['session_id'] ?? '',
                'conversation_id' => $metadata['conversation_id'] ?? '',
                'query_type' => $this->classify_query($message),
                'response_quality' => $this->analyze_response_quality($response),
            ]),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($this->prefix . 'analytics', $data);
    }

    public function track_document_processing($document_id, $metadata): void {
        global $wpdb;

        $data = [
            'event_type' => 'document_processed',
            'event_data' => wp_json_encode([
                'document_id' => $document_id,
                'processing_time' => $metadata['processing_time'] ?? 0,
                'chunk_count' => $metadata['chunks'] ?? 0,
                'embedding_count' => $metadata['embeddings'] ?? 0,
                'timestamp' => current_time('mysql')
            ]),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($this->prefix . 'analytics', $data);
    }

    public function track_error($error_type, $message, $metadata): void {
        global $wpdb;

        $data = [
            'event_type' => 'error',
            'event_data' => wp_json_encode([
                'error_type' => $error_type,
                'message' => $message,
                'component' => $metadata['component'] ?? 'unknown',
                'severity' => $metadata['severity'] ?? 'medium',
                'timestamp' => current_time('mysql')
            ]),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($this->prefix . 'analytics', $data);
    }

    /**
     * Classify query type using basic NLP
     */
    private function classify_query(string $message): string {
        $message = strtolower($message);
        
        $patterns = [
            'product_query' => '/product|price|stock|availability|shipping/',
            'support_query' => '/help|support|issue|problem|error/',
            'feature_query' => '/how to|can i|is it possible/',
            'comparison_query' => '/vs|versus|compare|difference/',
            'technical_query' => '/api|code|implementation|setup/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $message)) {
                return $type;
            }
        }

        return 'general_query';
    }

    /**
     * Analyze response quality
     */
    private function analyze_response_quality(array $response): float {
        $quality_score = 0;
        
        // Check response length (0-1 points)
        $length = strlen($response['response']);
        $quality_score += min(1, $length / 1000);
        
        // Check for citations (0-1 points)
        $has_citations = !empty($response['metadata']['context_chunks']) && $response['metadata']['context_chunks'] > 0;
        $quality_score += $has_citations ? 1 : 0;
        
        // Check for code blocks (0-1 points)
        $has_code = strpos($response['response'], '```') !== false;
        $quality_score += $has_code ? 1 : 0;
        
        // Check for formatting (0-1 points)
        $has_formatting = preg_match('/[*_#]/', $response['response']);
        $quality_score += $has_formatting ? 1 : 0;
        
        // Normalize to 0-1 scale
        return $quality_score / 4;
    }

    /**
     * Clear analytics cache to ensure fresh data
     */
    public function clear_analytics_cache(): void {
        // Clear all analytics dashboard cache entries
        $this->cache_manager->delete('analytics_dashboard_*');
    }

    /**
     * Force refresh analytics data by bypassing cache
     */
    public function get_dashboard_data_fresh($filters = []): array {
        // Clear cache first
        $this->clear_analytics_cache();
        
        // Get fresh data without cache
        return [
            'overview' => $this->get_overview_stats($filters),
            'time_series' => $this->get_time_series_data(
                $filters['start_date'] ?? gmdate('Y-m-d', strtotime('-30 days')),
                $filters['end_date'] ?? gmdate('Y-m-d'),
                $filters['interval'] ?? 'day'
            ),
            'top_queries' => $this->analyze_common_questions($filters),
            'error_rates' => $this->analyze_errors($filters),
            'performance' => $this->analyze_performance($filters)
        ];
    }
} 