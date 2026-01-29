<?php
/**
 * REST API Integration
 *
 * Provides external access to chatbot functionality through
 * standardized REST API endpoints.
 *
 * @package AI_BotKit\Integration
 * @since   1.0.0
 *
 * Extended in Phase 2 for:
 * - FR-201 to FR-209: Chat History Feature
 */

namespace AI_BotKit\Integration;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Features\Chat_History_Handler;
use AI_BotKit\Features\Search_Handler;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST_API class.
 *
 * Handles all REST API endpoints for the chatbot including
 * chat, conversations, documents, and analytics.
 *
 * @since 1.0.0
 */
class REST_API {
    /**
     * API namespace.
     *
     * @var string
     */
    private const API_NAMESPACE = 'ai-botkit/v1';

    /**
     * RAG Engine instance.
     *
     * @var RAG_Engine
     */
    private $rag_engine;

    /**
     * User Authentication instance.
     *
     * @var User_Authentication
     */
    private $auth;

    /**
     * Chat History Handler instance.
     *
     * @since 2.0.0
     * @var Chat_History_Handler|null
     */
    private $chat_history_handler;

    /**
     * Search Handler instance.
     *
     * @since 2.0.0
     * @var Search_Handler|null
     */
    private $search_handler;

    /**
     * Initialize the REST API.
     *
     * @since 1.0.0
     *
     * @param RAG_Engine          $rag_engine RAG Engine instance.
     * @param User_Authentication $auth       User Authentication instance.
     */
    public function __construct( RAG_Engine $rag_engine, User_Authentication $auth ) {
        $this->rag_engine = $rag_engine;
        $this->auth       = $auth;

        // Initialize chat history handler for Phase 2 features.
        try {
            require_once dirname( __FILE__, 2 ) . '/features/class-chat-history-handler.php';

            if ( class_exists( 'AI_BotKit\Features\Chat_History_Handler' ) ) {
                $this->chat_history_handler = new Chat_History_Handler();
            } else {
                $this->chat_history_handler = null;
            }
        } catch ( \Exception $e ) {
            $this->chat_history_handler = null;
        }

        // Initialize search handler for Phase 2 search features.
        try {
            require_once dirname( __FILE__, 2 ) . '/features/class-search-handler.php';

            if ( class_exists( 'AI_BotKit\Features\Search_Handler' ) ) {
                $this->search_handler = new Search_Handler();
            } else {
                $this->search_handler = null;
            }
        } catch ( \Exception $e ) {
            $this->search_handler = null;
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register API routes.
     *
     * @since 1.0.0
     */
    public function register_routes(): void {
        // Chat endpoints.
        register_rest_route(
            self::API_NAMESPACE,
            '/chat/message',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_chat_message' ),
                'permission_callback' => array( $this, 'check_chat_permission' ),
                'args'                => array(
                    'message'         => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'conversation_id' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'bot_id'          => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'context'         => array(
                        'required' => false,
                        'type'     => 'string',
                    ),
                ),
            )
        );

        // Conversation endpoints.
        register_rest_route(
            self::API_NAMESPACE,
            '/conversations',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_conversations' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'page'        => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page'    => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ),
                    'chatbot_id'  => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'is_favorite' => array(
                        'required' => false,
                        'type'     => 'boolean',
                    ),
                    'start_date'  => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'end_date'    => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/conversations/(?P<id>[\d]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_conversation' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/conversations/(?P<id>[\d]+)',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_conversation' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // =========================================================
        // Phase 2: Chat History REST endpoints (FR-201 to FR-209)
        // =========================================================

        // Toggle favorite.
        register_rest_route(
            self::API_NAMESPACE,
            '/conversations/(?P<id>[\d]+)/favorite',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'toggle_conversation_favorite' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Archive conversation.
        register_rest_route(
            self::API_NAMESPACE,
            '/conversations/(?P<id>[\d]+)/archive',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'archive_conversation' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Unarchive conversation.
        register_rest_route(
            self::API_NAMESPACE,
            '/conversations/(?P<id>[\d]+)/unarchive',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'unarchive_conversation' ),
                'permission_callback' => array( $this, 'check_history_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // =========================================================
        // Phase 2: Search REST endpoint (FR-210 to FR-219)
        // =========================================================
        register_rest_route(
            self::API_NAMESPACE,
            '/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_messages' ),
                'permission_callback' => array( $this, 'check_search_permission' ),
                'args'                => array(
                    'q'          => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function( $param ) {
                            return mb_strlen( $param ) >= 2;
                        },
                    ),
                    'page'       => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page'   => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 20,
                        'sanitize_callback' => 'absint',
                    ),
                    'chatbot_id' => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'user_id'    => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'description'       => 'Admin only: filter by user ID',
                    ),
                    'start_date' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'end_date'   => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'role'       => array(
                        'required'          => false,
                        'type'              => 'string',
                        'enum'              => array( 'user', 'assistant' ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // Document endpoints
        register_rest_route(self::API_NAMESPACE, '/documents', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_documents'],
            'permission_callback' => [$this, 'check_documents_permission'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/documents', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_document'],
            'permission_callback' => [$this, 'check_documents_permission'],
            'args' => [
                'title' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['text', 'url', 'file'],
                ],
            ],
        ]);

        // Analytics endpoints
        register_rest_route(self::API_NAMESPACE, '/analytics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_analytics_permission'],
            'args' => [
                'start_date' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'date',
                ],
                'end_date' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'date',
                ],
            ],
        ]);

