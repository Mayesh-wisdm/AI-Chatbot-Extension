<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Cache_Manager;

/**
 * Vector Database class for storing and searching embeddings
 * 
 * Features:
 * - Efficient vector storage
 * - Fast similarity search
 * - Batch operations
 * - Cache integration
 * - Metadata management
 * - Pinecone integration
 */
class Vector_Database {
    /**
     * Cache Manager instance
     */
    private $cache_manager;

    /**
     * Database prefix
     */
    private $table_prefix;

    /**
     * Pinecone Database instance
     */
    private $pinecone_database;

    /**
     * Initialize the database
     */
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_botkit_';
        $this->cache_manager = new Cache_Manager();
        
        // Initialize Pinecone database if enabled by user and configured
        if (get_option('ai_botkit_enable_pinecone', 0)) {
            $this->pinecone_database = new Pinecone_Database();
        }
    }

    /**
     * Store embeddings in the database
     * 
     * @param array $embeddings Array of embeddings with metadata
     * @return array Array of stored embedding IDs
     * @throws Vector_Database_Exception
     */
    public function store_embeddings(array $embeddings): array {
        global $wpdb;
        $stored_ids = [];

        try {
            foreach ($embeddings as $embedding) {
                // Store chunk first
                $chunk_id = $this->store_chunk(
                    $embedding['metadata']['document_id'] ?? 0,
                    $embedding['metadata']['content'] ?? '',
                    $embedding['metadata']
                );

                // Store embedding based on user preference and Pinecone configuration
                if ($this->pinecone_database && $this->pinecone_database->is_configured()) {
                    $result = $this->pinecone_database->upsert_vectors(
                        array(
                            'id' => (string)$chunk_id,
                            'values' => $embedding['embedding'],
                            'metadata' => $embedding['metadata']
                        )
                    );

                    if ($result === false) {
                        throw new Vector_Database_Exception(
                            esc_html__('Failed to store embedding in Pinecone: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
                        );
                    }

                    $stored_ids[] = $result['upsertedCount'] ?? $chunk_id;
                } else {
                    $result = $wpdb->insert(
                        $this->table_prefix . 'embeddings',
                        [
                            'chunk_id' => $chunk_id,
                            'embedding' => $this->serialize_vector($embedding['embedding']),
                            'model' => $embedding['model'],
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%s', '%s', '%s']
                    );

                    if ($result === false) {
                        throw new Vector_Database_Exception(
                            esc_html__('Failed to store embedding: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
                        );
                    }

                    $stored_ids[] = $wpdb->insert_id;
                }

                // Clear relevant caches
                $this->clear_related_caches($chunk_id);
            }

            return $stored_ids;

        } catch (\Exception $e) {
            throw new Vector_Database_Exception(
                esc_html__('Failed to store embeddings: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Find similar vectors using cosine similarity
     * 
     * @param array $query_vector Query vector
     * @param int $limit Maximum number of results
     * @param float $min_similarity Minimum similarity threshold
     * @param array $filters Optional metadata filters
     * @return array Array of similar chunks with scores
     * @throws Vector_Database_Exception
     */
    public function find_similar(
        int $bot_id,
        array $query_vector,
        int $limit = 5,
        float $min_similarity = 0.0,
        array $filters = []
    ): array {
        global $wpdb;

        try {
            // Try cache first
            $cache_key = 'similar_' . md5(serialize(func_get_args()));
            $cached_results = $this->cache_manager->get($cache_key);

            if ($cached_results !== false) {
                return $cached_results;
            }

            // Check if Pinecone is configured
            $pinecone_configured = $this->pinecone_database && $this->pinecone_database->is_configured();

            if ($pinecone_configured) {
                // Use Pinecone for vector search
                $results = $this->pinecone_database->query_vectors($query_vector, $limit, $bot_id, $min_similarity);
                
                // Process Pinecone results
                $processed_results = [];
                foreach ($results as $result) {
                    $processed_results[] = [
                        'chunk_id' => (int) $result['id'],
                        'content' => $result['metadata']['content'] ?? '',
                        'similarity' => $result['score'],
                        'metadata' => $result['metadata'],
                    ];
                }
            } else {
                // Use local database for vector search
                $user_aware_context = apply_filters('ai_botkit_user_aware_context', false, $bot_id);

                // Get all embeddings and chunks with enrollment metadata
                    $query = "SELECT 
                        c.id as chunk_id,
                        c.content,
                        c.metadata,
                        e.embedding,
                        d.id as document_id,
                    d.title as document_title,
                    d.source_id,
                    d.source_type
                        FROM {$this->table_prefix}embeddings e
                        JOIN {$this->table_prefix}chunks c ON e.chunk_id = c.id
                        JOIN {$this->table_prefix}documents d ON c.document_id = d.id
                        JOIN {$this->table_prefix}content_relationships cr ON cr.target_id = d.id
                    WHERE cr.source_id = %d";
                
                $results = $wpdb->get_results($wpdb->prepare($query, $bot_id), ARRAY_A);
                
                // If no results from complex query, try simpler query without content_relationships
                if (empty($results)) {
                    $simple_query = "SELECT 
                        c.id as chunk_id,
                        c.content,
                        c.metadata,
                        e.embedding,
                        d.id as document_id,
                        d.title as document_title,
                        d.source_id,
                        d.source_type
                        FROM {$this->table_prefix}embeddings e
                        JOIN {$this->table_prefix}chunks c ON e.chunk_id = c.id
                        JOIN {$this->table_prefix}documents d ON c.document_id = d.id";
                    
                    $results = $wpdb->get_results($simple_query, ARRAY_A);
                }
                
                // Add enrollment metadata to results
                if ($user_aware_context && is_array($user_aware_context)) {
                    $enrolled_course_ids = array_map('intval', $user_aware_context);
                    foreach ($results as &$result) {
                        $result['user_enrolled'] = in_array($result['source_id'], $enrolled_course_ids);
                        $result['enrolled_course_ids'] = $enrolled_course_ids;
                    }
                } else {
                    foreach ($results as &$result) {
                        $result['user_enrolled'] = true; // No enrollment filtering, treat as enrolled
                        $result['enrolled_course_ids'] = [];
                    }
                }
                
                if ($results === null) {
                    throw new Vector_Database_Exception(
                        esc_html__('Failed to fetch embeddings: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
                    );
                }

                $similar_results = [];
                foreach ($results as $row) {
                    $doc_embedding = $this->deserialize_vector($row['embedding']);
                    
                    // Calculate cosine similarity
                    $similarity = $this->calculate_cosine_similarity($query_vector, $doc_embedding);
                    
                    if ($similarity >= $min_similarity) {
                        $similar_results[] = [
                            'chunk_id' => (int) $row['chunk_id'],
                            'content' => $row['content'],
                            'similarity' => $similarity,
                            'metadata' => json_decode($row['metadata'], true),
                        ];
                    }
                }

                // Sort by similarity (highest first)
                usort($similar_results, function($a, $b) {
                    return $b['similarity'] <=> $a['similarity'];
                });

                // Limit results
                $processed_results = array_slice($similar_results, 0, $limit);
            }

            // Cache results
            $this->cache_manager->set($cache_key, $processed_results, HOUR_IN_SECONDS);

            return $processed_results;

        } catch (\Exception $e) {
            throw new Vector_Database_Exception(
                esc_html__('Failed to find similar vectors: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Calculate cosine similarity between two embedding vectors
     * 
     * @param array $vector_a First embedding vector
     * @param array $vector_b Second embedding vector
     * @return float Cosine similarity score (0-1)
     */
    private function calculate_cosine_similarity(array $vector_a, array $vector_b): float {
        $dot_product = 0;
        $magnitude_a = 0;
        $magnitude_b = 0;
        
        foreach ($vector_a as $i => $value_a) {
            $value_b = $vector_b[$i] ?? 0;
            $dot_product += $value_a * $value_b;
            $magnitude_a += $value_a * $value_a;
            $magnitude_b += $value_b * $value_b;
        }
        
        $magnitude_a = sqrt($magnitude_a);
        $magnitude_b = sqrt($magnitude_b);
        
        if ($magnitude_a == 0 || $magnitude_b == 0) {
            return 0;
        }
        
        return $dot_product / ($magnitude_a * $magnitude_b);
    }

    /**
     * Store a chunk in the database
     * 
     * @param int $document_id Document ID
     * @param string $content Chunk content
     * @param array $metadata Chunk metadata
     * @return int Chunk ID
     */
    private function store_chunk(int $document_id, string $content, array $metadata): int {
        global $wpdb;
        unset($metadata['document_id']);
        unset($metadata['content']);
        unset($metadata['chunk_index']);

        $result = $wpdb->insert(
            $this->table_prefix . 'chunks',
            [
                'document_id' => $document_id,
                'content' => $content,
                'chunk_index' => $metadata['chunk_index'] ?? 0,
                'metadata' => wp_json_encode($metadata),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            throw new Vector_Database_Exception(
                esc_html__('Failed to store chunk: ', 'ai-botkit-for-lead-generation') . esc_html($wpdb->last_error)
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Serialize vector for storage
     * 
     * @param array $vector Vector to serialize
     * @return string Serialized vector
     */
    private function serialize_vector(array $vector): string {
        return base64_encode(pack('f*', ...$vector));
    }

    /**
     * Deserialize vector from storage
     * 
     * @param string $serialized Serialized vector
     * @return array Vector array
     */
    private function deserialize_vector(string $serialized): array {
        return array_values(unpack('f*', base64_decode($serialized)));
    }

    /**
     * Clear related caches
     * 
     * @param int $chunk_id Chunk ID
     */
    private function clear_related_caches(int $chunk_id): void {
        $this->cache_manager->delete('chunk_' . $chunk_id);
        $this->cache_manager->delete('similar_*');
    }

    /**
     * Delete all data for a document (chunks and embeddings)
     * 
     * @param int $document_id Document ID
     * @return array Result with deletion counts
     */
    public function delete_document_embeddings(int $document_id): array {
        global $wpdb;

        try {
            // Get chunk IDs for this document
            $chunk_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$this->table_prefix}chunks WHERE document_id = %d",
                $document_id
            ));

            $deleted_chunks = 0;
            $deleted_embeddings = 0;

            if (!empty($chunk_ids)) {
                // Delete embeddings for these chunks
                if ($this->pinecone_database && $this->pinecone_database->is_configured()) {
                    // Delete from Pinecone
                    $pinecone_result = $this->pinecone_database->delete_vectors($chunk_ids);
                    if ($pinecone_result && isset($pinecone_result['deletedCount'])) {
                        $deleted_embeddings = $pinecone_result['deletedCount'];
                    } else {
                        // If no deletedCount in response, assume all were deleted
                        $deleted_embeddings = count($chunk_ids);
                    }
                } else {
                    // Delete from local embeddings table
                    $deleted_embeddings = $wpdb->delete(
                $this->table_prefix . 'embeddings',
                        ['chunk_id' => $chunk_ids],
                        ['%d']
                    );
                }

                // Delete chunks
                $deleted_chunks = $wpdb->delete(
                    $this->table_prefix . 'chunks',
                ['document_id' => $document_id],
                ['%d']
            );
            }

            // Clear caches
            $this->cache_manager->delete('similar_*');
            foreach ($chunk_ids as $chunk_id) {
                $this->cache_manager->delete('chunk_' . $chunk_id);
            }

            return [
                'deleted_chunks' => $deleted_chunks,
                'deleted_embeddings' => $deleted_embeddings,
                'success' => true
            ];

        } catch (\Exception $e) {
            throw new Vector_Database_Exception(
                esc_html__('Failed to delete document data: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Get database statistics
     * 
     * @return array Database statistics
     */
    public function get_stats(): array {
        global $wpdb;

        try {
            return [
                'total_embeddings' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_prefix}embeddings"),
                'total_chunks' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_prefix}chunks"),
                'total_documents' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT document_id) FROM {$this->table_prefix}chunks"),
                'average_chunk_size' => (int) $wpdb->get_var("SELECT AVG(LENGTH(content)) FROM {$this->table_prefix}chunks"),
            ];

        } catch (\Exception $e) {
            throw new Vector_Database_Exception(
                esc_html__('Failed to get database statistics: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }
}

/**
 * Custom exception for vector database operations
 */
class Vector_Database_Exception extends \Exception {} 