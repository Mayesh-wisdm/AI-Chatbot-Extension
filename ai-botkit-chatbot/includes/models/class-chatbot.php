<?php
namespace AI_BotKit\Models;
/**
 * Chatbot Model Class
 */
class Chatbot {
    private $id;
    private $data;
    private $table_name;

    /**
     * Constructor
     */
    public function __construct($id = null) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_botkit_chatbots';
        
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }

    /**
     * Load chatbot data
     */
    private function load() {
        global $wpdb;
        $this->data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $this->id),
            ARRAY_A
        );
    }

    /**
     * Get all chatbots
     */
    public static function get_all() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';
        return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY name ASC", ARRAY_A);
    }

    /**
     * Get active chatbots
     */
    public static function get_active() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';
        return $wpdb->get_results("SELECT * FROM {$table_name} WHERE active = 1 ORDER BY name ASC", ARRAY_A);
    }

    /**
     * Check if chatbot exists
     */
    public function exists() {
        return $this->id !== null && $this->data !== null;
    }

    /**
     * Create or update chatbot
     */
    public function save($data) {
        global $wpdb;

        $data = wp_parse_args($data, array(
            'name' => '',
            'location' => 'bottom-right',
            'model' => 'gpt-4-turbo',
            'personality' => '',
            'greeting' => '',
            'fallback' => '',
            'language' => 'en_US',
            'active' => 0
        ));

        // Sanitize data
        $data = array_map('sanitize_text_field', $data);
        $data['personality'] = wp_kses_post($data['personality']);
        $data['greeting'] = wp_kses_post($data['greeting']);
        $data['fallback'] = wp_kses_post($data['fallback']);
        $data['active'] = absint($data['active']);

        // Format specifiers for data types
        $format = array(
            '%s', // name
            '%s', // location
            '%s', // model
            '%s', // personality
            '%s', // greeting
            '%s', // fallback
            '%s', // language
            '%d'  // active
        );

        if ($this->id) {
            // Update existing chatbot
            $wpdb->update(
                $this->table_name,
                $data,
                array('id' => $this->id),
                $format,
                array('%d')
            );
        } else {
            // Create new chatbot
            $wpdb->insert($this->table_name, $data, $format);
            $this->id = $wpdb->insert_id;
        }

        // Reload data
        $this->load();
        return $this->id;
    }

    /**
     * Delete chatbot
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => $this->id),
            array('%d')
        );
    }

    /**
     * Get chatbot data
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get chatbot ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get analytics for chatbot
     */
    public function get_analytics($start_date = null, $end_date = null) {

        if (empty($start_date)) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }
        if (empty($end_date)) {
            $end_date = gmdate('Y-m-d');
        }

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_botkit_analytics';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) as count, DATE(created_at) as date 
                FROM {$analytics_table} 
                WHERE chatbot_id = %d AND created_at >= %s AND created_at <= %s
                GROUP BY event_type, DATE(created_at)
                ORDER BY date ASC",
                $this->id,
                $start_date,
                $end_date
            ),
            ARRAY_A
        );
    }

    /**
     * Log analytics event
     */
    public function log_event($event_type, $event_data = null) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_botkit_analytics';

        return $wpdb->insert(
            $analytics_table,
            array(
                'chatbot_id' => $this->id,
                'event_type' => $event_type,
                'event_data' => $event_data ? wp_json_encode($event_data) : null
            ),
            array('%d', '%s', '%s')
        );
    }

    /**
     * Associate content with the chatbot
     * 
     * @param string $content_type Type of content (document, post, etc.)
     * @param int $content_id Content ID
     * @param array $metadata Optional metadata about the relationship
     * @return bool Success status
     */
    public function add_content($content_type, $content_id, $metadata = null) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'ai_botkit_content_relationships',
            array(
                'source_type' => 'chatbot',
                'source_id' => $this->id,
                'target_type' => $content_type,
                'target_id' => $content_id,
                'relationship_type' => 'knowledge_base',
                'metadata' => $metadata ? wp_json_encode($metadata) : null
            ),
            array('%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Remove content association from the chatbot
     * 
     * @param string $content_type Type of content
     * @param int $content_id Content ID
     * @return bool Success status
     */
    public function remove_content($content_type, $content_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_content_relationships',
            array(
                'source_type' => 'chatbot',
                'source_id' => $this->id,
                'target_type' => $content_type,
                'target_id' => $content_id,
                'relationship_type' => 'knowledge_base'
            ),
            array('%s', '%d', '%s', '%d', '%s')
        );
    }

    /**
     * Get all content associated with this chatbot
     * 
     * @param string $content_type Optional content type filter
     * @return array Associated content
     */
    public function get_associated_content($content_type = null) {
        global $wpdb;
        
        $query = "SELECT cr.*, 
                    CASE 
                        WHEN cr.target_type = 'document' THEN d.title
                        WHEN cr.target_type = 'post' THEN p.post_title
                    END as content_title
                 FROM {$wpdb->prefix}ai_botkit_content_relationships cr
                 LEFT JOIN {$wpdb->prefix}ai_botkit_documents d 
                    ON cr.target_type = 'document' AND cr.target_id = d.id
                 LEFT JOIN {$wpdb->posts} p 
                    ON cr.target_type = 'post' AND cr.target_id = p.ID
                 WHERE cr.source_type = 'chatbot' 
                 AND cr.source_id = %d
                 AND cr.relationship_type = 'knowledge_base'";
        
        $params = array($this->id);
        
        if ($content_type) {
            $query .= " AND cr.target_type = %s";
            $params[] = $content_type;
        }
        
        $query .= " ORDER BY cr.created_at DESC";
        
        return $wpdb->get_results(
            $wpdb->prepare($query, $params), // @codingStandardsIgnoreLine need for proper formatting
            ARRAY_A
        );
    }

    /**
     * Check if content is associated with this chatbot
     * 
     * @param string $content_type Type of content
     * @param int $content_id Content ID
     * @return bool Is associated
     */
    public function has_content($content_type, $content_id) {
        global $wpdb;
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}ai_botkit_content_relationships 
             WHERE source_type = 'chatbot'
             AND source_id = %d
             AND target_type = %s
             AND target_id = %d
             AND relationship_type = 'knowledge_base'",
            $this->id,
            $content_type,
            $content_id
        ));
    }
} 
