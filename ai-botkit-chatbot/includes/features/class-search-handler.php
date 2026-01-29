<?php
/**
 * Search Handler
 *
 * Provides full-text search functionality for chat history messages.
 * Handles user search (own conversations) and admin search (all conversations).
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-210 to FR-219 (Search Functionality Feature)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Core\Unified_Cache_Manager;

/**
 * Search_Handler class.
 *
 * Manages search operations including:
 * - Full-text search on message content using MySQL FULLTEXT
 * - User-scoped search (own conversations only)
 * - Admin global search (all conversations)
 * - Search result highlighting
 * - Relevance ranking with recency boost
 * - Search suggestions/autocomplete
 *
 * @since 2.0.0
 */
class Search_Handler {

    /**
     * Messages table name.
     *
     * @var string
     */
    private string $messages_table;

    /**
     * Conversations table name.
     *
     * @var string
     */
    private string $conversations_table;

    /**
     * Chatbots table name.
     *
     * @var string
     */
    private string $chatbots_table;

    /**
     * Cache manager instance.
     *
     * @var Unified_Cache_Manager|null
     */
    private ?Unified_Cache_Manager $cache_manager = null;

    /**
     * Default results per page.
     *
     * @var int
     */
    private int $per_page = 20;

    /**
     * Minimum query length.
     *
     * @var int
     */
    private const MIN_QUERY_LENGTH = 2;

    /**
     * Cache TTL for search results (5 minutes).
     *
     * @var int
     */
    private const CACHE_TTL = 300;

    /**
     * Recency weight factor for relevance scoring.
     *
     * @var float
     */
    private const RECENCY_WEIGHT = 0.1;

