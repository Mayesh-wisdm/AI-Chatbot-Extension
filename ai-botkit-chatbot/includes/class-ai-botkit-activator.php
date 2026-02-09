<?php
namespace AI_BotKit\Core;

/**
 * Fired during plugin activation
 */
class Activator {
    /**
     * Activate the plugin
     */
    public static function activate() {
        global $wpdb;

        // Load Table_Helper before using it
        require_once AI_BOTKIT_INCLUDES_DIR . 'utils/class-table-helper.php';

        $is_first_install = get_option( 'ai_botkit_setup_completed' );
		if ( ! $is_first_install ) {
			set_transient( '_ai_botkit_activation_redirect', 1, 30 );
		}

        // Check if old tables exist
        $old_tables_exist = \AI_BotKit\Utils\Table_Helper::check_old_tables_exist();
        $migration_completed = get_option('knowvault_db_migration_completed', false);
        
        // Create database tables
        // For new installations, create knowvault_ tables
        // For existing installations with old tables, create old tables for backward compatibility
        // Migration is user-initiated only, not automatic
        if (!$old_tables_exist) {
            // New installation - create new tables
            self::create_new_tables();
        } else {
            // Existing installation - create old tables if they don't exist
            // Don't create new tables automatically - user must initiate migration
            self::create_old_tables();
        }
        
        // Add guest_ip column to conversations table for existing installations
        self::add_guest_ip_column();
        
        // Set default options
        self::set_default_options();
        
        // Create upload directory
        self::create_upload_directory();
        
        // Set plugin version
        update_option('ai_botkit_version', AI_BOTKIT_VERSION);

        // Schedule tasks
        self::schedule_tasks();

        // Run Phase 2 migrations (adds is_favorite, is_archived columns, etc.)
        self::run_phase2_migrations();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Run Phase 2 database migrations
     *
     * Adds new columns and tables for Phase 2 features:
     * - is_favorite and is_archived columns to conversations table
     * - media table for rich media support
     * - templates table for conversation templates
     * - user_interactions table for recommendations
     * - Various performance indexes
     */
    public static function run_phase2_migrations() {
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-phase2-migration.php';

        $migration = new \AI_BotKit\Core\Phase2_Migration();

        if ( $migration->is_migration_needed() ) {
            $result = $migration->run_migrations();

            if ( ! $result['success'] && ! empty( $result['errors'] ) ) {
                // Log migration errors for debugging
                error_log( 'AI BotKit Phase 2 Migration Errors: ' . implode( ', ', $result['errors'] ) );
            }
        }
    }
    
    /**
     * Create new knowvault_ tables
     */
    public static function create_new_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'knowvault_';

        // Documents table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}documents (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Document Metadata table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}document_metadata (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Chunks table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}chunks (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT(20) UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            chunk_index INT NOT NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY document_id (document_id),
            FULLTEXT KEY content (content)
        ) $charset_collate;";
        dbDelta($sql);

