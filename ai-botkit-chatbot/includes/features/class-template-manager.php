<?php
/**
 * Template Manager Class
 *
 * Manages conversation templates including CRUD operations,
 * applying templates to chatbots, and import/export functionality.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-230 to FR-239 (Conversation Templates)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Models\Template;
use AI_BotKit\Models\Chatbot;
use WP_Error;

/**
 * Template_Manager class.
 *
 * Provides template management functionality.
 *
 * @since 2.0.0
 */
class Template_Manager {

    /**
     * Pre-built template definitions directory.
     *
     * @var string
     */
    private string $templates_dir;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->templates_dir = AI_BOTKIT_PLUGIN_DIR . 'includes/features/templates/';
    }

    /**
     * Get all templates with optional filtering.
     *
     * Implements: FR-231 (Admin Template List View)
     *
     * @since 2.0.0
     *
     * @param array $filters {
     *     Optional. Filter arguments.
     *
     *     @type string $category  Filter by category.
     *     @type bool   $is_system Filter system templates only.
     *     @type bool   $is_active Filter active templates only.
     * }
     * @return array List of template records.
     */
    public function get_templates( array $filters = array() ): array {
        return Template::get_all( $filters );
    }

    /**
     * Get a single template by ID.
     *
     * @since 2.0.0
     *
     * @param int $template_id Template ID.
     * @return array|null Template data or null.
     */
    public function get_template( int $template_id ): ?array {
        $template = new Template( $template_id );

        if ( ! $template->exists() ) {
            return null;
        }

        return $template->get_data();
    }

    /**
     * Create a new template.
     *
     * Implements: FR-232 (Template Builder/Editor)
     *
     * @since 2.0.0
     *
     * @param array $data {
     *     Template data.
     *
     *     @type string $name                  Template name (required).
     *     @type string $description           Template description.
     *     @type string $category              Template category.
     *     @type array  $style                 Style configuration (JSON).
     *     @type array  $messages_template     Message templates (JSON).
     *     @type array  $model_config          Model configuration (JSON).
     *     @type array  $conversation_starters Initial prompts.
     *     @type string $thumbnail             Template preview image URL.
     * }
     * @return int|WP_Error Template ID or error.
     */
    public function create_template( array $data ) {
        // Validate required fields.
        if ( empty( $data['name'] ) ) {
            return new WP_Error(
                'missing_name',
                __( 'Template name is required.', 'knowvault' )
            );
        }

        // Check for duplicate names.
        if ( Template::name_exists( $data['name'] ) ) {
            return new WP_Error(
                'duplicate_name',
                __( 'A template with this name already exists.', 'knowvault' )
            );
        }

        // Apply filter for customization.
        $data = apply_filters( 'ai_botkit_template_data', $data );

        // Create template.
        $template = new Template();
        $result   = $template->save( $data );

        if ( ! $result ) {
            return new WP_Error(
                'save_failed',
                __( 'Failed to create template.', 'knowvault' )
            );
        }

        /**
         * Fires after a template is created.
         *
         * @since 2.0.0
         *
         * @param int   $template_id   Template ID.
         * @param array $template_data Template data.
         */
        do_action( 'ai_botkit_template_created', $result, $data );

        return $result;
    }

    /**
     * Update an existing template.
     *
     * Implements: FR-232 (Template Builder/Editor)
     *
     * @since 2.0.0
     *
     * @param int   $template_id Template ID.
     * @param array $data        Data to update.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function update_template( int $template_id, array $data ) {
        $template = new Template( $template_id );

        if ( ! $template->exists() ) {
            return new WP_Error(
                'not_found',
                __( 'Template not found.', 'knowvault' )
            );
        }

        // Prevent editing system templates.
        if ( $template->is_system() ) {
            return new WP_Error(
                'system_template',
                __( 'System templates cannot be modified. Use "Save as Copy" instead.', 'knowvault' )
            );
        }

        // Check for duplicate names (excluding current template).
        if ( isset( $data['name'] ) && Template::name_exists( $data['name'], $template_id ) ) {
            return new WP_Error(
                'duplicate_name',
                __( 'A template with this name already exists.', 'knowvault' )
            );
        }

        // Apply filter for customization.
        $data = apply_filters( 'ai_botkit_template_data', $data );

        // Update template.
        $result = $template->save( $data );

        if ( ! $result ) {
            return new WP_Error(
                'update_failed',
                __( 'Failed to update template.', 'knowvault' )
            );
        }

        /**
         * Fires after a template is updated.
         *
         * @since 2.0.0
         *
         * @param int   $template_id Template ID.
         * @param array $changes     Changed data.
         */
        do_action( 'ai_botkit_template_updated', $template_id, $data );

        return true;
    }

    /**
     * Delete a template.
     *
     * @since 2.0.0
     *
     * @param int $template_id Template ID.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function delete_template( int $template_id ) {
        $template = new Template( $template_id );

        if ( ! $template->exists() ) {
            return new WP_Error(
                'not_found',
                __( 'Template not found.', 'knowvault' )
            );
        }

        // Prevent deleting system templates.
        if ( $template->is_system() ) {
            return new WP_Error(
                'system_template',
                __( 'System templates cannot be deleted.', 'knowvault' )
            );
        }

        if ( ! $template->delete() ) {
            return new WP_Error(
                'delete_failed',
                __( 'Failed to delete template.', 'knowvault' )
            );
        }

        /**
         * Fires after a template is deleted.
         *
         * @since 2.0.0
         *
         * @param int $template_id Template ID.
         */
        do_action( 'ai_botkit_template_deleted', $template_id );

        return true;
    }

    /**
     * Apply a template to a chatbot.
     *
     * Implements: FR-234 (Apply Template to Chatbot)
     *
     * @since 2.0.0
     *
     * @param int  $template_id Template ID.
     * @param int  $chatbot_id  Chatbot ID.
     * @param bool $merge       Merge with existing config (true) or replace (false).
     * @return bool|WP_Error True on success, error on failure.
     */
    public function apply_to_chatbot( int $template_id, int $chatbot_id, bool $merge = true ) {
        // Load template.
        $template = new Template( $template_id );

        if ( ! $template->exists() ) {
            return new WP_Error(
                'template_not_found',
                __( 'Template not found.', 'knowvault' )
            );
        }

        // Load chatbot.
        $chatbot = new Chatbot( $chatbot_id );

        if ( ! $chatbot->exists() ) {
            return new WP_Error(
                'chatbot_not_found',
                __( 'Chatbot not found.', 'knowvault' )
            );
        }

        $template_data = $template->get_data();
        $chatbot_data  = $chatbot->get_data();

        // Build configuration to apply.
        $config = array();

        // Map template fields to chatbot fields.
        if ( isset( $template_data['style'] ) ) {
            if ( $merge && ! empty( $chatbot_data['style'] ) ) {
                $existing_style = is_string( $chatbot_data['style'] )
                    ? json_decode( $chatbot_data['style'], true )
                    : $chatbot_data['style'];
                $config['style'] = array_merge( $existing_style ?: array(), $template_data['style'] );
            } else {
                $config['style'] = $template_data['style'];
            }
        }

        if ( isset( $template_data['messages_template'] ) ) {
            if ( $merge && ! empty( $chatbot_data['messages_template'] ) ) {
                $existing_messages = is_string( $chatbot_data['messages_template'] )
                    ? json_decode( $chatbot_data['messages_template'], true )
                    : $chatbot_data['messages_template'];
                $config['messages_template'] = array_merge( $existing_messages ?: array(), $template_data['messages_template'] );
            } else {
                $config['messages_template'] = $template_data['messages_template'];
            }
        }

        if ( isset( $template_data['model_config'] ) ) {
            if ( $merge && ! empty( $chatbot_data['model_config'] ) ) {
                $existing_model = is_string( $chatbot_data['model_config'] )
                    ? json_decode( $chatbot_data['model_config'], true )
                    : $chatbot_data['model_config'];
                $config['model_config'] = array_merge( $existing_model ?: array(), $template_data['model_config'] );
            } else {
                $config['model_config'] = $template_data['model_config'];
            }
        }

        // Apply filter for customization.
        $config = apply_filters( 'ai_botkit_apply_template', $config, $template_data, $chatbot_data );

        // JSON encode the config arrays.
        foreach ( array( 'style', 'messages_template', 'model_config' ) as $key ) {
            if ( isset( $config[ $key ] ) && is_array( $config[ $key ] ) ) {
                $config[ $key ] = wp_json_encode( $config[ $key ] );
            }
        }

        // Update chatbot in database.
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'ai_botkit_chatbots',
            $config,
            array( 'id' => $chatbot_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            return new WP_Error(
                'apply_failed',
                __( 'Failed to apply template to chatbot.', 'knowvault' )
            );
        }

        // Increment template usage count.
        $template->increment_usage();

        /**
         * Fires after a template is applied to a chatbot.
         *
         * @since 2.0.0
         *
         * @param int $template_id Template ID.
         * @param int $chatbot_id  Chatbot ID.
         */
        do_action( 'ai_botkit_template_applied', $template_id, $chatbot_id );

        return true;
    }

    /**
     * Export template as JSON.
     *
     * Implements: FR-239 (Template Import/Export)
     *
     * @since 2.0.0
     *
     * @param int $template_id Template ID.
     * @return string|WP_Error JSON string or error.
     */
    public function export_template( int $template_id ) {
        $template = new Template( $template_id );

        if ( ! $template->exists() ) {
            return new WP_Error(
                'not_found',
                __( 'Template not found.', 'knowvault' )
            );
        }

        $export_data = $template->to_export_array();

        return wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    /**
     * Import template from JSON.
     *
     * Implements: FR-239 (Template Import/Export)
     *
     * @since 2.0.0
     *
     * @param string $json_data    JSON data.
     * @param string $conflict_mode How to handle name conflicts: 'replace', 'copy', 'error'.
     * @return int|WP_Error Template ID or error.
     */
    public function import_template( string $json_data, string $conflict_mode = 'error' ) {
        // Parse JSON.
        $data = json_decode( $json_data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'invalid_json',
                __( 'Invalid JSON format.', 'knowvault' )
            );
        }

        // Validate required fields.
        if ( empty( $data['name'] ) ) {
            return new WP_Error(
                'missing_name',
                __( 'Template name is required.', 'knowvault' )
            );
        }

        // Check for name conflicts.
        $existing = Template::get_by_name( $data['name'] );

        if ( $existing ) {
            switch ( $conflict_mode ) {
                case 'replace':
                    // Delete existing and create new.
                    if ( $existing->is_system() ) {
                        return new WP_Error(
                            'system_template',
                            __( 'Cannot replace system templates.', 'knowvault' )
                        );
                    }
                    $existing->delete();
                    break;

                case 'copy':
                    // Generate unique name.
                    $counter  = 1;
                    $new_name = $data['name'] . ' (Copy)';
                    while ( Template::name_exists( $new_name ) ) {
                        $counter++;
                        $new_name = $data['name'] . ' (Copy ' . $counter . ')';
                    }
                    $data['name'] = $new_name;
                    break;

                case 'error':
                default:
                    return new WP_Error(
                        'duplicate_name',
                        __( 'A template with this name already exists.', 'knowvault' )
                    );
            }
        }

        // Remove export metadata.
        unset( $data['export_version'], $data['exported_at'] );

        // Ensure not marked as system template.
        $data['is_system'] = 0;

        // Create template.
        return $this->create_template( $data );
    }

    /**
     * Install pre-built system templates.
     *
     * Implements: FR-235, FR-236, FR-237, FR-238 (Pre-built Templates)
     *
     * @since 2.0.0
     *
     * @return int Number of templates installed.
     */
    public function install_system_templates(): int {
        $installed = 0;
        $templates = $this->get_prebuilt_templates();

        foreach ( $templates as $template_data ) {
            // Check if already exists.
            if ( Template::get_by_name( $template_data['name'] ) ) {
                continue;
            }

            // Mark as system template.
            $template_data['is_system'] = 1;
            $template_data['is_active'] = 1;

            $template = new Template();
            $result   = $template->save( $template_data );

            if ( $result ) {
                $installed++;
            }
        }

        return $installed;
    }

    /**
     * Get pre-built template definitions.
     *
     * @since 2.0.0
     *
     * @return array Pre-built templates.
     */
    private function get_prebuilt_templates(): array {
        $templates = array();

        // Load templates from JSON files.
        $template_files = array(
            'faq-bot.json',
            'customer-support.json',
            'product-advisor.json',
            'lead-capture.json',
        );

        foreach ( $template_files as $file ) {
            $file_path = $this->templates_dir . $file;

            if ( file_exists( $file_path ) ) {
                $content = file_get_contents( $file_path );
                $data    = json_decode( $content, true );

                if ( $data && json_last_error() === JSON_ERROR_NONE ) {
                    $templates[] = $data;
                }
            }
        }

        return $templates;
    }

    /**
     * Create a template from an existing chatbot.
     *
     * @since 2.0.0
     *
     * @param int    $chatbot_id    Chatbot ID.
     * @param string $template_name Name for the new template.
     * @return int|WP_Error Template ID or error.
     */
    public function create_from_chatbot( int $chatbot_id, string $template_name ) {
        // Load chatbot.
        $chatbot = new Chatbot( $chatbot_id );

        if ( ! $chatbot->exists() ) {
            return new WP_Error(
                'chatbot_not_found',
                __( 'Chatbot not found.', 'knowvault' )
            );
        }

        $chatbot_data = $chatbot->get_data();

        // Build template data from chatbot.
        $template_data = array(
            'name'        => sanitize_text_field( $template_name ),
            'description' => sprintf(
                /* translators: %s: chatbot name */
                __( 'Template created from chatbot: %s', 'knowvault' ),
                $chatbot_data['name'] ?? 'Unknown'
            ),
            'category'    => 'general',
        );

        // Extract style.
        if ( ! empty( $chatbot_data['style'] ) ) {
            $template_data['style'] = is_string( $chatbot_data['style'] )
                ? json_decode( $chatbot_data['style'], true )
                : $chatbot_data['style'];
        }

        // Extract messages template.
        if ( ! empty( $chatbot_data['messages_template'] ) ) {
            $template_data['messages_template'] = is_string( $chatbot_data['messages_template'] )
                ? json_decode( $chatbot_data['messages_template'], true )
                : $chatbot_data['messages_template'];
        }

        // Extract model config.
        if ( ! empty( $chatbot_data['model_config'] ) ) {
            $template_data['model_config'] = is_string( $chatbot_data['model_config'] )
                ? json_decode( $chatbot_data['model_config'], true )
                : $chatbot_data['model_config'];
        }

        return $this->create_template( $template_data );
    }

    /**
     * Get template categories.
     *
     * @since 2.0.0
     *
     * @return array Categories with labels.
     */
    public static function get_categories(): array {
        return array(
            'support'   => __( 'Support', 'knowvault' ),
            'sales'     => __( 'Sales', 'knowvault' ),
            'marketing' => __( 'Marketing', 'knowvault' ),
            'education' => __( 'Education', 'knowvault' ),
            'general'   => __( 'General', 'knowvault' ),
        );
    }
}
