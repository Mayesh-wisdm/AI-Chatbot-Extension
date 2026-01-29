<?php
/**
 * Chat History Handler
 *
 * Provides chat history management functionality for logged-in users.
 * Handles listing, switching, filtering, and managing conversations.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-201 to FR-209 (Chat History Feature)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Models\Conversation;

/**
 * Chat_History_Handler class.
 *
 * Manages chat history operations including:
 * - Listing user conversations with previews
 * - Switching between conversations
 * - Filtering by date range
 * - Marking favorites
 * - Deleting conversations
 *
 * @since 2.0.0
 */
class Chat_History_Handler {

    /**
     * Conversations table name.
     *
     * @var string
     */
    private string $conversations_table;

    /**
     * Messages table name.
     *
     * @var string
     */
    private string $messages_table;

    /**
     * Chatbots table name.
     *
     * @var string
     */
    private string $chatbots_table;

    /**
     * Default items per page.
     *
     * @var int
     */
    private int $per_page = 10;

    /**
     * Constructor.
     *
     * Initializes table names.
     *
     * @since 2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
        $this->messages_table      = $wpdb->prefix . 'ai_botkit_messages';
        $this->chatbots_table      = $wpdb->prefix . 'ai_botkit_chatbots';
        $this->per_page            = (int) get_option( 'ai_botkit_history_per_page', 10 );
    }

    /**
     * Get paginated conversation list for a user.
     *
     * Retrieves conversations with previews, message counts, and metadata.
     * Only returns non-archived conversations by default.
     *
     * Implements: FR-201 (View Chat History)
     *
     * @since 2.0.0
     *
     * @param int      $user_id    WordPress user ID.
     * @param int|null $chatbot_id Optional chatbot filter.
     * @param int      $page       Page number (1-indexed).
     * @param int      $per_page   Items per page (default from settings).
     * @param bool     $include_archived Whether to include archived conversations.
     * @return array {
     *     Paginated conversation list.
     *
     *     @type array $conversations  List of conversation summaries.
     *     @type int   $total          Total conversation count.
     *     @type int   $pages          Total page count.
     *     @type int   $current_page   Current page number.
     * }
     */
    public function get_user_conversations(
        int $user_id,
        ?int $chatbot_id = null,
        int $page = 1,
        int $per_page = 0,
        bool $include_archived = false
    ): array {
        global $wpdb;

        if ( $per_page <= 0 ) {
            $per_page = $this->per_page;
        }

        // Ensure minimum values
        $page     = max( 1, $page );
        $per_page = max( 1, min( 100, $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Build WHERE clause
        $where_conditions = array( 'c.user_id = %d' );
        $where_params     = array( $user_id );

        if ( ! $include_archived ) {
            $where_conditions[] = '(c.is_archived = 0 OR c.is_archived IS NULL)';
        }

        if ( $chatbot_id !== null ) {
            $where_conditions[] = 'c.chatbot_id = %d';
            $where_params[]     = $chatbot_id;
        }

        $where_clause = implode( ' AND ', $where_conditions );

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->conversations_table} AS c WHERE {$where_clause}",
            $where_params
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $count_sql );

        // Get conversations with preview data
        $params = array_merge( $where_params, array( $per_page, $offset ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT
                c.id,
                c.chatbot_id,
                c.session_id,
                c.user_id,
                c.is_favorite,
                c.is_archived,
                c.created_at,
                c.updated_at,
                cb.name AS chatbot_name,
                cb.avatar AS chatbot_avatar,
                (SELECT content FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 AND role = 'user'
                 ORDER BY created_at ASC
                 LIMIT 1) AS first_user_message,
                (SELECT content FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 ORDER BY created_at DESC
                 LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM {$this->messages_table}
                 WHERE conversation_id = c.id) AS message_count,
                (SELECT created_at FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 ORDER BY created_at DESC
                 LIMIT 1) AS last_activity
            FROM {$this->conversations_table} AS c
            LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
            WHERE {$where_clause}
            ORDER BY c.updated_at DESC
            LIMIT %d OFFSET %d",
            $params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql, ARRAY_A );

        // Format conversation data
        $conversations = array();
        foreach ( $results as $row ) {
            $conversations[] = $this->format_conversation_item( $row );
        }

        return array(
            'conversations' => $conversations,
            'total'         => $total,
            'pages'         => (int) ceil( $total / $per_page ),
            'current_page'  => $page,
        );
    }

    /**
     * Get conversation preview (first message + metadata).
     *
     * Implements: FR-202 (Conversation Previews)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @return array {
     *     Conversation preview data.
     *
     *     @type string $preview        First 100 chars of first message.
     *     @type int    $message_count  Total messages in conversation.
     *     @type string $last_activity  Last message timestamp.
     *     @type string $chatbot_name   Associated chatbot name.
     *     @type bool   $is_favorite    Whether conversation is favorited.
     * }
     */
    public function get_conversation_preview( int $conversation_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT
                c.id,
                c.chatbot_id,
                c.is_favorite,
                c.created_at,
                c.updated_at,
                cb.name AS chatbot_name,
                (SELECT content FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 AND role = 'user'
                 ORDER BY created_at ASC
                 LIMIT 1) AS first_user_message,
                (SELECT COUNT(*) FROM {$this->messages_table}
                 WHERE conversation_id = c.id) AS message_count,
                (SELECT created_at FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 ORDER BY created_at DESC
                 LIMIT 1) AS last_activity
            FROM {$this->conversations_table} AS c
            LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
            WHERE c.id = %d",
            $conversation_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $sql, ARRAY_A );

        if ( ! $row ) {
            return array();
        }

        return array(
            'preview'       => $this->truncate_preview( $row['first_user_message'] ?? '' ),
            'message_count' => (int) $row['message_count'],
            'last_activity' => $row['last_activity'],
            'chatbot_name'  => $row['chatbot_name'] ?? __( 'Unknown Bot', 'knowvault' ),
            'is_favorite'   => (bool) $row['is_favorite'],
            'created_at'    => $row['created_at'],
        );
    }

