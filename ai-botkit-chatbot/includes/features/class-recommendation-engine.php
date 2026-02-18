<?php
/**
 * Recommendation Engine
 *
 * Generates personalized product and course recommendations based on
 * multiple signals: conversation context, browsing history, purchase/enrollment
 * history, and explicit requests.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-250 (Recommendation Engine Core)
 *             FR-251 (Conversation Context Analysis)
 *             FR-253 (Purchase/Enrollment History Integration)
 *             FR-254 (Explicit Recommendation Requests)
 *             FR-255 (Suggestion UI Cards)
 *             FR-258 (LearnDash Course Suggestions)
 *             FR-259 (WooCommerce Product Suggestions)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Utils\Table_Helper;

/**
 * Recommendation_Engine class.
 *
 * Core recommendation engine that combines multiple signals to generate
 * personalized product and course recommendations.
 *
 * Signal Weights (configurable via ai_botkit_recommendation_signals filter):
 * - Conversation Context: 40%
 * - Browsing History: 30%
 * - Purchase/Enrollment History: 20%
 * - Explicit Request: 10% bonus
 *
 * @since 2.0.0
 */
class Recommendation_Engine {

	/**
	 * Default signal weights.
	 *
	 * @var array
	 */
	private const DEFAULT_WEIGHTS = array(
		'conversation' => 0.40,
		'browsing'     => 0.30,
		'history'      => 0.20,
		'explicit'     => 0.10,
	);

	/**
	 * Cache manager instance.
	 *
	 * @var Unified_Cache_Manager|null
	 */
	private $cache_manager;

	/**
	 * Browsing tracker instance.
	 *
	 * @var Browsing_Tracker|null
	 */
	private $browsing_tracker;

	/**
	 * Maximum recommendations to return.
	 *
	 * @var int
	 */
	private int $max_recommendations = 2;

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private int $cache_ttl = 300;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $table_prefix;

	/**
	 * Recommendation intent patterns.
	 *
	 * @var array
	 */
	private const INTENT_PATTERNS = array(
		'recommend',
		'suggest',
		'what should i',
		'help me find',
		'looking for',
		'best',
		'show me',
		'can you recommend',
		'any recommendations',
		'top',
		'popular',
	);

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Unified_Cache_Manager|null $cache_manager    Optional cache manager.
	 * @param Browsing_Tracker|null      $browsing_tracker Optional browsing tracker.
	 */
	public function __construct( $cache_manager = null, $browsing_tracker = null ) {
		global $wpdb;

		$this->table_prefix     = Table_Helper::get_table_prefix();
		$this->cache_manager    = $cache_manager;
		$this->browsing_tracker = $browsing_tracker;

		// Set max recommendations from option.
		$this->max_recommendations = (int) get_option( 'ai_botkit_recommendation_limit', 2 );
	}

	/**
	 * Get recommendations for a user based on multiple signals.
	 *
	 * Main entry point for the recommendation engine. Combines all signals
	 * with configurable weights to generate scored recommendations.
	 *
	 * Implements: FR-250 (Recommendation Engine Core)
	 *
	 * @since 2.0.0
	 *
	 * @param int   $user_id User ID (0 for guest).
	 * @param array $context {
	 *     Context for generating recommendations.
	 *
	 *     @type string $conversation_text Recent conversation content.
	 *     @type int    $chatbot_id        Current chatbot ID.
	 *     @type string $intent            Detected intent (optional).
	 *     @type int    $conversation_id   Current conversation ID.
	 * }
	 * @param int   $limit Maximum recommendations to return.
	 * @return array List of recommendation objects formatted for UI.
	 */
	public function get_recommendations( int $user_id, array $context = array(), int $limit = 0 ): array {
		error_log( '[Recommendation] === START get_recommendations ===' );
		error_log( '[Recommendation] User ID: ' . $user_id . ', Limit: ' . $limit );
		error_log( '[Recommendation] Context: ' . wp_json_encode( $context ) );

		// Use default limit if not specified.
		if ( $limit <= 0 ) {
			$limit = $this->max_recommendations;
		}

		// Check if recommendations are enabled.
		if ( ! get_option( 'ai_botkit_recommendation_enabled', true ) ) {
			error_log( '[Recommendation] DISABLED - recommendations turned off in settings' );
			return array();
		}

		// Try to get from cache.
		$cache_key = $this->get_cache_key( $user_id, $context );
		if ( $this->cache_manager ) {
			$cached = $this->cache_manager->get( $cache_key, 'recommendations' );
			if ( false !== $cached ) {
				error_log( '[Recommendation] Returning ' . count( $cached ) . ' cached recommendations' );
				return array_slice( $cached, 0, $limit );
			}
		}

		// Get signal weights.
		$weights = $this->get_signal_weights();
		error_log( '[Recommendation] Weights: ' . wp_json_encode( $weights ) );

		// Gather signals.
		$signals = array();

		// Signal 1: Conversation Context (40%).
		$conversation_text = $context['conversation_text'] ?? '';

		// Extract ONLY the user's message (before bot responds).
		// Bot responses like "Of course!" can contaminate type detection.
		$user_message_only       = $this->extract_user_message( $conversation_text );
		error_log( '[Recommendation] User message extracted: ' . $user_message_only );
		$signals['conversation'] = $this->analyze_conversation_context( $user_message_only );
		error_log( '[Recommendation] Conversation keywords: ' . wp_json_encode( $signals['conversation']['keywords'] ) );

		// Signal 2: Browsing History (30%).
		$session_id          = $context['session_id'] ?? $this->get_session_id();
		$signals['browsing'] = $this->get_browsing_history( $user_id, $session_id );
		error_log( '[Recommendation] Browsing history: ' . wp_json_encode( $signals['browsing'] ) );

		// Signal 3: Purchase/Enrollment History (20%).
		$signals['history'] = $this->get_purchase_enrollment_history( $user_id );
		error_log( '[Recommendation] Purchase/enrollment history: ' . wp_json_encode( $signals['history'] ) );

		// Signal 4: Explicit Request Detection (10% bonus).
		$signals['explicit'] = $this->detect_explicit_request( $user_message_only );
		error_log( '[Recommendation] Explicit request: is_explicit=' . ( $signals['explicit']['is_explicit'] ? 'YES' : 'NO' ) . ', type=' . $signals['explicit']['type'] . ', confidence=' . $signals['explicit']['confidence'] );

		// CRITICAL: Only return recommendations if explicitly requested
		// This prevents recommendations from showing on EVERY message
		if ( ! $signals['explicit']['is_explicit'] || $signals['explicit']['confidence'] < 0.5 ) {
			error_log( '[Recommendation] NO EXPLICIT REQUEST - returning empty (is_explicit=' . ( $signals['explicit']['is_explicit'] ? 'true' : 'false' ) . ', confidence=' . $signals['explicit']['confidence'] . ')' );
			return array();
		}

		// Calculate scores and get recommendations.
		$recommendations = $this->calculate_scores( $signals, $weights, $user_id );
		error_log( '[Recommendation] After calculate_scores: ' . count( $recommendations ) . ' items' );

		// Mark already purchased/enrolled items (but don't filter them out)
		$recommendations = $this->mark_owned_items( $recommendations, $user_id );
		error_log( '[Recommendation] After mark_owned_items: ' . count( $recommendations ) . ' items' );

		// Limit results.
		$recommendations = array_slice( $recommendations, 0, $limit );
		error_log( '[Recommendation] After slice to limit ' . $limit . ': ' . count( $recommendations ) . ' items' );

		// Format for UI.
		$formatted = $this->format_suggestion_cards( $recommendations );
		error_log( '[Recommendation] After format_suggestion_cards: ' . count( $formatted ) . ' items' );
		error_log( '[Recommendation] === END get_recommendations - returning ' . count( $formatted ) . ' recommendations ===' );

		// Cache results.
		if ( $this->cache_manager && ! empty( $formatted ) ) {
			$this->cache_manager->set( $cache_key, $formatted, 'recommendations', $this->cache_ttl );
		}

		/**
		 * Filter recommendations before returning.
		 *
		 * @since 2.0.0
		 *
		 * @param array $formatted      Formatted recommendations.
		 * @param int   $user_id        User ID.
		 * @param array $context        Original context.
		 * @param array $signals        Raw signals data.
		 */
		return apply_filters( 'ai_botkit_recommendations', $formatted, $user_id, $context, $signals );
	}

