<?php
/**
 * Pinecone Vector Database Integration
 *
 * @package AI_BotKit
 * @subpackage AI_BotKit/includes/core
 */

namespace AI_BotKit\Core;

/**
 * Exception class for Pinecone-related errors
 */
class Pinecone_Exception extends \Exception {}

/**
 * Class Pinecone_Database
 * Handles interactions with Pinecone vector database
 *
 * @package AI_BotKit
 */
class Pinecone_Database {

    /**
     * Pinecone API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Pinecone host URL
     *
     * @var string
     */
    private $host;

    /**
     * Table prefix
     *
     * @var string
     */
    private $table_prefix;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_botkit_';
        $this->api_key = get_option('ai_botkit_pinecone_api_key', '');
        $this->host = get_option('ai_botkit_pinecone_host', '');
    }

    /**
     * Check if Pinecone is properly configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return ! empty($this->api_key) &&
               ! empty($this->host);
    }

    /**
     * Get the base URL for Pinecone API requests
     *
     * @return string The base URL
     */
    private function get_base_url() {
        return $this->host;
    }

    /**
     * Upsert vectors to Pinecone
     *
     * @param array $vectors Array of vectors to upsert.
     * @return array Response from Pinecone
     * @throws Pinecone_Exception If the request fails.
     */
    public function upsert_vectors($vector) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            // Clean vector before sending to Pinecone
            $cleaned_vector = $this->clean_vector_for_pinecone($vector);
            
            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode([
                    'vectors' => [$cleaned_vector],
                ]),
            );

            $response = wp_remote_post($this->get_base_url() . '/vectors/upsert', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_message = wp_remote_retrieve_response_message($response);
                $response_body = wp_remote_retrieve_body($response);
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . $error_message . ' (Response: ' . $response_body . ')',
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            return $data;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone upsert: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Clean vector for Pinecone compatibility
     * 
     * @param array $vector Vector to clean
     * @return array Cleaned vector
     */
    private function clean_vector_for_pinecone(array $vector): array {
        // Validate required fields
        if (!isset($vector['id'])) {
            throw new Pinecone_Exception('Vector is missing required "id" field');
        }
        
        if (!isset($vector['values']) || empty($vector['values'])) {
            throw new Pinecone_Exception('Vector is missing required "values" field or values are empty');
        }
        
        $cleaned_vector = [
            'id' => (string) $vector['id'],
            'values' => $vector['values'],
        ];
        
        // Clean metadata - only include essential fields for Pinecone
        if (isset($vector['metadata']) && is_array($vector['metadata'])) {
            $cleaned_metadata = [];
            
            // Only include essential metadata fields
            $essential_fields = [
                'content', 'document_id', 'chunk_index', 'post_type', 'source_type', 'source',
                'post_id', 'mime_type', 'extension', 'last_modified', 'total_chunks',
                'has_previous', 'has_next', 'has_overlap_prev', 'has_overlap_next',
                'size', 'original_size', 'migration_source', 'migration_timestamp'
            ];
            
            foreach ($vector['metadata'] as $key => $value) {
                // Skip null values and non-essential fields
                if ($value === null || !in_array($key, $essential_fields)) {
                    continue;
                }
                
                // Handle arrays by converting to JSON
                if (is_array($value)) {
                    $string_value = wp_json_encode($value);
                } else {
                    $string_value = (string) $value;
                }
                
                // Truncate if too long
                if (strlen($string_value) > 1000) {
                    $string_value = substr($string_value, 0, 1000) . '...';
                }
                
                // Remove any control characters that might cause issues
                $string_value = preg_replace('/[\x00-\x1F\x7F]/', '', $string_value);
                
                // Only include if not empty after cleaning
                if (!empty(trim($string_value))) {
                    $cleaned_metadata[$key] = $string_value;
                }
            }
            
            $cleaned_vector['metadata'] = $cleaned_metadata;
        }
        
        return $cleaned_vector;
    }

    /**
     * Query vectors in Pinecone
     *
     * @param array $query_vector Query vector
     * @param int $limit Maximum number of results
     * @param int $bot_id Bot ID for filtering
     * @param float $min_similarity Minimum similarity threshold
     * @return array Query results
     * @throws Pinecone_Exception If the request fails.
     */
    public function query_vectors($query_vector, $limit = 5, $bot_id = null, $min_similarity = 0.0) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception( 'Pinecone is not properly configured' );
        }

        try {
            $filter = array();
            
            // Get document IDs associated with this chatbot (like local database does)
            if ($bot_id) {
                global $wpdb;
                $document_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT target_id FROM {$wpdb->prefix}ai_botkit_content_relationships 
                     WHERE source_type = 'chatbot' AND source_id = %d AND relationship_type = 'knowledge_base'",
                    $bot_id
                ));
                
                if (!empty($document_ids)) {
                    // Filter by document_id - MUST use strings because metadata values are stored as strings in Pinecone
                    $filter['document_id'] = array('$in' => array_map('strval', $document_ids));
                } else {
                    return array();
                }
            }

            // Get user enrollment context but don't filter results
            $user_aware_context = apply_filters( 'ai_botkit_user_aware_context', false, $bot_id );
            $enrolled_course_ids = [];
            if ($user_aware_context && is_array($user_aware_context)) {
                $enrolled_course_ids = array_map('intval', $user_aware_context);
            }

            $request_body = [
                'vector' => $query_vector,
                'topK' => $limit,
                'includeMetadata' => true,
                'includeValues' => false,
                'filter' => !empty($filter) ? $filter : null,
            ];

            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode($request_body),
                'timeout'     => 30,
            );

            $response = wp_remote_post($this->get_base_url() . '/query', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception( $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . $error_message,
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            // Process and format results with enrollment metadata
            $results = array();
            if (isset($data['matches']) && is_array($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    if ($match['score'] >= $min_similarity) {
                        $metadata = $match['metadata'] ?? array();
                        
                        // Add enrollment metadata
                        if (!empty($enrolled_course_ids) && isset($metadata['source_id'])) {
                            $metadata['user_enrolled'] = in_array(intval($metadata['source_id']), $enrolled_course_ids);
                            $metadata['enrolled_course_ids'] = $enrolled_course_ids;
                        } else {
                            $metadata['user_enrolled'] = true; // No enrollment filtering, treat as enrolled
                            $metadata['enrolled_course_ids'] = [];
                        }
                        
                        $results[] = array(
                            'id' => $match['id'],
                            'score' => $match['score'],
                            'metadata' => $metadata,
                        );
                    }
                }
            }
            return $results;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone query: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Query vectors in Pinecone with values included (for migration)
     *
     * @param array $query_vector Query vector
     * @param int $limit Maximum number of results
     * @param int $bot_id Bot ID for filtering
     * @param float $min_similarity Minimum similarity threshold
     * @return array Query results with vector values
     * @throws Pinecone_Exception If the request fails.
     */
    public function query_vectors_with_values($query_vector, $limit = 5, $bot_id = null, $min_similarity = 0.0) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception( 'Pinecone is not properly configured' );
        }

        try {
            $filter = array();
            
            // Get document IDs associated with this chatbot (like local database does)
            if ($bot_id) {
                global $wpdb;
                $document_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT target_id FROM {$wpdb->prefix}ai_botkit_content_relationships 
                     WHERE source_type = 'chatbot' AND source_id = %d AND relationship_type = 'knowledge_base'",
                    $bot_id
                ));
                
                if (!empty($document_ids)) {
                    // Filter by document_id - MUST use strings because metadata values are stored as strings in Pinecone
                    $filter['document_id'] = array('$in' => array_map('strval', $document_ids));
                } else {
                    // No documents associated with this chatbot, return empty results
                    return array();
                }
            }
            
            // For migration (when bot_id is null), we need to handle the case where we want to get all vectors
            // Create a proper query vector if a dummy one is provided
            if (is_array($query_vector) && count($query_vector) === 1 && $query_vector[0] === 0.0) {
                // This is likely a migration request - create a proper query vector
                // Use a zero vector of the expected dimension (typically 1536 for OpenAI embeddings)
                $query_vector = array_fill(0, 1536, 0.0);
            }

            // Get user enrollment context but don't filter results
            $user_aware_context = apply_filters( 'ai_botkit_user_aware_context', false, $bot_id );
            $enrolled_course_ids = [];
            if ($user_aware_context && is_array($user_aware_context)) {
                $enrolled_course_ids = array_map('intval', $user_aware_context);
            }

            $request_body = [
                    'vector' => $query_vector,
                    'topK' => $limit,
                    'includeMetadata' => true,
                'includeValues' => true, // Include vector values for migration
                    'filter' => !empty($filter) ? $filter : null,
            ];

            // Log the request for debugging

            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode($request_body),
                'timeout'      => 30, // Increase timeout for large requests
            );

            $response = wp_remote_post($this->get_base_url() . '/query', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception( $response->get_error_message() );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . $error_message,
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            // Process and format results with enrollment metadata and vector values
            $results = array();
            if (isset($data['matches']) && is_array($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    if ($match['score'] >= $min_similarity) {
                        $metadata = $match['metadata'] ?? array();
                        
                        // Add enrollment metadata
                        if (!empty($enrolled_course_ids) && isset($metadata['source_id'])) {
                            $metadata['user_enrolled'] = in_array(intval($metadata['source_id']), $enrolled_course_ids);
                            $metadata['enrolled_course_ids'] = $enrolled_course_ids;
                        } else {
                            $metadata['user_enrolled'] = true; // No enrollment filtering, treat as enrolled
                            $metadata['enrolled_course_ids'] = [];
                        }
                        
                        $results[] = array(
                            'id' => $match['id'],
                            'score' => $match['score'],
                            'values' => $match['values'] ?? [], // Include vector values
                            'metadata' => $metadata,
                        );
                    }
                }
            }
            return $results;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone query: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Delete vectors from Pinecone
     *
     * @param array $ids Array of vector IDs to delete
     * @return array Response from Pinecone
     * @throws Pinecone_Exception If the request fails.
     */
    public function delete_vectors($ids) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode([
                    'ids' => $ids,
                ]),
            );

            $response = wp_remote_post($this->get_base_url() . '/vectors/delete', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                // Handle 404 (Not Found) gracefully - vectors may not exist
                if ($response_code === 404) {
                    return [
                        'deletedCount' => 0,
                        'message' => 'Vectors not found (already deleted or never existed)'
                    ];
                }
                
                $error_message = wp_remote_retrieve_response_message($response);
                $response_body = wp_remote_retrieve_body($response);
                
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . $error_message . ' (Response: ' . $response_body . ')',
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            return $data;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone delete: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Fetch vectors from Pinecone
     *
     * @param array $ids Array of vector IDs to fetch
     * @return array Response from Pinecone
     * @throws Pinecone_Exception If the request fails.
     */
    public function fetch_vectors($ids) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode([
                    'ids' => $ids,
                ]),
            );

            $response = wp_remote_post($this->get_base_url() . '/vectors/fetch', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . wp_remote_retrieve_response_message($response),
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            return $data;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone fetch: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Update vectors in Pinecone
     *
     * @param array $vectors Array of vectors to update
     * @return array Response from Pinecone
     * @throws Pinecone_Exception If the request fails.
     */
    public function update_vectors($vectors) {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'        => wp_json_encode([
                    'vectors' => $vectors,
                ]),
            );

            $response = wp_remote_post($this->get_base_url() . '/vectors/update', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . wp_remote_retrieve_response_message($response),
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            return $data;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone update: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }


    /**
     * Describe index statistics
     *
     * @return array Index statistics
     * @throws Pinecone_Exception If the request fails.
     */
    public function describe_index_stats() {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            $args = array(
                'headers'     => array(
                    'Api-Key'      => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
            );

            $response = wp_remote_get($this->get_base_url() . '/describe_index_stats', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Pinecone_Exception(
                    'Pinecone API error: ' . wp_remote_retrieve_response_message($response),
                    $response_code
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Pinecone_Exception(
                    'Invalid JSON response from Pinecone API'
                );
            }

            return $data;

        } catch (Pinecone_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone describe index stats: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Delete all vectors from Pinecone index
     * 
     * @return array Result of the operation
     * @throws Pinecone_Exception
     */
    public function delete_all_vectors() {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not configured');
        }

        try {
            $url = $this->get_base_url() . '/vectors/delete';
            
            $data = [
                'deleteAll' => true
            ];

            $response = wp_remote_post($url, [
                'headers' => [
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode($data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception('Failed to delete all vectors: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($status_code === 200) {
                return [
                    'success' => true,
                    'message' => __('All vectors deleted from Pinecone successfully', 'knowvault')
                ];
            } else {
                $error_data = json_decode($body, true);
                $error_message = is_array($error_data) ? ($error_data['message'] ?? 'Unknown error') : 'Unknown error';
                throw new Pinecone_Exception("Failed to delete all vectors: {$error_message} (Status: {$status_code})");
            }

        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone delete all vectors: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Test Pinecone connection by making a simple API call
     *
     * @return bool True if connection is successful
     * @throws Pinecone_Exception If the connection fails
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            throw new Pinecone_Exception('Pinecone is not properly configured');
        }

        try {
            // Make a simple describe_index_stats call to test connection
            $args = array(
                'headers' => array(
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json',
                ),
            );

            $response = wp_remote_get($this->get_base_url() . '/describe_index_stats', $args);

            if (is_wp_error($response)) {
                throw new Pinecone_Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                $error_message = wp_remote_retrieve_response_message($response);
                throw new Pinecone_Exception('Pinecone API error: ' . $error_message, $response_code);
            }

            return true;

        } catch (\Exception $e) {
            throw new Pinecone_Exception(
                'Pinecone connection test failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}

