<?php
/**
 * Phase 2 Database Migration
 *
 * Handles database schema changes for Phase 2 features including
 * Chat History (is_favorite, is_archived columns), Search Functionality
 * (FULLTEXT index on messages.content), and Rich Media Support
 * (ai_botkit_media table).
 *
 * @package AI_BotKit\Core
 * @since   2.0.0
 *
 * Implements: FR-201 to FR-209 (Chat History Feature)
 * Implements: FR-210 to FR-219 (Search Functionality Feature)
 * Implements: FR-220 to FR-229 (Rich Media Support)
 * Implements: FR-230 to FR-239 (Conversation Templates)
 * Implements: FR-250 to FR-259 (LMS/WooCommerce Suggestions)
 */

namespace AI_BotKit\Core;

/**
 * Phase2_Migration class.
 *
 * Manages Phase 2 database migrations including schema updates
 * and index creation for new features.
 *
 * @since 2.0.0
 */
class Phase2_Migration {

    /**
     * Database prefix.
     *
     * @var string
     */
    private string $table_prefix;

    /**
     * Migration version for Phase 2.
     *
     * @var string
     */
    private string $version = '2.0.0';

    /**
     * Option key for tracking Phase 2 migration.
     *
     * @var string
     */
    private const MIGRATION_OPTION = 'ai_botkit_phase2_db_version';

    /**
     * Constructor.
     *
     * Initializes the database prefix.
     *
     * @since 2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_botkit_';
    }

    /**
     * Run all Phase 2 migrations.
     *
     * @since 2.0.0
     *
     * @return array {
     *     Migration result.
     *
     *     @type bool   $success         Whether all migrations succeeded.
     *     @type int    $migrations_run  Number of migrations executed.
     *     @type array  $errors          Any error messages.
     *     @type string $version         Migration version.
     * }
     */
    public function run_migrations(): array {
        $migrations_run = 0;
        $errors         = array();

        try {
            // Migration 1: Add is_favorite column to conversations.
            $result = $this->add_favorite_column();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 2: Add is_archived column to conversations.
            $result = $this->add_archived_column();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 3: Create performance indexes for chat history.
            $result = $this->create_chat_history_indexes();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 4: Create FULLTEXT index for search functionality.
            $result = $this->create_fulltext_index();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 5: Create additional indexes for search performance.
            $result = $this->create_search_indexes();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 6: Create media table for rich media support.
            $result = $this->create_media_table();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 7: Create indexes for media table.
            $result = $this->create_media_indexes();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 8: Create templates table.
            $result = $this->create_templates_table();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 9: Create indexes for templates table.
            $result = $this->create_templates_indexes();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 10: Seed pre-built templates.
            $result = $this->seed_system_templates();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 11: Create user_interactions table (FR-250 to FR-259).
            $result = $this->create_user_interactions_table();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Migration 12: Create indexes for user_interactions table.
            $result = $this->create_user_interactions_indexes();
            if ( $result['success'] ) {
                $migrations_run++;
            } else {
                $errors = array_merge( $errors, $result['errors'] );
            }

            // Update migration version if successful.
            if ( empty( $errors ) ) {
                update_option( self::MIGRATION_OPTION, $this->version );
            }
        } catch ( \Exception $e ) {
            $errors[] = $e->getMessage();
        }

        return array(
            'success'        => empty( $errors ),
            'migrations_run' => $migrations_run,
            'errors'         => $errors,
            'version'        => $this->version,
        );
    }

