<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\RAG_Engine;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Integration
 * 
 * Provides external access to chatbot functionality through
 * standardized REST API endpoints.
 */
class REST_API {
    /**
     * API namespace
     */
    private const API_NAMESPACE = 'ai-botkit/v1';

    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * User Authentication instance
     */
    private $auth;

    /**
     * Initialize the REST API
     */
    public function __construct(RAG_Engine $rag_engine, User_Authentication $auth) {
        $this->rag_engine = $rag_engine;
        $this->auth = $auth;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register API routes
     */
    public function register_routes(): void {
        // Chat endpoints
        register_rest_route(self::API_NAMESPACE, '/chat/message', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_chat_message'],
            'permission_callback' => [$this, 'check_chat_permission'],
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'conversation_id' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'bot_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'context' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);

        // Conversation endpoints
        register_rest_route(self::API_NAMESPACE, '/conversations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_conversations'],
            'permission_callback' => [$this, 'check_history_permission'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/conversations/(?P<id>[a-zA-Z0-9-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_conversation'],
            'permission_callback' => [$this, 'check_history_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/conversations/(?P<id>[a-zA-Z0-9-]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'delete_conversation'],
            'permission_callback' => [$this, 'check_history_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

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
    }

    /**
     * Handle chat message
     */
    public function handle_chat_message(WP_REST_Request $request): WP_REST_Response {
        try {
            // Check if IP is blocked
            if ($this->is_ip_blocked()) {
                return new WP_REST_Response([
                    'error' => __('Access denied. Your IP address has been blocked.', 'ai-botkit-for-lead-generation')
                ], 403);
            }

            $message = $request->get_param('message');
            $conversation_id = $request->get_param('conversation_id');
            $bot_id = $request->get_param('bot_id');
            $context = $request->get_param('context');

        // Use default settings
        $settings = [
            'max_tokens' => get_option('ai_botkit_max_tokens', 1000),
            'temperature' => get_option('ai_botkit_temperature', 0.7),
            'model' => 'gpt-4-turbo',  // Explicitly use GPT-4 Turbo
        ];

            // Generate response
            $response = $this->rag_engine->generate_response(
                $message,
                $conversation_id,
                $bot_id,
                $context,
                $settings
            );

            return new WP_REST_Response($response, 200);
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversations
     */
    public function get_conversations(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';
        $user_id = get_current_user_id();

        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE user_id = %d 
                ORDER BY updated_at DESC",
                $user_id
            )
        );

        return new WP_REST_Response($conversations, 200);
    }

    /**
     * Get single conversation
     */
    public function get_conversation(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $conversation_id = $request->get_param('id');
        $user_id = get_current_user_id();

        $messages_table = $wpdb->prefix . 'ai_botkit_messages';
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$messages_table} 
                WHERE conversation_id = %s 
                ORDER BY created_at ASC",
                $conversation_id
            )
        );

        if (!$messages) {
            return new WP_REST_Response([
                'error' => 'Conversation not found'
            ], 404);
        }

        return new WP_REST_Response([
            'id' => $conversation_id,
            'messages' => $messages
        ], 200);
    }

    /**
     * Delete conversation
     */
    public function delete_conversation(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $conversation_id = $request->get_param('id');
        $user_id = get_current_user_id();

        $conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
        $messages_table = $wpdb->prefix . 'ai_botkit_messages';

        // Delete conversation and messages
        $wpdb->delete(
            $conversations_table,
            [
                'id' => $conversation_id,
                'user_id' => $user_id
            ],
            ['%s', '%d']
        );

        $wpdb->delete(
            $messages_table,
            ['conversation_id' => $conversation_id],
            ['%s']
        );

        return new WP_REST_Response(null, 204);
    }

    /**
     * Get documents
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
     * Check if the current user's IP address is blocked
     * 
     * @return bool True if IP is blocked, false otherwise
     */
    private function is_ip_blocked(): bool {
        // Get user's IP address
        $user_ip = $_SERVER['REMOTE_ADDR'];
        
        // Get blocked IPs from options
        $blocked_ips_json = get_option('ai_botkit_blocked_ips', '[]');
        $blocked_ips = json_decode($blocked_ips_json, true);
        
        // Check if user's IP is in the blocked list
        return in_array($user_ip, $blocked_ips);
    }
} 