<?php
namespace AI_BotKit\Monitoring;

use AI_BotKit\Core\LLM_Client;
use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Core\Unified_Cache_Manager;

/**
 * Health Checks system for monitoring system health and performance
 */
class Health_Checks {
    /**
     * Cache key for health status
     */
    private const CACHE_KEY = 'health_status';
    
    /**
     * Cache duration for health status (5 minutes)
     */
    private const CACHE_DURATION = 300;

    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * LLM Client instance
     */
    private $llm_client;

    /**
     * Cache Manager instance
     */
    private $cache_manager;

    /**
     * Initialize the health checks system
     */
    public function __construct(RAG_Engine $rag_engine, LLM_Client $llm_client, Unified_Cache_Manager $cache_manager) {
        $this->rag_engine = $rag_engine;
        $this->llm_client = $llm_client;
        $this->cache_manager = $cache_manager;
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_action('ai_botkit_hourly_health_check', [$this, 'run_scheduled_check']);
        add_action('admin_init', [$this, 'maybe_run_check']);
    }

    /**
     * Run health checks if needed
     */
    public function maybe_run_check(): void {
        if (!$this->cache_manager->has(self::CACHE_KEY)) {
            // $this->run_health_check();
        }
    }

    /**
     * Run scheduled health check
     */
    public function run_scheduled_check(): void {
        $this->run_health_check();
    }

    /**
     * Run comprehensive health check
     */
    public function run_health_check(): array {
        $status = [
            'last_check' => current_time('mysql'),
            'status' => 'healthy',
            'components' => [
                'api' => $this->check_api_connectivity(),
                'database' => $this->check_database_health(),
                // 'filesystem' => $this->check_filesystem_health(),
                'resources' => $this->check_resource_availability(),
                'cache' => $this->check_cache_health(),
                'queue' => $this->check_queue_health()
            ]
        ];

        // Determine overall status
        foreach ($status['components'] as $component) {
            if ($component['status'] === 'critical') {
                $status['status'] = 'critical';
                break;
            } elseif ($component['status'] === 'warning' && $status['status'] !== 'critical') {
                $status['status'] = 'warning';
            }
        }

        // Cache the results
        $this->cache_manager->set(self::CACHE_KEY, $status, 'default', self::CACHE_DURATION);

        return $status;
    }

    /**
     * Check API connectivity
     */
    private function check_api_connectivity(): array {
        try {
            // Test API connection with minimal token usage
            $this->llm_client->generate_embeddings(['test']);
            
            return [
                'status' => 'healthy',
                'message' => __('API connection is working properly', 'knowvault')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => sprintf(
                    __('API connection error: %s', 'knowvault'),
                    $e->getMessage()
                )
            ];
        }
    }

    /**
     * Check database health
     */
    private function check_database_health(): array {
        global $wpdb;

        $status = [
            'status' => 'healthy',
            'message' => __('Database is working properly', 'knowvault')
        ];

        // Check required tables
        $required_tables = [
            $wpdb->prefix . 'ai_botkit_documents',
            $wpdb->prefix . 'ai_botkit_chunks',
            $wpdb->prefix . 'ai_botkit_embeddings'
        ];

        foreach ($required_tables as $table) {
            if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
                $status = [
                    'status' => 'critical',
                    'message' => sprintf(
                        __('Required table %s is missing', 'knowvault'),
                        $table
                    )
                ];
                break;
            }
        }

        return $status;
    }

    /**
     * Check filesystem health
     */
    // private function check_filesystem_health(): array {
    //     $upload_dir = wp_upload_dir();
    //     $ai_botkit_dir = $upload_dir['basedir'] . '/ai-botkit';

    //     if (!file_exists($ai_botkit_dir)) {
    //         return [
    //             'status' => 'warning',
    //             'message' => __('Upload directory does not exist', 'knowvault')
    //         ];
    //     }

    //     if (!is_writable($ai_botkit_dir)) {
    //         return [
    //             'status' => 'critical',
    //             'message' => __('Upload directory is not writable', 'knowvault')
    //         ];
    //     }

    //     return [
    //         'status' => 'healthy',
    //         'message' => __('Filesystem is working properly', 'knowvault')
    //     ];
    // }

    /**
     * Check resource availability
     */
    private function check_resource_availability(): array {
        $status = [
            'status' => 'healthy',
            'message' => __('Resource usage is within limits', 'knowvault')
        ];

        // Check memory usage
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        if ( $memory_limit <= 0 ) {
            return $status;
        }
        $memory_percent = ($memory_usage / $memory_limit) * 100;

        if ($memory_percent > 90) {
            $status = [
                'status' => 'critical',
                'message' => __('Memory usage is critically high', 'knowvault')
            ];
        } elseif ($memory_percent > 75) {
            $status = [
                'status' => 'warning',
                'message' => __('Memory usage is high', 'knowvault')
            ];
        }

        return $status;
    }

    /**
     * Check cache health
     */
    private function check_cache_health(): array {
        $stats = $this->cache_manager->get_stats();
        $expired = $stats['expired'] ?? 0;
        $total = $stats['total'] ?? 0;
        
        if ($total > 0 && $expired > ($total * 0.5)) {
            return [
                'status' => 'warning',
                'message' => __('High number of expired cache entries', 'knowvault')
            ];
        }

        return [
            'status' => 'healthy',
            'message' => __('Cache system is working properly', 'knowvault')
        ];
    }

    /**
     * Check processing queue health
     */
    private function check_queue_health(): array {
        global $wpdb;

        // Check for stuck processing items
        $stuck_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents 
            WHERE status = 'processing' 
            AND updated_at < %s",
            gmdate('Y-m-d H:i:s', strtotime('-1 hour'))
        ));

        if ($stuck_items > 0) {
            return [
                'status' => 'warning',
                'message' => sprintf(
                    __('%d items stuck in processing', 'knowvault'),
                    $stuck_items
                )
            ];
        }

        return [
            'status' => 'healthy',
            'message' => __('Processing queue is working properly', 'knowvault')
        ];
    }

    /**
     * Get current health status
     */
    public function get_health_status(): array {
        $status = $this->cache_manager->get(self::CACHE_KEY);

        if ($status === false) {
            $status = $this->run_health_check();
        }

        return $status;
    }

    /**
     * Clear health status cache
     */
    public function clear_status_cache(): void {
        $this->cache_manager->delete(self::CACHE_KEY);
    }
}

/**
 * Exception class for health check errors
 */
class Health_Check_Exception extends \Exception {} 