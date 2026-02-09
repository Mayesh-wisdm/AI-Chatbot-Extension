<?php
/**
 * Conversation Model Class
 *
 * Represents a chat conversation with a chatbot.
 * Handles CRUD operations and message management.
 *
 * @package AI_BotKit\Models
 * @since   1.0.0
 *
 * Extended in Phase 2 for:
 * - FR-201 to FR-209: Chat History Feature
 */

namespace AI_BotKit\Models;

/**
 * Conversation Model Class.
 *
 * @since 1.0.0
 */
class Conversation {
    /**
     * Conversation ID.
     *
     * @var int|null
     */
    private $id;

    /**
     * Conversation data.
     *
     * @var array|null
     */
    private $data;

    /**
     * Conversations table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Messages table name.
     *
     * @var string
     */
    private $messages_table;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param int|string|null $id Conversation ID or session ID.
     */
    public function __construct( $id = null ) {
        global $wpdb;
        $this->table_name     = $wpdb->prefix . 'ai_botkit_conversations';
        $this->messages_table = $wpdb->prefix . 'ai_botkit_messages';

        if ( $id ) {
            $this->id = $id;
            $this->load();
        }
    }

    /**
     * Load conversation data
     */
    private function load() {
        global $wpdb;
        $this->data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d OR session_id = %s", $this->id, $this->id),
            ARRAY_A
        );