        // =========================================================
        // Phase 2: Chat Transcripts Export REST endpoints (FR-240 to FR-249)
        // =========================================================

        // GET /export/{conversation_id}/pdf - Download PDF transcript.
        register_rest_route(
            self::API_NAMESPACE,
            '/export/(?P<conversation_id>[\d]+)/pdf',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_conversation_pdf' ),
                'permission_callback' => array( $this, 'check_export_permission' ),
                'args'                => array(
                    'conversation_id'  => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'include_metadata' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => true,
                    ),
                    'include_branding' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => true,
                    ),
                    'paper_size' => array(
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => array( 'a4', 'letter' ),
                        'default'  => 'a4',
                    ),
                ),
            )
        );

        // POST /export/batch - Start batch export.
        register_rest_route(
            self::API_NAMESPACE,
            '/export/batch',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'start_batch_export' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'conversation_ids' => array(
                        'required'          => true,
                        'type'              => 'array',
                        'items'             => array( 'type' => 'integer' ),
                        'sanitize_callback' => function( $ids ) {
                            return array_map( 'absint', (array) $ids );
                        },
                    ),
                    'include_metadata' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => true,
                    ),
                    'include_branding' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => true,
                    ),
                    'paper_size' => array(
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => array( 'a4', 'letter' ),
                        'default'  => 'a4',
                    ),
                ),
            )
        );

        // GET /export/batch/{batch_id}/status - Check batch export status.
        register_rest_route(
            self::API_NAMESPACE,
            '/export/batch/(?P<batch_id>[a-zA-Z0-9-]+)/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_batch_export_status' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
                'args'                => array(
                    'batch_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    /**
     * Handle chat message.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function handle_chat_message( WP_REST_Request $request ): WP_REST_Response {
        try {
            // Check if IP is blocked.
            if ( $this->is_ip_blocked() ) {
                return new WP_REST_Response(
                    array( 'error' => __( 'Access denied. Your IP address has been blocked.', 'knowvault' ) ),
                    403
                );
            }

            $message         = $request->get_param( 'message' );
            $conversation_id = $request->get_param( 'conversation_id' );
            $bot_id          = $request->get_param( 'bot_id' );
            $context         = $request->get_param( 'context' );

            // Use default settings.
            $settings = array(
                'max_tokens'  => get_option( 'ai_botkit_max_tokens', 1000 ),
                'temperature' => get_option( 'ai_botkit_temperature', 0.7 ),
                'model'       => 'gpt-4-turbo',
            );

            // Generate response.
            $response = $this->rag_engine->generate_response(
                $message,
                $conversation_id,
                $bot_id,
                $context,
                $settings
            );

            return new WP_REST_Response( $response, 200 );
        } catch ( \Exception $e ) {
            return new WP_REST_Response(
                array( 'error' => $e->getMessage() ),
                500
            );
        }
    }

    /**
     * Get documents.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_documents(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_documents';

        $documents = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC"
        );

        return new WP_REST_Response($documents, 200);
    }

    /**
     * Create document
     */
    public function create_document(WP_REST_Request $request): WP_REST_Response {
        try {
            $title = $request->get_param('title');
            $content = $request->get_param('content');
            $type = $request->get_param('type');

            $document = $this->rag_engine->process_document(
                $content,
                $type,
                ['title' => $title]
            );

            return new WP_REST_Response($document, 201);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics data
     */
    public function get_analytics(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'ai_botkit_messages';
        $start_date = $request->get_param('start_date') ?: gmdate('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: gmdate('Y-m-d');

        // Get message counts by date
        $daily_messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM {$messages_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $start_date,
                $end_date
            )
        );

        // Get user engagement
        $user_engagement = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, COUNT(*) as message_count 
                FROM {$messages_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY user_id 
                ORDER BY message_count DESC 
                LIMIT 10",
                $start_date,
                $end_date
            )
        );

        return new WP_REST_Response([
            'daily_messages' => $daily_messages,
            'user_engagement' => $user_engagement,
            'total_messages' => array_sum(array_column($daily_messages, 'count')),
            'active_users' => count($user_engagement),
        ], 200);
    }

    /**
     * Check chat permission
     */
    public function check_chat_permission(WP_REST_Request $request): bool {
        //by-passing user auth for now
        return true;
        
        // return apply_filters(
        //     'ai_botkit_can_use_chat',
        //     true,
        //     get_current_user_id()
        // );
    }

    /**
     * Check history permission
     */
    public function check_history_permission(WP_REST_Request $request): bool {
        return apply_filters(
            'ai_botkit_can_view_history',
            true,
            get_current_user_id()
        );
    }

    /**
     * Check documents permission
     */
    public function check_documents_permission(WP_REST_Request $request): bool {
        return apply_filters(
            'ai_botkit_can_manage_documents',
            true,
            get_current_user_id()
        );
    }

    /**
     * Check analytics permission
     */
    public function check_analytics_permission(WP_REST_Request $request): bool {
        return current_user_can('view_ai_botkit_analytics');
    }

    /**
     * Check search permission.
     *
     * Users must be logged in to search.
     *
     * Implements: FR-213 (User Personal Search)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return bool True if user can search.
     */
    public function check_search_permission( WP_REST_Request $request ): bool {
        // Must be logged in to search.
        if ( ! is_user_logged_in() ) {
            return false;
        }

        return apply_filters(
            'ai_botkit_can_search',
            true,
            get_current_user_id()
        );
    }

    /**
     * Check if the current user's IP address is blocked.
     *
     * @since 1.0.0
     *
     * @return bool True if IP is blocked, false otherwise.
     */
    private function is_ip_blocked(): bool {
        // Get user's IP address.
        $user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        // Get blocked IPs from options.
        $blocked_ips_json = get_option( 'ai_botkit_blocked_ips', '[]' );
        $blocked_ips      = json_decode( $blocked_ips_json, true );

        // Check if user's IP is in the blocked list.
        return in_array( $user_ip, $blocked_ips, true );
    }

    // =========================================================
    // Phase 2: Chat History REST Methods (FR-201 to FR-209)
    // =========================================================

    /**
     * Check if chat history handler is available.
     *
     * @since 2.0.0
     *
     * @return bool True if chat history handler is available.
     */
    private function is_chat_history_available(): bool {
        return isset( $this->chat_history_handler ) && $this->chat_history_handler !== null;
    }

    /**
     * Get conversations with pagination and filters.
     *
     * Enhanced for Phase 2 with filtering support.
     *
     * Implements: FR-201 (View Chat History), FR-207 (Filter by Date)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_conversations( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to view conversations.', 'knowvault' ) ),
                401
            );
        }

        if ( ! $this->is_chat_history_available() ) {
            // Fallback to basic implementation.
            return $this->get_conversations_legacy( $request );
        }

        $page        = $request->get_param( 'page' ) ?: 1;
        $per_page    = $request->get_param( 'per_page' ) ?: 10;
        $chatbot_id  = $request->get_param( 'chatbot_id' );
        $is_favorite = $request->get_param( 'is_favorite' );
        $start_date  = $request->get_param( 'start_date' );
        $end_date    = $request->get_param( 'end_date' );

        // Use filter method if any filters are provided.
        if ( $start_date || $end_date || $is_favorite !== null ) {
            $result = $this->chat_history_handler->filter_conversations(
                $user_id,
                $start_date ?: '',
                $end_date ?: '',
                $chatbot_id,
                $is_favorite,
                $page,
                $per_page
            );
        } else {
            $result = $this->chat_history_handler->get_user_conversations(
                $user_id,
                $chatbot_id,
                $page,
                $per_page
            );
        }

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Legacy get conversations method for backward compatibility.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    private function get_conversations_legacy( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';
        $user_id    = get_current_user_id();

        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}
                WHERE user_id = %d
                ORDER BY updated_at DESC",
                $user_id
            )
        );

        return new WP_REST_Response( $conversations, 200 );
    }

    /**
     * Get single conversation with messages.
     *
     * Enhanced for Phase 2 with ownership verification.
     *
     * Implements: FR-203 (Resume Conversation)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to view this conversation.', 'knowvault' ) ),
                401
            );
        }

        if ( $this->is_chat_history_available() ) {
            $result = $this->chat_history_handler->switch_conversation( $conversation_id, $user_id );

            if ( is_wp_error( $result ) ) {
                $status = $result->get_error_data()['status'] ?? 500;
                return new WP_REST_Response(
                    array( 'error' => $result->get_error_message() ),
                    $status
                );
            }

            return new WP_REST_Response( $result, 200 );
        }

        // Fallback to basic implementation.
        return $this->get_conversation_legacy( $request );
    }

    /**
     * Legacy get conversation method for backward compatibility.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    private function get_conversation_legacy( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        $messages_table = $wpdb->prefix . 'ai_botkit_messages';
        $messages       = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$messages_table}
                WHERE conversation_id = %d
                ORDER BY created_at ASC",
                $conversation_id
            )
        );

        if ( ! $messages ) {
            return new WP_REST_Response(
                array( 'error' => 'Conversation not found' ),
                404
            );
        }

        return new WP_REST_Response(
            array(
                'id'       => $conversation_id,
                'messages' => $messages,
            ),
            200
        );
    }

    /**
     * Delete conversation.
     *
     * Enhanced for Phase 2 with ownership verification.
     *
     * Implements: FR-205 (Delete Conversation)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function delete_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to delete this conversation.', 'knowvault' ) ),
                401
            );
        }

        if ( $this->is_chat_history_available() ) {
            $result = $this->chat_history_handler->delete_conversation( $conversation_id, $user_id );

            if ( is_wp_error( $result ) ) {
                $status = $result->get_error_data()['status'] ?? 500;
                return new WP_REST_Response(
                    array( 'error' => $result->get_error_message() ),
                    $status
                );
            }

            return new WP_REST_Response( null, 204 );
        }

        // Fallback to basic implementation.
        return $this->delete_conversation_legacy( $request );
    }

    /**
     * Legacy delete conversation method for backward compatibility.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    private function delete_conversation_legacy( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        $conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
        $messages_table      = $wpdb->prefix . 'ai_botkit_messages';

        // Delete conversation and messages.
        $wpdb->delete(
            $conversations_table,
            array(
                'id'      => $conversation_id,
                'user_id' => $user_id,
            ),
            array( '%d', '%d' )
        );

        $wpdb->delete(
            $messages_table,
            array( 'conversation_id' => $conversation_id ),
            array( '%d' )
        );

        return new WP_REST_Response( null, 204 );
    }

    /**
     * Toggle conversation favorite status.
     *
     * Implements: FR-206 (Mark Favorite)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function toggle_conversation_favorite( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to modify this conversation.', 'knowvault' ) ),
                401
            );
        }

        if ( ! $this->is_chat_history_available() ) {
            return new WP_REST_Response(
                array( 'error' => __( 'Chat history feature is not available.', 'knowvault' ) ),
                500
            );
        }

        $result = $this->chat_history_handler->toggle_favorite( $conversation_id, $user_id );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 500;
            return new WP_REST_Response(
                array( 'error' => $result->get_error_message() ),
                $status
            );
        }

        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Archive a conversation.
     *
     * Implements: FR-208 (Archive Conversation)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function archive_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to archive this conversation.', 'knowvault' ) ),
                401
            );
        }

        if ( ! $this->is_chat_history_available() ) {
            return new WP_REST_Response(
                array( 'error' => __( 'Chat history feature is not available.', 'knowvault' ) ),
                500
            );
        }

        $result = $this->chat_history_handler->archive_conversation( $conversation_id, $user_id );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 500;
            return new WP_REST_Response(
                array( 'error' => $result->get_error_message() ),
                $status
            );
        }

        return new WP_REST_Response(
            array( 'message' => __( 'Conversation archived successfully.', 'knowvault' ) ),
            200
        );
    }

    /**
     * Unarchive a conversation.
     *
     * Implements: FR-209 (Restore Archived)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function unarchive_conversation( WP_REST_Request $request ): WP_REST_Response {
        $conversation_id = $request->get_param( 'id' );
        $user_id         = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to restore this conversation.', 'knowvault' ) ),
                401
            );
        }

        if ( ! $this->is_chat_history_available() ) {
            return new WP_REST_Response(
                array( 'error' => __( 'Chat history feature is not available.', 'knowvault' ) ),
                500
            );
        }

        $result = $this->chat_history_handler->unarchive_conversation( $conversation_id, $user_id );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 500;
            return new WP_REST_Response(
                array( 'error' => $result->get_error_message() ),
                $status
            );
        }

        return new WP_REST_Response(
            array( 'message' => __( 'Conversation restored successfully.', 'knowvault' ) ),
            200
        );
    }

    // =========================================================
    // Phase 2: Search Functionality REST Methods (FR-210 to FR-219)
    // =========================================================

    /**
     * Check if search handler is available.
     *
     * @since 2.0.0
     *
     * @return bool True if search handler is available.
     */
    private function is_search_handler_available(): bool {
        return isset( $this->search_handler ) && $this->search_handler !== null;
    }

    /**
     * Search messages via REST API.
     *
     * GET /wp-json/ai-botkit/v1/search?q={term}&filters={...}
     *
     * Implements: FR-211 (Full-Text Search on Messages)
     * Implements: FR-212 (Admin Global Search)
     * Implements: FR-213 (User Personal Search)
     * Implements: FR-214 (Search Filters)
     * Implements: FR-215 (Search Results Display)
     * Implements: FR-218 (Search Pagination)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function search_messages( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_REST_Response(
                array( 'error' => __( 'You must be logged in to search conversations.', 'knowvault' ) ),
                401
            );
        }

        if ( ! $this->is_search_handler_available() ) {
            return new WP_REST_Response(
                array( 'error' => __( 'Search feature is not available.', 'knowvault' ) ),
                500
            );
        }

        // Get query parameters.
        $query    = $request->get_param( 'q' );
        $page     = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 20;

        // Build filters.
        $filters = array();

        // Check if user can search all (admin privilege).
        $can_search_all = $this->search_handler->can_search_all( $user_id );

        // User filter: only admin can filter by other users.
        $requested_user_id = $request->get_param( 'user_id' );
        if ( $requested_user_id && $can_search_all ) {
            $filters['user_id'] = $requested_user_id;
        } elseif ( ! $can_search_all ) {
            // Non-admins can only search their own conversations.
            $filters['user_id'] = $user_id;
        }

        // Optional filters available to all.
        $chatbot_id = $request->get_param( 'chatbot_id' );
        if ( $chatbot_id ) {
            $filters['chatbot_id'] = $chatbot_id;
        }

        $start_date = $request->get_param( 'start_date' );
        if ( $start_date ) {
            $filters['start_date'] = $start_date;
        }

        $end_date = $request->get_param( 'end_date' );
        if ( $end_date ) {
            $filters['end_date'] = $end_date;
        }

        $role = $request->get_param( 'role' );
        if ( $role ) {
            $filters['role'] = $role;
        }

        // Perform search.
        $results = $this->search_handler->search( $query, $filters, $page, $per_page );

        // Log admin searches for audit.
        if ( $can_search_all && ( ! isset( $filters['user_id'] ) || $filters['user_id'] !== $user_id ) ) {
            /**
             * Fires when an admin performs a search via REST API.
             *
             * @since 2.0.0
             *
             * @param string $query    Search query.
             * @param array  $filters  Search filters.
             * @param int    $user_id  Admin user ID.
             */
            do_action( 'ai_botkit_admin_search_rest', $query, $filters, $user_id );
        }

        return new WP_REST_Response( $results, 200 );
    }

    // =========================================================
    // Phase 2: Chat Transcripts Export REST Handlers (FR-240 to FR-249)
    // =========================================================

    /**
     * Check if user has permission to export a conversation.
     *
     * Admin can export any conversation.
     * Users can only export their own conversations.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if permitted.
     */
    public function check_export_permission( WP_REST_Request $request ) {
        // Must be logged in.
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in to export conversations.', 'knowvault' ),
                array( 'status' => 401 )
            );
        }

        // Admin can export any conversation.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Check ownership for regular users.
        $conversation_id = $request->get_param( 'conversation_id' );
        $user_id         = get_current_user_id();

        global $wpdb;
        $conversations_table = $wpdb->prefix . 'ai_botkit_conversations';

        $owner_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$conversations_table} WHERE id = %d",
                $conversation_id
            )
        );

        if ( (int) $owner_id !== $user_id ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to export this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check if user has admin permission.
     *
     * @since 2.0.0
     *
     * @return bool|WP_Error True if permitted.
     */
    public function check_admin_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Export conversation to PDF via REST API.
     *
     * Implements: FR-243 (REST API Export)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function export_conversation_pdf( WP_REST_Request $request ) {
        require_once dirname( __FILE__, 2 ) . '/features/class-export-handler.php';

        if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
            return new WP_Error(
                'export_handler_missing',
                __( 'Export handler not available.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        $export_handler = new \AI_BotKit\Features\Export_Handler();

        // Check if dompdf is available.
        if ( ! $export_handler->is_dompdf_available() ) {
            return new WP_Error(
                'dompdf_not_available',
                __( 'PDF export requires the dompdf library. Please contact the site administrator.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        $conversation_id = $request->get_param( 'conversation_id' );
        $options         = array(
            'include_metadata' => $request->get_param( 'include_metadata' ),
            'include_branding' => $request->get_param( 'include_branding' ),
            'paper_size'       => $request->get_param( 'paper_size' ),
        );

        // Stream PDF to browser.
        $export_handler->stream_pdf( $conversation_id, $options );

        // Note: stream_pdf exits after sending file, so we won't reach here.
        // This return is just for IDE completion.
        return new WP_REST_Response( null, 200 );
    }

    /**
     * Start batch export via REST API.
     *
     * Implements: FR-246 (Batch Export)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function start_batch_export( WP_REST_Request $request ) {
        require_once dirname( __FILE__, 2 ) . '/features/class-export-handler.php';

        if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
            return new WP_Error(
                'export_handler_missing',
                __( 'Export handler not available.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        $export_handler = new \AI_BotKit\Features\Export_Handler();

        // Check if dompdf is available.
        if ( ! $export_handler->is_dompdf_available() ) {
            return new WP_Error(
                'dompdf_not_available',
                __( 'PDF export requires the dompdf library. Please contact the site administrator.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        $conversation_ids = $request->get_param( 'conversation_ids' );
        $options          = array(
            'include_metadata' => $request->get_param( 'include_metadata' ),
            'include_branding' => $request->get_param( 'include_branding' ),
            'paper_size'       => $request->get_param( 'paper_size' ),
        );

        if ( empty( $conversation_ids ) ) {
            return new WP_Error(
                'no_conversations',
                __( 'No conversations selected for export.', 'knowvault' ),
                array( 'status' => 400 )
            );
        }

        // Schedule batch export.
        $batch_id = $export_handler->schedule_export( $conversation_ids, $options );

        // For small batches, process synchronously.
        if ( count( $conversation_ids ) <= 5 ) {
            $result = $export_handler->process_batch_export( $batch_id );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $status = $export_handler->get_export_status( $batch_id );

            return new WP_REST_Response(
                array(
                    'batch_id'     => $batch_id,
                    'status'       => 'completed',
                    'download_url' => $status['download_url'] ?? null,
                    'message'      => sprintf(
                        /* translators: %d: number of conversations */
                        __( '%d conversation(s) exported successfully.', 'knowvault' ),
                        count( $conversation_ids )
                    ),
                ),
                200
            );
        }

        // For larger batches, process asynchronously.
        if ( ! wp_next_scheduled( 'ai_botkit_process_batch_export', array( $batch_id ) ) ) {
            wp_schedule_single_event( time(), 'ai_botkit_process_batch_export', array( $batch_id ) );
        }

        return new WP_REST_Response(
            array(
                'batch_id' => $batch_id,
                'status'   => 'processing',
                'message'  => sprintf(
                    /* translators: %d: number of conversations */
                    __( 'Batch export of %d conversations has been scheduled.', 'knowvault' ),
                    count( $conversation_ids )
                ),
            ),
            202
        );
    }

    /**
     * Get batch export status via REST API.
     *
     * Implements: FR-245 (Export Progress Indicator)
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_batch_export_status( WP_REST_Request $request ) {
        require_once dirname( __FILE__, 2 ) . '/features/class-export-handler.php';

        if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
            return new WP_Error(
                'export_handler_missing',
                __( 'Export handler not available.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        $export_handler = new \AI_BotKit\Features\Export_Handler();
        $batch_id       = $request->get_param( 'batch_id' );

        $status = $export_handler->get_export_status( $batch_id );

        if ( is_wp_error( $status ) ) {
            return $status;
        }

        return new WP_REST_Response( $status, 200 );
    }
} 