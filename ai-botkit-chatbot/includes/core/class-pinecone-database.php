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
    public function upsert_vectors($vectors) {
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

            $response = wp_remote_post($this->get_base_url() . '/vectors/upsert', $args);

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
                'Unexpected error during Pinecone upsert: ' . $e->getMessage(),
                0,
                $e
            );
        }
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
                    // Filter by document_id instead of bot_id (since bot_id doesn't exist in vector metadata)
                    $filter['document_id'] = array('$in' => array_map('intval', $document_ids));
                } else {
                    // No documents associated with this chatbot, return empty results
                    return array();
                }
            }

            $user_aware_context = apply_filters( 'ai_botkit_user_aware_context', false, $bot_id );
            if ($user_aware_context && is_array($user_aware_context)) {
                // Only apply user-aware context filter if we don't already have document_id filter
                // This prevents conflicting filters that would return no results
                if (!isset($filter['document_id'])) {
                    $filter['course_id'] = array('$in' => $user_aware_context);
                }
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

            // Process and format results
            $results = array();
            if (isset($data['matches']) && is_array($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    if ($match['score'] >= $min_similarity) {
                        $results[] = array(
                            'id' => $match['id'],
                            'score' => $match['score'],
                            'metadata' => $match['metadata'] ?? array(),
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
                    'message' => __('All vectors deleted from Pinecone successfully', 'ai-botkit-for-lead-generation')
                ];
            } else {
                $error_data = json_decode($body, true);
                $error_message = $error_data['message'] ?? 'Unknown error';
                throw new Pinecone_Exception("Failed to delete all vectors: {$error_message} (Status: {$status_code})");
            }

        } catch (\Exception $e) {
            error_log('AI BotKit Pinecone Error: Delete all vectors failed - ' . $e->getMessage());
            throw new Pinecone_Exception(
                'Unexpected error during Pinecone delete all vectors: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}

