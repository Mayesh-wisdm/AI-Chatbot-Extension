<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\Document_Loader;
use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Utils\Table_Helper;

/**
 * WooCommerce Integration
 * 
 * Handles integration with WooCommerce products, including product data,
 * variations, and attributes.
 */
class WooCommerce {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Document Loader instance
     */
    private $document_loader;

    /**
     * Integration status
     */
    private $is_active = false;

    /**
     * WooCommerce Assistant instance
     */
    private $wc_assistant;

    /**
     * Initialize the integration
     * 
     * @param RAG_Engine $rag_engine RAG Engine instance
     * @param Document_Loader $document_loader Document Loader instance
     * @param Unified_Cache_Manager $cache_manager Cache Manager instance
     */
    public function __construct(RAG_Engine $rag_engine, Document_Loader $document_loader, Unified_Cache_Manager $cache_manager) {
        $this->rag_engine = $rag_engine;
        $this->document_loader = $document_loader;
        
        // Check if WooCommerce is active
        if ($this->check_woocommerce()) {
            $this->is_active = true;
            $this->wc_assistant = new WooCommerce_Assistant($rag_engine, $cache_manager);
            $this->init_hooks();
        }
    }

    /**
     * Check if WooCommerce is active
     * 
     * @return bool Whether WooCommerce is active
     */
    private function check_woocommerce(): bool {
        return class_exists('WooCommerce');
    }

    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks(): void {
        // Product content hooks
        add_action('woocommerce_update_product', [$this, 'handle_product_update']);
        add_action('woocommerce_delete_product', [$this, 'handle_product_delete']);
        add_action('woocommerce_trash_product', [$this, 'handle_product_trash']);
        add_action('woocommerce_untrash_product', [$this, 'handle_product_untrash']);
        
        // Variation hooks
        add_action('woocommerce_save_product_variation', [$this, 'handle_variation_update']);
        add_action('woocommerce_delete_product_variation', [$this, 'handle_variation_delete']);
        
        // Category and attribute hooks
        add_action('created_product_cat', [$this, 'handle_category_update']);
        add_action('edited_product_cat', [$this, 'handle_category_update']);
        add_action('delete_product_cat', [$this, 'handle_category_delete']);
        add_action('woocommerce_attribute_added', [$this, 'handle_attribute_update']);
        add_action('woocommerce_attribute_updated', [$this, 'handle_attribute_update']);
        add_action('woocommerce_attribute_deleted', [$this, 'handle_attribute_delete']);

        // Chat enhancement hooks
        add_filter('ai_botkit_pre_response', [$this->wc_assistant, 'enhance_response'], 10, 3);
        add_action('ai_botkit_chat_message', [$this->wc_assistant, 'track_product_interaction'], 10, 2);
    }

    /**
     * Handle product update
     * 
     * @param int $product_id Product ID
     */
    public function handle_product_update(int $product_id): void {
        $product = wc_get_product($product_id);
        if ($product->get_status() !== 'publish') {
            return;
        }

        $this->queue_product_for_processing($product_id, 'update');
    }