    /**
     * Get messages from a conversation with pagination.
     *
     * Retrieves messages with optional pagination support.
     *
     * Implements: FR-203 (Resume Conversation)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @param int $page            Page number (1-indexed).
     * @param int $per_page        Messages per page (0 = all).
     * @return array|WP_Error Conversation data or error if not found/unauthorized.
     */
    public function get_conversation_messages(
        int $conversation_id,
        int $user_id,
        int $page = 1,
        int $per_page = 0
    ) {
        global $wpdb;

        // Verify ownership
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, cb.name AS chatbot_name, cb.avatar AS chatbot_avatar
                 FROM {$this->conversations_table} AS c
                 LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
                 WHERE c.id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'unauthorized',
                __( 'You do not have permission to access this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        // Build messages query
        if ( $per_page > 0 ) {
            $page   = max( 1, $page );
            $offset = ( $page - 1 ) * $per_page;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->messages_table}
                     WHERE conversation_id = %d
                     ORDER BY created_at ASC
                     LIMIT %d OFFSET %d",
                    $conversation_id,
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->messages_table} WHERE conversation_id = %d",
                    $conversation_id
                )
            );
        } else {
            // Get all messages
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->messages_table}
                     WHERE conversation_id = %d
                     ORDER BY created_at ASC",
                    $conversation_id
                ),
                ARRAY_A
            );
            $total = count( $messages );
        }

        // Format messages
        $formatted_messages = array();
        foreach ( $messages as $message ) {
            $formatted_messages[] = array(
                'id'         => (int) $message['id'],
                'role'       => $message['role'],
                'content'    => $message['content'],
                'metadata'   => json_decode( $message['metadata'], true ),
                'created_at' => $message['created_at'],
                'timestamp'  => strtotime( $message['created_at'] ),
            );
        }

        return array(
            'conversation_id' => $conversation_id,
            'session_id'      => $conversation['session_id'],
            'chatbot_id'      => (int) $conversation['chatbot_id'],
            'chatbot_name'    => $conversation['chatbot_name'] ?? __( 'Unknown Bot', 'knowvault' ),
            'chatbot_avatar'  => $conversation['chatbot_avatar'] ?? '',
            'is_favorite'     => (bool) ( $conversation['is_favorite'] ?? false ),
            'messages'        => $formatted_messages,
            'total_messages'  => $total,
            'created_at'      => $conversation['created_at'],
            'updated_at'      => $conversation['updated_at'],
        );
    }

    /**
     * Switch to a previous conversation.
     *
     * Loads a conversation and updates the last accessed timestamp.
     *
     * Implements: FR-204 (Conversation Switching)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @return array|WP_Error Conversation data or error.
     */
    public function switch_conversation( int $conversation_id, int $user_id ) {
        global $wpdb;

        // Get conversation with messages
        $result = $this->get_conversation_messages( $conversation_id, $user_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Update the accessed timestamp
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            $this->conversations_table,
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $conversation_id ),
            array( '%s' ),
            array( '%d' )
        );

        /**
         * Fires after a conversation is resumed.
         *
         * @since 2.0.0
         *
         * @param int $conversation_id Conversation ID.
         * @param int $user_id         User ID.
         */
        do_action( 'ai_botkit_conversation_resumed', $conversation_id, $user_id );

        return $result;
    }

    /**
     * Delete a conversation.
     *
     * Permanently deletes a conversation and all its messages.
     *
     * Implements: FR-205 (Delete Conversation)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_conversation( int $conversation_id, int $user_id ) {
        global $wpdb;

        // Verify ownership
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'unauthorized',
                __( 'You do not have permission to delete this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        // Delete messages first
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete(
            $this->messages_table,
            array( 'conversation_id' => $conversation_id ),
            array( '%d' )
        );

        // Delete conversation
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->delete(
            $this->conversations_table,
            array( 'id' => $conversation_id ),
            array( '%d' )
        );

        if ( $result ) {
            /**
             * Fires after a conversation is deleted.
             *
             * @since 2.0.0
             *
             * @param int $conversation_id Conversation ID.
             * @param int $user_id         User ID.
             */
            do_action( 'ai_botkit_conversation_deleted', $conversation_id, $user_id );

            return true;
        }

        return new \WP_Error(
            'delete_failed',
            __( 'Failed to delete conversation.', 'knowvault' ),
            array( 'status' => 500 )
        );
    }

    /**
     * Toggle favorite status for a conversation.
     *
     * Implements: FR-206 (Mark Favorite)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @return array|WP_Error Updated status or error.
     */
    public function toggle_favorite( int $conversation_id, int $user_id ) {
        global $wpdb;

        // Verify ownership
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'unauthorized',
                __( 'You do not have permission to modify this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        // Toggle the favorite status
        $current_status = (bool) ( $conversation['is_favorite'] ?? false );
        $new_status     = ! $current_status;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->update(
            $this->conversations_table,
            array(
                'is_favorite' => $new_status ? 1 : 0,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            /**
             * Fires after a conversation's favorite status is toggled.
             *
             * @since 2.0.0
             *
             * @param int  $conversation_id Conversation ID.
             * @param int  $user_id         User ID.
             * @param bool $is_favorite     New favorite status.
             */
            do_action( 'ai_botkit_conversation_favorite_toggled', $conversation_id, $user_id, $new_status );

            return array(
                'conversation_id' => $conversation_id,
                'is_favorite'     => $new_status,
            );
        }

        return new \WP_Error(
            'update_failed',
            __( 'Failed to update favorite status.', 'knowvault' ),
            array( 'status' => 500 )
        );
    }

    /**
     * Filter conversations by date range.
     *
     * Implements: FR-207 (Filter by Date)
     *
     * @since 2.0.0
     *
     * @param int         $user_id    WordPress user ID.
     * @param string      $start_date Start date (Y-m-d format).
     * @param string      $end_date   End date (Y-m-d format).
     * @param int|null    $chatbot_id Optional chatbot filter.
     * @param bool|null   $is_favorite Optional favorite filter.
     * @param int         $page       Page number.
     * @param int         $per_page   Items per page.
     * @return array Filtered conversation list.
     */
    public function filter_conversations(
        int $user_id,
        string $start_date = '',
        string $end_date = '',
        ?int $chatbot_id = null,
        ?bool $is_favorite = null,
        int $page = 1,
        int $per_page = 0
    ): array {
        global $wpdb;

        if ( $per_page <= 0 ) {
            $per_page = $this->per_page;
        }

        $page     = max( 1, $page );
        $per_page = max( 1, min( 100, $per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Build WHERE clause
        $where_conditions = array( 'c.user_id = %d' );
        $where_params     = array( $user_id );

        // Add archived filter (default: exclude archived)
        $where_conditions[] = '(c.is_archived = 0 OR c.is_archived IS NULL)';

        if ( ! empty( $start_date ) ) {
            $where_conditions[] = 'c.created_at >= %s';
            $where_params[]     = $start_date . ' 00:00:00';
        }

        if ( ! empty( $end_date ) ) {
            $where_conditions[] = 'c.created_at <= %s';
            $where_params[]     = $end_date . ' 23:59:59';
        }

        if ( $chatbot_id !== null ) {
            $where_conditions[] = 'c.chatbot_id = %d';
            $where_params[]     = $chatbot_id;
        }

        if ( $is_favorite !== null ) {
            $where_conditions[] = 'c.is_favorite = %d';
            $where_params[]     = $is_favorite ? 1 : 0;
        }

        $where_clause = implode( ' AND ', $where_conditions );

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->conversations_table} AS c WHERE {$where_clause}",
            $where_params
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $count_sql );

        // Get filtered conversations
        $params = array_merge( $where_params, array( $per_page, $offset ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT
                c.id,
                c.chatbot_id,
                c.session_id,
                c.user_id,
                c.is_favorite,
                c.is_archived,
                c.created_at,
                c.updated_at,
                cb.name AS chatbot_name,
                cb.avatar AS chatbot_avatar,
                (SELECT content FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 AND role = 'user'
                 ORDER BY created_at ASC
                 LIMIT 1) AS first_user_message,
                (SELECT COUNT(*) FROM {$this->messages_table}
                 WHERE conversation_id = c.id) AS message_count,
                (SELECT created_at FROM {$this->messages_table}
                 WHERE conversation_id = c.id
                 ORDER BY created_at DESC
                 LIMIT 1) AS last_activity
            FROM {$this->conversations_table} AS c
            LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
            WHERE {$where_clause}
            ORDER BY c.updated_at DESC
            LIMIT %d OFFSET %d",
            $params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results( $sql, ARRAY_A );

        // Format conversation data
        $conversations = array();
        foreach ( $results as $row ) {
            $conversations[] = $this->format_conversation_item( $row );
        }

        return array(
            'conversations' => $conversations,
            'total'         => $total,
            'pages'         => (int) ceil( $total / $per_page ),
            'current_page'  => $page,
            'filters'       => array(
                'start_date'  => $start_date,
                'end_date'    => $end_date,
                'chatbot_id'  => $chatbot_id,
                'is_favorite' => $is_favorite,
            ),
        );
    }

    /**
     * Archive a conversation (soft delete).
     *
     * Implements: FR-208 (Archive Conversation)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function archive_conversation( int $conversation_id, int $user_id ) {
        global $wpdb;

        // Verify ownership
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'unauthorized',
                __( 'You do not have permission to archive this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->update(
            $this->conversations_table,
            array(
                'is_archived' => 1,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            /**
             * Fires after a conversation is archived.
             *
             * @since 2.0.0
             *
             * @param int $conversation_id Conversation ID.
             * @param int $user_id         User ID.
             */
            do_action( 'ai_botkit_conversation_archived', $conversation_id, $user_id );

            return true;
        }

        return new \WP_Error(
            'archive_failed',
            __( 'Failed to archive conversation.', 'knowvault' ),
            array( 'status' => 500 )
        );
    }

    /**
     * Unarchive a conversation.
     *
     * Implements: FR-209 (Restore Archived)
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @param int $user_id         User ID for ownership verification.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function unarchive_conversation( int $conversation_id, int $user_id ) {
        global $wpdb;

        // Verify ownership
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE id = %d",
                $conversation_id
            ),
            ARRAY_A
        );

        if ( ! $conversation ) {
            return new \WP_Error(
                'not_found',
                __( 'Conversation not found.', 'knowvault' ),
                array( 'status' => 404 )
            );
        }

        if ( (int) $conversation['user_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'unauthorized',
                __( 'You do not have permission to unarchive this conversation.', 'knowvault' ),
                array( 'status' => 403 )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->update(
            $this->conversations_table,
            array(
                'is_archived' => 0,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            /**
             * Fires after a conversation is unarchived.
             *
             * @since 2.0.0
             *
             * @param int $conversation_id Conversation ID.
             * @param int $user_id         User ID.
             */
            do_action( 'ai_botkit_conversation_unarchived', $conversation_id, $user_id );

            return true;
        }

        return new \WP_Error(
            'unarchive_failed',
            __( 'Failed to unarchive conversation.', 'knowvault' ),
            array( 'status' => 500 )
        );
    }

    /**
     * Get total conversation count for a user.
     *
     * @since 2.0.0
     *
     * @param int  $user_id          User ID.
     * @param bool $include_archived Include archived conversations.
     * @return int Total count.
     */
    public function get_user_conversation_count( int $user_id, bool $include_archived = false ): int {
        global $wpdb;

        $archived_condition = $include_archived ? '' : 'AND (is_archived = 0 OR is_archived IS NULL)';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->conversations_table}
                 WHERE user_id = %d {$archived_condition}",
                $user_id
            )
        );
    }

    /**
     * Format a conversation row into standardized output.
     *
     * @since 2.0.0
     *
     * @param array $row Database row.
     * @return array Formatted conversation item.
     */
    private function format_conversation_item( array $row ): array {
        $preview = $this->truncate_preview( $row['first_user_message'] ?? '' );

        // Generate a title from the first message or use default
        $title = $this->generate_conversation_title( $row['first_user_message'] ?? '' );

        return array(
            'id'             => (int) $row['id'],
            'chatbot_id'     => (int) $row['chatbot_id'],
            'session_id'     => $row['session_id'],
            'chatbot_name'   => $row['chatbot_name'] ?? __( 'Unknown Bot', 'knowvault' ),
            'chatbot_avatar' => $row['chatbot_avatar'] ?? '',
            'title'          => $title,
            'preview'        => $preview,
            'message_count'  => (int) ( $row['message_count'] ?? 0 ),
            'is_favorite'    => (bool) ( $row['is_favorite'] ?? false ),
            'is_archived'    => (bool) ( $row['is_archived'] ?? false ),
            'created_at'     => $row['created_at'],
            'updated_at'     => $row['updated_at'],
            'last_activity'  => $row['last_activity'] ?? $row['updated_at'],
            'formatted_date' => $this->format_relative_date( $row['last_activity'] ?? $row['updated_at'] ),
        );
    }

    /**
     * Truncate text for preview display.
     *
     * @since 2.0.0
     *
     * @param string $text Text to truncate.
     * @param int    $length Maximum length.
     * @return string Truncated text.
     */
    private function truncate_preview( string $text, int $length = 100 ): string {
        $text = wp_strip_all_tags( $text );
        $text = trim( $text );

        if ( mb_strlen( $text ) <= $length ) {
            return $text;
        }

        return mb_substr( $text, 0, $length ) . '...';
    }

    /**
     * Generate a conversation title from the first message.
     *
     * @since 2.0.0
     *
     * @param string $first_message First user message.
     * @return string Generated title.
     */
    private function generate_conversation_title( string $first_message ): string {
        if ( empty( $first_message ) ) {
            return __( 'New Conversation', 'knowvault' );
        }

        $text = wp_strip_all_tags( $first_message );
        $text = trim( $text );

        // Take first 50 characters for title
        if ( mb_strlen( $text ) > 50 ) {
            $text = mb_substr( $text, 0, 47 ) . '...';
        }

        return $text;
    }

    /**
     * Format date as relative time.
     *
     * @since 2.0.0
     *
     * @param string $date MySQL datetime string.
     * @return string Formatted relative date.
     */
    private function format_relative_date( string $date ): string {
        if ( empty( $date ) ) {
            return '';
        }

        $timestamp = strtotime( $date );
        $now       = current_time( 'timestamp' );
        $diff      = $now - $timestamp;

        if ( $diff < 60 ) {
            return __( 'Just now', 'knowvault' );
        }

        if ( $diff < 3600 ) {
            $minutes = floor( $diff / 60 );
            /* translators: %d: number of minutes */
            return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'knowvault' ), $minutes );
        }

        if ( $diff < 86400 ) {
            $hours = floor( $diff / 3600 );
            /* translators: %d: number of hours */
            return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'knowvault' ), $hours );
        }

        // Check if it's today
        if ( gmdate( 'Y-m-d', $timestamp ) === gmdate( 'Y-m-d', $now ) ) {
            return __( 'Today', 'knowvault' ) . ' ' . gmdate( 'g:i A', $timestamp );
        }

        // Check if it's yesterday
        if ( gmdate( 'Y-m-d', $timestamp ) === gmdate( 'Y-m-d', $now - 86400 ) ) {
            return __( 'Yesterday', 'knowvault' ) . ' ' . gmdate( 'g:i A', $timestamp );
        }

        // Within last 7 days
        if ( $diff < 604800 ) {
            return gmdate( 'l', $timestamp ) . ' ' . gmdate( 'g:i A', $timestamp );
        }

        // More than a week ago
        return gmdate( 'M j, Y', $timestamp );
    }
}
