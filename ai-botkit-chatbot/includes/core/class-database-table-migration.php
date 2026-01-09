<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Table_Helper;

/**
 * Database Table Migration Class
 * 
 * Handles migration from old ai_botkit_ tables to new knowvault_ tables
 */
class Database_Table_Migration {
    
    /**
     * Migrate all data from old tables to new tables
     * 
     * @return array Migration result
     */
    public static function migrate_tables(): array {
        global $wpdb;
        
        $errors = [];
        $tables_migrated = 0;
        
        try {
            // Check if old tables exist
            if (!Table_Helper::check_old_tables_exist()) {
                return [
                    'success' => false,
                    'message' => __('Old tables do not exist. Nothing to migrate.', 'knowvault'),
                    'tables_migrated' => 0,
                    'errors' => []
                ];
            }
            
            // Ensure new tables exist
            self::ensure_new_tables_exist();
            
            // List of tables to migrate
            $tables = [
                'documents',
                'document_metadata',
                'chunks',
                'embeddings',
                'conversations',
                'messages',
                'analytics',
                'content_relationships',
                'chatbots'
            ];
            
            foreach ($tables as $table) {
                $result = self::migrate_table($table);
                if ($result['success']) {
                    $tables_migrated++;
                } else {
                    $errors[] = sprintf(
                        __('Failed to migrate %s: %s', 'knowvault'),
                        $table,
                        $result['message']
                    );
                }
            }
            
            // Mark migration as completed
            if (empty($errors)) {
                update_option('knowvault_db_migration_completed', true);
                update_option('knowvault_db_migration_date', current_time('mysql'));
                
                return [
                    'success' => true,
                    'message' => __('Database migration completed successfully.', 'knowvault'),
                    'tables_migrated' => $tables_migrated,
                    'errors' => []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => __('Migration completed with some errors.', 'knowvault'),
                    'tables_migrated' => $tables_migrated,
                    'errors' => $errors
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'tables_migrated' => $tables_migrated,
                'errors' => array_merge($errors, [$e->getMessage()])
            ];
        }
    }
    
    /**
     * Migrate a single table
     * 
     * @param string $table_name Table name without prefix
     * @return array Migration result
     */
    private static function migrate_table(string $table_name): array {
        global $wpdb;
        
        $old_table = Table_Helper::get_old_table_name($table_name);
        $new_table = Table_Helper::get_new_table_name($table_name);
        
        // Check if old table exists
        $old_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $old_table
        ));
        
        if (!$old_exists) {
            return [
                'success' => true,
                'message' => __('Old table does not exist, skipping.', 'knowvault')
            ];
        }
        
        // Check if new table exists
        $new_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $new_table
        ));
        
        if (!$new_exists) {
            return [
                'success' => false,
                'message' => __('New table does not exist.', 'knowvault')
            ];
        }
        
        // Get row count from old table
        $old_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$old_table}");
        
        if ($old_count === 0) {
            return [
                'success' => true,
                'message' => __('Old table is empty, nothing to migrate.', 'knowvault')
            ];
        }
        
        // Check if new table already has data
        $new_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_table}");
        
        if ($new_count > 0) {
            return [
                'success' => false,
                'message' => __('New table already contains data. Migration may have already been completed.', 'knowvault')
            ];
        }
        
        // Migrate data
        $result = $wpdb->query(
            "INSERT INTO {$new_table} SELECT * FROM {$old_table}"
        );
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => $wpdb->last_error ?: __('Unknown error during migration.', 'knowvault')
            ];
        }
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Migrated %d rows successfully.', 'knowvault'),
                $result
            )
        ];
    }
    
    /**
     * Ensure new tables exist
     */
    private static function ensure_new_tables_exist(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create all new tables (same structure as old ones)
        $tables_sql = self::get_tables_sql($charset_collate);
        
        foreach ($tables_sql as $sql) {
            dbDelta($sql);
        }
    }
    
    /**
     * Get SQL for creating new tables
     * 
     * @param string $charset_collate Charset and collation
     * @return array Array of SQL statements
     */
    private static function get_tables_sql(string $charset_collate): array {
        global $wpdb;
        $prefix = $wpdb->prefix . 'knowvault_';
        
        return [
            // Documents table
            "CREATE TABLE IF NOT EXISTS {$prefix}documents (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                source_type VARCHAR(50) NOT NULL,
                source_id BIGINT(20) NULL,
                file_path VARCHAR(255) NULL,
                mime_type VARCHAR(100) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY source_type_id (source_type, source_id),
                KEY status (status)
            ) $charset_collate;",
            
            // Document Metadata table
            "CREATE TABLE IF NOT EXISTS {$prefix}document_metadata (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id BIGINT(20) UNSIGNED NOT NULL,
                meta_key VARCHAR(255) NOT NULL,
                meta_value LONGTEXT,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY document_meta (document_id, meta_key),
                KEY document_id (document_id),
                KEY meta_key (meta_key)
            ) $charset_collate;",
            
            // Chunks table
            "CREATE TABLE IF NOT EXISTS {$prefix}chunks (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id BIGINT(20) UNSIGNED NOT NULL,
                content TEXT NOT NULL,
                chunk_index INT NOT NULL,
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY document_id (document_id),
                FULLTEXT KEY content (content)
            ) $charset_collate;",
            
            // Embeddings table
            "CREATE TABLE IF NOT EXISTS {$prefix}embeddings (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                chunk_id BIGINT(20) UNSIGNED NOT NULL,
                embedding LONGBLOB NOT NULL,
                model VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY chunk_model (chunk_id, model)
            ) $charset_collate;",
            
            // Conversations table
            "CREATE TABLE IF NOT EXISTS {$prefix}conversations (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                chatbot_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED NULL,
                session_id VARCHAR(100) NOT NULL,
                guest_ip VARCHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY session_id (session_id),
                KEY guest_ip (guest_ip)
            ) $charset_collate;",
            
            // Messages table
            "CREATE TABLE IF NOT EXISTS {$prefix}messages (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id)
            ) $charset_collate;",
            
            // Analytics table
            "CREATE TABLE IF NOT EXISTS {$prefix}analytics (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                chatbot_id BIGINT(20) UNSIGNED NULL,
                event_type VARCHAR(50) NOT NULL,
                event_data JSON NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY chatbot_id (chatbot_id)
            ) $charset_collate;",
            
            // Content relationships table
            "CREATE TABLE IF NOT EXISTS {$prefix}content_relationships (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                source_type VARCHAR(50) NOT NULL,
                source_id BIGINT(20) UNSIGNED NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                target_id BIGINT(20) UNSIGNED NOT NULL,
                relationship_type VARCHAR(50) NOT NULL,
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY source (source_type, source_id),
                KEY target (target_type, target_id),
                KEY relationship_type (relationship_type)
            ) $charset_collate;",
            
            // Chatbots table
            "CREATE TABLE IF NOT EXISTS {$prefix}chatbots (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                active tinyint(1) NOT NULL DEFAULT 0,
                avatar int(11) NOT NULL DEFAULT 0,
                feedback tinyint(1) NOT NULL DEFAULT 0,
                style JSON DEFAULT NULL,
                messages_template JSON DEFAULT NULL,
                model_config JSON DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;"
        ];
    }
}

