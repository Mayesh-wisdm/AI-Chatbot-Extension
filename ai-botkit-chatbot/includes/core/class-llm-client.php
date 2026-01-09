<?php
namespace AI_BotKit\Core;

use AI_BotKit\Core\Unified_Cache_Manager;

/**
 * LLM Client for handling interactions with Language Model APIs
 */
class LLM_Client {
    /**
     * Cache manager instance
     */
    private $cache_manager;
    
    /**
     * Default model configuration
     */
    private $default_config;
    
    /**
     * Initialize the client
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->load_default_config();
    }
    
    /**
     * Load default configuration
     */
    private function load_default_config(array $options = []) {
        $engine = get_option('ai_botkit_engine', 'openai');

        $this->default_config = [
            'model' => isset($options['model']) ? $options['model'] : get_option('ai_botkit_chat_model', 'gpt-4o-mini'),
            'max_tokens' => isset($options['max_tokens']) ? (int) $options['max_tokens'] : (int) get_option('ai_botkit_max_tokens', 1000),
            'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : (float) get_option('ai_botkit_temperature', 0.7),
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
        ];
    }
    
    /**
     * Check if client is configured
     */
    public function is_configured(): bool {
        $chat_engine = get_option('ai_botkit_engine', 'openai');
        $api_key = get_option('ai_botkit_' . $chat_engine . '_api_key');

        return !empty($api_key);
    }
    
    /**
     * Generate a completion
     */
    public function generate_completion(array $messages, array $parameters = []): array {
        // Try to get from cache first
        $cache_key = 'completion_' . md5(serialize($messages) . serialize($parameters));
        $cached_response = $this->cache_manager->get($cache_key, 'content');
        
        if ($cached_response !== false) {
            return $cached_response;
        }
        
        $engine = get_option('ai_botkit_engine', 'openai');

        $this->load_default_config($parameters);
        
        // Prepare request data based on engine
        switch ($engine) {
            case 'anthropic':
                $system_prompt = $messages[0]['content'];
                $messages = array_slice($messages, 1);
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages,
                    'model' => str_replace('gpt-4', 'claude-3', $this->default_config['model']),
                    'max_tokens' => $this->default_config['max_tokens'],
                    'system' => $system_prompt
                ]);
                $endpoint = 'messages';
                break;
                
            case 'google':
                $system_prompt = $messages[0]['content'];
                $messages = array_slice($messages, 1);
                $contents = array();
                $contents[] = array(
                    'role' => 'user',
                    'parts' => [['text' => $system_prompt]]
                );
                foreach ($messages as $message) {
                    if (empty($message['content'])) {
                        continue;
                    }
                    $contents[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : $message['role'],
                        'parts' => [['text' => $message['content']]]
                    ];
                }

                $request_data = array_merge($this->default_config, $parameters, [
                    'contents' => $contents,
                    'model' => str_replace('gpt-4', 'gemini-1.5-flash', $this->default_config['model'])
                ]);
                $endpoint = 'models/' . $request_data['model'] . '/generateContent';
                break;
                