        // Embeddings table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}embeddings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chunk_id BIGINT(20) UNSIGNED NOT NULL,
            embedding LONGBLOB NOT NULL,
            model VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY chunk_model (chunk_id, model)
        ) $charset_collate;";
        dbDelta($sql);

        // Conversations table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}conversations (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Messages table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Analytics table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}analytics (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chatbot_id BIGINT(20) UNSIGNED NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY chatbot_id (chatbot_id)
        ) $charset_collate;";
        dbDelta($sql);


        // Content relationships table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}content_relationships (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Create chatbots table
        $table_name = $prefix . 'chatbots';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
        ) $charset_collate;";
        dbDelta($sql);

        // WordPress Content queue table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}wp_content (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            action VARCHAR(20) NOT NULL DEFAULT 'create',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_type_id (post_id, post_type),
            KEY status (status),
            KEY post_type (post_type)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create old ai_botkit_ tables for backward compatibility
     */
    public static function create_old_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'ai_botkit_';

        // Documents table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}documents (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Document Metadata table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}document_metadata (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Chunks table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}chunks (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT(20) UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            chunk_index INT NOT NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY document_id (document_id),
            FULLTEXT KEY content (content)
        ) $charset_collate;";
        dbDelta($sql);

        // Embeddings table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}embeddings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chunk_id BIGINT(20) UNSIGNED NOT NULL,
            embedding LONGBLOB NOT NULL,
            model VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY chunk_model (chunk_id, model)
        ) $charset_collate;";
        dbDelta($sql);

        // Conversations table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}conversations (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Messages table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            metadata JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Analytics table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}analytics (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chatbot_id BIGINT(20) UNSIGNED NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY chatbot_id (chatbot_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Content relationships table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}content_relationships (
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
        ) $charset_collate;";
        dbDelta($sql);

        // Create chatbots table
        $table_name = $prefix . 'chatbots';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
        ) $charset_collate;";
        dbDelta($sql);

        // WordPress Content queue table
        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}wp_content (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            action VARCHAR(20) NOT NULL DEFAULT 'create',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_type_id (post_id, post_type),
            KEY status (status),
            KEY post_type (post_type)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Add guest_ip column to conversations table for existing installations
     */
    public static function add_guest_ip_column() {
        global $wpdb;
        
        // Check both old and new tables
        $old_table = $wpdb->prefix . 'ai_botkit_conversations';
        $new_table = $wpdb->prefix . 'knowvault_conversations';
        
        // Check and update old table
        $old_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table));
        if ($old_exists) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$old_table} LIKE 'guest_ip'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$old_table} ADD COLUMN guest_ip VARCHAR(64) NULL, ADD INDEX guest_ip (guest_ip)");
            }
        }
        
        // Check and update new table
        $new_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $new_table));
        if ($new_exists) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$new_table} LIKE 'guest_ip'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$new_table} ADD COLUMN guest_ip VARCHAR(64) NULL, ADD INDEX guest_ip (guest_ip)");
            }
        }
    }
    
    /**
     * Set default plugin options
     */
    public static function set_default_options() {
        $defaults = [
            // LLM Settings
            'ai_botkit_chat_model' => 'gpt-4-turbo-preview',
            'ai_botkit_embedding_model' => 'text-embedding-3-small',
            'ai_botkit_max_tokens' => 1000,
            'ai_botkit_temperature' => 0.7,
            
            // Processing Settings
            'ai_botkit_chunk_size' => 1000,
            'ai_botkit_chunk_overlap' => 200,
            'ai_botkit_batch_size' => 20,
            
            // Cache Settings
            'ai_botkit_cache_ttl' => 3600,
            
            // Rate Limiting
            'ai_botkit_max_requests_per_day' => 60,
            
            // WooCommerce Integration
            'ai_botkit_wc_product_sync' => true,
            'ai_botkit_wc_order_sync' => true,
            'ai_botkit_wc_customer_sync' => true,
            
            // Analytics Settings
            'ai_botkit_analytics_retention' => 90,
            'ai_botkit_performance_monitoring' => true,
            
            // Monitoring Settings
            'ai_botkit_health_check_interval' => 'daily',
            'ai_botkit_backup_retention' => 30,
            
            // Content Processing
            'ai_botkit_post_types' => ['post', 'page'],
            'ai_botkit_log_level' => 'info'
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
    
    /**
     * Create upload directory for documents
     */
    public static function create_upload_directory() {
        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();
        
        // Create AI BotKit directory
        $ai_botkit_dir = $upload_dir['basedir'] . '/ai-botkit';
        if (!file_exists($ai_botkit_dir)) {
            $result = wp_mkdir_p($ai_botkit_dir);
            if (!$result) {
                return;
            }
        }
        
        // Create documents directory
        $documents_dir = $ai_botkit_dir . '/documents';
        if (!file_exists($documents_dir)) {
            $result = wp_mkdir_p($documents_dir);
            if (!$result) {
                return;
            }
        }
        
        // Create .htaccess file to protect sensitive data
        $htaccess_file = $ai_botkit_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Deny access to all files\n";
            $htaccess_content .= "Order Allow,Deny\n";
            $htaccess_content .= "Deny from all\n";
            
            $result = file_put_contents($htaccess_file, $htaccess_content);
            if ($result === false) {
            }
        }
    }
    
    /**
     * Schedule plugin tasks
     */
    public static function schedule_tasks() {
        if (!wp_next_scheduled('ai_botkit_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'ai_botkit_daily_maintenance');
        }
    }
} 