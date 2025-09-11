<?php
namespace AI_BotKit\Core;

use AI_BotKit\Utils\Cache_Manager;
use AI_BotKit\Core\Document_Loader;
use AI_BotKit\Core\Pinecone_Database;
use AI_BotKit\Core\Embeddings_Generator;
use AI_BotKit\Core\LLM_Client;

/**
 * Migration Manager
 * 
 * Handles data migration between local database and Pinecone,
 * including bulk operations, selective migration, and data validation.
 */
class Migration_Manager {
    /**
     * Get migration batch size from configuration
     */
    private function get_batch_size(): int {
        return get_option('ai_botkit_batch_size', 20);
    }

    /**
     * Cache Manager instance
     */
    private $cache_manager;

    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Vector Database instance
     */
    private $vector_database;

    /**
     * Pinecone Database instance
     */
    private $pinecone_database;

    /**
     * Initialize the migration manager
     * 
     * @param RAG_Engine $rag_engine RAG Engine instance
     * @param Vector_Database $vector_database Vector Database instance
     */
    public function __construct(RAG_Engine $rag_engine, Vector_Database $vector_database) {
        $this->rag_engine = $rag_engine;
        $this->vector_database = $vector_database;
        $this->cache_manager = new Cache_Manager();
        
        // Initialize Pinecone database if enabled
        if (get_option('ai_botkit_enable_pinecone', 0)) {
            $this->pinecone_database = new Pinecone_Database();
        }
    }

    /**
     * Get migration status and available options
     * 
     * @return array Migration status information
     */
    public function get_migration_status(): array {
        global $wpdb;
        
        $local_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chunks"
        );
        
        $pinecone_enabled = get_option('ai_botkit_enable_pinecone', 0);
        $pinecone_configured = false;
        $pinecone_count = 0;
        
        if ($pinecone_enabled && $this->pinecone_database) {
            $pinecone_configured = $this->pinecone_database->is_configured();
            
            if ($pinecone_configured) {
                try {
                    $stats = $this->pinecone_database->describe_index_stats();
                    $pinecone_count = isset($stats['totalVectorCount']) ? (int) $stats['totalVectorCount'] : 0;
                } catch (\Exception $e) {
                    error_log('AI BotKit Migration Error: Failed to get Pinecone stats - ' . $e->getMessage());
                    $pinecone_count = 0;
                }
            }
        }
        
