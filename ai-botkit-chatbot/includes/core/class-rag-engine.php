<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Cache_Manager;
use AI_BotKit\Models\Conversation;
use AI_BotKit\Models\Chatbot;
/**
 * RAG Engine class that coordinates all components for the chatbot
 *
 * Features:
 * - Document processing pipeline
 * - Query processing
 * - Context retrieval
 * - Response generation
 * - Conversation management
 */
class RAG_Engine {
	/**
	 * Core components
	 */
	private $document_loader;
	private $text_chunker;
	private $embeddings_generator;
	private $vector_database;
	private $retriever;
	private $llm_client;
	private $cache_manager;
	private $conversation;
	private $rate_limiter;
	/**
	 * Default settings
	 */
	private const DEFAULT_SETTINGS = array(
		'max_context_chunks'     => 5,
		'min_chunk_relevance'    => 0.2, // Lowered from 0.4 to help with corrupted text
		'max_conversation_turns' => 10,
		'conversation_expiry'    => 3600, // 1 hour
		'system_prompt_template' => "
            You are a {chatbot_personality} designed for {site_name} Your primary goal is to provide accurate and helpful information to users.

            Contextual Understanding:
            - Recognizing Greetings: If the user sends a greeting like 'Hi,' 'Hello,' or 'Good morning,' respond with a warm, friendly greeting. Do not attempt to search for any context here. Keep it conversational and inviting.
            - First, check if the user is referring to a previous question. If so, look for relevant details in the chat history before considering the broader context.
            - If a question has already been answered, refer to the previous response to stay consistent.
            - If the answer isn't available or the question is unclear, ask a short clarifying question before responding.

            Response Guidelines:
            - Keep responses short, friendly, and clear (suitable for chatting). Avoid long or technical explanations.
            - No vague references like 'as mentioned above' unless a clear reference exists in the conversation.
            - Ask short clarifying questions when needed, such as:
                - What are you looking for?
                - What do you need help with?
                - Can you provide more details?
            - No guesswork – If the information isn't available, clearly state that and suggest where they can find it.
            - Strictly Use only HTML formatting for all responses:
                Use <b> for bold text
                Use <i> for italic text
                Use <br> for line breaks not '\n'
            - Generate response in the language of the user's choice. (mentioned in user query).
            - Keep your answer within 50 words
            - tone of the response should be {chat_tone}

            Attachments and images:
            - When the user attaches files or images, their URLs are included in the message. If you cannot view images directly, do not refuse to help. Ask the user to describe what is in the image and help based on their description. Never say you are \"unable to interpret\" or \"text-based only\" without offering to help if they describe the content.

            Recommendations:
            - This site may show course or product recommendation cards below your messages. You do not need to list specific courses or products in your reply; the system displays relevant recommendations. When users ask for course or product suggestions, give a short helpful reply and mention they can check the recommendations shown below. Do not say you \"don't have the ability to suggest\" courses or products.

            Context:
            {context}",
	);

	/**
	 * Initialize the engine
	 */
	public function __construct(
		Document_Loader $document_loader,
		Text_Chunker $text_chunker,
		Embeddings_Generator $embeddings_generator,
		Vector_Database $vector_database,
		Retriever $retriever,
		LLM_Client $llm_client
	) {
		$this->document_loader      = $document_loader;
		$this->text_chunker         = $text_chunker;
		$this->embeddings_generator = $embeddings_generator;
		$this->vector_database      = $vector_database;
		$this->retriever            = $retriever;
		$this->llm_client           = $llm_client;
		$this->cache_manager        = new Cache_Manager();

		// Initialize rate limiter with error handling
		try {
			$this->rate_limiter = new Rate_Limiter();
		} catch ( \Exception $e ) {
			$this->rate_limiter = null;
		}

		// process queue
		add_action( 'ai_botkit_process_queue', array( $this, 'process_queue' ) );

		do_action( 'ai_botkit_process_queue' );
	}