    /**
     * Handle product deletion
     * 
     * @param int $product_id Product ID
     */
    public function handle_product_delete(int $product_id): void {
        global $wpdb;

        // Delete related content and embeddings
        $table_name = Table_Helper::get_table_name('wp_content');
        $wpdb->delete(
            $table_name,
            [
                'post_id' => $product_id,
                'post_type' => 'product'
            ],
            ['%d', '%s']
        );

        // Delete document and related data
        $document_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ai_botkit_documents
            WHERE source_type = 'product' AND source_id = %d",
            $product_id
        ));

        if ($document_id) {
            $this->rag_engine->delete_document($document_id);
        }
    }

    /**
     * Handle product being moved to trash
     * 
     * @param int $product_id Product ID
     */
    public function handle_product_trash(int $product_id): void {
        $this->update_content_status($product_id, 'trashed');
    }

    /**
     * Handle product being restored from trash
     * 
     * @param int $product_id Product ID
     */
    public function handle_product_untrash(int $product_id): void {
        $product = wc_get_product($product_id);
        if ($product && $product->get_status() === 'publish') {
            $this->queue_product_for_processing($product_id, 'restore');
        }
    }

    /**
     * Handle variation update
     * 
     * @param int $variation_id Variation ID
     */
    public function handle_variation_update(int $variation_id): void {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        $product_id = $variation->get_parent_id();
        if ($product_id) {
            $this->queue_product_for_processing($product_id, 'variation_update');
        }
    }

    /**
     * Handle variation deletion
     * 
     * @param int $variation_id Variation ID
     */
    public function handle_variation_delete(int $variation_id): void {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }

        $product_id = $variation->get_parent_id();
        if ($product_id) {
            $this->queue_product_for_processing($product_id, 'variation_delete');
        }
    }

    /**
     * Handle category update
     * 
     * @param int $term_id Term ID
     */
    public function handle_category_update(int $term_id): void {
        // Get products in this category
        $products = wc_get_products([
            'category' => [$term_id],
            'return' => 'ids',
            'limit' => -1,
        ]);

        foreach ($products as $product_id) {
            $this->queue_product_for_processing($product_id, 'category_update');
        }
    }

    /**
     * Handle category deletion
     * 
     * @param int $term_id Term ID
     */
    public function handle_category_delete(int $term_id): void {
        // Similar to category update
        $products = wc_get_products([
            'category' => [$term_id],
            'return' => 'ids',
            'limit' => -1,
        ]);

        foreach ($products as $product_id) {
            $this->queue_product_for_processing($product_id, 'category_delete');
        }
    }

    /**
     * Handle attribute update
     * 
     * @param int $attribute_id Attribute ID
     */
    public function handle_attribute_update(int $attribute_id): void {
        // Get products with this attribute
        $products = wc_get_products([
            'limit' => -1,
            'return' => 'ids',
            'attributes' => [
                [
                    'attribute' => $attribute_id,
                    'operator' => 'EXISTS',
                ]
            ]
        ]);

        foreach ($products as $product_id) {
            $this->queue_product_for_processing($product_id, 'attribute_update');
        }
    }

    /**
     * Handle attribute deletion
     * 
     * @param int $attribute_id Attribute ID
     */
    public function handle_attribute_delete(int $attribute_id): void {
        // Similar to attribute update
        $products = wc_get_products([
            'limit' => -1,
            'return' => 'ids',
            'attributes' => [
                [
                    'attribute' => $attribute_id,
                    'operator' => 'EXISTS',
                ]
            ]
        ]);

        foreach ($products as $product_id) {
            $this->queue_product_for_processing($product_id, 'attribute_delete');
        }
    }

    /**
     * Process a product
     * 
     * @param int $product_id Product ID
     */
    private function process_product(int $product_id): void {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return;
        }

        // Build product content
        $content = $this->build_product_content($product);

        // Process through RAG engine
        $this->rag_engine->process_document(
            $content,
            'product',
            [
                'source_id' => $product_id,
                'title' => $product->get_name(),
                'metadata' => [
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'categories' => $product->get_category_ids(),
                    'type' => $product->get_type(),
                    'permalink' => $product->get_permalink(),
                ]
            ]
        );
    }

    /**
     * Build product content
     * 
     * @param \WC_Product $product Product object
     * @return string Product content
     */
    private function build_product_content(\WC_Product $product): string {
        $content = [];

        // Basic product information
        $content[] = "Product Name: " . $product->get_name();
        $content[] = "SKU: " . $product->get_sku();
        $content[] = "Price: " . $product->get_price();
        
        // Description
        if ($product->get_description()) {
            $content[] = "Description: " . $product->get_description();
        }
        if ($product->get_short_description()) {
            $content[] = "Short Description: " . $product->get_short_description();
        }

        // Categories
        $categories = wc_get_product_category_list($product->get_id());
        if ($categories) {
            $content[] = "Categories: " . wp_strip_all_tags($categories);
        }

        // Attributes
        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $content[] = "Attributes:";
            foreach ($attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                    if (!is_wp_error($terms)) {
                        $term_names = wp_list_pluck($terms, 'name');
                        $content[] = "- " . wc_attribute_label($attribute->get_name()) . ": " . implode(', ', $term_names);
                    }
                } else {
                    $content[] = "- " . $attribute->get_name() . ": " . implode(', ', $attribute->get_options());
                }
            }
        }

        // Variations if variable product
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            if (!empty($variations)) {
                $content[] = "Variations:";
                foreach ($variations as $variation) {
                    $variation_product = wc_get_product($variation['variation_id']);
                    $attributes_text = [];
                    foreach ($variation['attributes'] as $key => $value) {
                        $taxonomy = str_replace('attribute_', '', $key);
                        $term = get_term_by('slug', $value, $taxonomy);
                        $attributes_text[] = $term ? $term->name : $value;
                    }
                    $content[] = "- " . implode(' / ', $attributes_text) . " - " . $variation_product->get_price();
                }
            }
        }

        return implode("\n\n", $content);
    }

    /**
     * Queue product for processing
     * 
     * @param int $product_id Product ID
     * @param string $action Action type
     */
    private function queue_product_for_processing(int $product_id, string $action): void {
        global $wpdb;

        $data = [
            'post_id' => $product_id,
            'post_type' => 'product',
            'status' => 'pending',
            'action' => $action,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Check if already exists
        $table_name = Table_Helper::get_table_name('wp_content');
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name}
            WHERE post_id = %d AND post_type = %s",
            $product_id,
            'product'
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                $data,
                ['id' => $existing],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table_name,
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
     * @param int $product_id Product ID
     * @param string $status New status
     */
    private function update_content_status(int $product_id, string $status): void {
        global $wpdb;

        $table_name = Table_Helper::get_table_name('wp_content');
        $wpdb->update(
            $table_name,
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            [
                'post_id' => $product_id,
                'post_type' => 'product'
            ],
            ['%s', '%s'],
            ['%d', '%s']
        );
    }

    /**
     * Get processing statistics
     * 
     * @return array Processing statistics
     */
    public function get_stats(): array {
        if (!$this->is_active) {
            return [
                'status' => 'inactive',
                'message' => 'WooCommerce is not active'
            ];
        }

        global $wpdb;

        $table_name = Table_Helper::get_table_name('wp_content');
        $stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                FROM {$table_name}
                WHERE post_type = %s
                GROUP BY status",
                'product'
            ),
            ARRAY_A
        );

        $formatted = [
            'status' => 'active',
            'counts' => [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'error' => 0,
                'trashed' => 0,
            ]
        ];

        foreach ($stats as $stat) {
            $formatted['counts'][$stat['status']] = (int) $stat['count'];
        }

        return $formatted;
    }
} 