            case 'together':
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages,
                    'max_tokens' => $this->default_config['max_tokens'],
                    'temperature' => $this->default_config['temperature']
                ]);
                $endpoint = 'chat/completions';
                break;
                
            case 'openai':
            default:
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages
                ]);
                $endpoint = 'chat/completions';
                break;
        }
        
        // Apply filters before request
        $request_data = apply_filters('ai_botkit_before_llm_request', $request_data, $this->default_config['model']);
        
        try {
            
            // Transform response based on engine
            switch ($engine) {
                case 'openai':
                    $response = $this->generate_openai_response( $request_data );
                    $response_data = [
                        'response' => $response['choices'][0]['message']['content'],
                        'usage' =>  $response['usage']['total_tokens'],
                        'model' => $response['model']
                    ];
                    break;
                case 'anthropic':
                    $response = $this->generate_anthropic_response( $request_data );
                    $response_data = [
                        'response' => $response['content'][0]['text'],
                        'usage' => $response['usage']['input_tokens'] + $response['usage']['output_tokens'],
                        'model' => $response['model']
                    ];
                    break;
                    
                case 'google':
                    $response = $this->generate_google_response( $request_data );
                    $response_data = [
                        'response' => $response['candidates'][0]['content']['parts'][0]['text'],
                        'usage' => $response['usageMetadata']['totalTokenCount'] ?? 0,
                        'model' => $this->default_config['model']
                    ];
                    break;
                    
                case 'together':
                    $response = $this->generate_together_response( $request_data );
                    $response_data = [
                        'response' => $response['choices'][0]['message']['content'],
                        'usage' => $response['usage']['total_tokens'],
                        'model' => $response['model']
                    ];
                    break;
            }
            
            // Cache the response
            $this->cache_manager->set($cache_key, $response_data, 'content', get_option('ai_botkit_cache_ttl', 3600));
            
            // Apply filters after response
            do_action('ai_botkit_after_llm_response', $response_data, $request_data);
            
            return $response_data;
            
        } catch (\Exception $e) {
            do_action('ai_botkit_llm_error', $e, $request_data);
            throw new LLM_Request_Exception(
                esc_html__('Failed to generate completion: ', 'knowvault') . esc_html($e->getMessage())
            );
        }
    }
    
    /**
     * Stream a completion
     */
    public function stream_completion(array $messages, callable $callback, array $parameters = []): void {
        $engine = get_option('ai_botkit_engine', 'openai');

        $this->load_default_config($parameters);
        
        // Prepare request data based on engine
        switch ($engine) {
            case 'anthropic':
                $system_prompt = $messages[0]['content'];
                $messages = array_slice($messages, 1);
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages,
                    'model' => str_replace('gpt-4', 'claude-3', $this->default_config['model']),
                    'max_tokens' => $this->default_config['max_tokens'],
                    'system' => $system_prompt
                ]);
                $endpoint = 'messages';
                break;
                
            case 'google':
                $system_prompt = $messages[0]['content'];
                $messages = array_slice($messages, 1);
                $contents = array();
                $contents[] = array(
                    'role' => 'user',
                    'parts' => [['text' => $system_prompt]]
                );
                foreach ($messages as $message) {
                    if (empty($message['content'])) {
                        continue;
                    }
                    $contents[] = [
                        'role' => $message['role'] === 'assistant' ? 'model' : $message['role'],
                        'parts' => [['text' => $message['content']]]
                    ];
                }

                $request_data = array_merge($this->default_config, $parameters, [
                    'contents' => $contents,
                    'model' => str_replace('gpt-4', 'gemini-1.5-flash', $this->default_config['model'])
                ]);
                $endpoint = 'models/' . $request_data['model'] . ':generateContent?key=' . get_option('ai_botkit_google_api_key');
                break;
                
            case 'together':
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages,
                    'max_tokens' => $this->default_config['max_tokens'],
                    'temperature' => $this->default_config['temperature'],
                    'stream' => true
                ]);
                $endpoint = 'chat/completions';
                break;
                
            case 'openai':
            default:
                $request_data = array_merge($this->default_config, $parameters, [
                    'messages' => $messages,
                    'stream' => true
                ]);
                $endpoint = 'chat/completions';
                break;
        }

        // Apply filters before request
        $request_data = apply_filters('ai_botkit_before_llm_request', $request_data, $this->default_config['model']);

        try {

            switch ($engine) {
                case 'openai':
                    $this->api_client = new HTTP_Client([
                        'base_uri' => 'https://api.openai.com/v1/',
                        'headers' => array(
                            'Authorization' => 'Bearer ' . get_option('ai_botkit_openai_api_key'),
                            'Content-Type' => 'application/json',
                        )
                    ]);
                    break;
                case 'anthropic':
                    $this->api_client = new HTTP_Client([
                        'base_uri' => 'https://api.anthropic.com/v1/',
                        'headers' => array(
                            'x-api-key' => get_option('ai_botkit_anthropic_api_key'),
                            'anthropic-version' => '2023-06-01',
                            'Content-Type' => 'application/json',
                        )
                    ]);
                    break;
                case 'google':
                    $this->api_client = new HTTP_Client([
                        'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
                        'headers' => array(
                            'Authorization' => 'Bearer ' . get_option('ai_botkit_google_api_key'),
                            'Content-Type' => 'application/json',
                        )
                    ]);
                    break;
            }

            $response = $this->api_client->post($endpoint, [
                'json' => $request_data,
                'stream' => true
            ]);


            
            $response->getBody()->on('data', function($chunk) use ($callback, $engine) {
                if ($chunk !== "data: [DONE]\n\n") {
                    $data = json_decode(substr($chunk, 6), true);
                    
                    // Transform streaming data based on engine
                    switch ($engine) {
                        case 'anthropic':
                            $data = [
                                'choices' => [
                                    [
                                        'delta' => [
                                            'content' => $data['delta']['text'],
                                            'role' => 'assistant'
                                        ]
                                    ]
                                ]
                            ];
                            break;
                            
                        case 'google':
                            $data = [
                                'choices' => [
                                    [
                                        'delta' => [
                                            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
                                            'role' => 'assistant'
                                        ]
                                    ]
                                ]
                            ];
                            break;
                            
                        case 'together':
                            // Together AI already follows the OpenAI format for streaming
                            // No transformation needed
                            break;
                    }
                    
                    $callback($data);
                }
            });
            
        } catch (\Exception $e) {
            do_action('ai_botkit_llm_error', $e, $request_data);
            throw new LLM_Stream_Exception(
                esc_html__('Failed to stream completion: ', 'knowvault') . esc_html($e->getMessage())
            );
        }
    }
    
    /**
     * Generate embeddings
     */
    public function generate_embeddings($texts): array {
        if (!is_array($texts)) {
            $texts = [$texts];
        }

        $texts = array_map(function($text) {
            return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }, $texts);

        if (!$this->is_configured()) {
            return [];
        }
        
        $model = get_option('ai_botkit_embedding_model', 'text-embedding-3-small');

        $engine = get_option('ai_botkit_engine', 'openai');
        try {

            switch ($engine) {
                case 'openai':
                    $response_data = $this->generate_openai_embeddings($model, $texts);
                    $embeddings = array_map(function($data) {
                        return $data['embedding'];
                    }, $response_data['data']);
                    break;
                case 'anthropic':
                    $response_data = $this->generate_anthropic_embeddings($model, $texts);
                    $embeddings = array_map(function($data) {
                        return $data['embedding'];
                    }, $response_data['data']);
                    break;
                case 'google':
                    foreach ( $texts as $text ) {
                        $response_data = $this->generate_google_embeddings($model, $text);
                        $embeddings[] = $response_data['embedding']['values'];
                    }
                    break;
                case 'together':
                    $response_data = $this->generate_together_embeddings($model, $texts);
                    // Together AI returns the standard format with 'data' array
                    $embeddings = array_map(function($data) {
                        return $data['embedding'];
                    }, $response_data['data']);
                    break;
            }
            
            if ( isset($response_data['error']) ) {
                throw new LLM_Embedding_Exception(
                    esc_html__('Failed to generate embeddings: ', 'knowvault') . esc_html($response_data['error']['message'])
                );
            }
            
            return $embeddings;
            
        } catch (\Exception $e) {
            do_action('ai_botkit_llm_error', $e, ['model' => $model, 'texts' => $texts]);
            throw new LLM_Embedding_Exception(
                esc_html__('Failed to generate embeddings: ', 'knowvault') . esc_html($e->getMessage())
            );
        }
    }

    private function generate_openai_response(array $parameters = []): array {
        $api_key = get_option('ai_botkit_openai_api_key');
        $org_id = get_option('ai_botkit_openai_org_id');

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'timeout' => 60,
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Organization' => $org_id,
                ),
                'body' => wp_json_encode(array(
                    'model' => $parameters['model'],
                    'messages' => $parameters['messages'],
                    'temperature' => (float) $parameters['temperature'],
                    'max_tokens' => (int) $parameters['max_tokens'],
                    'stream' => $parameters['stream'] ? true : false,
                )),
            )
        );

        $response = $this->check_response('openai', $response);

        return $response;
    }

    private function generate_anthropic_response(array $parameters = []): array {
        $api_key = get_option('ai_botkit_anthropic_api_key');
        $response = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            array(
                'timeout' => 60,
                'method' => 'POST',
                'headers' => array(
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $parameters['model'],
                    'messages' => $parameters['messages'],
                    'max_tokens' => $parameters['max_tokens'],
                    'system' => $parameters['system'],
                    'temperature' => $parameters['temperature'],
                )),
            )
        );

        return $this->check_response('anthropic', $response);
    }

    private function generate_google_response(array $parameters = []): array {
        $api_key = get_option('ai_botkit_google_api_key');

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $parameters['model'] . ':generateContent?key=' . $api_key,
            array(
                'timeout' => 60,
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'contents' => $parameters['contents'],
                )),
            )
        );

        return $this->check_response('google', $response);
    }
    
    private function generate_together_response(array $parameters = []): array {
        $api_key = get_option('ai_botkit_together_api_key');
        
        $response = wp_remote_post(
            'https://api.together.xyz/v1/chat/completions',
            array(
                'timeout' => 30,
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $parameters['model'],
                    'messages' => $parameters['messages'],
                    'temperature' => (float) $parameters['temperature'],
                    'max_tokens' => (int) $parameters['max_tokens'],
                )),
            )
        );

        return $this->check_response('together', $response);
    }

    private function generate_openai_embeddings($model, $texts): array {
        $api_key = get_option('ai_botkit_openai_api_key');
        $response = wp_remote_post(
            'https://api.openai.com/v1/embeddings',
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'input' => $texts,
                )),
            )
        );
        return $this->check_response('openai', $response);
    }

    private function generate_anthropic_embeddings($model, $texts): array {

        $api_key = get_option('ai_botkit_voyageai_api_key');

        $response = wp_remote_post(
            'https://api.voyageai.com/v1/embeddings',
            array(
                'timeout' => 60,
                'headers' => array(
                    'x-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'input' => $texts,
                )),
            )
        );
        return $this->check_response('anthropic', $response);
    }

    private function generate_google_embeddings($model, $texts): array {
        $api_key = get_option('ai_botkit_google_api_key');
        $parts = array();
        $parts[] = array(
            'text' => $texts,
        );

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':embedContent?key=' . $api_key,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'content' => array(
                        'parts' => $parts
                    ),
                    "taskType" => "SEMANTIC_SIMILARITY"
                )),
            )
        );
        return $this->check_response('google', $response);
    }

    private function generate_together_embeddings($model, $texts): array {
        $api_key = get_option('ai_botkit_together_api_key');
        
        // Handle both single string and array of strings
        $input = is_array($texts) && count($texts) === 1 ? $texts[0] : $texts;
        
        $response = wp_remote_post(
            'https://api.together.xyz/v1/embeddings',
            array(
                'timeout' => 30,
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => $model,
                    'input' => $input,
                )),
            )
        );
        
        $result = $this->check_response('together', $response);
        
        return $result;
    }

    private function check_response($engine, $response) {
        if (is_wp_error($response)) {
            throw new LLM_Request_Exception(
                esc_html__('Failed to generate completion: ', 'knowvault') . esc_html($response->get_error_message())
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_message = "HTTP Error $status_code";
            
            if (!empty($body)) {
                $decoded_body = json_decode($body, true);
                if (isset($decoded_body['error']['message'])) {
                    $error_message .= ': ' . $decoded_body['error']['message'];
                } else if (isset($decoded_body['message'])) {
                    $error_message .= ': ' . $decoded_body['message'];
                }
            }
            
            throw new LLM_Request_Exception(
                esc_html__('API Error: ', 'knowvault') . esc_html($error_message)
            );
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}

/**
 * Custom exception classes
 */
class LLM_Request_Exception extends \Exception {}
class LLM_Stream_Exception extends \Exception {}
class LLM_Embedding_Exception extends \Exception {}

/**
 * HTTP Client class for making API requests
 */
class HTTP_Client {
    private $options;
    
    public function __construct(array $options = []) {
        $this->options = $options;
    }
    
    public function post(string $endpoint, array $options = []) {
        $url = $this->options['base_uri'] . $endpoint;
        
        $args = [
            'method' => 'POST',
            'headers' => array_merge(
                $this->options['headers'] ?? [],
                $options['headers'] ?? []
            ),
            'body' => isset($options['json']) ? wp_json_encode($options['json']) : null,
            'timeout' => 30,
        ];
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception(esc_html__('Failed to make API request: ', 'knowvault') . esc_html($response->get_error_message()));
        }
        
        return new HTTP_Response($response);
    }
}

/**
 * HTTP Response class
 */
class HTTP_Response {
    private $response;
    
    public function __construct($response) {
        $this->response = $response;
    }
    
    public function getBody() {
        return wp_remote_retrieve_body($this->response);
    }
} 