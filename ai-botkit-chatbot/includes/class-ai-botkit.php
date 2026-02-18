<?php
namespace AI_BotKit;

use AI_BotKit\Core\{
    Document_Loader,
    Text_Chunker,
    Embeddings_Generator,
    Vector_Database,
    Retriever,
    LLM_Client,
    RAG_Engine,
    AIBotKit_Performance_Manager
};
use AI_BotKit\Core\Unified_Cache_Manager;
use AI_BotKit\Admin\Admin;
use AI_BotKit\Public\{Ajax_Handler, Shortcode_Handler};
use AI_BotKit\Integration\{
    WordPress_Content
};
use AI_BotKit\Monitoring\{
    Health_Checks,
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
     */
    private $user_auth;
    private $wp_content;
    private $woocommerce;
    private $learndash;
    private $rest_api;

    /**
     * Monitoring components
     */
    private $health_checks;
    private $analytics;

    /**
     * Interface components
     */
    private $admin;
    private $ajax_handler;
    private $shortcode_handler;
    
    /**
     * Performance optimization components
     */
    private $performance_manager;
    private $performance_integration;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->check_and_run_migrations();
        $this->setup_components();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->init_background_processes();
    }

    /**
     * Check and run any pending database migrations.
     *
     * This ensures migrations run even when the plugin is updated
     * without being deactivated/reactivated.
     */
    private function check_and_run_migrations(): void {
        // Only run in admin or during AJAX to avoid slowing down frontend
        if ( ! is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Run Phase 2 migrations if needed
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-phase2-migration.php';
        $phase2_migration = new \AI_BotKit\Core\Phase2_Migration();

        if ( $phase2_migration->is_migration_needed() ) {
            $result = $phase2_migration->run_migrations();

            if ( ! $result['success'] && ! empty( $result['errors'] ) ) {
                error_log( 'AI BotKit Phase 2 Migration Errors: ' . implode( ', ', $result['errors'] ) );
            }
        }
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
        
        // Performance optimization components
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-ai-botkit-performance-manager.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-unified-cache-manager.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-unified-performance-monitor.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-unified-error-handler.php';
        
        // Utils
        require_once AI_BOTKIT_INCLUDES_DIR . 'utils/class-table-helper.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-performance-configuration-manager.php';

        // Pinecone database class
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-pinecone-database.php';
        
        // Migration manager
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-migration-manager.php';

        // Integration components
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-wordpress-content.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-woocommerce-assistant.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-woocommerce.php';
        // LearnDash is loaded conditionally in setup_components() when LEARNDASH_VERSION is defined.
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-user-authentication.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-rest-api.php';

        // Monitoring components
        require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-health-checks.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-performance-monitor.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-logging-system.php';
        // require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-backup-restore.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'monitoring/class-analytics.php';

        // Admin and public
        require_once AI_BOTKIT_INCLUDES_DIR . 'admin/class-admin.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'admin/class-ajax-handler.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'public/class-ajax-handler.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'public/class-shortcode-handler.php';

        // Core classes that need to be included FIRST
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-cache-configuration.php';
        
        // Performance optimization classes
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-database-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-content-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-ajax-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-migration-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-admin-interface-optimizer.php';
        
        // Additional optimization dependencies
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-wordpress-function-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-knowledge-base-interface-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-my-bots-interface-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-ajax-request-optimizer.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-ajax-response-compressor.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-content-processing-monitor.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-database-migration.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-unified-performance-monitor.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-unified-error-handler.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'core/class-performance-configuration-manager.php';
        
        // Utilities
        require_once AI_BOTKIT_INCLUDES_DIR . 'utils/class-cache-manager.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'utils/class-migration-logger.php';

        // Models
        require_once AI_BOTKIT_INCLUDES_DIR . 'models/class-chatbot.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'models/class-conversation.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'models/class-template.php';

        // Features (Phase 2)
        require_once AI_BOTKIT_INCLUDES_DIR . 'features/class-template-manager.php';
        require_once AI_BOTKIT_INCLUDES_DIR . 'features/class-template-ajax-handler.php';

        // No external dependencies - using lightweight PDF extraction

        // Core dependencies are autoloaded via composer
        $this->cache_manager = new \AI_BotKit\Core\Unified_Cache_Manager();
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
        $this->user_auth = new Integration\User_Authentication($this->rag_engine);
        $this->wp_content = new Integration\WordPress_Content($this->rag_engine, $this->document_loader);
        $this->woocommerce = new Integration\WooCommerce($this->rag_engine, $this->document_loader, $this->cache_manager);

        // Initialize LearnDash integration
        if (defined('LEARNDASH_VERSION')) {
            require_once AI_BOTKIT_INCLUDES_DIR . 'integration/class-learndash.php';
            $this->learndash = new Integration\LearnDash($this->rag_engine, $this->document_loader);
        }

        $this->rest_api = new Integration\REST_API($this->rag_engine, $this->user_auth);

        // Initialize interface components
        $this->admin = new Admin($this->get_plugin_name(), $this->get_version(), $this->cache_manager, $this->rag_engine);
        $this->ajax_handler = new Ajax_Handler($this->rag_engine);
        $this->shortcode_handler = new Shortcode_Handler($this->rag_engine);
        
        // Initialize performance optimization system
        $this->performance_manager = AIBotKit_Performance_Manager::get_instance();
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks(): void {
        // Admin hooks are registered in the Admin class to avoid duplicates
        // This method is kept for future admin-specific hooks if needed
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks(): void {
        // AJAX handlers are registered in the public AJAX handler class
        // to avoid duplicate registrations

        add_action('wp_footer', [$this->shortcode_handler, 'render_sitewide_chatbot']);
    }

    /**
     * Initialize background processes
     */
    private function init_background_processes(): void {
        // Schedule health checks
        if (!wp_next_scheduled('ai_botkit_health_check')) {
            wp_schedule_event(time(), 'daily', 'ai_botkit_health_check');
        }

        // Schedule document processing
        if (!wp_next_scheduled('ai_botkit_process_queue')) {
            wp_schedule_event(time(), '5min', 'ai_botkit_process_queue');
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
        add_action('ai_botkit_process_queue', [$this->rag_engine, 'process_queue']);
        // add_action('ai_botkit_backup', [$this->backup_restore, 'create_scheduled_backup']);
        // add_action('ai_botkit_cleanup', [$this->backup_restore, 'cleanup_old_backups']);
    }

    /**
     * Run the plugin
     */
    public function run(): void {
        // Load plugin text domain.
        load_plugin_textdomain(
            'knowvault',
            false,
            dirname(plugin_basename(dirname(__FILE__))) . '/languages'
        );

        // Initialize components.
        do_action('ai_botkit_init', $this);
    }


    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return string The name of the plugin.
     */
    public function get_plugin_name(): string {
        return 'knowvault';
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version(): string {
        return defined('AI_BOTKIT_VERSION') ? AI_BOTKIT_VERSION : '1.0.0';
    }

    /**
     * Get LearnDash integration instance
     *
     * @return Integration\LearnDash|null The LearnDash integration instance or null if not initialized
     */
    public function get_learndash_integration() {
        return $this->learndash ?? null;
    }
} 