    /**
     * Constructor.
     *
     * Initializes table names and cache manager.
     *
     * @since 2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->messages_table      = $wpdb->prefix . 'ai_botkit_messages';
        $this->conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
        $this->chatbots_table      = $wpdb->prefix . 'ai_botkit_chatbots';
        $this->per_page            = (int) get_option( 'ai_botkit_search_per_page', 20 );

        // Initialize cache manager if available.
        if ( class_exists( '\AI_BotKit\Core\Unified_Cache_Manager' ) ) {
            $this->cache_manager = new Unified_Cache_Manager();
        }
    }

    /**
     * Search messages with filters.
     *
     * Performs full-text search on message content with optional filters.
     * For non-admin users, automatically filters to their own conversations.
     *
     * Implements: FR-211 (Full-Text Search on Messages)
     * Implements: FR-213 (User Personal Search)
     * Implements: FR-214 (Search Filters)
     *
     * @since 2.0.0
     *
     * @param string $query    Search query (minimum 2 characters).
     * @param array  $filters  {
     *     Optional. Search filters.
     *
     *     @type int      $user_id      Limit to user (required for non-admins).
     *     @type int      $chatbot_id   Filter by chatbot.
     *     @type string   $start_date   Filter by date range start (Y-m-d).
     *     @type string   $end_date     Filter by date range end (Y-m-d).
     *     @type string   $role         Filter by role (user|assistant).
     * }
     * @param int    $page     Page number (1-indexed).
     * @param int    $per_page Results per page.
     * @return array {
     *     Search results.
     *
     *     @type array  $results      Search results with highlights.
     *     @type int    $total        Total matching results.
     *     @type int    $pages        Total pages.
     *     @type int    $current_page Current page.
     *     @type float  $search_time  Query execution time in seconds.
     *     @type string $query        The search query.
     * }
     */
    public function search(
        string $query,
        array $filters = array(),
        int $page = 1,
        int $per_page = 0
    ): array {
        $start_time = microtime( true );

        // Validate query length.
        $query = trim( $query );
        if ( mb_strlen( $query ) < self::MIN_QUERY_LENGTH ) {
            return $this->empty_results( $query, $start_time );
        }

        // Validate and set per_page.
        if ( $per_page <= 0 ) {
            $per_page = $this->per_page;
        }
        $per_page = max( 1, min( 100, $per_page ) );
        $page     = max( 1, $page );

        // Check for user_id filter requirement.
        $current_user_id = get_current_user_id();
        if ( ! $this->can_search_all( $current_user_id ) ) {
            // Non-admins can only search their own conversations.
            $filters['user_id'] = $current_user_id;
        }

        // Check cache.
        $cache_key = $this->generate_cache_key( $query, $filters, $page, $per_page );
        if ( $this->cache_manager ) {
            $cached = $this->cache_manager->get( $cache_key, 'search' );
            if ( false !== $cached ) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        // Perform the search.
        $results = $this->execute_search( $query, $filters, $page, $per_page );

        // Calculate search time.
        $results['search_time'] = round( microtime( true ) - $start_time, 3 );
        $results['query']       = $query;
        $results['from_cache']  = false;

        // Cache the results.
        if ( $this->cache_manager ) {
            $this->cache_manager->set( $cache_key, $results, 'search', self::CACHE_TTL );
        }

        return $results;
    }

    /**
     * Search conversations (titles/previews).
     *
     * Searches for conversations based on their first message preview.
     *
     * Implements: FR-210 (Search Input Interface)
     *
     * @since 2.0.0
     *
     * @param string $query    Search query.
     * @param int    $user_id  User ID for scoping (0 for admin global search).
     * @param int    $limit    Maximum results.
     * @return array List of matching conversations.
     */
    public function search_conversations(
        string $query,
        int $user_id = 0,
        int $limit = 10
    ): array {
        global $wpdb;

        $query = trim( $query );
        if ( mb_strlen( $query ) < self::MIN_QUERY_LENGTH ) {
            return array();
        }

        $escaped_query = $this->escape_fulltext_query( $query );

        // Build base query.
        $sql = "SELECT DISTINCT
                    c.id,
                    c.chatbot_id,
                    c.user_id,
                    c.session_id,
                    c.created_at,
                    c.updated_at,
                    cb.name AS chatbot_name,
                    (SELECT content FROM {$this->messages_table}
                     WHERE conversation_id = c.id
                     AND role = 'user'
                     ORDER BY created_at ASC
                     LIMIT 1) AS preview
                FROM {$this->conversations_table} AS c
                INNER JOIN {$this->messages_table} AS m ON m.conversation_id = c.id
                LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
                WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                AND (c.is_archived = 0 OR c.is_archived IS NULL)";

        $params = array( $escaped_query );

        // Add user filter for non-admins.
        if ( $user_id > 0 ) {
            $sql     .= ' AND c.user_id = %d';
            $params[] = $user_id;
        }

        $sql     .= ' ORDER BY c.updated_at DESC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        return $results ?: array();
    }

    /**
     * Get search suggestions based on partial query.
     *
     * Provides autocomplete suggestions from recent messages.
     *
     * Implements: FR-210 (Search Input Interface - suggestions)
     *
     * @since 2.0.0
     *
     * @param string $partial_query Partial search query.
     * @param int    $user_id       User ID for scoping.
     * @param int    $limit         Maximum suggestions.
     * @return array List of suggested completions.
     */
    public function get_search_suggestions(
        string $partial_query,
        int $user_id,
        int $limit = 5
    ): array {
        global $wpdb;

        $partial_query = trim( $partial_query );
        if ( mb_strlen( $partial_query ) < self::MIN_QUERY_LENGTH ) {
            return array();
        }

        // Escape for LIKE query.
        $like_query = '%' . $wpdb->esc_like( $partial_query ) . '%';

        // Get distinct phrases/words from user's messages.
        $sql = "SELECT DISTINCT
                    SUBSTRING_INDEX(SUBSTRING_INDEX(m.content, ' ', 5), ' ', -5) AS phrase
                FROM {$this->messages_table} AS m
                INNER JOIN {$this->conversations_table} AS c ON m.conversation_id = c.id
                WHERE c.user_id = %d
                AND m.content LIKE %s
                AND m.role = 'user'
                ORDER BY m.created_at DESC
                LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_col( $wpdb->prepare( $sql, $user_id, $like_query, $limit * 2 ) );

        // Extract unique suggestions containing the query.
        $suggestions = array();
        $seen        = array();

        foreach ( $results as $phrase ) {
            $phrase = trim( $phrase );
            if ( empty( $phrase ) ) {
                continue;
            }

            // Extract words containing the partial query.
            $words = preg_split( '/\s+/', $phrase );
            foreach ( $words as $word ) {
                $word = strtolower( trim( $word ) );
                if ( mb_strlen( $word ) >= 3 && stripos( $word, $partial_query ) !== false ) {
                    if ( ! isset( $seen[ $word ] ) ) {
                        $suggestions[] = $word;
                        $seen[ $word ] = true;
                    }
                }
            }

            if ( count( $suggestions ) >= $limit ) {
                break;
            }
        }

        /**
         * Filters the search suggestions before returning.
         *
         * @since 2.0.0
         *
         * @param array  $suggestions   The suggestions array.
         * @param string $partial_query The partial search query.
         * @param int    $user_id       The user ID.
         */
        return apply_filters( 'ai_botkit_search_suggestions', array_slice( $suggestions, 0, $limit ), $partial_query, $user_id );
    }

    /**
     * Highlight search terms in content.
     *
     * Wraps matching terms in <mark> tags for visual highlighting.
     *
     * Implements: FR-216 (Search Term Highlighting)
     *
     * @since 2.0.0
     *
     * @param string $content The content to highlight.
     * @param string $query   The search query.
     * @return string HTML with highlighted matches.
     */
    public function highlight_matches( string $content, string $query ): string {
        if ( empty( $content ) || empty( $query ) ) {
            return esc_html( $content );
        }

        // Sanitize content first to prevent XSS.
        $content = wp_kses_post( $content );

        // Split query into individual terms.
        $terms = $this->extract_search_terms( $query );

        if ( empty( $terms ) ) {
            return $content;
        }

        // Build regex pattern for all terms (case-insensitive).
        $patterns = array();
        foreach ( $terms as $term ) {
            $escaped_term = preg_quote( $term, '/' );
            $patterns[]   = $escaped_term;
        }

        $pattern = '/(' . implode( '|', $patterns ) . ')/iu';

        // Replace matches with highlighted version.
        $highlighted = preg_replace_callback(
            $pattern,
            function ( $matches ) {
                return '<mark class="ai-botkit-highlight">' . esc_html( $matches[0] ) . '</mark>';
            },
            $content
        );

        return $highlighted ?: $content;
    }

    /**
     * Calculate relevance score for a search result.
     *
     * Combines MySQL FULLTEXT score with recency boost.
     *
     * Implements: FR-217 (Search Relevance Ranking)
     *
     * @since 2.0.0
     *
     * @param float  $fulltext_score MySQL FULLTEXT match score.
     * @param string $created_at     Message creation timestamp.
     * @return float Combined relevance score.
     */
    public function calculate_relevance( float $fulltext_score, string $created_at ): float {
        // Calculate recency factor (0 to 1, where 1 is today).
        $message_time = strtotime( $created_at );
        $now          = time();
        $days_old     = ( $now - $message_time ) / DAY_IN_SECONDS;

        // Decay factor: recent messages get boost, older messages get less.
        // Uses exponential decay with 30-day half-life.
        $recency_factor = exp( -0.023 * $days_old ); // ln(2)/30 = ~0.023

        // Combine fulltext score with recency boost.
        $relevance = $fulltext_score * ( 1 + ( self::RECENCY_WEIGHT * $recency_factor ) );

        /**
         * Filters the calculated relevance score.
         *
         * @since 2.0.0
         *
         * @param float  $relevance       The calculated relevance score.
         * @param float  $fulltext_score  The original FULLTEXT score.
         * @param string $created_at      The message creation timestamp.
         * @param float  $recency_factor  The calculated recency factor.
         */
        return apply_filters( 'ai_botkit_search_relevance', $relevance, $fulltext_score, $created_at, $recency_factor );
    }

    /**
     * Check if user can search all conversations.
     *
     * Admins and users with manage_ai_botkit capability can search globally.
     *
     * Implements: FR-212 (Admin Global Search)
     *
     * @since 2.0.0
     *
     * @param int $user_id User ID to check.
     * @return bool True if user can search all conversations.
     */
    public function can_search_all( int $user_id ): bool {
        if ( $user_id === 0 ) {
            return false;
        }

        // Check for admin capabilities.
        $can_search = user_can( $user_id, 'manage_options' ) ||
                      user_can( $user_id, 'manage_ai_botkit' ) ||
                      user_can( $user_id, 'search_ai_botkit_all' );

        /**
         * Filters whether a user can search all conversations.
         *
         * @since 2.0.0
         *
         * @param bool $can_search Whether the user can search all.
         * @param int  $user_id    The user ID.
         */
        return apply_filters( 'ai_botkit_can_search_all', $can_search, $user_id );
    }

    /**
     * Execute the actual search query.
     *
     * @since 2.0.0
     *
     * @param string $query    Search query.
     * @param array  $filters  Search filters.
     * @param int    $page     Page number.
     * @param int    $per_page Results per page.
     * @return array Search results.
     */
    private function execute_search(
        string $query,
        array $filters,
        int $page,
        int $per_page
    ): array {
        global $wpdb;

        $offset        = ( $page - 1 ) * $per_page;
        $escaped_query = $this->escape_fulltext_query( $query );

        // Build WHERE conditions.
        $where_conditions = array( 'MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)' );
        $where_params     = array( $escaped_query );

        // User filter (required for non-admins).
        if ( ! empty( $filters['user_id'] ) ) {
            $where_conditions[] = 'c.user_id = %d';
            $where_params[]     = (int) $filters['user_id'];
        }

        // Chatbot filter.
        if ( ! empty( $filters['chatbot_id'] ) ) {
            $where_conditions[] = 'c.chatbot_id = %d';
            $where_params[]     = (int) $filters['chatbot_id'];
        }

        // Date range filters.
        if ( ! empty( $filters['start_date'] ) ) {
            $where_conditions[] = 'm.created_at >= %s';
            $where_params[]     = sanitize_text_field( $filters['start_date'] ) . ' 00:00:00';
        }

        if ( ! empty( $filters['end_date'] ) ) {
            $where_conditions[] = 'm.created_at <= %s';
            $where_params[]     = sanitize_text_field( $filters['end_date'] ) . ' 23:59:59';
        }

        // Role filter (user/assistant).
        if ( ! empty( $filters['role'] ) && in_array( $filters['role'], array( 'user', 'assistant' ), true ) ) {
            $where_conditions[] = 'm.role = %s';
            $where_params[]     = $filters['role'];
        }

        // Exclude archived conversations by default.
        $where_conditions[] = '(c.is_archived = 0 OR c.is_archived IS NULL)';

        $where_clause = implode( ' AND ', $where_conditions );

        // Count total results.
        $count_sql    = "SELECT COUNT(DISTINCT m.id)
                        FROM {$this->messages_table} AS m
                        INNER JOIN {$this->conversations_table} AS c ON m.conversation_id = c.id
                        WHERE {$where_clause}";
        $count_params = $where_params;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_params ) );

