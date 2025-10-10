<?php
namespace AI_BotKit\Core;

/**
 * Database Optimizer Class
 * 
 * Handles database optimization including index creation, query optimization,
 * and performance improvements for the AI BotKit plugin.
 */
class Database_Optimizer {
    
    /**
     * Database prefix
     */
    private $table_prefix;
    
    /**
     * Initialize the database optimizer
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_botkit_';
    }
    
    /**
     * Create database indexes for performance optimization
     * 
     * @return array Result of index creation
     */
    public function create_database_indexes() {
        global $wpdb;
        
        $indexes_created = 0;
        $errors = [];
        
        try {
            // Documents table indexes
            $indexes_created += $this->create_documents_table_indexes();
            
            // Chatbots table indexes
            $indexes_created += $this->create_chatbots_table_indexes();
            
            // Chunks table indexes
            $indexes_created += $this->create_chunks_table_indexes();
            
            // Embeddings table indexes
            $indexes_created += $this->create_embeddings_table_indexes();
            
            // Content relationships table indexes
            $indexes_created += $this->create_content_relationships_table_indexes();
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'indexes_created' => $indexes_created,
            'errors' => $errors
        ];
    }
    
    /**
     * Create indexes for documents table
     * 
     * @return int Number of indexes created
     */
    private function create_documents_table_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'documents';
        $indexes_created = 0;
        
        $indexes = [
            'idx_source_type' => 'source_type',
            'idx_created_at' => 'created_at',
            'idx_source_type_created' => 'source_type, created_at',
            'idx_source_id' => 'source_id'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_if_not_exists($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create indexes for chatbots table
     * 
     * @return int Number of indexes created
     */
    private function create_chatbots_table_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'chatbots';
        $indexes_created = 0;
        
        $indexes = [
            'idx_active' => 'active',
            'idx_created_at' => 'created_at'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_if_not_exists($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create indexes for chunks table
     * 
     * @return int Number of indexes created
     */
    private function create_chunks_table_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'chunks';
        $indexes_created = 0;
        
        $indexes = [
            'idx_document_id' => 'document_id',
            'idx_chunk_index' => 'chunk_index'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_if_not_exists($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create indexes for embeddings table
     * 
     * @return int Number of indexes created
     */
    private function create_embeddings_table_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'embeddings';
        $indexes_created = 0;
        
        $indexes = [
            'idx_chunk_id' => 'chunk_id',
            'idx_model' => 'model'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_if_not_exists($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create indexes for content relationships table
     * 
     * @return int Number of indexes created
     */
    private function create_content_relationships_table_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'content_relationships';
        $indexes_created = 0;
        
        $indexes = [
            'idx_source_type_id' => 'source_type, source_id',
            'idx_target_type_id' => 'target_type, target_id',
            'idx_relationship_type' => 'relationship_type'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_if_not_exists($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create index if it doesn't exist
     * 
     * @param string $table_name Table name
     * @param string $index_name Index name
     * @param string $columns Column(s) to index
     * @return bool True if index was created, false if it already exists
     */
    private function create_index_if_not_exists($table_name, $index_name, $columns) {
        global $wpdb;
        
        // Check if index already exists
        $existing_index = $wpdb->get_row($wpdb->prepare(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
            $index_name
        ));
        
        if ($existing_index) {
            return false; // Index already exists
        }
        
        // Create the index
        $sql = "CREATE INDEX {$index_name} ON {$table_name} ({$columns})";
        $result = $wpdb->query($sql);
        
        return $result !== false;
    }
    
    /**
     * Get optimized post type count
     * 
     * @param string $post_type Post type name
     * @return int Post count
     */
    public function get_post_type_count($post_type) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));
    }
    
    /**
     * Get batch post type counts in a single query
     * 
     * @param array $post_types Array of post type names
     * @return array Array of post type counts
     */
    public function get_batch_post_type_counts($post_types) {
        global $wpdb;
        
        if (empty($post_types)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_type, COUNT(*) as count 
             FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_type IN ($placeholders)
             GROUP BY post_type",
            ...$post_types
        ), ARRAY_A);
        
        $result = [];
        foreach ($counts as $count) {
            $result[$count['post_type']] = (int) $count['count'];
        }
        
        return $result;
    }
    
    /**
     * Get optimized document statistics
     * 
     * @return array Document statistics
     */
    public function get_document_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_documents,
                COUNT(DISTINCT source_type) as content_types,
                SUM(CASE WHEN source_type = 'post' THEN 1 ELSE 0 END) as posts,
                SUM(CASE WHEN source_type = 'file' THEN 1 ELSE 0 END) as files,
                SUM(CASE WHEN source_type = 'url' THEN 1 ELSE 0 END) as urls
            FROM {$this->table_prefix}documents",
            ARRAY_A
        );
        
        return $stats ?: [
            'total_documents' => 0,
            'content_types' => 0,
            'posts' => 0,
            'files' => 0,
            'urls' => 0
        ];
    }
    
    /**
     * Get optimized chatbot statistics
     * 
     * @return array Chatbot statistics
     */
    public function get_chatbot_statistics() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_chatbots,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_chatbots,
                SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_chatbots
            FROM {$this->table_prefix}chatbots",
            ARRAY_A
        );
        
        return $stats ?: [
            'total_chatbots' => 0,
            'active_chatbots' => 0,
            'inactive_chatbots' => 0
        ];
    }
    
    /**
     * Check if database indexes exist
     * 
     * @return array Index status
     */
    public function check_index_status() {
        global $wpdb;
        
        $tables = [
            'documents' => ['idx_source_type', 'idx_created_at', 'idx_source_type_created', 'idx_source_id'],
            'chatbots' => ['idx_active', 'idx_created_at'],
            'chunks' => ['idx_document_id', 'idx_chunk_index'],
            'embeddings' => ['idx_chunk_id', 'idx_model'],
            'content_relationships' => ['idx_source_type_id', 'idx_target_type_id', 'idx_relationship_type']
        ];
        
        $status = [];
        
        foreach ($tables as $table => $indexes) {
            $table_name = $this->table_prefix . $table;
            $status[$table] = [];
            
            foreach ($indexes as $index) {
                $exists = $wpdb->get_row($wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    $index
                ));
                
                $status[$table][$index] = $exists !== null;
            }
        }
        
        return $status;
    }
    
    /**
     * Get database performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        global $wpdb;
        
        $metrics = [];
        
        // Test query performance
        $start_time = microtime(true);
        $this->get_document_statistics();
        $end_time = microtime(true);
        
        $metrics['document_stats_query_time'] = $end_time - $start_time;
        
        // Test post type count performance
        $start_time = microtime(true);
        $this->get_post_type_count('post');
        $end_time = microtime(true);
        
        $metrics['post_type_count_query_time'] = $end_time - $start_time;
        
        return $metrics;
    }
}
