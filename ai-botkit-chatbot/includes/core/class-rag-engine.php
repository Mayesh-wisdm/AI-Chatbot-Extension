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
    private const DEFAULT_SETTINGS = [
        'max_context_chunks' => 5,
        'min_chunk_relevance' => 0.4,
        'max_conversation_turns' => 10,
        'conversation_expiry' => 3600, // 1 hour
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

            Context:
            {context}",
    ];

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
        $this->document_loader = $document_loader;
        $this->text_chunker = $text_chunker;
        $this->embeddings_generator = $embeddings_generator;
        $this->vector_database = $vector_database;
        $this->retriever = $retriever;
        $this->llm_client = $llm_client;
        $this->cache_manager = new Cache_Manager();
        
        // Initialize rate limiter with error handling
        try {
            $this->rate_limiter = new Rate_Limiter();
        } catch (\Exception $e) {
            error_log('AI BotKit RAG Engine Error: Rate_Limiter initialization failed - ' . $e->getMessage());
            $this->rate_limiter = null;
        }

        // process queue
        add_action('ai_botkit_process_queue', [$this, 'process_queue']);

        do_action('ai_botkit_process_queue');
    }

    /**
     * Process a document for RAG
     * 
     * @param string $source Source identifier (file path, URL, post ID)
     * @param string $source_type Type of source (file, url, post)
     * @param array $options Processing options
     * @return array Processing results
     * @throws RAG_Engine_Exception
     */
    public function process_document(string $source, string $source_type, int $document_id, array $options = []): array {
        try {
            // Check if this is an update (document already exists)
            $is_update = $this->document_exists($document_id);
            
            // If this is an update, clean up old chunks and embeddings first
            if ($is_update) {
                $cleanup_result = $this->vector_database->delete_document_embeddings($document_id);
                error_log('AI BotKit RAG Engine: Cleaned up old chunks for document ' . $document_id . 
                         ' - Chunks: ' . $cleanup_result['deleted_chunks'] . 
                         ', Embeddings: ' . $cleanup_result['deleted_embeddings']);
            }

            // Load document based on source type
            $document = match($source_type) {
                'file' => $this->document_loader->load_from_file($source, $document_id),
                'url' => $this->document_loader->load_from_url($source, $document_id),
                'post' => $this->document_loader->load_from_post((int)$source, $document_id),
                default => throw new RAG_Engine_Exception("Unsupported source type: $source_type")
            };

            // Split into chunks
            $chunks = $this->text_chunker->split_text($document['content'], $document['metadata']);
            
            // Generate embeddings
            $embeddings = $this->embeddings_generator->generate_embeddings($chunks);

            // Store in vector database
            $stored = $this->vector_database->store_embeddings($embeddings);

            return [
                'document_id' => $document_id,
                'chunk_count' => count($chunks),
                'embedding_count' => count($embeddings),
                'metadata' => $document['metadata'],
                'is_update' => $is_update,
                'cleanup_result' => $is_update ? $cleanup_result : null
            ];

        } catch (\Exception $e) {
            throw new RAG_Engine_Exception(
                esc_html__('Failed to process document: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
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
     * @param array $options Response options
     * @return array Response data
     * @throws RAG_Engine_Exception
     */
    public function generate_response(string $message, string $conversation_id, int $bot_id, string $context = '', array $options = []): array {

        try {
            $this->conversation = new Conversation($conversation_id);

            $chatbot = new Chatbot($bot_id);
            $chatbot_data = $chatbot->get_data();

            $model_config = json_decode($chatbot_data['model_config'], true);

            // Check for banned keywords
            $banned_keywords_json = get_option('ai_botkit_banned_keywords', '[]');
            $banned_keywords = json_decode($banned_keywords_json, true);
            
            if (!empty($banned_keywords)) {
                $message_lowercase = strtolower($message);
                
                foreach ($banned_keywords as $keyword) {
                    // Use word boundary to match whole words only
                    $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
                    if (preg_match($pattern, $message_lowercase)) {
                        // Return early with warning message
                        $warning_message = sprintf(
                            esc_html__('⚠️ The word "%s" is not allowed in this chat.', 'ai-botkit-for-lead-generation'),
                            esc_html($keyword)
                        );
                        //return that this word is not allowed
                        return [
                            'content' => $warning_message,
                        ];
                    }
                }
            }
            
            // Check rate limits if available
            if ($this->rate_limiter) {
                $rate_check = $this->rate_limiter->check_user_limits();
                
                if (is_array($rate_check) && isset($rate_check['limited']) && $rate_check['limited']) {
                    throw new RAG_Engine_Exception($rate_check['message']);
                }
            }
            
            // check if message have hi, hello etc greetings and less than 5 words
            if (strlen($message) < 10 && in_array(strtolower($message), ['hi', 'hello', 'hey', 'hola', 'greetings'])) {
                $message = 'Hello! How can I assist you today? 😊';
                return [
                    'response' => wp_unslash($message),
                    'context' => [],
                    'metadata' => []
                ];
            }

            // Get conversation history
            $history = $this->get_conversation_history($conversation_id);

            // Find relevant context
            $retrieval_options = [
                'max_results' => $model_config['context_length'] ? $model_config['context_length'] : self::DEFAULT_SETTINGS['max_context_chunks'],
                'min_similarity' => $model_config['min_chunk_relevance'] ?? self::DEFAULT_SETTINGS['min_chunk_relevance']
            ];
            
            $context = $this->retriever->find_context($message, $bot_id, $retrieval_options);

            // check if context is empty
            if (empty($context)) {
                $message_template = json_decode($chatbot_data['messages_template'], true);
                $message = $message_template['fallback'];
                return [
                    'response' => wp_unslash($message),
                    'context' => [],
                    'metadata' => []
                ];
            }

            // Format context for prompt
            $formatted_context = $this->format_context_for_prompt($context);

            $options['model'] = $model_config['model'] ? $model_config['model'] : get_option('ai_botkit_chat_model', 'gpt-4o-mini');
            $options['max_tokens'] = $model_config['max_tokens'] ? $model_config['max_tokens'] : get_option('ai_botkit_max_tokens', 1000);
            $options['temperature'] = $model_config['temperature'] ? $model_config['temperature'] : get_option('ai_botkit_temperature', 0.7);

            // Build conversation messages
            $messages = $this->build_conversation_messages($message, $history, $formatted_context, $model_config);

            // Generate completion
            $completion = $this->llm_client->generate_completion(
                $messages,
                $options
            );

            $this->conversation->save(
                array(
                    'chatbot_id' => $bot_id,
                    'session_id' => $conversation_id
                )
            );

            // Store in conversation history
            $this->store_conversation_turn($this->conversation->get_id(), [
                'role' => 'user',
                'content' => $message
            ], [
                'model' => $completion['model'],
                'tokens' => $completion['usage']
            ]);
            $this->store_conversation_turn($this->conversation->get_id(), [
                'role' => 'assistant',
                'content' => $completion['response']
            ], [
                'model' => $completion['model'],
                'tokens' => $completion['usage']
            ]);

            return [
                'response' => $completion['response'],
                'context' => $context,
                'metadata' => [
                    'tokens' => $completion['usage'],
                    'model' => $completion['model'],
                    'context_chunks' => count($context),
                    'conversation_id' => $conversation_id,
                    'processing_time' => microtime(true) - (float)$_SERVER["REQUEST_TIME_FLOAT"],
                ]
            ];

        } catch (\Exception $e) {
            throw new RAG_Engine_Exception(
                esc_html__('Failed to generate response: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Stream a response for a chat message
     * 
     * @param string $message User message
     * @param string $conversation_id Conversation ID
     * @param int $bot_id Bot ID
     * @param callable $callback Callback for streaming chunks
     * @param array $options Response options
     * @return void
     * @throws RAG_Engine_Exception
     */
    public function stream_response( string $message, string $conversation_id, int $bot_id, callable $callback, array $options = [] ): void {
        try {
            $this->conversation = new Conversation($conversation_id);

            $chatbot = new Chatbot($bot_id);
            $chatbot_data = $chatbot->get_data();

            $model_config = json_decode($chatbot_data['model_config'], true);

            // Check for banned keywords
            $banned_keywords_json = get_option('ai_botkit_banned_keywords', '[]');
            $banned_keywords = json_decode($banned_keywords_json, true);
            
            if (!empty($banned_keywords)) {
                $message_lowercase = strtolower($message);
                
                foreach ($banned_keywords as $keyword) {
                    // Use word boundary to match whole words only
                    $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
                    if (preg_match($pattern, $message_lowercase)) {
                        // Return early with warning message
                        $warning_message = sprintf(
                            esc_html__('⚠️ The word "%s" is not allowed in this chat.', 'ai-botkit-for-lead-generation'),
                            esc_html($keyword)
                        );
                        $callback([
                            'content' => $warning_message,
                            'sources' => []
                        ]);
                        //return that this word is not allowed
                        return;
                    }
                }
            }
            
            // Check rate limits if available
            if ($this->rate_limiter) {
                $rate_check = $this->rate_limiter->check_user_limits();
                
                if (is_array($rate_check) && isset($rate_check['limited']) && $rate_check['limited']) {
                    throw new RAG_Engine_Exception($rate_check['message']);
                }
            }
            
            // check if message have hi, hello etc greetings and less than 5 words
            // if (strlen($message) < 10 && in_array(strtolower($message), ['hi', 'hello', 'hey', 'hola', 'greetings'])) {
            //     $message = 'Hello! How can I assist you today? 😊';
            //     return [
            //         'response' => wp_unslash($message),
            //         'context' => [],
            //         'metadata' => []
            //     ];
            // }

            // Get conversation history
            $history = $this->get_conversation_history($conversation_id);

            // Find relevant context
            $retrieval_options = [
                'max_results' => $model_config['context_length'] ? $model_config['context_length'] : self::DEFAULT_SETTINGS['max_context_chunks'],
                'min_similarity' => $model_config['min_chunk_relevance'] ?? self::DEFAULT_SETTINGS['min_chunk_relevance']
            ];
            
            $context = $this->retriever->find_context($message, $bot_id, $retrieval_options);

            // check if context is empty
            if (empty($context)) {
                // $message_template = json_decode($chatbot_data['messages_template'], true);
                // $message = $message_template['fallback'];
                // return [
                //     'response' => wp_unslash($message),
                //     'context' => [],
                //     'metadata' => []
                // ];
            }

            // Format context for prompt
            $formatted_context = $this->format_context_for_prompt($context);

            $options['model'] = $model_config['model'] ? $model_config['model'] : get_option('ai_botkit_chat_model', 'gpt-4o-mini');
            $options['max_tokens'] = $model_config['max_tokens'] ? $model_config['max_tokens'] : get_option('ai_botkit_max_tokens', 1000);
            $options['temperature'] = $model_config['temperature'] ? $model_config['temperature'] : get_option('ai_botkit_temperature', 0.7);

            // Build conversation messages
            $messages = $this->build_conversation_messages($message, $history, $formatted_context, $model_config);

            // Initialize response accumulator
            $response_content = '';

            // Stream completion
            $this->llm_client->stream_completion(
                $messages,
                function($chunk) use ($callback, &$response_content) {
                    $content = $chunk['choices'][0]['delta']['content'] ?? '';
                    $response_content .= $content;
                    $callback($content);
                },
                $options
            );

            // Store in conversation history after complete
            $this->store_conversation_turn($conversation_id, [
                'role' => 'user',
                'content' => $message
            ]);
            $this->store_conversation_turn($conversation_id, [
                'role' => 'assistant',
                'content' => $response_content
            ]);

        } catch (\Exception $e) {
            throw new RAG_Engine_Exception(
                esc_html__('Failed to stream response: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
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
    private function get_conversation_history(string $conversation_id): array {
            
        $messages = $this->conversation->get_messages();

        $cache_messages = $this->cache_manager->get("conversation_$conversation_id", []);

        if (empty($cache_messages)) {
            return $messages;
        }

        return $cache_messages;
    }

    /**
     * Store a conversation turn
     * 
     * @param string $conversation_id Conversation ID
     * @param array $turn Turn data
     */
    private function store_conversation_turn(string $conversation_id, array $turn, array $options = []): void {
        $history = $this->get_conversation_history($conversation_id);
        
        // Add new turn
        $history[] = $turn;
        
        // Trim to max turns
        if (count($history) > self::DEFAULT_SETTINGS['max_conversation_turns'] * 2) {
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
        $this->conversation->add_message(array_merge($turn, $options));
    }

    /**
     * Format context for prompt
     * 
     * @param array $context Context chunks
     * @return string Formatted context
     */
    private function format_context_for_prompt(array $context): string {
        $formatted = [];
        
        foreach ($context as $chunk) {
            $source = $chunk['source'];
            $formatted[] = sprintf(
                "Source: %s (%s)\n%s",
                $source['title'] ?: $source['url'] ?: $source['type'],
                $source['url'],
                $chunk['content']
            );
        }
        
        return implode("\n\n", $formatted);
    }

    /**
     * Build conversation messages
     * 
     * @param string $message Current message
     * @param array $history Conversation history
     * @param string $context Formatted context
     * @return array Messages for LLM
     */
    private function build_conversation_messages(
        string $message,
        array $history,
        string $context,
        array $chatbot_data
    ): array {
        $content = str_replace('{context}', $context, self::DEFAULT_SETTINGS['system_prompt_template']);
        $content = str_replace('{chatbot_personality}', !empty($chatbot_data['personality']) ? $chatbot_data['personality'] : 'Website Chatbot', $content);
        $content = str_replace('{site_name}', get_bloginfo('name'), $content);
        $content = str_replace('{chat_tone}', !empty($chatbot_data['tone']) ? $chatbot_data['tone'] : 'friendly', $content);

        $messages = [
            [
                'role' => 'system',
                'content' => $content
            ]
        ];

        // limit history to last 5 messages
        $history = array_slice($history, -$chatbot_data['max_messages']);

        // Add conversation history
        foreach ($history as $turn) {
            $messages[] = [
                'role' => $turn['role'],
                'content' => $turn['content']
            ];
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

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

        foreach ($pending as $document) {
            try {
                // Update status to processing
                $wpdb->update(
                    "{$wpdb->prefix}ai_botkit_documents",
                    ['status' => 'processing'],
                    ['id' => $document['id']]
                );

                if ($wpdb->last_error) {
                    throw new RAG_Engine_Exception(
                        esc_html__('Failed to update document status: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
                    );
                }

                // Process document
                $source = $document['file_path'] ?? $document['source_id'];
                if (empty($source)) {
                    throw new RAG_Engine_Exception(
                        esc_html__('No valid source found for document', 'ai-botkit-for-lead-generation')
                    );
                }

                // Update MIME type if not set and it's a file
                if ($document['source_type'] === 'file' && empty($document['mime_type'])) {
                    $mime_type = mime_content_type($document['file_path']);
                    $wpdb->update(
                        "{$wpdb->prefix}ai_botkit_documents",
                        ['mime_type' => $mime_type],
                        ['id' => $document['id']]
                    );
                    $document['mime_type'] = $mime_type;
                }

                // Process the document
                $result = $this->process_document($source, $document['source_type'], $document['id']);

                // Store processing results in metadata
                $this->store_document_metadata($document['id'], [
                    'processing_results' => $result,
                    'chunk_count' => $result['chunk_count'],
                    'embedding_count' => $result['embedding_count'],
                    'processing_time' => microtime(true) - (float)$_SERVER["REQUEST_TIME_FLOAT"],
                    'mime_type' => $document['mime_type'],
                    'file_size' => $document['source_type'] === 'file' ? filesize($document['file_path']) : null
                ]);

                // Update status to completed
                $wpdb->update(
                    "{$wpdb->prefix}ai_botkit_documents",
                    ['status' => 'completed'],
                    ['id' => $document['id']]
                );

                if ($wpdb->last_error) {
                    throw new RAG_Engine_Exception(
                        esc_html__('Failed to update document status: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
                    );
                }

            } catch (\Exception $e) {
                // Store error in metadata
                $this->store_document_metadata($document['id'], [
                    'error' => $e->getMessage(),
                    'error_time' => current_time('mysql'),
                    'processing_time' => microtime(true) - (float)$_SERVER["REQUEST_TIME_FLOAT"]
                ]);

                // Update status to failed
                $wpdb->update(
                    "{$wpdb->prefix}ai_botkit_documents",
                    ['status' => 'failed'],
                    ['id' => $document['id']]
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
    private function document_exists(int $document_id): bool {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents WHERE id = %d",
            $document_id
        ));
        
        return (int)$count > 0;
    }

    /**
     * Store document metadata
     * 
     * @param int $document_id Document ID
     * @param array $metadata Metadata to store
     */
    private function store_document_metadata(int $document_id, array $metadata): void {
        global $wpdb;

        foreach ($metadata as $key => $value) {
            $wpdb->replace(
                "{$wpdb->prefix}ai_botkit_document_metadata",
                [
                    'document_id' => $document_id,
                    'meta_key' => $key,
                    'meta_value' => is_array($value) ? wp_json_encode($value) : $value,
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s']
            );

            if ($wpdb->last_error) {
                error_log("Failed to store document metadata: " . $wpdb->last_error);
            }
        }
    }


    /**
     * Get engine settings
     * 
     * @return array Current settings
     */
    public function get_settings(): array {
        return [
            'document_loader' => $this->document_loader->get_settings(),
            'text_chunker' => $this->text_chunker->get_settings(),
            'embeddings_generator' => $this->embeddings_generator->get_settings(),
            'retriever' => $this->retriever->get_settings(),
            'default_settings' => self::DEFAULT_SETTINGS
        ];
    }
}

/**
 * Custom exception for RAG engine operations
 */
class RAG_Engine_Exception extends \Exception {} 