	/**
	 * Process a document for RAG
	 *
	 * @param string $source Source identifier (file path, URL, post ID, or content)
	 * @param string $source_type Type of source (file, url, post, learndash_course, product)
	 * @param array  $options Processing options (source_id, title, type, url, metadata, etc.)
	 * @return array Processing results
	 * @throws RAG_Engine_Exception
	 */
	public function process_document( string $source, string $source_type, array $options = array() ): array {
		try {
			// Extract or get document ID from options
			$source_id = $options['source_id'] ?? 0;
			$document_id = $this->get_or_create_document_id( $source_type, $source_id, $options );

			// Check if this is an update (document already exists)
			$is_update = $this->document_exists( $document_id );

			// If this is an update, clean up old chunks and embeddings first
			if ( $is_update ) {
				// Get old chunks before cleanup for comparison
				global $wpdb;
				$old_chunks = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, content, chunk_index FROM {$wpdb->prefix}ai_botkit_chunks WHERE document_id = %d ORDER BY chunk_index",
						$document_id
					)
				);

				try {
					$cleanup_result = $this->vector_database->delete_document_embeddings( $document_id );
				} catch ( \Exception $e ) {
					// Continue processing even if cleanup fails
					$cleanup_result = array(
						'deleted_chunks'     => 0,
						'deleted_embeddings' => 0,
					);
				}
			}

			// Load document based on source type
			$document = match ( $source_type ) {
				'file' => $this->document_loader->load_from_file( $source, $document_id ),
				'url' => $this->document_loader->load_from_url( $source, $document_id ),
				'post' => $this->document_loader->load_from_post( (int) $source, $document_id ),
				// For all other source types (learndash_course, product, etc.), treat $source as raw content
				default => array(
					'content' => $source,
					'metadata' => array_merge(
						array(
							'source_type' => $source_type,
							'document_id' => $document_id,
						),
						$options
					),
				)
			};

			// Split into chunks
			$chunks = $this->text_chunker->split_text( $document['content'], $document['metadata'] );

			// Log each new chunk content
			foreach ( $chunks as $i => $chunk ) {
			}

			// Generate embeddings
			$embeddings = $this->embeddings_generator->generate_embeddings( $chunks );

			// Store in vector database
			$stored = $this->vector_database->store_embeddings( $embeddings );