    /**
     * Add is_favorite column to conversations table.
     *
     * Implements: FR-206 (Mark Favorite)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function add_favorite_column(): array {
        global $wpdb;

        $table_name = $this->table_prefix . 'conversations';
        $errors     = array();

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            return array(
                'success' => false,
                'errors'  => array( 'Conversations table does not exist.' ),
            );
        }

        // Check if column already exists.
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = 'is_favorite'",
                DB_NAME,
                $table_name
            )
        );

        if ( $column_exists ) {
            // Column already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Add the column.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0
             AFTER user_id"
        );

        if ( $result === false ) {
            $errors[] = sprintf(
                'Failed to add is_favorite column: %s',
                $wpdb->last_error
            );
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Add is_archived column to conversations table.
     *
     * Implements: FR-208 (Archive Conversation)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function add_archived_column(): array {
        global $wpdb;

        $table_name = $this->table_prefix . 'conversations';
        $errors     = array();

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            return array(
                'success' => false,
                'errors'  => array( 'Conversations table does not exist.' ),
            );
        }

        // Check if column already exists.
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = 'is_archived'",
                DB_NAME,
                $table_name
            )
        );

        if ( $column_exists ) {
            // Column already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Add the column.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0
             AFTER is_favorite"
        );

        if ( $result === false ) {
            $errors[] = sprintf(
                'Failed to add is_archived column: %s',
                $wpdb->last_error
            );
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Create performance indexes for chat history feature.
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_chat_history_indexes(): array {
        $errors = array();

        $conversations_table = $this->table_prefix . 'conversations';
        $messages_table      = $this->table_prefix . 'messages';

        // Indexes for conversations table.
        $conversation_indexes = array(
            'idx_user_favorite'       => 'user_id, is_favorite',
            'idx_user_archived'       => 'user_id, is_archived',
            'idx_user_updated'        => 'user_id, updated_at',
            'idx_chatbot_user'        => 'chatbot_id, user_id',
            'idx_is_favorite'         => 'is_favorite',
            'idx_is_archived'         => 'is_archived',
            'idx_conversation_filter' => 'user_id, is_archived, is_favorite, updated_at',
        );

        foreach ( $conversation_indexes as $index_name => $columns ) {
            if ( ! $this->create_index_safely( $conversations_table, $index_name, $columns ) ) {
                // Index already exists or table doesn't exist - not an error.
            }
        }

        // Indexes for messages table (for preview queries).
        $message_indexes = array(
            'idx_conversation_role'    => 'conversation_id, role',
            'idx_conversation_created' => 'conversation_id, created_at',
        );

        foreach ( $message_indexes as $index_name => $columns ) {
            if ( ! $this->create_index_safely( $messages_table, $index_name, $columns ) ) {
                // Index already exists or table doesn't exist - not an error.
            }
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Create index safely (check if exists first).
     *
     * @since 2.0.0
     *
     * @param string $table_name Table name.
     * @param string $index_name Index name.
     * @param string $columns    Column(s) to index.
     * @return bool True if index was created, false if already exists.
     */
    private function create_index_safely( string $table_name, string $index_name, string $columns ): bool {
        global $wpdb;

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            return false;
        }

