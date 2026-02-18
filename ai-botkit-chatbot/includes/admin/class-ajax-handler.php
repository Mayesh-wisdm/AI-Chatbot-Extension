<?php
namespace AI_BotKit\Admin;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Core\Rate_Limiter;
use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Models\Chatbot;
use AI_BotKit\Utils\Table_Helper;

/**
 * Class Ajax_Handler
 *
 * Handles all AJAX requests for the AI BotKit plugin.
 * This class manages API testing, chatbot operations, and fallback order management.
 */
class Ajax_Handler {
	/**
	 * Rate limiter instance
	 *
	 * @var Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Constructor - Registers all AJAX hooks
	 */
	public function __construct() {
		$this->rate_limiter = new Rate_Limiter();

		// API testing endpoint
		add_action( 'wp_ajax_ai_botkit_test_api_connection', array( $this, 'handle_test_api' ) );

		// Chatbot management endpoints
		add_action( 'wp_ajax_ai_botkit_save_chatbot', array( $this, 'handle_save_chatbot' ) );
		add_action( 'wp_ajax_ai_botkit_get_chatbot', array( $this, 'handle_get_chatbot' ) );
		add_action( 'wp_ajax_ai_botkit_delete_chatbot', array( $this, 'handle_delete_chatbot' ) );

		// Fallback order management endpoint
		add_action( 'wp_ajax_ai_botkit_update_fallback_order', array( $this, 'handle_update_fallback_order' ) );

		// Rate limit management endpoints
		add_action( 'wp_ajax_ai_botkit_set_rate_limits', array( $this, 'handle_set_rate_limits' ) );
		add_action( 'wp_ajax_ai_botkit_reset_rate_limits', array( $this, 'handle_reset_rate_limits' ) );

		// Preview content endpoint
		add_action( 'wp_ajax_ai_botkit_preview_content', array( $this, 'ai_botkit_preview_content' ) );

		// Document import endpoints
		add_action( 'wp_ajax_ai_botkit_upload_file', array( $this, 'handle_file_upload' ) );
		add_action( 'wp_ajax_ai_botkit_import_url', array( $this, 'handle_url_import' ) );
		add_action( 'wp_ajax_ai_botkit_import_wp_content', array( $this, 'handle_wp_content_import' ) );
		add_action( 'wp_ajax_ai_botkit_delete_document', array( $this, 'handle_delete_document' ) );

		// Chatbot document management endpoints
		add_action( 'wp_ajax_ai_botkit_add_chatbot_documents', array( $this, 'handle_add_chatbot_documents' ) );
		add_action( 'wp_ajax_ai_botkit_remove_chatbot_document', array( $this, 'handle_remove_chatbot_document' ) );
		add_action( 'wp_ajax_ai_botkit_get_chatbot_documents', array( $this, 'handle_get_chatbot_documents' ) );

		// New endpoint for getting available documents for selection
		add_action( 'wp_ajax_ai_botkit_get_available_documents', array( $this, 'handle_get_available_documents' ) );

		// Avatar upload endpoint
		add_action( 'wp_ajax_ai_botkit_upload_avatar', array( $this, 'handle_upload_avatar' ) );
		add_action( 'wp_ajax_ai_botkit_upload_background_image', array( $this, 'handle_upload_background_image' ) );

		// Enable chatbot sitewide endpoint
		add_action( 'wp_ajax_ai_botkit_enable_chatbot_sitewide', array( $this, 'handle_enable_chatbot_sitewide' ) );
		add_action( 'wp_ajax_ai_botkit_enable_chatbot', array( $this, 'handle_enable_chatbot' ) );

		// Migration endpoints
		add_action( 'wp_ajax_ai_botkit_get_migration_status', array( $this, 'handle_get_migration_status' ) );
		add_action( 'wp_ajax_ai_botkit_get_content_types', array( $this, 'handle_get_content_types' ) );
		add_action( 'wp_ajax_ai_botkit_start_migration', array( $this, 'handle_start_migration' ) );
		add_action( 'wp_ajax_ai_botkit_download_migration_log', array( $this, 'handle_download_migration_log' ) );
		add_action( 'wp_ajax_ai_botkit_clear_migration_lock', array( $this, 'handle_clear_migration_lock' ) );
		add_action( 'wp_ajax_ai_botkit_clear_database', array( $this, 'handle_clear_database' ) );

		// Analytics endpoints
		add_action( 'wp_ajax_ai_botkit_get_analytics_data', array( $this, 'handle_get_analytics_data' ) );

		// Knowledge base endpoints
		add_action( 'wp_ajax_ai_botkit_get_knowledge_base_data', array( $this, 'handle_get_knowledge_base_data' ) );
		add_action( 'wp_ajax_ai_botkit_get_chatbot_sessions', array( $this, 'handle_get_chatbot_sessions' ) );
		add_action( 'wp_ajax_ai_botkit_reprocess_document', array( $this, 'handle_reprocess_document' ) );
		add_action( 'wp_ajax_ai_botkit_get_document_error_details', array( $this, 'handle_get_document_error_details' ) );

		// Settings validation endpoints
		add_action( 'wp_ajax_ai_botkit_test_pinecone_connection', array( $this, 'handle_test_pinecone_connection' ) );

		// =========================================================
		// Phase 2: Chat Transcripts Export endpoints (FR-240 to FR-249)
		// =========================================================
		add_action( 'wp_ajax_ai_botkit_export_pdf', array( $this, 'handle_export_pdf' ) );
		add_action( 'wp_ajax_ai_botkit_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'wp_ajax_ai_botkit_batch_export', array( $this, 'handle_batch_export' ) );
		add_action( 'wp_ajax_ai_botkit_export_status', array( $this, 'handle_export_status' ) );

		// Bulk action endpoints for knowledge base
		add_action( 'wp_ajax_ai_botkit_bulk_delete', array( $this, 'handle_bulk_delete' ) );
		add_action( 'wp_ajax_ai_botkit_bulk_reprocess', array( $this, 'handle_bulk_reprocess' ) );
		add_action( 'wp_ajax_ai_botkit_bulk_add_to_bot', array( $this, 'handle_bulk_add_to_bot' ) );
		add_action( 'wp_ajax_ai_botkit_bulk_export', array( $this, 'handle_bulk_export' ) );
	}

	/**
	 * Check rate limit for current user and action
	 *
	 * @param string $action The action being rate limited
	 * @return bool|WP_Error True if allowed, WP_Error if limited
	 */
	private function check_rate_limit( $action ) {
		$user_id = get_current_user_id();
		return $this->rate_limiter->check_rate_limit( $user_id, $action );
	}

	public function handle_test_api() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Check rate limit
		$rate_limit_check = $this->check_rate_limit( 'test_api' );
		if ( is_wp_error( $rate_limit_check ) ) {
			wp_send_json_error(
				array(
					'message' => $rate_limit_check->get_error_message(),
					'data'    => $rate_limit_check->get_error_data(),
				)
			);
		}

		$provider = sanitize_text_field( $_POST['provider'] );
		$api_key  = sanitize_text_field( $_POST['api_key'] );

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API key is required.', 'knowvault' ) ) );
		}

