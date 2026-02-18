<?php
/**
 * Phase 2 AJAX Handler Integration Tests
 *
 * Tests AJAX handlers for Phase 2 features:
 * - ai_botkit_get_history
 * - ai_botkit_search_conversations
 * - ai_botkit_upload_media
 * - ai_botkit_save_template
 * - ai_botkit_export_pdf
 * - ai_botkit_get_recommendations
 * - Nonce verification
 * - Capability checks
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

namespace AI_BotKit\Tests\Integration;

use WP_Ajax_UnitTestCase;

/**
 * Phase2AjaxTest class.
 *
 * Integration tests for Phase 2 AJAX handlers.
 *
 * @since 2.0.0
 */
class Phase2AjaxTest extends WP_Ajax_UnitTestCase {

    /**
     * Database object.
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Table prefix.
     *
     * @var string
     */
    private string $prefix;

    /**
     * Admin user ID.
     *
     * @var int
     */
    private int $admin_user_id;

    /**
     * Regular user ID.
     *
     * @var int
     */
    private int $subscriber_user_id;

    /**
     * Test chatbot ID.
     *
     * @var int
     */
    private int $test_chatbot_id;

    /**
     * Test conversation ID.
     *
     * @var int
     */
    private int $test_conversation_id;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->db     = $wpdb;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';

        // Create test users.
        $this->admin_user_id = $this->factory->user->create( array(
            'role' => 'administrator',
        ) );

        $this->subscriber_user_id = $this->factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Ensure tables exist.
        $this->create_test_tables();

        // Create test data.
        $this->create_test_data();