        if ( ! empty( $this->data ) && ! empty( $this->data['id'] ) ) {
            $this->id = $this->data['id'];
        } else {
            $this->id = null;
        }
    }

    /**
     * Create or update conversation
     */
    public function save($data) {
        global $wpdb;

        $args = array();

        $args['chatbot_id'] = absint($data['chatbot_id']);
        $args['user_id'] = get_current_user_id();
        $args['session_id'] = sanitize_text_field($data['session_id']);

        // Handle guest users by storing hashed IP address
        if (!is_user_logged_in()) {
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $ip_hash = hash('sha256', $user_ip);
            $args['guest_ip'] = $ip_hash;
        }

        if (!$this->id) {
            $args['created_at'] = current_time('mysql');
        }
        $args['updated_at'] = current_time('mysql');

        $format = array(
            '%d', // chatbot_id
            '%d', // user_id
            '%s', // session_id
            '%s', // updated_at
        );

        // Add guest_ip format if set
        if (!is_user_logged_in()) {
            $format[] = '%s'; // guest_ip
        }

        if (!$this->id) {
            $format[] = '%s'; // created_at
        }

        if ($this->id) {
            $wpdb->update(
                $this->table_name,
                $args,
                array('id' => $this->id),
                $format,
                array('%d')
            );
        } else {
            $wpdb->insert($this->table_name, $args, $format);
            $this->id = $wpdb->insert_id;
        }

        $this->load();
        return $this->id;
    }

    /**
     * Add message to conversation
     */
    public function add_message($data) {
        global $wpdb;

        $args = array();
        // Sanitize data
        $args['conversation_id'] = sanitize_text_field($data['conversation_id']);
        $args['role'] = sanitize_text_field($data['role']);
        $args['content'] = wp_kses_post($data['content']);
        $args['metadata'] = wp_json_encode(array(
            'tokens' => $data['tokens'],
            'model' => $data['model']
        ));
        $args['created_at'] = current_time('mysql');

        $format = array(
            '%d', // conversation_id
            '%s', // role
            '%s', // content
            '%s', // metadata
            '%s'  // created_at
        );

        $wpdb->insert($this->messages_table, $args, $format);
        
        // Update conversation's updated_at timestamp
        // $this->save(array('updated_at' => current_time('mysql')));
        
        return $wpdb->insert_id;
    }

    /**
     * Get messages from conversation
     */
    public function get_messages($limit = 5, $offset = 0) {
        global $wpdb;

        $sql = "SELECT * FROM {$this->messages_table} 
                WHERE conversation_id = %d 
                ORDER BY created_at ASC";
        
        if ($limit !== null) {
            $sql .= " LIMIT %d OFFSET %d";
            return $wpdb->get_results(
                $wpdb->prepare($sql, $this->id, $limit, $offset),
                ARRAY_A
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare($sql, $this->id),
            ARRAY_A
        );
    }

    /**
     * Get conversation data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get conversation ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get conversations by user
     */
    public static function get_by_user($user_id, $limit = 10, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';
        $messages_table = $wpdb->prefix . 'ai_botkit_messages';

        $sql = "SELECT c.*, 
                (SELECT content FROM {$messages_table} 
                WHERE conversation_id = c.id 
                AND role = 'user' 
                ORDER BY created_at ASC 
                LIMIT 1) AS first_message,
                (SELECT created_at FROM {$messages_table} 
                WHERE conversation_id = c.id 
                ORDER BY created_at DESC 
                LIMIT 1) AS last_activity
                FROM {$table_name} AS c
                WHERE c.user_id = %d
                ORDER BY c.created_at DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $user_id, $limit, $offset),
            ARRAY_A
        );
    }

    /**
     * Get conversations by chatbot
     */
    public static function get_by_chatbot($chatbot_id, $limit = 10, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';
        $messages_table = $wpdb->prefix . 'ai_botkit_messages';

        $sql = "SELECT c.*, 
                (SELECT content FROM {$messages_table} 
                WHERE conversation_id = c.id 
                AND role = 'user' 
                ORDER BY created_at ASC 
                LIMIT 1) AS first_message,
                (SELECT created_at FROM {$messages_table} 
                WHERE conversation_id = c.id 
                ORDER BY created_at DESC 
                LIMIT 1) AS last_activity
                FROM {$table_name} AS c
                WHERE c.chatbot_id = %d
                ORDER BY c.created_at DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $chatbot_id, $limit, $offset),
            ARRAY_A
        );
    }

    /**
     * Get conversation by session ID
     */
    public static function get_by_session_id($session_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_conversations';

        $sql = "SELECT * FROM {$table_name} WHERE session_id = %s LIMIT 1";
        $result = $wpdb->get_row($wpdb->prepare($sql, $session_id), ARRAY_A);

        if ($result) {
            $conversation = new self();
            $conversation->id = $result['id'];
            $conversation->data = $result;
            return $conversation;
        }

        return null;
    }

    /**
     * Delete conversation.
     *
     * @since 1.0.0
     *
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        // Delete messages first
        $wpdb->delete(
            $this->messages_table,
            array( 'conversation_id' => $this->id ),
            array( '%d' )
        );

        // Then delete conversation
        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $this->id ),
            array( '%d' )
        );

        if ( $result ) {
            $this->id   = null;
            $this->data = null;
            return true;
        }

        return false;
    }

    /**
     * Get conversation preview (first user message).
     *
     * Implements: FR-202 (Conversation Previews)
     *
     * @since 2.0.0
     *
     * @param int $max_length Maximum preview length.
     * @return string First user message truncated to max_length.
     */
    public function get_preview( int $max_length = 100 ): string {
        global $wpdb;

        if ( ! $this->id ) {
            return '';
        }

        $first_message = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT content FROM {$this->messages_table}
                 WHERE conversation_id = %d
                 AND role = 'user'
                 ORDER BY created_at ASC
                 LIMIT 1",
                $this->id
            )
        );

        if ( empty( $first_message ) ) {
            return '';
        }

        $text = wp_strip_all_tags( $first_message );
        $text = trim( $text );

        if ( mb_strlen( $text ) <= $max_length ) {
            return $text;
        }

        return mb_substr( $text, 0, $max_length ) . '...';
    }

    /**
     * Get paginated messages from conversation.
     *
     * Extended for Phase 2 to support pagination.
     *
     * @since 2.0.0
     *
     * @param int $limit  Number of messages to retrieve.
     * @param int $offset Starting offset.
     * @return array Messages array.
     */
    public function get_messages_paginated( int $limit = 10, int $offset = 0 ): array {
        global $wpdb;

        if ( ! $this->id ) {
            return array();
        }

        $sql = "SELECT * FROM {$this->messages_table}
                WHERE conversation_id = %d
                ORDER BY created_at ASC
                LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $this->id, $limit, $offset ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get total message count for conversation.
     *
     * @since 2.0.0
     *
     * @return int Total message count.
     */
    public function get_message_count(): int {
        global $wpdb;

        if ( ! $this->id ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->messages_table} WHERE conversation_id = %d",
                $this->id
            )
        );
    }

    /**
     * Check if conversation is favorited.
     *
     * Implements: FR-206 (Mark Favorite)
     *
     * @since 2.0.0
     *
     * @return bool True if favorited.
     */
    public function is_favorite(): bool {
        if ( ! $this->data ) {
            return false;
        }

        return (bool) ( $this->data['is_favorite'] ?? false );
    }

    /**
     * Set favorite status.
     *
     * @since 2.0.0
     *
     * @param bool $is_favorite Whether conversation should be favorited.
     * @return bool True on success.
     */
    public function set_favorite( bool $is_favorite ): bool {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            array(
                'is_favorite' => $is_favorite ? 1 : 0,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $this->id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            $this->data['is_favorite'] = $is_favorite ? 1 : 0;
            return true;
        }

        return false;
    }

    /**
     * Check if conversation is archived.
     *
     * Implements: FR-208 (Archive Conversation)
     *
     * @since 2.0.0
     *
     * @return bool True if archived.
     */
    public function is_archived(): bool {
        if ( ! $this->data ) {
            return false;
        }

        return (bool) ( $this->data['is_archived'] ?? false );
    }

    /**
     * Set archived status.
     *
     * @since 2.0.0
     *
     * @param bool $is_archived Whether conversation should be archived.
     * @return bool True on success.
     */
    public function set_archived( bool $is_archived ): bool {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            array(
                'is_archived' => $is_archived ? 1 : 0,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $this->id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            $this->data['is_archived'] = $is_archived ? 1 : 0;
            return true;
        }

        return false;
    }

    /**
     * Get last activity timestamp.
     *
     * @since 2.0.0
     *
     * @return string|null MySQL datetime or null.
     */
    public function get_last_activity(): ?string {
        global $wpdb;

        if ( ! $this->id ) {
            return null;
        }

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$this->messages_table}
                 WHERE conversation_id = %d
                 ORDER BY created_at DESC
                 LIMIT 1",
                $this->id
            )
        );
    }

    /**
     * Get the chatbot ID for this conversation.
     *
     * @since 2.0.0
     *
     * @return int|null Chatbot ID or null.
     */
    public function get_chatbot_id(): ?int {
        if ( ! $this->data ) {
            return null;
        }

        return isset( $this->data['chatbot_id'] ) ? (int) $this->data['chatbot_id'] : null;
    }

    /**
     * Get the user ID for this conversation.
     *
     * @since 2.0.0
     *
     * @return int|null User ID or null.
     */
    public function get_user_id(): ?int {
        if ( ! $this->data ) {
            return null;
        }

        return isset( $this->data['user_id'] ) ? (int) $this->data['user_id'] : null;
    }

    /**
     * Get the session ID for this conversation.
     *
     * @since 2.0.0
     *
     * @return string|null Session ID or null.
     */
    public function get_session_id(): ?string {
        if ( ! $this->data ) {
            return null;
        }

        return $this->data['session_id'] ?? null;
    }

    /**
     * Check if user owns this conversation.
     *
     * @since 2.0.0
     *
     * @param int $user_id User ID to check.
     * @return bool True if user owns conversation.
     */
    public function is_owner( int $user_id ): bool {
        return $this->get_user_id() === $user_id;
    }
} 