		try {
			$result = $this->test_api_connection( $provider, $api_key );
			if ( $result ) {
				wp_send_json_success( array( 'message' => __( 'API connection successful.', 'knowvault' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'API connection failed. The response was valid but did not contain expected data.', 'knowvault' ) ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						__( 'API connection failed: %s', 'knowvault' ),
						$e->getMessage()
					),
					'data'    => array(
						'provider' => $provider,
						'error'    => $e->getMessage(),
					),
				)
			);
		}
	}

	public function handle_save_chatbot() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Check rate limit
		$rate_limit_check = $this->check_rate_limit( 'save_chatbot' );
		if ( is_wp_error( $rate_limit_check ) ) {
			wp_send_json_error(
				array(
					'message' => $rate_limit_check->get_error_message(),
					'data'    => $rate_limit_check->get_error_data(),
				)
			);
		}

		$chatbot_id = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;

		// Avatar can be an attachment ID (integer) or a URL string for predefined icons
		// The avatar column stores attachment IDs only; URLs are stored in style.avatar JSON
		$avatar_value = isset( $_POST['chatbot_avatar'] ) ? sanitize_text_field( $_POST['chatbot_avatar'] ) : '';
		$avatar_id    = is_numeric( $avatar_value ) ? intval( $avatar_value ) : 0;
		$avatar_url   = is_numeric( $avatar_value )
			? wp_get_attachment_url( $avatar_id )
			: ( $avatar_value ?: esc_url( AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.png' ) );

		$chatbot_data = array(
			'name'              => sanitize_text_field( $_POST['name'] ),
			'active'            => isset( $_POST['active'] ) ? 1 : 0,
			'avatar'            => $avatar_id,
			'feedback'          => isset( $_POST['enable_feedback'] ) ? 1 : 0,

			// Combine all style-related fields into JSON
			'style'             => wp_json_encode(
				array(
					'avatar'                    => $avatar_url,
					'widget'                    => isset( $_POST['chatbot_widget'] ) ? sanitize_text_field( $_POST['chatbot_widget'] ) : esc_url( AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.png' ),
					'location'                  => sanitize_text_field( $_POST['location'] ),
					'primary_color'             => sanitize_text_field( $_POST['chatbot_primary_color'] ),
					'font_family'               => sanitize_text_field( $_POST['chatbot_font_family'] ),
					'font_size'                 => sanitize_text_field( $_POST['chatbot_font_size'] ),
					'theme'                     => sanitize_text_field( $_POST['chatbot_theme'] ),
					'header_bg_color'           => isset( $_POST['chatbot_header_bg_color'] ) ? sanitize_text_field( $_POST['chatbot_header_bg_color'] ) : '#FFFFFF',
					'header_color'              => isset( $_POST['chatbot_header_font_color'] ) ? sanitize_text_field( $_POST['chatbot_header_font_color'] ) : '#333333',
					'header_icon_color'         => isset( $_POST['chatbot_header_icon_color'] ) ? sanitize_text_field( $_POST['chatbot_header_icon_color'] ) : '#333333',
					'width'                     => sanitize_text_field( $_POST['chatbot_width'] ),
					'max_height'                => sanitize_text_field( $_POST['chatbot_max_height'] ),
					'background_image'          => isset( $_POST['background_image'] ) ? sanitize_text_field( $_POST['background_image'] ) : '',
					'body_bg_color'             => isset( $_POST['chatbot_bg_color'] ) ? sanitize_text_field( $_POST['chatbot_bg_color'] ) : '#FFFFFF',
					'ai_msg_bg_color'           => isset( $_POST['chatbot_ai_msg_bg_color'] ) ? sanitize_text_field( $_POST['chatbot_ai_msg_bg_color'] ) : '#F5F5F5',
					'ai_msg_font_color'         => isset( $_POST['chatbot_ai_msg_font_color'] ) ? sanitize_text_field( $_POST['chatbot_ai_msg_font_color'] ) : '#333333',
					'user_msg_bg_color'         => isset( $_POST['chatbot_user_msg_bg_color'] ) ? sanitize_text_field( $_POST['chatbot_user_msg_bg_color'] ) : '#1E3A8A',
					'user_msg_font_color'       => isset( $_POST['chatbot_user_msg_font_color'] ) ? sanitize_text_field( $_POST['chatbot_user_msg_font_color'] ) : '#FFFFFF',
					'initiate_msg_bg_color'     => isset( $_POST['chatbot_initiate_msg_bg_color'] ) ? sanitize_text_field( $_POST['chatbot_initiate_msg_bg_color'] ) : '#FFFFFF',
					'initiate_msg_border_color' => isset( $_POST['chatbot_initiate_msg_border_color'] ) ? sanitize_text_field( $_POST['chatbot_initiate_msg_border_color'] ) : '#E7E7E7',
					'initiate_msg_font_color'   => isset( $_POST['chatbot_initiate_msg_font_color'] ) ? sanitize_text_field( $_POST['chatbot_initiate_msg_font_color'] ) : '#283B3C',
					'bubble_height'             => sanitize_text_field( $_POST['chatbot_bubble_height'] ),
					'bubble_width'              => sanitize_text_field( $_POST['chatbot_bubble_width'] ),
					'gradient_color_1'          => sanitize_text_field( $_POST['chatbot_gradient_color_1'] ),
					'gradient_color_2'          => sanitize_text_field( $_POST['chatbot_gradient_color_2'] ),
					'enable_gradient'           => isset( $_POST['enable_gradient'] ) ? 1 : 0,
					'suggestion_title_color'    => isset( $_POST['chatbot_suggestion_title_color'] ) ? sanitize_text_field( $_POST['chatbot_suggestion_title_color'] ) : '#555555',
					'suggestion_card_bg'         => isset( $_POST['chatbot_suggestion_card_bg'] ) ? sanitize_text_field( $_POST['chatbot_suggestion_card_bg'] ) : '#FFFFFF',
					'suggestion_card_border'     => isset( $_POST['chatbot_suggestion_card_border'] ) ? sanitize_text_field( $_POST['chatbot_suggestion_card_border'] ) : '#E7E7E7',
				)
			),
			// Combine personality, greeting, fallback into JSON
			'messages_template' => wp_json_encode(
				array(
					'personality' => sanitize_textarea_field( wp_unslash( $_POST['personality'] ) ),
					'greeting'    => sanitize_textarea_field( wp_unslash( $_POST['greeting'] ) ),
					'fallback'    => sanitize_textarea_field( wp_unslash( $_POST['fallback'] ) ),
				)
			),

			// Combine model-related config
			'model_config'      => wp_json_encode(
				array(
					'engine'              => sanitize_text_field( $_POST['engine'] ),
					'model'               => sanitize_text_field( $_POST['model'] ),
					'max_messages'        => intval( $_POST['max_messages'] ),
					'context_length'      => intval( $_POST['context_length'] ),
					'max_tokens'          => intval( $_POST['max_tokens'] ),
					'tone'                => sanitize_text_field( $_POST['tone'] ),
					'temperature'         => floatval( $_POST['model_temperature'] ),
					'min_chunk_relevance' => floatval( $_POST['min_chunk_relevance'] ),
				)
			),
		);

		$imports = isset( $_POST['imports'] ) ? json_decode( wp_unslash( $_POST['imports'] ), true ) : array(); // sanitized below
		if ( ! is_array( $imports ) ) {
			$imports = array();
		}
		$imports = array_unique( array_map( 'absint', $imports ) ); // ensure all values are integers and sanitized
		global $wpdb;
		$table_name = $wpdb->prefix . 'ai_botkit_chatbots';

		if ( $chatbot_id ) {
			$result = $wpdb->update(
				$table_name,
				$chatbot_data,
				array( 'id' => $chatbot_id ),
				array(
					'%s', // name
					'%d', // active
					'%d', // avatar
					'%d', // feedback
					'%s', // style (JSON)
					'%s', // messages_template (JSON)
					'%s', // model_config (JSON)
				),
				array( '%d' ) // id
			);
		} else {
			$result     = $wpdb->insert(
				$table_name,
				$chatbot_data,
				array(
					'%s', // name
					'%d', // active
					'%d', // avatar
					'%d', // feedback
					'%s', // style (JSON)
					'%s', // messages_template (JSON)
					'%s', // model_config (JSON)
				)
			);
			$chatbot_id = $wpdb->insert_id;
		}

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save chatbot.', 'knowvault' ) ) );
		}

		// Always manage document relationships, regardless of imports
		$chatbot              = new Chatbot( $chatbot_id );
		$associated_documents = $chatbot->get_associated_content();
		$associated_documents = array_column( $associated_documents, 'target_id' );

		// Calculate what to add and remove
		$docs_to_add    = array_diff( $imports, $associated_documents );
		$docs_to_remove = array_diff( $associated_documents, $imports );

		// Add new relationships
		foreach ( $docs_to_add as $doc_id ) {
			$chatbot->add_content( 'document', $doc_id );
		}

		// Remove old relationships
		foreach ( $docs_to_remove as $doc_id ) {
			$chatbot->remove_content( 'document', $doc_id );
		}
		// Get updated list of associated documents
		$documents = $chatbot->get_associated_content( 'document' );

		// You can also return the chatbot ID if needed
		wp_send_json_success(
			array(
				'message'    => __( 'Chatbot saved successfully.', 'knowvault' ),
				'chatbot_id' => $chatbot_id,
			)
		);
	}

	public function handle_get_chatbot() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id = intval( $_POST['chatbot_id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'ai_botkit_chatbots';

		$chatbot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $chatbot_id ) );

		if ( ! $chatbot ) {
			wp_send_json_error( array( 'message' => __( 'Chatbot not found.', 'knowvault' ) ) );
		}

		$image              = wp_get_attachment_image_src( $chatbot->avatar, 'full' );
		$chatbot->avatar_id = $chatbot->avatar;
		if ( $image ) {
			$chatbot->avatar = $image[0];
		} else {
			$chatbot->avatar = '';
		}
		wp_send_json_success( $chatbot );
	}

	public function handle_delete_chatbot() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id = intval( $_POST['chatbot_id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'ai_botkit_chatbots';

		$result = $wpdb->delete( $table_name, array( 'id' => $chatbot_id ) );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete chatbot.', 'knowvault' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Chatbot deleted successfully.', 'knowvault' ) ) );
	}

	public function handle_update_fallback_order() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$order = isset( $_POST['order'] ) ? json_decode( wp_unslash( $_POST['order'] ), true ) : array(); // sanitized below
		$order = array_unique( array_map( 'absint', $order ) ); // ensure all values are integers and sanitized
		if ( ! is_array( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'knowvault' ) ) );
		}

		$sanitized_order = array_map( 'sanitize_text_field', $order );
		update_option( 'ai_botkit_fallback_order', $sanitized_order );

		wp_send_json_success( array( 'message' => __( 'Fallback order updated successfully.', 'knowvault' ) ) );
	}

	/**
	 * Handle setting custom rate limits for a user
	 */
	public function handle_set_rate_limits() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$user_id      = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$action       = sanitize_text_field( $_POST['action_name'] );
		$window       = isset( $_POST['window'] ) ? intval( $_POST['window'] ) : Rate_Limiter::DEFAULT_WINDOW;
		$max_requests = isset( $_POST['max_requests'] ) ? intval( $_POST['max_requests'] ) : Rate_Limiter::DEFAULT_MAX_REQUESTS;

		if ( ! $user_id || empty( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$result = $this->rate_limiter->set_user_rate_limits( $user_id, $action, $window, $max_requests );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Rate limits updated successfully.', 'knowvault' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update rate limits.', 'knowvault' ) ) );
		}
	}

	/**
	 * Handle resetting rate limits for a user
	 */
	public function handle_reset_rate_limits() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$action  = sanitize_text_field( $_POST['action_name'] );

		if ( ! $user_id || empty( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$result = $this->rate_limiter->reset_rate_limit( $user_id, $action );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Rate limits reset successfully.', 'knowvault' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reset rate limits.', 'knowvault' ) ) );
		}
	}

	/**
	 * Tests API connection for a specific provider
	 *
	 * @param string $provider The API provider (openai, anthropic, google)
	 * @param string $api_key The API key to test
	 * @return bool True if connection is successful
	 * @throws Exception If provider is invalid or connection fails
	 */
	private function test_api_connection( $provider, $api_key ) {
		switch ( $provider ) {
			case 'openai':
				return $this->test_openai_connection( $api_key );
			case 'anthropic':
				return $this->test_anthropic_connection( $api_key );
			case 'google':
				return $this->test_google_connection( $api_key );
			case 'together':
				return $this->test_together_connection( $api_key );
			case 'voyageai':
				return $this->test_voyageai_connection( $api_key );
			default:
				throw new \Exception( esc_html__( 'Invalid provider.', 'knowvault' ) );
		}
	}

	/**
	 * Tests OpenAI API connection
	 *
	 * @param string $api_key OpenAI API key
	 * @return bool True if connection is successful
	 * @throws Exception If connection fails
	 */
	private function test_openai_connection( $api_key ) {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['data'] ) && is_array( $body['data'] );
	}

	/**
	 * Tests Anthropic API connection
	 *
	 * @param string $api_key Anthropic API key
	 * @return bool True if connection is successful
	 * @throws Exception If connection fails
	 */
	private function test_anthropic_connection( $api_key ) {
		$response = wp_remote_get(
			'https://api.anthropic.com/v1/models',
			array(
				'headers' => array(
					'x-api-key'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['data'] ) && is_array( $body['data'] );
	}

	/**
	 * Tests Google AI API connection
	 *
	 * @param string $api_key Google AI API key
	 * @return bool True if connection is successful
	 * @throws Exception If connection fails
	 */
	private function test_google_connection( $api_key ) {
		// Using Google AI API endpoint for model listing
		$response = wp_remote_get(
			'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['models'] ) && is_array( $body['models'] );
	}

	/**
	 * Tests Together AI API connection
	 *
	 * @param string $api_key Together AI API key
	 * @return bool True if connection is successful
	 * @throws Exception If connection fails
	 */
	private function test_together_connection( $api_key ) {
		$response = wp_remote_get(
			'https://api.together.xyz/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body[0]['id'] ) && ! empty( $body );
	}

	/**
	 * Tests VoyageAI API connection
	 *
	 * @param string $api_key VoyageAI API key
	 * @return bool True if connection is successful
	 * @throws Exception If connection fails
	 */
	private function test_voyageai_connection( $api_key ) {
		$response = wp_remote_post(
			'https://api.voyageai.com/v1/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => array( 'test' ),
						'model' => 'voyage-3-lite',
					)
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $body['data'][0]['embedding'] ) && ! empty( $body['data'][0]['embedding'] );
	}

	/**
	 * Preview content
	 */
	function ai_botkit_preview_content() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', $_POST['post_types'] ) : array();
		$search     = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$date_from  = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to    = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		if ( empty( $post_types ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select at least one content type.', 'knowvault' ) ) );
		}
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
			$args['date_query'] = array();

			if ( ! empty( $date_from ) ) {
				$args['date_query']['after'] = $date_from;
			}

			if ( ! empty( $date_to ) ) {
				$args['date_query']['before'] = $date_to;
			}
		}
		$query = new \WP_Query( $args );

		ob_start();

		if ( $query->have_posts() ) {

			while ( $query->have_posts() ) {
				$query->the_post();
				$post_type_obj = get_post_type_object( get_post_type() );
				echo '<div class="ai-botkit-kb-item">
                    <div class="ai-botkit-kb-left">
                        <input type="checkbox" id="ai-botkit-wp-item-' . esc_attr( get_the_ID() ) . '" class="ai-botkit-wp-checkbox" value="' . esc_attr( get_the_ID() ) . '" />

                        <div class="ai-botkit-kb-info">
                            <label for="ai-botkit-wp-item-' . esc_attr( get_the_ID() ) . '" class="ai-botkit-kb-name">
                                ' . esc_html( get_the_title() ) . '
                            </label>

                            <div class="ai-botkit-kb-type">
                                <span>' . esc_html( $post_type_obj->labels->singular_name ) . '</span>
                            </div>
                        </div>
                    </div>

                    <div class="ai-botkit-kb-tags">
                        ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( get_the_date() ) ) ) . '
                    </div>
                </div>';
			}
		} else {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No content found matching your criteria.', 'knowvault' ) . '</p></div>';
		}

		wp_reset_postdata();
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Handle file upload for knowledge base
	 */
	public function handle_file_upload() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'knowvault' ) ) );
		}

		$file  = $_FILES['file'];
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => esc_html__( 'File upload failed.', 'knowvault' ) ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		// If it's a txt or md file, force the MIME type
		if ( in_array( $ext, array( 'txt', 'md' ) ) ) {
			$file['type'] = 'text/plain';
		}

		// Validate file type
		$allowed_types = array( 'text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/markdown', 'application/octet-stream' );
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type.', 'knowvault' ) ) );
		}

		$filetype = wp_check_filetype( $file['name'] );
		if ( empty( $filetype['ext'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type.', 'knowvault' ) ) );
		}

		// user wp_handle_upload to upload the file
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => esc_html( $upload['error'] ) ) );
		}

		$file_name = $upload['file'];

		if ( empty( $title ) ) {
			$title = basename( $file_name );
		}

		// Save document in database
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'ai_botkit_documents',
			array(
				'title'       => $title,
				'source_type' => 'file',
				'file_path'   => $file_name,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save document.', 'knowvault' ) ) );
		}

		wp_send_json_success(
			array(
				'message'     => esc_html__( 'Document uploaded successfully.', 'knowvault' ),
				'document_id' => $wpdb->insert_id,
			)
		);
	}

	/**
	 * Handle reprocess document
	 */
	public function handle_reprocess_document() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing document ID.', 'knowvault' ) ) );
		}

		$document_id = intval( $_POST['document_id'] );

		try {
			global $wpdb;

			// Get document details
			$document = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ai_botkit_documents WHERE id = %d",
					$document_id
				)
			);

			if ( ! $document ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Document not found.', 'knowvault' ) ) );
			}

			// Create RAG Engine dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			// Reprocess the document
			// For URLs, file_path contains the URL; for files, it's the file path; for posts, source_id contains post ID
			if ( $document->source_type === 'url' ) {
				$source = $document->file_path;
			} elseif ( $document->source_type === 'file' ) {
				$source = $document->file_path;
			} else {
				// For posts, use source_id
				$source = $document->source_id;
			}

			$result = $rag_engine->process_document( $source, $document->source_type, $document_id );

			// Check if embeddings were actually generated
			if ( empty( $result['embedding_count'] ) || $result['embedding_count'] == 0 ) {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Reprocessing completed but no embeddings were generated. Please check your API key and embedding model configuration in Settings.', 'knowvault' ),
						'details' => $result,
					)
				);
				return;
			}

			wp_send_json_success(
				array(
					'message' => esc_html__( 'Document reprocessed successfully.', 'knowvault' ),
					'result'  => $result,
				)
			);

		} catch ( \Exception $e ) {

			// Check if this is a "Not Found" error from Pinecone (which is often expected)
			if ( strpos( $e->getMessage(), 'Not Found' ) !== false ) {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Document reprocessing failed: The document embeddings were not found in the vector database. This may indicate the document was never properly processed or has already been deleted. Please try processing the document again.', 'knowvault' ),
						'details' => $e->getMessage(),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Document reprocessing failed: ', 'knowvault' ) . $e->getMessage(),
						'details' => $e->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Handle getting document error details
	 */
	public function handle_get_document_error_details() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing document ID.', 'knowvault' ) ) );
		}

		$document_id = intval( $_POST['document_id'] );

		try {
			global $wpdb;

			// Get document details
			$document = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ai_botkit_documents WHERE id = %d",
					$document_id
				)
			);

			if ( ! $document ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Document not found.', 'knowvault' ) ) );
			}

			// Get error metadata
			$error_metadata = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->prefix}ai_botkit_document_metadata 
                 WHERE document_id = %d AND meta_key IN ('error', 'error_time', 'processing_time')",
					$document_id
				)
			);

			$error_details = array();
			foreach ( $error_metadata as $meta ) {
				$error_details[ $meta->meta_key ] = $meta->meta_value;
			}

			wp_send_json_success(
				array(
					'document'      => $document,
					'error_details' => $error_details,
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle URL import for knowledge base
	 */
	public function handle_url_import() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['url'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields.', 'knowvault' ) ) );
		}

		$url        = esc_url_raw( $_POST['url'] );
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$chatbot_id = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;

		// Debug logging

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid URL.', 'knowvault' ) ) );
		}

		// Skip URL accessibility check for now - many sites block automated requests
		// The URL will be processed during the actual import process
		// This prevents 403 errors from academic sites and other protected domains

		if ( empty( trim( $title ) ) ) {
			// Try to extract title from the URL only if no title provided
			$title = $this->extract_title_from_url( $url );
		}

		// Save document in database
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'ai_botkit_documents',
			array(
				'title'       => $title,
				'source_type' => 'url',
				'file_path'   => $url,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save document.', 'knowvault' ) ) );
		}

		$document_id = $wpdb->insert_id;

		// If chatbot_id is provided, create the relationship
		if ( $chatbot_id > 0 ) {
			try {
				$chatbot = new \AI_BotKit\Models\Chatbot( $chatbot_id );
				if ( method_exists( $chatbot, 'add_content' ) ) {
					$chatbot->add_content( 'document', $document_id );
				}
			} catch ( \Exception $e ) {
				// Don't fail the import if linking fails
			}
		}

		// Process the queue (may take a few seconds for URL fetch and embedding).
		try {
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);
			$rag_engine->process_queue();
		} catch ( \Exception $e ) {
			// Non-fatal; document remains pending for later processing.
		}

		wp_send_json_success(
			array(
				'message'     => esc_html__( 'URL imported successfully.', 'knowvault' ),
				'document_id' => $document_id,
			)
		);
	}

	/**
	 * Extract title from URL by fetching the page content
	 *
	 * @param string $url URL to extract title from
	 * @return string Extracted title or fallback title
	 */
	private function extract_title_from_url( $url ) {
		try {
			// Fetch the page content
			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 15,
					'sslverify'  => true,
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					'headers'    => array(
						'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
						'Accept-Language'           => 'en-US,en;q=0.5',
						'Accept-Encoding'           => 'gzip, deflate',
						'Connection'                => 'keep-alive',
						'Upgrade-Insecure-Requests' => '1',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $this->get_fallback_title( $url );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				// Log the response code for debugging but don't fail
				return $this->get_fallback_title( $url );
			}

			$content      = wp_remote_retrieve_body( $response );
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );

			// Only process HTML content
			if ( strpos( $content_type, 'text/html' ) === false ) {
				return $this->get_fallback_title( $url );
			}

			// Extract title using regex
			$title = $this->extract_title_from_html( $content );

			if ( ! empty( $title ) ) {
				return $title;
			}

			return $this->get_fallback_title( $url );

		} catch ( \Exception $e ) {
			return $this->get_fallback_title( $url );
		}
	}

	/**
	 * Extract title from HTML content
	 *
	 * @param string $html HTML content
	 * @return string Extracted title or empty string
	 */
	private function extract_title_from_html( $html ) {
		// Try to find title tag
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			$title = trim( $matches[1] );
			// Clean up the title
			$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$title = strip_tags( $title );
			$title = preg_replace( '/\s+/', ' ', $title );
			$title = trim( $title );

			// Remove common suffixes like " | Site Name"
			$title = preg_replace( '/\s*[|\-–—]\s*.*$/', '', $title );
			$title = preg_replace( '/\s*:\s*.*$/', '', $title );

			if ( ! empty( $title ) && strlen( $title ) > 3 ) {
				return $title;
			}
		}

		// Try to find Open Graph title
		if ( preg_match( '/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
			$title = trim( $matches[1] );
			if ( ! empty( $title ) && strlen( $title ) > 3 ) {
				return html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		// Try to find Twitter title
		if ( preg_match( '/<meta[^>]*name=["\']twitter:title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
			$title = trim( $matches[1] );
			if ( ! empty( $title ) && strlen( $title ) > 3 ) {
				return html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		return '';
	}

	/**
	 * Get fallback title from URL
	 *
	 * @param string $url URL
	 * @return string Fallback title
	 */
	private function get_fallback_title( $url ) {
		$parsed_url = parse_url( $url );
		$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : 'Unknown Site';

		// Remove www. prefix
		$host = preg_replace( '/^www\./', '', $host );

		// Capitalize first letter
		$host = ucfirst( $host );

		return $host . ' - ' . esc_html__( 'Web Page', 'knowvault' );
	}

	/**
	 * Handle WordPress content import for knowledge base
	 */
	public function handle_wp_content_import() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['post_ids'] ) || ! is_array( $_POST['post_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No content selected.', 'knowvault' ) ) );
		}

		$post_ids       = array_map( 'intval', $_POST['post_ids'] );
		$imported_count = 0;
		$failed_count   = 0;
		$document_ids   = array();

		global $wpdb;
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				++$failed_count;
				continue;
			}

			// Save document in database
			$result = $wpdb->insert(
				$wpdb->prefix . 'ai_botkit_documents',
				array(
					'title'       => $post->post_title,
					'source_type' => 'post',
					'source_id'   => $post_id,
					'status'      => 'pending',
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $result ) {
				++$imported_count;
				$document_ids[] = $wpdb->insert_id;
			} else {
				++$failed_count;
			}
		}

		wp_send_json_success(
			array(
				'message'        => sprintf(
					__( '%1$d items imported successfully. %2$d items failed.', 'knowvault' ),
					$imported_count,
					$failed_count
				),
				'imported_count' => $imported_count,
				'failed_count'   => $failed_count,
				'document_ids'   => $document_ids,
			)
		);
	}

	/**
	 * Handle deleting document from knowledge base
	 */
	public function handle_delete_document() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$document_id = intval( $_POST['document_id'] );

		if ( $document_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid document ID.', 'knowvault' ) ) );
		}

		global $wpdb;
		$result = $wpdb->delete(
			$wpdb->prefix . 'ai_botkit_documents',
			array( 'id' => $document_id ),
			array( '%d' )
		);

		// Get chunk IDs for this document
		$chunk_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ai_botkit_chunks WHERE document_id = %d",
				$document_id
			)
		);

		// Delete embeddings for these chunks
		if ( ! empty( $chunk_ids ) ) {
			$chunk_ids_placeholders = implode( ',', array_fill( 0, count( $chunk_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}ai_botkit_embeddings WHERE chunk_id IN ($chunk_ids_placeholders)",
					$chunk_ids
				)
			);
		}

		// Delete chunks
		$wpdb->delete(
			$wpdb->prefix . 'ai_botkit_chunks',
			array( 'document_id' => $document_id ),
			array( '%d' )
		);

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Document deleted successfully.', 'knowvault' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete document.', 'knowvault' ) ) );
		}
	}

	/**
	 * Handle adding documents to chatbot
	 */
	public function handle_add_chatbot_documents() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id   = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;
		$document_ids = isset( $_POST['document_ids'] ) ? array_map( 'intval', $_POST['document_ids'] ) : array();

		if ( ! $chatbot_id || empty( $document_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'knowvault' ) ) );
		}

		try {
			$chatbot = new Chatbot( $chatbot_id );

			foreach ( $document_ids as $doc_id ) {
				$chatbot->add_content( 'document', $doc_id );
			}

			// Get updated list of associated documents
			$documents = $chatbot->get_associated_content( 'document' );

			wp_send_json_success(
				array(
					'message'   => __( 'Documents added successfully.', 'knowvault' ),
					'documents' => $documents,
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle removing document from chatbot
	 */
	public function handle_remove_chatbot_document() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id  = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;
		$document_id = isset( $_POST['document_id'] ) ? intval( $_POST['document_id'] ) : 0;

		if ( ! $chatbot_id || ! $document_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		try {
			$chatbot = new Chatbot( $chatbot_id );

			if ( $chatbot->remove_content( 'document', $document_id ) ) {
				// Get updated list of associated documents
				$documents = $chatbot->get_associated_content( 'document' );

				wp_send_json_success(
					array(
						'message'   => __( 'Document removed successfully.', 'knowvault' ),
						'documents' => $documents,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to remove document.', 'knowvault' ) ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle getting chatbot documents
	 */
	public function handle_get_chatbot_documents() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;

		if ( ! $chatbot_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		try {
			$chatbot   = new Chatbot( $chatbot_id );
			$documents = $chatbot->get_associated_content( 'document' );

			wp_send_json_success(
				array(
					'documents' => $documents,
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle getting available documents for selection
	 */
	public function handle_get_available_documents() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		global $wpdb;
		$chatbot_id = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;
		$response   = array();
		// Get all documents
		if ( $chatbot_id > 0 ) {
			$documents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.id, d.title, d.source_type, d.status, d.file_path, d.created_at
                FROM {$wpdb->prefix}ai_botkit_documents as d
                LEFT JOIN {$wpdb->prefix}ai_botkit_content_relationships cr
                    ON d.id = cr.target_id
                WHERE cr.source_type = 'chatbot'
                AND cr.source_id = %d
                AND cr.relationship_type = 'knowledge_base'
                ORDER BY d.created_at DESC",
					$chatbot_id
				),
				ARRAY_A
			);
		} else {
			$documents = array();
		}

		wp_send_json_success(
			array(
				'documents' => $documents,
			)
		);
	}

	/**
	 * Handle avatar upload for chatbot
	 */
	public function handle_upload_avatar() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_FILES['avatar'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded.', 'knowvault' ) ) );
		}

		$file = $_FILES['avatar'];

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => esc_html__( 'File upload failed.', 'knowvault' ) ) );
		}

		// Validate file type
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type. Please upload an image (JPG, PNG, or GIF).', 'knowvault' ) ) );
		}

		$filetype = wp_check_filetype( $file['name'] );
		if ( empty( $filetype['ext'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type.', 'knowvault' ) ) );
		}

		// Upload the file to WordPress media library
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		// Create attachment post
		$attachment = array(
			'post_mime_type' => $file['type'],
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attach_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save image.', 'knowvault' ) ) );
		}

		// Generate attachment metadata and update
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Avatar uploaded successfully.', 'knowvault' ),
				'id'      => $attach_id,
				'url'     => $upload['url'],
			)
		);
	}

	/**
	 * Handle background image upload for chatbot
	 */
	public function handle_upload_background_image() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_FILES['background_image'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No file uploaded.', 'knowvault' ) ) );
		}

		$file = $_FILES['background_image'];

		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => esc_html__( 'File upload failed.', 'knowvault' ) ) );
		}

		// Validate file type
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		if ( ! in_array( $file['type'], $allowed_types ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type. Please upload an image (JPG, PNG, or GIF).', 'knowvault' ) ) );
		}

		$filetype = wp_check_filetype( $file['name'] );
		if ( empty( $filetype['ext'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid file type.', 'knowvault' ) ) );
		}

		// Upload the file to WordPress media library
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ) );
		}

		// Create attachment post
		$attachment = array(
			'post_mime_type' => $file['type'],
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attach_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save image.', 'knowvault' ) ) );
		}

		// Generate attachment metadata and update
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Background image uploaded successfully.', 'knowvault' ),
				'id'      => $attach_id,
				'url'     => $upload['url'],
			)
		);
	}

	/**
	 * Handle enabling chatbot sitewide
	 */
	public function handle_enable_chatbot_sitewide() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id              = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;
		$enable_chatbot_sitewide = isset( $_POST['enable_chatbot_sitewide'] ) ? intval( $_POST['enable_chatbot_sitewide'] ) : 0;

		if ( $chatbot_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid chatbot ID.', 'knowvault' ) ) );
		}

		if ( $enable_chatbot_sitewide ) {
			update_option( 'ai_botkit_chatbot_sitewide_enabled', $chatbot_id );
			wp_send_json_success( array( 'message' => esc_html__( 'Chatbot enabled sitewide.', 'knowvault' ) ) );
		} else {
			update_option( 'ai_botkit_chatbot_sitewide_enabled', 0 );
			wp_send_json_error( array( 'message' => esc_html__( 'Chatbot disabled sitewide.', 'knowvault' ) ) );
		}
	}

	/**
	 * Handle enabling chatbot
	 */
	public function handle_enable_chatbot() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$chatbot_id     = isset( $_POST['chatbot_id'] ) ? intval( $_POST['chatbot_id'] ) : 0;
		$enable_chatbot = isset( $_POST['enable_chatbot'] ) ? intval( $_POST['enable_chatbot'] ) : 0;

		if ( $chatbot_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid chatbot ID.', 'knowvault' ) ) );
		}

		// Update chatbot publish status
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'ai_botkit_chatbots',
			array( 'active' => $enable_chatbot ),
			array( 'id' => $chatbot_id )
		);

		if ( $result ) {
			if ( $enable_chatbot == 1 ) {
				wp_send_json_success( array( 'message' => esc_html__( 'Chatbot enabled.', 'knowvault' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Chatbot disabled.', 'knowvault' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to enable chatbot.', 'knowvault' ) ) );
		}
	}

	/**
	 * Handle getting migration status
	 */
	public function handle_get_migration_status() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Check if Pinecone API key and host exist before proceeding
		$pinecone_api_key = get_option( 'ai_botkit_pinecone_api_key', '' );
		$pinecone_host    = get_option( 'ai_botkit_pinecone_host', '' );

		if ( empty( $pinecone_api_key ) || empty( $pinecone_host ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Pinecone API key and host are required. Please configure Pinecone in Settings to use migration features.', 'knowvault' ) ) );
		}

		// Test Pinecone credentials validity
		try {
			$pinecone_database = new \AI_BotKit\Core\Pinecone_Database();
			if ( ! $pinecone_database->is_configured() ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Pinecone is not properly configured. Please check your API key and host in Settings.', 'knowvault' ) ) );
			}

			// Test connection by making a simple API call
			$test_result = $pinecone_database->test_connection();

		} catch ( \AI_BotKit\Core\Pinecone_Exception $e ) {
			$error_message = $e->getMessage();
			if ( strpos( $error_message, '401' ) !== false || strpos( $error_message, 'Unauthorized' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid Pinecone API key. Please check your credentials in Settings.', 'knowvault' ) ) );
			} elseif ( strpos( $error_message, '403' ) !== false || strpos( $error_message, 'Forbidden' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Pinecone API access denied. Please check your API key permissions in Settings.', 'knowvault' ) ) );
			} elseif ( strpos( $error_message, '404' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid Pinecone host URL. Please check your host configuration in Settings.', 'knowvault' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Pinecone connection failed: ', 'knowvault' ) . $error_message ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to validate Pinecone credentials: ', 'knowvault' ) . $e->getMessage() ) );
		}

		try {
			// Create required dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			$migration_manager = new \AI_BotKit\Core\Migration_Manager(
				$rag_engine,
				$vector_database
			);

			$status = $migration_manager->get_migration_status();
			wp_send_json_success( $status );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle getting content types for migration
	 */
	public function handle_get_content_types() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Check if Pinecone API key and host exist before proceeding
		$pinecone_api_key = get_option( 'ai_botkit_pinecone_api_key', '' );
		$pinecone_host    = get_option( 'ai_botkit_pinecone_host', '' );

		if ( empty( $pinecone_api_key ) || empty( $pinecone_host ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Pinecone API key and host are required. Please configure Pinecone in Settings to use migration features.', 'knowvault' ) ) );
		}

		try {
			// Create required dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			$migration_manager = new \AI_BotKit\Core\Migration_Manager(
				$rag_engine,
				$vector_database
			);

			$content_types = $migration_manager->get_available_content_types();
			wp_send_json_success( $content_types );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle starting migration
	 */
	public function handle_start_migration() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Check if Pinecone API key and host exist before proceeding
		$pinecone_api_key = get_option( 'ai_botkit_pinecone_api_key', '' );
		$pinecone_host    = get_option( 'ai_botkit_pinecone_host', '' );

		if ( empty( $pinecone_api_key ) || empty( $pinecone_host ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Pinecone API key and host are required. Please configure Pinecone in Settings to use migration features.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['options'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Migration options are required.', 'knowvault' ) ) );
		}

		try {
			// $_POST['options'] is already an array when sent via AJAX
			$options = $_POST['options'];

			if ( ! is_array( $options ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid migration options.', 'knowvault' ) ) );
			}

			// Create required dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			$migration_manager = new \AI_BotKit\Core\Migration_Manager(
				$rag_engine,
				$vector_database
			);

			$result = $migration_manager->start_migration( $options );
			wp_send_json_success( $result );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle downloading migration log
	 */
	public function handle_download_migration_log() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'knowvault' ) );
		}

		$log_file = isset( $_GET['log_file'] ) ? sanitize_file_name( $_GET['log_file'] ) : '';

		if ( empty( $log_file ) ) {
			wp_die( __( 'Log file not specified.', 'knowvault' ) );
		}

		$log_path = WP_CONTENT_DIR . '/ai-botkit-logs/' . $log_file;

		if ( ! file_exists( $log_path ) ) {
			wp_die( __( 'Log file not found.', 'knowvault' ) );
		}

		// Security check - ensure file is within logs directory
		$real_path = realpath( $log_path );
		$logs_dir  = realpath( WP_CONTENT_DIR . '/ai-botkit-logs' );

		if ( strpos( $real_path, $logs_dir ) !== 0 ) {
			wp_die( __( 'Invalid log file path.', 'knowvault' ) );
		}

		// Set headers for download
		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . basename( $log_file ) . '"' );
		header( 'Content-Length: ' . filesize( $log_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output file contents
		readfile( $log_path );
		exit;
	}

	/**
	 * Handle clearing stuck migration lock
	 */
	public function handle_clear_migration_lock() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		delete_transient( 'ai_botkit_migration_in_progress' );
		wp_send_json_success( array( 'message' => esc_html__( 'Migration lock cleared successfully.', 'knowvault' ) ) );
	}

	/**
	 * Handle clearing database
	 */
	public function handle_clear_database() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Get database type first to determine if Pinecone check is needed
		$database = sanitize_text_field( $_POST['database'] ?? '' );

		// Only check Pinecone configuration for migration operations, not for clearing
		if ( ! in_array( $database, array( 'local', 'pinecone', 'knowledge_base' ) ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid database specified.', 'knowvault' ) ) );
		}

		try {

			// Additional server-side validation for Pinecone operations
			if ( $database === 'pinecone' ) {
				// Check if Pinecone is configured
				$pinecone_api_key = get_option( 'ai_botkit_pinecone_api_key' );
				$pinecone_host    = get_option( 'ai_botkit_pinecone_host' );

				if ( empty( $pinecone_api_key ) || empty( $pinecone_host ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Pinecone API key and host are required to clear Pinecone database.', 'knowvault' ) ) );
				}
			}

			// Create required dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			$migration_manager = new \AI_BotKit\Core\Migration_Manager(
				$rag_engine,
				$vector_database
			);

			$result = $migration_manager->clear_database( $database );
			wp_send_json_success( $result );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle analytics data request
	 */
	public function handle_get_analytics_data() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		try {
			$time_range = isset( $_POST['time_range'] ) ? sanitize_text_field( $_POST['time_range'] ) : '7 days';

			// Calculate date range
			if ( $time_range === '7 days' ) {
				$start_date = date( 'Y-m-d', strtotime( '-7 days', current_time( 'timestamp' ) ) );
				$end_date   = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} elseif ( $time_range === '30 days' ) {
				$start_date = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );
				$end_date   = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} elseif ( $time_range === '90 days' ) {
				$start_date = date( 'Y-m-d', strtotime( '-90 days', current_time( 'timestamp' ) ) );
				$end_date   = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} elseif ( $time_range === '1 year' ) {
				$start_date = date( 'Y-m-d', strtotime( '-1 year', current_time( 'timestamp' ) ) );
				$end_date   = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			} else {
				$start_date = date( 'Y-m-d', strtotime( '-7 days', current_time( 'timestamp' ) ) );
				$end_date   = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );
			}

			// Get analytics data
			$analytics = new \AI_BotKit\Monitoring\Analytics( new \AI_BotKit\Core\Unified_Cache_Manager() );
			$data      = $analytics->get_dashboard_data(
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);

			wp_send_json_success(
				array(
					'overview'    => $data['overview'],
					'time_series' => $data['time_series'],
					'top_queries' => $data['top_queries'],
					'error_rates' => $data['error_rates'],
					'performance' => $data['performance'],
					'time_range'  => $time_range,
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle knowledge base data request
	 */
	public function handle_get_knowledge_base_data() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		try {
			$type           = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'all';
			$current_page   = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
			$search_term    = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
			$items_per_page = 20;
			$offset         = ( $current_page - 1 ) * $items_per_page;

			global $wpdb;

			// Build WHERE clause based on filter type and search term
			$where_conditions = array();
			$where_values     = array();

			if ( $type !== 'all' ) {
				$where_conditions[] = 'source_type = %s';
				$where_values[]     = $type;
			}

			if ( ! empty( $search_term ) ) {
				$where_conditions[] = 'title LIKE %s';
				$search_like        = '%' . $wpdb->esc_like( $search_term ) . '%';
				$where_values[]     = $search_like;
			}

			$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

			// Calculate total documents based on filter type and search term
			$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents " . $where_clause;
			if ( ! empty( $where_values ) ) {
				$total_documents = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) );
			} else {
				$total_documents = $wpdb->get_var( $count_query );
			}

			// Get documents with pagination
			$query        = "SELECT * FROM {$wpdb->prefix}ai_botkit_documents " . $where_clause . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
			$query_values = array_merge( $where_values, array( $items_per_page, $offset ) );

			if ( ! empty( $query_values ) ) {
				$documents = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) );
			} else {
				$documents = $wpdb->get_results( $wpdb->prepare( $query, $items_per_page, $offset ) );
			}

			$total_pages = ceil( $total_documents / $items_per_page );

			// Format documents for response
			$formatted_documents = array();
			foreach ( $documents as $document ) {
				$document_type = $document->source_type;
				$document_name = $document->title;
				$document_date = $document->created_at;

				if ( 'post' == $document_type ) {
					$document_url = '<a href="' . get_permalink( $document->source_id ) . '" target="_blank">' . get_the_title( $document->source_id ) . '</a>';
				} elseif ( 'url' == $document_type ) {
					$document_url = '<a href="' . $document->file_path . '" target="_blank">' . esc_html__( 'Visit URL', 'knowvault' ) . '</a>';
				} elseif ( 'file' == $document_type ) {
					$document_url = size_format( filesize( $document->file_path ), 2 );
				} else {
					// Handle other document types (LearnDash courses, WooCommerce products, etc.)
					$document_url = !empty($document->source_id) ? '<a href="' . get_permalink($document->source_id) . '" target="_blank">' . esc_html__('View', 'knowvault') . '</a>' : esc_html__('N/A', 'knowvault');
				}

				$status_badge = '';
				if ( 'pending' == $document->status ) {
					$status_badge = '<span class="ai-botkit-badge ai-botkit-badge-warning">' . esc_html__( 'Pending', 'knowvault' ) . '</span>';
				} elseif ( 'processing' == $document->status ) {
					$status_badge = '<span class="ai-botkit-badge ai-botkit-badge-info">' . esc_html__( 'Processing', 'knowvault' ) . '</span>';
				} elseif ( 'completed' == $document->status ) {
					$status_badge = '<span class="ai-botkit-badge ai-botkit-badge-success">' . esc_html__( 'Completed', 'knowvault' ) . '</span>';
				} elseif ( 'failed' == $document->status ) {
					$status_badge = '<span class="ai-botkit-badge ai-botkit-badge-danger ai-botkit-error-clickable" data-document-id="' . $document->id . '" style="cursor: pointer;" title="Click to view error details">' . esc_html__( 'Failed', 'knowvault' ) . '</span>';
				} else {
					// Default case for NULL or unknown status
					$status_badge = '<span class="ai-botkit-badge ai-botkit-badge-secondary">' . esc_html__( 'Unknown', 'knowvault' ) . '</span>';
				}

				// Add reprocess button for completed documents
				$actions = '';
				if ( 'completed' == $document->status ) {
					// Set appropriate reprocess label based on document type
					$reprocess_title = '';
					if ( 'file' == $document_type ) {
						$reprocess_title = esc_attr__( 'Reprocess file', 'knowvault' );
					} elseif ( 'post' == $document_type ) {
						$reprocess_title = esc_attr__( 'Reprocess post', 'knowvault' );
					} elseif ( 'url' == $document_type ) {
						$reprocess_title = esc_attr__( 'Reprocess URL', 'knowvault' );
					} else {
						$reprocess_title = esc_attr__( 'Reprocess document', 'knowvault' );
					}
					$actions = '<button class="ai-botkit-reprocess-btn" data-id="' . esc_attr( $document->id ) . '" data-type="' . esc_attr( $document_type ) . '" title="' . $reprocess_title . '"><i class="ti ti-refresh"></i></button>';
				}

				$formatted_documents[] = array(
					'id'      => $document->id,
					'name'    => strlen( $document_name ) > 20 ? substr( $document_name, 0, 20 ) . '...' : esc_html( $document_name ),
					'type'    => esc_html( $document_type ),
					'status'  => $status_badge,
					'date'    => esc_html( $document_date ),
					'url'     => 'file' == $document_type ? esc_html( $document_url ) : $document_url,
					'actions' => $actions,
				);
			}

			wp_send_json_success(
				array(
					'documents'  => $formatted_documents,
					'pagination' => array(
						'current_page'    => $current_page,
						'total_pages'     => $total_pages,
						'total_documents' => $total_documents,
						'items_per_page'  => $items_per_page,
					),
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle chatbot sessions list (with optional search in conversation messages).
	 * Used on the Chatbot Sessions admin page; avoids calling KB data by mistake.
	 *
	 * @since 2.0.0
	 */
	public function handle_get_chatbot_sessions() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_ai_botkit' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$bot_id   = isset( $_POST['bot_id'] ) ? absint( $_POST['bot_id'] ) : 0;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! $bot_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid chatbot.', 'knowvault' ) ) );
		}

		global $wpdb;
		$conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
		$messages_table      = $wpdb->prefix . 'ai_botkit_messages';

		if ( strlen( $search ) < 2 ) {
			// No search or too short: return paginated sessions for this chatbot.
			$sessions = \AI_BotKit\Models\Conversation::get_by_chatbot( $bot_id, $per_page, $offset );
		} else {
			// Search in message content for this chatbot; return matching conversations.
			if ( ! class_exists( '\AI_BotKit\Features\Search_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Search is not available. Run database migration if needed.', 'knowvault' ) ) );
			}

			$search_handler = new \AI_BotKit\Features\Search_Handler();
			$search_result  = $search_handler->search( $search, array( 'chatbot_id' => $bot_id ), $page, $per_page );

			if ( ! empty( $search_result['error'] ) ) {
				wp_send_json_error( array( 'message' => $search_result['error'] ) );
			}

			$results          = isset( $search_result['results'] ) ? $search_result['results'] : array();
			$conversation_ids = array_unique( array_filter( wp_list_pluck( $results, 'conversation_id' ) ) );

			if ( empty( $conversation_ids ) ) {
				wp_send_json_success(
					array(
						'sessions'   => array(),
						'pagination' => array(
							'current_page' => $page,
							'total_pages'  => 0,
							'total'        => 0,
						),
					)
				);
			}

			$ids_placeholders = implode( ',', array_fill( 0, count( $conversation_ids ), '%d' ) );
			$sql              = "SELECT c.id, c.user_id, c.updated_at,
                (SELECT COUNT(*) FROM {$messages_table} WHERE conversation_id = c.id) AS message_count
                FROM {$conversations_table} AS c
                WHERE c.id IN ($ids_placeholders) AND c.chatbot_id = %d
                ORDER BY c.updated_at DESC";
			$params           = array_merge( $conversation_ids, array( $bot_id ) );
			$rows             = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

			$sessions = array();
			foreach ( $rows as $row ) {
				$sessions[] = array(
					'id'            => (int) $row['id'],
					'user_id'       => (int) $row['user_id'],
					'updated_at'    => $row['updated_at'],
					'message_count' => (int) $row['message_count'],
				);
			}

			$total       = isset( $search_result['total'] ) ? (int) $search_result['total'] : count( $sessions );
			$total_pages = isset( $search_result['pages'] ) ? (int) $search_result['pages'] : 1;

			$this->send_chatbot_sessions_response( $sessions, $bot_id, $page, $total_pages, $total, $per_page );
			return;
		}

		$total_sessions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$conversations_table} WHERE chatbot_id = %d",
				$bot_id
			)
		);
		$total_pages    = (int) ceil( $total_sessions / $per_page );
		$this->send_chatbot_sessions_response( $sessions, $bot_id, $page, $total_pages, $total_sessions, $per_page );
	}

	/**
	 * Send JSON response for chatbot sessions list.
	 *
	 * @param array $sessions    Session rows (id, user_id, updated_at, message_count).
	 * @param int   $bot_id      Chatbot ID.
	 * @param int   $page        Current page.
	 * @param int   $total_pages Total pages.
	 * @param int   $total       Total count.
	 * @param int   $per_page    Per page.
	 */
	private function send_chatbot_sessions_response( $sessions, $bot_id, $page, $total_pages, $total, $per_page ) {
		$nonce     = wp_create_nonce( 'ai_botkit_chatbots' );
		$formatted = array();
		foreach ( $sessions as $s ) {
			$session_id = isset( $s['id'] ) ? $s['id'] : $s['id'];
			$user_id    = isset( $s['user_id'] ) ? (int) $s['user_id'] : 0;
			$user_name  = get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true );
			$user_name  = trim( $user_name );
			if ( $user_id === 0 || $user_name === '' ) {
				$user_name = $user_id === 0 ? __( 'Guest User', 'knowvault' ) : ( 'User #' . $user_id );
			}
			$updated_at = isset( $s['updated_at'] ) ? $s['updated_at'] : ( isset( $s['last_activity'] ) ? $s['last_activity'] : '' );
			$msg_count  = isset( $s['message_count'] ) ? $s['message_count'] : 0;
			if ( ! isset( $s['message_count'] ) && isset( $s['id'] ) ) {
				global $wpdb;
				$msg_count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_messages WHERE conversation_id = %d",
						$s['id']
					)
				);
			}
			$session_url = admin_url( 'admin.php?page=ai-botkit&tab=chatbots&bot_id=' . $bot_id . '&chat_session_id=' . $session_id . '&nonce=' . $nonce );
			$formatted[] = array(
				'id'            => $session_id,
				'user_name'     => $user_name,
				'updated_at'    => $updated_at,
				'message_count' => $msg_count,
				'session_url'   => $session_url,
			);
		}

		wp_send_json_success(
			array(
				'sessions'   => $formatted,
				'pagination' => array(
					'current_page' => $page,
					'total_pages'  => $total_pages,
					'total'        => $total,
					'per_page'     => $per_page,
				),
			)
		);
	}

	/**
	 * Handle testing Pinecone connection
	 */
	public function handle_test_pinecone_connection() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		// Get credentials from POST data
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		$host    = sanitize_text_field( $_POST['host'] ?? '' );

		if ( empty( $api_key ) || empty( $host ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API key and host are required.', 'knowvault' ) ) );
		}

		try {
			// Temporarily set the options for testing
			$original_api_key = get_option( 'ai_botkit_pinecone_api_key', '' );
			$original_host    = get_option( 'ai_botkit_pinecone_host', '' );

			update_option( 'ai_botkit_pinecone_api_key', $api_key );
			update_option( 'ai_botkit_pinecone_host', $host );

			// Test the connection
			$pinecone_database = new \AI_BotKit\Core\Pinecone_Database();

			if ( ! $pinecone_database->is_configured() ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Pinecone is not properly configured.', 'knowvault' ) ) );
			}

			// Test connection by making a simple API call
			$test_result = $pinecone_database->test_connection();

			// Restore original values (don't save the test values)
			update_option( 'ai_botkit_pinecone_api_key', $original_api_key );
			update_option( 'ai_botkit_pinecone_host', $original_host );

			wp_send_json_success(
				array(
					'message' => esc_html__( 'Connection successful! Your Pinecone credentials are valid.', 'knowvault' ),
					'status'  => 'success',
				)
			);

		} catch ( \AI_BotKit\Core\Pinecone_Exception $e ) {
			// Restore original values on error
			update_option( 'ai_botkit_pinecone_api_key', $original_api_key );
			update_option( 'ai_botkit_pinecone_host', $original_host );

			$error_message = $e->getMessage();
			if ( strpos( $error_message, '401' ) !== false || strpos( $error_message, 'Unauthorized' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid API key. Please check your Pinecone API key.', 'knowvault' ) ) );
			} elseif ( strpos( $error_message, '403' ) !== false || strpos( $error_message, 'Forbidden' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'API access denied. Please check your API key permissions.', 'knowvault' ) ) );
			} elseif ( strpos( $error_message, '404' ) !== false ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid host URL. Please check your Pinecone host configuration.', 'knowvault' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Connection failed: ', 'knowvault' ) . $error_message ) );
			}
		} catch ( \Exception $e ) {
			// Restore original values on error
			update_option( 'ai_botkit_pinecone_api_key', $original_api_key );
			update_option( 'ai_botkit_pinecone_host', $original_host );

			wp_send_json_error( array( 'message' => esc_html__( 'Failed to test connection: ', 'knowvault' ) . $e->getMessage() ) );
		}
	}

	// =========================================================
	// Phase 2: Chat Transcripts Export Handlers (FR-240 to FR-249)
	// =========================================================

	/**
	 * Handle admin export of any conversation to PDF.
	 *
	 * Admin can export any conversation regardless of ownership.
	 *
	 * Implements: FR-240 (Admin Export), FR-241 (PDF Generation)
	 *
	 * @since 2.0.0
	 */
	public function handle_export_pdf() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

		if ( empty( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Conversation ID is required.', 'knowvault' ) ) );
		}

		try {
			// Initialize export handler.
			require_once dirname( __DIR__, 1 ) . '/features/class-export-handler.php';

			if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Export handler not available.', 'knowvault' ) ) );
			}

			$export_handler = new \AI_BotKit\Features\Export_Handler();

			// Check if dompdf is available.
			if ( ! $export_handler->is_dompdf_available() ) {
				wp_send_json_error(
					array(
						'message'        => esc_html__( 'PDF export requires the dompdf library. Please run "composer require dompdf/dompdf" in the plugin includes directory.', 'knowvault' ),
						'dompdf_missing' => true,
					)
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

			// Note: stream_pdf exits after sending file.

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle CSV export of a single conversation.
	 *
	 * @since 2.0.0
	 */
	public function handle_export_csv() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

		if ( empty( $conversation_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Conversation ID is required.', 'knowvault' ) ) );
		}

		try {
			// Initialize export handler.
			require_once dirname( __DIR__, 1 ) . '/features/class-export-handler.php';

			if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Export handler not available.', 'knowvault' ) ) );
			}

			$export_handler = new \AI_BotKit\Features\Export_Handler();

			// Get export options.
			$options = array(
				'include_metadata' => isset( $_POST['include_metadata'] ) ? (bool) $_POST['include_metadata'] : true,
			);

			// Stream CSV to browser.
			$export_handler->stream_csv( $conversation_id, $options );

			// Note: stream_csv exits after sending file.

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle batch export of multiple conversations.
	 *
	 * Implements: FR-246 (Batch Export)
	 *
	 * @since 2.0.0
	 */
	public function handle_batch_export() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$conversation_ids = isset( $_POST['conversation_ids'] ) ? array_map( 'absint', (array) $_POST['conversation_ids'] ) : array();

		if ( empty( $conversation_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No conversations selected for export.', 'knowvault' ) ) );
		}

		try {
			// Initialize export handler.
			require_once dirname( __DIR__, 1 ) . '/features/class-export-handler.php';

			if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Export handler not available.', 'knowvault' ) ) );
			}

			$export_handler = new \AI_BotKit\Features\Export_Handler();

			// Check if dompdf is available.
			if ( ! $export_handler->is_dompdf_available() ) {
				wp_send_json_error(
					array(
						'message'        => esc_html__( 'PDF export requires the dompdf library. Please run "composer require dompdf/dompdf" in the plugin includes directory.', 'knowvault' ),
						'dompdf_missing' => true,
					)
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

			// For small batches (5 or less), process synchronously.
			if ( count( $conversation_ids ) <= 5 ) {
				$batch_id = $export_handler->schedule_export( $conversation_ids, $options );
				$result   = $export_handler->process_batch_export( $batch_id );

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				// Get upload URL for the ZIP file.
				$upload_dir   = wp_upload_dir();
				$download_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $result );

				wp_send_json_success(
					array(
						'message'      => sprintf(
							/* translators: %d: number of conversations exported */
							esc_html__( '%d conversation(s) exported successfully.', 'knowvault' ),
							count( $conversation_ids )
						),
						'download_url' => $download_url,
						'batch_id'     => $batch_id,
						'status'       => 'completed',
					)
				);
			} else {
				// For larger batches, schedule async processing.
				$batch_id = $export_handler->schedule_export( $conversation_ids, $options );

				// Schedule background processing.
				add_action(
					'ai_botkit_process_batch_export',
					function ( $batch_id ) use ( $export_handler ) {
						$export_handler->process_batch_export( $batch_id );
					}
				);

				// Trigger via WP-Cron or immediately if possible.
				if ( ! wp_next_scheduled( 'ai_botkit_process_batch_export', array( $batch_id ) ) ) {
					wp_schedule_single_event( time(), 'ai_botkit_process_batch_export', array( $batch_id ) );
				}

				wp_send_json_success(
					array(
						'message'  => sprintf(
							/* translators: %d: number of conversations */
							esc_html__( 'Batch export of %d conversations has been scheduled. You will be notified when it is ready.', 'knowvault' ),
							count( $conversation_ids )
						),
						'batch_id' => $batch_id,
						'status'   => 'processing',
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle checking batch export status.
	 *
	 * Implements: FR-245 (Export Progress Indicator)
	 *
	 * @since 2.0.0
	 */
	public function handle_export_status() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';

		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Batch ID is required.', 'knowvault' ) ) );
		}

		try {
			// Initialize export handler.
			require_once dirname( __DIR__, 1 ) . '/features/class-export-handler.php';

			if ( ! class_exists( 'AI_BotKit\Features\Export_Handler' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Export handler not available.', 'knowvault' ) ) );
			}

			$export_handler = new \AI_BotKit\Features\Export_Handler();
			$status         = $export_handler->get_export_status( $batch_id );

			if ( is_wp_error( $status ) ) {
				wp_send_json_error( array( 'message' => $status->get_error_message() ) );
			}

			wp_send_json_success( $status );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle bulk delete documents
	 *
	 * @since 2.0.4
	 */
	public function handle_bulk_delete() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_ids'] ) || ! is_array( $_POST['document_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$document_ids = array_map( 'intval', $_POST['document_ids'] );

		if ( empty( $document_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents selected.', 'knowvault' ) ) );
		}

		global $wpdb;
		$deleted_count = 0;
		$errors        = array();

		$documents_table  = Table_Helper::get_table_name('documents');
		$chunks_table     = Table_Helper::get_table_name('chunks');
		$embeddings_table = Table_Helper::get_table_name('embeddings');

		foreach ( $document_ids as $document_id ) {
			if ( $document_id <= 0 ) {
				continue;
			}

			// Delete from documents table
			$result = $wpdb->delete(
				$documents_table,
				array( 'id' => $document_id ),
				array( '%d' )
			);

			if ( $result ) {
				// Get chunk IDs for this document
				$chunk_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$chunks_table} WHERE document_id = %d",
						$document_id
					)
				);

				// Delete embeddings for these chunks
				if ( ! empty( $chunk_ids ) ) {
					$chunk_ids_placeholders = implode( ',', array_fill( 0, count( $chunk_ids ), '%d' ) );
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$embeddings_table} WHERE chunk_id IN ($chunk_ids_placeholders)",
							$chunk_ids
						)
					);
				}

				// Delete chunks
				$wpdb->delete(
					$chunks_table,
					array( 'document_id' => $document_id ),
					array( '%d' )
				);

				$deleted_count++;
			} else {
				$errors[] = sprintf( esc_html__( 'Failed to delete document ID %d', 'knowvault' ), $document_id );
			}
		}

		if ( $deleted_count > 0 ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						esc_html( _n( '%d document deleted successfully.', '%d documents deleted successfully.', $deleted_count, 'knowvault' ) ),
						$deleted_count
					),
					'deleted' => $deleted_count,
					'errors'  => $errors,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents were deleted.', 'knowvault' ) ) );
		}
	}

	/**
	 * Handle bulk reprocess documents
	 *
	 * @since 2.0.4
	 */
	public function handle_bulk_reprocess() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_ids'] ) || ! is_array( $_POST['document_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$document_ids = array_map( 'intval', $_POST['document_ids'] );

		if ( empty( $document_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents selected.', 'knowvault' ) ) );
		}

		try {
			global $wpdb;

			$documents_table = Table_Helper::get_table_name('documents');

			// Create RAG Engine dependencies
			$llm_client           = new \AI_BotKit\Core\LLM_Client();
			$document_loader      = new \AI_BotKit\Core\Document_Loader();
			$text_chunker         = new \AI_BotKit\Core\Text_Chunker();
			$embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator( $llm_client );
			$vector_database      = new \AI_BotKit\Core\Vector_Database();
			$retriever            = new \AI_BotKit\Core\Retriever( $vector_database, $embeddings_generator );
			$rag_engine           = new \AI_BotKit\Core\RAG_Engine(
				$document_loader,
				$text_chunker,
				$embeddings_generator,
				$vector_database,
				$retriever,
				$llm_client
			);

			$processed_count = 0;
			$errors          = array();

			foreach ( $document_ids as $document_id ) {
				if ( $document_id <= 0 ) {
					continue;
				}

				// Get document details
				$document = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$documents_table} WHERE id = %d",
						$document_id
					)
				);

				if ( ! $document ) {
					$errors[] = sprintf( esc_html__( 'Document ID %d not found', 'knowvault' ), $document_id );
					continue;
				}

				// Determine source based on type
				if ( $document->source_type === 'url' ) {
					$source = $document->file_path;
				} elseif ( $document->source_type === 'file' ) {
					$source = $document->file_path;
				} else {
					$source = $document->source_id;
				}

				try {
					$result = $rag_engine->process_document( $source, $document->source_type, $document_id );

					if ( empty( $result['embedding_count'] ) || $result['embedding_count'] == 0 ) {
						$errors[] = sprintf( esc_html__( 'Document ID %d: No embeddings generated', 'knowvault' ), $document_id );
					} else {
						$processed_count++;
					}
				} catch ( \Exception $e ) {
					$errors[] = sprintf( esc_html__( 'Document ID %d: %s', 'knowvault' ), $document_id, $e->getMessage() );
				}
			}

			if ( $processed_count > 0 ) {
				wp_send_json_success(
					array(
						'message'   => sprintf(
							esc_html( _n( '%d document reprocessed successfully.', '%d documents reprocessed successfully.', $processed_count, 'knowvault' ) ),
							$processed_count
						),
						'processed' => $processed_count,
						'errors'    => $errors,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'No documents were reprocessed.', 'knowvault' ),
						'errors'  => $errors,
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle bulk add to bot
	 *
	 * @since 2.0.4
	 */
	public function handle_bulk_add_to_bot() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_ids'] ) || ! is_array( $_POST['document_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['chatbot_id'] ) || empty( $_POST['chatbot_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Chatbot ID is required.', 'knowvault' ) ) );
		}

		$document_ids = array_map( 'intval', $_POST['document_ids'] );
		$chatbot_id   = intval( $_POST['chatbot_id'] );

		if ( empty( $document_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents selected.', 'knowvault' ) ) );
		}

		if ( $chatbot_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid chatbot ID.', 'knowvault' ) ) );
		}

		try {
			$chatbot     = new Chatbot( $chatbot_id );
			$added_count = 0;
			$errors      = array();

			foreach ( $document_ids as $doc_id ) {
				if ( $doc_id <= 0 ) {
					continue;
				}

				try {
					$chatbot->add_content( 'document', $doc_id );
					$added_count++;
				} catch ( \Exception $e ) {
					$errors[] = sprintf( esc_html__( 'Document ID %d: %s', 'knowvault' ), $doc_id, $e->getMessage() );
				}
			}

			if ( $added_count > 0 ) {
				wp_send_json_success(
					array(
						'message' => sprintf(
							esc_html( _n( '%d document added to chatbot.', '%d documents added to chatbot.', $added_count, 'knowvault' ) ),
							$added_count
						),
						'added'   => $added_count,
						'errors'  => $errors,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => esc_html__( 'No documents were added to the chatbot.', 'knowvault' ),
						'errors'  => $errors,
					)
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Handle bulk export documents
	 *
	 * @since 2.0.4
	 */
	public function handle_bulk_export() {
		check_ajax_referer( 'ai_botkit_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'knowvault' ) ) );
		}

		if ( ! isset( $_POST['document_ids'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters.', 'knowvault' ) ) );
		}

		$document_ids = json_decode( stripslashes( $_POST['document_ids'] ), true );

		if ( ! is_array( $document_ids ) || empty( $document_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents selected.', 'knowvault' ) ) );
		}

		$document_ids = array_map( 'intval', $document_ids );

		global $wpdb;

		$documents_table = Table_Helper::get_table_name('documents');

		// Get documents
		$placeholders = implode( ',', array_fill( 0, count( $document_ids ), '%d' ) );
		$documents    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, source_type, file_path, source_id, status, created_at
				FROM {$documents_table}
				WHERE id IN ($placeholders)",
				$document_ids
			)
		);

		if ( empty( $documents ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No documents found.', 'knowvault' ) ) );
		}

		// Generate CSV
		$filename = 'knowvault-documents-' . date( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// CSV headers
		fputcsv( $output, array( 'ID', 'Title', 'Type', 'Source', 'Status', 'Created' ) );

		// CSV rows
		foreach ( $documents as $document ) {
			$source = '';
			if ( $document->source_type === 'url' ) {
				$source = $document->file_path;
			} elseif ( $document->source_type === 'file' ) {
				$source = basename( $document->file_path );
			} elseif ( ! empty( $document->source_id ) ) {
				$source = get_permalink( $document->source_id );
			}

			fputcsv(
				$output,
				array(
					$document->id,
					$document->title,
					$document->source_type,
					$source,
					$document->status,
					$document->created_at,
				)
			);
		}

		fclose( $output );
		exit;
	}
}
