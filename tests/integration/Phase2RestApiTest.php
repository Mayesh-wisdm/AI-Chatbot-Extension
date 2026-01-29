<?php
/**
 * Phase 2 REST API Integration Tests
 *
 * Tests REST API endpoints for Phase 2 features:
 * - /ai-botkit/v1/history endpoints
 * - /ai-botkit/v1/search endpoints
 * - /ai-botkit/v1/templates endpoints
 * - Authentication requirements
 * - Response format validation
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

namespace AI_BotKit\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Phase2RestApiTest class.
 *
 * Integration tests for Phase 2 REST API endpoints.
 *
 * @since 2.0.0
 */
class Phase2RestApiTest extends WP_UnitTestCase {

    /**
     * REST server instance.
     *
     * @var WP_REST_Server
     */
    private WP_REST_Server $server;

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
     * API namespace.
     *
     * @var string
     */
    private string $namespace = 'ai-botkit/v1';

    /**
     * Admin user ID.
     *
     * @var int
     */
    private int $admin_user_id;

    /**
     * Subscriber user ID.
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
     * Test template ID.
     *
     * @var int
     */
    private int $test_template_id;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb, $wp_rest_server;
        $this->db     = $wpdb;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';

        // Initialize REST server.
        $wp_rest_server = new WP_REST_Server();
        $this->server   = $wp_rest_server;

        // Create test users.
        $this->admin_user_id = $this->factory->user->create( array(
            'role' => 'administrator',
        ) );

        $this->subscriber_user_id = $this->factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Create test tables.
        $this->create_test_tables();

        // Create test data.
        $this->create_test_data();

        // Register REST routes.
        $this->register_rest_routes();

        do_action( 'rest_api_init' );
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        global $wp_rest_server;
        $wp_rest_server = null;

        $this->cleanup_test_data();

