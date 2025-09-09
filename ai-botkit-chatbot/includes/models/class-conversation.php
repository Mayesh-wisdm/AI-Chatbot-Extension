<?php

namespace AI_BotKit\Models;
/**
 * Conversation Model Class
 */
class Conversation {
    private $id;
    private $data;
    private $table_name;
    private $messages_table;

    /**
     * Constructor
     */
    public function __construct($id = null) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_botkit_conversations';
        $this->messages_table = $wpdb->prefix . 'ai_botkit_messages';
        
        if ($id) {
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
     * Delete conversation
     */
    public function delete() {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        // Delete messages first
        $wpdb->delete(
            $this->messages_table,
            array('conversation_id' => $this->id),
            array('%d')
        );

        // Then delete conversation
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $this->id),
            array('%d')
        );

        if ($result) {
            $this->id = null;
            $this->data = null;
            return true;
        }

        return false;
    }
} 