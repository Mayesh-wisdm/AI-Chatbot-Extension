<?php
namespace AI_BotKit\Admin;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Models\Chatbot;
use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Admin\Ajax_Handler;
use AI_BotKit\Models\Conversation;
/**
 * The admin-specific functionality of the plugin.
 *
 * @package AI_BotKit
 * @subpackage AI_BotKit/admin
 */

class Admin {
    /**
     * The ID of this plugin.
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     * @var string
     */
    private $version;

    /**
     * Cache manager instance
     * @var Cache_Manager
     */
    private $cache_manager;

    /**
     * RAG engine instance
     * @var RAG_Engine
     */
    private $rag_engine;

    /**
     * AJAX Handler instance
     */
    private $ajax_handler;

    /**
     * Plugin settings pages
     */
    private static $admin_pages;

    /**
     * Current tab
     */
    private $tab;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     * @param Unified_Cache_Manager|null $cache_manager Optional. Cache manager instance.
     * @param RAG_Engine|null $rag_engine Optional. RAG engine instance.
     */
    public function __construct($plugin_name, $version, Unified_Cache_Manager $cache_manager = null, RAG_Engine $rag_engine = null) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cache_manager = $cache_manager;
        $this->rag_engine = $rag_engine;
        $this->ajax_handler = new Ajax_Handler();

        // Initialize Template AJAX Handler (FR-230 to FR-239).
        if ( class_exists( '\\AI_BotKit\\Features\\Template_Ajax_Handler' ) ) {
            new \AI_BotKit\Features\Template_Ajax_Handler();
        }

        // Initialize admin hooks
        $this->init_hooks();

