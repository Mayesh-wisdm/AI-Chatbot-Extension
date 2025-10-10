<?php
namespace AI_BotKit\Public;

use AI_BotKit\Core\Rate_Limiter;

/**
 * Handles AJAX requests for the chat interface
 */
class Ajax_Handler {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Rate limiter instance
     */
    private $rate_limiter;

    /**
     * Initialize the handler
     * 
     * @param \AI_BotKit\Core\RAG_Engine $rag_engine RAG Engine instance
     */
    public function __construct($rag_engine) {
        $this->rag_engine = $rag_engine;
        
        // Initialize rate limiter with error handling
        try {
            // Make sure the class file is loaded
            require_once dirname(__FILE__, 2) . '/core/class-rate-limiter.php';
            
            if (class_exists('AI_BotKit\Core\Rate_Limiter')) {
                $this->rate_limiter = new \AI_BotKit\Core\Rate_Limiter();
            } else {
                $this->rate_limiter = null;
            }
        } catch (\Exception $e) {
            $this->rate_limiter = null;
        }
        
        $this->register_handlers();
    }

    /**
     * Register AJAX handlers
     */
    private function register_handlers(): void {
        // Chat message handler
        add_action('wp_ajax_ai_botkit_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_ai_botkit_chat_message', [$this, 'handle_chat_message']);

        // Stream response handler
        // add_action('wp_ajax_ai_botkit_stream_response', [$this, 'handle_stream_response']);
        // add_action('wp_ajax_nopriv_ai_botkit_stream_response', [$this, 'handle_stream_response']);

        // Conversation history handler
        add_action('wp_ajax_ai_botkit_get_history', [$this, 'handle_get_history']);
        add_action('wp_ajax_nopriv_ai_botkit_get_history', [$this, 'handle_get_history']);

        // Clear conversation handler
        add_action('wp_ajax_ai_botkit_clear_conversation', [$this, 'handle_clear_conversation']);
        add_action('wp_ajax_nopriv_ai_botkit_clear_conversation', [$this, 'handle_clear_conversation']);

        // Feedback handler
        add_action('wp_ajax_ai_botkit_feedback', [$this, 'handle_feedback']);
        add_action('wp_ajax_nopriv_ai_botkit_feedback', [$this, 'handle_feedback']);
        
        // Diagnostic endpoint
        add_action('wp_ajax_ai_botkit_check_rate_limiter', [$this, 'handle_check_rate_limiter']);
    }

    /**
     * Check if rate limiter has the required method
     * 
     * @return bool True if rate limiter is available
     */
    private function is_rate_limiter_available(): bool {
        return isset($this->rate_limiter) && method_exists($this->rate_limiter, 'check_user_limits');
    }

