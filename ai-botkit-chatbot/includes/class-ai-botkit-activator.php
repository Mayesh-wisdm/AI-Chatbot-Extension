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

        $is_first_install = get_option( 'ai_botkit_setup_completed' );
		if ( ! $is_first_install ) {
			set_transient( '_ai_botkit_activation_redirect', 1, 30 );
		}

        // Create database tables
        self::create_tables();
        
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
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create necessary database tables
     */
    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // Documents table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_documents (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_document_metadata (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_chunks (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_embeddings (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_conversations (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_messages (
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
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_analytics (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type)
        ) $charset_collate;";
        dbDelta($sql);


        // Content relationships table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_content_relationships (
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
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';
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
    }
    
    /**
     * Add guest_ip column to conversations table for existing installations
     */
    public static function add_guest_ip_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';
        
        // Check if the column already exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'guest_ip'");
        
        // If column doesn't exist, add it
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN guest_ip VARCHAR(64) NULL, ADD INDEX guest_ip (guest_ip)");
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
                error_log('AI BotKit Activator Error: Failed to create directory - ' . $ai_botkit_dir);
                return;
            }
        }
        
        // Create documents directory
        $documents_dir = $ai_botkit_dir . '/documents';
        if (!file_exists($documents_dir)) {
            $result = wp_mkdir_p($documents_dir);
            if (!$result) {
                error_log('AI BotKit Activator Error: Failed to create documents directory - ' . $documents_dir);
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
                error_log('AI BotKit Activator Error: Failed to create .htaccess file - ' . $htaccess_file);
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