        // Check if index already exists.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing_index = $wpdb->get_row(
            $wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                $index_name
            )
        );

        if ( $existing_index ) {
            return false; // Index already exists.
        }

        // Create the index.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query( "CREATE INDEX {$index_name} ON {$table_name} ({$columns})" );

        return $result !== false;
    }

    /**
     * Create FULLTEXT index on messages.content for search functionality.
     *
     * Implements: FR-211 (Full-Text Search on Messages)
     * Implements: FR-219 (Search Performance Optimization)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_fulltext_index(): array {
        global $wpdb;

        $table_name = $this->table_prefix . 'messages';
        $errors     = array();

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            return array(
                'success' => false,
                'errors'  => array( 'Messages table does not exist.' ),
            );
        }

        // Check if FULLTEXT index already exists on content column.
        $index_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s
                 AND TABLE_NAME = %s
                 AND INDEX_TYPE = 'FULLTEXT'
                 AND COLUMN_NAME = 'content'",
                DB_NAME,
                $table_name
            )
        );

        if ( $index_exists ) {
            // Index already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Create the FULLTEXT index.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query(
            "ALTER TABLE {$table_name}
             ADD FULLTEXT INDEX ft_content (content)"
        );

        if ( $result === false ) {
            $errors[] = sprintf(
                'Failed to create FULLTEXT index: %s',
                $wpdb->last_error
            );
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Create additional indexes for search performance.
     *
     * Implements: FR-214 (Search Filters)
     * Implements: FR-219 (Search Performance Optimization)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_search_indexes(): array {
        $errors = array();

        $messages_table      = $this->table_prefix . 'messages';
        $conversations_table = $this->table_prefix . 'conversations';

        // Indexes for search filtering on messages table.
        $message_indexes = array(
            'idx_convo_created_role' => 'conversation_id, created_at, role',
            'idx_role_created'       => 'role, created_at',
        );

        foreach ( $message_indexes as $index_name => $columns ) {
            $this->create_index_safely( $messages_table, $index_name, $columns );
        }

        // Indexes for search filtering on conversations table.
        $conversation_indexes = array(
            'idx_user_chatbot_archived' => 'user_id, chatbot_id, is_archived',
        );

        foreach ( $conversation_indexes as $index_name => $columns ) {
            $this->create_index_safely( $conversations_table, $index_name, $columns );
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Create media table for rich media support.
     *
     * Implements: FR-224 (Media Upload Handling)
     * Implements: FR-229 (Storage Management)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_media_table(): array {
        global $wpdb;

        $table_name      = $this->table_prefix . 'media';
        $charset_collate = $wpdb->get_charset_collate();
        $errors          = array();

        // Check if table already exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( $table_exists ) {
            // Table already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Create the media table.
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned DEFAULT NULL,
            conversation_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            media_type varchar(20) NOT NULL COMMENT 'image, video, document, link',
            file_name varchar(255) NOT NULL,
            file_path varchar(500) DEFAULT NULL,
            file_url varchar(500) NOT NULL,
            mime_type varchar(100) NOT NULL,
            file_size bigint(20) unsigned NOT NULL DEFAULT 0,
            metadata longtext DEFAULT NULL COMMENT 'JSON: dimensions, thumbnail, etc.',
            status varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active, orphaned, deleted',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_message_id (message_id),
            KEY idx_conversation_id (conversation_id),
            KEY idx_user_id (user_id),
            KEY idx_media_type (media_type),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Verify table was created.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            $errors[] = 'Failed to create media table.';
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Create indexes for media table.
     *
     * Implements: FR-229 (Storage Management)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_media_indexes(): array {
        $errors = array();

        $media_table = $this->table_prefix . 'media';

        // Additional indexes for media table.
        $media_indexes = array(
            'idx_message_status'      => 'message_id, status',
            'idx_conversation_status' => 'conversation_id, status',
            'idx_user_type_status'    => 'user_id, media_type, status',
            'idx_status_created'      => 'status, created_at',
        );

        foreach ( $media_indexes as $index_name => $columns ) {
            $this->create_index_safely( $media_table, $index_name, $columns );
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Check if Phase 2 migration is needed.
     *
     * @since 2.0.0
     *
     * @return bool True if migration is needed.
     */
    public function is_migration_needed(): bool {
        $current_version = get_option( self::MIGRATION_OPTION, '0.0.0' );
        return version_compare( $current_version, $this->version, '<' );
    }

    /**
     * Get current Phase 2 migration version.
     *
     * @since 2.0.0
     *
     * @return string Current version.
     */
    public function get_current_version(): string {
        return get_option( self::MIGRATION_OPTION, '0.0.0' );
    }

    /**
     * Get Phase 2 migration status.
     *
     * @since 2.0.0
     *
     * @return array Migration status information.
     */
    public function get_migration_status(): array {
        return array(
            'current_version'    => $this->get_current_version(),
            'target_version'     => $this->version,
            'migration_needed'   => $this->is_migration_needed(),
            'columns_status'     => $this->get_columns_status(),
            'indexes_status'     => $this->get_indexes_status(),
        );
    }

    /**
     * Get status of Phase 2 columns.
     *
     * @since 2.0.0
     *
     * @return array Column existence status.
     */
    private function get_columns_status(): array {
        global $wpdb;

        $table_name = $this->table_prefix . 'conversations';
        $columns    = array( 'is_favorite', 'is_archived' );
        $status     = array();

        foreach ( $columns as $column ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = %s
                     AND TABLE_NAME = %s
                     AND COLUMN_NAME = %s",
                    DB_NAME,
                    $table_name,
                    $column
                )
            );

            $status[ $column ] = (bool) $exists;
        }

        return $status;
    }

    /**
     * Get status of Phase 2 indexes.
     *
     * @since 2.0.0
     *
     * @return array Index existence status.
     */
    private function get_indexes_status(): array {
        global $wpdb;

        $tables = array(
            'conversations' => array(
                'idx_user_favorite',
                'idx_user_archived',
                'idx_user_updated',
                'idx_chatbot_user',
                'idx_is_favorite',
                'idx_is_archived',
                'idx_conversation_filter',
                'idx_user_chatbot_archived', // Search feature.
            ),
            'messages'      => array(
                'idx_conversation_role',
                'idx_conversation_created',
                'ft_content',                 // FULLTEXT index for search.
                'idx_convo_created_role',     // Search feature.
                'idx_role_created',           // Search feature.
            ),
            'media'         => array(       // Rich media support.
                'idx_message_id',
                'idx_conversation_id',
                'idx_user_id',
                'idx_media_type',
                'idx_status',
                'idx_created_at',
                'idx_message_status',
                'idx_conversation_status',
                'idx_user_type_status',
                'idx_status_created',
            ),
            'templates'     => array(       // Conversation templates.
                'uk_name',
                'idx_category',
                'idx_is_system',
                'idx_is_active',
                'idx_created_by',
                'idx_created_at',
                'idx_category_active',
                'idx_system_active',
                'idx_usage_count',
            ),
        );

        $status = array();

        foreach ( $tables as $table => $indexes ) {
            $table_name      = $this->table_prefix . $table;
            $status[ $table ] = array();

            foreach ( $indexes as $index ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $exists = $wpdb->get_row(
                    $wpdb->prepare(
                        "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                        $index
                    )
                );

                $status[ $table ][ $index ] = $exists !== null;
            }
        }

        return $status;
    }

    /**
     * Create templates table for conversation templates.
     *
     * Implements: FR-230 (Template Data Model)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_templates_table(): array {
        global $wpdb;

        $table_name      = $this->table_prefix . 'templates';
        $charset_collate = $wpdb->get_charset_collate();
        $errors          = array();

        // Check if table already exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( $table_exists ) {
            // Table already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Create the templates table.
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            category varchar(50) NOT NULL DEFAULT 'general' COMMENT 'support, sales, marketing, education, general',
            style longtext DEFAULT NULL COMMENT 'JSON: UI styling configuration',
            messages_template longtext DEFAULT NULL COMMENT 'JSON: personality, greeting, fallback, etc.',
            model_config longtext DEFAULT NULL COMMENT 'JSON: model, temperature, max_tokens, etc.',
            conversation_starters longtext DEFAULT NULL COMMENT 'JSON: array of starter prompts',
            thumbnail varchar(500) DEFAULT NULL,
            is_system tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Pre-built templates cannot be deleted',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            usage_count int(11) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_name (name),
            KEY idx_category (category),
            KEY idx_is_system (is_system),
            KEY idx_is_active (is_active),
            KEY idx_created_by (created_by),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Verify table was created.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            $errors[] = 'Failed to create templates table.';
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Create indexes for templates table.
     *
     * Implements: FR-231 (Admin Template List View)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_templates_indexes(): array {
        $errors = array();

        $templates_table = $this->table_prefix . 'templates';

        // Additional indexes for templates table.
        $template_indexes = array(
            'idx_category_active' => 'category, is_active',
            'idx_system_active'   => 'is_system, is_active',
            'idx_usage_count'     => 'usage_count',
        );

        foreach ( $template_indexes as $index_name => $columns ) {
            $this->create_index_safely( $templates_table, $index_name, $columns );
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Seed pre-built system templates.
     *
     * Implements: FR-235 (FAQ Bot), FR-236 (Customer Support),
     * FR-237 (Product Advisor), FR-238 (Lead Capture)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function seed_system_templates(): array {
        $errors = array();

        // Use the Template_Manager to install system templates.
        if ( class_exists( '\\AI_BotKit\\Features\\Template_Manager' ) ) {
            $template_manager = new \AI_BotKit\Features\Template_Manager();
            $installed        = $template_manager->install_system_templates();

            if ( $installed === 0 ) {
                // Templates might already exist, not an error.
            }
        } else {
            // Fallback: Load templates directly if class not available yet.
            $this->seed_templates_directly();
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Seed templates directly (fallback method).
     *
     * @since 2.0.0
     */
    private function seed_templates_directly(): void {
        global $wpdb;

        $table_name    = $this->table_prefix . 'templates';
        $templates_dir = AI_BOTKIT_PLUGIN_DIR . 'includes/features/templates/';

        $template_files = array(
            'faq-bot.json',
            'customer-support.json',
            'product-advisor.json',
            'lead-capture.json',
        );

        foreach ( $template_files as $file ) {
            $file_path = $templates_dir . $file;

            if ( ! file_exists( $file_path ) ) {
                continue;
            }

            $content = file_get_contents( $file_path );
            $data    = json_decode( $content, true );

            if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
                continue;
            }

            // Check if template already exists.
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE name = %s",
                    $data['name']
                )
            );

            if ( $exists ) {
                continue;
            }

            // Insert template.
            $wpdb->insert(
                $table_name,
                array(
                    'name'                  => sanitize_text_field( $data['name'] ),
                    'description'           => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
                    'category'              => sanitize_key( $data['category'] ?? 'general' ),
                    'style'                 => isset( $data['style'] ) ? wp_json_encode( $data['style'] ) : null,
                    'messages_template'     => isset( $data['messages_template'] ) ? wp_json_encode( $data['messages_template'] ) : null,
                    'model_config'          => isset( $data['model_config'] ) ? wp_json_encode( $data['model_config'] ) : null,
                    'conversation_starters' => isset( $data['conversation_starters'] ) ? wp_json_encode( $data['conversation_starters'] ) : null,
                    'is_system'             => 1,
                    'is_active'             => 1,
                    'usage_count'           => 0,
                    'created_at'            => current_time( 'mysql' ),
                    'updated_at'            => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
            );
        }
    }

    /**
     * Create user_interactions table for browsing/recommendation tracking.
     *
     * Implements: FR-252 (Browsing History Tracking)
     * Implements: FR-253 (Purchase/Enrollment History Integration)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_user_interactions_table(): array {
        global $wpdb;

        $table_name      = $this->table_prefix . 'user_interactions';
        $charset_collate = $wpdb->get_charset_collate();
        $errors          = array();

        // Check if table already exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( $table_exists ) {
            // Table already exists, no action needed.
            return array(
                'success' => true,
                'errors'  => array(),
            );
        }

        // Create the user_interactions table.
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '0 for guests',
            session_id varchar(64) NOT NULL COMMENT 'Session identifier for guests',
            interaction_type varchar(50) NOT NULL COMMENT 'page_view, product_view, course_view, add_to_cart, enroll',
            item_type varchar(50) NOT NULL COMMENT 'product, course, post, page',
            item_id bigint(20) unsigned NOT NULL,
            chatbot_id bigint(20) unsigned DEFAULT NULL COMMENT 'Associated chatbot if any',
            metadata longtext DEFAULT NULL COMMENT 'JSON: additional interaction data',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_interaction_type (interaction_type),
            KEY idx_item_type (item_type),
            KEY idx_item_id (item_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Verify table was created.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
        );

        if ( ! $table_exists ) {
            $errors[] = 'Failed to create user_interactions table.';
        }

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }

    /**
     * Create indexes for user_interactions table.
     *
     * Implements: FR-252 (Browsing History Tracking)
     *
     * @since 2.0.0
     *
     * @return array Migration result.
     */
    private function create_user_interactions_indexes(): array {
        $errors = array();

        $table_name = $this->table_prefix . 'user_interactions';

        // Additional composite indexes for recommendation queries.
        $indexes = array(
            'idx_user_interaction_type'    => 'user_id, interaction_type',
            'idx_session_interaction_type' => 'session_id, interaction_type',
            'idx_user_item'                => 'user_id, item_type, item_id',
            'idx_session_item'             => 'session_id, item_type, item_id',
            'idx_user_created'             => 'user_id, created_at',
            'idx_session_created'          => 'session_id, created_at',
            'idx_chatbot_user'             => 'chatbot_id, user_id',
        );

        foreach ( $indexes as $index_name => $columns ) {
            $this->create_index_safely( $table_name, $index_name, $columns );
        }

        return array(
            'success' => true,
            'errors'  => $errors,
        );
    }

    /**
     * Rollback Phase 2 migrations (for testing/debugging).
     *
     * WARNING: This will remove Phase 2 columns and data.
     *
     * @since 2.0.0
     *
     * @return array Rollback result.
     */
    public function rollback(): array {
        global $wpdb;

        $errors     = array();
        $table_name = $this->table_prefix . 'conversations';

        // Only allow rollback if admin.
        if ( ! current_user_can( 'manage_options' ) ) {
            return array(
                'success' => false,
                'errors'  => array( 'Insufficient permissions to rollback.' ),
            );
        }

        // Remove is_favorite column.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS is_favorite" );
        if ( $result === false && $wpdb->last_error ) {
            $errors[] = 'Failed to drop is_favorite column: ' . $wpdb->last_error;
        }

        // Remove is_archived column.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN IF EXISTS is_archived" );
        if ( $result === false && $wpdb->last_error ) {
            $errors[] = 'Failed to drop is_archived column: ' . $wpdb->last_error;
        }

        // Reset migration version.
        delete_option( self::MIGRATION_OPTION );

        return array(
            'success' => empty( $errors ),
            'errors'  => $errors,
        );
    }
}