        if ( $total === 0 ) {
            return array(
                'results'      => array(),
                'total'        => 0,
                'pages'        => 0,
                'current_page' => $page,
            );
        }

        // Get results with relevance score.
        $select_sql = "SELECT
                        m.id AS message_id,
                        m.conversation_id,
                        m.role,
                        m.content,
                        m.created_at,
                        m.metadata,
                        c.chatbot_id,
                        c.user_id,
                        cb.name AS chatbot_name,
                        u.display_name AS user_name,
                        MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance_score
                       FROM {$this->messages_table} AS m
                       INNER JOIN {$this->conversations_table} AS c ON m.conversation_id = c.id
                       LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
                       LEFT JOIN {$wpdb->users} AS u ON c.user_id = u.ID
                       WHERE {$where_clause}
                       ORDER BY relevance_score DESC, m.created_at DESC
                       LIMIT %d OFFSET %d";

        $select_params   = array_merge( array( $escaped_query ), $where_params, array( $per_page, $offset ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $raw_results = $wpdb->get_results( $wpdb->prepare( $select_sql, $select_params ), ARRAY_A );

        // Format results with highlighting.
        $formatted_results = array();
        foreach ( $raw_results as $row ) {
            $formatted_results[] = $this->format_search_result( $row, $query );
        }

        /**
         * Fires after a search is performed.
         *
         * @since 2.0.0
         *
         * @param string $query   The search query.
         * @param int    $total   Total results found.
         * @param int    $user_id The current user ID.
         */
        do_action( 'ai_botkit_search_performed', $query, $total, get_current_user_id() );

        return array(
            'results'      => $formatted_results,
            'total'        => $total,
            'pages'        => (int) ceil( $total / $per_page ),
            'current_page' => $page,
        );
    }

    /**
     * Format a single search result.
     *
     * Implements: FR-215 (Search Results Display)
     *
     * @since 2.0.0
     *
     * @param array  $row   Database row.
     * @param string $query Search query for highlighting.
     * @return array Formatted result.
     */
    private function format_search_result( array $row, string $query ): array {
        // Get excerpt with context around match.
        $excerpt = $this->get_search_excerpt( $row['content'], $query, 200 );

        // Calculate combined relevance with recency.
        $relevance = $this->calculate_relevance(
            (float) $row['relevance_score'],
            $row['created_at']
        );

        $result = array(
            'message_id'          => (int) $row['message_id'],
            'conversation_id'     => (int) $row['conversation_id'],
            'chatbot_id'          => (int) $row['chatbot_id'],
            'chatbot_name'        => $row['chatbot_name'] ?? __( 'Unknown Bot', 'knowvault' ),
            'role'                => $row['role'],
            'content'             => $excerpt,
            'content_highlighted' => $this->highlight_matches( $excerpt, $query ),
            'full_content'        => wp_kses_post( $row['content'] ),
            'created_at'          => $row['created_at'],
            'formatted_date'      => $this->format_date( $row['created_at'] ),
            'relevance_score'     => round( $relevance, 4 ),
            'metadata'            => json_decode( $row['metadata'] ?? '{}', true ),
        );

        // Add user info for admin searches.
        if ( ! empty( $row['user_id'] ) && current_user_can( 'manage_options' ) ) {
            $result['user_id']   = (int) $row['user_id'];
            $result['user_name'] = $row['user_name'] ?? __( 'Unknown User', 'knowvault' );
        }

        /**
         * Filters a search result before returning.
         *
         * @since 2.0.0
         *
         * @param array  $result   The formatted result.
         * @param array  $row      The raw database row.
         * @param string $query    The search query.
         */
        return apply_filters( 'ai_botkit_search_result', $result, $row, $query );
    }

    /**
     * Get excerpt with context around search match.
     *
     * @since 2.0.0
     *
     * @param string $content   Full content.
     * @param string $query     Search query.
     * @param int    $max_length Maximum excerpt length.
     * @return string Excerpt with context.
     */
    private function get_search_excerpt( string $content, string $query, int $max_length = 200 ): string {
        $content = wp_strip_all_tags( $content );
        $content = trim( $content );

        // If content is shorter than max length, return as-is.
        if ( mb_strlen( $content ) <= $max_length ) {
            return $content;
        }

        // Find the first occurrence of any search term.
        $terms    = $this->extract_search_terms( $query );
        $position = 0;

        foreach ( $terms as $term ) {
            $pos = mb_stripos( $content, $term );
            if ( $pos !== false ) {
                $position = $pos;
                break;
            }
        }

        // Calculate excerpt start position (center around match).
        $context_before = 50;
        $start          = max( 0, $position - $context_before );

        // Adjust to word boundary.
        if ( $start > 0 ) {
            $space_pos = mb_strpos( $content, ' ', $start );
            if ( $space_pos !== false && $space_pos < $position ) {
                $start = $space_pos + 1;
            }
        }

        // Extract excerpt.
        $excerpt = mb_substr( $content, $start, $max_length );

        // Adjust end to word boundary.
        $last_space = mb_strrpos( $excerpt, ' ' );
        if ( $last_space !== false && $last_space > $max_length - 30 ) {
            $excerpt = mb_substr( $excerpt, 0, $last_space );
        }

        // Add ellipsis.
        if ( $start > 0 ) {
            $excerpt = '...' . $excerpt;
        }
        if ( $start + mb_strlen( $excerpt ) < mb_strlen( $content ) ) {
            $excerpt .= '...';
        }

        return $excerpt;
    }

    /**
     * Extract individual search terms from query.
     *
     * @since 2.0.0
     *
     * @param string $query Search query.
     * @return array Array of search terms.
     */
    private function extract_search_terms( string $query ): array {
        // Handle quoted phrases.
        $terms = array();

        // Extract quoted phrases first.
        if ( preg_match_all( '/"([^"]+)"/', $query, $matches ) ) {
            $terms = $matches[1];
            $query = preg_replace( '/"[^"]+"/', '', $query );
        }

        // Split remaining query into individual words.
        $words = preg_split( '/\s+/', trim( $query ) );

        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( mb_strlen( $word ) >= self::MIN_QUERY_LENGTH ) {
                // Remove common operators.
                $word = preg_replace( '/^[+-~<>*]/', '', $word );
                if ( ! empty( $word ) ) {
                    $terms[] = $word;
                }
            }
        }

        return array_unique( array_filter( $terms ) );
    }