    /**
     * Handle chat message request
     */
    public function handle_chat_message(): void {
        try {
            // Verify nonce
            $this->verify_nonce('ai_botkit_chat');

            // Check if IP is blocked
            if ($this->is_ip_blocked()) {
                throw new \Exception(__('Access denied. Your IP address has been blocked.', 'ai-botkit-for-lead-generation'));
            }

            // Get request data
            $message = sanitize_textarea_field($_POST['message'] ?? '');
            $conversation_id = sanitize_key($_POST['conversation_id'] ?? '');
            $context = sanitize_text_field($_POST['context'] ?? '');
            $bot_id = sanitize_key($_POST['bot_id'] ?? '');
            if (empty($message)) {
                throw new \Exception(__('Message cannot be empty', 'ai-botkit-for-lead-generation'));
            }

            // Generate response
            if (get_option('ai_botkit_stream_responses', false)) {
                // For streaming responses, start the generation and return response ID
                $response_id = $this->start_streaming_response($message, $conversation_id, $bot_id, $context);
                wp_send_json_success(['response_id' => $response_id]);
            } else {
                // For non-streaming responses, generate complete response
                $response = $this->rag_engine->generate_response($message, $conversation_id, $bot_id, $context);

                do_action( 'ai_botkit_chat_message', $message, $response, $response['metadata'] );

                wp_send_json_success($response);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Handle streaming response request
     */
    public function handle_stream_response(): void {
        try {
            // Verify nonce
            $this->verify_nonce('ai_botkit_chat');

            // Check if IP is blocked
            if ($this->is_ip_blocked()) {
                throw new \Exception(__('Access denied. Your IP address has been blocked.', 'ai-botkit-for-lead-generation'));
            }

            // Get response ID
            $response_id = sanitize_key($_POST['response_id'] ?? '');
            if (empty($response_id)) {
                throw new \Exception(__('Invalid response ID', 'ai-botkit-for-lead-generation'));
            }

            // Get response data from transient
            $response_data = get_transient('ai_botkit_response_' . $response_id);
            if (!$response_data) {
                throw new \Exception(__('Response not found or expired', 'ai-botkit-for-lead-generation'));
            }

            // Return response data
            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Handle conversation history request
     */
    public function handle_get_history(): void {
        try {
            // Verify nonce
            $this->verify_nonce('ai_botkit_chat');

            // Get conversation ID
            $conversation_id = sanitize_key($_POST['conversation_id'] ?? '');
            if (empty($conversation_id)) {
                throw new \Exception(__('Invalid conversation ID', 'ai-botkit-for-lead-generation'));
            }

            // Get conversation history
            $history = $this->get_conversation_history($conversation_id);
            wp_send_json_success(['history' => $history]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Handle clear conversation request
     */
    public function handle_clear_conversation(): void {
        try {
            // Verify nonce
            $this->verify_nonce('ai_botkit_chat');

            // Get conversation ID
            $conversation_id = sanitize_key($_POST['conversation_id'] ?? '');
            if (empty($conversation_id)) {
                throw new \Exception(__('Invalid conversation ID', 'ai-botkit-for-lead-generation'));
            }

            // Clear conversation history
            // $this->clear_conversation_history($conversation_id);
            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Start streaming response generation
     * 
     * @param string $message User message
     * @param string $conversation_id Conversation ID
     * @param int $bot_id Bot ID
     * @param string $context Optional context
     * @return string Response ID
     */
    private function start_streaming_response(string $message, string $conversation_id, int $bot_id, string $context = ''): string {
        // Generate unique response ID
        $response_id = uniqid('resp_');

        // Store initial response data
        set_transient('ai_botkit_response_' . $response_id, [
            'status' => 'processing',
            'content' => '',
            'sources' => [],
        ], HOUR_IN_SECONDS);

        // Start streaming response in background
        $this->rag_engine->stream_response($message, $conversation_id, $bot_id, function($chunk) use ($response_id) {
            $response_data = get_transient('ai_botkit_response_' . $response_id);
            if ($response_data) {
                // Handle both string and array formats for consistency
                if (is_string($chunk)) {
                    $response_data['content'] .= $chunk;
                } elseif (is_array($chunk)) {
                    $response_data['content'] .= $chunk['content'] ?? '';
                    $response_data['sources'] = array_merge(
                        $response_data['sources'] ?? [],
                        $chunk['sources'] ?? []
                    );
                }
                set_transient('ai_botkit_response_' . $response_id, $response_data, HOUR_IN_SECONDS);
            }
        }, [
            'context' => $context,
            'max_tokens' => get_option('ai_botkit_max_tokens', 1000),
            'temperature' => get_option('ai_botkit_temperature', 0.7),
        ]);

        return $response_id;
    }

    /**
     * Get conversation history
     * 
     * @param string $conversation_id Conversation ID
     * @return array Conversation history
     */
    private function get_conversation_history(string $conversation_id): array {
        global $wpdb;

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_messages
            WHERE conversation_id = %s
            ORDER BY created_at ASC",
            $conversation_id
        ));

        return array_map(function($message) {
            return [
                'role' => $message->role,
                'content' => $message->content,
                'metadata' => json_decode($message->metadata, true),
                'timestamp' => strtotime($message->created_at),
            ];
        }, $messages);
    }

    /**
     * Clear conversation history
     * 
     * @param string $conversation_id Conversation ID
     */
    private function clear_conversation_history(string $conversation_id): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_messages',
            ['conversation_id' => $conversation_id],
            ['%s']
        );
    }

    /**
     * Verify nonce
     * 
     * @param string $action Nonce action
     * @throws \Exception If nonce verification fails
     */
    private function verify_nonce(string $action): void {
        if (!check_ajax_referer($action, 'nonce', false)) {
            throw new \Exception(esc_html__('Security check failed', 'ai-botkit-for-lead-generation'));
        }
    }

    /**
     * Handle feedback request
     */
    public function handle_feedback(): void {
        try {
            // Verify nonce
            $this->verify_nonce('ai_botkit_chat');

            // Get request data
            $chat_id = sanitize_key($_POST['chat_id'] ?? '');
            $message = sanitize_textarea_field($_POST['message'] ?? '');
            $feedback = sanitize_text_field($_POST['feedback'] ?? '');
            if ( empty($chat_id) || empty($message) || empty($feedback) ) {
                wp_send_json_error([
                    'message' => __('Invalid request', 'ai-botkit-for-lead-generation'),
                    'code' => 'invalid_request'
                ]);
            }

            if ( 'up' == $feedback ) {
                $feedback = 1;
            } else {
                $feedback = 0;
            }

            // Update feedback
            $this->update_feedback($chat_id, $message, $feedback);

            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * Update feedback
     * 
     * @param string $chat_id Chat ID
     * @param string $message Message
     * @param int $feedback Feedback
     */
    private function update_feedback(string $chat_id, string $message, int $feedback): void {
        global $wpdb;
        // update feedback in metadata
        // get the metadata
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_messages as messages
            JOIN {$wpdb->prefix}ai_botkit_conversations as conversations ON messages.conversation_id = conversations.id
            WHERE conversations.session_id = %s
            AND messages.content = %s",
            $chat_id,
            $message
        ));
        if ( empty($results) ) {
            return;
        }
        $metadata = json_decode($results[0]->metadata, true);
        $metadata['feedback'] = $feedback;
        $conversation_id = $results[0]->id;
        
        $wpdb->update(
            $wpdb->prefix . 'ai_botkit_messages',
            ['metadata' => wp_json_encode($metadata)],
            ['conversation_id' => $conversation_id, 'content' => $message],
            ['%s']
        );
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

    /**
     * Handle rate limiter check request
     */
    public function handle_check_rate_limiter(): void {
        try {
            // Only allow logged-in users with manage_options capability
            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                throw new \Exception(__('Unauthorized access', 'ai-botkit-for-lead-generation'), 403);
            }
            
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
            
            $debug_data = [
                'rate_limiter_available' => $this->is_rate_limiter_available(),
                'user_id' => $user_id
            ];
            
            if ($this->is_rate_limiter_available()) {
                $debug_data['rate_check'] = $this->rate_limiter->check_user_limits($user_id);
                
                if (method_exists($this->rate_limiter, 'get_user_usage_stats')) {
                    $debug_data['usage_stats'] = $this->rate_limiter->get_user_usage_stats($user_id);
                }
                
                if (method_exists($this->rate_limiter, 'get_remaining_limits')) {
                    $debug_data['remaining_limits'] = $this->rate_limiter->get_remaining_limits($user_id);
                }
                
                if (method_exists($this->rate_limiter, 'debug_check_tables')) {
                    $debug_data['table_info'] = $this->rate_limiter->debug_check_tables();
                }
            }
            
            wp_send_json_success($debug_data);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }
} 