	/**
	 * Analyze conversation context for recommendation signals.
	 *
	 * Extracts keywords, categories, and intent from conversation text
	 * to match against products and courses.
	 *
	 * Implements: FR-251 (Conversation Context Analysis)
	 *
	 * @since 2.0.0
	 *
	 * @param string $conversation_text Recent conversation content.
	 * @return array {
	 *     Analysis results.
	 *
	 *     @type array  $keywords     Extracted keywords.
	 *     @type array  $categories   Detected categories.
	 *     @type string $intent       Primary intent.
	 *     @type float  $confidence   Intent confidence score.
	 *     @type array  $item_ids     Mentioned product/course IDs.
	 * }
	 */
	public function analyze_conversation_context( string $conversation_text ): array {
		if ( empty( $conversation_text ) ) {
			return array(
				'keywords'   => array(),
				'categories' => array(),
				'intent'     => 'general',
				'confidence' => 0.0,
				'item_ids'   => array(),
			);
		}

		$text_lower = strtolower( $conversation_text );

		// Extract keywords.
		$keywords = $this->extract_keywords( $text_lower );

		// Detect categories mentioned.
		$categories = $this->detect_categories( $text_lower );

		// Detect item IDs mentioned in conversation.
		$item_ids = $this->extract_item_mentions( $conversation_text );

		// Detect intent and type.
		$intent     = $this->detect_intent_type( $text_lower );
		$confidence = $this->calculate_intent_confidence( $text_lower, $intent );

		error_log( '[Recommendation] Detected intent: ' . $intent . ' with confidence: ' . $confidence );
		error_log( '[Recommendation] Keywords: ' . wp_json_encode( $keywords ) );

		return array(
			'keywords'   => $keywords,
			'categories' => $categories,
			'intent'     => $intent,
			'confidence' => $confidence,
			'item_ids'   => $item_ids,
		);
	}

	/**
	 * Get browsing history for recommendation signals.
	 *
	 * Retrieves session-based page view data for products and courses.
	 *
	 * Implements: FR-252 (Browsing History Tracking)
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id    User ID.
	 * @param string $session_id Session ID.
	 * @return array {
	 *     Browsing history data.
	 *
	 *     @type array $product_ids Viewed product IDs.
	 *     @type array $course_ids  Viewed course IDs.
	 *     @type array $categories  Categories from viewed items.
	 *     @type int   $view_count  Total views.
	 * }
	 */
	public function get_browsing_history( int $user_id, string $session_id ): array {
		// Try browsing tracker first.
		if ( $this->browsing_tracker ) {
			return $this->browsing_tracker->get_session_history( $user_id, $session_id );
		}

		// Fallback to direct database query.
		global $wpdb;

		$table = $this->table_prefix . 'user_interactions';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return array(
				'product_ids' => array(),
				'course_ids'  => array(),
				'categories'  => array(),
				'view_count'  => 0,
			);
		}