    /**
     * Escape query for FULLTEXT search.
     *
     * Prevents SQL injection and handles special characters.
     *
     * @since 2.0.0
     *
     * @param string $query Raw query.
     * @return string Escaped query.
     */
    private function escape_fulltext_query( string $query ): string {
        // Remove special FULLTEXT operators that could cause issues.
        // Note: We keep basic functionality, just sanitize malicious input.
        $query = sanitize_text_field( $query );

        // Escape characters that have special meaning in FULLTEXT boolean mode.
        // But since we use NATURAL LANGUAGE MODE, this is mainly for safety.
        $special_chars = array( '@', '(', ')', '<', '>', '~', '*', '"', '+', '-' );
        foreach ( $special_chars as $char ) {
            $query = str_replace( $char, ' ', $query );
        }

        // Collapse multiple spaces.
        $query = preg_replace( '/\s+/', ' ', $query );

        return trim( $query );
    }

    /**
     * Generate cache key for search results.
     *
     * @since 2.0.0
     *
     * @param string $query    Search query.
     * @param array  $filters  Search filters.
     * @param int    $page     Page number.
     * @param int    $per_page Results per page.
     * @return string Cache key.
     */
    private function generate_cache_key( string $query, array $filters, int $page, int $per_page ): string {
        $key_data = array(
            'q'        => strtolower( $query ),
            'filters'  => $filters,
            'page'     => $page,
            'per_page' => $per_page,
        );

        return 'search_' . md5( wp_json_encode( $key_data ) );
    }