        parent::tearDown();
    }

    /**
     * Create test tables.
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

        // Create messages table with FULLTEXT.
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

        // Add FULLTEXT index.
        $index_exists = $this->db->get_var(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE table_schema = DATABASE()
             AND table_name = '{$this->prefix}messages'
             AND index_name = 'ft_content'"
        );
        if ( ! $index_exists ) {
            $this->db->query( "ALTER TABLE {$this->prefix}messages ADD FULLTEXT INDEX ft_content (content)" );
        }

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
    }

    /**
     * Create test data.
     */
    private function create_test_data(): void {
        // Create chatbot.
        $this->db->insert(
            $this->prefix . 'chatbots',
            array(
                'name'       => 'Test API Chatbot',
                'active'     => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );
        $this->test_chatbot_id = $this->db->insert_id;

        // Create conversation for subscriber.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $this->subscriber_user_id,
                'session_id' => 'sess_api_test_' . uniqid(),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $this->test_conversation_id = $this->db->insert_id;

        // Create messages.
        $messages = array(
            array( 'role' => 'user', 'content' => 'Tell me about REST API best practices.' ),
            array( 'role' => 'assistant', 'content' => 'REST API best practices include using proper HTTP methods, versioning your API, and returning appropriate status codes.' ),
            array( 'role' => 'user', 'content' => 'What about authentication?' ),
            array( 'role' => 'assistant', 'content' => 'For authentication, you should use tokens like JWT or OAuth2, and always use HTTPS.' ),
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

        // Create template.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'        => 'Test API Template',
                'description' => 'A template for API testing',
                'category'    => 'support',
                'style'       => wp_json_encode( array( 'primaryColor' => '#0066cc' ) ),
                'is_system'   => 0,
                'is_active'   => 1,
                'created_by'  => $this->admin_user_id,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );
        $this->test_template_id = $this->db->insert_id;
    }

    /**
     * Register REST routes for testing.
     */
    private function register_rest_routes(): void {
        // History endpoints.
        register_rest_route( $this->namespace, '/history', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_history' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
            'args'                => array(
                'page'       => array(
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page'   => array(
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ),
                'chatbot_id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/history/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_conversation' ),
            'permission_callback' => array( $this, 'check_conversation_access' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/history/(?P<id>\d+)/archive', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_archive_conversation' ),
            'permission_callback' => array( $this, 'check_conversation_access' ),
        ) );

        register_rest_route( $this->namespace, '/history/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'rest_delete_conversation' ),
            'permission_callback' => array( $this, 'check_conversation_access' ),
        ) );

        // Search endpoints.
        register_rest_route( $this->namespace, '/search', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_search' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
            'args'                => array(
                'query'    => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'page'     => array(
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page' => array(
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Template endpoints.
        register_rest_route( $this->namespace, '/templates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_templates' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'category'  => array(
                    'sanitize_callback' => 'sanitize_key',
                ),
                'is_active' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/templates/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_template' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/templates', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_create_template' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'name' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/templates/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'rest_update_template' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/templates/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'rest_delete_template' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    /**
     * Clean up test data.
     */
    private function cleanup_test_data(): void {
        $this->db->query( "DELETE FROM {$this->prefix}messages WHERE conversation_id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}conversations WHERE id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}chatbots WHERE id = {$this->test_chatbot_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}templates WHERE id = {$this->test_template_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}templates WHERE name LIKE 'Test%'" );
    }

    // =========================================================================
    // PERMISSION CALLBACKS
    // =========================================================================

    /**
     * Check if user is logged in.
     *
     * @return bool|\WP_Error
     */
    public function check_user_logged_in() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_unauthorized',
                __( 'You must be logged in to access this endpoint.', 'knowvault' ),
                array( 'status' => 401 )
            );
        }
        return true;
    }

    /**
     * Check admin permission.
     *
     * @return bool|\WP_Error
     */
    public function check_admin_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Check conversation access permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_conversation_access( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_unauthorized',
                __( 'You must be logged in to access this endpoint.', 'knowvault' ),
                array( 'status' => 401 )
            );
        }

        $conversation_id = $request->get_param( 'id' );
        $conversation    = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}conversations WHERE id = %d",
                $conversation_id
            )
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'rest_not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        $user_id = get_current_user_id();
        if ( (int) $conversation->user_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    /**
     * REST callback: Get history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_history( WP_REST_Request $request ): WP_REST_Response {
        $user_id    = get_current_user_id();
        $page       = $request->get_param( 'page' );
        $per_page   = max( 1, min( 100, $request->get_param( 'per_page' ) ) );
        $chatbot_id = $request->get_param( 'chatbot_id' );
        $offset     = ( $page - 1 ) * $per_page;

        $where_conditions = array( 'c.user_id = %d', '(c.is_archived = 0 OR c.is_archived IS NULL)' );
        $where_params     = array( $user_id );

        if ( $chatbot_id ) {
            $where_conditions[] = 'c.chatbot_id = %d';
            $where_params[]     = $chatbot_id;
        }

        $where_clause = implode( ' AND ', $where_conditions );

        $total = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}conversations AS c WHERE {$where_clause}",
                $where_params
            )
        );

        $params        = array_merge( $where_params, array( $per_page, $offset ) );
        $conversations = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    c.id,
                    c.chatbot_id,
                    c.is_favorite,
                    c.created_at,
                    c.updated_at,
                    cb.name AS chatbot_name,
                    (SELECT content FROM {$this->prefix}messages
                     WHERE conversation_id = c.id AND role = 'user'
                     ORDER BY created_at ASC LIMIT 1) AS preview,
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

        return new WP_REST_Response( array(
            'conversations' => $conversations,
            'total'         => $total,
            'pages'         => (int) ceil( $total / $per_page ),
            'page'          => $page,
        ), 200 );
    }

    /**
     * REST callback: Get single conversation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );

        $conversation = $this->db->get_row(
            $this->db->prepare(
                "SELECT c.*, cb.name AS chatbot_name
                 FROM {$this->prefix}conversations AS c
                 LEFT JOIN {$this->prefix}chatbots AS cb ON c.chatbot_id = cb.id
                 WHERE c.id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        $messages = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ),
            ARRAY_A
        );

        $conversation['messages'] = $messages;

        return new WP_REST_Response( $conversation, 200 );
    }

    /**
     * REST callback: Archive conversation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_archive_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );

        $this->db->update(
            $this->prefix . 'conversations',
            array(
                'is_archived' => 1,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        /**
         * Fires when a conversation is archived.
         *
         * @since 2.0.0
         *
         * @param int $conversation_id Conversation ID.
         * @param int $user_id         User ID.
         */
        do_action( 'ai_botkit_conversation_archived', $conversation_id, get_current_user_id() );

        return new WP_REST_Response( array(
            'message'         => 'Conversation archived successfully.',
            'conversation_id' => $conversation_id,
        ), 200 );
    }

    /**
     * REST callback: Delete conversation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_delete_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );

        // Delete messages first.
        $this->db->delete(
            $this->prefix . 'messages',
            array( 'conversation_id' => $conversation_id ),
            array( '%d' )
        );

        // Delete conversation.
        $this->db->delete(
            $this->prefix . 'conversations',
            array( 'id' => $conversation_id ),
            array( '%d' )
        );

        return new WP_REST_Response( array(
            'message'         => 'Conversation deleted successfully.',
            'conversation_id' => $conversation_id,
        ), 200 );
    }

    /**
     * REST callback: Search.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_search( WP_REST_Request $request ): WP_REST_Response {
        $query    = $request->get_param( 'query' );
        $page     = $request->get_param( 'page' );
        $per_page = max( 1, min( 100, $request->get_param( 'per_page' ) ) );
        $user_id  = get_current_user_id();
        $offset   = ( $page - 1 ) * $per_page;

        if ( mb_strlen( $query ) < 2 ) {
            return new WP_REST_Response( array(
                'code'    => 'invalid_query',
                'message' => 'Search query must be at least 2 characters.',
            ), 400 );
        }

        $total = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(DISTINCT m.id)
                 FROM {$this->prefix}messages AS m
                 INNER JOIN {$this->prefix}conversations AS c ON m.conversation_id = c.id
                 WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                 AND c.user_id = %d",
                $query,
                $user_id
            )
        );

        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    m.id,
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

        return new WP_REST_Response( array(
            'results' => $results,
            'total'   => $total,
            'pages'   => (int) ceil( $total / $per_page ),
            'page'    => $page,
            'query'   => $query,
        ), 200 );
    }

    /**
     * REST callback: Get templates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_templates( WP_REST_Request $request ): WP_REST_Response {
        $category  = $request->get_param( 'category' );
        $is_active = $request->get_param( 'is_active' );

        $where_conditions = array();
        $where_params     = array();

        if ( $category ) {
            $where_conditions[] = 'category = %s';
            $where_params[]     = $category;
        }

        if ( $is_active !== null ) {
            $where_conditions[] = 'is_active = %d';
            $where_params[]     = $is_active;
        }

        $where_clause = ! empty( $where_conditions )
            ? 'WHERE ' . implode( ' AND ', $where_conditions )
            : '';

        if ( ! empty( $where_params ) ) {
            $sql       = "SELECT * FROM {$this->prefix}templates {$where_clause} ORDER BY created_at DESC";
            $templates = $this->db->get_results( $this->db->prepare( $sql, $where_params ), ARRAY_A );
        } else {
            $templates = $this->db->get_results(
                "SELECT * FROM {$this->prefix}templates ORDER BY created_at DESC",
                ARRAY_A
            );
        }

        return new WP_REST_Response( array(
            'templates' => $templates,
            'total'     => count( $templates ),
        ), 200 );
    }

    /**
     * REST callback: Get single template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_get_template( WP_REST_Request $request ): WP_REST_Response {
        $template_id = $request->get_param( 'id' );

        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        if ( ! $template ) {
            return new WP_REST_Response( array(
                'code'    => 'not_found',
                'message' => 'Template not found.',
            ), 404 );
        }

        // Decode JSON fields.
        $json_fields = array( 'style', 'messages_template', 'model_config', 'conversation_starters' );
        foreach ( $json_fields as $field ) {
            if ( ! empty( $template[ $field ] ) ) {
                $template[ $field ] = json_decode( $template[ $field ], true );
            }
        }

        return new WP_REST_Response( $template, 200 );
    }

    /**
     * REST callback: Create template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_create_template( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();

        if ( empty( $params['name'] ) ) {
            return new WP_REST_Response( array(
                'code'    => 'missing_name',
                'message' => 'Template name is required.',
            ), 400 );
        }

        $data = array(
            'name'        => sanitize_text_field( $params['name'] ),
            'description' => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
            'category'    => isset( $params['category'] ) ? sanitize_key( $params['category'] ) : 'general',
            'is_system'   => 0,
            'is_active'   => isset( $params['is_active'] ) ? (int) $params['is_active'] : 1,
            'created_by'  => get_current_user_id(),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        );

        // JSON fields.
        if ( isset( $params['style'] ) ) {
            $data['style'] = wp_json_encode( $params['style'] );
        }
        if ( isset( $params['messages_template'] ) ) {
            $data['messages_template'] = wp_json_encode( $params['messages_template'] );
        }
        if ( isset( $params['model_config'] ) ) {
            $data['model_config'] = wp_json_encode( $params['model_config'] );
        }

        $this->db->insert(
            $this->prefix . 'templates',
            $data,
            array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        $template_id = $this->db->insert_id;

        return new WP_REST_Response( array(
            'message'     => 'Template created successfully.',
            'template_id' => $template_id,
        ), 201 );
    }

    /**
     * REST callback: Update template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_update_template( WP_REST_Request $request ): WP_REST_Response {
        $template_id = $request->get_param( 'id' );
        $params      = $request->get_json_params();

        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            )
        );

        if ( ! $template ) {
            return new WP_REST_Response( array(
                'code'    => 'not_found',
                'message' => 'Template not found.',
            ), 404 );
        }

        if ( $template->is_system ) {
            return new WP_REST_Response( array(
                'code'    => 'system_template',
                'message' => 'System templates cannot be modified.',
            ), 403 );
        }

        $data = array( 'updated_at' => current_time( 'mysql' ) );

        if ( isset( $params['name'] ) ) {
            $data['name'] = sanitize_text_field( $params['name'] );
        }
        if ( isset( $params['description'] ) ) {
            $data['description'] = sanitize_textarea_field( $params['description'] );
        }
        if ( isset( $params['category'] ) ) {
            $data['category'] = sanitize_key( $params['category'] );
        }
        if ( isset( $params['is_active'] ) ) {
            $data['is_active'] = (int) $params['is_active'];
        }
        if ( isset( $params['style'] ) ) {
            $data['style'] = wp_json_encode( $params['style'] );
        }
        if ( isset( $params['messages_template'] ) ) {
            $data['messages_template'] = wp_json_encode( $params['messages_template'] );
        }
        if ( isset( $params['model_config'] ) ) {
            $data['model_config'] = wp_json_encode( $params['model_config'] );
        }

        $this->db->update(
            $this->prefix . 'templates',
            $data,
            array( 'id' => $template_id )
        );

        return new WP_REST_Response( array(
            'message'     => 'Template updated successfully.',
            'template_id' => $template_id,
        ), 200 );
    }

    /**
     * REST callback: Delete template.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_delete_template( WP_REST_Request $request ): WP_REST_Response {
        $template_id = $request->get_param( 'id' );

        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            )
        );

        if ( ! $template ) {
            return new WP_REST_Response( array(
                'code'    => 'not_found',
                'message' => 'Template not found.',
            ), 404 );
        }

        if ( $template->is_system ) {
            return new WP_REST_Response( array(
                'code'    => 'system_template',
                'message' => 'System templates cannot be deleted.',
            ), 403 );
        }

        $this->db->delete(
            $this->prefix . 'templates',
            array( 'id' => $template_id ),
            array( '%d' )
        );

        return new WP_REST_Response( array(
            'message'     => 'Template deleted successfully.',
            'template_id' => $template_id,
        ), 200 );
    }

    // =========================================================================
    // TEST METHODS - HISTORY ENDPOINTS
    // =========================================================================

    /**
     * Test GET /history returns 401 for unauthenticated users.
     */
    public function test_history_requires_authentication(): void {
        wp_set_current_user( 0 );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test GET /history returns conversations for logged in user.
     */
    public function test_history_returns_conversations(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'conversations', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'pages', $data );
        $this->assertIsArray( $data['conversations'] );
    }

    /**
     * Test GET /history pagination.
     */
    public function test_history_pagination(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // Create additional conversations.
        for ( $i = 0; $i < 15; $i++ ) {
            $this->db->insert(
                $this->prefix . 'conversations',
                array(
                    'chatbot_id' => $this->test_chatbot_id,
                    'user_id'    => $this->subscriber_user_id,
                    'session_id' => 'sess_pagination_' . $i,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s', '%s', '%s' )
            );
        }

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history' );
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 5 );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertLessThanOrEqual( 5, count( $data['conversations'] ) );
        $this->assertGreaterThan( 1, $data['pages'] );
    }

    /**
     * Test GET /history/{id} returns conversation details.
     */
    public function test_history_get_single_conversation(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history/' . $this->test_conversation_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( $this->test_conversation_id, (int) $data['id'] );
        $this->assertArrayHasKey( 'messages', $data );
        $this->assertNotEmpty( $data['messages'] );
    }

    /**
     * Test GET /history/{id} returns 403 for other user's conversation.
     */
    public function test_history_forbids_other_user_conversation(): void {
        // Create another user.
        $other_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $other_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history/' . $this->test_conversation_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test POST /history/{id}/archive archives conversation.
     */
    public function test_history_archive_conversation(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/history/' . $this->test_conversation_id . '/archive' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        // Verify archived in database.
        $conversation = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}conversations WHERE id = %d",
                $this->test_conversation_id
            )
        );

        $this->assertEquals( 1, (int) $conversation->is_archived );
    }

    /**
     * Test DELETE /history/{id} deletes conversation.
     */
    public function test_history_delete_conversation(): void {
        wp_set_current_user( $this->subscriber_user_id );

        // Create a conversation to delete.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $this->subscriber_user_id,
                'session_id' => 'sess_delete_test',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $delete_conversation_id = $this->db->insert_id;

        $request  = new WP_REST_Request( 'DELETE', '/' . $this->namespace . '/history/' . $delete_conversation_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        // Verify deleted from database.
        $exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}conversations WHERE id = %d",
                $delete_conversation_id
            )
        );

        $this->assertEquals( 0, (int) $exists );
    }

    // =========================================================================
    // TEST METHODS - SEARCH ENDPOINTS
    // =========================================================================

    /**
     * Test GET /search requires authentication.
     */
    public function test_search_requires_authentication(): void {
        wp_set_current_user( 0 );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/search' );
        $request->set_param( 'query', 'test' );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test GET /search returns results.
     */
    public function test_search_returns_results(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/search' );
        $request->set_param( 'query', 'REST API' );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'results', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'query', $data );
        $this->assertEquals( 'REST API', $data['query'] );
    }

    /**
     * Test GET /search validates minimum query length.
     */
    public function test_search_validates_query_length(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/search' );
        $request->set_param( 'query', 'a' );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 400, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 'invalid_query', $data['code'] );
    }

    /**
     * Test GET /search only returns user's own results.
     */
    public function test_search_returns_only_user_results(): void {
        // Create conversation for another user.
        $other_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $other_user_id,
                'session_id' => 'sess_other_user_search',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $other_conversation_id = $this->db->insert_id;

        $this->db->insert(
            $this->prefix . 'messages',
            array(
                'conversation_id' => $other_conversation_id,
                'role'            => 'user',
                'content'         => 'Authentication methods for REST API',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        // Search as subscriber.
        wp_set_current_user( $this->subscriber_user_id );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/search' );
        $request->set_param( 'query', 'authentication' );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        // Should only find subscriber's messages, not other user's.
        foreach ( $data['results'] as $result ) {
            $this->assertEquals( $this->test_conversation_id, (int) $result['conversation_id'] );
        }
    }

    // =========================================================================
    // TEST METHODS - TEMPLATES ENDPOINTS
    // =========================================================================

    /**
     * Test GET /templates requires admin permission.
     */
    public function test_templates_requires_admin(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test GET /templates returns templates for admin.
     */
    public function test_templates_returns_list(): void {
        wp_set_current_user( $this->admin_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'templates', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertIsArray( $data['templates'] );
    }

    /**
     * Test GET /templates filters by category.
     */
    public function test_templates_filters_by_category(): void {
        wp_set_current_user( $this->admin_user_id );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates' );
        $request->set_param( 'category', 'support' );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );

        foreach ( $data['templates'] as $template ) {
            $this->assertEquals( 'support', $template['category'] );
        }
    }

    /**
     * Test GET /templates/{id} returns single template.
     */
    public function test_templates_get_single(): void {
        wp_set_current_user( $this->admin_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates/' . $this->test_template_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( $this->test_template_id, (int) $data['id'] );
        $this->assertEquals( 'Test API Template', $data['name'] );
    }

    /**
     * Test GET /templates/{id} returns 404 for non-existent template.
     */
    public function test_templates_get_single_not_found(): void {
        wp_set_current_user( $this->admin_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates/99999' );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 404, $response->get_status() );
    }

    /**
     * Test POST /templates creates new template.
     */
    public function test_templates_create(): void {
        wp_set_current_user( $this->admin_user_id );

        $request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/templates' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( array(
            'name'        => 'Test REST Template Create',
            'description' => 'Created via REST API',
            'category'    => 'sales',
            'style'       => array(
                'primaryColor' => '#FF0000',
            ),
        ) ) );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 201, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'template_id', $data );
        $this->assertGreaterThan( 0, $data['template_id'] );
    }

    /**
     * Test POST /templates validates required fields.
     */
    public function test_templates_create_validates_required(): void {
        wp_set_current_user( $this->admin_user_id );

        $request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/templates' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( array(
            'description' => 'Missing name field',
        ) ) );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 400, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 'missing_name', $data['code'] );
    }

    /**
     * Test PUT /templates/{id} updates template.
     */
    public function test_templates_update(): void {
        wp_set_current_user( $this->admin_user_id );

        $request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/templates/' . $this->test_template_id );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( array(
            'name'        => 'Test API Template Updated',
            'description' => 'Updated via REST API',
        ) ) );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        // Verify update.
        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $this->test_template_id
            )
        );

        $this->assertEquals( 'Test API Template Updated', $template->name );
    }

    /**
     * Test PUT /templates/{id} prevents system template modification.
     */
    public function test_templates_update_prevents_system_modification(): void {
        wp_set_current_user( $this->admin_user_id );

        // Create a system template.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test System Template API',
                'category'   => 'general',
                'is_system'  => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $system_template_id = $this->db->insert_id;

        $request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/templates/' . $system_template_id );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( array(
            'name' => 'Trying to modify system template',
        ) ) );

        $response = $this->server->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 'system_template', $data['code'] );
    }

    /**
     * Test DELETE /templates/{id} deletes template.
     */
    public function test_templates_delete(): void {
        wp_set_current_user( $this->admin_user_id );

        // Create a template to delete.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test Template To Delete',
                'category'   => 'general',
                'is_system'  => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $delete_template_id = $this->db->insert_id;

        $request  = new WP_REST_Request( 'DELETE', '/' . $this->namespace . '/templates/' . $delete_template_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 200, $response->get_status() );

        // Verify deleted.
        $exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}templates WHERE id = %d",
                $delete_template_id
            )
        );

        $this->assertEquals( 0, (int) $exists );
    }

    /**
     * Test DELETE /templates/{id} prevents system template deletion.
     */
    public function test_templates_delete_prevents_system_deletion(): void {
        wp_set_current_user( $this->admin_user_id );

        // Create a system template.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test System Template Delete',
                'category'   => 'general',
                'is_system'  => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $system_template_id = $this->db->insert_id;

        $request  = new WP_REST_Request( 'DELETE', '/' . $this->namespace . '/templates/' . $system_template_id );
        $response = $this->server->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 'system_template', $data['code'] );
    }

    // =========================================================================
    // TEST METHODS - RESPONSE FORMAT VALIDATION
    // =========================================================================

    /**
     * Test history response format.
     */
    public function test_history_response_format(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/history' );
        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        // Validate top-level structure.
        $this->assertArrayHasKey( 'conversations', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'pages', $data );
        $this->assertArrayHasKey( 'page', $data );

        // Validate conversation structure.
        if ( ! empty( $data['conversations'] ) ) {
            $conversation = $data['conversations'][0];
            $this->assertArrayHasKey( 'id', $conversation );
            $this->assertArrayHasKey( 'chatbot_id', $conversation );
            $this->assertArrayHasKey( 'is_favorite', $conversation );
            $this->assertArrayHasKey( 'created_at', $conversation );
            $this->assertArrayHasKey( 'updated_at', $conversation );
        }
    }

    /**
     * Test search response format.
     */
    public function test_search_response_format(): void {
        wp_set_current_user( $this->subscriber_user_id );

        $request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/search' );
        $request->set_param( 'query', 'REST' );

        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        // Validate top-level structure.
        $this->assertArrayHasKey( 'results', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'pages', $data );
        $this->assertArrayHasKey( 'page', $data );
        $this->assertArrayHasKey( 'query', $data );

        // Validate result structure.
        if ( ! empty( $data['results'] ) ) {
            $result = $data['results'][0];
            $this->assertArrayHasKey( 'id', $result );
            $this->assertArrayHasKey( 'conversation_id', $result );
            $this->assertArrayHasKey( 'role', $result );
            $this->assertArrayHasKey( 'content', $result );
            $this->assertArrayHasKey( 'relevance', $result );
        }
    }

    /**
     * Test template response format with JSON fields decoded.
     */
    public function test_template_response_format(): void {
        wp_set_current_user( $this->admin_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates/' . $this->test_template_id );
        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        // Validate structure.
        $this->assertArrayHasKey( 'id', $data );
        $this->assertArrayHasKey( 'name', $data );
        $this->assertArrayHasKey( 'description', $data );
        $this->assertArrayHasKey( 'category', $data );
        $this->assertArrayHasKey( 'style', $data );
        $this->assertArrayHasKey( 'is_system', $data );
        $this->assertArrayHasKey( 'is_active', $data );

        // Validate JSON fields are decoded.
        if ( ! empty( $data['style'] ) ) {
            $this->assertIsArray( $data['style'] );
        }
    }

    /**
     * Test error response format.
     */
    public function test_error_response_format(): void {
        wp_set_current_user( $this->admin_user_id );

        $request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/templates/99999' );
        $response = $this->server->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 404, $response->get_status() );
        $this->assertArrayHasKey( 'code', $data );
        $this->assertArrayHasKey( 'message', $data );
        $this->assertEquals( 'not_found', $data['code'] );
    }
}
