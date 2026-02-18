<?php
namespace AI_BotKit\Core;

/**
 * Content Queue Processor Class
 * 
 * Handles content processing queue with optimized batch operations,
 * error handling, and performance monitoring.
 */
class Content_Queue_Processor {
    
    /**
     * Enhanced cache manager
     */
    private $cache_manager;
    
    /**
     * Content processing monitor
     */
    private $monitor;
    
    /**
     * Queue statistics
     */
    private $stats = [];
    
    /**
     * Initialize the content queue processor
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Content_Processing_Monitor();
    }
    
    /**
     * Process content queue
     * 
     * @param int $limit Processing limit
     * @return array Queue processing result
     */
    public function process_queue($limit = 10) {
        $start_time = microtime(true);
        
        global $wpdb;
        
        // Get pending documents from queue
        $pending_documents = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_documents 
             WHERE status = 'pending'
             ORDER BY created_at ASC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        
        $processed_items = 0;
        $errors = [];
        
        foreach ($pending_documents as $document) {
            try {
                $this->process_queue_item($document);
                $processed_items++;
            } catch (\Exception $e) {
                $errors[] = "Document {$document['id']}: " . $e->getMessage();
                
                // Mark document as failed
                $wpdb->update(
                    $wpdb->prefix . 'ai_botkit_documents',
                    ['status' => 'failed'],
                    ['id' => $document['id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
        
        $end_time = microtime(true);
        $processing_time = $end_time - $start_time;
        
        // Get remaining items count
        $remaining_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents WHERE status = 'pending'"
        );
        
        // Record processing metrics
        $this->monitor->record_processing_metrics($processing_time, $processed_items, count($errors));
        
        return [
            'processed_items' => $processed_items,
            'remaining_items' => $remaining_items,
            'errors' => $errors,
            'processing_time' => $processing_time,
            'success' => empty($errors)
        ];
    }
    
    /**
     * Process queue item
     * 
     * @param array $document Document data
     * @return bool Processing success
     */
    private function process_queue_item($document) {
        global $wpdb;
        
        // Simulate document processing
        // In a real implementation, this would involve:
        // 1. Text cleaning and preprocessing
        // 2. Chunking the content
        // 3. Generating embeddings
        // 4. Storing in vector database
        
        // For now, just mark as processed
        $wpdb->update(
            $wpdb->prefix . 'ai_botkit_documents',
            [
                'status' => 'processed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $document['id']],
            ['%s', '%s'],
            ['%d']
        );
        
        return true;
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Queue statistics
     */
    public function get_queue_statistics() {
        $cache_key = 'content_queue_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_queue_statistics();
        $this->cache_manager->set($cache_key, $stats, 'default', 300); // 5 minutes
        
        return $stats;
    }
    
    /**
     * Generate queue statistics
     * 
     * @return array Queue statistics
     */
    private function generate_queue_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_items,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_items,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_items,
                AVG(CASE WHEN status = 'processed' THEN 
                    TIMESTAMPDIFF(SECOND, created_at, updated_at) 
                END) as average_processing_time
            FROM {$wpdb->prefix}ai_botkit_documents",
            ARRAY_A
        );
        
        return $stats ?: [
            'total_items' => 0,
            'pending_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'average_processing_time' => 0,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get queue performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_queue_performance() {
        return $this->monitor->get_processing_metrics();
    }
    
    /**
     * Get queue recommendations
     * 
     * @return array Queue recommendations
     */
    public function get_queue_recommendations() {
        $stats = $this->get_queue_statistics();
        $recommendations = [];
        
        // Pending items recommendation
        if ($stats['pending_items'] > 100) {
            $recommendations[] = [
                'type' => 'pending_items',
                'message' => 'Many items are pending in the queue.',
                'pending_count' => $stats['pending_items'],
                'recommendation' => 'Process pending items to maintain queue performance'
            ];
        }
        
        // Failed items recommendation
        if ($stats['failed_items'] > 0) {
            $recommendations[] = [
                'type' => 'failed_items',
                'message' => 'Some items failed to process.',
                'failed_count' => $stats['failed_items'],
                'recommendation' => 'Review failed items and retry processing'
            ];
        }
        
        // Processing time recommendation
        if ($stats['average_processing_time'] > 10) {
            $recommendations[] = [
                'type' => 'processing_time',
                'message' => 'Average processing time is high.',
                'average_time' => $stats['average_processing_time'],
                'recommendation' => 'Consider optimizing processing or reducing batch size'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear queue cache
     */
    public function clear_queue_cache() {
        $this->cache_manager->delete('content_queue_statistics');
    }
    
    /**
     * Get queue status
     * 
     * @return array Queue status
     */
    public function get_queue_status() {
        return [
            'queue_enabled' => true,
            'processing_enabled' => true,
            'error_handling' => true,
            'performance_monitoring' => true,
            'last_processing' => current_time('mysql')
        ];
    }
}