    /**
     * Return empty results structure.
     *
     * @since 2.0.0
     *
     * @param string $query      Search query.
     * @param float  $start_time Start timestamp.
     * @return array Empty results.
     */
    private function empty_results( string $query, float $start_time ): array {
        return array(
            'results'      => array(),
            'total'        => 0,
            'pages'        => 0,
            'current_page' => 1,
            'search_time'  => round( microtime( true ) - $start_time, 3 ),
            'query'        => $query,
            'from_cache'   => false,
        );
    }

    /**
     * Format date for display.
     *
     * @since 2.0.0
     *
     * @param string $date MySQL datetime.
     * @return string Formatted date.
     */
    private function format_date( string $date ): string {
        $timestamp = strtotime( $date );
        $now       = current_time( 'timestamp' );
        $diff      = $now - $timestamp;

        if ( $diff < DAY_IN_SECONDS ) {
            return sprintf(
                /* translators: %s: time ago */
                __( '%s ago', 'knowvault' ),
                human_time_diff( $timestamp, $now )
            );
        }

        if ( $diff < WEEK_IN_SECONDS ) {
            return wp_date( 'l, g:i A', $timestamp );
        }

        return wp_date( 'M j, Y g:i A', $timestamp );
    }

    /**
     * Check if FULLTEXT index exists on messages table.
     *
     * @since 2.0.0
     *
     * @return bool True if index exists.
     */
    public function has_fulltext_index(): bool {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s
                 AND TABLE_NAME = %s
                 AND INDEX_TYPE = 'FULLTEXT'
                 AND COLUMN_NAME = 'content'",
                DB_NAME,
                $this->messages_table
            )
        );

        return $result !== null;
    }

    /**
     * Invalidate search cache when new messages are added.
     *
     * Should be called via hook when messages are created.
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID (optional, for targeted invalidation).
     * @return bool True on success.
     */
    public function invalidate_cache( int $conversation_id = 0 ): bool {
        if ( ! $this->cache_manager ) {
            return false;
        }

        // Invalidate the entire search cache group.
        // In production, you might want more targeted invalidation.
        return $this->cache_manager->invalidate_group( 'search' );
    }

    /**
     * Get search statistics.
     *
     * @since 2.0.0
     *
     * @return array Search statistics.
     */
    public function get_statistics(): array {
        global $wpdb;

        // Get total searchable messages.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_messages = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->messages_table}" );

        // Check index status.
        $has_index = $this->has_fulltext_index();

        return array(
            'total_messages'   => $total_messages,
            'fulltext_enabled' => $has_index,
            'min_query_length' => self::MIN_QUERY_LENGTH,
            'cache_ttl'        => self::CACHE_TTL,
            'per_page_default' => $this->per_page,
        );
    }
}
