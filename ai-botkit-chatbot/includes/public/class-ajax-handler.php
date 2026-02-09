<?php
/**
 * Handles AJAX requests for the chat interface.
 *
 * @package AI_BotKit\Public
 * @since   1.0.0
 *
 * Extended in Phase 2 for:
 * - FR-201 to FR-209: Chat History Feature
 * - FR-250 to FR-259: LMS/WooCommerce Suggestions
 */

namespace AI_BotKit\Public;

use AI_BotKit\Core\Rate_Limiter;
use AI_BotKit\Features\Chat_History_Handler;
use AI_BotKit\Features\Media_Handler;
use AI_BotKit\Features\Search_Handler;
use AI_BotKit\Features\Recommendation_Engine;
use AI_BotKit\Features\Browsing_Tracker;

/**
 * Ajax_Handler class.
 *
 * Handles all public AJAX requests including chat messages,
 * conversation history, and feedback.
 *
 * @since 1.0.0
 */
class Ajax_Handler {
	/**
	 * RAG Engine instance.
	 *
	 * @var \AI_BotKit\Core\RAG_Engine
	 */
	private $rag_engine;

	/**
	 * Rate limiter instance.
	 *
	 * @var Rate_Limiter|null
	 */
	private $rate_limiter;

	/**
	 * Chat history handler instance.
	 *
	 * @since 2.0.0
	 * @var Chat_History_Handler|null
	 */
	private $chat_history_handler;

	/**
	 * Media handler instance.
	 *
	 * @since 2.0.0
	 * @var Media_Handler|null
	 */
	private $media_handler;

	/**
	 * Search handler instance.
	 *
	 * @since 2.0.0
	 * @var Search_Handler|null
	 */
	private $search_handler;

	/**
	 * Recommendation engine instance.
	 *
	 * @since 2.0.0
	 * @var Recommendation_Engine|null
	 */
	private $recommendation_engine;

	/**
	 * Browsing tracker instance.
	 *
	 * @since 2.0.0
	 * @var Browsing_Tracker|null
	 */
	private $browsing_tracker;

