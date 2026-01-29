<?php
/**
 * Template Model Class
 *
 * Represents a conversation template entity with CRUD operations.
 * Templates store reusable chatbot configurations that can be applied
 * to create new chatbots or update existing ones.
 *
 * @package AI_BotKit\Models
 * @since   2.0.0
 *
 * Implements: FR-230 (Template Data Model)
 */

namespace AI_BotKit\Models;

/**
 * Template class.
 *
 * Provides CRUD operations for conversation templates.
 *
 * @since 2.0.0
 */
class Template {

    /**
     * Template ID.
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Template data.
     *
     * @var array|null
     */
    private ?array $data = null;

    /**
     * Database table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Valid template categories.
     *
     * @var array
     */
    public const CATEGORIES = array(
        'support',
        'sales',
        'marketing',
        'education',
        'general',
    );

    /**
     * Constructor.
     *
     * @since 2.0.0
     *
     * @param int|null $id Optional. Template ID to load.
     */
    public function __construct( ?int $id = null ) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_botkit_templates';

        if ( $id ) {
            $this->id = $id;
            $this->load();
        }
    }

    /**
     * Load template data from database.
     *
     * @since 2.0.0
     */
    private function load(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $this->id
            ),
            ARRAY_A
        );

        // Decode JSON columns.
        if ( $this->data ) {
            $json_columns = array( 'style', 'messages_template', 'model_config', 'conversation_starters' );
            foreach ( $json_columns as $column ) {
                if ( isset( $this->data[ $column ] ) && is_string( $this->data[ $column ] ) ) {
                    $this->data[ $column ] = json_decode( $this->data[ $column ], true );
                }
            }
        }
    }

    /**
     * Check if template exists.
     *
     * @since 2.0.0
     *
     * @return bool True if template exists.
     */
    public function exists(): bool {
        return $this->id !== null && $this->data !== null;
    }

    /**
     * Check if this is a system template.
     *
     * @since 2.0.0
     *
     * @return bool True if system template.
     */
    public function is_system(): bool {
        return $this->exists() && ! empty( $this->data['is_system'] );
    }

    /**
     * Get template ID.
     *
     * @since 2.0.0
     *
     * @return int|null Template ID.
     */
    public function get_id(): ?int {
        return $this->id;
    }

    /**
     * Get template data.
     *
     * @since 2.0.0
     *
     * @return array|null Template data.
     */
    public function get_data(): ?array {
        return $this->data;
    }

    /**
     * Get a specific property.
     *
     * @since 2.0.0
     *
     * @param string $key     Property key.
     * @param mixed  $default Default value.
     * @return mixed Property value or default.
     */
    public function get( string $key, $default = null ) {
        if ( ! $this->data ) {
            return $default;
        }
        return $this->data[ $key ] ?? $default;
    }

    /**
     * Save template (create or update).
     *
     * @since 2.0.0
     *
     * @param array $data Template data.
     * @return int|false Template ID on success, false on failure.
     */
    public function save( array $data ) {
        global $wpdb;

        // Validate required fields.
        if ( empty( $data['name'] ) ) {
            return false;
        }

        // Validate category.
        $data['category'] = $data['category'] ?? 'general';
        if ( ! in_array( $data['category'], self::CATEGORIES, true ) ) {
            $data['category'] = 'general';
        }

        // Sanitize text fields.
        $data['name']        = sanitize_text_field( $data['name'] );
        $data['description'] = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $data['category']    = sanitize_key( $data['category'] );
        $data['thumbnail']   = isset( $data['thumbnail'] ) ? esc_url_raw( $data['thumbnail'] ) : '';

        // Handle boolean/integer fields.
        $data['is_system'] = isset( $data['is_system'] ) ? absint( $data['is_system'] ) : 0;
        $data['is_active'] = isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1;

        // Encode JSON columns.
        $json_columns = array( 'style', 'messages_template', 'model_config', 'conversation_starters' );
        foreach ( $json_columns as $column ) {
            if ( isset( $data[ $column ] ) && is_array( $data[ $column ] ) ) {
                $data[ $column ] = wp_json_encode( $data[ $column ] );
            } elseif ( ! isset( $data[ $column ] ) ) {
                $data[ $column ] = null;
            }
        }

        // Prepare data for database.
        $db_data = array(
            'name'                  => $data['name'],
            'description'           => $data['description'],
            'category'              => $data['category'],
            'style'                 => $data['style'],
            'messages_template'     => $data['messages_template'],
            'model_config'          => $data['model_config'],
            'conversation_starters' => $data['conversation_starters'],
            'thumbnail'             => $data['thumbnail'],
            'is_system'             => $data['is_system'],
            'is_active'             => $data['is_active'],
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

        if ( $this->id ) {
            // Prevent editing system templates.
            if ( $this->is_system() ) {
                return false;
            }

            // Update existing template.
            $db_data['updated_at'] = current_time( 'mysql' );
            $format[]              = '%s';

            $result = $wpdb->update(
                $this->table_name,
                $db_data,
                array( 'id' => $this->id ),
                $format,
                array( '%d' )
            );

            if ( $result === false ) {
                return false;
            }
        } else {
            // Create new template.
            $db_data['created_by'] = get_current_user_id();
            $db_data['created_at'] = current_time( 'mysql' );
            $db_data['updated_at'] = current_time( 'mysql' );
            $format[]              = '%d';
            $format[]              = '%s';
            $format[]              = '%s';

            $result = $wpdb->insert( $this->table_name, $db_data, $format );

            if ( $result === false ) {
                return false;
            }

            $this->id = $wpdb->insert_id;
        }

        // Reload data.
        $this->load();

        return $this->id;
    }

    /**
     * Delete template.
     *
     * @since 2.0.0
     *
     * @return bool True on success.
     */
    public function delete(): bool {
        if ( ! $this->id ) {
            return false;
        }

        // Prevent deleting system templates.
        if ( $this->is_system() ) {
            return false;
        }

        global $wpdb;

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
     * Increment usage count.
     *
     * @since 2.0.0
     *
     * @return bool True on success.
     */
    public function increment_usage(): bool {
        if ( ! $this->id ) {
            return false;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET usage_count = usage_count + 1 WHERE id = %d",
                $this->id
            )
        );

        if ( $result !== false ) {
            $this->load();
            return true;
        }

        return false;
    }

    /**
     * Get all templates.
     *
     * @since 2.0.0
     *
     * @param array $args {
     *     Optional. Query arguments.
     *
     *     @type string $category  Filter by category.
     *     @type bool   $is_system Filter system templates only.
     *     @type bool   $is_active Filter active templates only.
     *     @type string $orderby   Order by column. Default 'name'.
     *     @type string $order     Sort order. Default 'ASC'.
     * }
     * @return array Array of templates.
     */
    public static function get_all( array $args = array() ): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_botkit_templates';
        $where      = array();
        $values     = array();

        // Build WHERE clause.
        if ( isset( $args['category'] ) && in_array( $args['category'], self::CATEGORIES, true ) ) {
            $where[]  = 'category = %s';
            $values[] = $args['category'];
        }

        if ( isset( $args['is_system'] ) ) {
            $where[]  = 'is_system = %d';
            $values[] = (int) $args['is_system'];
        }

        if ( isset( $args['is_active'] ) ) {
            $where[]  = 'is_active = %d';
            $values[] = (int) $args['is_active'];
        }

        // Build ORDER BY clause.
        $valid_orderby = array( 'name', 'category', 'usage_count', 'created_at', 'updated_at' );
        $orderby       = isset( $args['orderby'] ) && in_array( $args['orderby'], $valid_orderby, true )
            ? $args['orderby']
            : 'name';
        $order         = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        // Build query.
        $sql = "SELECT * FROM {$table_name}";

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $sql .= " ORDER BY {$orderby} {$order}";

        // Execute query.
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results( $sql, ARRAY_A );
        }

        // Decode JSON columns.
        $json_columns = array( 'style', 'messages_template', 'model_config', 'conversation_starters' );
        foreach ( $results as &$row ) {
            foreach ( $json_columns as $column ) {
                if ( isset( $row[ $column ] ) && is_string( $row[ $column ] ) ) {
                    $row[ $column ] = json_decode( $row[ $column ], true );
                }
            }
        }

        return $results ?: array();
    }

    /**
     * Get template by name.
     *
     * @since 2.0.0
     *
     * @param string $name Template name.
     * @return Template|null Template instance or null.
     */
    public static function get_by_name( string $name ): ?Template {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_botkit_templates';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE name = %s",
                $name
            )
        );

        if ( $id ) {
            return new self( (int) $id );
        }

        return null;
    }

    /**
     * Check if template name exists.
     *
     * @since 2.0.0
     *
     * @param string   $name        Template name.
     * @param int|null $exclude_id  Optional. Template ID to exclude from check.
     * @return bool True if name exists.
     */
    public static function name_exists( string $name, ?int $exclude_id = null ): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_botkit_templates';

        if ( $exclude_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE name = %s AND id != %d",
                    $name,
                    $exclude_id
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE name = %s",
                    $name
                )
            );
        }

        return (int) $count > 0;
    }

    /**
     * Convert template to array for JSON export.
     *
     * @since 2.0.0
     *
     * @return array Template data for export.
     */
    public function to_export_array(): array {
        if ( ! $this->data ) {
            return array();
        }

        return array(
            'name'                  => $this->data['name'],
            'description'           => $this->data['description'],
            'category'              => $this->data['category'],
            'style'                 => $this->data['style'],
            'messages_template'     => $this->data['messages_template'],
            'model_config'          => $this->data['model_config'],
            'conversation_starters' => $this->data['conversation_starters'],
            'thumbnail'             => $this->data['thumbnail'],
            'export_version'        => '1.0',
            'exported_at'           => current_time( 'c' ),
        );
    }
}
