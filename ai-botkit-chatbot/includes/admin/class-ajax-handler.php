<?php
namespace AI_BotKit\Admin;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Utils\Cache_Manager;
use AI_BotKit\Models\Chatbot;

/**
 * Class Ajax_Handler
 * 
 * Handles all AJAX requests for the AI BotKit plugin.
 * This class manages API testing, chatbot operations, and fallback order management.
 */
class Ajax_Handler {
    /**
     * Rate limiter instance
     *
     * @var Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Constructor - Registers all AJAX hooks
     */
    public function __construct() {
        $this->rate_limiter = new Rate_Limiter();

        // API testing endpoint
        add_action('wp_ajax_ai_botkit_test_api_connection', array($this, 'handle_test_api'));
        
        // Chatbot management endpoints
        add_action('wp_ajax_ai_botkit_save_chatbot', array($this, 'handle_save_chatbot'));
        add_action('wp_ajax_ai_botkit_get_chatbot', array($this, 'handle_get_chatbot'));
        add_action('wp_ajax_ai_botkit_delete_chatbot', array($this, 'handle_delete_chatbot'));
        
        // Fallback order management endpoint
        add_action('wp_ajax_ai_botkit_update_fallback_order', array($this, 'handle_update_fallback_order'));

        // Rate limit management endpoints
        add_action('wp_ajax_ai_botkit_set_rate_limits', array($this, 'handle_set_rate_limits'));
        add_action('wp_ajax_ai_botkit_reset_rate_limits', array($this, 'handle_reset_rate_limits'));

        // Preview content endpoint
        add_action('wp_ajax_ai_botkit_preview_content', array($this, 'ai_botkit_preview_content'));

        // Document import endpoints
        add_action('wp_ajax_ai_botkit_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_ai_botkit_import_url', array($this, 'handle_url_import'));
        add_action('wp_ajax_ai_botkit_import_wp_content', array($this, 'handle_wp_content_import'));
        add_action('wp_ajax_ai_botkit_delete_document', array($this, 'handle_delete_document'));

        // Chatbot document management endpoints
        add_action('wp_ajax_ai_botkit_add_chatbot_documents', array($this, 'handle_add_chatbot_documents'));
        add_action('wp_ajax_ai_botkit_remove_chatbot_document', array($this, 'handle_remove_chatbot_document'));
        add_action('wp_ajax_ai_botkit_get_chatbot_documents', array($this, 'handle_get_chatbot_documents'));

        // New endpoint for getting available documents for selection
        add_action('wp_ajax_ai_botkit_get_available_documents', array($this, 'handle_get_available_documents'));

        // Avatar upload endpoint
        add_action('wp_ajax_ai_botkit_upload_avatar', array($this, 'handle_upload_avatar'));
        add_action('wp_ajax_ai_botkit_upload_background_image', array($this, 'handle_upload_background_image'));

        // Enable chatbot sitewide endpoint
        add_action('wp_ajax_ai_botkit_enable_chatbot_sitewide', array($this, 'handle_enable_chatbot_sitewide'));
        add_action('wp_ajax_ai_botkit_enable_chatbot', array($this, 'handle_enable_chatbot'));

        // Migration endpoints
        add_action('wp_ajax_ai_botkit_get_migration_status', array($this, 'handle_get_migration_status'));
        add_action('wp_ajax_ai_botkit_get_content_types', array($this, 'handle_get_content_types'));
        add_action('wp_ajax_ai_botkit_start_migration', array($this, 'handle_start_migration'));
        add_action('wp_ajax_ai_botkit_clear_database', array($this, 'handle_clear_database'));
    }

    /**
     * Check rate limit for current user and action
     *
     * @param string $action The action being rate limited
     * @return bool|WP_Error True if allowed, WP_Error if limited
     */
    private function check_rate_limit($action) {
        $user_id = get_current_user_id();
        return $this->rate_limiter->check_rate_limit($user_id, $action);
    }

