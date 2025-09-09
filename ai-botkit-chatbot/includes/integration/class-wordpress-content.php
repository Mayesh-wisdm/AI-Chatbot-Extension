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

        // Queue post for processing
        $this->queue_content_for_processing($post_id, 'post', $update ? 'update' : 'create');
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
     * Process content queue
     */
    public function process_content_queue(): void {
        global $wpdb;

        // Get queued items
        $items = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_wp_content
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 10"
        );

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
            } catch (\Exception $e) {
                // Log error and mark as failed
                error_log('AI BotKit content processing error: ' . $e->getMessage());
                $this->update_content_status($item->post_id, 'error');
            }
        }
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
     * Queue content for processing
     * 
     * @param int $content_id Content ID
     * @param string $type Content type
     * @param string $action Action type
     */
    private function queue_content_for_processing(int $content_id, string $type, string $action): void {
        global $wpdb;

        $data = [
            'post_id' => $content_id,
            'post_type' => $type,
            'status' => 'pending',
            'action' => $action,
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
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ai_botkit_wp_content',
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s']
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