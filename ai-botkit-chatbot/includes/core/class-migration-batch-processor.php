<?php
namespace AI_BotKit\Core;

/**
 * Migration Batch Processor Class
 * 
 * Handles migration batch processing with optimized operations,
 * error handling, and performance monitoring.
 */
class Migration_Batch_Processor {
    
    /**
     * Migration performance monitor
     */
    private $monitor;
    
    /**
     * Migration error handler
     */
    private $error_handler;
    
    /**
     * Initialize the migration batch processor
     */
    public function __construct() {
        $this->monitor = new Unified_Performance_Monitor();
        $this->error_handler = new Unified_Error_Handler();
    }
    
    /**
     * Process migration batch
     * 
     * @param array $post_types Post types to process
     * @param int $batch_size Batch size
     * @return array Batch processing result
     */
    public function process_migration_batch($post_types, $batch_size = 10) {
        $start_time = microtime(true);
        $processed_items = 0;
        $errors = [];
        
        try {
            foreach ($post_types as $post_type) {
                $result = $this->process_post_type_batch($post_type, $batch_size);
                $processed_items += $result['processed'];
                $errors = array_merge($errors, $result['errors']);
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        $end_time = microtime(true);
        $processing_time = $end_time - $start_time;
        
        // Record performance metrics
        $this->monitor->record_migration_operation('batch_processing', $processing_time, $processed_items);
        
        return [
            'processed_items' => $processed_items,
            'errors' => $errors,
            'processing_time' => $processing_time,
            'batch_size' => $batch_size,
            'post_types' => $post_types,
            'success' => empty($errors)
        ];
    }
    
    /**
     * Process post type batch
     * 
     * @param string $post_type Post type
     * @param int $batch_size Batch size
     * @return array Processing result
     */
    private function process_post_type_batch($post_type, $batch_size) {
        global $wpdb;
        
        $processed = 0;
        $errors = [];
        
        // Get posts to process
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt, post_date, post_modified
             FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status = 'publish'
             ORDER BY post_date DESC
             LIMIT %d",
            $post_type,
            $batch_size
        ), ARRAY_A);
        
        foreach ($posts as $post) {
            try {
                $this->process_single_post($post);
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Post {$post['ID']}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => $processed,
            'errors' => $errors
        ];
    }
    
    /**
     * Process single post
     * 
     * @param array $post Post data
     * @return bool Processing success
     */
    private function process_single_post($post) {
        global $wpdb;
        
        // Check if post already exists in documents table
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ai_botkit_documents 
             WHERE source_type = %s AND source_id = %d",
            'post',
            $post['ID']
        ));
        
        if ($existing) {
            // Update existing document
            $wpdb->update(
                $wpdb->prefix . 'ai_botkit_documents',
                [
                    'title' => $post['post_title'],
                    'content' => $post['post_content'],
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new document
            $wpdb->insert(
                $wpdb->prefix . 'ai_botkit_documents',
                [
                    'title' => $post['post_title'],
                    'source_type' => 'post',
                    'source_id' => $post['ID'],
                    'content' => $post['post_content'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        }
        
        return true;
    }
    
    /**
     * Get batch processing statistics
     * 
     * @return array Batch processing statistics
     */
    public function get_batch_processing_statistics() {
        $metrics = $this->monitor->get_performance_metrics();
        
        return [
            'total_batches_processed' => $metrics['total_operations'],
            'total_items_processed' => $metrics['total_items_processed'],
            'average_batch_time' => $metrics['average_operation_time'],
            'average_items_per_batch' => $metrics['average_items_per_operation'],
            'last_updated' => $metrics['last_updated']
        ];
    }
    
    /**
     * Get batch processing recommendations
     * 
     * @return array Batch processing recommendations
     */
    public function get_batch_processing_recommendations() {
        $stats = $this->get_batch_processing_statistics();
        $recommendations = [];
        
        // Batch size recommendations
        if ($stats['average_batch_time'] > 2.0) {
            $recommendations[] = [
                'type' => 'batch_size',
                'message' => 'Batch processing is slow. Consider reducing batch size.',
                'average_time' => $stats['average_batch_time'],
                'recommendation' => 'Reduce batch size to improve performance'
            ];
        }
        
        // Processing efficiency recommendations
        if ($stats['average_items_per_batch'] < 5) {
            $recommendations[] = [
                'type' => 'processing_efficiency',
                'message' => 'Low processing efficiency. Consider optimizing batch processing.',
                'average_items' => $stats['average_items_per_batch'],
                'recommendation' => 'Optimize batch processing logic'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get batch processing status
     * 
     * @return array Batch processing status
     */
    public function get_batch_processing_status() {
        return [
            'batch_processing_enabled' => true,
            'performance_monitoring' => true,
            'error_handling' => true,
            'last_processing' => current_time('mysql')
        ];
    }
}