		$where_clause = $user_id > 0
			? $wpdb->prepare( 'user_id = %d', $user_id )
			: $wpdb->prepare( 'session_id = %s', $session_id );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT item_type, item_id, COUNT(*) as view_count
             FROM {$table}
             WHERE {$where_clause}
             AND interaction_type IN ('page_view', 'product_view', 'course_view')
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY item_type, item_id
             ORDER BY view_count DESC, MAX(created_at) DESC
             LIMIT 50"
		);

		$product_ids = array();
		$course_ids  = array();
		$categories  = array();
		$view_count  = 0;

		foreach ( $results as $row ) {
			$view_count += $row->view_count;

			if ( 'product' === $row->item_type ) {
				$product_ids[] = (int) $row->item_id;
				// Get product categories.
				$terms = wp_get_post_terms( $row->item_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) ) {
					$categories = array_merge( $categories, $terms );
				}
			} elseif ( 'course' === $row->item_type ) {
				$course_ids[] = (int) $row->item_id;
				// Get course categories.
				$terms = wp_get_post_terms( $row->item_id, 'ld_course_category', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) ) {
					$categories = array_merge( $categories, $terms );
				}
			}
		}

		return array(
			'product_ids' => array_unique( $product_ids ),
			'course_ids'  => array_unique( $course_ids ),
			'categories'  => array_unique( $categories ),
			'view_count'  => $view_count,
		);
	}

	/**
	 * Get purchase and enrollment history.
	 *
	 * Queries WooCommerce orders and LearnDash enrollments to identify
	 * user preferences and suggest complementary items.
	 *
	 * Implements: FR-253 (Purchase/Enrollment History Integration)
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array {
	 *     Purchase and enrollment history.
	 *
	 *     @type array $purchased_ids    Purchased product IDs.
	 *     @type array $enrolled_ids     Enrolled course IDs.
	 *     @type array $categories       Categories from owned items.
	 *     @type array $price_range      Typical price range.
	 *     @type array $complementary    Complementary item IDs.
	 * }
	 */
	public function get_purchase_enrollment_history( int $user_id ): array {
		$result = array(
			'purchased_ids' => array(),
			'enrolled_ids'  => array(),
			'categories'    => array(),
			'price_range'   => array(
				'min' => 0,
				'max' => 0,
			),
			'complementary' => array(),
		);

		if ( $user_id <= 0 ) {
			return $result;
		}

		// Get WooCommerce purchase history.
		if ( $this->is_woocommerce_active() ) {
			$result = array_merge( $result, $this->get_woocommerce_history( $user_id ) );
		}

		// Get LearnDash enrollment history.
		if ( $this->is_learndash_active() ) {
			$result = array_merge( $result, $this->get_learndash_history( $user_id ) );
		}

		return $result;
	}

	/**
	 * Detect explicit recommendation requests.
	 *
	 * Identifies when users explicitly ask for recommendations and
	 * extracts any specific criteria mentioned.
	 *
	 * Implements: FR-254 (Explicit Recommendation Requests)
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Conversation text.
	 * @return array {
	 *     Explicit request data.
	 *
	 *     @type bool   $is_explicit Whether explicit request was detected.
	 *     @type string $type        Type requested (product/course/any).
	 *     @type array  $criteria    Extracted criteria.
	 *     @type float  $confidence  Detection confidence.
	 * }
	 */
	public function detect_explicit_request( string $text ): array {
		$text_lower = strtolower( $text );

		$result = array(
			'is_explicit' => false,
			'type'        => 'any',
			'criteria'    => array(),
			'confidence'  => 0.0,
		);

		// Check for intent patterns.
		$matches = 0;
		foreach ( self::INTENT_PATTERNS as $pattern ) {
			if ( strpos( $text_lower, $pattern ) !== false ) {
				++$matches;
				$result['is_explicit'] = true;
			}
		}

		if ( ! $result['is_explicit'] ) {
			return $result;
		}

		// Calculate confidence based on matches.
		$result['confidence'] = min( $matches * 0.25, 1.0 );

		// Detect type requested - check for course keywords first
		if ( preg_match( '/course|class|lesson|learn|training|tutorial|education|curriculum|module/i', $text_lower ) ) {
			$result['type'] = 'course';
		} elseif ( preg_match( '/product|item|buy|purchase|shop|order|shirt|tshirt|t-shirt|hoodie|hat|cap|shoe|shoes|bag|watch|laptop|phone|tablet|accessory|accessories|gadget|goods/i', $text_lower ) ) {
			$result['type'] = 'product';
		} else {
			// Fallback based on what's active
			if ( $this->is_woocommerce_active() && ! $this->is_learndash_active() ) {
				$result['type'] = 'product';
			} elseif ( $this->is_learndash_active() && ! $this->is_woocommerce_active() ) {
				$result['type'] = 'course';
			}
		}

		error_log( '[Recommendation] Explicit request detected type: ' . $result['type'] );

		// Extract criteria.
		$result['criteria'] = $this->extract_criteria( $text_lower );

		return $result;
	}

	/**
	 * Calculate scores and rank recommendations.
	 *
	 * Combines all signals with weights to produce scored recommendations.
	 *
	 * @since 2.0.0
	 *
	 * @param array $signals Signal data from all sources.
	 * @param array $weights Signal weights.
	 * @param int   $user_id User ID.
	 * @return array Scored and sorted recommendation items.
	 */
	public function calculate_scores( array $signals, array $weights, int $user_id ): array {
		error_log( '[Recommendation] === calculate_scores START ===' );
		$scored_items = array();

		// Get candidate items from conversation context.
		if ( ! empty( $signals['conversation']['keywords'] ) || ! empty( $signals['conversation']['categories'] ) ) {
			error_log( '[Recommendation] Getting items from conversation context...' );
			$context_items = $this->get_items_from_context( $signals['conversation'] );
			error_log( '[Recommendation] Found ' . count( $context_items ) . ' items from context' );
			foreach ( $context_items as $item ) {
				$key = $item['type'] . '_' . $item['id'];
				if ( ! isset( $scored_items[ $key ] ) ) {
					$scored_items[ $key ]           = $item;
					$scored_items[ $key ]['score']  = 0;
					$scored_items[ $key ]['source'] = 'conversation';
				}
				$scored_items[ $key ]['score'] += $item['relevance'] * $weights['conversation'];
				error_log( '[Recommendation] Added/updated ' . $item['type'] . ' #' . $item['id'] . ' with score ' . $scored_items[ $key ]['score'] );
			}
		} else {
			error_log( '[Recommendation] No keywords or categories in conversation signal - skipping context items' );
		}

		// Get candidate items from browsing history.
		if ( ! empty( $signals['browsing']['product_ids'] ) || ! empty( $signals['browsing']['course_ids'] ) ) {
			$browsing_items = $this->get_items_from_browsing( $signals['browsing'] );
			foreach ( $browsing_items as $item ) {
				$key = $item['type'] . '_' . $item['id'];
				if ( ! isset( $scored_items[ $key ] ) ) {
					$scored_items[ $key ]           = $item;
					$scored_items[ $key ]['score']  = 0;
					$scored_items[ $key ]['source'] = 'browsing';
				}
				$scored_items[ $key ]['score'] += $item['relevance'] * $weights['browsing'];
			}
		}

		// Get candidate items from purchase/enrollment history.
		if ( ! empty( $signals['history']['categories'] ) || ! empty( $signals['history']['complementary'] ) ) {
			$history_items = $this->get_items_from_history( $signals['history'] );
			foreach ( $history_items as $item ) {
				$key = $item['type'] . '_' . $item['id'];
				if ( ! isset( $scored_items[ $key ] ) ) {
					$scored_items[ $key ]           = $item;
					$scored_items[ $key ]['score']  = 0;
					$scored_items[ $key ]['source'] = 'history';
				}
				$scored_items[ $key ]['score'] += $item['relevance'] * $weights['history'];
			}
		}

		// Apply explicit request bonus.
		if ( ! empty( $signals['explicit']['is_explicit'] ) ) {
			$boost = $signals['explicit']['confidence'] * $weights['explicit'];
			foreach ( $scored_items as $key => $item ) {
				// Boost items matching the explicit type.
				if ( 'any' === $signals['explicit']['type'] || $item['type'] === $signals['explicit']['type'] ) {
					$scored_items[ $key ]['score'] += $boost;
				}
			}
		}

		// Fallback to popular items if no recommendations.
		if ( empty( $scored_items ) ) {
			$scored_items = $this->get_fallback_recommendations();
		}

		// Sort by score descending.
		uasort(
			$scored_items,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		// Filter by explicit type if specified.
		if ( ! empty( $signals['explicit']['is_explicit'] ) && 'any' !== $signals['explicit']['type'] ) {
			$requested_type = $signals['explicit']['type'];
			$scored_items   = array_filter(
				$scored_items,
				function ( $item ) use ( $requested_type ) {
					return $item['type'] === $requested_type;
				}
			);
		}

		return array_values( $scored_items );
	}

	/**
	 * Format recommendations as suggestion cards for UI.
	 *
	 * Implements: FR-255 (Suggestion UI Cards)
	 *
	 * @since 2.0.0
	 *
	 * @param array $recommendations Raw recommendations.
	 * @return array Formatted suggestion cards.
	 */
	public function format_suggestion_cards( array $recommendations ): array {
		$cards = array();

		foreach ( $recommendations as $item ) {
			$card = array(
				'id'              => $item['id'],
				'type'            => $item['type'],
				'title'           => '',
				'image'           => '',
				'price'           => '',
				'description'     => '',
				'url'             => '',
				'action'          => array(),
				'relevance_score' => round( $item['score'], 2 ),
				'source'          => $item['source'] ?? 'unknown',
				'is_owned'        => $item['is_owned'] ?? false,
				'ownership_status' => $item['ownership_status'] ?? null,
			);

			if ( 'product' === $item['type'] && $this->is_woocommerce_active() ) {
				$card = $this->format_product_card( $item['id'], $card );
			} elseif ( 'course' === $item['type'] && $this->is_learndash_active() ) {
				$card = $this->format_course_card( $item['id'], $card );
			}

			// Only add if we got valid data.
			if ( ! empty( $card['title'] ) ) {
				/**
				 * Filter individual recommendation item.
				 *
				 * @since 2.0.0
				 *
				 * @param array $card     Formatted card data.
				 * @param array $item     Raw item data.
				 */
				$cards[] = apply_filters( 'ai_botkit_recommendation_item', $card, $item );
			}
		}

		return $cards;
	}

	/**
	 * Format a WooCommerce product as a suggestion card.
	 *
	 * Implements: FR-259 (WooCommerce Product Suggestions)
	 *
	 * @since 2.0.0
	 *
	 * @param int   $product_id Product ID.
	 * @param array $card       Base card array.
	 * @return array Formatted card.
	 */
	private function format_product_card( int $product_id, array $card ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $card;
		}

		$card['title']        = mb_strimwidth( $product->get_name(), 0, 50, '...' );
		$card['image']        = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '';
		$card['price']        = $product->get_price_html();
		$card['description']  = mb_strimwidth( wp_strip_all_tags( $product->get_short_description() ), 0, 80, '...' );
		$card['url']          = get_permalink( $product_id );
		$card['rating']       = $product->get_average_rating();
		$card['review_count'] = $product->get_review_count();
		$card['stock_status'] = $product->get_stock_status();

		// Check if product is already in cart.
		$in_cart = false;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] === $product_id || ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] === $product_id ) ) {
					$in_cart = true;
					break;
				}
			}
		}

		// Determine action based on product type and cart status.
		if ( $in_cart ) {
			// Product is already in cart - show View Cart button.
			$cart_url       = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
			$card['action'] = array(
				'type'  => 'view_cart',
				'label' => __( 'View Cart', 'knowvault' ),
				'url'   => $cart_url,
			);
		} elseif ( $product->is_purchasable() && $product->is_in_stock() ) {
			if ( $product->is_type( 'simple' ) ) {
				$card['action'] = array(
					'type'  => 'add_to_cart',
					'label' => __( 'Add to Cart', 'knowvault' ),
				);
			} else {
				$card['action'] = array(
					'type'  => 'view',
					'label' => __( 'Select Options', 'knowvault' ),
				);
			}
		} else {
			$card['action'] = array(
				'type'  => 'view',
				'label' => __( 'View Product', 'knowvault' ),
			);
		}

		return $card;
	}

	/**
	 * Format a LearnDash course as a suggestion card.
	 *
	 * Implements: FR-258 (LearnDash Course Suggestions)
	 *
	 * @since 2.0.0
	 *
	 * @param int   $course_id Course ID.
	 * @param array $card      Base card array.
	 * @return array Formatted card.
	 */
	private function format_course_card( int $course_id, array $card ): array {
		$course = get_post( $course_id );
		if ( ! $course || 'sfwd-courses' !== $course->post_type ) {
			return $card;
		}

		$card['title']       = mb_strimwidth( $course->post_title, 0, 50, '...' );
		$card['image']       = get_the_post_thumbnail_url( $course_id, 'thumbnail' ) ?: '';
		$card['description'] = mb_strimwidth( wp_strip_all_tags( $course->post_excerpt ?: $course->post_content ), 0, 80, '...' );
		$card['url']         = get_permalink( $course_id );

		// Get course meta.
		$price_type = get_post_meta( $course_id, '_sfwd-courses_course_price_type', true );
		$price      = get_post_meta( $course_id, '_sfwd-courses_course_price', true );

		if ( 'free' === $price_type || empty( $price ) ) {
			$card['price'] = __( 'Free', 'knowvault' );
		} else {
			$card['price'] = wc_price( $price );
		}

		// Get lesson count.
		if ( function_exists( 'learndash_get_course_lessons_list' ) ) {
			$lessons              = learndash_get_course_lessons_list( $course_id );
			$card['lesson_count'] = count( $lessons );
		}

		// Check enrollment status.
		$user_id = get_current_user_id();
		if ( $user_id > 0 && function_exists( 'sfwd_lms_has_access' ) ) {
			$has_access = sfwd_lms_has_access( $course_id, $user_id );

			if ( $has_access ) {
				// User is enrolled - show progress.
				if ( function_exists( 'learndash_course_progress' ) ) {
					$progress         = learndash_course_progress(
						array(
							'user_id'   => $user_id,
							'course_id' => $course_id,
							'array'     => true,
						)
					);
					$card['progress'] = $progress['percentage'] ?? 0;
				}

				$card['action'] = array(
					'type'  => 'continue',
					'label' => __( 'Continue Learning', 'knowvault' ),
				);
			} else {
				// Not enrolled.
				if ( 'free' === $price_type || empty( $price ) ) {
					$card['action'] = array(
						'type'  => 'enroll',
						'label' => __( 'Enroll Now', 'knowvault' ),
					);
				} else {
					$card['action'] = array(
						'type'  => 'view',
						'label' => __( 'View Course', 'knowvault' ),
					);
				}
			}
		} else {
			$card['action'] = array(
				'type'  => 'view',
				'label' => __( 'View Course', 'knowvault' ),
			);
		}

		return $card;
	}

	/**
	 * Get WooCommerce purchase history.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array Purchase history data.
	 */
	private function get_woocommerce_history( int $user_id ): array {
		$result = array(
			'purchased_ids' => array(),
			'categories'    => array(),
			'price_range'   => array(
				'min' => 0,
				'max' => 0,
			),
			'complementary' => array(),
		);

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing' ),
				'limit'       => 20,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$prices = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id                = $item->get_product_id();
				$result['purchased_ids'][] = $product_id;

				// Get categories.
				$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) ) {
					$result['categories'] = array_merge( $result['categories'], $terms );
				}

				// Track price.
				$product = $item->get_product();
				if ( $product ) {
					$prices[] = (float) $product->get_price();

					// Get related products as complementary.
					$related                 = wc_get_related_products( $product_id, 3 );
					$result['complementary'] = array_merge( $result['complementary'], $related );
				}
			}
		}

		$result['purchased_ids'] = array_unique( $result['purchased_ids'] );
		$result['categories']    = array_unique( $result['categories'] );
		$result['complementary'] = array_unique( $result['complementary'] );

		if ( ! empty( $prices ) ) {
			$result['price_range'] = array(
				'min' => min( $prices ),
				'max' => max( $prices ),
			);
		}

		return $result;
	}

	/**
	 * Get LearnDash enrollment history.
	 *
	 * @since 2.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array Enrollment history data.
	 */
	private function get_learndash_history( int $user_id ): array {
		$result = array(
			'enrolled_ids' => array(),
			'categories'   => array(),
		);

		if ( ! function_exists( 'learndash_user_get_enrolled_courses' ) ) {
			return $result;
		}

		$courses = learndash_user_get_enrolled_courses( $user_id );

		foreach ( $courses as $course_id ) {
			$result['enrolled_ids'][] = $course_id;

			// Get course categories.
			$terms = wp_get_post_terms( $course_id, 'ld_course_category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				$result['categories'] = array_merge( $result['categories'], $terms );
			}
		}

		$result['enrolled_ids'] = array_unique( $result['enrolled_ids'] );
		$result['categories']   = array_unique( $result['categories'] );

		return $result;
	}

	/**
	 * Get items from conversation context.
	 *
	 * @since 2.0.0
	 *
	 * @param array $context Conversation analysis.
	 * @return array Items with relevance scores.
	 */
	private function get_items_from_context( array $context ): array {
		$items = array();

		// Get products matching keywords.
		if ( $this->is_woocommerce_active() && ! empty( $context['keywords'] ) ) {
			$products = $this->search_products( $context['keywords'], $context['categories'] );
			foreach ( $products as $product ) {
				$items[] = array(
					'id'        => $product->get_id(),
					'type'      => 'product',
					'relevance' => $this->calculate_keyword_relevance( $product->get_name(), $context['keywords'] ),
				);
			}
		}

		// Get courses matching keywords.
		if ( $this->is_learndash_active() && ! empty( $context['keywords'] ) ) {
			$courses = $this->search_courses( $context['keywords'], $context['categories'] );
			foreach ( $courses as $course ) {
				$items[] = array(
					'id'        => $course->ID,
					'type'      => 'course',
					'relevance' => $this->calculate_keyword_relevance( $course->post_title, $context['keywords'] ),
				);
			}
		}

		return $items;
	}

	/**
	 * Get items from browsing history.
	 *
	 * @since 2.0.0
	 *
	 * @param array $browsing Browsing history data.
	 * @return array Items with relevance scores.
	 */
	private function get_items_from_browsing( array $browsing ): array {
		$items = array();

		// Get related products to viewed products.
		if ( $this->is_woocommerce_active() && ! empty( $browsing['product_ids'] ) ) {
			foreach ( $browsing['product_ids'] as $product_id ) {
				$related = wc_get_related_products( $product_id, 3 );
				foreach ( $related as $related_id ) {
					$items[] = array(
						'id'        => $related_id,
						'type'      => 'product',
						'relevance' => 0.8,
					);
				}
			}
		}

		// Get related courses.
		if ( $this->is_learndash_active() && ! empty( $browsing['course_ids'] ) ) {
			foreach ( $browsing['course_ids'] as $course_id ) {
				// Get courses in same category.
				$categories = wp_get_post_terms( $course_id, 'ld_course_category', array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
					$related_courses = get_posts(
						array(
							'post_type'      => 'sfwd-courses',
							'post_status'    => 'publish',
							'posts_per_page' => 2,
							'post__not_in'   => array( $course_id ),
							'tax_query'      => array(
								array(
									'taxonomy' => 'ld_course_category',
									'field'    => 'term_id',
									'terms'    => $categories,
								),
							),
						)
					);

					foreach ( $related_courses as $related ) {
						$items[] = array(
							'id'        => $related->ID,
							'type'      => 'course',
							'relevance' => 0.8,
						);
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Get items from purchase/enrollment history.
	 *
	 * @since 2.0.0
	 *
	 * @param array $history History data.
	 * @return array Items with relevance scores.
	 */
	private function get_items_from_history( array $history ): array {
		$items = array();

		// Get complementary products.
		if ( $this->is_woocommerce_active() && ! empty( $history['complementary'] ) ) {
			foreach ( array_slice( $history['complementary'], 0, 10 ) as $product_id ) {
				$items[] = array(
					'id'        => $product_id,
					'type'      => 'product',
					'relevance' => 0.7,
				);
			}
		}

		// Get courses in purchased categories.
		if ( $this->is_learndash_active() && ! empty( $history['categories'] ) ) {
			$courses = get_posts(
				array(
					'post_type'      => 'sfwd-courses',
					'post_status'    => 'publish',
					'posts_per_page' => 2,
					'post__not_in'   => $history['enrolled_ids'] ?? array(),
				)
			);

			foreach ( $courses as $course ) {
				$items[] = array(
					'id'        => $course->ID,
					'type'      => 'course',
					'relevance' => 0.6,
				);
			}
		}

		return $items;
	}

	/**
	 * Get fallback recommendations when no signals are available.
	 *
	 * @since 2.0.0
	 *
	 * @return array Fallback items.
	 */
	private function get_fallback_recommendations(): array {
		$items = array();

		// Get featured/popular products.
		if ( $this->is_woocommerce_active() ) {
			$featured = wc_get_products(
				array(
					'featured' => true,
					'status'   => 'publish',
					'limit'    => 2,
					'orderby'  => 'popularity',
				)
			);

			// If no featured products, get any published products (most recent or popular).
			if ( empty( $featured ) ) {
				$featured = wc_get_products(
					array(
						'status'  => 'publish',
						'limit'   => 2,
						'orderby' => 'date',
						'order'   => 'DESC',
					)
				);
			}

			foreach ( $featured as $product ) {
				$items[] = array(
					'id'        => $product->get_id(),
					'type'      => 'product',
					'relevance' => 0.5,
					'score'     => 0.5,
					'source'    => 'featured',
				);
			}
		}

		// Get popular courses.
		if ( $this->is_learndash_active() ) {
			$courses = get_posts(
				array(
					'post_type'      => 'sfwd-courses',
					'post_status'    => 'publish',
					'posts_per_page' => 2,
					'orderby'        => 'comment_count',
					'order'          => 'DESC',
				)
			);

			foreach ( $courses as $course ) {
				$items[] = array(
					'id'        => $course->ID,
					'type'      => 'course',
					'relevance' => 0.5,
					'score'     => 0.5,
					'source'    => 'popular',
				);
			}
		}

		return $items;
	}

	/**
	 * Filter out items user already owns.
	 *
	 * @since 2.0.0
	 *
	 * @param array $items   Items to filter.
	 * @param int   $user_id User ID.
	 * @return array Filtered items.
	 */
	private function filter_owned_items( array $items, int $user_id ): array {
		if ( $user_id <= 0 ) {
			return $items;
		}

		$history = $this->get_purchase_enrollment_history( $user_id );

		return array_filter(
			$items,
			function ( $item ) use ( $history ) {
				if ( 'product' === $item['type'] && in_array( $item['id'], $history['purchased_ids'] ?? array(), true ) ) {
					return false;
				}
				if ( 'course' === $item['type'] && in_array( $item['id'], $history['enrolled_ids'] ?? array(), true ) ) {
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * Mark already owned items (purchased products or enrolled courses)
	 * without filtering them out.
	 *
	 * @since 2.0.0
	 *
	 * @param array $items   Recommendation items.
	 * @param int   $user_id User ID.
	 * @return array Items with ownership status marked.
	 */
	private function mark_owned_items( array $items, int $user_id ): array {
		if ( $user_id <= 0 ) {
			return $items;
		}

		$history = $this->get_purchase_enrollment_history( $user_id );

		foreach ( $items as $key => $item ) {
			$is_owned = false;

			if ( 'product' === $item['type'] && in_array( $item['id'], $history['purchased_ids'] ?? array(), true ) ) {
				$is_owned = true;
				$items[ $key ]['ownership_status'] = 'purchased';
			} elseif ( 'course' === $item['type'] && in_array( $item['id'], $history['enrolled_ids'] ?? array(), true ) ) {
				$is_owned = true;
				$items[ $key ]['ownership_status'] = 'enrolled';
			}

			$items[ $key ]['is_owned'] = $is_owned;
		}

		return $items;
	}

	/**
	 * Search WooCommerce products.
	 *
	 * @since 2.0.0
	 *
	 * @param array $keywords   Keywords to search.
	 * @param array $categories Categories to filter.
	 * @return array WC_Product objects.
	 */
	private function search_products( array $keywords, array $categories = array() ): array {
		error_log( '[Recommendation] search_products called with keywords: ' . wp_json_encode( $keywords ) );

		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			error_log( '[Recommendation] WooCommerce is not active, returning empty array' );
			return array();
		}

		if ( ! empty( $keywords ) ) {
			$search_query = implode( ' ', $keywords );
			error_log( '[Recommendation] Searching WooCommerce products for: ' . $search_query );

			// Use WP_Query for keyword search
			$query_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				's'              => $search_query,
			);

			if ( ! empty( $categories ) ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $categories,
					),
				);
			}

			$query    = new \WP_Query( $query_args );
			$products = array();

			error_log( '[Recommendation] WP_Query found ' . $query->found_posts . ' products' );

			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( $product ) {
					$products[] = $product;
					error_log( '[Recommendation] Added product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')' );
				}
			}

			error_log( '[Recommendation] Returning ' . count( $products ) . ' products from keyword search' );
			return $products;
		}

		// Fallback: get popular products
		$args = array(
			'status'  => 'publish',
			'limit'   => 10,
			'orderby' => 'popularity',
		);

		$products = wc_get_products( $args );
		error_log( '[Recommendation] Returning ' . count( $products ) . ' popular products (no keywords)' );
		return $products;
	}

	/**
	 * Search LearnDash courses.
	 *
	 * @since 2.0.0
	 *
	 * @param array $keywords   Keywords to search.
	 * @param array $categories Categories to filter.
	 * @return array WP_Post objects.
	 */
	private function search_courses( array $keywords, array $categories = array() ): array {
		error_log( '[Recommendation] search_courses called with keywords: ' . wp_json_encode( $keywords ) );
		global $wpdb;

		// Use indexed content search if we have keywords
		if ( ! empty( $keywords ) ) {
			$search_query = implode( ' ', $keywords );
			error_log( '[Recommendation] Searching indexed content for: ' . $search_query );

			// Filter out very common words that appear in almost all courses
			// These create too much noise and false positives
			$common_filler_words = array( 'available', 'various', 'options', 'check', 'appearing', 'below', 'sure', 'happy', 'there', 'many', 'great', 'more' );
			$filtered_keywords   = array_diff( $keywords, $common_filler_words );

			// If no significant keywords left after filtering, return empty
			if ( empty( $filtered_keywords ) ) {
				error_log( '[Recommendation] No significant keywords after filtering common words' );
				return array();
			}

			error_log( '[Recommendation] Filtered keywords: ' . wp_json_encode( $filtered_keywords ) );

			// Build keyword search conditions for chunks
			$keyword_conditions = array();
			$keyword_values     = array();
			foreach ( $filtered_keywords as $keyword ) {
				$keyword_conditions[] = '(c.content LIKE %s OR d.title LIKE %s)';
				$keyword_values[]     = '%' . $wpdb->esc_like( $keyword ) . '%';
				$keyword_values[]     = '%' . $wpdb->esc_like( $keyword ) . '%';
			}
			$keyword_where = implode( ' OR ', $keyword_conditions );

			// Search in chunks and join with documents to get matching courses
			// Give MUCH higher weight to title matches (100x) to prioritize relevance
			$query = $wpdb->prepare(
				"SELECT DISTINCT d.source_id, d.title,
					COUNT(DISTINCT c.id) as chunk_matches,
					SUM(
						CASE
							WHEN d.title LIKE %s THEN 100
							ELSE 1
						END
					) as relevance_score
				FROM {$wpdb->prefix}ai_botkit_chunks c
				INNER JOIN {$wpdb->prefix}ai_botkit_documents d ON c.document_id = d.id
				WHERE d.source_type = %s
					AND d.status = 'processed'
					AND d.source_id IS NOT NULL
					AND ({$keyword_where})
				GROUP BY d.source_id, d.title
				HAVING relevance_score > 2
				ORDER BY relevance_score DESC, chunk_matches DESC
				LIMIT 10",
				array_merge(
					array( '%' . $wpdb->esc_like( $search_query ) . '%' ), // Title search
					array( 'learndash_course' ),
					$keyword_values
				)
			);

			$documents = $wpdb->get_results( $query );

			error_log( '[Recommendation] Found ' . count( $documents ) . ' courses matching keywords' );
			if ( ! empty( $documents ) ) {
				error_log( '[Recommendation] Top match: ' . wp_json_encode( $documents[0] ) );
			}

			// Convert to post objects
			$courses = array();
			foreach ( $documents as $doc ) {
				$course = get_post( $doc->source_id );
				if ( $course && $course->post_status === 'publish' ) {
					$courses[] = $course;
					error_log( '[Recommendation] Added course: ' . $course->post_title . ' (ID: ' . $course->ID . ', relevance: ' . $doc->relevance_score . ')' );
				} else {
					error_log( '[Recommendation] Skipped course ID ' . $doc->source_id . ' - not found or not published' );
				}
			}

			error_log( '[Recommendation] Returning ' . count( $courses ) . ' courses from keyword search' );
			return $courses;
		}

		// Fallback to WordPress search if no keywords
		$args = array(
			'post_type'      => 'sfwd-courses',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		);

		if ( ! empty( $categories ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'ld_course_category',
					'field'    => 'term_id',
					'terms'    => $categories,
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Extract keywords from text.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text to analyze.
	 * @return array Keywords.
	 */
	private function extract_keywords( string $text ): array {
		// Remove common stop words.
		$stop_words = array(
			'the',
			'a',
			'an',
			'and',
			'or',
			'but',
			'is',
			'are',
			'was',
			'were',
			'to',
			'of',
			'in',
			'for',
			'on',
			'with',
			'at',
			'by',
			'from',
			'as',
			'i',
			'you',
			'we',
			'they',
			'he',
			'she',
			'it',
			'this',
			'that',
			'can',
			'could',
			'would',
			'should',
			'will',
			'may',
			'might',
			'do',
			'does',
			'did',
			'have',
			'has',
			'had',
			'be',
			'been',
			'being',
			'what',
			'which',
			'who',
			'whom',
			'where',
			'when',
			'why',
			'how',
			'me',
			'my',
			'your',
			'our',
			'their',
			'his',
			'her',
			'its',
			'recommend',
			'suggest',
			'looking',
			'want',
			'need',
			'find',
			'help',
			'please',
			'thanks',
			'thank',
			'hi',
			'hello',
			'hey',
			'some',
			'maybe',
			'could',
			'would',
			'should',
		);

		// Normalize common product terms (e.g., "t-shirt", "t shirt", "tshirt" -> "tshirt").
		$text = preg_replace( '/\b(t-?shirt|tee-?shirt|tshirt)\b/i', 'tshirt', $text );
		$text = preg_replace( '/\b(t-?shirts|tee-?shirts|tshirts)\b/i', 'tshirts', $text );

		// Tokenize.
		$words = preg_split( '/\s+/', $text );
		$words = array_map(
			function ( $word ) {
				return preg_replace( '/[^a-z0-9]/', '', strtolower( $word ) );
			},
			$words
		);

		// Filter.
		$keywords = array_filter(
			$words,
			function ( $word ) use ( $stop_words ) {
				return strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
			}
		);

		return array_values( array_unique( $keywords ) );
	}

	/**
	 * Detect categories mentioned in text.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text to analyze.
	 * @return array Category IDs.
	 */
	private function detect_categories( string $text ): array {
		$categories = array();

		// Check WooCommerce categories.
		if ( $this->is_woocommerce_active() ) {
			$product_cats = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
				)
			);

			foreach ( $product_cats as $cat ) {
				if ( stripos( $text, strtolower( $cat->name ) ) !== false ) {
					$categories[] = $cat->term_id;
				}
			}
		}

		// Check LearnDash categories.
		if ( $this->is_learndash_active() ) {
			$course_cats = get_terms(
				array(
					'taxonomy'   => 'ld_course_category',
					'hide_empty' => true,
				)
			);

			foreach ( $course_cats as $cat ) {
				if ( stripos( $text, strtolower( $cat->name ) ) !== false ) {
					$categories[] = $cat->term_id;
				}
			}
		}

		return $categories;
	}

	/**
	 * Extract item mentions from conversation.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Conversation text.
	 * @return array Item IDs mentioned.
	 */
	private function extract_item_mentions( string $text ): array {
		$items = array();

		// Look for product/course names mentioned.
		// This is a simplified implementation.
		// In production, could use more sophisticated NER.

		return $items;
	}

	/**
	 * Detect intent type from text.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text to analyze.
	 * @return string Intent type.
	 */
	private function detect_intent_type( string $text ): string {
		// Check for course-related keywords first
		if ( preg_match( '/course|class|lesson|learn|training|tutorial|education|curriculum|module/i', $text ) ) {
			return 'course';
		}

		// Check for product-related keywords and common product types
		// Includes: generic product terms, clothing, electronics, accessories, etc.
		if ( preg_match( '/product|buy|purchase|shop|order|price|cost|shirt|tshirt|t-shirt|hoodie|hat|cap|shoe|shoes|bag|watch|laptop|phone|tablet|accessory|accessories|gadget|item|goods/i', $text ) ) {
			return 'product';
		}

		// If WooCommerce is active but LearnDash isn't, assume products
		if ( $this->is_woocommerce_active() && ! $this->is_learndash_active() ) {
			return 'product';
		}

		// If LearnDash is active but WooCommerce isn't, assume courses
		if ( $this->is_learndash_active() && ! $this->is_woocommerce_active() ) {
			return 'course';
		}

		return 'general';
	}

	/**
	 * Calculate intent confidence.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text   Text to analyze.
	 * @param string $intent Detected intent.
	 * @return float Confidence score 0-1.
	 */
	private function calculate_intent_confidence( string $text, string $intent ): float {
		$confidence = 0.0;

		foreach ( self::INTENT_PATTERNS as $pattern ) {
			if ( strpos( $text, $pattern ) !== false ) {
				$confidence += 0.2;
			}
		}

		return min( $confidence, 1.0 );
	}

	/**
	 * Extract criteria from recommendation request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Text to analyze.
	 * @return array Criteria.
	 */
	private function extract_criteria( string $text ): array {
		$criteria = array();

		// Extract price constraints.
		if ( preg_match( '/under\s*\$?(\d+)/i', $text, $matches ) ) {
			$criteria['price_max'] = (float) $matches[1];
		}
		if ( preg_match( '/over\s*\$?(\d+)/i', $text, $matches ) ) {
			$criteria['price_min'] = (float) $matches[1];
		}

		// Extract level.
		if ( preg_match( '/beginner|basic|intro/i', $text ) ) {
			$criteria['level'] = 'beginner';
		} elseif ( preg_match( '/intermediate|medium/i', $text ) ) {
			$criteria['level'] = 'intermediate';
		} elseif ( preg_match( '/advanced|expert/i', $text ) ) {
			$criteria['level'] = 'advanced';
		}

		return $criteria;
	}

	/**
	 * Calculate keyword relevance.
	 *
	 * @since 2.0.0
	 *
	 * @param string $title    Item title.
	 * @param array  $keywords Keywords to match.
	 * @return float Relevance score 0-1.
	 */
	private function calculate_keyword_relevance( string $title, array $keywords ): float {
		if ( empty( $keywords ) ) {
			return 0.5;
		}

		$title_lower = strtolower( $title );
		$matches     = 0;

		foreach ( $keywords as $keyword ) {
			if ( strpos( $title_lower, $keyword ) !== false ) {
				++$matches;
			}
		}

		return min( $matches / count( $keywords ), 1.0 );
	}

	/**
	 * Get signal weights.
	 *
	 * @since 2.0.0
	 *
	 * @return array Signal weights.
	 */
	private function get_signal_weights(): array {
		$weights = self::DEFAULT_WEIGHTS;

		/**
		 * Filter recommendation signal weights.
		 *
		 * @since 2.0.0
		 *
		 * @param array $weights Default weights.
		 */
		return apply_filters( 'ai_botkit_recommendation_signals', $weights );
	}

	/**
	 * Get cache key for recommendations.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $context Context array.
	 * @return string Cache key.
	 */
	private function get_cache_key( int $user_id, array $context ): string {
		$key_parts = array(
			'recommendations',
			$user_id,
			md5( $context['conversation_text'] ?? '' ),
			$context['chatbot_id'] ?? 0,
		);

		return implode( '_', $key_parts );
	}

	/**
	 * Get session ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string Session ID.
	 */
	private function get_session_id(): string {
		if ( ! session_id() ) {
			if ( ! headers_sent() ) {
				session_start();
			}
		}

		return session_id() ?: wp_generate_uuid4();
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if WooCommerce is active.
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check if LearnDash is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if LearnDash is active.
	 */
	private function is_learndash_active(): bool {
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Track a user interaction for future recommendations.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $user_id          User ID.
	 * @param string $interaction_type Interaction type.
	 * @param string $item_type        Item type.
	 * @param int    $item_id          Item ID.
	 * @param array  $metadata         Additional metadata.
	 * @return bool Success.
	 */
	public function track_interaction( int $user_id, string $interaction_type, string $item_type, int $item_id, array $metadata = array() ): bool {
		global $wpdb;

		$table = $this->table_prefix . 'user_interactions';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'          => $user_id,
				'session_id'       => $this->get_session_id(),
				'interaction_type' => $interaction_type,
				'item_type'        => $item_type,
				'item_id'          => $item_id,
				'chatbot_id'       => $metadata['chatbot_id'] ?? null,
				'metadata'         => wp_json_encode( $metadata ),
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		/**
		 * Action fired after interaction tracked.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $user_id          User ID.
		 * @param string $interaction_type Interaction type.
		 * @param array  $data             Interaction data.
		 */
		do_action(
			'ai_botkit_interaction_tracked',
			$user_id,
			$interaction_type,
			array(
				'item_type' => $item_type,
				'item_id'   => $item_id,
				'metadata'  => $metadata,
			)
		);

		return $result !== false;
	}

	/**
	 * Extract only the user's message from conversation text.
	 *
	 * Removes bot responses which can contaminate type detection.
	 * For example: "Can you recommend T shirts? Of course! ..." - the "Of course!"
	 * contains "course" which wrongly triggers course detection.
	 *
	 * @since 2.0.0
	 *
	 * @param string $conversation_text Full conversation text.
	 * @return string User's message only.
	 */
	private function extract_user_message( string $conversation_text ): string {
		if ( empty( $conversation_text ) ) {
			return '';
		}

		// Common patterns that indicate the start of a bot response
		$bot_response_markers = array(
			'? Sure,',
			'? Of course',
			'? Certainly',
			'? Yes,',
			'? No,',
			'? I can',
			'? I\'ll',
			'? I would',
			'? Let me',
			'? Here',
			'? There',
		);

		// Find the first occurrence of any bot response marker
		$split_pos = false;
		foreach ( $bot_response_markers as $marker ) {
			$pos = stripos( $conversation_text, $marker );
			if ( $pos !== false && ( $split_pos === false || $pos < $split_pos ) ) {
				$split_pos = $pos + 1; // +1 to include the '?'
			}
		}

		// If we found a marker, extract everything before it
		if ( $split_pos !== false && $split_pos > 0 ) {
			$user_message = substr( $conversation_text, 0, $split_pos );
			error_log( '[Recommendation] Extracted user message (split at pos ' . $split_pos . '): ' . $user_message );
			return trim( $user_message );
		}

		// Fallback: Try to extract just the first sentence/question
		$sentences = preg_split( '/[.!?]+/', $conversation_text, 2 );
		if ( ! empty( $sentences[0] ) ) {
			$first_sentence = trim( $sentences[0] );
			// If it's longer than 20 chars and contains recommendation keywords, use it
			if ( strlen( $first_sentence ) > 20 ) {
				error_log( '[Recommendation] Extracted first sentence: ' . $first_sentence );
				return $first_sentence;
			}
		}

		// Last resort: return the whole text
		error_log( '[Recommendation] Using full conversation text (no split detected)' );
		return $conversation_text;
	}
}
