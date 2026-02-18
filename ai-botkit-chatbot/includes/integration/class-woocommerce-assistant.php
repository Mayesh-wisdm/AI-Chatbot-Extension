<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Features\Recommendation_Engine;
use AI_BotKit\Features\Browsing_Tracker;

/**
 * WooCommerce Assistant
 *
 * Handles advanced WooCommerce interactions including shopping cart assistance
 * and product recommendations.
 *
 * Extended in Phase 2 for:
 * - FR-250 to FR-259: LMS/WooCommerce Suggestions
 */
class WooCommerce_Assistant {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Cache manager instance
     */
    private $cache_manager;

    /**
     * Recommendation engine instance
     *
     * @since 2.0.0
     * @var Recommendation_Engine|null
     */
    private $recommendation_engine;

    /**
     * Browsing tracker instance
     *
     * @since 2.0.0
     * @var Browsing_Tracker|null
     */
    private $browsing_tracker;

    /**
     * Initialize the assistant
     */
    public function __construct(RAG_Engine $rag_engine, Unified_Cache_Manager $cache_manager) {
        $this->rag_engine = $rag_engine;
        $this->cache_manager = $cache_manager;

        // Initialize Phase 2 components.
        $this->init_phase2_components();

        $this->init_hooks();
    }

    /**
     * Initialize Phase 2 components.
     *
     * @since 2.0.0
     */
    private function init_phase2_components(): void {
        // Initialize browsing tracker.
        try {
            require_once dirname( __FILE__, 2 ) . '/features/class-browsing-tracker.php';
            if ( class_exists( 'AI_BotKit\Features\Browsing_Tracker' ) ) {
                $this->browsing_tracker = new Browsing_Tracker();
            }
        } catch ( \Exception $e ) {
            $this->browsing_tracker = null;
        }

        // Initialize recommendation engine.
        try {
            require_once dirname( __FILE__, 2 ) . '/features/class-recommendation-engine.php';
            if ( class_exists( 'AI_BotKit\Features\Recommendation_Engine' ) ) {
                $this->recommendation_engine = new Recommendation_Engine(
                    $this->cache_manager,
                    $this->browsing_tracker
                );
            }
        } catch ( \Exception $e ) {
            $this->recommendation_engine = null;
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_filter('ai_botkit_pre_response', [$this, 'enhance_response'], 10, 3);
        add_action('ai_botkit_chat_message', [$this, 'track_product_interaction'], 10, 2);
    }

    /**
     * Enhance chat response with WooCommerce functionality
     */
    public function enhance_response(array $response, string $message, array $context): array {
        if (!$this->is_woocommerce_query($message)) {
            return $response;
        }

        $intent = $this->detect_shopping_intent($message);
        
        switch ($intent) {
            case 'product_info':
                return $this->enhance_with_product_info($response, $message);
            case 'shopping_cart':
                return $this->enhance_with_cart_info($response, $message);
            case 'order_status':
                return $this->enhance_with_order_info($response, $message);
            case 'product_recommendation':
                return $this->enhance_with_recommendations($response, $message);
            default:
                return $response;
        }
    }

    /**
     * Detect shopping intent from message
     */
    private function detect_shopping_intent(string $message): string {
        $message = strtolower($message);

        $patterns = [
            'product_info' => '/price|stock|availability|specs|details|information about/',
            'shopping_cart' => '/cart|basket|add to|remove|checkout/',
            'order_status' => '/order|shipping|delivery|track|status/',
            'product_recommendation' => '/recommend|suggest|similar|alternative|best/'
        ];

        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $message)) {
                return $intent;
            }
        }

        return 'general';
    }

    /**
     * Enhance response with product information
     */
    private function enhance_with_product_info(array $response, string $message): array {
        $products = $this->find_relevant_products($message);
        
        if (empty($products)) {
            return $response;
        }

        $product_info = [];
        foreach ($products as $product) {
            $product_info[] = $this->get_product_details($product);
        }

        $response['content'] .= "\n\nProduct Information:\n";
        foreach ($product_info as $info) {
            $response['content'] .= sprintf(
                "\n- %s: %s (Price: %s, Stock: %s)\n  %s\n",
                $info['name'],
                $info['short_description'],
                $info['price'],
                $info['stock_status'],
                $info['url']
            );
        }

        $response['products'] = $product_info;
        return $response;
    }

    /**
     * Enhance response with shopping cart information
     */
    private function enhance_with_cart_info(array $response, string $message): array {
        if (!is_user_logged_in()) {
            $response['content'] .= "\n\nTo manage your shopping cart, please log in first.";
            return $response;
        }

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty()) {
            $response['content'] .= "\n\nYour shopping cart is currently empty.";
            return $response;
        }

        $response['content'] .= "\n\nShopping Cart:\n";
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $response['content'] .= sprintf(
                "\n- %s (Qty: %d, Price: %s)\n",
                $product->get_name(),
                $cart_item['quantity'],
                wc_price($cart_item['line_total'])
            );
        }

        $response['content'] .= sprintf(
            "\nSubtotal: %s\nTotal: %s",
            wc_price($cart->get_subtotal()),
            wc_price($cart->get_total())
        );

        return $response;
    }

    /**
     * Enhance response with order information
     */
    private function enhance_with_order_info(array $response, string $message): array {
        if (!is_user_logged_in()) {
            $response['content'] .= "\n\nTo check your order status, please log in first.";
            return $response;
        }

        $order_id = $this->extract_order_id($message);
        if (!$order_id) {
            $response['content'] .= "\n\nPlease provide an order number to check its status.";
            return $response;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_customer_id() !== get_current_user_id()) {
            $response['content'] .= "\n\nSorry, I couldn't find that order.";
            return $response;
        }

        $response['content'] .= sprintf(
            "\n\nOrder #%s Status:\nStatus: %s\nDate: %s\nTotal: %s\nShipping Method: %s\n",
            $order->get_order_number(),
            wc_get_order_status_name($order->get_status()),
            $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : '',
            $order->get_formatted_order_total(),
            $order->get_shipping_method()
        );

        return $response;
    }

    /**
     * Enhance response with product recommendations.
     *
     * Uses the Recommendation_Engine (FR-250) for personalized suggestions
     * based on conversation context, browsing history, and purchase history.
     *
     * @since 2.0.0 - Integrated with Recommendation_Engine
     */
    private function enhance_with_recommendations(array $response, string $message): array {
        // Try Phase 2 recommendation engine first (FR-250 to FR-259).
        if ( $this->recommendation_engine ) {
            $user_id = get_current_user_id();
            $context = array(
                'conversation_text' => $message,
                'intent'            => 'product_recommendation',
            );

            $suggestions = $this->recommendation_engine->get_recommendations( $user_id, $context, 5 );

            if ( ! empty( $suggestions ) ) {
                // Add suggestion cards to response (FR-255).
                $response['suggestion_cards'] = $suggestions;

                // Also add text recommendations for backwards compatibility.
                $response['content'] .= "\n\nRecommended Products:\n";
                foreach ( $suggestions as $card ) {
                    if ( 'product' === $card['type'] ) {
                        $response['content'] .= sprintf(
                            "\n- %s: %s\n  Price: %s\n  %s\n",
                            $card['title'],
                            $card['description'],
                            $card['price'],
                            $card['url']
                        );
                    }
                }

                $response['recommendations'] = array_map(
                    function( $card ) {
                        return array(
                            'id'                => $card['id'],
                            'name'              => $card['title'],
                            'price'             => $card['price'],
                            'short_description' => $card['description'],
                            'url'               => $card['url'],
                            'image_url'         => $card['image'],
                            'rating'            => $card['rating'] ?? 0,
                            'review_count'      => $card['review_count'] ?? 0,
                        );
                    },
                    array_filter( $suggestions, function( $s ) { return 'product' === $s['type']; } )
                );

                return $response;
            }
        }

        // Fallback to original recommendation logic.
        $recommendations = $this->get_product_recommendations($message);

        if (empty($recommendations)) {
            return $response;
        }

        $response['content'] .= "\n\nRecommended Products:\n";
        foreach ($recommendations as $product) {
            $response['content'] .= sprintf(
                "\n- %s: %s\n  Price: %s | Rating: %s/5 (%d reviews)\n  %s\n",
                $product['name'],
                $product['short_description'],
                $product['price'],
                $product['rating'],
                $product['review_count'],
                $product['url']
            );
        }

        $response['recommendations'] = $recommendations;
        return $response;
    }

    /**
     * Find relevant products based on message
     */
    private function find_relevant_products(string $message): array {
        $cache_key = 'product_search_' . md5($message);
        
        return $this->cache_manager->remember($cache_key, function() use ($message) {
            $search_terms = $this->extract_search_terms($message);
            
            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                's' => implode(' ', $search_terms),
                'orderby' => 'relevance',
            ];

            $query = new \WP_Query($args);
            $products = [];

            foreach ($query->posts as $post) {
                $product = wc_get_product($post);
                if ($product) {
                    $products[] = $product;
                }
            }

            return $products;
        }, 3600);
    }

    /**
     * Get product recommendations based on message and user history
     */
    private function get_product_recommendations(string $message): array {
        $cache_key = 'product_recommendations_' . md5($message . get_current_user_id());
        
        return $this->cache_manager->remember($cache_key, function() use ($message) {
            $recommendations = [];
            
            // Get products based on user's browsing history
            $viewed_products = $this->get_recently_viewed_products();
            
            // Get products based on message context
            $context_products = $this->find_relevant_products($message);
            
            // Get related products
            $related_products = [];
            foreach ($context_products as $product) {
                $related = wc_get_related_products($product->get_id(), 3);
                foreach ($related as $related_id) {
                    $related_product = wc_get_product($related_id);
                    if ($related_product) {
                        $related_products[] = $related_product;
                    }
                }
            }
            
            // Combine and sort recommendations
            $all_products = array_merge($context_products, $viewed_products, $related_products);
            $all_products = array_unique($all_products, SORT_REGULAR);
            
            // Sort by relevance score
            usort($all_products, function($a, $b) use ($message) {
                return $this->calculate_relevance_score($b, $message) 
                    <=> $this->calculate_relevance_score($a, $message);
            });
            
            // Format recommendations
            foreach (array_slice($all_products, 0, 5) as $product) {
                $recommendations[] = $this->get_product_details($product);
            }
            
            return $recommendations;
        }, 3600);
    }

    /**
     * Get product details in consistent format
     */
    private function get_product_details(\WC_Product $product): array {
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price_html(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'short_description' => $product->get_short_description(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
            'tags' => wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']),
            'rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
            'url' => get_permalink($product->get_id()),
            'image_url' => wp_get_attachment_url($product->get_image_id()),
        ];
    }

    /**
     * Calculate relevance score for product
     */
    private function calculate_relevance_score(\WC_Product $product, string $message): float {
        $score = 0;
        $message = strtolower($message);
        
        // Match product name and description
        $name_match = similar_text($message, strtolower($product->get_name()), $percent);
        $score += $percent / 100;
        
        // Match categories and tags
        $terms = array_merge(
            wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
            wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names'])
        );
        foreach ($terms as $term) {
            if (stripos($message, strtolower($term)) !== false) {
                $score += 0.5;
            }
        }
        
        // Consider rating and review count
        $score += min(($product->get_average_rating() / 5) * 0.3, 0.3);
        $score += min(($product->get_review_count() / 100) * 0.2, 0.2);
        
        return $score;
    }

    /**
     * Get recently viewed products
     */
    private function get_recently_viewed_products(): array {
        $session = WC()->session;
        if ( ! $session ) {
            return array();
        }
        $viewed_products = array_filter(array_map('wc_get_product',
            $session->get('recently_viewed', [])
        ));

        return array_slice($viewed_products, 0, 5);
    }

    /**
     * Extract search terms from message
     */
    private function extract_search_terms(string $message): array {
        // Remove common words and shopping-related terms
        $stop_words = ['price', 'cost', 'how', 'much', 'where', 'can', 'i', 'find', 'buy', 'purchase'];
        $words = array_filter(
            explode(' ', strtolower($message)),
            function($word) use ($stop_words) {
                return !in_array($word, $stop_words) && strlen($word) > 2;
            }
        );
        
        return array_values($words);
    }

    /**
     * Extract order ID from message
     */
    private function extract_order_id(string $message): ?int {
        if (preg_match('/(?:order|#)[\s#]*(\d+)/i', $message, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Check if message is WooCommerce related
     */
    private function is_woocommerce_query(string $message): bool {
        $patterns = [
            '/product|price|stock|cart|order|shipping|delivery|purchase|buy|shop/',
            '/recommend|suggest|similar|alternative|best/',
            '/discount|sale|offer|deal|coupon/',
            '/refund|return|exchange|warranty/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtolower($message))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Track product interaction for analytics
     */
    public function track_product_interaction(string $message, array $response): void {
        if (!isset($response['products']) && !isset($response['recommendations'])) {
            return;
        }

        $products = array_merge(
            $response['products'] ?? [],
            $response['recommendations'] ?? []
        );

        foreach ($products as $product) {
            update_post_meta(
                $product['id'],
                '_ai_botkit_interaction_count',
                (int)get_post_meta($product['id'], '_ai_botkit_interaction_count', true) + 1
            );

            // Track interaction in recommendation engine (FR-252).
            if ( $this->recommendation_engine ) {
                $this->recommendation_engine->track_interaction(
                    get_current_user_id(),
                    'recommendation_shown',
                    'product',
                    $product['id'],
                    array( 'message' => $message )
                );
            }
        }
    }

    // =========================================================
    // Phase 2: Recommendation Engine Integration (FR-250 to FR-259)
    // =========================================================

    /**
     * Get personalized recommendations using the Recommendation Engine.
     *
     * Implements: FR-250 (Recommendation Engine Core)
     *
     * @since 2.0.0
     *
     * @param array $context {
     *     Context for generating recommendations.
     *
     *     @type string $conversation_text Recent conversation content.
     *     @type int    $chatbot_id        Current chatbot ID.
     * }
     * @param int   $limit Maximum recommendations to return.
     * @return array Formatted suggestion cards.
     */
    public function get_personalized_recommendations( array $context = array(), int $limit = 5 ): array {
        if ( ! $this->recommendation_engine ) {
            return array();
        }

        return $this->recommendation_engine->get_recommendations(
            get_current_user_id(),
            $context,
            $limit
        );
    }

    /**
     * Track a browsing interaction.
     *
     * Implements: FR-252 (Browsing History Tracking)
     *
     * @since 2.0.0
     *
     * @param string $item_type Item type (product, course).
     * @param int    $item_id   Item ID.
     * @param array  $metadata  Additional metadata.
     * @return bool Success.
     */
    public function track_browse( string $item_type, int $item_id, array $metadata = array() ): bool {
        if ( $this->browsing_tracker ) {
            return $this->browsing_tracker->track_page_view( $item_type, $item_id, $metadata );
        }

        return false;
    }

    /**
     * Get the recommendation engine instance.
     *
     * @since 2.0.0
     *
     * @return Recommendation_Engine|null
     */
    public function get_recommendation_engine(): ?Recommendation_Engine {
        return $this->recommendation_engine;
    }

    /**
     * Get the browsing tracker instance.
     *
     * @since 2.0.0
     *
     * @return Browsing_Tracker|null
     */
    public function get_browsing_tracker(): ?Browsing_Tracker {
        return $this->browsing_tracker;
    }
} 