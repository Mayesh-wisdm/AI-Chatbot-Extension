<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\Document_Loader;
use AI_BotKit\Core\RAG_Engine;

/**
 * WordPress Content Integration
 * 
 * Handles integration with WordPress content types, including automatic processing
 * of posts, pages, and custom post types.
 */
class WordPress_Content {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Document Loader instance
     */
    private $document_loader;

    /**
     * Post types to process
     */
    private $post_types;

    /**
     * Initialize the integration
     * 
     * @param RAG_Engine $rag_engine RAG Engine instance
     * @param Document_Loader $document_loader Document Loader instance
     */
    public function __construct(RAG_Engine $rag_engine, Document_Loader $document_loader) {
        $this->rag_engine = $rag_engine;
        $this->document_loader = $document_loader;
        $this->post_types = $this->get_enabled_post_types();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Content update hooks
        add_action('save_post', [$this, 'handle_post_save'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_post_delete']);
        add_action('wp_trash_post', [$this, 'handle_post_trash']);
        add_action('untrash_post', [$this, 'handle_post_untrash']);

        // Term hooks
        add_action('edited_term', [$this, 'handle_term_update'], 10, 3);
        add_action('delete_term', [$this, 'handle_term_delete'], 10, 3);

        // Bulk processing hooks
        add_action('ai_botkit_process_content_queue', [$this, 'process_content_queue']);
    }

    /**
     * Handle post save/update
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function handle_post_save(int $post_id, \WP_Post $post, bool $update): void {
        // Skip revisions and autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Skip if post type not enabled
        if (!in_array($post->post_type, $this->post_types)) {
            return;
        }

        // Skip if post is not published
        if ($post->post_status !== 'publish') {
            return;
        }

        // Check if content actually changed using hash comparison
        if ($this->has_content_changed($post_id, $post)) {
            // Queue post for processing with priority based on content size
            $priority = $this->get_processing_priority($post);
            $this->queue_content_for_processing($post_id, 'post', $update ? 'update' : 'create', $priority);
        }
    }

    /**
     * Handle post deletion
     * 
     * @param int $post_id Post ID
     */
    public function handle_post_delete(int $post_id): void {
        global $wpdb;

        // Delete related content and embeddings
        $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_wp_content',
            ['post_id' => $post_id],
            ['%d']
        );

        // Delete document and related data
        $document_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ai_botkit_documents
            WHERE source_type = 'post' AND source_id = %d",
            $post_id
        ));