			// Verify what was actually stored in the database
			$stored_chunks = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, content, chunk_index FROM {$wpdb->prefix}ai_botkit_chunks WHERE document_id = %d ORDER BY chunk_index",
					$document_id
				)
			);

			foreach ( $stored_chunks as $i => $chunk ) {
			}

			// Mark document as processed successfully
			$wpdb->update(
				$wpdb->prefix . 'ai_botkit_documents',
				array(
					'status' => 'processed',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $document_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$result = array(
				'document_id'     => $document_id,
				'chunk_count'     => count( $chunks ),
				'embedding_count' => count( $embeddings ),
				'metadata'        => $document['metadata'],
				'is_update'       => $is_update,
				'cleanup_result'  => $is_update ? $cleanup_result : null,
			);

			return $result;

		} catch ( \Exception $e ) {
			// Mark document as failed if we have a document_id
			if ( isset( $document_id ) && $document_id > 0 ) {
				$wpdb->update(
					$wpdb->prefix . 'ai_botkit_documents',
					array(
						'status' => 'failed',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $document_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}

			throw new RAG_Engine_Exception(
				esc_html__( 'Failed to process document: ', 'knowvault' ) . esc_html( $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/**
	 * Generate a response for a chat message
	 *
	 * @param string $message User message
	 * @param string $conversation_id Conversation ID
	 * @param array  $options Response options
	 * @return array Response data
	 * @throws RAG_Engine_Exception
	 */
	public function generate_response( string $message, string $conversation_id, int $bot_id, string $context = '', array $options = array() ): array {

		try {
			$this->conversation = new Conversation( $conversation_id );

			$chatbot      = new Chatbot( $bot_id );
			$chatbot_data = $chatbot->get_data();

			if ( ! $chatbot_data ) {
				throw new RAG_Engine_Exception( esc_html__( 'Chatbot not found', 'knowvault' ) );
			}

			$model_config = json_decode( $chatbot_data['model_config'] ?? '', true ) ?? array();

			// Check for banned keywords
			$banned_keywords_json = get_option( 'ai_botkit_banned_keywords', '[]' );
			$banned_keywords      = json_decode( $banned_keywords_json, true ) ?? array();

			if ( ! empty( $banned_keywords ) ) {
				$message_lowercase = strtolower( $message );

				foreach ( $banned_keywords as $keyword ) {
					// Use word boundary to match whole words only
					$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/i';
					if ( preg_match( $pattern, $message_lowercase ) ) {
						// Return early with warning message
						$warning_message = sprintf(
							esc_html__( '⚠️ The word "%s" is not allowed in this chat.', 'knowvault' ),
							esc_html( $keyword )
						);
						// return that this word is not allowed
						return array(
							'response' => $warning_message,
							'context'  => array(),
							'metadata' => array(),
						);
					}
				}
			}

			// Check rate limits if available
			if ( $this->rate_limiter ) {
				$rate_check = $this->rate_limiter->check_user_limits();

				if ( is_array( $rate_check ) && isset( $rate_check['limited'] ) && $rate_check['limited'] ) {
					throw new RAG_Engine_Exception( $rate_check['message'] );
				}
			}

			// check if message have hi, hello etc greetings and less than 5 words
			if ( strlen( $message ) < 10 && in_array( strtolower( $message ), array( 'hi', 'hello', 'hey', 'hola', 'greetings' ) ) ) {
				$message = 'Hello! How can I assist you today? 😊';
				return array(
					'response' => wp_unslash( $message ),
					'context'  => array(),
					'metadata' => array(),
				);
			}

			// Get conversation history
			$history = $this->get_conversation_history( $conversation_id );

			// Find relevant context
			$retrieval_options = array(
				'max_results'    => $model_config['context_length'] ?? self::DEFAULT_SETTINGS['max_context_chunks'],
				'min_similarity' => $model_config['min_chunk_relevance'] ?? self::DEFAULT_SETTINGS['min_chunk_relevance'],
			);

			$context = $this->retriever->find_context( $message, $bot_id, $retrieval_options );

			// Debug logging for context retrieval
			if ( ! empty( $context ) ) {
			}

			// check if context is empty
			if ( empty( $context ) ) {
				$message_template = json_decode( $chatbot_data['messages_template'] ?? '', true ) ?? array();
				$message          = $message_template['fallback'] ?? esc_html__( 'I could not find relevant information.', 'knowvault' );
				return array(
					'response' => wp_unslash( $message ),
					'context'  => array(),
					'metadata' => array(),
				);
			}

			// Process context for enrollment status
			$current_user_id = get_current_user_id();
			$context         = $this->process_context_for_enrollment( $context, $current_user_id );

			// Format context for prompt
			$formatted_context = $this->format_context_for_prompt( $context );

			$options['model']       = $model_config['model'] ?? get_option( 'ai_botkit_chat_model', 'gpt-4o-mini' );
			$options['max_tokens']  = $model_config['max_tokens'] ?? get_option( 'ai_botkit_max_tokens', 1000 );
			$options['temperature'] = $model_config['temperature'] ?? get_option( 'ai_botkit_temperature', 0.7 );

			// Build conversation messages (include attachments in user message context)
			$messages = $this->build_conversation_messages( $message, $history, $formatted_context, $model_config, $options );

			// Generate completion
			$completion = $this->llm_client->generate_completion(
				$messages,
				$options
			);

			$this->conversation->save(
				array(
					'chatbot_id' => $bot_id,
					'session_id' => $conversation_id,
				)
			);

			// Store in conversation history
			$this->store_conversation_turn(
				$this->conversation->get_id(),
				array(
					'role'    => 'user',
					'content' => $message,
				),
				array(
					'model'  => $completion['model'],
					'tokens' => $completion['usage'],
				)
			);
			$this->store_conversation_turn(
				$this->conversation->get_id(),
				array(
					'role'    => 'assistant',
					'content' => $completion['response'],
				),
				array(
					'model'  => $completion['model'],
					'tokens' => $completion['usage'],
				)
			);

			return array(
				'response' => $completion['response'],
				'context'  => $context,
				'metadata' => array(
					'tokens'          => $completion['usage'],
					'model'           => $completion['model'],
					'context_chunks'  => count( $context ),
					'conversation_id' => $conversation_id,
					'processing_time' => microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'],
				),
			);

		} catch ( \Exception $e ) {
			throw new RAG_Engine_Exception(
				esc_html__( 'Failed to generate response: ', 'knowvault' ) . esc_html( $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/**
	 * Stream a response for a chat message
	 *
	 * @param string   $message User message
	 * @param string   $conversation_id Conversation ID
	 * @param int      $bot_id Bot ID
	 * @param callable $callback Callback for streaming chunks
	 * @param array    $options Response options
	 * @return void
	 * @throws RAG_Engine_Exception
	 */
	public function stream_response( string $message, string $conversation_id, int $bot_id, callable $callback, array $options = array() ): void {
		try {
			$this->conversation = new Conversation( $conversation_id );

			$chatbot      = new Chatbot( $bot_id );
			$chatbot_data = $chatbot->get_data();

			if ( ! $chatbot_data ) {
				throw new RAG_Engine_Exception( esc_html__( 'Chatbot not found', 'knowvault' ) );
			}

			$model_config = json_decode( $chatbot_data['model_config'] ?? '', true ) ?? array();

			// Check for banned keywords
			$banned_keywords_json = get_option( 'ai_botkit_banned_keywords', '[]' );
			$banned_keywords      = json_decode( $banned_keywords_json, true ) ?? array();

			if ( ! empty( $banned_keywords ) ) {
				$message_lowercase = strtolower( $message );

				foreach ( $banned_keywords as $keyword ) {
					// Use word boundary to match whole words only
					$pattern = '/\b' . preg_quote( $keyword, '/' ) . '\b/i';
					if ( preg_match( $pattern, $message_lowercase ) ) {
						// Return early with warning message
						$warning_message = sprintf(
							esc_html__( '⚠️ The word "%s" is not allowed in this chat.', 'knowvault' ),
							esc_html( $keyword )
						);
						$callback( $warning_message );
						// return that this word is not allowed
						return;
					}
				}
			}

			// Check rate limits if available
			if ( $this->rate_limiter ) {
				$rate_check = $this->rate_limiter->check_user_limits();

				if ( is_array( $rate_check ) && isset( $rate_check['limited'] ) && $rate_check['limited'] ) {
					throw new RAG_Engine_Exception( $rate_check['message'] );
				}
			}

			// check if message have hi, hello etc greetings and less than 5 words
			// if (strlen($message) < 10 && in_array(strtolower($message), ['hi', 'hello', 'hey', 'hola', 'greetings'])) {
			// $message = 'Hello! How can I assist you today? 😊';
			// return [
			// 'response' => wp_unslash($message),
			// 'context' => [],
			// 'metadata' => []
			// ];
			// }

			// Get conversation history
			$history = $this->get_conversation_history( $conversation_id );

			// Find relevant context
			$retrieval_options = array(
				'max_results'    => $model_config['context_length'] ?? self::DEFAULT_SETTINGS['max_context_chunks'],
				'min_similarity' => $model_config['min_chunk_relevance'] ?? self::DEFAULT_SETTINGS['min_chunk_relevance'],
			);

			$context = $this->retriever->find_context( $message, $bot_id, $retrieval_options );

			// check if context is empty
			if ( empty( $context ) ) {
				// $message_template = json_decode($chatbot_data['messages_template'], true);
				// $message = $message_template['fallback'];
				// return [
				// 'response' => wp_unslash($message),
				// 'context' => [],
				// 'metadata' => []
				// ];
			}

			// Process context for enrollment status
			$current_user_id = get_current_user_id();
			$context         = $this->process_context_for_enrollment( $context, $current_user_id );

			// Format context for prompt
			$formatted_context = $this->format_context_for_prompt( $context );

			$options['model']       = $model_config['model'] ?? get_option( 'ai_botkit_chat_model', 'gpt-4o-mini' );
			$options['max_tokens']  = $model_config['max_tokens'] ?? get_option( 'ai_botkit_max_tokens', 1000 );
			$options['temperature'] = $model_config['temperature'] ?? get_option( 'ai_botkit_temperature', 0.7 );

			// Build conversation messages (include attachments when streaming)
			$messages = $this->build_conversation_messages( $message, $history, $formatted_context, $model_config, $options );

			// Initialize response accumulator
			$response_content = '';

			// Stream completion
			$this->llm_client->stream_completion(
				$messages,
				function ( $chunk ) use ( $callback, &$response_content ) {
					$content           = $chunk['choices'][0]['delta']['content'] ?? '';
					$response_content .= $content;
					$callback( $content );
				},
				$options
			);

			// Store in conversation history after complete
			$this->store_conversation_turn(
				$conversation_id,
				array(
					'role'    => 'user',
					'content' => $message,
				)
			);
			$this->store_conversation_turn(
				$conversation_id,
				array(
					'role'    => 'assistant',
					'content' => $response_content,
				)
			);

		} catch ( \Exception $e ) {
			throw new RAG_Engine_Exception(
				esc_html__( 'Failed to stream response: ', 'knowvault' ) . esc_html( $e->getMessage() ),
				0,
				$e
			);
		}
	}

	/**
	 * Get conversation history
	 *
	 * @param string $conversation_id Conversation ID
	 * @return array Conversation turns
	 */
	private function get_conversation_history( string $conversation_id ): array {

		$messages = $this->conversation->get_messages();

		$cache_messages = $this->cache_manager->get( "conversation_$conversation_id", array() );

		if ( empty( $cache_messages ) ) {
			return $messages;
		}

		return $cache_messages;
	}

	/**
	 * Store a conversation turn
	 *
	 * @param string $conversation_id Conversation ID
	 * @param array  $turn Turn data
	 */
	private function store_conversation_turn( string $conversation_id, array $turn, array $options = array() ): void {
		$history = $this->get_conversation_history( $conversation_id );

		// Add new turn
		$history[] = $turn;

		// Trim to max turns
		if ( count( $history ) > self::DEFAULT_SETTINGS['max_conversation_turns'] * 2 ) {
			$history = array_slice(
				$history,
				-self::DEFAULT_SETTINGS['max_conversation_turns'] * 2
			);
		}

		$this->cache_manager->set(
			"conversation_$conversation_id",
			$history,
			self::DEFAULT_SETTINGS['conversation_expiry']
		);

		$options['conversation_id'] = $conversation_id;

		// save to database
		$this->conversation->add_message( array_merge( $turn, $options ) );
	}

	/**
	 * Format context for prompt
	 *
	 * @param array $context Context chunks
	 * @return string Formatted context
	 */
	private function format_context_for_prompt( array $context ): string {
		$formatted = array();

		foreach ( $context as $chunk ) {
			$source      = $chunk['source'] ?? array();
			$formatted[] = sprintf(
				"Source: %s (%s)\n%s",
				( $source['title'] ?? '' ) ?: ( $source['url'] ?? '' ) ?: ( $source['type'] ?? 'unknown' ),
				$source['url'] ?? '',
				$chunk['content']
			);
		}

		return implode( "\n\n", $formatted );
	}

	/**
	 * Build conversation messages
	 *
	 * @param string $message      Current message
	 * @param array  $history      Conversation history
	 * @param string $context      Formatted context
	 * @param array  $chatbot_data Chatbot config
	 * @param array  $options      Optional. May contain 'attachments' (array of id, url, type).
	 * @return array Messages for LLM
	 */
	private function build_conversation_messages(
		string $message,
		array $history,
		string $context,
		array $chatbot_data,
		array $options = array()
	): array {
		$content = str_replace( '{context}', $context, self::DEFAULT_SETTINGS['system_prompt_template'] );
		$content = str_replace( '{chatbot_personality}', ! empty( $chatbot_data['personality'] ) ? $chatbot_data['personality'] : 'Website Chatbot', $content );
		$content = str_replace( '{site_name}', get_bloginfo( 'name' ), $content );
		$content = str_replace( '{chat_tone}', ! empty( $chatbot_data['tone'] ) ? $chatbot_data['tone'] : 'friendly', $content );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $content,
			),
		);

		// Limit history to last N messages
		$max_messages = (int) ( $chatbot_data['max_messages'] ?? 10 );
		if ( $max_messages > 0 ) {
			$history = array_slice( $history, -$max_messages );
		}

		// Add conversation history
		foreach ( $history as $turn ) {
			$messages[] = array(
				'role'    => $turn['role'],
				'content' => $turn['content'],
			);
		}

		// Append attachment context to user message so the bot can use uploaded files
		$user_content = $message;
		$attachments  = isset( $options['attachments'] ) && is_array( $options['attachments'] ) ? $options['attachments'] : array();
		if ( ! empty( $attachments ) ) {
			$lines = array();
			foreach ( $attachments as $a ) {
				$type = isset( $a['type'] ) ? $a['type'] : 'file';
				$url  = isset( $a['url'] ) ? $a['url'] : '';
				if ( $url ) {
					$lines[] = sprintf( '[%s](%s)', $type, $url );
				}
			}
			if ( ! empty( $lines ) ) {
				$user_content .= "\n\n" . __( 'The user has attached the following files. Consider them when answering:', 'knowvault' ) . ' ' . implode( ', ', $lines );
			}
		}

		// Add current message
		$messages[] = array(
			'role'    => 'user',
			'content' => $user_content,
		);

		return $messages;
	}

	/**
	 * Process the document queue
	 */
	public function process_queue(): void {
		global $wpdb;

		// Get pending documents
		$pending = $wpdb->get_results(
			"SELECT id, source_type, source_id, file_path, mime_type
            FROM {$wpdb->prefix}ai_botkit_documents
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 5",
			ARRAY_A
		);

		foreach ( $pending as $document ) {
			try {
				// Update status to processing
				$wpdb->update(
					"{$wpdb->prefix}ai_botkit_documents",
					array( 'status' => 'processing' ),
					array( 'id' => $document['id'] )
				);

				if ( $wpdb->last_error ) {
					throw new RAG_Engine_Exception(
						esc_html__( 'Failed to update document status: ', 'knowvault' ) . esc_html( $wpdb->last_error )
					);
				}

				// Process document
				$source = $document['file_path'] ?? $document['source_id'];
				if ( empty( $source ) ) {
					throw new RAG_Engine_Exception(
						esc_html__( 'No valid source found for document', 'knowvault' )
					);
				}

				// Update MIME type if not set and it's a file
				if ( $document['source_type'] === 'file' && empty( $document['mime_type'] ) ) {
					$mime_type = mime_content_type( $document['file_path'] );
					$wpdb->update(
						"{$wpdb->prefix}ai_botkit_documents",
						array( 'mime_type' => $mime_type ),
						array( 'id' => $document['id'] )
					);
					$document['mime_type'] = $mime_type;
				}

				// Process the document
				$result = $this->process_document( $source, $document['source_type'], $document['id'] );

				// Store processing results in metadata
				$this->store_document_metadata(
					$document['id'],
					array(
						'processing_results' => $result,
						'chunk_count'        => $result['chunk_count'],
						'embedding_count'    => $result['embedding_count'],
						'processing_time'    => microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'],
						'mime_type'          => $document['mime_type'],
						'file_size'          => $document['source_type'] === 'file' ? filesize( $document['file_path'] ) : null,
					)
				);

				// Update status to completed
				$wpdb->update(
					"{$wpdb->prefix}ai_botkit_documents",
					array( 'status' => 'completed' ),
					array( 'id' => $document['id'] )
				);

				if ( $wpdb->last_error ) {
					throw new RAG_Engine_Exception(
						esc_html__( 'Failed to update document status: ', 'knowvault' ) . esc_html( $wpdb->last_error )
					);
				}
			} catch ( \Exception $e ) {
				// Store error in metadata
				$this->store_document_metadata(
					$document['id'],
					array(
						'error'           => $e->getMessage(),
						'error_time'      => current_time( 'mysql' ),
						'processing_time' => microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'],
					)
				);

				// Update status to failed
				$wpdb->update(
					"{$wpdb->prefix}ai_botkit_documents",
					array( 'status' => 'failed' ),
					array( 'id' => $document['id'] )
				);
			}
		}
	}

	/**
	 * Check if a document already exists in the database
	 *
	 * @param int $document_id Document ID
	 * @return bool True if document exists, false otherwise
	 */
	/**
	 * Get existing document ID or create a new document record
	 *
	 * @param string $source_type Type of source
	 * @param int $source_id Source ID (post ID, course ID, product ID, etc.)
	 * @param array $options Document options (title, metadata, etc.)
	 * @return int Document ID
	 */
	private function get_or_create_document_id( string $source_type, int $source_id, array $options ): int {
		global $wpdb;

		// Try to find existing document by source_type and source_id
		if ( $source_id > 0 ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ai_botkit_documents WHERE source_type = %s AND source_id = %d",
					$source_type,
					$source_id
				)
			);

			if ( $existing_id ) {
				// Update the document status to processing
				$wpdb->update(
					$wpdb->prefix . 'ai_botkit_documents',
					array(
						'status' => 'processing',
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $existing_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				return (int) $existing_id;
			}
		}

		// Create new document record
		$title = $options['title'] ?? 'Untitled Document';

		$wpdb->insert(
			$wpdb->prefix . 'ai_botkit_documents',
			array(
				'title' => $title,
				'source_type' => $source_type,
				'source_id' => $source_id > 0 ? $source_id : null,
				'status' => 'processing',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function document_exists( int $document_id ): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents WHERE id = %d",
				$document_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Store document metadata
	 *
	 * @param int   $document_id Document ID
	 * @param array $metadata Metadata to store
	 */
	private function store_document_metadata( int $document_id, array $metadata ): void {
		global $wpdb;

		foreach ( $metadata as $key => $value ) {
			$wpdb->replace(
				"{$wpdb->prefix}ai_botkit_document_metadata",
				array(
					'document_id' => $document_id,
					'meta_key'    => $key,
					'meta_value'  => is_array( $value ) ? wp_json_encode( $value ) : $value,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

			if ( $wpdb->last_error ) {
			}
		}
	}


	/**
	 * Get engine settings
	 *
	 * @return array Current settings
	 */
	public function get_settings(): array {
		return array(
			'document_loader'      => $this->document_loader->get_settings(),
			'text_chunker'         => $this->text_chunker->get_settings(),
			'embeddings_generator' => $this->embeddings_generator->get_settings(),
			'retriever'            => $this->retriever->get_settings(),
			'default_settings'     => self::DEFAULT_SETTINGS,
		);
	}

	/**
	 * Check if a document is a LearnDash course
	 *
	 * @param int $document_id Document ID
	 * @return bool True if it's a LearnDash course
	 */
	private function is_learndash_course( $document_id ) {
		$post_type = get_post_type( $document_id );
		return $post_type === 'sfwd-courses';
	}

	/**
	 * Check if user is enrolled in a specific course
	 *
	 * @param int $user_id User ID
	 * @param int $course_id Course ID
	 * @return bool True if user is enrolled
	 */
	private function is_user_enrolled_in_course( $user_id, $course_id ) {
		if ( ! function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			return false;
		}

		$enrolled_courses = learndash_user_get_enrolled_courses( $user_id );
		return in_array( $course_id, $enrolled_courses );
	}

	/**
	 * Generate course enrollment message for non-enrolled users
	 *
	 * @param int $course_id Course ID
	 * @return string Enrollment message
	 */
	private function get_course_enrollment_message( $course_id ) {
		$course = get_post( $course_id );
		if ( ! $course ) {
			return 'Course information is not available.';
		}

		$course_url         = get_permalink( $course_id );
		$course_description = wp_strip_all_tags( $course->post_content );

		// Limit description length
		if ( strlen( $course_description ) > 200 ) {
			$course_description = substr( $course_description, 0, 200 ) . '...';
		}

		return "Course: <b>{$course->post_title}</b><br>" .
				"Description: {$course_description}<br><br>" .
				'To access the full course content including lessons, topics, and quizzes, ' .
				'you need to enroll in this course first.<br><br>' .
				"<a href='{$course_url}' target='_blank'><b>Click here to enroll in this course</b></a>";
	}

	/**
	 * Process context chunks based on enrollment status
	 *
	 * @param array $context Context chunks
	 * @param int   $user_id User ID
	 * @return array Processed context
	 */
	private function process_context_for_enrollment( $context, $user_id ) {
		$processed_context = array();

		foreach ( $context as $chunk ) {
			$document_id = $chunk['document_id'] ?? null;
			$source_id   = $chunk['source_id'] ?? $document_id;

			// Check if this is a LearnDash course
			if ( $this->is_learndash_course( $source_id ) ) {
				// Check enrollment status from metadata or direct check
				$user_enrolled = $chunk['user_enrolled'] ?? $this->is_user_enrolled_in_course( $user_id, $source_id );

				if ( $user_enrolled ) {
					// User is enrolled - include full content
					$processed_context[] = $chunk;
				} else {
					// User not enrolled - replace with enrollment message
					$enrollment_message  = $this->get_course_enrollment_message( $source_id );
					$processed_context[] = array(
						'chunk_id'       => 'enrollment_' . $source_id,
						'content'        => $enrollment_message,
						'document_id'    => $source_id,
						'document_title' => get_the_title( $source_id ),
						'similarity'     => $chunk['similarity'] ?? 1.0,
						'metadata'       => array(
							'type'          => 'enrollment_message',
							'course_id'     => $source_id,
							'user_enrolled' => false,
						),
					);
				}
			} else {
				// Not a LearnDash course - include normal content
				$processed_context[] = $chunk;
			}
		}

		return $processed_context;
	}
}

/**
 * Custom exception for RAG engine operations
 */
class RAG_Engine_Exception extends \Exception {}
