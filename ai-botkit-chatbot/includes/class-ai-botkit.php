<?php
namespace AI_BotKit;

use AI_BotKit\Core\{
    Document_Loader,
    Text_Chunker,
    Embeddings_Generator,
    Vector_Database,
    Retriever,
    LLM_Client,
    RAG_Engine
};
use AI_BotKit\Utils\Cache_Manager;
use AI_BotKit\Admin\Admin;
use AI_BotKit\Public\{Ajax_Handler, Shortcode_Handler};
use AI_BotKit\Integration\{
    WordPress_Content
    // WooCommerce,
    // LearnDash,
    // User_Authentication,
    // REST_API
};
use AI_BotKit\Monitoring\{
    Health_Checks,
    // Performance_Monitor,
    // Logging_System,
    // Backup_Restore,
    Analytics
};

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package AI_BotKit
 */
class AI_BotKit {
    /**
     * Core components
     */
    private $rag_engine;
    private $cache_manager;
    private $llm_client;
    private $document_loader;
    private $text_chunker;
    private $embeddings_generator;
    private $vector_database;
    private $retriever;

    /**
     * Integration components
     * For future use
     */
    private $wp_content;
    // private $woocommerce;
    // private $learndash;
    // private $user_auth;
    // private $rest_api;

    /**
     * Monitoring components
     */
    private $health_checks;
    // private $performance_monitor;
    // private $logging_system;
    // private $backup_restore;
    private $analytics;

    /**
     * Interface components
     */
    private $admin;
    private $ajax_handler;
    private $shortcode_handler;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->setup_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_background_processes();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {

        // Core components
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-document-loader.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-text-chunker.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-embeddings-generator.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-vector-database.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-retriever.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-llm-client.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-rag-engine.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-rate-limiter.php';

        // Pinecone database class
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-pinecone-database.php';
        
        // Migration manager
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-migration-manager.php';

        // Integration components
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-wordpress-content.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-woocommerce-assistant.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-woocommerce.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-learndash.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-user-authentication.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-rest-api.php';

        // Monitoring components
        require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-health-checks.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-performance-monitor.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-logging-system.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-backup-restore.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-analytics.php';

        // Admin and public
        require_once AI_BOTKIT_INCLUDES_DIR . 'admin/class-admin.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'admin/class-ajax-handler.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'admin/class-rate-limiter.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'public/class-ajax-handler.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'public/class-shortcode-handler.php';

        // Utilities
        require_once AI_BOTKIT_INCLUDES_DIR . 'utils/class-cache-manager.php';

        // Models
        require_once AI_BOTKIT_INCLUDES_DIR . 'models/class-chatbot.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'models/class-conversation.php';

        // vendor dependencies
        require_once AI_BOTKIT_INCLUDES_DIR . 'vendor/autoload.php';

        // Core dependencies are autoloaded via composer
        $this->cache_manager = new Cache_Manager();
        $this->llm_client = new LLM_Client();
        
        // Initialize core components
        $this->document_loader = new Document_Loader();
        $this->text_chunker = new Text_Chunker();
        $this->embeddings_generator = new Embeddings_Generator($this->llm_client);
        $this->vector_database = new Vector_Database();
        $this->retriever = new Retriever(
            $this->vector_database,
            $this->embeddings_generator
        );

        // Initialize RAG Engine
        $this->rag_engine = new RAG_Engine(
            $this->document_loader,
            $this->text_chunker,
            $this->embeddings_generator,
            $this->vector_database,
            $this->retriever,
            $this->llm_client
        );
    }

    /**
     * Setup plugin components
     */
    private function setup_components(): void {
        // Initialize monitoring components
        // $this->logging_system = new Logging_System($this->cache_manager);
        // $this->performance_monitor = new Performance_Monitor($this->rag_engine, $this->cache_manager);
        $this->health_checks = new Health_Checks($this->rag_engine, $this->llm_client, $this->cache_manager);
        // $this->backup_restore = new Backup_Restore($this->cache_manager, $this->logging_system);
        $this->analytics = new Analytics($this->cache_manager);

        // Initialize integration components
        // $this->user_auth = new User_Authentication($this->rag_engine);
        $this->wp_content = new WordPress_Content($this->rag_engine, $this->document_loader);
        // $this->woocommerce = new WooCommerce($this->rag_engine, $this->document_loader, $this->cache_manager);
        // $this->learndash = new LearnDash($this->rag_engine, $this->document_loader);
        // $this->rest_api = new REST_API($this->rag_engine, $this->user_auth);

        // Initialize interface components
        $this->admin = new Admin($this->get_plugin_name(), $this->get_version(), $this->cache_manager, $this->rag_engine);
        $this->ajax_handler = new Ajax_Handler($this->rag_engine);
        $this->shortcode_handler = new Shortcode_Handler($this->rag_engine);
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks(): void {
        // Admin menu and pages
        add_action('admin_menu', [$this->admin, 'add_plugin_admin_menu']);
        add_action('admin_init', [$this->admin, 'register_settings']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_scripts']);

        // Health checks
        add_filter('debug_information', [$this->health_checks, 'add_debug_information']);
        add_filter('site_status_tests', [$this->health_checks, 'register_tests']);
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks(): void {
        // AJAX handlers
        add_action('wp_ajax_ai_botkit_chat_message', [$this->ajax_handler, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_ai_botkit_chat_message', [$this->ajax_handler, 'handle_chat_message']);
        add_action('wp_ajax_ai_botkit_stream_response', [$this->ajax_handler, 'handle_stream_response']);
        add_action('wp_ajax_nopriv_ai_botkit_stream_response', [$this->ajax_handler, 'handle_stream_response']);

        add_action('wp_footer', [$this->shortcode_handler, 'render_sitewide_chatbot']);

        // REST API
        // add_action('rest_api_init', [$this->rest_api, 'register_routes']);
    }

    /**
     * Initialize background processes
     */
    private function init_background_processes(): void {
        // Schedule health checks
        if (!wp_next_scheduled('ai_botkit_health_check')) {
            wp_schedule_event(time(), 'daily', 'ai_botkit_health_check');
        }

        // Schedule backup
        // if (!wp_next_scheduled('ai_botkit_backup')) {
        //     wp_schedule_event(time(), 'daily', 'ai_botkit_backup');
        // }

        // Schedule cleanup tasks
        // if (!wp_next_scheduled('ai_botkit_cleanup')) {
        //     wp_schedule_event(time(), 'daily', 'ai_botkit_cleanup');
        // }

        // Add action hooks for scheduled tasks
        add_action('ai_botkit_health_check', [$this->health_checks, 'run_health_check']);
        // add_action('ai_botkit_backup', [$this->backup_restore, 'create_scheduled_backup']);
        // add_action('ai_botkit_cleanup', [$this->backup_restore, 'cleanup_old_backups']);
    }

    /**
     * Run the plugin
     */
    public function run(): void {
        // Load plugin text domain
        // load_plugin_textdomain(
        //     'ai-botkit-for-lead-generation',
        //     false,
        //     dirname(plugin_basename(dirname(__FILE__))) . '/languages'
        // );
        
        // Initialize components
        do_action('ai_botkit_init', $this);
    }


    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return string The name of the plugin.
     */
    public function get_plugin_name(): string {
        return 'ai-botkit-for-lead-generation';
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version(): string {
        return defined('AI_BOTKIT_VERSION') ? AI_BOTKIT_VERSION : '1.0.0';
    }
} 