        if ($document_id) {
            $this->rag_engine->delete_document($document_id);
        }
    }

    /**
     * Handle post being moved to trash
     * 
     * @param int $post_id Post ID
     */
    public function handle_post_trash(int $post_id): void {
        $this->update_content_status($post_id, 'trashed');
    }

    /**
     * Handle post being restored from trash
     * 
     * @param int $post_id Post ID
     */
    public function handle_post_untrash(int $post_id): void {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            $this->queue_content_for_processing($post_id, 'post', 'restore');
        }
    }

    /**
     * Handle term updates
     * 
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public function handle_term_update(int $term_id, int $tt_id, string $taxonomy): void {
        // Get posts using this term
        $posts = get_posts([
            'post_type' => $this->post_types,
            'tax_query' => [[
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term_id,
            ]],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Queue affected posts for processing
        foreach ($posts as $post_id) {
            $this->queue_content_for_processing($post_id, 'post', 'term_update');
        }
    }

    /**
     * Handle term deletion
     * 
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public function handle_term_delete(int $term_id, int $tt_id, string $taxonomy): void {
        // Similar to term update, but mark as term_delete
        $posts = get_posts([
            'post_type' => $this->post_types,
            'tax_query' => [[
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term_id,
            ]],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        foreach ($posts as $post_id) {
            $this->queue_content_for_processing($post_id, 'post', 'term_delete');
        }
    }

    /**
     * Process content queue with priority and rate limiting
     */
    public function process_content_queue(): void {
        global $wpdb;

        // Check rate limiting
        if (!$this->check_rate_limits()) {
            return;
        }

        // Get queued items ordered by priority and creation time
        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_wp_content
            WHERE status = 'pending'
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                    ELSE 4 
                END,
                created_at ASC
            LIMIT 5"
        );

        $processed_count = 0;
        foreach ($items as $item) {
            try {
                // Update status to processing
                $this->update_content_status($item->post_id, 'processing');

                // Process content based on type
                if ($item->post_type === 'post') {
                    $this->process_post($item->post_id);
                }

                // Mark as completed
                $this->update_content_status($item->post_id, 'completed');
                $processed_count++;

                // Rate limiting: don't process more than 3 items per batch
                if ($processed_count >= 3) {
                    break;
                }

            } catch (\Exception $e) {
                // Log error and mark as failed
                $this->update_content_status($item->post_id, 'error');
                
                // Increment error count for rate limiting
                $this->increment_error_count();
            }
        }

        // Update rate limiting counters
        $this->update_rate_limit_counters($processed_count);
    }

    /**
     * Process a single post
     * 
     * @param int $post_id Post ID
     */
    private function process_post(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        // Load post content
        $content = $this->document_loader->load_from_post($post_id);

        // Process through RAG engine
        $this->rag_engine->process_document(
            $content,
            'post',
            [
                'source_id' => $post_id,
                'title' => $post->post_title,
                'metadata' => [
                    'post_type' => $post->post_type,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'permalink' => get_permalink($post_id),
                ]
            ]
        );
    }

    /**
     * Check if content has actually changed using hash comparison
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return bool True if content changed, false otherwise
     */
    private function has_content_changed(int $post_id, \WP_Post $post): bool {
        // Get current content hash
        $current_content = $post->post_content . $post->post_title . $post->post_excerpt;
        $current_hash = md5($current_content);
        
        // Get stored hash from post meta
        $stored_hash = get_post_meta($post_id, '_ai_botkit_content_hash', true);
        
        // If no stored hash, content has changed (first time processing)
        if (empty($stored_hash)) {
            update_post_meta($post_id, '_ai_botkit_content_hash', $current_hash);
            return true;
        }
        
        // Compare hashes
        if ($current_hash !== $stored_hash) {
            // Update stored hash
            update_post_meta($post_id, '_ai_botkit_content_hash', $current_hash);
            return true;
        }
        
        return false;
    }

    /**
     * Get processing priority based on content characteristics
     * 
     * @param \WP_Post $post Post object
     * @return string Priority level (high, normal, low)
     */
    private function get_processing_priority(\WP_Post $post): string {
        $content_length = strlen($post->post_content);
        
        // High priority for important content types
        if (in_array($post->post_type, ['sfwd-courses', 'product', 'page'])) {
            return 'high';
        }
        
        // Low priority for very long content (will be processed in background)
        if ($content_length > 5000) {
            return 'low';
        }
        
        // Normal priority for everything else
        return 'normal';
    }

    /**
     * Queue content for processing
     * 
     * @param int $content_id Content ID
     * @param string $type Content type
     * @param string $action Action type
     * @param string $priority Processing priority
     */
    private function queue_content_for_processing(int $content_id, string $type, string $action, string $priority = 'normal'): void {
        global $wpdb;

        $data = [
            'post_id' => $content_id,
            'post_type' => $type,
            'status' => 'pending',
            'action' => $action,
            'priority' => $priority,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Check if already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ai_botkit_wp_content
            WHERE post_id = %d AND post_type = %s",
            $content_id,
            $type
        ));

        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'ai_botkit_wp_content',
                $data,
                ['id' => $existing],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ai_botkit_wp_content',
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        // Schedule processing if not already scheduled
        if (!wp_next_scheduled('ai_botkit_process_content_queue')) {
            wp_schedule_event(time(), '5min', 'ai_botkit_process_content_queue');
        }
    }

    /**
     * Update content processing status
     * 
     * @param int $content_id Content ID
     * @param string $status New status
     */
    private function update_content_status(int $content_id, string $status): void {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ai_botkit_wp_content',
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['post_id' => $content_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Get enabled post types
     * 
     * @return array Enabled post types
     */
    private function get_enabled_post_types(): array {
        $enabled = get_option('ai_botkit_post_types', ['post', 'page']);
        
        if (!is_array($enabled)) {
            $enabled = ['post', 'page'];
        }
        
        return array_filter($enabled, 'post_type_exists');
    }

    /**
     * Check rate limits for processing
     * 
     * @return bool True if processing is allowed, false otherwise
     */
    private function check_rate_limits(): bool {
        $current_time = time();
        $minute_key = 'ai_botkit_rate_limit_minute_' . floor($current_time / 60);
        $hour_key = 'ai_botkit_rate_limit_hour_' . floor($current_time / 3600);
        
        // Check per-minute limit (max 10 operations per minute)
        $minute_count = get_transient($minute_key) ?: 0;
        if ($minute_count >= 10) {
            return false;
        }
        
        // Check per-hour limit (max 100 operations per hour)
        $hour_count = get_transient($hour_key) ?: 0;
        if ($hour_count >= 100) {
            return false;
        }
        
        // Check error rate (max 20% error rate)
        $error_count = get_transient('ai_botkit_error_count_' . floor($current_time / 300)) ?: 0; // 5-minute window
        $total_count = get_transient('ai_botkit_total_count_' . floor($current_time / 300)) ?: 1;
        
        if ($error_count / $total_count > 0.2) {
            return false;
        }
        
        return true;
    }

    /**
     * Update rate limiting counters
     * 
     * @param int $processed_count Number of items processed
     */
    private function update_rate_limit_counters(int $processed_count): void {
        $current_time = time();
        $minute_key = 'ai_botkit_rate_limit_minute_' . floor($current_time / 60);
        $hour_key = 'ai_botkit_rate_limit_hour_' . floor($current_time / 3600);
        $total_key = 'ai_botkit_total_count_' . floor($current_time / 300);
        
        // Update minute counter
        $minute_count = get_transient($minute_key) ?: 0;
        set_transient($minute_key, $minute_count + $processed_count, 60);
        
        // Update hour counter
        $hour_count = get_transient($hour_key) ?: 0;
        set_transient($hour_key, $hour_count + $processed_count, 3600);
        
        // Update total counter
        $total_count = get_transient($total_key) ?: 0;
        set_transient($total_key, $total_count + $processed_count, 300);
    }

    /**
     * Increment error count for rate limiting
     */
    private function increment_error_count(): void {
        $current_time = time();
        $error_key = 'ai_botkit_error_count_' . floor($current_time / 300);
        
        $error_count = get_transient($error_key) ?: 0;
        set_transient($error_key, $error_count + 1, 300);
    }

    /**
     * Get processing statistics
     * 
     * @return array Processing statistics
     */
    public function get_stats(): array {
        global $wpdb;

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
            FROM {$wpdb->prefix}ai_botkit_wp_content
            GROUP BY status",
            ARRAY_A
        );

        $formatted = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'error' => 0,
            'trashed' => 0,
        ];

        foreach ($stats as $stat) {
            $formatted[$stat['status']] = (int) $stat['count'];
        }

        return $formatted;
    }

} 