        self::$admin_pages = [
            'KnowVault' => [
                'title' =>  __('KnowVault Dashboard', 'knowvault'), /* translators: Main plugin admin page title */
                'menu_title' => __('KnowVault', 'knowvault'),
                'capability' => 'manage_options',
                'icon' => 'dashicons-format-chat',
                'position' => 30,
                'callback' => 'display_dashboard_page'
            ],
            'KnowVault-knowledge' => [
                'title' => __('Knowledge Base Management', 'knowvault'), /* translators: Knowledge base page title */
                'menu_title' => __('Knowledge Base', 'knowvault'),
                'parent' => 'KnowVault',
                'capability' => 'manage_options',
                'callback' => 'display_knowledge_base_page'
            ],
            'KnowVault-settings' => [
                'title' => __('Plugin Settings', 'knowvault'), /* translators: Settings page title */
                'menu_title' => __('Settings', 'knowvault'),
                'parent' => 'KnowVault',
                'capability' => 'manage_options',
                'callback' => 'display_settings_page'
            ],
            'KnowVault-analytics' => [
                'title' => __('Usage Analytics', 'knowvault'), /* translators: Analytics page title */
                'menu_title' => __('Analytics', 'knowvault'),
                'parent' => 'KnowVault',
                'capability' => 'manage_options',
                'callback' => 'display_analytics_page'
            ]
        ];
    }

    /**
     * Initialize all admin hooks
     */
    private function init_hooks() {
        // Admin menu and pages
        add_action('admin_menu', [$this, 'add_plugin_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_activation_redirect']);
        add_action('admin_footer', [$this, 'add_deactivate_modal']);
        
        // Add help widget to admin footer (only on AI BotKit pages)
        add_action('admin_footer', [$this, 'inject_help_widget']);
        
        // Database migration notice
        add_action('admin_notices', [$this, 'show_database_migration_notice']);
        
        // AJAX handler for database migration
        add_action('wp_ajax_knowvault_migrate_database', [$this, 'handle_database_migration']);
        add_action('wp_ajax_knowvault_dismiss_migration_notice', [$this, 'handle_dismiss_notice']);
        
        // Custom menu icon
        add_action('admin_head', [$this, 'add_custom_menu_icon']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Debug endpoints
        add_action('wp_ajax_ai_botkit_debug_rate_limiter', [$this, 'debug_rate_limiter']);
        add_action('wp_ajax_ai_botkit_debug_class_loading', [$this, 'debug_class_loading']);

        // Health checks
        // add_filter('debug_information', [$this->health_checks, 'add_debug_information']);
        // add_filter('site_status_tests', [$this->health_checks, 'register_tests']);

        // add txt and md mime types
        add_filter('upload_mimes', [$this, 'add_mime_types']);
    }

    /**
     * Handle the activation redirect
     */
    public function handle_activation_redirect() {
        if (get_transient('_ai_botkit_activation_redirect')) {
            delete_transient('_ai_botkit_activation_redirect');
            wp_safe_redirect(admin_url('admin.php?page=ai-botkit&tab=chatbots'));
            exit;
        }
    }

    /**
     * Add the deactivate modal
     */
    public function add_deactivate_modal() {
        if (get_current_screen()->id !== 'plugins') {
            return;
        }
        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/deactivate-modal.php';
    }

    /**
     * Register the admin menu items
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            __('KnowVault', 'knowvault'),
            __('KnowVault', 'knowvault'),
            'manage_options',
            'ai-botkit',
            array($this, 'display_dashboard_page'),
            'dashicons-format-chat',
            30
        );

        // // Submenus
        // add_submenu_page(
        //     'ai-botkit',
        //     __('Knowledge Base', 'knowvault'),
        //     __('Knowledge Base', 'knowvault'),
        //     'manage_options',
        //     'ai-botkit-knowledge',
        //     array($this, 'display_knowledge_base_page')
        // );

        // add_submenu_page(
        //     'ai-botkit',
        //     __('Chatbots', 'knowvault'),
        //     __('Chatbots', 'knowvault'),
        //     'manage_options',
        //     'ai-botkit-chatbots',
        //     array($this, 'display_chatbots_page')
        // );

        // add_submenu_page(
        //     'ai-botkit',
        //     __('Analytics', 'knowvault'),
        //     __('Analytics', 'knowvault'),
        //     'manage_options',
        //     'ai-botkit-analytics',
        //     array($this, 'display_analytics_page')
        // );

        // add_submenu_page(
        //     'ai-botkit',
        //     __('Settings', 'knowvault'),
        //     __('Settings', 'knowvault'),
        //     'manage_options',
        //     'ai-botkit-settings',
        //     array($this, 'display_settings_page')
        // );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // API Settings
        register_setting('ai_botkit_settings',
            'ai_botkit_engine',
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_openai_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_anthropic_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_google_api_key',
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_openai_org_id',
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_chat_model',
            array(
                'default' => 'gpt-4-turbo-preview',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_embedding_model',
            array(
                'default' => 'text-embedding-3-small',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );

        // Processing Settings
        register_setting('ai_botkit_settings',
            'ai_botkit_chunk_size',
            array(
                'default' => 1000,
                'sanitize_callback' => 'absint'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_chunk_overlap',
            array(
                'default' => 200,
                'sanitize_callback' => 'absint'
            )
        );

        // Rate Limiting Settings
        register_setting('ai_botkit_settings',
            'ai_botkit_token_bucket_limit',
            array(
                'default' => 100000,
                'sanitize_callback' => 'absint'
            )
        );
        register_setting('ai_botkit_settings',
            'ai_botkit_max_requests_per_day',
            array(
                'default' => 60,
                'sanitize_callback' => 'absint'
            )
        );
    }

    /**
     * Enqueue admin-specific styles
     */
    public function enqueue_styles($hook) {
        // Always load Tabler icons
        wp_enqueue_style(
            'tabler-icons',
            AI_BOTKIT_PLUGIN_URL . 'admin/css/tabler-icons.css',
            array(),
            $this->version
        );

        // Only load help widget styles on AI BotKit pages
        if ($this->is_plugin_page($hook)) {
            wp_enqueue_style(
                'ai-botkit-admin-help-widget-css',
                AI_BOTKIT_PLUGIN_URL . 'admin/css/admin-help-widget.css',
                array('tabler-icons'),
                $this->version
            );
        }

        if ( 'plugins.php' === $hook ) {
            wp_enqueue_style(
                $this->plugin_name . '-admin-deactivate',
                AI_BOTKIT_PLUGIN_URL . 'admin/css/admin-deactivate.css',
                array(),
                $this->version
            );
            return;
        } else if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            AI_BOTKIT_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );

        $css = "
            #wpcontent{
                padding-left: 0 !important;
                background-color:#F5FBF9 !important;
            }
            #wpbody-content .notice{
                display: none !important;
            }";
        
        wp_add_inline_style( $this->plugin_name . '-admin', wp_kses_post($css) );

        // Page specific styles
        // $page = $this->get_current_page();
        // if ($page) {
        //     wp_enqueue_style(
        //         $this->plugin_name . '-' . $page,
        //         AI_BOTKIT_PLUGIN_URL . 'admin/css/' . $page . '.css',
        //         array($this->plugin_name . '-admin'),
        //         $this->version
        //     );
        // }
    }

    /**
     * Register and enqueue admin-specific scripts
     */
    public function enqueue_scripts($hook) {
        // Only load help widget scripts on AI BotKit pages
        if ($this->is_plugin_page($hook)) {
            wp_enqueue_script(
                'ai-botkit-admin-help-widget-js',
                AI_BOTKIT_PLUGIN_URL . 'admin/js/admin-help-widget.js',
                array('jquery'),
                $this->version,
                true
            );
            
            // Localize script with REST API endpoint and bot ID
            wp_localize_script('ai-botkit-admin-help-widget-js', 'aiBotKitAdminHelp', array(
                'restApiEndpoint' => 'https://aibotkit.io/wp-json/ai-botkit/v1/chat/message',
                'botId' => 1, // Documentation bot ID
                'isExternalEndpoint' => true
            ));
        }

        // Only load on plugin pages
        if ( 'plugins.php' === $hook ) {
            wp_enqueue_script(
                $this->plugin_name . '-admin-deactivate',
                AI_BOTKIT_PLUGIN_URL . 'admin/js/admin-deactivate.js',
                array(),
                $this->version,
                true
            );
            return;
        } else if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_script(
            'ai-botkit-admin',
            AI_BOTKIT_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker'),
            $this->version,
            false
        );

        // Enqueue Color Picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('jquery-ui-sortable');

        // Enqueue SweetAlert for modern confirmations
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);

        // Enqueue migration wizard scripts on knowledge base page
        // Check both tab=knowledge and page=ai-botkit-knowledge
        $is_knowledge_page = (strpos($hook, 'ai-botkit') !== false && isset($_GET['tab']) && $_GET['tab'] === 'knowledge') 
                          || (strpos($hook, 'ai-botkit-knowledge') !== false);
        
        if ($is_knowledge_page) {
            wp_enqueue_script(
                'ai-botkit-migration-wizard',
                AI_BOTKIT_PLUGIN_URL . 'admin/js/migration-wizard.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_enqueue_style(
                'ai-botkit-migration-wizard',
                AI_BOTKIT_PLUGIN_URL . 'admin/css/migration-wizard.css',
                array(),
                $this->version
            );

            // Localize migration script
            wp_localize_script('ai-botkit-migration-wizard', 'aiBotKitMigration', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_botkit_admin'),
                'strings' => array(
                    'loading' => __('Loading...', 'knowvault'),
                    'error' => __('An error occurred', 'knowvault'),
                    'success' => __('Success', 'knowvault')
                )
            ));
        }

        // Enqueue templates scripts on templates page.
        $is_templates_page = ( strpos( $hook, 'ai-botkit' ) !== false && isset( $_GET['tab'] ) && $_GET['tab'] === 'templates' );

        if ( $is_templates_page ) {
            wp_enqueue_script(
                'ai-botkit-templates',
                AI_BOTKIT_PLUGIN_URL . 'admin/js/templates.js',
                array( 'jquery' ),
                $this->version,
                true
            );

            wp_enqueue_style(
                'ai-botkit-templates',
                AI_BOTKIT_PLUGIN_URL . 'admin/css/templates.css',
                array(),
                $this->version
            );
        }

        // Localize script with plugin data
        wp_localize_script('ai-botkit-admin', 'ai_botkitAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_botkit_admin'),
            'i18n' => $this->get_js_translations(),
            'stats' => $this->get_stats(),
            'engines' => $this->get_engines(),
            'api_key_status' => $this->get_api_key_status(),
            'page' => $this->get_current_page(),
            'site_wide_chatbot_id' => get_option('ai_botkit_chatbot_sitewide_enabled')
        ));
    }

    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        // Get registered tabs including extensions
        $registered_tabs = apply_filters('ai_botkit_admin_tabs', array(
            // 'dashboard' => array(
            //     'title' => __('Dashboard', 'knowvault'),
            //     'capability' => 'manage_options'
            // ),
            'chatbots' => array(
                'title' => __('My Bots', 'knowvault'),
                'capability' => 'manage_options'
            ),
            'knowledge' => array(
                'title' => __('Knowledge Base', 'knowvault'),
                'capability' => 'manage_options'
            ),
            'templates' => array(
                'title' => __('Templates', 'knowvault'),
                'capability' => 'manage_options'
            ),
            'analytics' => array(
                'title' => __('Analytics', 'knowvault'),
                'capability' => 'manage_options'
            ),
            'security' => array(
                'title' => __('Security', 'knowvault'),
                'capability' => 'manage_options'
            ),
            'settings' => array(
                'title' => __('Settings', 'knowvault'),
                'capability' => 'manage_options'
            )
        ));

        // Get current tab
        $this->tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'chatbots';
        
        // Verify nonce if provided
        if (isset($_GET['nonce'])) {
            $nonce = sanitize_key($_GET['nonce']);
            if (!wp_verify_nonce($nonce, 'ai_botkit_chatbots')) {
                wp_die(__('Security check failed. Please refresh the page and try again.', 'knowvault'));
            }
        }

        // Now render the layout with the tab determined
        $this->before_main_content();

        // Check if current tab is registered
        if (array_key_exists($this->tab, $registered_tabs)) {
            // Check if this is a core tab or an extension tab
            // Core tabs are the ones that have view files in admin/views/
            $core_tabs = array('knowledge', 'chatbots', 'templates', 'analytics', 'settings', 'security');
            
            if (in_array($this->tab, $core_tabs)) {
                // Handle known core tabs
                switch ($this->tab) {
            // case 'dashboard':
            //     require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/dashboard.php';
            //     break;
            case 'knowledge':
                require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/knowledge-base.php';
                break;
            case 'chatbots':
                // nonce check. only check if bot_id is set else request from wp-admin menu item
                if ( isset($_GET['bot_id']) && ( !isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_GET['nonce'] ) ), 'ai_botkit_chatbots' ) ) ) {
                    wp_die(__('Invalid request', 'knowvault'));
                }
                $bot_id = isset($_GET['bot_id']) ? sanitize_text_field($_GET['bot_id']) : null;
                $chat_session_id = isset($_GET['chat_session_id']) ? sanitize_text_field($_GET['chat_session_id']) : null;
                if ($bot_id && empty($chat_session_id)) {
                    require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/chatbot-sessions.php';
                } elseif ($chat_session_id) {
                    require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/chat-session.php';
                } else {
                    require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/chatbots.php';
                }
                break;
            case 'templates':
                require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/templates.php';
                break;
            case 'analytics':
                require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/analytics.php';
                break;
            case 'settings':
                require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/settings.php';
                break;
                                case 'security':
                        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/security.php';
                        break;
                }
            } else {
                // This is an extension tab, let extensions handle it
                $this->handle_extension_tab($this->tab);
            }
        } else {
            // Allow extensions to handle unknown tabs
            $this->handle_extension_tab($this->tab);
        }
        $this->after_main_content();

    }

    /**
     * Handle extension tab content
     */
    private function handle_extension_tab($tab) {
        ob_start();
        do_action('ai_botkit_admin_tab_content', $tab);
        $extension_content = ob_get_clean();
        
        if (!empty($extension_content)) {
            echo $extension_content;
        } else {
            // Fallback to chatbots if no extension content
            require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/chatbots.php';
        }
    }

    /**
     * Display knowledge base page
     * Not used in the new UI
     */
    public function display_knowledge_base_page() {
        $this->before_main_content();
        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/knowledge-base.php';
        $this->after_main_content();
    }

    /**
     * Display chatbots page
     * Not used in the new UI
     */
    public function display_chatbots_page() {
        $this->before_main_content();
        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/chatbots.php';
        $this->after_main_content();
    }

    /**
     * Display analytics page
     * Not used in the new UI
     */
    public function display_analytics_page() {
        $this->before_main_content();
        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/analytics.php';
        $this->after_main_content();
    }

    /**
     * Display settings page
     * Not used in the new UI
     */
    public function display_settings_page() {
        $this->before_main_content();
        require_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/settings.php';
        $this->after_main_content();
    }

    /**
     * Helper methods
     */
    private function is_plugin_page($hook) {
        return strpos($hook, 'ai-botkit') !== false;
    }

    private function get_current_page() {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        return str_replace('ai-botkit-', '', $screen->id);
    }

    private function get_js_translations() {
        return array(
            'confirmDelete' => __('Are you sure you want to delete this item?', 'knowvault'),
            'confirmBulk' => __('Are you sure you want to perform this action?', 'knowvault'),
            'success' => __('Operation completed successfully.', 'knowvault'),
            'error' => __('An error occurred.', 'knowvault'),
            'loading' => __('Loading...', 'knowvault'),
            'saving' => __('Saving...', 'knowvault'),
            'deleting' => __('Deleting...', 'knowvault'),
            'processing' => __('Processing...', 'knowvault'),
            'addNewChatbot' => __('Add New Chatbot', 'knowvault'),
            'editChatbot' => __('Edit Chatbot', 'knowvault'),
            'confirmDeleteChatbot' => __('Are you sure you want to delete this chatbot?', 'knowvault'),
            'noDocumentsSelected' => __('No documents selected.', 'knowvault'),
            'noDocuments' => __('No documents found.', 'knowvault'),
            'remove' => __('Remove', 'knowvault'),
            'noApiKey' => __('API key is required.', 'knowvault'),
            'confirmReprocess' => __('Are you sure you want to reprocess this document?', 'knowvault'),
            'noSelection' => __('No selection made.', 'knowvault'),
            'confirmBulk' => __('Are you sure you want to perform this action?', 'knowvault'),
            'invalidDateRange' => __('Invalid date range.', 'knowvault'),
            'confirmRemoveDocument' => __('Are you sure you want to remove this document?', 'knowvault'),
            'successChatbotSaved' => __('Chatbot saved successfully.', 'knowvault'),
            'errorChatbotSaved' => __('Error saving chatbot.', 'knowvault'),
            'showPreview' => __('Show Preview', 'knowvault'),
            'hidePreview' => __('Hide Preview', 'knowvault'),
        );
    }

    private function get_stats() {
        global $wpdb;
        $stats = [
            'documents' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents"),
            'chunks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chunks"),
            'conversations' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_conversations"),
            'embeddings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_embeddings")
        ];

        return $stats;
    }

    private function get_engines() {
        // // Available engines and their models
        return array(
            'openai' => array(
                'name' => 'OpenAI',
                'chat_models' => array(
                    'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                    'gpt-4' => 'GPT-4',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
                ),
                'embedding_models' => array(
                    'text-embedding-3-small' => 'Text Embedding 3 Small',
                    'text-embedding-3-large' => 'Text Embedding 3 Large',
                    'text-embedding-ada-002' => 'Text Embedding Ada 002'
                )
            ),
            'anthropic' => array(
                'name' => 'Anthropic',
                'chat_models' => array(
                    'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet',
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                    'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
                    'claude-3-opus-20240229' => 'Claude 3 Opus',
                    'claude-3-5-haiku-20240307' => 'Claude 3.5 Haiku 20240307'
                ),
                'embedding_models' => array(
                    'voyage-3-large' => 'Voyage 3 Large',
                    'voyage-3-lite' => 'Voyage 3 Lite',
                    'voyage-3-mini' => 'Voyage 3 Mini'
                )
            ),
            'google' => array(
                'name' => 'Google',
                'chat_models' => array(
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro'
                ),
                'embedding_models' => array(
                    'embedding-001' => 'Text Embedding 001'
                )
            ),
            'together' => array(
                'name' => 'Together AI',
                'chat_models' => array(
                    'meta-llama/Llama-3.3-70B-Instruct-Turbo' => 'Llama 3.3 Instruct',
                    'deepseek-ai/DeepSeek-V3' => 'DeepSeek V3',
                    'mistralai/Mistral-7B-Instruct-v0.3' => 'Mistral Instruct v0.3',
                    'perplexity-ai/r1-1776' => 'Perplexity AI (R1-1776)'
                ),
                'embedding_models' => array(
                    'BAAI/bge-base-en-v1.5' => 'BGE-Base-EN v1.5',
                    'BAAI/bge-large-en-v1.5' => 'BGE-Large-EN v1.5',
                    'intfloat/multilingual-e5-large-instruct' => 'Multilingual E5-Large'
                )
            )
        );
    }

    /**
     * Get API key status for all engines
     */
    private function get_api_key_status() {
        $engines = $this->get_engines();
        $status = [];
        
        foreach ($engines as $engine_id => $engine) {
            $status[$engine_id] = !empty(get_option('ai_botkit_'.$engine_id.'_api_key', ''));
        }
        
        // Special case for VoyageAI (used by Anthropic for embeddings)
        $status['voyageai'] = !empty(get_option('ai_botkit_voyageai_api_key', ''));
        
        return $status;
    }

    private function before_main_content() {
        ?>
          <div class="ai-botkit-layout">
            <!-- Sidebar -->
            <div id="ai-botkit-sidebar" class="ai-botkit-sidebar-wrapper">
                <?php 
                // Pass the current tab to the sidebar
                $current_tab = $this->tab;
                include_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/sidebar.php'; 
                ?>
            </div>
            <!-- Right section: topbar + main -->
            <div class="ai-botkit-main-content">
                <!-- Topbar -->
                <div class="ai-botkit-topbar">
                    <div class="ai-botkit-topbar-content">
                        <!-- Hamburger menu -->
                        <button id="ai-botkit-hamburger-menu" class="ai-botkit-hamburger-menu">
                            <i class="ti ti-menu-2"></i>
                        </button>
                    </div>
                </div>
                <!-- Main Content -->
                <main class="ai-botkit-main">
            <?php
    }

    private function after_main_content() {
        ?>
                </main>
            </div>
        </div>
        <?php
    }

    /**
     * Inject help widget into admin footer
     * Only on AI BotKit admin pages
     */
    public function inject_help_widget() {
        // Only show on AI BotKit admin pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'ai-botkit') !== false) {
            // Include our custom help widget template
            include_once AI_BOTKIT_PLUGIN_DIR . 'admin/views/help-widget.php';
        }
    }

    /**
     * Add mime types
     */
    public function add_mime_types($mimes) {
        $mimes['txt'] = 'text/plain';
        $mimes['md'] = 'text/plain';
        return $mimes;
    }

    /**
     * Debug rate limiter functionality
     */
    public function debug_rate_limiter() {
        // Check if user has admin capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }
        
        // Create rate limiter instance
        $rate_limiter = new \AI_BotKit\Core\Rate_Limiter();
        
        // Get user ID from request or use current user
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
        
        // Create LLM client to check its rate limiter
        $llm_client = new \AI_BotKit\Core\LLM_Client();
        
        $debug_data = [
            'user_id' => $user_id,
            'table_info' => $rate_limiter->debug_check_tables(),
            'usage_stats' => $rate_limiter->get_user_usage_stats($user_id),
            'remaining_limits' => $rate_limiter->get_remaining_limits($user_id),
            'token_bucket_limit' => $rate_limiter->get_token_bucket_limit(),
            'max_requests_per_day' => $rate_limiter->get_max_requests_per_day(),
            'llm_client_rate_limiter' => $llm_client->debug_rate_limiter()
        ];
        
        wp_send_json_success($debug_data);
    }

    /**
     * Debug class loading
     */
    public function debug_class_loading() {
        // Check if user has admin capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }
        
        $debug_data = [
            'rate_limiter_class_exists' => class_exists('AI_BotKit\Core\Rate_Limiter'),
            'rate_limiter_file_exists' => file_exists(AI_BOTKIT_INCLUDES_DIR . 'core/class-rate-limiter.php'),
            'rate_limiter_file_readable' => is_readable(AI_BOTKIT_INCLUDES_DIR . 'core/class-rate-limiter.php'),
            'rate_limiter_file_content' => file_exists(AI_BOTKIT_INCLUDES_DIR . 'core/class-rate-limiter.php') ? 
                substr(file_get_contents(AI_BOTKIT_INCLUDES_DIR . 'core/class-rate-limiter.php'), 0, 200) : 'File not found',
            'includes_dir' => AI_BOTKIT_INCLUDES_DIR,
            'loaded_classes' => get_declared_classes(),
            'loaded_files' => get_included_files()
        ];
        
        wp_send_json_success($debug_data);
    }
    
    /**
     * Show database migration notice
     */
    public function show_database_migration_notice() {
        // Only show on KnowVault admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'ai-botkit') === false) {
            return;
        }
        
        // Check if migration is needed
        $migration_completed = get_option('knowvault_db_migration_completed', false);
        $old_tables_exist = \AI_BotKit\Utils\Table_Helper::check_old_tables_exist();
        
        // Only show if old tables exist and migration hasn't been completed
        if (!$old_tables_exist || $migration_completed) {
            return;
        }
        
        // Check if user dismissed the notice
        $dismissed = get_user_meta(get_current_user_id(), 'knowvault_migration_notice_dismissed', true);
        if ($dismissed) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible knowvault-migration-notice" data-notice="knowvault-migration">
            <p>
                <strong><?php esc_html_e('KnowVault Database Update Available', 'knowvault'); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Your database is using the old table structure. We recommend updating to the new structure for better performance and future compatibility.', 'knowvault'); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="knowvault-migrate-db">
                    <?php esc_html_e('Update Database', 'knowvault'); ?>
                </button>
                <button type="button" class="button" id="knowvault-dismiss-notice">
                    <?php esc_html_e('Dismiss', 'knowvault'); ?>
                </button>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#knowvault-migrate-db').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_html_e('Updating...', 'knowvault'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'knowvault_migrate_database',
                        nonce: '<?php echo wp_create_nonce('knowvault_migrate_db'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.knowvault-migration-notice').fadeOut(function() {
                                $(this).remove();
                            });
                            alert('<?php esc_html_e('Database updated successfully!', 'knowvault'); ?>');
                        } else {
                            alert('<?php esc_html_e('Migration failed:', 'knowvault'); ?> ' + (response.data.message || '<?php esc_html_e('Unknown error', 'knowvault'); ?>'));
                            $button.prop('disabled', false).text('<?php esc_html_e('Update Database', 'knowvault'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('An error occurred during migration.', 'knowvault'); ?>');
                        $button.prop('disabled', false).text('<?php esc_html_e('Update Database', 'knowvault'); ?>');
                    }
                });
            });
            
            $('#knowvault-dismiss-notice').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'knowvault_dismiss_migration_notice',
                        nonce: '<?php echo wp_create_nonce('knowvault_dismiss_notice'); ?>'
                    }
                });
                $('.knowvault-migration-notice').fadeOut();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle database migration via AJAX
     */
    public function handle_database_migration() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'knowvault_migrate_db')) {
            wp_send_json_error(['message' => __('Security check failed.', 'knowvault')]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'knowvault')]);
            return;
        }
        
        // Run migration
        $result = \AI_BotKit\Core\Database_Table_Migration::migrate_tables();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'tables_migrated' => $result['tables_migrated']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'errors' => $result['errors']
            ]);
        }
    }
    
    /**
     * Handle dismissing migration notice
     */
    public function handle_dismiss_notice() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'knowvault_dismiss_notice')) {
            wp_send_json_error(['message' => __('Security check failed.', 'knowvault')]);
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'knowvault')]);
            return;
        }
        
        // Dismiss notice for current user
        update_user_meta(get_current_user_id(), 'knowvault_migration_notice_dismissed', true);
        
        wp_send_json_success(['message' => __('Notice dismissed.', 'knowvault')]);
    }
    
    /**
     * Add custom menu icon for KnowVault
     */
    public function add_custom_menu_icon() {
        $icon_url = AI_BOTKIT_PLUGIN_URL . 'admin/knowvault_logo_sq.png';
        ?>
        <style type="text/css">
            #toplevel_page_ai-botkit .wp-menu-image img {
                width: 20px;
                height: 20px;
                padding: 0;
                opacity: 1;
            }
            #toplevel_page_ai-botkit .wp-menu-image {
                background-image: url('<?php echo esc_url($icon_url); ?>');
                background-size: 20px 20px;
                background-position: center;
                background-repeat: no-repeat;
            }
            #toplevel_page_ai-botkit .wp-menu-image:before {
                content: '';
            }
        </style>
        <?php
    }
} 