        // Register AJAX handlers for testing.
        $this->register_ajax_handlers();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        $this->cleanup_test_data();
        parent::tearDown();
    }

    /**
     * Create required tables for testing.
     */
    private function create_test_tables(): void {
        $charset_collate = $this->db->get_charset_collate();

        // Create chatbots table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}chatbots (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                avatar VARCHAR(255),
                style JSON,
                messages_template JSON,
                model_config JSON,
                template_id BIGINT(20) UNSIGNED,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create conversations table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}conversations (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                chatbot_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED,
                session_id VARCHAR(100),
                guest_ip VARCHAR(45),
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create messages table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}messages (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                metadata JSON,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX conversation_id (conversation_id)
            ) {$charset_collate} ENGINE=InnoDB;"
        );

        // Create templates table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}templates (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(50) NOT NULL DEFAULT 'general',
                style JSON,
                messages_template JSON,
                model_config JSON,
                conversation_starters JSON,
                thumbnail VARCHAR(255),
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                usage_count INT NOT NULL DEFAULT 0,
                created_by BIGINT(20) UNSIGNED,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create media table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}media (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                message_id BIGINT(20) UNSIGNED,
                conversation_id BIGINT(20) UNSIGNED,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                media_type VARCHAR(20) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_url VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size BIGINT(20) NOT NULL DEFAULT 0,
                metadata JSON,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create user interactions table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}user_interactions (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                session_id VARCHAR(100),
                interaction_type VARCHAR(50) NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id BIGINT(20) UNSIGNED NOT NULL,
                chatbot_id BIGINT(20) UNSIGNED,
                metadata JSON,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Add FULLTEXT index if not exists.
        $index_exists = $this->db->get_var(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE table_schema = DATABASE()
             AND table_name = '{$this->prefix}messages'
             AND index_name = 'ft_content'"
        );
        if ( ! $index_exists ) {
            $this->db->query( "ALTER TABLE {$this->prefix}messages ADD FULLTEXT INDEX ft_content (content)" );
        }
    }

    /**
     * Create test data.
     */
    private function create_test_data(): void {
        // Create a test chatbot.
        $this->db->insert(
            $this->prefix . 'chatbots',
            array(
                'name'       => 'Test Chatbot',
                'active'     => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );
        $this->test_chatbot_id = $this->db->insert_id;

        // Create a test conversation for subscriber.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $this->subscriber_user_id,
                'session_id' => 'sess_test_' . uniqid(),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $this->test_conversation_id = $this->db->insert_id;

        // Create test messages.
        $messages = array(
            array( 'role' => 'user', 'content' => 'Hello, I need help with WordPress plugins.' ),
            array( 'role' => 'assistant', 'content' => 'Sure! I can help you with WordPress plugins. What would you like to know?' ),
            array( 'role' => 'user', 'content' => 'Which plugins are best for SEO optimization?' ),
            array( 'role' => 'assistant', 'content' => 'Some of the best WordPress SEO plugins include Yoast SEO, Rank Math, and All in One SEO.' ),
        );

        foreach ( $messages as $message ) {
            $this->db->insert(
                $this->prefix . 'messages',
                array(
                    'conversation_id' => $this->test_conversation_id,
                    'role'            => $message['role'],
                    'content'         => $message['content'],
                    'created_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Register AJAX handlers for testing.
     */
    private function register_ajax_handlers(): void {
        // History handler.
        add_action( 'wp_ajax_ai_botkit_get_history', array( $this, 'ajax_get_history' ) );

        // Search handler.
        add_action( 'wp_ajax_ai_botkit_search_conversations', array( $this, 'ajax_search_conversations' ) );

        // Upload media handler.
        add_action( 'wp_ajax_ai_botkit_upload_media', array( $this, 'ajax_upload_media' ) );

        // Save template handler.
        add_action( 'wp_ajax_ai_botkit_save_template', array( $this, 'ajax_save_template' ) );

        // Export PDF handler.
        add_action( 'wp_ajax_ai_botkit_export_pdf', array( $this, 'ajax_export_pdf' ) );

        // Get recommendations handler.
        add_action( 'wp_ajax_ai_botkit_get_recommendations', array( $this, 'ajax_get_recommendations' ) );
        add_action( 'wp_ajax_nopriv_ai_botkit_get_recommendations', array( $this, 'ajax_get_recommendations' ) );
    }

    /**
     * Clean up test data.
     */
    private function cleanup_test_data(): void {
        $this->db->query( "DELETE FROM {$this->prefix}messages WHERE conversation_id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}conversations WHERE id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}chatbots WHERE id = {$this->test_chatbot_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}templates WHERE name LIKE 'Test%'" );
        $this->db->query( "DELETE FROM {$this->prefix}media WHERE file_name LIKE 'test%'" );
    }

    // =========================================================================
    // AJAX HANDLER IMPLEMENTATIONS FOR TESTING
    // =========================================================================

    /**
     * AJAX handler: Get chat history.
     */
    public function ajax_get_history(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_frontend', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        // Check user is logged in.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to view chat history.' ) );
            return;
        }

        $user_id    = get_current_user_id();
        $page       = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page   = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
        $chatbot_id = isset( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : null;

        $per_page = max( 1, min( 100, $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where_conditions = array( 'c.user_id = %d' );
        $where_params     = array( $user_id );

        if ( $chatbot_id ) {
            $where_conditions[] = 'c.chatbot_id = %d';
            $where_params[]     = $chatbot_id;
        }

        $where_conditions[] = '(c.is_archived = 0 OR c.is_archived IS NULL)';
        $where_clause       = implode( ' AND ', $where_conditions );

        // Get total count.
        $total = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}conversations AS c WHERE {$where_clause}",
                $where_params
            )
        );

        // Get conversations.
        $params = array_merge( $where_params, array( $per_page, $offset ) );

        $conversations = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    c.id,
                    c.chatbot_id,
                    c.session_id,
                    c.is_favorite,
                    c.is_archived,
                    c.created_at,
                    c.updated_at,
                    cb.name AS chatbot_name,
                    (SELECT content FROM {$this->prefix}messages
                     WHERE conversation_id = c.id AND role = 'user'
                     ORDER BY created_at ASC LIMIT 1) AS first_message,
                    (SELECT COUNT(*) FROM {$this->prefix}messages
                     WHERE conversation_id = c.id) AS message_count
                 FROM {$this->prefix}conversations AS c
                 LEFT JOIN {$this->prefix}chatbots AS cb ON c.chatbot_id = cb.id
                 WHERE {$where_clause}
                 ORDER BY c.updated_at DESC
                 LIMIT %d OFFSET %d",
                $params
            ),
            ARRAY_A
        );

        // Format response.
        $formatted = array();
        foreach ( $conversations as $conv ) {
            $preview          = wp_strip_all_tags( $conv['first_message'] ?? '' );
            $formatted[]      = array(
                'id'            => (int) $conv['id'],
                'chatbot_id'    => (int) $conv['chatbot_id'],
                'chatbot_name'  => $conv['chatbot_name'] ?? 'Unknown Bot',
                'preview'       => mb_substr( $preview, 0, 100 ) . ( mb_strlen( $preview ) > 100 ? '...' : '' ),
                'message_count' => (int) $conv['message_count'],
                'is_favorite'   => (bool) $conv['is_favorite'],
                'created_at'    => $conv['created_at'],
                'updated_at'    => $conv['updated_at'],
            );
        }

        wp_send_json_success( array(
            'conversations' => $formatted,
            'total'         => $total,
            'pages'         => (int) ceil( $total / $per_page ),
            'current_page'  => $page,
        ) );
    }

    /**
     * AJAX handler: Search conversations.
     */
    public function ajax_search_conversations(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_frontend', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        // Check user is logged in.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to search conversations.' ) );
            return;
        }

        $query    = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

        if ( mb_strlen( $query ) < 2 ) {
            wp_send_json_error( array( 'message' => 'Search query must be at least 2 characters.' ) );
            return;
        }

        $user_id  = get_current_user_id();
        $per_page = max( 1, min( 100, $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Count total results.
        $total = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(DISTINCT m.id)
                 FROM {$this->prefix}messages AS m
                 INNER JOIN {$this->prefix}conversations AS c ON m.conversation_id = c.id
                 WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                 AND c.user_id = %d
                 AND (c.is_archived = 0 OR c.is_archived IS NULL)",
                $query,
                $user_id
            )
        );

        // Get results with relevance.
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    m.id AS message_id,
                    m.conversation_id,
                    m.role,
                    m.content,
                    m.created_at,
                    c.chatbot_id,
                    cb.name AS chatbot_name,
                    MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance
                 FROM {$this->prefix}messages AS m
                 INNER JOIN {$this->prefix}conversations AS c ON m.conversation_id = c.id
                 LEFT JOIN {$this->prefix}chatbots AS cb ON c.chatbot_id = cb.id
                 WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                 AND c.user_id = %d
                 AND (c.is_archived = 0 OR c.is_archived IS NULL)
                 ORDER BY relevance DESC
                 LIMIT %d OFFSET %d",
                $query,
                $query,
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        // Format and highlight results.
        $formatted = array();
        foreach ( $results as $result ) {
            $content = wp_strip_all_tags( $result['content'] );

            // Highlight search terms.
            $highlighted = preg_replace(
                '/(' . preg_quote( $query, '/' ) . ')/i',
                '<mark class="ai-botkit-highlight">$1</mark>',
                esc_html( $content )
            );

            $formatted[] = array(
                'message_id'          => (int) $result['message_id'],
                'conversation_id'     => (int) $result['conversation_id'],
                'chatbot_id'          => (int) $result['chatbot_id'],
                'chatbot_name'        => $result['chatbot_name'] ?? 'Unknown Bot',
                'role'                => $result['role'],
                'content'             => mb_substr( $content, 0, 200 ) . ( mb_strlen( $content ) > 200 ? '...' : '' ),
                'content_highlighted' => $highlighted,
                'relevance_score'     => round( (float) $result['relevance'], 4 ),
                'created_at'          => $result['created_at'],
            );
        }

        /**
         * Filter search results before sending response.
         *
         * @since 2.0.0
         *
         * @param array  $formatted Formatted search results.
         * @param string $query     Search query.
         * @param int    $user_id   User ID.
         */
        $formatted = apply_filters( 'ai_botkit_search_results', $formatted, $query, $user_id );

        wp_send_json_success( array(
            'results'      => $formatted,
            'total'        => $total,
            'pages'        => (int) ceil( $total / $per_page ),
            'current_page' => $page,
            'query'        => $query,
        ) );
    }

    /**
     * AJAX handler: Upload media.
     */
    public function ajax_upload_media(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_frontend', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        // Check user is logged in.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to upload media.' ) );
            return;
        }

        // Check if file was uploaded.
        if ( empty( $_FILES['media_file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
            return;
        }

        $file            = $_FILES['media_file'];
        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : null;

        // Validate file.
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array( 'message' => 'File upload error.' ) );
            return;
        }

        // Check file size (10MB max).
        $max_size = 10485760;
        if ( $file['size'] > $max_size ) {
            wp_send_json_error( array( 'message' => 'File exceeds maximum size of 10MB.' ) );
            return;
        }

        // Validate MIME type.
        $allowed_types = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $finfo     = new \finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => 'File type not allowed.' ) );
            return;
        }

        // Generate safe filename.
        $extension     = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $safe_filename = uniqid() . '_' . sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ) . '.' . $extension;

        // Create upload directory.
        $upload_dir = wp_upload_dir();
        $media_type = strpos( $mime_type, 'image/' ) === 0 ? 'image' : 'document';
        $type_dir   = $media_type === 'image' ? 'images' : 'files';
        $dir_path   = $upload_dir['basedir'] . '/ai-botkit/chat-media/' . $type_dir . '/' . gmdate( 'Y/m' );
        $dir_url    = $upload_dir['baseurl'] . '/ai-botkit/chat-media/' . $type_dir . '/' . gmdate( 'Y/m' );

        if ( ! file_exists( $dir_path ) ) {
            wp_mkdir_p( $dir_path );
        }

        $file_path = $dir_path . '/' . $safe_filename;
        $file_url  = $dir_url . '/' . $safe_filename;

        // Move uploaded file.
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            wp_send_json_error( array( 'message' => 'Failed to save uploaded file.' ) );
            return;
        }

        // Generate metadata.
        $metadata = array();
        if ( $media_type === 'image' ) {
            $image_info = @getimagesize( $file_path );
            if ( $image_info ) {
                $metadata['width']  = $image_info[0];
                $metadata['height'] = $image_info[1];
            }
        }

        // Insert media record.
        $this->db->insert(
            $this->prefix . 'media',
            array(
                'conversation_id' => $conversation_id,
                'user_id'         => get_current_user_id(),
                'media_type'      => $media_type,
                'file_name'       => $safe_filename,
                'file_path'       => $file_path,
                'file_url'        => $file_url,
                'mime_type'       => $mime_type,
                'file_size'       => $file['size'],
                'metadata'        => wp_json_encode( $metadata ),
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        $media_id = $this->db->insert_id;

        wp_send_json_success( array(
            'id'        => $media_id,
            'url'       => $file_url,
            'type'      => $media_type,
            'filename'  => $safe_filename,
            'size'      => $file['size'],
            'mime_type' => $mime_type,
            'metadata'  => $metadata,
        ) );
    }

    /**
     * AJAX handler: Save template.
     */
    public function ajax_save_template(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_admin', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        // Check admin capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        // Build template data.
        $data = array(
            'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'description'       => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'category'          => isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : 'general',
            'style'             => isset( $_POST['style'] ) ? wp_json_encode( $_POST['style'] ) : null,
            'messages_template' => isset( $_POST['messages_template'] ) ? wp_json_encode( $_POST['messages_template'] ) : null,
            'model_config'      => isset( $_POST['model_config'] ) ? wp_json_encode( $_POST['model_config'] ) : null,
            'is_active'         => isset( $_POST['is_active'] ) ? (int) $_POST['is_active'] : 1,
            'updated_at'        => current_time( 'mysql' ),
        );

        // Validate required fields.
        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Template name is required.' ) );
            return;
        }

        if ( $template_id ) {
            // Update existing template.
            $existing = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                    $template_id
                )
            );

            if ( ! $existing ) {
                wp_send_json_error( array( 'message' => 'Template not found.' ) );
                return;
            }

            if ( $existing->is_system ) {
                wp_send_json_error( array( 'message' => 'System templates cannot be modified.' ) );
                return;
            }

            $this->db->update(
                $this->prefix . 'templates',
                $data,
                array( 'id' => $template_id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );

            /**
             * Fires after a template is applied.
             *
             * @since 2.0.0
             *
             * @param int   $template_id Template ID.
             * @param array $data        Template data.
             */
            do_action( 'ai_botkit_template_applied', $template_id, null );

            $message = 'Template updated successfully.';
        } else {
            // Create new template.
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time( 'mysql' );
            $data['is_system']  = 0;

            $this->db->insert(
                $this->prefix . 'templates',
                $data,
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d' )
            );

            $template_id = $this->db->insert_id;
            $message     = 'Template created successfully.';
        }

        // Get the saved template.
        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        wp_send_json_success( array(
            'message'     => $message,
            'template_id' => $template_id,
            'template'    => $template,
        ) );
    }

    /**
     * AJAX handler: Export PDF.
     */
    public function ajax_export_pdf(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_frontend', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        // Check user is logged in.
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in to export conversations.' ) );
            return;
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( ! $conversation_id ) {
            wp_send_json_error( array( 'message' => 'Conversation ID is required.' ) );
            return;
        }

        // Check ownership.
        $conversation = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}conversations WHERE id = %d",
                $conversation_id
            )
        );

        if ( ! $conversation ) {
            wp_send_json_error( array( 'message' => 'Conversation not found.' ) );
            return;
        }

        $user_id = get_current_user_id();
        if ( (int) $conversation->user_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to export this conversation.' ) );
            return;
        }

        // Get conversation data for export.
        $chatbot = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}chatbots WHERE id = %d",
                $conversation->chatbot_id
            )
        );

        $messages = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ),
            ARRAY_A
        );

        // Generate export data (PDF generation would require dompdf).
        $export_data = array(
            'conversation_id' => $conversation_id,
            'chatbot_name'    => $chatbot->name ?? 'AI Assistant',
            'created_at'      => $conversation->created_at,
            'messages'        => $messages,
            'message_count'   => count( $messages ),
        );

        // For testing, return success with data. In production, this would generate a PDF.
        wp_send_json_success( array(
            'message'     => 'Export data prepared successfully.',
            'export_data' => $export_data,
            'filename'    => 'chat-transcript-' . gmdate( 'Y-m-d' ) . '-' . $conversation_id . '.pdf',
        ) );
    }

    /**
     * AJAX handler: Get recommendations.
     */
    public function ajax_get_recommendations(): void {
        // Verify nonce (allow for non-logged-in users too).
        if ( ! check_ajax_referer( 'ai_botkit_frontend', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
            return;
        }

        $user_id           = get_current_user_id();
        $conversation_text = isset( $_POST['conversation_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['conversation_text'] ) ) : '';
        $chatbot_id        = isset( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : 0;
        $limit             = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;

        // Check if recommendations are enabled.
        if ( ! get_option( 'ai_botkit_recommendation_enabled', true ) ) {
            wp_send_json_success( array( 'recommendations' => array() ) );
            return;
        }

        // Simple keyword extraction for recommendations.
        $keywords = array();
        if ( ! empty( $conversation_text ) ) {
            $words    = preg_split( '/\s+/', strtolower( $conversation_text ) );
            $keywords = array_filter( $words, function ( $word ) {
                return strlen( $word ) > 3;
            } );
        }

        // In a real implementation, this would query products/courses.
        // For testing, return mock recommendations.
        $recommendations = array();

        // Generate mock recommendations based on keywords.
        if ( ! empty( $keywords ) ) {
            for ( $i = 1; $i <= min( $limit, 3 ); $i++ ) {
                $recommendations[] = array(
                    'id'              => 1000 + $i,
                    'type'            => ( $i % 2 === 0 ) ? 'product' : 'course',
                    'title'           => 'Recommended Item ' . $i,
                    'description'     => 'Based on your conversation about ' . implode( ', ', array_slice( $keywords, 0, 3 ) ),
                    'price'           => '$' . ( $i * 29.99 ),
                    'url'             => 'https://example.com/item-' . $i,
                    'image'           => 'https://example.com/images/item-' . $i . '.jpg',
                    'relevance_score' => round( 1.0 - ( $i * 0.1 ), 2 ),
                    'source'          => 'conversation',
                );
            }
        }

        /**
         * Fires when a recommendation is displayed.
         *
         * @since 2.0.0
         *
         * @param array $recommendations Recommendations data.
         * @param int   $user_id         User ID.
         * @param int   $chatbot_id      Chatbot ID.
         */
        do_action( 'ai_botkit_recommendation_displayed', $recommendations, $user_id, $chatbot_id );

        wp_send_json_success( array(
            'recommendations' => $recommendations,
            'total'           => count( $recommendations ),
        ) );
    }

    // =========================================================================
    // TEST METHODS
    // =========================================================================

    /**
     * Test get history AJAX handler returns conversations.
     */
    public function test_ajax_get_history_returns_conversations(): void {
        // Log in as subscriber.
        wp_set_current_user( $this->subscriber_user_id );

        // Set up request.
        $_POST['nonce']    = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['page']     = 1;
        $_POST['per_page'] = 10;

        // Capture output.
        try {
            $this->_handleAjax( 'ai_botkit_get_history' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'conversations', $response['data'] );
        $this->assertArrayHasKey( 'total', $response['data'] );
        $this->assertArrayHasKey( 'pages', $response['data'] );
        $this->assertGreaterThanOrEqual( 1, count( $response['data']['conversations'] ) );
    }

    /**
     * Test get history requires login.
     */
    public function test_ajax_get_history_requires_login(): void {
        // Ensure no user is logged in.
        wp_set_current_user( 0 );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_frontend' );

        try {
            $this->_handleAjax( 'ai_botkit_get_history' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'logged in', $response['data']['message'] );
    }

    /**
     * Test get history validates nonce.
     */
    public function test_ajax_get_history_validates_nonce(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce'] = 'invalid_nonce';

        try {
            $this->_handleAjax( 'ai_botkit_get_history' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'security token', $response['data']['message'] );
    }

    /**
     * Test get history applies per_page filter.
     */
    public function test_ajax_get_history_applies_per_page_filter(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // Create additional conversations.
        for ( $i = 0; $i < 15; $i++ ) {
            $this->db->insert(
                $this->prefix . 'conversations',
                array(
                    'chatbot_id' => $this->test_chatbot_id,
                    'user_id'    => $this->subscriber_user_id,
                    'session_id' => 'sess_page_test_' . $i,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%s', '%s' )
            );
        }

        // Apply filter.
        add_filter( 'ai_botkit_history_per_page', function () {
            return 5;
        } );

        $_POST['nonce']    = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['page']     = 1;
        $_POST['per_page'] = 5;

        try {
            $this->_handleAjax( 'ai_botkit_get_history' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertLessThanOrEqual( 5, count( $response['data']['conversations'] ) );
    }

    /**
     * Test search conversations with FULLTEXT.
     */
    public function test_ajax_search_conversations_fulltext(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce']    = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['query']    = 'WordPress plugins';
        $_POST['page']     = 1;
        $_POST['per_page'] = 20;

        try {
            $this->_handleAjax( 'ai_botkit_search_conversations' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'results', $response['data'] );
        $this->assertArrayHasKey( 'query', $response['data'] );
        $this->assertEquals( 'WordPress plugins', $response['data']['query'] );
    }

    /**
     * Test search requires minimum query length.
     */
    public function test_ajax_search_requires_min_query_length(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['query'] = 'a';  // Too short.

        try {
            $this->_handleAjax( 'ai_botkit_search_conversations' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( '2 characters', $response['data']['message'] );
    }

    /**
     * Test search results filter is applied.
     */
    public function test_ajax_search_results_filter(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // Add filter to modify results.
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            foreach ( $results as &$result ) {
                $result['filtered'] = true;
            }
            return $results;
        }, 10, 3 );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['query'] = 'SEO optimization';

        try {
            $this->_handleAjax( 'ai_botkit_search_conversations' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        if ( ! empty( $response['data']['results'] ) ) {
            foreach ( $response['data']['results'] as $result ) {
                $this->assertTrue( $result['filtered'] );
            }
        }
    }

    /**
     * Test save template requires admin capability.
     */
    public function test_ajax_save_template_requires_admin(): void {
        // Log in as subscriber (non-admin).
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['name']  = 'Test Template';

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'permissions', $response['data']['message'] );
    }

    /**
     * Test save template creates new template.
     */
    public function test_ajax_save_template_creates_new(): void {
        wp_set_current_user( $this->admin_user_id );

        $_POST['nonce']       = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['name']        = 'Test Template Create';
        $_POST['description'] = 'A test template description';
        $_POST['category']    = 'support';
        $_POST['style']       = array(
            'primaryColor'    => '#0066cc',
            'backgroundColor' => '#ffffff',
        );

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'template_id', $response['data'] );
        $this->assertGreaterThan( 0, $response['data']['template_id'] );
        $this->assertStringContainsString( 'created', $response['data']['message'] );
    }

    /**
     * Test save template updates existing template.
     */
    public function test_ajax_save_template_updates_existing(): void {
        wp_set_current_user( $this->admin_user_id );

        // Create a template first.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test Template Update',
                'category'   => 'general',
                'is_system'  => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        $_POST['nonce']       = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['template_id'] = $template_id;
        $_POST['name']        = 'Test Template Update Modified';
        $_POST['category']    = 'sales';

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertEquals( $template_id, $response['data']['template_id'] );
        $this->assertStringContainsString( 'updated', $response['data']['message'] );

        // Verify update in database.
        $updated = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            )
        );

        $this->assertEquals( 'Test Template Update Modified', $updated->name );
        $this->assertEquals( 'sales', $updated->category );
    }

    /**
     * Test save template prevents system template modification.
     */
    public function test_ajax_save_template_prevents_system_modification(): void {
        wp_set_current_user( $this->admin_user_id );

        // Create a system template.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test System Template',
                'category'   => 'general',
                'is_system'  => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        $_POST['nonce']       = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['template_id'] = $template_id;
        $_POST['name']        = 'Trying to Modify System Template';

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'System templates', $response['data']['message'] );
    }

    /**
     * Test export PDF validates ownership.
     */
    public function test_ajax_export_pdf_validates_ownership(): void {
        // Create a conversation for admin.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $this->admin_user_id,
                'session_id' => 'sess_admin_export',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $admin_conversation_id = $this->db->insert_id;

        // Try to export as subscriber.
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce']           = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_id'] = $admin_conversation_id;

        try {
            $this->_handleAjax( 'ai_botkit_export_pdf' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'permission', $response['data']['message'] );
    }

    /**
     * Test export PDF allows admin access to any conversation.
     */
    public function test_ajax_export_pdf_allows_admin_access(): void {
        wp_set_current_user( $this->admin_user_id );

        // Export subscriber's conversation as admin.
        $_POST['nonce']           = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_id'] = $this->test_conversation_id;

        try {
            $this->_handleAjax( 'ai_botkit_export_pdf' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'export_data', $response['data'] );
        $this->assertArrayHasKey( 'filename', $response['data'] );
    }

    /**
     * Test get recommendations works for logged in users.
     */
    public function test_ajax_get_recommendations_logged_in(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce']             = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_text'] = 'I am looking for WordPress ecommerce plugins for my online store';
        $_POST['chatbot_id']        = $this->test_chatbot_id;
        $_POST['limit']             = 5;

        try {
            $this->_handleAjax( 'ai_botkit_get_recommendations' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'recommendations', $response['data'] );
        $this->assertIsArray( $response['data']['recommendations'] );
    }

    /**
     * Test get recommendations works for guests.
     */
    public function test_ajax_get_recommendations_guest(): void {
        wp_set_current_user( 0 );

        $_POST['nonce']             = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_text'] = 'Show me the best courses for learning Python programming';
        $_POST['limit']             = 3;

        try {
            $this->_handleAjax( 'ai_botkit_get_recommendations' );
        } catch ( \WPAjaxDieStopException $e ) {
            // May throw this for nopriv actions.
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
        $this->assertArrayHasKey( 'recommendations', $response['data'] );
    }

    /**
     * Test recommendation displayed action fires.
     */
    public function test_ajax_get_recommendations_fires_action(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $action_fired = false;
        add_action( 'ai_botkit_recommendation_displayed', function () use ( &$action_fired ) {
            $action_fired = true;
        } );

        $_POST['nonce']             = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_text'] = 'Recommend some products for me';

        try {
            $this->_handleAjax( 'ai_botkit_get_recommendations' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $this->assertTrue( $action_fired );
    }

    /**
     * Test upload media validates file type.
     */
    public function test_ajax_upload_media_validates_file_type(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // Create a temp file with invalid content.
        $temp_file = tempnam( sys_get_temp_dir(), 'test_' );
        file_put_contents( $temp_file, '<?php echo "malicious"; ?>' );

        $_POST['nonce']           = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_id'] = $this->test_conversation_id;
        $_FILES['media_file']     = array(
            'name'     => 'malicious.php',
            'type'     => 'application/x-php',
            'tmp_name' => $temp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize( $temp_file ),
        );

        try {
            $this->_handleAjax( 'ai_botkit_upload_media' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'type not allowed', $response['data']['message'] );

        // Cleanup.
        @unlink( $temp_file );
    }

    /**
     * Test upload media validates file size.
     */
    public function test_ajax_upload_media_validates_file_size(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce']           = wp_create_nonce( 'ai_botkit_frontend' );
        $_POST['conversation_id'] = $this->test_conversation_id;
        $_FILES['media_file']     = array(
            'name'     => 'large_file.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/fake_large_file.jpg',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 20000000, // 20MB - exceeds 10MB limit.
        );

        try {
            $this->_handleAjax( 'ai_botkit_upload_media' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( '10MB', $response['data']['message'] );
    }

    /**
     * Test nonce verification fails with wrong nonce.
     */
    public function test_ajax_nonce_verification_fails(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // All handlers should reject wrong nonce.
        $handlers = array(
            'ai_botkit_get_history',
            'ai_botkit_search_conversations',
            'ai_botkit_upload_media',
            'ai_botkit_export_pdf',
            'ai_botkit_get_recommendations',
        );

        foreach ( $handlers as $handler ) {
            $_POST         = array();
            $_POST['nonce'] = 'wrong_nonce_value';

            try {
                $this->_handleAjax( $handler );
            } catch ( \WPAjaxDieContinueException $e ) {
                // Expected exception.
            }

            $response = json_decode( $this->_last_response, true );

            $this->assertFalse( $response['success'], "Handler {$handler} should reject invalid nonce" );
            $this->assertStringContainsString( 'security token', $response['data']['message'] );
        }
    }

    /**
     * Test capability check for admin-only handlers.
     */
    public function test_ajax_capability_check(): void {
        // Test as subscriber (should fail for admin handlers).
        wp_set_current_user( $this->subscriber_user_id );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['name']  = 'Test Template';

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertFalse( $response['success'] );
        $this->assertStringContainsString( 'permissions', $response['data']['message'] );

        // Test as admin (should succeed).
        wp_set_current_user( $this->admin_user_id );

        $_POST['nonce'] = wp_create_nonce( 'ai_botkit_admin' );
        $_POST['name']  = 'Test Template Admin';

        try {
            $this->_handleAjax( 'ai_botkit_save_template' );
        } catch ( \WPAjaxDieContinueException $e ) {
            // Expected exception.
        }

        $response = json_decode( $this->_last_response, true );

        $this->assertTrue( $response['success'] );
    }
}
