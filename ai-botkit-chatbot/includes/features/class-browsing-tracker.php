<?php
/**
 * Browsing Tracker
 *
 * Tracks user page views for products and courses to inform recommendations.
 * Works with both logged-in users (tracked by user_id) and guests (tracked by session).
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-252 (Browsing History Tracking)
 */

namespace AI_BotKit\Features;

/**
 * Browsing_Tracker class.
 *
 * Tracks page views for products, courses, and other content types
 * to provide browsing history signals for recommendations.
 *
 * @since 2.0.0
 */
class Browsing_Tracker {

    /**
     * Table name for user interactions.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Session ID for current request.
     *
     * @var string
     */
    private string $session_id;

    /**
     * Track in session only (for guests without DB).
     *
     * @var bool
     */
    private bool $session_only = false;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'ai_botkit_user_interactions';
        $this->session_id = $this->get_or_create_session_id();

        // Check if table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name )
        );

        if ( ! $table_exists ) {
            $this->session_only = true;
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @since 2.0.0
     */
    private function init_hooks(): void {
        // Track page views via AJAX (called from JavaScript).
        add_action( 'wp_ajax_ai_botkit_track_page_view', array( $this, 'handle_track_page_view' ) );
        add_action( 'wp_ajax_nopriv_ai_botkit_track_page_view', array( $this, 'handle_track_page_view' ) );

        // Auto-track on page load for logged-in users.
        add_action( 'wp', array( $this, 'maybe_track_page_view' ), 20 );

        // Enqueue tracking script.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

        // Regenerate session ID on authentication state changes to prevent session fixation.
        add_action( 'wp_login', array( $this, 'regenerate_session_on_login' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'regenerate_session_on_logout' ) );
    }

    /**
     * Track a page view.
     *
     * Records the page view in the database for logged-in users
     * or in the session for guests.
     *
     * @since 2.0.0
     *
     * @param string $item_type Item type (product, course, post, page).
     * @param int    $item_id   Item ID.
     * @param array  $metadata  Additional metadata.
     * @return bool Success.
     */
    public function track_page_view( string $item_type, int $item_id, array $metadata = array() ): bool {
        $user_id = get_current_user_id();

        // For session-only tracking (table doesn't exist).
        if ( $this->session_only ) {
            return $this->track_in_session( $item_type, $item_id, $metadata );
        }

        global $wpdb;

        // Prevent duplicate tracking within short period.
        $recent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE (user_id = %d OR session_id = %s)
                 AND item_type = %s
                 AND item_id = %d
                 AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                $user_id,
                $this->session_id,
                $item_type,
                $item_id
            )
        );

        if ( $recent > 0 ) {
            return true; // Already tracked recently.
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id'          => $user_id,
                'session_id'       => $this->session_id,
                'interaction_type' => $this->get_interaction_type( $item_type ),
                'item_type'        => $item_type,
                'item_id'          => $item_id,
                'chatbot_id'       => $metadata['chatbot_id'] ?? null,
                'metadata'         => wp_json_encode( $metadata ),
                'created_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        return $result !== false;
    }

    /**
     * Get session browsing history.
     *
     * Retrieves all page views for the current session or user.
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
    public function get_session_history( int $user_id = 0, string $session_id = '' ): array {
        // Default result.
        $result = array(
            'product_ids' => array(),
            'course_ids'  => array(),
            'categories'  => array(),
            'view_count'  => 0,
        );

        // Use current values if not provided.
        if ( $user_id <= 0 ) {
            $user_id = get_current_user_id();
        }
        if ( empty( $session_id ) ) {
            $session_id = $this->session_id;
        }

        // Try session storage first.
        $session_data = $this->get_from_session();
        if ( ! empty( $session_data ) ) {
            $result = array_merge( $result, $session_data );
        }

        // If table doesn't exist, return session data only.
        if ( $this->session_only ) {
            return $result;
        }

        global $wpdb;

        // Build where clause.
        if ( $user_id > 0 ) {
            $where = $wpdb->prepare( 'user_id = %d', $user_id );
        } else {
            $where = $wpdb->prepare( 'session_id = %s', $session_id );
        }

        // Query database.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT item_type, item_id, COUNT(*) as view_count
             FROM {$this->table_name}
             WHERE {$where}
             AND interaction_type IN ('page_view', 'product_view', 'course_view')
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY item_type, item_id
             ORDER BY view_count DESC, MAX(created_at) DESC
             LIMIT 50"
        );

        foreach ( $rows as $row ) {
            $result['view_count'] += (int) $row->view_count;

            if ( 'product' === $row->item_type ) {
                $result['product_ids'][] = (int) $row->item_id;

                // Get product categories.
                $terms = wp_get_post_terms( $row->item_id, 'product_cat', array( 'fields' => 'ids' ) );
                if ( ! is_wp_error( $terms ) ) {
                    $result['categories'] = array_merge( $result['categories'], $terms );
                }
            } elseif ( 'course' === $row->item_type ) {
                $result['course_ids'][] = (int) $row->item_id;

                // Get course categories.
                $terms = wp_get_post_terms( $row->item_id, 'ld_course_category', array( 'fields' => 'ids' ) );
                if ( ! is_wp_error( $terms ) ) {
                    $result['categories'] = array_merge( $result['categories'], $terms );
                }
            }
        }

        // Deduplicate.
        $result['product_ids'] = array_unique( $result['product_ids'] );
        $result['course_ids']  = array_unique( $result['course_ids'] );
        $result['categories']  = array_unique( $result['categories'] );

        return $result;
    }

    /**
     * Extract product IDs from viewed pages.
     *
     * @since 2.0.0
     *
     * @param int    $user_id    User ID.
     * @param string $session_id Session ID.
     * @return array Product IDs.
     */
    public function extract_product_ids( int $user_id = 0, string $session_id = '' ): array {
        $history = $this->get_session_history( $user_id, $session_id );
        return $history['product_ids'];
    }

    /**
     * Extract course IDs from viewed pages.
     *
     * @since 2.0.0
     *
     * @param int    $user_id    User ID.
     * @param string $session_id Session ID.
     * @return array Course IDs.
     */
    public function extract_course_ids( int $user_id = 0, string $session_id = '' ): array {
        $history = $this->get_session_history( $user_id, $session_id );
        return $history['course_ids'];
    }

    /**
     * Handle AJAX track page view request.
     *
     * @since 2.0.0
     */
    public function handle_track_page_view(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'ai_botkit_track', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        $item_type = isset( $_POST['item_type'] ) ? sanitize_key( $_POST['item_type'] ) : '';
        $item_id   = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $metadata  = isset( $_POST['metadata'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['metadata'] ) ), true ) : array();

        if ( empty( $item_type ) || $item_id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
        }

        // Validate item type.
        $valid_types = array( 'product', 'course', 'post', 'page' );
        if ( ! in_array( $item_type, $valid_types, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid item type' ), 400 );
        }

        $result = $this->track_page_view( $item_type, $item_id, $metadata ?: array() );

        if ( $result ) {
            wp_send_json_success( array( 'tracked' => true ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to track' ), 500 );
        }
    }

    /**
     * Maybe track page view on page load.
     *
     * Called on 'wp' action to auto-track product/course views.
     *
     * @since 2.0.0
     */
    public function maybe_track_page_view(): void {
        // Only track on singular pages.
        if ( ! is_singular() ) {
            return;
        }

        $post_id   = get_the_ID();
        $post_type = get_post_type( $post_id );

        // Determine item type.
        $item_type = null;

        if ( 'product' === $post_type && class_exists( 'WooCommerce' ) ) {
            $item_type = 'product';
        } elseif ( 'sfwd-courses' === $post_type && defined( 'LEARNDASH_VERSION' ) ) {
            $item_type = 'course';
        }

        if ( $item_type ) {
            $this->track_page_view( $item_type, $post_id );
        }
    }

    /**
     * Enqueue tracking script.
     *
     * @since 2.0.0
     */
    public function enqueue_tracking_script(): void {
        // Only enqueue on relevant pages.
        if ( ! is_singular( array( 'product', 'sfwd-courses' ) ) ) {
            return;
        }

        wp_enqueue_script(
            'ai-botkit-browsing-tracker',
            plugins_url( 'public/js/browsing-tracker.js', dirname( __DIR__ ) ),
            array( 'jquery' ),
            AI_BOTKIT_VERSION ?? '2.0.0',
            true
        );

        wp_localize_script(
            'ai-botkit-browsing-tracker',
            'aiBotKitTracker',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'ai_botkit_track' ),
                'sessionId' => $this->session_id,
                'itemType'  => $this->get_current_item_type(),
                'itemId'    => get_the_ID(),
            )
        );
    }

    /**
     * Track in session (fallback when table doesn't exist).
     *
     * @since 2.0.0
     *
     * @param string $item_type Item type.
     * @param int    $item_id   Item ID.
     * @param array  $metadata  Metadata.
     * @return bool Success.
     */
    private function track_in_session( string $item_type, int $item_id, array $metadata ): bool {
        if ( ! session_id() ) {
            if ( headers_sent() ) {
                return false;
            }
            session_start();
        }

        $key = 'ai_botkit_browsing_history';

        if ( ! isset( $_SESSION[ $key ] ) ) {
            $_SESSION[ $key ] = array(
                'product_ids' => array(),
                'course_ids'  => array(),
                'view_count'  => 0,
            );
        }

        if ( 'product' === $item_type ) {
            if ( ! in_array( $item_id, $_SESSION[ $key ]['product_ids'], true ) ) {
                $_SESSION[ $key ]['product_ids'][] = $item_id;
            }
        } elseif ( 'course' === $item_type ) {
            if ( ! in_array( $item_id, $_SESSION[ $key ]['course_ids'], true ) ) {
                $_SESSION[ $key ]['course_ids'][] = $item_id;
            }
        }

        $_SESSION[ $key ]['view_count']++;

        return true;
    }

    /**
     * Get browsing history from session.
     *
     * @since 2.0.0
     *
     * @return array Session browsing data.
     */
    private function get_from_session(): array {
        if ( ! session_id() ) {
            if ( headers_sent() ) {
                return array();
            }
            session_start();
        }

        $key = 'ai_botkit_browsing_history';

        if ( isset( $_SESSION[ $key ] ) ) {
            return $_SESSION[ $key ];
        }

        return array();
    }

    /**
     * Get or create session ID.
     *
     * @since 2.0.0
     *
     * @return string Session ID.
     */
    private function get_or_create_session_id(): string {
        // Try to get existing session ID.
        if ( isset( $_COOKIE['ai_botkit_session'] ) ) {
            return sanitize_key( $_COOKIE['ai_botkit_session'] );
        }

        // Try PHP session.
        if ( ! session_id() ) {
            if ( ! headers_sent() ) {
                session_start();
            }
        }

        if ( session_id() ) {
            return session_id();
        }

        // Generate new session ID.
        $session_id = wp_generate_uuid4();

        // Try to set cookie.
        if ( ! headers_sent() ) {
            setcookie(
                'ai_botkit_session',
                $session_id,
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        return $session_id;
    }

    /**
     * Get interaction type based on item type.
     *
     * @since 2.0.0
     *
     * @param string $item_type Item type.
     * @return string Interaction type.
     */
    private function get_interaction_type( string $item_type ): string {
        switch ( $item_type ) {
            case 'product':
                return 'product_view';
            case 'course':
                return 'course_view';
            default:
                return 'page_view';
        }
    }

    /**
     * Get current item type for the page.
     *
     * @since 2.0.0
     *
     * @return string Item type.
     */
    private function get_current_item_type(): string {
        if ( ! is_singular() ) {
            return '';
        }

        $post_type = get_post_type();

        if ( 'product' === $post_type ) {
            return 'product';
        } elseif ( 'sfwd-courses' === $post_type ) {
            return 'course';
        }

        return '';
    }

    /**
     * Clear browsing history for a user.
     *
     * @since 2.0.0
     *
     * @param int $user_id User ID.
     * @return bool Success.
     */
    public function clear_user_history( int $user_id ): bool {
        if ( $this->session_only ) {
            // Clear session.
            if ( session_id() ) {
                unset( $_SESSION['ai_botkit_browsing_history'] );
            }
            return true;
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array( 'user_id' => $user_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Cleanup old interactions.
     *
     * @since 2.0.0
     *
     * @param int $days_old Days to keep.
     * @return int Number of rows deleted.
     */
    public function cleanup_old_interactions( int $days_old = 90 ): int {
        if ( $this->session_only ) {
            return 0;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            )
        );

        return $deleted ?: 0;
    }

    /**
     * Get the session ID.
     *
     * @since 2.0.0
     *
     * @return string Session ID.
     */
    public function get_session_id(): string {
        return $this->session_id;
    }

    /**
     * Regenerate session ID on user login.
     *
     * Prevents session fixation attacks by generating a new session ID
     * when the user's authentication state changes from guest to logged-in.
     *
     * @since 2.0.0
     *
     * @param string   $user_login Username of the logged-in user.
     * @param \WP_User $user       WP_User object of the logged-in user.
     */
    public function regenerate_session_on_login( string $user_login, \WP_User $user ): void {
        // Regenerate PHP session ID if session is active.
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_regenerate_id( true );
        }

        // Generate new plugin session ID.
        $new_session_id = wp_generate_uuid4();

        // Update cookie with new session ID.
        if ( ! headers_sent() ) {
            setcookie(
                'ai_botkit_session',
                $new_session_id,
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        // Update instance property.
        $this->session_id = $new_session_id;

        /**
         * Fires after session ID is regenerated on login.
         *
         * @since 2.0.0
         *
         * @param string   $new_session_id The new session ID.
         * @param \WP_User $user           The logged-in user.
         */
        do_action( 'ai_botkit_session_regenerated_login', $new_session_id, $user );
    }

    /**
     * Regenerate session ID on user logout.
     *
     * Prevents session fixation attacks by generating a new session ID
     * when the user's authentication state changes from logged-in to guest.
     * Also clears any sensitive session data.
     *
     * @since 2.0.0
     */
    public function regenerate_session_on_logout(): void {
        // Clear browsing history from session on logout for privacy.
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            unset( $_SESSION['ai_botkit_browsing_history'] );
            session_regenerate_id( true );
        }

        // Generate new plugin session ID for the now-guest user.
        $new_session_id = wp_generate_uuid4();

        // Update cookie with new session ID.
        if ( ! headers_sent() ) {
            setcookie(
                'ai_botkit_session',
                $new_session_id,
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        // Update instance property.
        $this->session_id = $new_session_id;

        /**
         * Fires after session ID is regenerated on logout.
         *
         * @since 2.0.0
         *
         * @param string $new_session_id The new session ID.
         */
        do_action( 'ai_botkit_session_regenerated_logout', $new_session_id );
    }
}
