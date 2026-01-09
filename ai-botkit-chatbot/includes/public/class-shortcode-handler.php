<?php
namespace AI_BotKit\Public;

use AI_BotKit\Models\Chatbot;

/**
 * Handles shortcode integration for the chat interface
 */
class Shortcode_Handler {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Initialize the handler
     * 
     * @param \AI_BotKit\Core\RAG_Engine $rag_engine RAG Engine instance
     */
    public function __construct($rag_engine) {
        $this->rag_engine = $rag_engine;
        $this->register_shortcodes();
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes(): void {
        add_shortcode('ai_botkit_chat', [$this, 'render_chat']);
        add_shortcode('ai_botkit_widget', [$this, 'render_widget']);
    }

    /**
     * Render chat interface
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered chat interface
     */
    public function render_chat($atts = []): string {
        // Parse attributes
        $args = shortcode_atts([
            'id' => '',
            'title' => __('AI Assistant', 'knowvault'),
            'welcome_message' => __('Hello! How can I help you today?', 'knowvault'),
            'placeholder' => __('Type your message...', 'knowvault'),
            'context' => '', // Optional context to focus the chat
            'width' => '100%',
            'height' => '600px',
            'theme' => 'light',
            'widget' => 0
        ], $atts);

        $chatbot = new Chatbot($args['id']);

        if (!$chatbot->exists()) {
            return __('Chatbot not found.', 'knowvault');
        }

        $chatbot_data = $chatbot->get_data();
        
        // Check if chatbot is active
        if (!$chatbot_data['active']) {
            return ''; // Return empty string for inactive chatbots
        }

        $chat_id = uniqid('chat_');

        // Enqueue required assets
        $this->enqueue_assets($chat_id, $chatbot_data);

        // Start output buffering
        ob_start();

        // Include chat template
        include AI_BOTKIT_PUBLIC_DIR . 'templates/chat.php';

        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Render chat widget
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered chat widget
     */
    public function render_widget($atts = []): string {
        // Parse attributes
        $args = shortcode_atts([
            'id' => '',
            'position' => 'right',
            'offset_x' => 20,
            'offset_y' => 20,
            'title' => __('AI Assistant', 'knowvault'),
            'welcome_message' => __('Hello! How can I help you today?', 'knowvault'),
            'button_text' => __('Chat with AI', 'knowvault'),
            'context' => '', // Optional context to focus the chat
            'theme' => 'light',
        ], $atts);

        // Set widget flag
        $args['widget'] = true;

        $chatbot = new Chatbot($args['id']);

        if (!$chatbot->exists()) {
            return __('Chatbot not found.', 'knowvault');
        }

        $chatbot_data = $chatbot->get_data();
        
        // Check if chatbot is active
        if (!$chatbot_data['active']) {
            return ''; // Return empty string for inactive chatbots
        }

        $chat_id = uniqid('chat_');

        // Enqueue required assets
        $this->enqueue_assets($chat_id, $chatbot_data);

        // Start output buffering
        ob_start();

        // Include widget template
        include AI_BOTKIT_PUBLIC_DIR . 'templates/widget.php';

        // Return buffered content
        return ob_get_clean();
    }

    /**
     * Enqueue required assets
     */
    private function enqueue_assets($chat_id, $chatbot_data): void {
        // Enqueue styles
        wp_enqueue_style(
            'ai-botkit-chat',
            AI_BOTKIT_PLUGIN_URL . 'public/css/chat.css',
            [],
            AI_BOTKIT_VERSION
        );

        wp_enqueue_style(
            'tabler-icons',
            AI_BOTKIT_PLUGIN_URL . 'admin/css/tabler-icons.css',
            array(),
            AI_BOTKIT_VERSION
        );

        $styles = json_decode($chatbot_data['style'], true);
        $messages_template = json_decode($chatbot_data['messages_template'], true);

        $inline_css = $this->get_inline_css($styles);
        wp_add_inline_style('ai-botkit-chat', wp_kses_post($inline_css));
        // Enqueue SweetAlert for modern confirmations
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            array(),
            '11.0.0',
            true
        );

        // Enqueue scripts
        wp_enqueue_script(
            'ai-botkit-chat',
            AI_BOTKIT_PLUGIN_URL . 'public/js/chat.js',
            ['jquery', 'sweetalert2'],
            AI_BOTKIT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('ai-botkit-chat', 'ai_botkitChat', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_botkit_chat'),
            'chatId' => $chat_id,
            'botID' => $chatbot_data['id'],
            'color' => $styles['primary_color'],
            'i18n' => [
                'errorMessage' => __('An error occurred. Please try again.', 'knowvault'),
                'networkError' => __('Network error. Please check your connection.', 'knowvault'),
                'sendError' => __('Failed to send message. Please try again.', 'knowvault'),
                'welcomeMessage' => $messages_template['greeting']
            ]
        ]);

        // Add SweetAlert configuration
        wp_add_inline_script('sweetalert2', '
            if (typeof Swal !== "undefined") {
                Swal.mixin({
                    customClass: {
                        popup: "ai-botkit-swal-popup",
                        backdrop: "ai-botkit-swal-backdrop"
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    backdrop: true
                });
            }
        ');
    }

    /**
     * Get chat settings
     * 
     * @return array Chat settings
     */
    private function get_settings(): array {
        return [
            'maxTokens' => get_option('ai_botkit_max_tokens', 1000),
            'temperature' => get_option('ai_botkit_temperature', 0.7),
            'model' => get_option('ai_botkit_openai_model', 'gpt-4-turbo-preview'),
            'streamResponse' => true,
        ];
    }

    /**
     * Get inline CSS
     * 
     * @param array $styles Chatbot data
     * @return string Inline CSS
     */
    private function get_inline_css($styles): string {
        $css = '';

        $default_styles = [
            "width" => "424",
            "location" => "bottom-right",
            "font_size" => "14",
            "max_height" => "700",
            "font_family" => "Inter",
            "bubble_width" => "55",
            "header_color" => "#000000",
            "body_bg_color" => "#ffffff",
            "bubble_height" => "55",
            "primary_color" => "#1E3A8A",
            "ai_msg_bg_color" => "#1E3A8A1a",
            "header_bg_color" => "#1E3A8A",
            "ai_msg_font_color" => "#1C1C1C",
            "user_msg_bg_color" => "#1E3A8A",
            "user_msg_font_color" => "#1C1C1C"
        ];

        // merge default styles with user styles
        $styles = array_merge($default_styles, $styles);
        $css .= '
        .ai-botkit-widget,
        .ai-botkit-widget-container{
            width: ' . $styles['width'] . 'px;
            max-height: ' . $styles['max_height'] . 'px;
            
        }
        .ai-botkit-chat-header{
            background-color: ' . $styles['header_bg_color'] . ' !important;
            color: ' . $styles['header_color'] . ' !important;
        }
        .ai-botkit-chat-actions button{
            color: ' . ($styles['header_icon_color'] ?? '#ffffff') . ' !important;
        }
        
        .ai-botkit-chat-messages{
            background-color: ' . $styles['body_bg_color'] . ';
            color: ' . $styles['ai_msg_font_color'] . ';
            background-image: url(' . ($styles['background_image'] ?? '') . ');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .ai-botkit-message.user .ai-botkit-message-content,
        .ai-botkit-message.user .ai-botkit-message-avatar{
            background-color: ' . ($styles['user_msg_bg_color'] ?? '#007cba') . ';
            color: ' . ($styles['user_msg_font_color'] ?? '#ffffff') . ';
        }
        .ai-botkit-message.assistant .ai-botkit-message-content,
        .ai-botkit-message.assistant .ai-botkit-message-avatar{
            background-color: ' . ($styles['ai_msg_bg_color'] ?? '#ffffff') . ';
            color: ' . ($styles['ai_msg_font_color'] ?? '#333333') . ';
        }
        .ai-botkit-message-text{
            font-size: ' . $styles['font_size'] . 'px;
        }

        @media (max-width: ' . $styles['width'] . 'px) {
            .ai-botkit-widget,
            .ai-botkit-widget-container{
                width: 100%;
                
            }
            .ai-botkit-bottom-right{
                right: 0;
            }
            .ai-botkit-bottom-left{
                left: 0;
            }
        }
            
        ';
        if(($styles['theme'] ?? 'theme-1') === 'theme-2' || ($styles['theme'] ?? 'theme-1') === 'theme-4') {
            if(isset($styles['background_image']) && $styles['background_image']) {
                $image = $styles['background_image'];
            } else {
                $image = esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/chatbot-bg.svg');
            }
            $css .= '
            .ai-botkit-chat-messages{
                background-image: url(' . $image . ');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
            }
            ';
        }
        if(($styles['enable_gradient'] ?? 0) === 1) {
            $css .= '
            .ai-botkit-widget-button{
                background: linear-gradient(to right, ' . $styles['gradient_color_1'] . ', ' . $styles['gradient_color_2'] . ') !important;
            }
            .ai-botkit-send-button{
                background: linear-gradient(to right, ' . $styles['gradient_color_1'] . ', ' . $styles['gradient_color_2'] . ') !important;
            }
            .ai-botkit-chat-avatar{
                background: linear-gradient(to right, ' . $styles['gradient_color_1'] . ', ' . $styles['gradient_color_2'] . ') !important;
            }
            ';
        } else {
            $css .= '
            .ai-botkit-widget-button{
                background: ' . $styles['primary_color'] . ' !important;
            }
            .ai-botkit-send-button{
                background-color: ' . $styles['primary_color'] . ' !important;
            }
            .ai-botkit-chat-avatar{
                background-color: ' . $styles['primary_color'] . ' !important;
            }
            ';
        }
        return $css;
    }

    /**
     * Render sitewide chatbot
     */
    public function render_sitewide_chatbot(): void {
        $site_wide_chatbot_id = get_option('ai_botkit_chatbot_sitewide_enabled');
        // check if there is already a widget on the page
        global $post;

        if ( isset($post->post_content) && has_shortcode($post->post_content, 'ai_botkit_widget') ) {
            return;
        }
        if ( is_numeric($site_wide_chatbot_id) && $site_wide_chatbot_id > 0 ) {
            echo do_shortcode('[ai_botkit_widget id="' . $site_wide_chatbot_id . '"]');
        }
    }
    
} 