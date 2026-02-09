<?php
/**
 * Template AJAX Handler Class
 *
 * Handles all AJAX requests for conversation templates including
 * CRUD operations, apply to chatbot, and import/export.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-230 to FR-239 (Conversation Templates)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Models\Template;

/**
 * Template_Ajax_Handler class.
 *
 * Registers and handles AJAX endpoints for templates.
 *
 * @since 2.0.0
 */
class Template_Ajax_Handler {

    /**
     * Template manager instance.
     *
     * @var Template_Manager
     */
    private Template_Manager $template_manager;

    /**
     * Constructor.
     *
     * Registers all AJAX hooks for template operations.
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->template_manager = new Template_Manager();

        // Template management AJAX handlers.
        add_action( 'wp_ajax_ai_botkit_get_template', array( $this, 'handle_get_template' ) );
        add_action( 'wp_ajax_ai_botkit_list_templates', array( $this, 'handle_list_templates' ) );
        add_action( 'wp_ajax_ai_botkit_save_template', array( $this, 'handle_save_template' ) );
        add_action( 'wp_ajax_ai_botkit_delete_template', array( $this, 'handle_delete_template' ) );
        add_action( 'wp_ajax_ai_botkit_apply_template', array( $this, 'handle_apply_template' ) );
        add_action( 'wp_ajax_ai_botkit_export_template', array( $this, 'handle_export_template' ) );
        add_action( 'wp_ajax_ai_botkit_import_template', array( $this, 'handle_import_template' ) );
        add_action( 'wp_ajax_ai_botkit_get_template_categories', array( $this, 'handle_get_categories' ) );
        add_action( 'wp_ajax_ai_botkit_duplicate_template', array( $this, 'handle_duplicate_template' ) );
    }

    /**
     * Verify AJAX request has valid nonce and admin permission.
     *
     * @since 2.0.0
     *
     * @return bool True if verified, sends error and exits otherwise.
     */
    private function verify_request(): bool {
        check_ajax_referer( 'ai_botkit_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Insufficient permissions.', 'knowvault' ) )
            );
            return false;
        }

        return true;
    }

    /**
     * Handle get template AJAX request.
     *
     * @since 2.0.0
     */
    public function handle_get_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Template ID is required.', 'knowvault' ) )
            );
        }

        $template = $this->template_manager->get_template( $template_id );

        if ( ! $template ) {
            wp_send_json_error(
                array( 'message' => __( 'Template not found.', 'knowvault' ) )
            );
        }

        wp_send_json_success(
            array(
                'template' => $template,
            )
        );
    }

    /**
     * Handle list templates AJAX request.
     *
     * Implements: FR-231 (Admin Template List View)
     *
     * @since 2.0.0
     */
    public function handle_list_templates(): void {
        $this->verify_request();

        // Build filters from request.
        $filters = array();

        if ( isset( $_POST['category'] ) && ! empty( $_POST['category'] ) ) {
            $filters['category'] = sanitize_key( $_POST['category'] );
        }

        if ( isset( $_POST['is_system'] ) && $_POST['is_system'] !== '' ) {
            $filters['is_system'] = (bool) $_POST['is_system'];
        }

        if ( isset( $_POST['is_active'] ) && $_POST['is_active'] !== '' ) {
            $filters['is_active'] = (bool) $_POST['is_active'];
        }

        if ( isset( $_POST['orderby'] ) ) {
            $filters['orderby'] = sanitize_key( $_POST['orderby'] );
        }

        if ( isset( $_POST['order'] ) ) {
            $filters['order'] = sanitize_key( $_POST['order'] );
        }

        $templates = $this->template_manager->get_templates( $filters );

        wp_send_json_success(
            array(
                'templates'  => $templates,
                'total'      => count( $templates ),
                'categories' => Template_Manager::get_categories(),
            )
        );
    }

    /**
     * Handle save template AJAX request.
     *
     * Implements: FR-232 (Template Builder/Editor)
     *
     * @since 2.0.0
     */
    public function handle_save_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        // Build template data from request.
        $data = array();

        // Required fields.
        if ( isset( $_POST['name'] ) ) {
            $data['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        }

        // Optional text fields.
        if ( isset( $_POST['description'] ) ) {
            $data['description'] = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );
        }

        if ( isset( $_POST['category'] ) ) {
            $data['category'] = sanitize_key( $_POST['category'] );
        }

        if ( isset( $_POST['thumbnail'] ) ) {
            $data['thumbnail'] = esc_url_raw( $_POST['thumbnail'] );
        }

        // JSON fields.
        if ( isset( $_POST['style'] ) ) {
            $data['style'] = is_array( $_POST['style'] )
                ? $this->sanitize_json_array( $_POST['style'] )
                : json_decode( wp_unslash( $_POST['style'] ), true );
        }

        if ( isset( $_POST['messages_template'] ) ) {
            $data['messages_template'] = is_array( $_POST['messages_template'] )
                ? $this->sanitize_json_array( $_POST['messages_template'] )
                : json_decode( wp_unslash( $_POST['messages_template'] ), true );
        }

        if ( isset( $_POST['model_config'] ) ) {
            $data['model_config'] = is_array( $_POST['model_config'] )
                ? $this->sanitize_json_array( $_POST['model_config'] )
                : json_decode( wp_unslash( $_POST['model_config'] ), true );
        }

        if ( isset( $_POST['conversation_starters'] ) ) {
            $data['conversation_starters'] = is_array( $_POST['conversation_starters'] )
                ? $this->sanitize_json_array( $_POST['conversation_starters'] )
                : json_decode( wp_unslash( $_POST['conversation_starters'] ), true );
        }

        // Boolean fields.
        if ( isset( $_POST['is_active'] ) ) {
            $data['is_active'] = (int) $_POST['is_active'];
        }

        // Perform create or update.
        if ( $template_id ) {
            $result = $this->template_manager->update_template( $template_id, $data );
        } else {
            $result = $this->template_manager->create_template( $data );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        $saved_id       = $template_id ?: $result;
        $saved_template = $this->template_manager->get_template( $saved_id );

        wp_send_json_success(
            array(
                'message'     => $template_id
                    ? __( 'Template updated successfully.', 'knowvault' )
                    : __( 'Template created successfully.', 'knowvault' ),
                'template_id' => $saved_id,
                'template'    => $saved_template,
            )
        );
    }

    /**
     * Handle delete template AJAX request.
     *
     * @since 2.0.0
     */
    public function handle_delete_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Template ID is required.', 'knowvault' ) )
            );
        }

        $result = $this->template_manager->delete_template( $template_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        wp_send_json_success(
            array( 'message' => __( 'Template deleted successfully.', 'knowvault' ) )
        );
    }

    /**
     * Handle apply template to chatbot AJAX request.
     *
     * Implements: FR-234 (Apply Template to Chatbot)
     *
     * @since 2.0.0
     */
    public function handle_apply_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $chatbot_id  = isset( $_POST['chatbot_id'] ) ? absint( $_POST['chatbot_id'] ) : 0;
        $merge       = isset( $_POST['merge'] ) ? (bool) $_POST['merge'] : true;

        if ( ! $template_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Template ID is required.', 'knowvault' ) )
            );
        }

        if ( ! $chatbot_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Chatbot ID is required.', 'knowvault' ) )
            );
        }

        $result = $this->template_manager->apply_to_chatbot( $template_id, $chatbot_id, $merge );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Template applied successfully.', 'knowvault' ),
            )
        );
    }

    /**
     * Handle export template AJAX request.
     *
     * Implements: FR-239 (Template Import/Export)
     *
     * @since 2.0.0
     */
    public function handle_export_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Template ID is required.', 'knowvault' ) )
            );
        }

        $json = $this->template_manager->export_template( $template_id );

        if ( is_wp_error( $json ) ) {
            wp_send_json_error(
                array( 'message' => $json->get_error_message() )
            );
        }

        // Get template name for filename.
        $template = $this->template_manager->get_template( $template_id );
        $filename = sanitize_file_name( $template['name'] ?? 'template' ) . '-export.json';

        wp_send_json_success(
            array(
                'json'     => $json,
                'filename' => $filename,
            )
        );
    }

    /**
     * Handle import template AJAX request.
     *
     * Implements: FR-239 (Template Import/Export)
     *
     * @since 2.0.0
     */
    public function handle_import_template(): void {
        $this->verify_request();

        // Check for file upload.
        if ( isset( $_FILES['template_file'] ) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK ) {
            $json_data = file_get_contents( $_FILES['template_file']['tmp_name'] );
        } elseif ( isset( $_POST['json_data'] ) ) {
            $json_data = wp_unslash( $_POST['json_data'] );
        } else {
            wp_send_json_error(
                array( 'message' => __( 'No template data provided.', 'knowvault' ) )
            );
            return;
        }

        $conflict_mode = isset( $_POST['conflict_mode'] )
            ? sanitize_key( $_POST['conflict_mode'] )
            : 'error';

        $result = $this->template_manager->import_template( $json_data, $conflict_mode );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        $imported_template = $this->template_manager->get_template( $result );

        wp_send_json_success(
            array(
                'message'     => __( 'Template imported successfully.', 'knowvault' ),
                'template_id' => $result,
                'template'    => $imported_template,
            )
        );
    }

    /**
     * Handle get categories AJAX request.
     *
     * @since 2.0.0
     */
    public function handle_get_categories(): void {
        $this->verify_request();

        wp_send_json_success(
            array(
                'categories' => Template_Manager::get_categories(),
            )
        );
    }

    /**
     * Handle duplicate template AJAX request.
     *
     * @since 2.0.0
     */
    public function handle_duplicate_template(): void {
        $this->verify_request();

        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

        if ( ! $template_id ) {
            wp_send_json_error(
                array( 'message' => __( 'Template ID is required.', 'knowvault' ) )
            );
        }

        // Get original template.
        $original = $this->template_manager->get_template( $template_id );

        if ( ! $original ) {
            wp_send_json_error(
                array( 'message' => __( 'Template not found.', 'knowvault' ) )
            );
        }

        // Generate unique name.
        $counter  = 1;
        $new_name = $original['name'] . ' (Copy)';
        while ( Template::name_exists( $new_name ) ) {
            $counter++;
            $new_name = $original['name'] . ' (Copy ' . $counter . ')';
        }

        // Create duplicate.
        $new_data = array(
            'name'                  => $new_name,
            'description'           => $original['description'],
            'category'              => $original['category'],
            'style'                 => $original['style'],
            'messages_template'     => $original['messages_template'],
            'model_config'          => $original['model_config'],
            'conversation_starters' => $original['conversation_starters'],
            'thumbnail'             => $original['thumbnail'],
            'is_system'             => 0, // Duplicates are never system templates.
            'is_active'             => 1,
        );

        $result = $this->template_manager->create_template( $new_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array( 'message' => $result->get_error_message() )
            );
        }

        $new_template = $this->template_manager->get_template( $result );

        wp_send_json_success(
            array(
                'message'     => __( 'Template duplicated successfully.', 'knowvault' ),
                'template_id' => $result,
                'template'    => $new_template,
            )
        );
    }

    /**
     * Recursively sanitize array for JSON storage.
     *
     * @since 2.0.0
     *
     * @param mixed $data Data to sanitize.
     * @return mixed Sanitized data.
     */
    private function sanitize_json_array( $data ) {
        if ( is_array( $data ) ) {
            $sanitized = array();
            foreach ( $data as $key => $value ) {
                $sanitized[ sanitize_key( $key ) ] = $this->sanitize_json_array( $value );
            }
            return $sanitized;
        }

        if ( is_bool( $data ) ) {
            return $data;
        }

        if ( is_int( $data ) || is_float( $data ) ) {
            return $data;
        }

        if ( is_string( $data ) ) {
            // Allow HTML for certain fields that need it.
            return sanitize_textarea_field( $data );
        }

        return $data;
    }
}
