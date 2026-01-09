<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Cache_Manager;

/**
 * Embeddings Generator class for converting text into vector embeddings
 * 
 * Features:
 * - Efficient batch processing
 * - Caching support
 * - Error handling
 * - Progress tracking
 * - Rate limiting
 */
class Embeddings_Generator {
    /**
     * LLM Client instance
     */
    private $llm_client;

    /**
     * Cache Manager instance
     */
    private $cache_manager;

    /**
     * Default embedding model
     */
    private $default_model;

    /**
     * Batch size for processing
     */
    private $batch_size;

    /**
     * Initialize the generator
     * 
     * @param LLM_Client $llm_client LLM client instance
     */
    public function __construct(LLM_Client $llm_client) {
        $this->llm_client = $llm_client;
        $this->cache_manager = new Cache_Manager();
        $this->default_model = get_option('ai_botkit_embedding_model', 'text-embedding-3-small');
        $this->batch_size = get_option('ai_botkit_batch_size', 20);
    }

    /**
     * Generate embeddings for text chunks
     * 
     * @param array $chunks Array of text chunks with metadata
     * @param string|null $model Optional model to use
     * @return array Array of embeddings with metadata
     * @throws Embedding_Generation_Exception
     */
    public function generate_embeddings(array $chunks, ?string $model = null): array {
        $model = $model ?? $this->default_model;
        
        $embeddings = [];
        $batch = [];
        $batch_metadata = [];

        try {
            foreach ($chunks as $index => $chunk) {
                // Check cache first
                $cache_key = 'embedding_' . md5($chunk['content'] . $model);
                $cached_embedding = $this->cache_manager->get($cache_key);

                if ($cached_embedding !== false) {
                    $embeddings[] = array_merge($cached_embedding, [
                        'metadata' => $chunk['metadata']
                    ]);
                    continue;
                }

                // Add to batch for processing
                $batch[] = $chunk['content'];
                $batch_metadata[] = isset($chunk['metadata']) ? $chunk['metadata'] : [];

                // Process batch if full or last chunk
                if (count($batch) >= $this->batch_size || $index === count($chunks) - 1) {
                    $batch_embeddings = $this->process_batch($batch, $model);

                    // Combine embeddings with metadata and cache
                    foreach ($batch_embeddings as $i => $embedding) {
                        $result = [
                            'embedding' => $embedding,
                            'model' => $model,
                            'metadata' => $batch_metadata[$i]
                        ];

                        $result['metadata']['document_id'] = isset($batch_metadata[$i]['document_id']) ? $batch_metadata[$i]['document_id'] : null;
                        $result['metadata']['content'] = isset($batch[$i]) ? $batch[$i] : null;

                        // Cache the embedding
                        $cache_key = 'embedding_' . md5($batch[$i] . $model);
                        $this->cache_manager->set($cache_key, $result, DAY_IN_SECONDS);

                        $embeddings[] = $result;
                    }

                    // Clear batch
                    $batch = [];
                    $batch_metadata = [];

                    // Allow other processes to run
                    if (count($chunks) > $this->batch_size) {
                        $this->maybe_sleep();
                    }
                }
            }

            return $embeddings;

        } catch (\Exception $e) {
            throw new Embedding_Generation_Exception(
                esc_html__('Failed to generate embeddings: ', 'knowvault') . esc_html($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Process a batch of texts
     * 
     * @param array $texts Array of texts to process
     * @param string $model Model to use
     * @return array Array of embeddings
     */
    private function process_batch(array $texts, string $model): array {
        // Track progress
        $total_tokens = array_sum(array_map([$this, 'estimate_tokens'], $texts));
        
        do_action('ai_botkit_embedding_batch_start', [
            'count' => count($texts),
            'total_tokens' => $total_tokens,
            'model' => $model
        ]);

        // Generate embeddings
        $embeddings = $this->llm_client->generate_embeddings($texts);

        do_action('ai_botkit_embedding_batch_complete', [
            'count' => count($texts),
            'total_tokens' => $total_tokens,
            'model' => $model
        ]);

        return $embeddings;
    }

    /**
     * Estimate token count for a text
     * 
     * @param string $text Text to estimate tokens for
     * @return int Estimated token count
     */
    private function estimate_tokens(string $text): int {
        // Rough estimate: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Sleep between batches if necessary
     */
    private function maybe_sleep(): void {
        if (function_exists('sleep')) {
            sleep(1); // 1 second delay between large batches
        }
    }

    /**
     * Set batch size
     * 
     * @param int $size New batch size
     * @return void
     */
    public function set_batch_size(int $size): void {
        $this->batch_size = max(1, min(100, $size));
    }

    /**
     * Set default model
     * 
     * @param string $model Model identifier
     * @return void
     */
    public function set_default_model(string $model): void {
        $this->default_model = $model;
    }

    /**
     * Get current settings
     * 
     * @return array Current settings
     */
    public function get_settings(): array {
        return [
            'default_model' => $this->default_model,
            'batch_size' => $this->batch_size,
        ];
    }
}

/**
 * Custom exception for embedding generation errors
 */
class Embedding_Generation_Exception extends \Exception {} 