        return [
            'local_database' => [
                'enabled' => true,
                'configured' => true,
                'chunk_count' => (int) $local_count,
                'status' => $local_count > 0 ? 'has_data' : 'empty'
            ],
            'pinecone_database' => [
                'enabled' => (bool) $pinecone_enabled,
                'configured' => $pinecone_configured,
                'chunk_count' => $pinecone_count,
                'status' => $pinecone_configured ? 'configured' : 'not_configured'
            ],
            'migration_available' => $this->can_migrate(),
            'last_migration' => get_option('ai_botkit_last_migration_time', ''),
            'migration_in_progress' => get_transient('ai_botkit_migration_in_progress', false)
        ];
    }

    /**
     * Check if migration is possible
     * 
     * @return bool True if migration is possible
     */
    private function can_migrate(): bool {
        $pinecone_enabled = get_option('ai_botkit_enable_pinecone', 0);
        
        if (!$pinecone_enabled || !$this->pinecone_database) {
            return false;
        }
        
        return $this->pinecone_database->is_configured();
    }

    /**
     * Start migration process
     * 
     * @param array $options Migration options
     * @return array Migration result
     */
    public function start_migration(array $options = []): array {
        // Check if migration is already in progress
        if (get_transient('ai_botkit_migration_in_progress', false)) {
            return [
                'success' => false,
                'message' => __('Migration is already in progress', 'ai-botkit-for-lead-generation')
            ];
        }

        // Set migration in progress
        set_transient('ai_botkit_migration_in_progress', true, 3600); // 1 hour timeout

        try {
            $direction = $options['direction'] ?? 'to_pinecone';
            $scope = $options['scope'] ?? 'all';
            $content_types = $options['content_types'] ?? [];
            $date_range = $options['date_range'] ?? [];

            // Validate options
            if (!$this->validate_migration_options($options)) {
                return [
                    'success' => false,
                    'message' => __('Invalid migration options', 'ai-botkit-for-lead-generation')
                ];
            }

            // Start migration based on direction
            if ($direction === 'to_pinecone') {
                $result = $this->migrate_to_pinecone($scope, $content_types, $date_range);
            } else {
                $result = $this->migrate_to_local($scope, $content_types, $date_range);
            }

            // Update last migration time
            update_option('ai_botkit_last_migration_time', current_time('mysql'));

            return $result;

        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Migration failed: ', 'ai-botkit-for-lead-generation') . $e->getMessage()
            ];
        } finally {
            // Clear migration in progress flag
            delete_transient('ai_botkit_migration_in_progress');
        }
    }

    /**
     * Migrate data to Pinecone
     * 
     * @param string $scope Migration scope
     * @param array $content_types Content types to migrate
     * @param array $date_range Date range for migration
     * @return array Migration result
     */
    private function migrate_to_pinecone(string $scope, array $content_types, array $date_range): array {
        global $wpdb;

        // Get chunks to migrate
        $chunks = $this->get_chunks_for_migration($scope, $content_types, $date_range);
        
        if (empty($chunks)) {
            return [
                'success' => true,
                'message' => __('No data to migrate', 'ai-botkit-for-lead-generation'),
                'migrated_count' => 0
            ];
        }

        $migrated_count = 0;
        $error_count = 0;

        // Process chunks in batches
        $batches = array_chunk($chunks, $this->get_batch_size());

        foreach ($batches as $batch) {
            try {
                $vectors = [];
                
                foreach ($batch as $chunk) {
                    // Get embedding for chunk
                    $embedding = $this->get_chunk_embedding($chunk);
                    
                    if ($embedding) {
                        // Reconstruct complete metadata to match normal flow
                        $complete_metadata = $this->reconstruct_metadata($chunk);
                        
                        $vectors[] = [
                            'id' => (string) $chunk->id,
                            'values' => $embedding,
                            'metadata' => $complete_metadata
                        ];
                    }
                }

                // Upsert to Pinecone
                if (!empty($vectors)) {
                    $result = $this->pinecone_database->upsert_vectors($vectors);
                    $migrated_count += count($vectors);
                }

            } catch (\Exception $e) {
                error_log('AI BotKit Migration Error: Batch processing failed - ' . $e->getMessage());
                $error_count += count($batch);
            }
        }

        return [
            'success' => $error_count === 0,
            'message' => sprintf(
                __('Migration completed. Migrated: %d, Errors: %d', 'ai-botkit-for-lead-generation'),
                $migrated_count,
                $error_count
            ),
            'migrated_count' => $migrated_count,
            'error_count' => $error_count
        ];
    }

    /**
     * Migrate data to local database
     * 
     * @param string $scope Migration scope
     * @param array $content_types Content types to migrate
     * @param array $date_range Date range for migration
     * @return array Migration result
     */
    private function migrate_to_local(string $scope, array $content_types, array $date_range): array {
        if (!$this->pinecone_database || !$this->pinecone_database->is_configured()) {
            return [
                'success' => false,
                'message' => __('Pinecone is not configured', 'ai-botkit-for-lead-generation')
            ];
        }

        try {
            // Get vectors from Pinecone based on scope
            $vectors = $this->get_vectors_from_pinecone($scope, $content_types, $date_range);
            
            if (empty($vectors)) {
                return [
                    'success' => true,
                    'message' => __('No data to migrate from Pinecone', 'ai-botkit-for-lead-generation'),
                    'migrated_count' => 0
                ];
            }

            $migrated_count = 0;
            $error_count = 0;

            // Process vectors in batches
            $batches = array_chunk($vectors, $this->get_batch_size());

            foreach ($batches as $batch) {
                try {
                    foreach ($batch as $vector) {
                        $result = $this->store_vector_to_local($vector);
                        if ($result['success']) {
                            $migrated_count++;
                        } else {
                            $error_count++;
                            error_log('AI BotKit Migration Error: Failed to store vector to local - ' . $result['message']);
                        }
                    }
                } catch (\Exception $e) {
                    error_log('AI BotKit Migration Error: Batch processing failed - ' . $e->getMessage());
                    $error_count += count($batch);
                }
            }

            return [
                'success' => $error_count === 0,
                'message' => sprintf(
                    __('Migration completed. Migrated: %d, Errors: %d', 'ai-botkit-for-lead-generation'),
                    $migrated_count,
                    $error_count
                ),
                'migrated_count' => $migrated_count,
                'error_count' => $error_count
            ];

        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: Pinecone to local failed - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Migration failed: ', 'ai-botkit-for-lead-generation') . $e->getMessage()
            ];
        }
    }

    /**
     * Get vectors from Pinecone based on criteria
     * 
     * @param string $scope Migration scope
     * @param array $content_types Content types to migrate
     * @param array $date_range Date range for migration
     * @return array Array of vector data
     */
    private function get_vectors_from_pinecone(string $scope, array $content_types, array $date_range): array {
        try {
            // Build filters based on scope
            $filters = [];
            
            if ($scope === 'by_type' && !empty($content_types)) {
                $filters['post_type'] = ['$in' => $content_types];
            }
            
            if ($scope === 'by_date' && !empty($date_range)) {
                if (!empty($date_range['start'])) {
                    $filters['created_at'] = ['$gte' => $date_range['start']];
                }
                if (!empty($date_range['end'])) {
                    $filters['created_at'] = ['$lte' => $date_range['end']];
                }
            }
            
            // Query Pinecone for vectors
            $query_result = $this->pinecone_database->query_vectors(
                [0.0], // Dummy vector for query
                10000, // Large limit to get all vectors
                0.0,   // Low similarity threshold
                $filters
            );
            
            return $query_result['matches'] ?? [];
            
        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: Failed to get vectors from Pinecone - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Store vector data to local database
     * 
     * @param array $vector Vector data from Pinecone
     * @return array Result with success status
     */
    private function store_vector_to_local(array $vector): array {
        global $wpdb;
        
        try {
            $vector_id = $vector['id'];
            $metadata = $vector['metadata'] ?? [];
            $content = $metadata['content'] ?? '';
            $document_id = $metadata['document_id'] ?? 0;
            $chunk_index = $metadata['chunk_index'] ?? 0;
            
            if (empty($content) || empty($document_id)) {
                return [
                    'success' => false,
                    'message' => 'Missing required data: content or document_id'
                ];
            }

            // Validate that document exists
            $document_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents WHERE id = %d",
                $document_id
            ));

            if (!$document_exists) {
                return [
                    'success' => false,
                    'message' => 'Document does not exist: ' . $document_id
                ];
            }

            // Clean metadata for storage (remove content and document_id as they're stored separately)
            $clean_metadata = $metadata;
            unset($clean_metadata['content']);
            unset($clean_metadata['document_id']);
            
            // Add migration tracking metadata
            $clean_metadata['migration_source'] = 'pinecone_to_local';
            $clean_metadata['migration_timestamp'] = current_time('mysql');
            
            // Check if chunk already exists
            $existing_chunk = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ai_botkit_chunks WHERE id = %d",
                $vector_id
            ));
            
            if ($existing_chunk) {
                // Update existing chunk
                $result = $wpdb->update(
                    $wpdb->prefix . 'ai_botkit_chunks',
                    [
                        'content' => $content,
                        'metadata' => wp_json_encode($clean_metadata),
                        'chunk_index' => $chunk_index
                    ],
                    ['id' => $vector_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );
                
                if ($result === false) {
                    return [
                        'success' => false,
                        'message' => 'Failed to update existing chunk'
                    ];
                }
            } else {
                // Insert new chunk
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ai_botkit_chunks',
                    [
                        'id' => $vector_id,
                        'document_id' => $document_id,
                        'content' => $content,
                        'chunk_index' => $chunk_index,
                        'metadata' => wp_json_encode($clean_metadata),
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s', '%s']
                );
                
                if ($result === false) {
                    return [
                        'success' => false,
                        'message' => 'Failed to insert new chunk'
                    ];
                }
            }
            
            // Store embedding in local embeddings table
            $embedding_result = $wpdb->replace(
                $wpdb->prefix . 'ai_botkit_embeddings',
                [
                    'chunk_id' => $vector_id,
                    'embedding' => $this->serialize_vector($vector['values']),
                    'model' => 'text-embedding-3-small',
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            if ($embedding_result === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to store embedding'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Successfully stored vector to local database'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Serialize vector for storage
     * 
     * @param array $vector Vector array
     * @return string Serialized vector
     */
    private function serialize_vector(array $vector): string {
        return wp_json_encode($vector);
    }

    /**
     * Get chunks for migration based on criteria
     * 
     * @param string $scope Migration scope
     * @param array $content_types Content types
     * @param array $date_range Date range
     * @return array Chunks to migrate
     */
    private function get_chunks_for_migration(string $scope, array $content_types, array $date_range): array {
        global $wpdb;

        $where_conditions = [];
        $where_values = [];

        // Add content type filter
        if (!empty($content_types)) {
            $placeholders = implode(',', array_fill(0, count($content_types), '%s'));
            $where_conditions[] = "d.source_type IN ($placeholders)";
            $where_values = array_merge($where_values, $content_types);
        }

        // Add date range filter
        if (!empty($date_range['start']) && !empty($date_range['end'])) {
            $where_conditions[] = "d.created_at BETWEEN %s AND %s";
            $where_values[] = $date_range['start'];
            $where_values[] = $date_range['end'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $query = "
            SELECT c.*, d.source_type as post_type, d.source_id as post_id, d.mime_type, d.file_path
            FROM {$wpdb->prefix}ai_botkit_chunks c
            JOIN {$wpdb->prefix}ai_botkit_documents d ON c.document_id = d.id
            $where_clause
            ORDER BY c.created_at ASC
        ";

        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_results($query);
        }
    }

    /**
     * Reconstruct complete metadata for migration to match normal flow
     * 
     * @param object $chunk Chunk object from database
     * @return array Complete metadata structure
     */
    private function reconstruct_metadata($chunk): array {
        // Parse existing metadata from JSON
        $existing_metadata = [];
        if (!empty($chunk->metadata)) {
            $existing_metadata = json_decode($chunk->metadata, true) ?: [];
        }

        // Determine source type and extension
        $source_type = $chunk->post_type ?? 'post';
        $extension = 'txt';
        $mime_type = 'text/plain';
        
        if ($source_type === 'file' && !empty($chunk->file_path)) {
            $extension = pathinfo($chunk->file_path, PATHINFO_EXTENSION);
            $mime_type = $chunk->mime_type ?: wp_check_filetype($chunk->file_path)['type'];
        }

        // Get total chunks for this document
        global $wpdb;
        $total_chunks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chunks WHERE document_id = %d",
            $chunk->document_id
        ));

        // Calculate chunk relationships
        $chunk_index = (int) $chunk->chunk_index;
        $has_previous = $chunk_index > 0;
        $has_next = $chunk_index < ($total_chunks - 1);

        // Get post modification date if it's a post
        $last_modified = current_time('mysql');
        if ($source_type === 'post' && !empty($chunk->post_id)) {
            $post = get_post($chunk->post_id);
            if ($post) {
                $last_modified = $post->post_modified;
            }
        }

        // Build complete metadata structure matching normal flow
        return array_merge($existing_metadata, [
            // Core identification
            'source' => $source_type,
            'document_id' => (int) $chunk->document_id,
            'post_id' => !empty($chunk->post_id) ? (int) $chunk->post_id : null,
            'post_type' => $chunk->post_type ?? null,
            
            // File metadata
            'mime_type' => $mime_type,
            'extension' => $extension,
            'last_modified' => $last_modified,
            
            // Chunk positioning
            'chunk_index' => $chunk_index,
            'total_chunks' => (int) $total_chunks,
            
            // Chunk relationships
            'has_previous' => $has_previous,
            'has_next' => $has_next,
            'has_overlap_prev' => $existing_metadata['has_overlap_prev'] ?? false,
            'has_overlap_next' => $existing_metadata['has_overlap_next'] ?? false,
            
            // Size information
            'size' => strlen($chunk->content),
            'original_size' => $existing_metadata['original_size'] ?? strlen($chunk->content),
            
            // Migration tracking
            'migration_source' => 'local_to_pinecone',
            'migration_timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Get embedding for a chunk
     * 
     * @param object $chunk Chunk object
     * @return array|null Embedding array or null
     */
    private function get_chunk_embedding($chunk): ?array {
        try {
            // Use the embeddings generator to generate embedding
            $embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator(new \AI_BotKit\Core\LLM_Client());
            
            // Format chunk data for the embeddings generator
            $chunk_data = [
                'content' => $chunk->content,
                'metadata' => [
                    'chunk_id' => $chunk->id,
                    'document_id' => $chunk->document_id,
                    'chunk_index' => $chunk->chunk_index ?? 0
                ]
            ];
            
            $embeddings = $embeddings_generator->generate_embeddings([$chunk_data]);
            
            if (!empty($embeddings) && isset($embeddings[0]['embedding'])) {
                return $embeddings[0]['embedding'];
            }
            
            return null;
        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: Embedding generation failed - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate migration options
     * 
     * @param array $options Migration options
     * @return bool True if valid
     */
    private function validate_migration_options(array $options): bool {
        $valid_directions = ['to_pinecone', 'to_local'];
        $valid_scopes = ['all', 'selected', 'by_type', 'by_date'];

        if (!isset($options['direction']) || !in_array($options['direction'], $valid_directions)) {
            return false;
        }

        if (!isset($options['scope']) || !in_array($options['scope'], $valid_scopes)) {
            return false;
        }

        return true;
    }

    /**
     * Get available content types for migration
     * 
     * @return array Available content types
     */
    public function get_available_content_types(): array {
        global $wpdb;

        // First try to get from documents table
        $types = $wpdb->get_results(
            "SELECT DISTINCT source_type as post_type, COUNT(*) as count
            FROM {$wpdb->prefix}ai_botkit_documents
            GROUP BY source_type",
            ARRAY_A
        );

        $formatted = [];
        foreach ($types as $type) {
            $formatted[$type['post_type']] = [
                'name' => ucfirst(str_replace('-', ' ', $type['post_type'])),
                'count' => (int) $type['count']
            ];
        }

        // If no documents found, get from WordPress post types
        if (empty($formatted)) {
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                $count = wp_count_posts($post_type->name)->publish;
                if ($count > 0) {
                    $formatted[$post_type->name] = [
                        'name' => $post_type->labels->singular_name,
                        'count' => $count
                    ];
                }
            }
        }

        // If still empty, provide some default content types
        if (empty($formatted)) {
            $formatted = [
                'post' => [
                    'name' => 'Posts',
                    'count' => 0
                ],
                'page' => [
                    'name' => 'Pages', 
                    'count' => 0
                ]
            ];
        }

        return $formatted;
    }

    /**
     * Clear data from specified database
     * 
     * @param string $database Database to clear ('local' or 'pinecone')
     * @param array $options Clear options
     * @return array Clear result
     */
    public function clear_database(string $database, array $options = []): array {
        try {
            if ($database === 'local') {
                return $this->clear_local_database($options);
            } elseif ($database === 'pinecone') {
                return $this->clear_pinecone_database($options);
            } else {
                return [
                    'success' => false,
                    'message' => __('Invalid database specified', 'ai-botkit-for-lead-generation')
                ];
            }
        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: Database clear failed - ' . $e->getMessage());
            return [
                'success' => false,
                'message' => __('Failed to clear database: ', 'ai-botkit-for-lead-generation') . $e->getMessage()
            ];
        }
    }

    /**
     * Clear local database
     * 
     * @param array $options Clear options
     * @return array Clear result
     */
    private function clear_local_database(array $options): array {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'ai_botkit_chunks',
            $wpdb->prefix . 'ai_botkit_documents',
            $wpdb->prefix . 'ai_botkit_embeddings'
        ];

        $cleared_count = 0;
        foreach ($tables as $table) {
            $result = $wpdb->query("TRUNCATE TABLE $table");
            if ($result !== false) {
                $cleared_count++;
            }
        }

        return [
            'success' => $cleared_count > 0,
            'message' => sprintf(
                __('Cleared %d tables from local database', 'ai-botkit-for-lead-generation'),
                $cleared_count
            ),
            'cleared_tables' => $cleared_count
        ];
    }

    /**
     * Clear Pinecone database
     * 
     * @param array $options Clear options
     * @return array Clear result
     */
    private function clear_pinecone_database(array $options): array {
        if (!$this->pinecone_database || !$this->pinecone_database->is_configured()) {
            return [
                'success' => false,
                'message' => __('Pinecone is not configured', 'ai-botkit-for-lead-generation')
            ];
        }

        try {
            // Note: This would require implementing a delete_all method in Pinecone_Database
            // For now, we'll return a placeholder response
            return [
                'success' => false,
                'message' => __('Pinecone clear functionality is not yet implemented', 'ai-botkit-for-lead-generation')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => __('Failed to clear Pinecone: ', 'ai-botkit-for-lead-generation') . $e->getMessage()
            ];
        }
    }
}
