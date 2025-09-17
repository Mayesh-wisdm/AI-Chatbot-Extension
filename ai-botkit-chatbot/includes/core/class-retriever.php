<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Cache_Manager;

/**
 * Retriever class for finding and assembling relevant context
 * 
 * Features:
 * - Query processing
 * - Context assembly
 * - Result ranking
 * - Cache integration
 * - Metadata filtering
 */
class Retriever {
    /**
     * Vector Database instance
     */
    private $vector_db;

    /**
     * Embeddings Generator instance
     */
    private $embeddings_generator;

    /**
     * Cache Manager instance
     */
    private $cache_manager;

    /**
     * Default embedding model
     */
    private $default_model;

    /**
     * Default retrieval settings
     */
    private const DEFAULT_SETTINGS = [
        'max_results' => 5,
        'min_similarity' => 0.0, // Lowered from 0.7 to allow more matches
        'context_window' => 3,
        'deduplication_threshold' => 0.95,
        'reranking_enabled' => true
    ];

    /**
     * Initialize the retriever
     * 
     * @param Vector_Database $vector_db Vector database instance
     * @param Embeddings_Generator $embeddings_generator Embeddings generator instance
     * @param string|null $default_model Default embedding model
     */
    public function __construct(
        Vector_Database $vector_db,
        Embeddings_Generator $embeddings_generator,
        ?string $default_model = null
    ) {
        $this->vector_db = $vector_db;
        $this->embeddings_generator = $embeddings_generator;
        $this->cache_manager = new Cache_Manager();
        $this->default_model = $default_model ?? get_option('ai_botkit_embedding_model', 'text-embedding-3-small');
    }