    public function handle_test_api() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        // Check rate limit
        $rate_limit_check = $this->check_rate_limit('test_api');
        if (is_wp_error($rate_limit_check)) {
            wp_send_json_error(array(
                'message' => $rate_limit_check->get_error_message(),
                'data' => $rate_limit_check->get_error_data()
            ));
        }

        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required.', 'ai-botkit-for-lead-generation')));
        }

        try {
            $result = $this->test_api_connection($provider, $api_key);
            if ($result) {
                wp_send_json_success(array('message' => __('API connection successful.', 'ai-botkit-for-lead-generation')));
            } else {
                wp_send_json_error(array('message' => __('API connection failed. The response was valid but did not contain expected data.', 'ai-botkit-for-lead-generation')));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('API connection failed: %s', 'ai-botkit-for-lead-generation'),
                    $e->getMessage()
                ),
                'data' => array(
                    'provider' => $provider,
                    'error' => $e->getMessage()
                )
            ));
        }
    }

    public function handle_save_chatbot() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        // Check rate limit
        $rate_limit_check = $this->check_rate_limit('save_chatbot');
        if (is_wp_error($rate_limit_check)) {
            wp_send_json_error(array(
                'message' => $rate_limit_check->get_error_message(),
                'data' => $rate_limit_check->get_error_data()
            ));
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;

        $chatbot_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'active' => isset($_POST['active']) ? 1 : 0,
            'avatar' => isset($_POST['chatbot_avatar']) ? sanitize_text_field($_POST['chatbot_avatar']) : esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.png'),
            'feedback' => isset($_POST['feedback']) ? 1 : 0,
        
            // Combine all style-related fields into JSON
            'style' => wp_json_encode(array(
                'avatar' => isset($_POST['chatbot_avatar']) ? sanitize_text_field($_POST['chatbot_avatar']) : esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.png'),
                'widget' => isset($_POST['chatbot_widget']) ? sanitize_text_field($_POST['chatbot_widget']) : esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.png'),
                'location' => sanitize_text_field($_POST['location']),
                'primary_color' => sanitize_text_field($_POST['chatbot_primary_color']),
                'font_family' => sanitize_text_field($_POST['chatbot_font_family']),
                'font_size' => sanitize_text_field($_POST['chatbot_font_size']),
                'theme' => sanitize_text_field($_POST['chatbot_theme']),
                'header_bg_color' => isset($_POST['chatbot_header_bg_color']) ? sanitize_text_field($_POST['chatbot_header_bg_color']) : '#FFFFFF',
                'header_color' => isset($_POST['chatbot_header_font_color']) ? sanitize_text_field($_POST['chatbot_header_font_color']) : '#333333',
                'header_icon_color' => isset($_POST['chatbot_header_icon_color']) ? sanitize_text_field($_POST['chatbot_header_icon_color']) : '#333333',
                'width' => sanitize_text_field($_POST['chatbot_width']),
                'max_height' => sanitize_text_field($_POST['chatbot_max_height']),
                'background_image' => isset($_POST['background_image']) ? sanitize_text_field($_POST['background_image']) : '',
                'body_bg_color' => isset($_POST['chatbot_bg_color']) ? sanitize_text_field($_POST['chatbot_bg_color']) : '#FFFFFF',
                'ai_msg_bg_color' => isset($_POST['chatbot_ai_msg_bg_color']) ? sanitize_text_field($_POST['chatbot_ai_msg_bg_color']) : '#F5F5F5',
                'ai_msg_font_color' => isset($_POST['chatbot_ai_msg_font_color']) ? sanitize_text_field($_POST['chatbot_ai_msg_font_color']) : '#333333',
                'user_msg_bg_color' => isset($_POST['chatbot_user_msg_bg_color']) ? sanitize_text_field($_POST['chatbot_user_msg_bg_color']) : '#008858',
                'user_msg_font_color' => isset($_POST['chatbot_user_msg_font_color']) ? sanitize_text_field($_POST['chatbot_user_msg_font_color']) : '#FFFFFF',
                'initiate_msg_bg_color' => isset($_POST['chatbot_initiate_msg_bg_color']) ? sanitize_text_field($_POST['chatbot_initiate_msg_bg_color']) : '#FFFFFF',
                'initiate_msg_border_color' => isset($_POST['chatbot_initiate_msg_border_color']) ? sanitize_text_field($_POST['chatbot_initiate_msg_border_color']) : '#E7E7E7',
                'initiate_msg_font_color' => isset($_POST['chatbot_initiate_msg_font_color']) ? sanitize_text_field($_POST['chatbot_initiate_msg_font_color']) : '#283B3C',
                'bubble_height' => sanitize_text_field($_POST['chatbot_bubble_height']),
                'bubble_width' => sanitize_text_field($_POST['chatbot_bubble_width']),
                'gradient_color_1' => sanitize_text_field($_POST['chatbot_gradient_color_1']),
                'gradient_color_2' => sanitize_text_field($_POST['chatbot_gradient_color_2']),
                'enable_gradient' => isset($_POST['enable_gradient']) ? 1 : 0,
            )),
            // Combine personality, greeting, fallback into JSON
            'messages_template' => wp_json_encode(array(
                'personality' => sanitize_textarea_field(wp_unslash($_POST['personality'])),
                'greeting' => sanitize_textarea_field(wp_unslash($_POST['greeting'])),
                'fallback' => sanitize_textarea_field(wp_unslash($_POST['fallback'])),
            )),
        
            // Combine model-related config
            'model_config' => wp_json_encode(array(
                'engine' => sanitize_text_field($_POST['engine']),
                'model' => sanitize_text_field($_POST['model']),
                'max_messages' => intval($_POST['max_messages']),
                'context_length' => intval($_POST['context_length']),
                'max_tokens' => intval($_POST['max_tokens']),
                'tone' => sanitize_text_field($_POST['tone']),
                'temperature' => floatval($_POST['model_temperature']),
                'min_chunk_relevance' => floatval($_POST['min_chunk_relevance']),
            )),
        );
    
        

        $imports = isset($_POST['imports']) ? json_decode(wp_unslash($_POST['imports']), true) : []; // sanitized below
        if( ! is_array($imports) ) {
            $imports = [];
        }
        $imports = array_unique(array_map('absint', $imports)); // ensure all values are integers and sanitized
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';

        if ($chatbot_id) {
            $result = $wpdb->update(
                $table_name,
                $chatbot_data,
                ['id' => $chatbot_id],
                [
                    '%s', // name
                    '%d', // active
                    '%d', // avatar
                    '%d', // feedback
                    '%s', // style (JSON)
                    '%s', // messages_template (JSON)
                    '%s', // model_config (JSON)
                ],
                ['%d'] // id
            );
        } else {
            $result = $wpdb->insert(
                $table_name,
                $chatbot_data,
                [
                    '%s', // name
                    '%d', // active
                    '%d', // avatar
                    '%d', // feedback
                    '%s', // style (JSON)
                    '%s', // messages_template (JSON)
                    '%s', // model_config (JSON)
                ]
            );
            $chatbot_id = $wpdb->insert_id;
        }


        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save chatbot.', 'ai-botkit-for-lead-generation')));
        }
        
        // add document to chatbot
        $chatbot = new Chatbot($chatbot_id);
        if( ! empty($imports) ) {
            $associated_documents = $chatbot->get_associated_content();
            $associated_documents = array_column($associated_documents, 'target_id');
            // add if not already in the associated documents and remove if in there but not in the imports
            $docs_to_add = array_diff($imports, $associated_documents);
            $docs_to_remove = array_diff($associated_documents, $imports);
            foreach ($docs_to_add as $doc_id) {
                $chatbot->add_content('document', $doc_id);
            }
            foreach ($docs_to_remove as $doc_id) {
                $chatbot->remove_content('document', $doc_id);
            }
        }
        // Get updated list of associated documents
        $documents = $chatbot->get_associated_content('document');

        // You can also return the chatbot ID if needed
        wp_send_json_success(array(
            'message' => __('Chatbot saved successfully.', 'ai-botkit-for-lead-generation'),
            'chatbot_id' => $chatbot_id
        ));
    }

    public function handle_get_chatbot() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        $chatbot_id = intval($_POST['chatbot_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';

        $chatbot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $chatbot_id));
        
        if (!$chatbot) {
            wp_send_json_error(array('message' => __('Chatbot not found.', 'ai-botkit-for-lead-generation')));
        }

        $image = wp_get_attachment_image_src($chatbot->avatar, 'full');
        $chatbot->avatar_id = $chatbot->avatar;
        if( $image ) {
            $chatbot->avatar = $image[0];
        } else {
            $chatbot->avatar = '';
        }
        wp_send_json_success($chatbot);
    }

    public function handle_delete_chatbot() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        $chatbot_id = intval($_POST['chatbot_id']);
        
        global $wpdb;   
        $table_name = $wpdb->prefix . 'ai_botkit_chatbots';

        $result = $wpdb->delete($table_name, ['id' => $chatbot_id]);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete chatbot.', 'ai-botkit-for-lead-generation')));
        }

        wp_send_json_success(array('message' => __('Chatbot deleted successfully.', 'ai-botkit-for-lead-generation')));
    }

    public function handle_update_fallback_order() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        $order = isset($_POST['order']) ? json_decode(wp_unslash($_POST['order']), true) : []; // sanitized below
        $order = array_unique(array_map('absint', $order)); // ensure all values are integers and sanitized
        if (!is_array($order)) {
            wp_send_json_error(array('message' => __('Invalid order data.', 'ai-botkit-for-lead-generation')));
        }

        $sanitized_order = array_map('sanitize_text_field', $order);
        update_option('ai_botkit_fallback_order', $sanitized_order);
        
        wp_send_json_success(array('message' => __('Fallback order updated successfully.', 'ai-botkit-for-lead-generation')));
    }

    /**
     * Handle setting custom rate limits for a user
     */
    public function handle_set_rate_limits() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $action = sanitize_text_field($_POST['action_name']);
        $window = isset($_POST['window']) ? intval($_POST['window']) : Rate_Limiter::DEFAULT_WINDOW;
        $max_requests = isset($_POST['max_requests']) ? intval($_POST['max_requests']) : Rate_Limiter::DEFAULT_MAX_REQUESTS;

        if (!$user_id || empty($action)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-botkit-for-lead-generation')));
        }

        $result = $this->rate_limiter->set_user_rate_limits($user_id, $action, $window, $max_requests);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Rate limits updated successfully.', 'ai-botkit-for-lead-generation')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update rate limits.', 'ai-botkit-for-lead-generation')));
        }
    }

    /**
     * Handle resetting rate limits for a user
     */
    public function handle_reset_rate_limits() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-botkit-for-lead-generation')));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $action = sanitize_text_field($_POST['action_name']);

        if (!$user_id || empty($action)) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'ai-botkit-for-lead-generation')));
        }

        $result = $this->rate_limiter->reset_rate_limit($user_id, $action);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Rate limits reset successfully.', 'ai-botkit-for-lead-generation')));
        } else {
            wp_send_json_error(array('message' => __('Failed to reset rate limits.', 'ai-botkit-for-lead-generation')));
        }
    }

    /**
     * Tests API connection for a specific provider
     * 
     * @param string $provider The API provider (openai, anthropic, google)
     * @param string $api_key The API key to test
     * @return bool True if connection is successful
     * @throws Exception If provider is invalid or connection fails
     */
    private function test_api_connection($provider, $api_key) {
        switch ($provider) {
            case 'openai':
                return $this->test_openai_connection($api_key);
            case 'anthropic':
                return $this->test_anthropic_connection($api_key);
            case 'google':
                return $this->test_google_connection($api_key);
            case 'together':
                return $this->test_together_connection($api_key);
            default:
                throw new \Exception(esc_html__('Invalid provider.', 'ai-botkit-for-lead-generation'));
        }
    }

    /**
     * Tests OpenAI API connection
     * 
     * @param string $api_key OpenAI API key
     * @return bool True if connection is successful
     * @throws Exception If connection fails
     */
    private function test_openai_connection($api_key) {
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['data']) && is_array($body['data']);
    }

    /**
     * Tests Anthropic API connection
     * 
     * @param string $api_key Anthropic API key
     * @return bool True if connection is successful
     * @throws Exception If connection fails
     */
    private function test_anthropic_connection($api_key) {
        $response = wp_remote_get('https://api.anthropic.com/v1/models', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['data']) && is_array($body['data']);
    }

    /**
     * Tests Google AI API connection
     * 
     * @param string $api_key Google AI API key
     * @return bool True if connection is successful
     * @throws Exception If connection fails
     */
    private function test_google_connection($api_key) {
        // Note: Replace with actual Google AI API endpoint when available
        $response = wp_remote_get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 60
            )
        );

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['models']) && is_array($body['models']);
    }

    /**
     * Tests Together AI API connection
     * 
     * @param string $api_key Together AI API key
     * @return bool True if connection is successful
     * @throws Exception If connection fails
     */
    private function test_together_connection($api_key) {
        $response = wp_remote_get('https://api.together.xyz/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body[0]['id']) && !empty($body);
    }

    /**
    * Preview content
    */
    function ai_botkit_preview_content() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : [];
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        if (empty($post_types)) {
            wp_send_json_error(['message' => esc_html__('Please select at least one content type.', 'ai-botkit-for-lead-generation')]);
        }
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($date_from) || !empty($date_to)) {
            $args['date_query'] = [];
            
            if (!empty($date_from)) {
                $args['date_query']['after'] = $date_from;
            }
            
            if (!empty($date_to)) {
                $args['date_query']['before'] = $date_to;
            }
        }
        $query = new \WP_Query($args);
        
        ob_start();

        if ($query->have_posts()) {

            while ($query->have_posts()) {
                $query->the_post();
                $post_type_obj = get_post_type_object(get_post_type());
                echo '<div class="ai-botkit-kb-item">
                    <div class="ai-botkit-kb-left">
                        <input type="checkbox" id="ai-botkit-wp-item-' . esc_attr(get_the_ID()) . '" class="ai-botkit-wp-checkbox" value="' . esc_attr(get_the_ID()) . '" />

                        <div class="ai-botkit-kb-info">
                            <label for="ai-botkit-wp-item-' . esc_attr(get_the_ID()) . '" class="ai-botkit-kb-name">
                                ' . esc_html(get_the_title()) . '
                            </label>

                            <div class="ai-botkit-kb-type">
                                <span>' . esc_html($post_type_obj->labels->singular_name) . '</span>
                            </div>
                        </div>
                    </div>

                    <div class="ai-botkit-kb-tags">
                        ' . esc_html(date_i18n(get_option('date_format'), strtotime(get_the_date()))) . '
                    </div>
                </div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No content found matching your criteria.', 'ai-botkit-for-lead-generation') . '</p></div>';
        }
        
        wp_reset_postdata();
        $html = ob_get_clean();
        

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle file upload for knowledge base
     */
    public function handle_file_upload() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if ( ! isset($_FILES['file']) ) {
            wp_send_json_error(['message' => esc_html__('Missing required fields.', 'ai-botkit-for-lead-generation')]);
        }

        $file = $_FILES['file'];
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => esc_html__('File upload failed.', 'ai-botkit-for-lead-generation')]);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // If it's a txt or md file, force the MIME type
        if (in_array($ext, ['txt', 'md'])) {
            $file['type'] = 'text/plain';
        }

        // Validate file type
        $allowed_types = ['text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/markdown', 'application/octet-stream'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => esc_html__('Invalid file type.', 'ai-botkit-for-lead-generation')]);
        }

        $filetype = wp_check_filetype($file['name']);
        if( empty($filetype['ext']) ) {
            wp_send_json_error(['message' => esc_html__('Invalid file type.', 'ai-botkit-for-lead-generation')]);
        }

        // user wp_handle_upload to upload the file
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => esc_html($upload['error'])]);
        }

        $file_name = $upload['file'];

        if( empty($title) ) {
            $title = basename($file_name);
        }

        // Save document in database
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'ai_botkit_documents',
            [
                'title' => $title,
                'source_type' => 'file',
                'file_path' => $file_name,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => esc_html__('Failed to save document.', 'ai-botkit-for-lead-generation')]);
        }

        wp_send_json_success([
            'message' => esc_html__('Document uploaded successfully.', 'ai-botkit-for-lead-generation'),
            'document_id' => $wpdb->insert_id
        ]);
    }

    /**
     * Handle URL import for knowledge base
     */
    public function handle_url_import() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_POST['url']) ) {
            wp_send_json_error(['message' => esc_html__('Missing required fields.', 'ai-botkit-for-lead-generation')]);
        }

        $url = esc_url_raw($_POST['url']);
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => esc_html__('Invalid URL.', 'ai-botkit-for-lead-generation')]);
        }

        // Verify URL is accessible
        $response = wp_remote_head($url);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_send_json_error(['message' => esc_html__('URL is not accessible.', 'ai-botkit-for-lead-generation')]);
        }

        if( empty($title) ) {
            $title = $url;
        }

        // Save document in database
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'ai_botkit_documents',
            [
                'title' => $title,
                'source_type' => 'url',
                'file_path' => $url,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => esc_html__('Failed to save document.', 'ai-botkit-for-lead-generation')]);
        }

        wp_send_json_success([
            'message' => esc_html__('URL imported successfully.', 'ai-botkit-for-lead-generation'),
            'document_id' => $wpdb->insert_id
        ]);
    }

    /**
     * Handle WordPress content import for knowledge base
     */
    public function handle_wp_content_import() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_POST['post_ids']) || !is_array($_POST['post_ids'])) {
            wp_send_json_error(['message' => esc_html__('No content selected.', 'ai-botkit-for-lead-generation')]);
        }

        $post_ids = array_map('intval', $_POST['post_ids']);
        $imported_count = 0;
        $failed_count = 0;
        $document_ids = [];

        global $wpdb;
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $failed_count++;
                continue;
            }

            // Save document in database
            $result = $wpdb->insert(
                $wpdb->prefix . 'ai_botkit_documents',
                [
                    'title' => $post->post_title,
                    'source_type' => 'post',
                    'source_id' => $post_id,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result) {
                $imported_count++;
                $document_ids[] = $wpdb->insert_id;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                __('%1$d items imported successfully. %2$d items failed.', 'ai-botkit-for-lead-generation'),
                $imported_count,
                $failed_count
            ),
            'imported_count' => $imported_count,
            'failed_count' => $failed_count,
            'document_ids' => $document_ids
        ]);
    }

    /**
     * Handle deleting document from knowledge base
     */
    public function handle_delete_document() {
        check_ajax_referer('ai_botkit_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_POST['document_id'])) {
            wp_send_json_error(['message' => esc_html__('Invalid parameters.', 'ai-botkit-for-lead-generation')]);
        }

        $document_id = intval($_POST['document_id']);

        if ($document_id <= 0) {
            wp_send_json_error(['message' => esc_html__('Invalid document ID.', 'ai-botkit-for-lead-generation')]);
        }

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_documents',
            ['id' => $document_id],
            ['%d']
        );

        // delete chunks and embeddings
        $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_chunks',
            ['document_id' => $document_id],
            ['%d']
        );

        $wpdb->delete(
            $wpdb->prefix . 'ai_botkit_embeddings',
            ['document_id' => $document_id],
            ['%d']
        );

        if ($result) {
            wp_send_json_success([
                'message' => esc_html__('Document deleted successfully.', 'ai-botkit-for-lead-generation')
            ]);
        } else {
            wp_send_json_error(['message' => esc_html__('Failed to delete document.', 'ai-botkit-for-lead-generation')]);
        }
        
        
    }

    /**
     * Handle adding documents to chatbot
     */
    public function handle_add_chatbot_documents() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
        $document_ids = isset($_POST['document_ids']) ? array_map('intval', $_POST['document_ids']) : [];

        if (!$chatbot_id || empty($document_ids)) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            $chatbot = new Chatbot($chatbot_id);
            
            foreach ($document_ids as $doc_id) {
                $chatbot->add_content('document', $doc_id);
            }

            // Get updated list of associated documents
            $documents = $chatbot->get_associated_content('document');
            
            wp_send_json_success([
                'message' => __('Documents added successfully.', 'ai-botkit-for-lead-generation'),
                'documents' => $documents
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle removing document from chatbot
     */
    public function handle_remove_chatbot_document() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
        $document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;

        if (!$chatbot_id || !$document_id) {
            wp_send_json_error(['message' => esc_html__('Invalid parameters.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            $chatbot = new Chatbot($chatbot_id);
            
            if ($chatbot->remove_content('document', $document_id)) {
                // Get updated list of associated documents
                $documents = $chatbot->get_associated_content('document');
                
                wp_send_json_success([
                    'message' => __('Document removed successfully.', 'ai-botkit-for-lead-generation'),
                    'documents' => $documents
                ]);
            } else {
                wp_send_json_error(['message' => esc_html__('Failed to remove document.', 'ai-botkit-for-lead-generation')]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle getting chatbot documents
     */
    public function handle_get_chatbot_documents() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;

        if (!$chatbot_id) {
            wp_send_json_error(['message' => esc_html__('Invalid parameters.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            $chatbot = new Chatbot($chatbot_id);
            $documents = $chatbot->get_associated_content('document');
            
            wp_send_json_success([
                'documents' => $documents
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle getting available documents for selection
     */
    public function handle_get_available_documents() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        global $wpdb;
        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
        $response = array();
        // Get all documents
        if( $chatbot_id > 0 ) {
            $documents = $wpdb->get_results($wpdb->prepare(
                "SELECT d.id, d.title, d.source_type, d.status, d.file_path, d.created_at
                FROM {$wpdb->prefix}ai_botkit_documents as d
                LEFT JOIN {$wpdb->prefix}ai_botkit_content_relationships cr
                    ON d.id = cr.target_id
                WHERE cr.source_type = 'chatbot'
                AND cr.source_id = %d
                AND cr.relationship_type = 'knowledge_base'
                ORDER BY d.created_at DESC",
                $chatbot_id
            ), ARRAY_A);
        } else {
            $documents = array();
        }

            
        wp_send_json_success([
            'documents' => $documents,
        ]);
    }

    /**
     * Handle avatar upload for chatbot
     */
    public function handle_upload_avatar() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_FILES['avatar'])) {
            wp_send_json_error(['message' => esc_html__('No file uploaded.', 'ai-botkit-for-lead-generation')]);
        }

        $file = $_FILES['avatar'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => esc_html__('File upload failed.', 'ai-botkit-for-lead-generation')]);
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => esc_html__('Invalid file type. Please upload an image (JPG, PNG, or GIF).', 'ai-botkit-for-lead-generation')]);
        }

        $filetype = wp_check_filetype($file['name']);
        if( empty($filetype['ext']) ) {
            wp_send_json_error(['message' => esc_html__('Invalid file type.', 'ai-botkit-for-lead-generation')]);
        }

        // Upload the file to WordPress media library
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        // Create attachment post
        $attachment = [
            'post_mime_type' => $file['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => esc_html__('Failed to save image.', 'ai-botkit-for-lead-generation')]);
        }

        // Generate attachment metadata and update
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success([
            'message' => esc_html__('Avatar uploaded successfully.', 'ai-botkit-for-lead-generation'),
            'id' => $attach_id,
            'url' => $upload['url']
        ]);
    }

    /**
     * Handle background image upload for chatbot
     */
    public function handle_upload_background_image() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_FILES['background_image'])) {
            wp_send_json_error(['message' => esc_html__('No file uploaded.', 'ai-botkit-for-lead-generation')]);
        }

        $file = $_FILES['background_image'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => esc_html__('File upload failed.', 'ai-botkit-for-lead-generation')]);
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => esc_html__('Invalid file type. Please upload an image (JPG, PNG, or GIF).', 'ai-botkit-for-lead-generation')]);
        }

        $filetype = wp_check_filetype($file['name']);
        if( empty($filetype['ext']) ) {
            wp_send_json_error(['message' => esc_html__('Invalid file type.', 'ai-botkit-for-lead-generation')]);
        }

        // Upload the file to WordPress media library
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        // Create attachment post
        $attachment = [
            'post_mime_type' => $file['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => esc_html__('Failed to save image.', 'ai-botkit-for-lead-generation')]);
        }

        // Generate attachment metadata and update
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        wp_send_json_success([
            'message' => esc_html__('Background image uploaded successfully.', 'ai-botkit-for-lead-generation'),
            'id' => $attach_id,
            'url' => $upload['url']
        ]);
    }

    /**
     * Handle enabling chatbot sitewide
     */
    public function handle_enable_chatbot_sitewide() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
        $enable_chatbot_sitewide = isset($_POST['enable_chatbot_sitewide']) ? intval($_POST['enable_chatbot_sitewide']) : 0;
        
        if( $chatbot_id <= 0 ) {
            wp_send_json_error(['message' => esc_html__('Invalid chatbot ID.', 'ai-botkit-for-lead-generation')]);
        }

        if( $enable_chatbot_sitewide ) {
            update_option('ai_botkit_chatbot_sitewide_enabled', $chatbot_id);
            wp_send_json_success(['message' => esc_html__('Chatbot enabled sitewide.', 'ai-botkit-for-lead-generation')]);
        } else {
            update_option('ai_botkit_chatbot_sitewide_enabled', 0);
            wp_send_json_error(['message' => esc_html__('Chatbot disabled sitewide.', 'ai-botkit-for-lead-generation')]);
        }
    }

    /**
     * Handle enabling chatbot
     */
    public function handle_enable_chatbot() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
        $enable_chatbot = isset($_POST['enable_chatbot']) ? intval($_POST['enable_chatbot']) : 0;

        if( $chatbot_id <= 0 ) {
            wp_send_json_error(['message' => esc_html__('Invalid chatbot ID.', 'ai-botkit-for-lead-generation')]);
        }
        
        // Update chatbot publish status
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ai_botkit_chatbots',
            ['active' => $enable_chatbot],
            ['id' => $chatbot_id]
        );

        if( $result ) {
            if( $enable_chatbot == 1 ) {
                wp_send_json_success(['message' => esc_html__('Chatbot enabled.', 'ai-botkit-for-lead-generation')]);
            } else {
                wp_send_json_error(['message' => esc_html__('Chatbot disabled.', 'ai-botkit-for-lead-generation')]);
            }
        } else {
            wp_send_json_error(['message' => esc_html__('Failed to enable chatbot.', 'ai-botkit-for-lead-generation')]);
        }
    }

    /**
     * Handle getting migration status
     */
    public function handle_get_migration_status() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            // Create required dependencies
            $llm_client = new \AI_BotKit\Core\LLM_Client();
            $document_loader = new \AI_BotKit\Core\Document_Loader();
            $text_chunker = new \AI_BotKit\Core\Text_Chunker();
            $embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator($llm_client);
            $vector_database = new \AI_BotKit\Core\Vector_Database();
            $retriever = new \AI_BotKit\Core\Retriever($vector_database, $embeddings_generator);
            $rag_engine = new \AI_BotKit\Core\RAG_Engine(
                $document_loader,
                $text_chunker,
                $embeddings_generator,
                $vector_database,
                $retriever,
                $llm_client
            );
            
            $migration_manager = new \AI_BotKit\Core\Migration_Manager(
                $rag_engine,
                $vector_database
            );

            $status = $migration_manager->get_migration_status();
            wp_send_json_success($status);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle getting content types for migration
     */
    public function handle_get_content_types() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            // Create required dependencies
            $llm_client = new \AI_BotKit\Core\LLM_Client();
            $document_loader = new \AI_BotKit\Core\Document_Loader();
            $text_chunker = new \AI_BotKit\Core\Text_Chunker();
            $embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator($llm_client);
            $vector_database = new \AI_BotKit\Core\Vector_Database();
            $retriever = new \AI_BotKit\Core\Retriever($vector_database, $embeddings_generator);
            $rag_engine = new \AI_BotKit\Core\RAG_Engine(
                $document_loader,
                $text_chunker,
                $embeddings_generator,
                $vector_database,
                $retriever,
                $llm_client
            );
            
            $migration_manager = new \AI_BotKit\Core\Migration_Manager(
                $rag_engine,
                $vector_database
            );

            $content_types = $migration_manager->get_available_content_types();
            wp_send_json_success($content_types);

        } catch (\Exception $e) {
            error_log('AI BotKit Migration Error: Migration operation failed - ' . $e->getMessage());
            error_log('AI BotKit Migration Error: Stack trace - ' . $e->getTraceAsString());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle starting migration
     */
    public function handle_start_migration() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        if (!isset($_POST['options'])) {
            wp_send_json_error(['message' => esc_html__('Migration options are required.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            // $_POST['options'] is already an array when sent via AJAX
            $options = $_POST['options'];
            
            if (!is_array($options)) {
                wp_send_json_error(['message' => esc_html__('Invalid migration options.', 'ai-botkit-for-lead-generation')]);
            }

            // Create required dependencies
            $llm_client = new \AI_BotKit\Core\LLM_Client();
            $document_loader = new \AI_BotKit\Core\Document_Loader();
            $text_chunker = new \AI_BotKit\Core\Text_Chunker();
            $embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator($llm_client);
            $vector_database = new \AI_BotKit\Core\Vector_Database();
            $retriever = new \AI_BotKit\Core\Retriever($vector_database, $embeddings_generator);
            $rag_engine = new \AI_BotKit\Core\RAG_Engine(
                $document_loader,
                $text_chunker,
                $embeddings_generator,
                $vector_database,
                $retriever,
                $llm_client
            );
            
            $migration_manager = new \AI_BotKit\Core\Migration_Manager(
                $rag_engine,
                $vector_database
            );

            $result = $migration_manager->start_migration($options);
            wp_send_json_success($result);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle clearing database
     */
    public function handle_clear_database() {
        check_ajax_referer('ai_botkit_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Insufficient permissions.', 'ai-botkit-for-lead-generation')]);
        }

        try {
            $database = sanitize_text_field($_POST['database'] ?? '');
            
            if (empty($database) || !in_array($database, ['local', 'pinecone'])) {
                wp_send_json_error(['message' => esc_html__('Invalid database specified.', 'ai-botkit-for-lead-generation')]);
            }

            // Additional server-side validation
            if ($database === 'pinecone') {
                // Check if Pinecone is configured
                $pinecone_api_key = get_option('ai_botkit_pinecone_api_key');
                $pinecone_environment = get_option('ai_botkit_pinecone_environment');
                $pinecone_index = get_option('ai_botkit_pinecone_index');
                
                if (empty($pinecone_api_key) || empty($pinecone_environment) || empty($pinecone_index)) {
                    wp_send_json_error(['message' => esc_html__('Pinecone is not properly configured.', 'ai-botkit-for-lead-generation')]);
                }
            }

            // Create required dependencies
            $llm_client = new \AI_BotKit\Core\LLM_Client();
            $document_loader = new \AI_BotKit\Core\Document_Loader();
            $text_chunker = new \AI_BotKit\Core\Text_Chunker();
            $embeddings_generator = new \AI_BotKit\Core\Embeddings_Generator($llm_client);
            $vector_database = new \AI_BotKit\Core\Vector_Database();
            $retriever = new \AI_BotKit\Core\Retriever($vector_database, $embeddings_generator);
            $rag_engine = new \AI_BotKit\Core\RAG_Engine(
                $document_loader,
                $text_chunker,
                $embeddings_generator,
                $vector_database,
                $retriever,
                $llm_client
            );

            $migration_manager = new \AI_BotKit\Core\Migration_Manager(
                $rag_engine,
                $vector_database
            );

            $result = $migration_manager->clear_database($database);
            wp_send_json_success($result);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
} 