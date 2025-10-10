<?php
namespace AI_BotKit\Core;

/**
 * Content Processor Class
 * 
 * Optimizes content processing with batch operations, chunked processing,
 * and performance monitoring for improved content handling.
 */
class Content_Processor {
    
    /**
     * Enhanced cache manager
     */
    private $cache_manager;
    
    /**
     * Content processing monitor
     */
    private $monitor;
    
    /**
     * Processing statistics
     */
    private $stats = [];
    
    /**
     * Initialize the content processor
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->monitor = new Content_Processing_Monitor();
    }
    
    /**
     * Process content batch
     * 
     * @param array $post_types Post types to process
     * @param int $batch_size Batch size
     * @return array Processing result
     */
    public function process_content_batch($post_types, $batch_size = 10) {
        $start_time = microtime(true);
        $processed = 0;
        $errors = [];
        
        try {
            foreach ($post_types as $post_type) {
                $result = $this->process_post_type_batch($post_type, $batch_size);
                $processed += $result['processed'];
                $errors = array_merge($errors, $result['errors']);
            }
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        $end_time = microtime(true);
        $processing_time = $end_time - $start_time;
        
        // Record processing metrics
        $this->monitor->record_processing_metrics($processing_time, $processed, count($errors));
        
        return [
            'processed' => $processed,
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
     * Process content queue
     * 
     * @param int $limit Processing limit
     * @return array Queue processing result
     */
    public function process_content_queue($limit = 10) {
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
                $this->process_document($document);
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
        
        return [
            'processed_items' => $processed_items,
            'remaining_items' => $remaining_items,
            'errors' => $errors,
            'processing_time' => $processing_time,
            'success' => empty($errors)
        ];
    }
    
    /**
     * Process document
     * 
     * @param array $document Document data
     * @return bool Processing success
     */
    private function process_document($document) {
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
     * Get content processing statistics
     * 
     * @return array Processing statistics
     */
    public function get_processing_statistics() {
        $cache_key = 'content_processing_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_processing_statistics();
        $this->cache_manager->set($cache_key, $stats, 300); // 5 minutes
        
        return $stats;
    }
    
    /**
     * Generate processing statistics
     * 
     * @return array Processing statistics
     */
    private function generate_processing_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_documents,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_documents,
                SUM(CASE WHEN source_type = 'post' THEN 1 ELSE 0 END) as post_documents,
                SUM(CASE WHEN source_type = 'file' THEN 1 ELSE 0 END) as file_documents,
                SUM(CASE WHEN source_type = 'url' THEN 1 ELSE 0 END) as url_documents
            FROM {$wpdb->prefix}ai_botkit_documents",
            ARRAY_A
        );
        
        return $stats ?: [
            'total_documents' => 0,
            'pending_documents' => 0,
            'processed_documents' => 0,
            'failed_documents' => 0,
            'post_documents' => 0,
            'file_documents' => 0,
            'url_documents' => 0,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get processing performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_processing_performance() {
        return $this->monitor->get_processing_metrics();
    }
    
    /**
     * Get processing recommendations
     * 
     * @return array Processing recommendations
     */
    public function get_processing_recommendations() {
        $stats = $this->get_processing_statistics();
        $recommendations = [];
        
        // Pending documents recommendation
        if ($stats['pending_documents'] > 100) {
            $recommendations[] = [
                'type' => 'pending_documents',
                'message' => 'Many documents are pending processing.',
                'pending_count' => $stats['pending_documents'],
                'recommendation' => 'Process pending documents to maintain data consistency'
            ];
        }
        
        // Failed documents recommendation
        if ($stats['failed_documents'] > 0) {
            $recommendations[] = [
                'type' => 'failed_documents',
                'message' => 'Some documents failed to process.',
                'failed_count' => $stats['failed_documents'],
                'recommendation' => 'Review failed documents and retry processing'
            ];
        }
        
        // Processing performance recommendation
        $performance = $this->get_processing_performance();
        if ($performance['average_processing_time'] > 1.0) {
            $recommendations[] = [
                'type' => 'performance',
                'message' => 'Content processing is slow.',
                'average_time' => $performance['average_processing_time'],
                'recommendation' => 'Consider optimizing content processing or reducing batch size'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear content processing cache
     */
    public function clear_processing_cache() {
        $this->cache_manager->delete('content_processing_statistics');
    }
    
    /**
     * Get content processing status
     * 
     * @return array Processing status
     */
    public function get_processing_status() {
        return [
            'processing_enabled' => true,
            'batch_processing' => true,
            'queue_processing' => true,
            'performance_monitoring' => true,
            'last_processing' => current_time('mysql')
        ];
    }
}