    /**
     * Find relevant context for a query
     * 
     * @param string $query Query text
     * @param array $options Search options
     * @return array Array of relevant context chunks
     * @throws Retriever_Exception
     */
    public function find_context(string $query, int $bot_id, array $options = []): array {
        try {
            // Try cache first
            $cache_key = 'context_' . md5($query . serialize($options));
            $cached_results = $this->cache_manager->get($cache_key);

            if ($cached_results !== false) {
                return $cached_results;
            }

            // Process options
            $options = array_merge(self::DEFAULT_SETTINGS, $options);

            // Generate query embedding
            $query_embedding = $this->embeddings_generator->generate_embeddings([
                ['content' => $query]
            ], $this->default_model)[0]['embedding'];

            // Find similar chunks
            $similar_chunks = $this->vector_db->find_similar(
                $bot_id,
                $query_embedding,
                $options['max_results'],
                $options['min_similarity'],
                $options['filters'] ?? []
            );

            // Debug logging for similarity search
            error_log('AI BotKit Retriever Debug: Found ' . count($similar_chunks) . ' similar chunks');
            if (!empty($similar_chunks)) {
                error_log('AI BotKit Retriever Debug: Top similarity scores: ' . implode(', ', array_slice(array_column($similar_chunks, 'similarity'), 0, 3)));
            } else {
                error_log('AI BotKit Retriever Debug: No similar chunks found - checking if embeddings exist in database');
                // Check if there are any embeddings in the database
                global $wpdb;
                $total_embeddings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_embeddings");
                error_log('AI BotKit Retriever Debug: Total embeddings in database: ' . $total_embeddings);
            }

            // Process and assemble context
            $context = $this->process_results($similar_chunks, $options);

            // Cache results
            $this->cache_manager->set($cache_key, $context, HOUR_IN_SECONDS);

            return $context;

        } catch (\Exception $e) {
            throw new Retriever_Exception(
                esc_html__('Failed to find context: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Process and assemble search results
     * 
     * @param array $chunks Similar chunks
     * @param array $options Processing options
     * @return array Processed context
     */
    private function process_results(array $chunks, array $options): array {
        // Deduplicate results
        $chunks = $this->deduplicate_chunks($chunks, $options['deduplication_threshold']);

        // Rerank if enabled
        if ($options['reranking_enabled']) {
            $chunks = $this->rerank_chunks($chunks);
        }

        // Expand context window
        $chunks = $this->expand_context($chunks, $options['context_window']);

        // Format final context
        return array_map(function($chunk) {
            return [
                'content' => $chunk['current']['content'],
                'metadata' => $chunk['current']['metadata'],
                'relevance' => $chunk['current']['similarity'],
                'source' => isset($chunk['current']['metadata']) ? $this->format_source($chunk['current']['metadata']) : []
            ];
        }, $chunks);
    }

    /**
     * Deduplicate similar chunks
     * 
     * @param array $chunks Array of chunks
     * @param float $threshold Similarity threshold
     * @return array Deduplicated chunks
     */
    private function deduplicate_chunks(array $chunks, float $threshold): array {
        $deduplicated = [];
        
        foreach ($chunks as $chunk) {
            $is_duplicate = false;
            
            foreach ($deduplicated as $existing) {
                $similarity = $this->calculate_text_similarity(
                    $chunk['content'],
                    $existing['content']
                );
                
                if ($similarity >= $threshold) {
                    $is_duplicate = true;
                    break;
                }
            }
            
            if (!$is_duplicate) {
                $deduplicated[] = $chunk;
            }
        }
        
        return $deduplicated;
    }

    /**
     * Rerank chunks based on additional criteria
     * 
     * @param array $chunks Array of chunks
     * @return array Reranked chunks
     */
    private function rerank_chunks(array $chunks): array {
        // Sort by combined score of similarity and other factors
        usort($chunks, function($a, $b) {
            $score_a = $this->calculate_rank_score($a);
            $score_b = $this->calculate_rank_score($b);
            return $score_b <=> $score_a;
        });

        return $chunks;
    }

    /**
     * Calculate ranking score for a chunk
     * 
     * @param array $chunk Chunk data
     * @return float Ranking score
     */
    private function calculate_rank_score(array $chunk): float {
        $score = $chunk['similarity'];

        // Boost based on metadata factors
        if (isset($chunk['metadata'])) {
            // Boost recent content
            if (isset($chunk['metadata']['created_at'])) {
                $age_days = (time() - strtotime($chunk['metadata']['created_at'])) / DAY_IN_SECONDS;
                $recency_boost = 1 / (1 + $age_days / 30); // Decay over 30 days
                $score *= (1 + $recency_boost * 0.2);
            }

            // Boost based on content type
            if (isset($chunk['metadata']['post_type'])) {
                $type_boosts = [
                    'page' => 1.2,
                    'post' => 1.1,
                    'product' => 1.15,
                    'course' => 1.15
                ];
                $score *= ($type_boosts[$chunk['metadata']['post_type']] ?? 1.0);
            }
        }

        return $score;
    }

    /**
     * Expand context window around chunks
     * 
     * @param array $chunks Array of chunks
     * @param int $window_size Context window size
     * @return array Expanded chunks
     */
    private function expand_context(array $chunks, int $window_size): array {
        global $wpdb;
        $expanded = [];

        foreach ($chunks as $chunk) {
            $context = [
                'before' => [],
                'current' => $chunk,
                'after' => []
            ];

            // Get surrounding chunks if from same document
            if (isset($chunk['metadata']['document_id'])) {
                $current_index = $chunk['metadata']['chunk_index'];
                $document_id = $chunk['metadata']['document_id'];

                // Get previous chunks
                $before = $wpdb->get_results($wpdb->prepare(
                    "SELECT content, metadata FROM {$wpdb->prefix}ai_botkit_chunks
                    WHERE document_id = %d AND chunk_index < %d
                    ORDER BY chunk_index DESC LIMIT %d",
                    $document_id,
                    $current_index,
                    $window_size
                ), ARRAY_A);

                // Get next chunks
                $after = $wpdb->get_results($wpdb->prepare(
                    "SELECT content, metadata FROM {$wpdb->prefix}ai_botkit_chunks
                    WHERE document_id = %d AND chunk_index > %d
                    ORDER BY chunk_index ASC LIMIT %d",
                    $document_id,
                    $current_index,
                    $window_size
                ), ARRAY_A);

                $context['before'] = array_reverse($before);
                $context['after'] = $after;
            }

            $expanded[] = $context;
        }

        return $expanded;
    }

    /**
     * Calculate text similarity between two strings
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score
     */
    private function calculate_text_similarity(string $text1, string $text2): float {
        // Simple Jaccard similarity for quick comparison
        $tokens1 = array_flip(str_word_count(strtolower($text1), 1));
        $tokens2 = array_flip(str_word_count(strtolower($text2), 1));
        
        $intersection = count(array_intersect_key($tokens1, $tokens2));
        $union = count($tokens1 + $tokens2);
        
        return $union > 0 ? $intersection / $union : 0;
    }

    /**
     * Format source information from metadata
     * 
     * @param array $metadata Chunk metadata
     * @return array Formatted source info
     */
    private function format_source(array $metadata): array {
        $source = [
            'type' => $metadata['source_type'] ?? 'unknown',
            'title' => $metadata['title'] ?? '',
            'url' => '',
        ];

        // Add source URL based on type
        if (isset($metadata['post_id'])) {
            $source['url'] = get_permalink($metadata['post_id']);
        } elseif (isset($metadata['file_path'])) {
            $source['url'] = wp_get_attachment_url($metadata['file_path']);
        } elseif (isset($metadata['url'])) {
            $source['url'] = $metadata['url'];
        }

        return $source;
    }

    /**
     * Get retriever settings
     * 
     * @return array Current settings
     */
    public function get_settings(): array {
        return [
            'default_model' => $this->default_model,
            'default_settings' => self::DEFAULT_SETTINGS
        ];
    }
}

/**
 * Custom exception for retriever operations
 */
class Retriever_Exception extends \Exception {} 