	/**
	 * Initialize the handler.
	 *
	 * @since 1.0.0
	 *
	 * @param \AI_BotKit\Core\RAG_Engine $rag_engine RAG Engine instance.
	 */
	public function __construct( $rag_engine ) {
		$this->rag_engine = $rag_engine;

		// Initialize rate limiter with error handling.
		try {
			// Make sure the class file is loaded.
			require_once dirname( __DIR__, 1 ) . '/core/class-rate-limiter.php';

			if ( class_exists( 'AI_BotKit\Core\Rate_Limiter' ) ) {
				$this->rate_limiter = new \AI_BotKit\Core\Rate_Limiter();
			} else {
				$this->rate_limiter = null;
			}
		} catch ( \Exception $e ) {
			$this->rate_limiter = null;
		}

		// Initialize chat history handler for Phase 2 features.
		try {
			require_once dirname( __DIR__, 1 ) . '/features/class-chat-history-handler.php';

			if ( class_exists( 'AI_BotKit\Features\Chat_History_Handler' ) ) {
				$this->chat_history_handler = new Chat_History_Handler();
			} else {
				$this->chat_history_handler = null;
			}
		} catch ( \Exception $e ) {
			$this->chat_history_handler = null;
		}

		// Initialize media handler for Phase 2 rich media features.
		try {
			require_once dirname( __DIR__, 1 ) . '/features/class-media-handler.php';

			if ( class_exists( 'AI_BotKit\Features\Media_Handler' ) ) {
				$this->media_handler = new Media_Handler();
			} else {
				$this->media_handler = null;
			}
		} catch ( \Exception $e ) {
			$this->media_handler = null;
		}

		// Initialize search handler for Phase 2 search features.
		try {
			require_once dirname( __DIR__, 1 ) . '/features/class-search-handler.php';

			if ( class_exists( 'AI_BotKit\Features\Search_Handler' ) ) {
				$this->search_handler = new Search_Handler();
			} else {
				$this->search_handler = null;
			}
		} catch ( \Exception $e ) {
			$this->search_handler = null;
		}

		// Initialize browsing tracker for Phase 2 recommendation features (FR-252).
		try {
			require_once dirname( __DIR__, 1 ) . '/features/class-browsing-tracker.php';

			if ( class_exists( 'AI_BotKit\Features\Browsing_Tracker' ) ) {
				$this->browsing_tracker = new Browsing_Tracker();
			} else {
				$this->browsing_tracker = null;
			}
		} catch ( \Exception $e ) {
			$this->browsing_tracker = null;
		}

		// Initialize recommendation engine for Phase 2 suggestion features (FR-250 to FR-259).
		try {
			require_once dirname( __DIR__, 1 ) . '/features/class-recommendation-engine.php';

			if ( class_exists( 'AI_BotKit\Features\Recommendation_Engine' ) ) {
				$this->recommendation_engine = new Recommendation_Engine(
					null, // Cache manager will be set if available.
					$this->browsing_tracker
				);
			} else {
				$this->recommendation_engine = null;
			}
		} catch ( \Exception $e ) {
			$this->recommendation_engine = null;
		}

		$this->register_handlers();
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 */
	private function register_handlers(): void {
		// Chat message handler.
		add_action( 'wp_ajax_ai_botkit_chat_message', array( $this, 'handle_chat_message' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_chat_message', array( $this, 'handle_chat_message' ) );

		// Stream response handler.
		// add_action('wp_ajax_ai_botkit_stream_response', [$this, 'handle_stream_response']);
		// add_action('wp_ajax_nopriv_ai_botkit_stream_response', [$this, 'handle_stream_response']);

		// Conversation history handler.
		add_action( 'wp_ajax_ai_botkit_get_history', array( $this, 'handle_get_history' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_get_history', array( $this, 'handle_get_history' ) );

		// Clear conversation handler.
		add_action( 'wp_ajax_ai_botkit_clear_conversation', array( $this, 'handle_clear_conversation' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_clear_conversation', array( $this, 'handle_clear_conversation' ) );

		// Feedback handler.
		add_action( 'wp_ajax_ai_botkit_feedback', array( $this, 'handle_feedback' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_feedback', array( $this, 'handle_feedback' ) );

		// Diagnostic endpoint.
		add_action( 'wp_ajax_ai_botkit_check_rate_limiter', array( $this, 'handle_check_rate_limiter' ) );

		// =========================================================
		// Phase 2: Chat History AJAX handlers (FR-201 to FR-209)
		// Only available for logged-in users.
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_list_conversations', array( $this, 'handle_list_conversations' ) );
		add_action( 'wp_ajax_ai_botkit_switch_conversation', array( $this, 'handle_switch_conversation' ) );
		add_action( 'wp_ajax_ai_botkit_delete_conversation', array( $this, 'handle_delete_conversation' ) );
		add_action( 'wp_ajax_ai_botkit_toggle_favorite', array( $this, 'handle_toggle_favorite' ) );
		add_action( 'wp_ajax_ai_botkit_filter_history', array( $this, 'handle_filter_history' ) );
		add_action( 'wp_ajax_ai_botkit_archive_conversation', array( $this, 'handle_archive_conversation' ) );
		add_action( 'wp_ajax_ai_botkit_unarchive_conversation', array( $this, 'handle_unarchive_conversation' ) );

		// =========================================================
		// Phase 2: Rich Media Support AJAX handlers (FR-220 to FR-229)
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_upload_chat_media', array( $this, 'handle_upload_chat_media' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_upload_chat_media', array( $this, 'handle_upload_chat_media' ) );
		add_action( 'wp_ajax_ai_botkit_get_link_preview', array( $this, 'handle_get_link_preview' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_get_link_preview', array( $this, 'handle_get_link_preview' ) );
		add_action( 'wp_ajax_ai_botkit_delete_media', array( $this, 'handle_delete_media' ) );
		add_action( 'wp_ajax_ai_botkit_download_media', array( $this, 'handle_download_media' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_download_media', array( $this, 'handle_download_media' ) );
		add_action( 'wp_ajax_ai_botkit_process_video_url', array( $this, 'handle_process_video_url' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_process_video_url', array( $this, 'handle_process_video_url' ) );

		// =========================================================
		// Phase 2: Search Functionality AJAX handlers (FR-210 to FR-219)
		// User search - logged-in users only, searches own conversations.
		// Admin search - admins only, searches all conversations.
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_search_messages', array( $this, 'handle_search_messages' ) );
		add_action( 'wp_ajax_ai_botkit_search_admin', array( $this, 'handle_search_admin' ) );
		add_action( 'wp_ajax_ai_botkit_search_suggestions', array( $this, 'handle_search_suggestions' ) );

		// =========================================================
		// Phase 2: Chat Transcripts Export AJAX handlers (FR-240 to FR-249)
		// User export - logged-in users can export their own conversations.
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_export_my_pdf', array( $this, 'handle_export_my_pdf' ) );

		// =========================================================
		// Phase 2: LMS/WooCommerce Suggestions AJAX handlers (FR-250 to FR-259)
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_track_page_view', array( $this, 'handle_track_page_view' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_track_page_view', array( $this, 'handle_track_page_view' ) );
		add_action( 'wp_ajax_ai_botkit_get_recommendations', array( $this, 'handle_get_recommendations' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_get_recommendations', array( $this, 'handle_get_recommendations' ) );
		add_action( 'wp_ajax_ai_botkit_add_to_cart', array( $this, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_ai_botkit_add_to_cart', array( $this, 'handle_add_to_cart' ) );
		add_action( 'wp_ajax_ai_botkit_enroll_course', array( $this, 'handle_enroll_course' ) );
	}

	/**
	 * Check if rate limiter has the required method
	 *
	 * @return bool True if rate limiter is available
	 */
	private function is_rate_limiter_available(): bool {
		return isset( $this->rate_limiter ) && method_exists( $this->rate_limiter, 'check_user_limits' );
	}

	/**
	 * Handle chat message request
	 */
	public function handle_chat_message(): void {
		try {
			// Verify nonce
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if IP is blocked
			if ( $this->is_ip_blocked() ) {
				throw new \Exception( __( 'Access denied. Your IP address has been blocked.', 'knowvault' ) );
			}

			// Get request data
			$message         = sanitize_textarea_field( $_POST['message'] ?? '' );
			$conversation_id = sanitize_key( $_POST['conversation_id'] ?? '' );
			$context         = sanitize_text_field( $_POST['context'] ?? '' );
			$bot_id          = sanitize_key( $_POST['bot_id'] ?? '' );
			if ( empty( $message ) ) {
				throw new \Exception( __( 'Message cannot be empty', 'knowvault' ) );
			}

			// Parse attachments (rich media sent with message)
			$attachments = array();
			if ( ! empty( $_POST['attachments'] ) && is_string( $_POST['attachments'] ) ) {
				$decoded = json_decode( wp_unslash( $_POST['attachments'] ), true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $a ) {
						if ( ! empty( $a['id'] ) && ! empty( $a['url'] ) ) {
							$attachments[] = array(
								'id'   => absint( $a['id'] ),
								'url'  => esc_url_raw( $a['url'] ),
								'type' => sanitize_key( $a['type'] ?? 'document' ),
							);
						}
					}
				}
			}

			// Generate response
			if ( get_option( 'ai_botkit_stream_responses', false ) ) {
				// For streaming responses, start the generation and return response ID
				$response_id = $this->start_streaming_response( $message, $conversation_id, $bot_id, $context, $attachments );
				wp_send_json_success( array( 'response_id' => $response_id ) );
			} else {
				// For non-streaming responses, generate complete response
				$response = $this->rag_engine->generate_response( $message, $conversation_id, $bot_id, $context, array( 'attachments' => $attachments ) );

				do_action( 'ai_botkit_chat_message', $message, $response, $response['metadata'] );

				wp_send_json_success( $response );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle streaming response request
	 */
	public function handle_stream_response(): void {
		try {
			// Verify nonce
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if IP is blocked
			if ( $this->is_ip_blocked() ) {
				throw new \Exception( __( 'Access denied. Your IP address has been blocked.', 'knowvault' ) );
			}

			// Get response ID
			$response_id = sanitize_key( $_POST['response_id'] ?? '' );
			if ( empty( $response_id ) ) {
				throw new \Exception( __( 'Invalid response ID', 'knowvault' ) );
			}

			// Get response data from transient
			$response_data = get_transient( 'ai_botkit_response_' . $response_id );
			if ( ! $response_data ) {
				throw new \Exception( __( 'Response not found or expired', 'knowvault' ) );
			}

			// Return response data
			wp_send_json_success( $response_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle conversation history request
	 */
	public function handle_get_history(): void {
		try {
			// Verify nonce
			$this->verify_nonce( 'ai_botkit_chat' );

			// Get conversation ID
			$conversation_id = sanitize_key( $_POST['conversation_id'] ?? '' );
			if ( empty( $conversation_id ) ) {
				throw new \Exception( __( 'Invalid conversation ID', 'knowvault' ) );
			}

			// Get conversation history
			$history = $this->get_conversation_history( $conversation_id );
			wp_send_json_success( array( 'history' => $history ) );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle clear conversation request
	 */
	public function handle_clear_conversation(): void {
		try {
			// Verify nonce
			$this->verify_nonce( 'ai_botkit_chat' );

			// Get conversation ID
			$conversation_id = sanitize_key( $_POST['conversation_id'] ?? '' );
			if ( empty( $conversation_id ) ) {
				throw new \Exception( __( 'Invalid conversation ID', 'knowvault' ) );
			}

			// Clear conversation history
			// $this->clear_conversation_history($conversation_id);
			wp_send_json_success();
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Start streaming response generation
	 *
	 * @param string $message User message
	 * @param string $conversation_id Conversation ID
	 * @param int    $bot_id Bot ID
	 * @param string $context Optional context
	 * @return string Response ID
	 */
	private function start_streaming_response( string $message, string $conversation_id, int $bot_id, string $context = '', array $attachments = array() ): string {
		// Generate unique response ID
		$response_id = uniqid( 'resp_' );

		// Store initial response data
		set_transient(
			'ai_botkit_response_' . $response_id,
			array(
				'status'  => 'processing',
				'content' => '',
				'sources' => array(),
			),
			HOUR_IN_SECONDS
		);

		$options = ! empty( $attachments ) ? array( 'attachments' => $attachments ) : array();

		// Start streaming response in background
		$this->rag_engine->stream_response(
			$message,
			$conversation_id,
			$bot_id,
			function ( $chunk ) use ( $response_id ) {
				$response_data = get_transient( 'ai_botkit_response_' . $response_id );
				if ( $response_data ) {
					// Handle both string and array formats for consistency
					if ( is_string( $chunk ) ) {
						$response_data['content'] .= $chunk;
					} elseif ( is_array( $chunk ) ) {
						$response_data['content'] .= $chunk['content'] ?? '';
						$response_data['sources']  = array_merge(
							$response_data['sources'] ?? array(),
							$chunk['sources'] ?? array()
						);
					}
					set_transient( 'ai_botkit_response_' . $response_id, $response_data, HOUR_IN_SECONDS );
				}
			},
			array_merge(
				array(
					'context'     => $context,
					'max_tokens'  => get_option( 'ai_botkit_max_tokens', 1000 ),
					'temperature' => get_option( 'ai_botkit_temperature', 0.7 ),
				),
				$options
			)
		);

		return $response_id;
	}

	/**
	 * Get conversation history
	 *
	 * @param string $conversation_id Conversation ID
	 * @return array Conversation history
	 */
	private function get_conversation_history( string $conversation_id ): array {
		global $wpdb;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ai_botkit_messages
            WHERE conversation_id = %s
            ORDER BY created_at ASC",
				$conversation_id
			)
		);

		return array_map(
			function ( $message ) {
				return array(
					'role'      => $message->role,
					'content'   => $message->content,
					'metadata'  => json_decode( $message->metadata, true ),
					'timestamp' => strtotime( $message->created_at ),
				);
			},
			$messages
		);
	}

	/**
	 * Clear conversation history
	 *
	 * @param string $conversation_id Conversation ID
	 */
	private function clear_conversation_history( string $conversation_id ): void {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'ai_botkit_messages',
			array( 'conversation_id' => $conversation_id ),
			array( '%s' )
		);
	}

	/**
	 * Verify nonce
	 *
	 * @param string $action Nonce action
	 * @throws \Exception If nonce verification fails
	 */
	private function verify_nonce( string $action ): void {
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			throw new \Exception( esc_html__( 'Security check failed', 'knowvault' ) );
		}
	}

	/**
	 * Handle feedback request
	 */
	public function handle_feedback(): void {
		try {
			// Verify nonce
			$this->verify_nonce( 'ai_botkit_chat' );

			// Get request data
			$chat_id  = sanitize_key( $_POST['chat_id'] ?? '' );
			$message  = sanitize_textarea_field( $_POST['message'] ?? '' );
			$feedback = sanitize_text_field( $_POST['feedback'] ?? '' );
			if ( empty( $chat_id ) || empty( $message ) || empty( $feedback ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid request', 'knowvault' ),
						'code'    => 'invalid_request',
					)
				);
			}

			if ( 'up' == $feedback ) {
				$feedback = 1;
			} else {
				$feedback = 0;
			}

			// Update feedback
			$this->update_feedback( $chat_id, $message, $feedback );

			wp_send_json_success();
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Update feedback
	 *
	 * @param string $chat_id Chat ID
	 * @param string $message Message
	 * @param int    $feedback Feedback
	 */
	private function update_feedback( string $chat_id, string $message, int $feedback ): void {
		global $wpdb;
		// update feedback in metadata
		// get the metadata
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ai_botkit_messages as messages
            JOIN {$wpdb->prefix}ai_botkit_conversations as conversations ON messages.conversation_id = conversations.id
            WHERE conversations.session_id = %s
            AND messages.content = %s",
				$chat_id,
				$message
			)
		);
		if ( empty( $results ) ) {
			return;
		}
		$metadata             = json_decode( $results[0]->metadata, true );
		$metadata['feedback'] = $feedback;
		$conversation_id      = $results[0]->id;

		$wpdb->update(
			$wpdb->prefix . 'ai_botkit_messages',
			array( 'metadata' => wp_json_encode( $metadata ) ),
			array(
				'conversation_id' => $conversation_id,
				'content'         => $message,
			),
			array( '%s' )
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
		$blocked_ips_json = get_option( 'ai_botkit_blocked_ips', '[]' );
		$blocked_ips      = json_decode( $blocked_ips_json, true );

		// Check if user's IP is in the blocked list
		return in_array( $user_ip, $blocked_ips );
	}

	/**
	 * Handle rate limiter check request.
	 *
	 * @since 1.0.0
	 */
	public function handle_check_rate_limiter(): void {
		try {
			// Only allow logged-in users with manage_options capability.
			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( esc_html__( 'Unauthorized access', 'knowvault' ), 403 );
			}

			$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : get_current_user_id();

			$debug_data = array(
				'rate_limiter_available' => $this->is_rate_limiter_available(),
				'user_id'                => $user_id,
			);

			if ( $this->is_rate_limiter_available() ) {
				$debug_data['rate_check'] = $this->rate_limiter->check_user_limits( $user_id );

				if ( method_exists( $this->rate_limiter, 'get_user_usage_stats' ) ) {
					$debug_data['usage_stats'] = $this->rate_limiter->get_user_usage_stats( $user_id );
				}

				if ( method_exists( $this->rate_limiter, 'get_remaining_limits' ) ) {
					$debug_data['remaining_limits'] = $this->rate_limiter->get_remaining_limits( $user_id );
				}

				if ( method_exists( $this->rate_limiter, 'debug_check_tables' ) ) {
					$debug_data['table_info'] = $this->rate_limiter->debug_check_tables();
				}
			}

			wp_send_json_success( $debug_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	// =========================================================
	// Phase 2: Chat History AJAX Handlers (FR-201 to FR-209)
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
	 * Handle list conversations request.
	 *
	 * Returns paginated list of user conversations with previews.
	 *
	 * Implements: FR-201 (View Chat History)
	 *
	 * @since 2.0.0
	 */
	public function handle_list_conversations(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can access chat history.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to view chat history.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$user_id    = get_current_user_id();
			$chatbot_id = isset( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : null;
			$page       = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page   = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;

			// Get conversations.
			$result = $this->chat_history_handler->get_user_conversations(
				$user_id,
				$chatbot_id,
				$page,
				$per_page
			);

			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle switch conversation request.
	 *
	 * Loads a previous conversation and returns its messages.
	 *
	 * Implements: FR-203 (Resume Conversation), FR-204 (Conversation Switching)
	 *
	 * @since 2.0.0
	 */
	public function handle_switch_conversation(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can access chat history.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to access conversations.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Invalid conversation ID.', 'knowvault' ), 400 );
			}

			$user_id = get_current_user_id();

			// Switch to conversation.
			$result = $this->chat_history_handler->switch_conversation( $conversation_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
			}

			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle delete conversation request.
	 *
	 * Permanently deletes a conversation and all its messages.
	 *
	 * Implements: FR-205 (Delete Conversation)
	 *
	 * @since 2.0.0
	 */
	public function handle_delete_conversation(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can delete conversations.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to delete conversations.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Invalid conversation ID.', 'knowvault' ), 400 );
			}

			$user_id = get_current_user_id();

			// Delete conversation.
			$result = $this->chat_history_handler->delete_conversation( $conversation_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
			}

			wp_send_json_success(
				array(
					'message'         => __( 'Conversation deleted successfully.', 'knowvault' ),
					'conversation_id' => $conversation_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle toggle favorite request.
	 *
	 * Toggles the favorite status of a conversation.
	 *
	 * Implements: FR-206 (Mark Favorite)
	 *
	 * @since 2.0.0
	 */
	public function handle_toggle_favorite(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can toggle favorites.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to mark favorites.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Invalid conversation ID.', 'knowvault' ), 400 );
			}

			$user_id = get_current_user_id();

			// Toggle favorite.
			$result = $this->chat_history_handler->toggle_favorite( $conversation_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
			}

			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle filter history request.
	 *
	 * Filters conversations by date range and other criteria.
	 *
	 * Implements: FR-207 (Filter by Date)
	 *
	 * @since 2.0.0
	 */
	public function handle_filter_history(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can filter history.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to filter chat history.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$user_id     = get_current_user_id();
			$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
			$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
			$chatbot_id  = isset( $_POST['chatbot_id'] ) && ! empty( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : null;
			$is_favorite = isset( $_POST['is_favorite'] ) ? filter_var( $_POST['is_favorite'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;
			$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page    = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;

			// Validate date formats.
			if ( ! empty( $start_date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
				throw new \Exception( esc_html__( 'Invalid start date format. Use YYYY-MM-DD.', 'knowvault' ), 400 );
			}

			if ( ! empty( $end_date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
				throw new \Exception( esc_html__( 'Invalid end date format. Use YYYY-MM-DD.', 'knowvault' ), 400 );
			}

			// Filter conversations.
			$result = $this->chat_history_handler->filter_conversations(
				$user_id,
				$start_date,
				$end_date,
				$chatbot_id,
				$is_favorite,
				$page,
				$per_page
			);

			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle archive conversation request.
	 *
	 * Archives a conversation (soft delete).
	 *
	 * Implements: FR-208 (Archive Conversation)
	 *
	 * @since 2.0.0
	 */
	public function handle_archive_conversation(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can archive conversations.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to archive conversations.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Invalid conversation ID.', 'knowvault' ), 400 );
			}

			$user_id = get_current_user_id();

			// Archive conversation.
			$result = $this->chat_history_handler->archive_conversation( $conversation_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
			}

			wp_send_json_success(
				array(
					'message'         => __( 'Conversation archived successfully.', 'knowvault' ),
					'conversation_id' => $conversation_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle unarchive conversation request.
	 *
	 * Restores an archived conversation.
	 *
	 * Implements: FR-209 (Restore Archived)
	 *
	 * @since 2.0.0
	 */
	public function handle_unarchive_conversation(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can unarchive conversations.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to restore conversations.', 'knowvault' ), 401 );
			}

			// Check if chat history handler is available.
			if ( ! $this->is_chat_history_available() ) {
				throw new \Exception( esc_html__( 'Chat history feature is not available.', 'knowvault' ), 500 );
			}

			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Invalid conversation ID.', 'knowvault' ), 400 );
			}

			$user_id = get_current_user_id();

			// Unarchive conversation.
			$result = $this->chat_history_handler->unarchive_conversation( $conversation_id, $user_id );

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
			}

			wp_send_json_success(
				array(
					'message'         => __( 'Conversation restored successfully.', 'knowvault' ),
					'conversation_id' => $conversation_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	// =========================================================
	// Phase 2: Rich Media Support AJAX Handlers (FR-220 to FR-229)
	// =========================================================

	/**
	 * Check if media handler is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if media handler is available.
	 */
	private function is_media_handler_available(): bool {
		return isset( $this->media_handler ) && $this->media_handler !== null;
	}

	/**
	 * Handle upload chat media request.
	 *
	 * Uploads images and documents for chat messages.
	 *
	 * Implements: FR-220 (Image Attachments), FR-222 (File Attachments), FR-224 (Media Upload Handling)
	 *
	 * @since 2.0.0
	 */
	public function handle_upload_chat_media(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if media handler is available.
			if ( ! $this->is_media_handler_available() ) {
				throw new \Exception( esc_html__( 'Media upload feature is not available.', 'knowvault' ), 500 );
			}

			// Check if file was uploaded.
			if ( empty( $_FILES['media'] ) || $_FILES['media']['error'] === UPLOAD_ERR_NO_FILE ) {
				throw new \Exception( esc_html__( 'No file was uploaded.', 'knowvault' ), 400 );
			}

			// Get optional message and conversation IDs.
			$message_id      = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : null;
			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : null;

			// Upload the media file.
			$result = $this->media_handler->upload_media(
				$_FILES['media'],
				$message_id,
				$conversation_id
			);

			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message(), $result->get_error_data()['status'] ?? 400 );
			}

			wp_send_json_success(
				array(
					'message' => __( 'File uploaded successfully.', 'knowvault' ),
					'media'   => $result,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle get link preview request.
	 *
	 * Fetches OpenGraph metadata for a URL.
	 *
	 * Implements: FR-223 (Rich Link Previews)
	 *
	 * @since 2.0.0
	 */
	public function handle_get_link_preview(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if media handler is available.
			if ( ! $this->is_media_handler_available() ) {
				throw new \Exception( esc_html__( 'Link preview feature is not available.', 'knowvault' ), 500 );
			}

			// Get URL from request.
			$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

			if ( empty( $url ) ) {
				throw new \Exception( esc_html__( 'URL is required.', 'knowvault' ), 400 );
			}

			// Get link preview.
			$preview = $this->media_handler->get_link_preview( $url );

			if ( isset( $preview['error'] ) ) {
				throw new \Exception( $preview['error'], 400 );
			}

			// Optionally save to database.
			$save_to_db      = isset( $_POST['save'] ) && $_POST['save'] === 'true';
			$message_id      = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : null;
			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : null;

			if ( $save_to_db ) {
				$media_id = $this->media_handler->save_link_preview( $preview, $message_id, $conversation_id );

				if ( ! is_wp_error( $media_id ) ) {
					$preview['media_id'] = $media_id;
				}
			}

			wp_send_json_success(
				array(
					'preview' => $preview,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle process video URL request.
	 *
	 * Processes YouTube/Vimeo URLs for embedding.
	 *
	 * Implements: FR-221 (Video Embeds)
	 *
	 * @since 2.0.0
	 */
	public function handle_process_video_url(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if media handler is available.
			if ( ! $this->is_media_handler_available() ) {
				throw new \Exception( esc_html__( 'Video embed feature is not available.', 'knowvault' ), 500 );
			}

			// Get URL from request.
			$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

			if ( empty( $url ) ) {
				throw new \Exception( esc_html__( 'URL is required.', 'knowvault' ), 400 );
			}

			// Process video URL.
			$video_data = $this->media_handler->process_video_embed( $url );

			if ( $video_data === false ) {
				throw new \Exception( esc_html__( 'URL is not a supported video platform (YouTube or Vimeo).', 'knowvault' ), 400 );
			}

			// Optionally save to database.
			$save_to_db      = isset( $_POST['save'] ) && $_POST['save'] === 'true';
			$message_id      = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : null;
			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : null;

			if ( $save_to_db ) {
				$media_id = $this->media_handler->save_video_embed( $video_data, $message_id, $conversation_id );

				if ( ! is_wp_error( $media_id ) ) {
					$video_data['media_id'] = $media_id;
				}
			}

			wp_send_json_success(
				array(
					'video' => $video_data,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle delete media request.
	 *
	 * Deletes an uploaded media file.
	 *
	 * Implements: FR-229 (Storage Management)
	 *
	 * @since 2.0.0
	 */
	public function handle_delete_media(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can delete media.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to delete media.', 'knowvault' ), 401 );
			}

			// Check if media handler is available.
			if ( ! $this->is_media_handler_available() ) {
				throw new \Exception( esc_html__( 'Media feature is not available.', 'knowvault' ), 500 );
			}

			// Get media ID.
			$media_id = isset( $_POST['media_id'] ) ? absint( $_POST['media_id'] ) : 0;

			if ( empty( $media_id ) ) {
				throw new \Exception( esc_html__( 'Invalid media ID.', 'knowvault' ), 400 );
			}

			// Get media record to verify ownership.
			$media = $this->media_handler->get_media( $media_id );

			if ( ! $media ) {
				throw new \Exception( esc_html__( 'Media not found.', 'knowvault' ), 404 );
			}

			// Check ownership (user_id must match or user must be admin).
			$user_id = get_current_user_id();
			if ( (int) $media['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
				throw new \Exception( esc_html__( 'You do not have permission to delete this media.', 'knowvault' ), 403 );
			}

			// Delete the media.
			$result = $this->media_handler->delete_media( $media_id );

			if ( ! $result ) {
				throw new \Exception( esc_html__( 'Failed to delete media.', 'knowvault' ), 500 );
			}

			wp_send_json_success(
				array(
					'message'  => __( 'Media deleted successfully.', 'knowvault' ),
					'media_id' => $media_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle download media request.
	 *
	 * Serves a media file for download with proper headers.
	 *
	 * Implements: FR-227 (File Download Handling)
	 *
	 * @since 2.0.0
	 */
	public function handle_download_media(): void {
		try {
			// Get media ID and verify nonce.
			$media_id = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
			$nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

			if ( empty( $media_id ) ) {
				throw new \Exception( esc_html__( 'Invalid media ID.', 'knowvault' ), 400 );
			}

			// Verify download nonce.
			if ( ! wp_verify_nonce( $nonce, 'ai_botkit_download_' . $media_id ) ) {
				throw new \Exception( esc_html__( 'Security check failed.', 'knowvault' ), 403 );
			}

			// Check if media handler is available.
			if ( ! $this->is_media_handler_available() ) {
				throw new \Exception( esc_html__( 'Media feature is not available.', 'knowvault' ), 500 );
			}

			// Get media record.
			$media = $this->media_handler->get_media( $media_id );

			if ( ! $media ) {
				throw new \Exception( esc_html__( 'Media not found.', 'knowvault' ), 404 );
			}

			// Check file exists.
			if ( empty( $media['file_path'] ) || ! file_exists( $media['file_path'] ) ) {
				throw new \Exception( esc_html__( 'File not found on server.', 'knowvault' ), 404 );
			}

			// Log download for analytics.
			/**
			 * Fires when a media file is downloaded.
			 *
			 * @since 2.0.0
			 *
			 * @param int $media_id Media ID.
			 * @param int $user_id  User ID (0 if guest).
			 */
			do_action( 'ai_botkit_media_downloaded', $media_id, get_current_user_id() );

			// Set headers for download.
			$filename = $media['metadata']['original_name'] ?? $media['file_name'];

			// Clear any output buffers.
			while ( ob_get_level() ) {
				ob_end_clean();
			}

			// Send headers.
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . $media['file_size'] );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			// Read and output file.
			readfile( $media['file_path'] );
			exit;
		} catch ( \Exception $e ) {
			// For download errors, we need to output HTML.
			status_header( $e->getCode() >= 400 ? $e->getCode() : 500 );
			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Download Error', 'knowvault' ),
				array( 'response' => $e->getCode() >= 400 ? $e->getCode() : 500 )
			);
		}
	}

	// =========================================================
	// Phase 2: Search Functionality AJAX Handlers (FR-210 to FR-219)
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
	 * Handle user search messages request.
	 *
	 * Performs full-text search on user's own conversations only.
	 *
	 * Implements: FR-210 (Search Input Interface)
	 * Implements: FR-211 (Full-Text Search on Messages)
	 * Implements: FR-213 (User Personal Search)
	 * Implements: FR-215 (Search Results Display)
	 * Implements: FR-218 (Search Pagination)
	 *
	 * @since 2.0.0
	 */
	public function handle_search_messages(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can search.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to search conversations.', 'knowvault' ), 401 );
			}

			// Check if search handler is available.
			if ( ! $this->is_search_handler_available() ) {
				throw new \Exception( esc_html__( 'Search feature is not available.', 'knowvault' ), 500 );
			}

			// Get search parameters.
			$query    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
			$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

			// Validate query length.
			if ( mb_strlen( $query ) < 2 ) {
				throw new \Exception( esc_html__( 'Search query must be at least 2 characters.', 'knowvault' ), 400 );
			}

			// Get optional filters.
			$filters = array();

			// User ID is always the current user for non-admin search.
			$filters['user_id'] = get_current_user_id();

			// Optional chatbot filter.
			if ( isset( $_POST['filters']['chatbot_id'] ) && ! empty( $_POST['filters']['chatbot_id'] ) ) {
				$filters['chatbot_id'] = absint( $_POST['filters']['chatbot_id'] );
			}

			// Optional date range filters.
			if ( isset( $_POST['filters']['start_date'] ) && ! empty( $_POST['filters']['start_date'] ) ) {
				$filters['start_date'] = sanitize_text_field( wp_unslash( $_POST['filters']['start_date'] ) );
			}

			if ( isset( $_POST['filters']['end_date'] ) && ! empty( $_POST['filters']['end_date'] ) ) {
				$filters['end_date'] = sanitize_text_field( wp_unslash( $_POST['filters']['end_date'] ) );
			}

			// Optional role filter.
			if ( isset( $_POST['filters']['role'] ) && ! empty( $_POST['filters']['role'] ) ) {
				$filters['role'] = sanitize_text_field( wp_unslash( $_POST['filters']['role'] ) );
			}

			// Perform search.
			$results = $this->search_handler->search( $query, $filters, $page, $per_page );

			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle admin search request.
	 *
	 * Performs full-text search across all conversations (admin only).
	 *
	 * Implements: FR-212 (Admin Global Search)
	 *
	 * @since 2.0.0
	 */
	public function handle_search_admin(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if user has admin capabilities.
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_ai_botkit' ) ) {
				throw new \Exception( esc_html__( 'You do not have permission to perform admin search.', 'knowvault' ), 403 );
			}

			// Check if search handler is available.
			if ( ! $this->is_search_handler_available() ) {
				throw new \Exception( esc_html__( 'Search feature is not available.', 'knowvault' ), 500 );
			}

			// Get search parameters.
			$query    = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
			$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
			$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

			// Validate query length.
			if ( mb_strlen( $query ) < 2 ) {
				throw new \Exception( esc_html__( 'Search query must be at least 2 characters.', 'knowvault' ), 400 );
			}

			// Get optional filters (admin can search all conversations).
			$filters = array();

			// Optional user_id filter for admin to search specific user's conversations.
			if ( isset( $_POST['filters']['user_id'] ) && ! empty( $_POST['filters']['user_id'] ) ) {
				$filters['user_id'] = absint( $_POST['filters']['user_id'] );
			}

			// Optional chatbot filter.
			if ( isset( $_POST['filters']['chatbot_id'] ) && ! empty( $_POST['filters']['chatbot_id'] ) ) {
				$filters['chatbot_id'] = absint( $_POST['filters']['chatbot_id'] );
			}

			// Optional date range filters.
			if ( isset( $_POST['filters']['start_date'] ) && ! empty( $_POST['filters']['start_date'] ) ) {
				$filters['start_date'] = sanitize_text_field( wp_unslash( $_POST['filters']['start_date'] ) );
			}

			if ( isset( $_POST['filters']['end_date'] ) && ! empty( $_POST['filters']['end_date'] ) ) {
				$filters['end_date'] = sanitize_text_field( wp_unslash( $_POST['filters']['end_date'] ) );
			}

			// Optional role filter.
			if ( isset( $_POST['filters']['role'] ) && ! empty( $_POST['filters']['role'] ) ) {
				$filters['role'] = sanitize_text_field( wp_unslash( $_POST['filters']['role'] ) );
			}

			// Perform search.
			$results = $this->search_handler->search( $query, $filters, $page, $per_page );

			// Log admin search for audit purposes.
			/**
			 * Fires when an admin performs a search.
			 *
			 * @since 2.0.0
			 *
			 * @param string $query    Search query.
			 * @param array  $filters  Search filters.
			 * @param int    $user_id  Admin user ID.
			 */
			do_action( 'ai_botkit_admin_search', $query, $filters, get_current_user_id() );

			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle search suggestions request.
	 *
	 * Returns autocomplete suggestions based on partial query.
	 *
	 * Implements: FR-210 (Search Input Interface - suggestions)
	 *
	 * @since 2.0.0
	 */
	public function handle_search_suggestions(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Only logged-in users can get suggestions.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to search.', 'knowvault' ), 401 );
			}

			// Check if search handler is available.
			if ( ! $this->is_search_handler_available() ) {
				throw new \Exception( esc_html__( 'Search feature is not available.', 'knowvault' ), 500 );
			}

			// Get partial query.
			$partial_query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
			$limit         = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;

			// Get suggestions.
			$suggestions = $this->search_handler->get_search_suggestions(
				$partial_query,
				get_current_user_id(),
				min( $limit, 10 ) // Cap at 10 suggestions.
			);

			wp_send_json_success(
				array(
					'suggestions' => $suggestions,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	// =========================================================
	// Phase 2: Chat Transcripts Export AJAX Handlers (FR-240 to FR-249)
	// =========================================================

	/**
	 * Handle user export of their own conversation to PDF.
	 *
	 * Allows logged-in users to export their own conversations.
	 *
	 * Implements: FR-244 (User Self-Service Export)
	 *
	 * @since 2.0.0
	 */
	public function handle_export_my_pdf(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Must be logged in.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to export conversations.', 'knowvault' ), 401 );
			}

			// Get parameters.
			$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

			if ( empty( $conversation_id ) ) {
				throw new \Exception( esc_html__( 'Conversation ID is required.', 'knowvault' ), 400 );
			}

			// Initialize export handler.
			require_once dirname( __DIR__, 1 ) . '/features/class-export-handler.php';

			if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
				throw new \Exception( esc_html__( 'Export handler not available.', 'knowvault' ), 500 );
			}

			$export_handler = new \AI_BotKit\Features\Export_Handler();

			// Check if user can export this conversation (ownership check).
			$user_id = get_current_user_id();
			if ( ! $export_handler->can_export( $conversation_id, $user_id ) ) {
				throw new \Exception( esc_html__( 'You do not have permission to export this conversation.', 'knowvault' ), 403 );
			}

			// Check if dompdf is available.
			if ( ! $export_handler->is_dompdf_available() ) {
				throw new \Exception(
					esc_html__( 'PDF export is not available. The dompdf library is not installed. Please contact the site administrator.', 'knowvault' ),
					500
				);
			}

			// Get export options.
			$options = array(
				'include_metadata' => isset( $_POST['include_metadata'] ) ? (bool) $_POST['include_metadata'] : true,
				'include_branding' => isset( $_POST['include_branding'] ) ? (bool) $_POST['include_branding'] : true,
				'paper_size'       => isset( $_POST['paper_size'] ) && in_array( $_POST['paper_size'], array( 'a4', 'letter' ), true )
					? sanitize_text_field( $_POST['paper_size'] )
					: 'a4',
			);

			// Stream PDF to browser.
			$export_handler->stream_pdf( $conversation_id, $options );

			// Note: stream_pdf exits after sending file, so we won't reach here.

		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	// =========================================================
	// Phase 2: LMS/WooCommerce Suggestions AJAX Handlers (FR-250 to FR-259)
	// =========================================================

	/**
	 * Check if recommendation engine is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if recommendation engine is available.
	 */
	private function is_recommendation_engine_available(): bool {
		return isset( $this->recommendation_engine ) && $this->recommendation_engine !== null;
	}

	/**
	 * Check if browsing tracker is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if browsing tracker is available.
	 */
	private function is_browsing_tracker_available(): bool {
		return isset( $this->browsing_tracker ) && $this->browsing_tracker !== null;
	}

	/**
	 * Handle track page view request.
	 *
	 * Records page views for products and courses to inform recommendations.
	 *
	 * Implements: FR-252 (Browsing History Tracking)
	 *
	 * @since 2.0.0
	 */
	public function handle_track_page_view(): void {
		try {
			// Verify nonce.
			if ( ! check_ajax_referer( 'ai_botkit_track', 'nonce', false ) ) {
				throw new \Exception( esc_html__( 'Security check failed.', 'knowvault' ), 403 );
			}

			// Check if browsing tracker is available.
			if ( ! $this->is_browsing_tracker_available() ) {
				throw new \Exception( esc_html__( 'Browsing tracking feature is not available.', 'knowvault' ), 500 );
			}

			// Get request parameters.
			$item_type = isset( $_POST['item_type'] ) ? sanitize_key( $_POST['item_type'] ) : '';
			$item_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
			$metadata  = isset( $_POST['metadata'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['metadata'] ) ), true ) : array();

			// Validate parameters.
			if ( empty( $item_type ) || $item_id <= 0 ) {
				throw new \Exception( esc_html__( 'Invalid parameters.', 'knowvault' ), 400 );
			}

			// Validate item type.
			$valid_types = array( 'product', 'course', 'post', 'page' );
			if ( ! in_array( $item_type, $valid_types, true ) ) {
				throw new \Exception( esc_html__( 'Invalid item type.', 'knowvault' ), 400 );
			}

			// Track the page view.
			$result = $this->browsing_tracker->track_page_view( $item_type, $item_id, $metadata ?: array() );

			if ( $result ) {
				wp_send_json_success( array( 'tracked' => true ) );
			} else {
				wp_send_json_success(
					array(
						'tracked' => false,
						'message' => 'Already tracked recently.',
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle get recommendations request.
	 *
	 * Returns personalized product and course suggestions based on multiple signals.
	 *
	 * Implements: FR-250 (Recommendation Engine Core)
	 * Implements: FR-251 (Conversation Context Analysis)
	 * Implements: FR-255 (Suggestion UI Cards)
	 *
	 * @since 2.0.0
	 */
	public function handle_get_recommendations(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if recommendation engine is available.
			if ( ! $this->is_recommendation_engine_available() ) {
				throw new \Exception( esc_html__( 'Recommendation feature is not available.', 'knowvault' ), 500 );
			}

			// Get request parameters.
			$conversation_text = isset( $_POST['conversation_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['conversation_text'] ) ) : '';
			$chatbot_id        = isset( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : 0;
			$limit             = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 5;
			$session_id        = isset( $_POST['session_id'] ) ? sanitize_key( $_POST['session_id'] ) : '';

			// Build context.
			$context = array(
				'conversation_text' => $conversation_text,
				'chatbot_id'        => $chatbot_id,
				'session_id'        => $session_id,
			);

			// Get recommendations.
			$user_id         = get_current_user_id();
			$recommendations = $this->recommendation_engine->get_recommendations( $user_id, $context, min( $limit, 10 ) );

			// Track this interaction.
			if ( $this->is_recommendation_engine_available() && ! empty( $recommendations ) ) {
				$this->recommendation_engine->track_interaction(
					$user_id,
					'recommendations_requested',
					'recommendation',
					0,
					array(
						'count'             => count( $recommendations ),
						'conversation_text' => mb_substr( $conversation_text, 0, 200 ),
					)
				);
			}

			wp_send_json_success(
				array(
					'recommendations' => $recommendations,
					'count'           => count( $recommendations ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle add to cart request.
	 *
	 * Adds a WooCommerce product to the cart from the chat suggestion.
	 *
	 * Implements: FR-256 (Add to Cart Action)
	 *
	 * @since 2.0.0
	 */
	public function handle_add_to_cart(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Check if WooCommerce is active.
			if ( ! class_exists( 'WooCommerce' ) ) {
				throw new \Exception( esc_html__( 'WooCommerce is not available.', 'knowvault' ), 500 );
			}

			// Get request parameters.
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

			if ( empty( $product_id ) ) {
				throw new \Exception( esc_html__( 'Invalid product ID.', 'knowvault' ), 400 );
			}

			// Validate quantity.
			if ( $quantity < 1 ) {
				$quantity = 1;
			}

			// Get the product.
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				throw new \Exception( esc_html__( 'Product not found.', 'knowvault' ), 404 );
			}

			// Check if product is purchasable.
			if ( ! $product->is_purchasable() ) {
				throw new \Exception( esc_html__( 'This product cannot be purchased.', 'knowvault' ), 400 );
			}

			// Check stock status.
			if ( ! $product->is_in_stock() ) {
				throw new \Exception( esc_html__( 'Sorry, this product is currently out of stock.', 'knowvault' ), 400 );
			}

			// Handle variable products.
			if ( $product->is_type( 'variable' ) ) {
				wp_send_json_success(
					array(
						'redirect' => true,
						'url'      => get_permalink( $product_id ),
						'message'  => __( 'Please select your options on the product page.', 'knowvault' ),
					)
				);
				return;
			}

			// Add to cart.
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

			if ( ! $cart_item_key ) {
				throw new \Exception( esc_html__( 'Failed to add product to cart.', 'knowvault' ), 500 );
			}

			// Track this interaction.
			if ( $this->is_recommendation_engine_available() ) {
				$this->recommendation_engine->track_interaction(
					get_current_user_id(),
					'add_to_cart',
					'product',
					$product_id,
					array( 'quantity' => $quantity )
				);
			}

			// Get updated cart info.
			$cart_count = WC()->cart->get_cart_contents_count();
			$cart_total = WC()->cart->get_cart_total();
			$cart_url   = wc_get_cart_url();

			wp_send_json_success(
				array(
					'message'    => sprintf(
						/* translators: %s: product name */
						__( '%s added to cart!', 'knowvault' ),
						$product->get_name()
					),
					'cart_count' => $cart_count,
					'cart_total' => $cart_total,
					'cart_url'   => $cart_url,
					'product_id' => $product_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}

	/**
	 * Handle enroll in course request.
	 *
	 * Enrolls the user in a free LearnDash course from the chat suggestion.
	 *
	 * Implements: FR-257 (Enroll Now Action)
	 *
	 * @since 2.0.0
	 */
	public function handle_enroll_course(): void {
		try {
			// Verify nonce.
			$this->verify_nonce( 'ai_botkit_chat' );

			// Must be logged in to enroll.
			if ( ! is_user_logged_in() ) {
				throw new \Exception( esc_html__( 'You must be logged in to enroll in courses.', 'knowvault' ), 401 );
			}

			// Check if LearnDash is active.
			if ( ! defined( 'LEARNDASH_VERSION' ) ) {
				throw new \Exception( esc_html__( 'LearnDash is not available.', 'knowvault' ), 500 );
			}

			// Get request parameters.
			$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;

			if ( empty( $course_id ) ) {
				throw new \Exception( esc_html__( 'Invalid course ID.', 'knowvault' ), 400 );
			}

			// Verify the course exists.
			$course = get_post( $course_id );
			if ( ! $course || 'sfwd-courses' !== $course->post_type ) {
				throw new \Exception( esc_html__( 'Course not found.', 'knowvault' ), 404 );
			}

			$user_id = get_current_user_id();

			// Check if already enrolled.
			if ( function_exists( 'sfwd_lms_has_access' ) && sfwd_lms_has_access( $course_id, $user_id ) ) {
				// Already enrolled - return course link.
				wp_send_json_success(
					array(
						'message'    => __( 'You are already enrolled in this course.', 'knowvault' ),
						'enrolled'   => true,
						'already'    => true,
						'course_url' => get_permalink( $course_id ),
					)
				);
				return;
			}

			// Check if course is free.
			$price_type = get_post_meta( $course_id, '_sfwd-courses_course_price_type', true );
			$price      = get_post_meta( $course_id, '_sfwd-courses_course_price', true );

			$is_free = ( 'free' === $price_type || 'open' === $price_type || empty( $price ) );

			if ( ! $is_free ) {
				// Paid course - redirect to course page.
				wp_send_json_success(
					array(
						'redirect' => true,
						'url'      => get_permalink( $course_id ),
						'message'  => __( 'This course requires purchase. Please complete the enrollment on the course page.', 'knowvault' ),
						'is_paid'  => true,
					)
				);
				return;
			}

			// Enroll the user in the free course.
			if ( function_exists( 'ld_update_course_access' ) ) {
				ld_update_course_access( $user_id, $course_id );

				// Track this interaction.
				if ( $this->is_recommendation_engine_available() ) {
					$this->recommendation_engine->track_interaction(
						$user_id,
						'enroll',
						'course',
						$course_id,
						array()
					);
				}

				wp_send_json_success(
					array(
						'message'    => sprintf(
							/* translators: %s: course title */
							__( 'Successfully enrolled in "%s"!', 'knowvault' ),
							$course->post_title
						),
						'enrolled'   => true,
						'course_url' => get_permalink( $course_id ),
						'course_id'  => $course_id,
					)
				);
			} else {
				throw new \Exception( esc_html__( 'Enrollment function not available.', 'knowvault' ), 500 );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				)
			);
		}
	}
}
