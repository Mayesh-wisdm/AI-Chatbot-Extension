<?php
namespace AI_BotKit\Core;

/**
 * Database Migration Class
 * 
 * Handles database migrations for the AI BotKit plugin, including
 * index creation and schema updates.
 */
class Database_Migration {
    
    /**
     * Database prefix
     */
    private $table_prefix;
    
    /**
     * Migration version
     */
    private $version = '1.0.0';
    
    /**
     * Initialize the database migration
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_botkit_';
    }
    
    /**
     * Run database migrations
     * 
     * @return array Migration result
     */
    public function run_migrations() {
        $migrations_run = 0;
        $errors = [];
        
        try {
            // Run index creation migration
            $result = $this->create_performance_indexes();
            if ($result['success']) {
                $migrations_run++;
            } else {
                $errors = array_merge($errors, $result['errors']);
            }
            
            // Update migration version
            update_option('ai_botkit_db_version', $this->version);
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'migrations_run' => $migrations_run,
            'errors' => $errors,
            'version' => $this->version
        ];
    }
    
    /**
     * Create performance indexes
     * 
     * @return array Index creation result
     */
    private function create_performance_indexes() {
        global $wpdb;
        
        $indexes_created = 0;
        $errors = [];
        
        try {
            // Documents table indexes
            $indexes_created += $this->create_documents_indexes();
            
            // Chatbots table indexes
            $indexes_created += $this->create_chatbots_indexes();
            
            // Chunks table indexes
            $indexes_created += $this->create_chunks_indexes();
            
            // Embeddings table indexes
            $indexes_created += $this->create_embeddings_indexes();
            
            // Content relationships table indexes
            $indexes_created += $this->create_content_relationships_indexes();
            
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
     * Create documents table indexes
     * 
     * @return int Number of indexes created
     */
    private function create_documents_indexes() {
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
            if ($this->create_index_safely($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create chatbots table indexes
     * 
     * @return int Number of indexes created
     */
    private function create_chatbots_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'chatbots';
        $indexes_created = 0;
        
        $indexes = [
            'idx_active' => 'active',
            'idx_created_at' => 'created_at'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_safely($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create chunks table indexes
     * 
     * @return int Number of indexes created
     */
    private function create_chunks_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'chunks';
        $indexes_created = 0;
        
        $indexes = [
            'idx_document_id' => 'document_id',
            'idx_chunk_index' => 'chunk_index'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_safely($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create embeddings table indexes
     * 
     * @return int Number of indexes created
     */
    private function create_embeddings_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'embeddings';
        $indexes_created = 0;
        
        $indexes = [
            'idx_chunk_id' => 'chunk_id',
            'idx_model' => 'model'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_safely($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create content relationships table indexes
     * 
     * @return int Number of indexes created
     */
    private function create_content_relationships_indexes() {
        global $wpdb;
        
        $table_name = $this->table_prefix . 'content_relationships';
        $indexes_created = 0;
        
        $indexes = [
            'idx_source_type_id' => 'source_type, source_id',
            'idx_target_type_id' => 'target_type, target_id',
            'idx_relationship_type' => 'relationship_type'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            if ($this->create_index_safely($table_name, $index_name, $columns)) {
                $indexes_created++;
            }
        }
        
        return $indexes_created;
    }
    
    /**
     * Create index safely (check if exists first)
     * 
     * @param string $table_name Table name
     * @param string $index_name Index name
     * @param string $columns Column(s) to index
     * @return bool True if index was created, false if it already exists
     */
    private function create_index_safely($table_name, $index_name, $columns) {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return false; // Table doesn't exist
        }
        
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
        
        if ($result === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if migration is needed
     * 
     * @return bool True if migration is needed
     */
    public function is_migration_needed() {
        $current_version = get_option('ai_botkit_db_version', '0.0.0');
        return version_compare($current_version, $this->version, '<');
    }
    
    /**
     * Get current database version
     * 
     * @return string Current version
     */
    public function get_current_version() {
        return get_option('ai_botkit_db_version', '0.0.0');
    }
    
    /**
     * Get migration status
     * 
     * @return array Migration status
     */
    public function get_migration_status() {
        return [
            'current_version' => $this->get_current_version(),
            'target_version' => $this->version,
            'migration_needed' => $this->is_migration_needed(),
            'indexes_status' => $this->get_indexes_status()
        ];
    }
    
    /**
     * Get indexes status
     * 
     * @return array Indexes status
     */
    private function get_indexes_status() {
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
}
