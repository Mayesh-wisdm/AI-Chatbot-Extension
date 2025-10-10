<?php
namespace AI_BotKit\Core;

/**
 * Cache Invalidation Class
 * 
 * Handles intelligent cache invalidation based on data changes
 * and WordPress hooks to maintain cache consistency.
 */
class Cache_Invalidation {
    
    /**
     * Enhanced cache manager
     */
    private $cache_manager;
    
    /**
     * AJAX cache manager
     */
    private $ajax_cache_manager;
    
    /**
     * Migration cache manager
     */
    private $migration_cache_manager;
    
    /**
     * Cache configuration
     */
    private $config;
    
    /**
     * Initialize cache invalidation
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->ajax_cache_manager = new Unified_Cache_Manager();
        $this->migration_cache_manager = new Unified_Cache_Manager();
        $this->config = Cache_Configuration::get_instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for automatic cache invalidation
     */
    private function init_hooks() {
        // Post-related hooks
        add_action('save_post', [$this, 'invalidate_on_post_save'], 10, 2);
        add_action('delete_post', [$this, 'invalidate_on_post_delete'], 10, 2);
        add_action('wp_trash_post', [$this, 'invalidate_on_post_delete'], 10, 2);
        add_action('untrash_post', [$this, 'invalidate_on_post_save'], 10, 2);
        
        // Document-related hooks
        add_action('ai_botkit_document_processed', [$this, 'invalidate_document_caches']);
        add_action('ai_botkit_document_deleted', [$this, 'invalidate_document_caches']);
        
        // Chatbot-related hooks
        add_action('ai_botkit_chatbot_created', [$this, 'invalidate_chatbot_caches']);
        add_action('ai_botkit_chatbot_updated', [$this, 'invalidate_chatbot_caches']);
        add_action('ai_botkit_chatbot_deleted', [$this, 'invalidate_chatbot_caches']);
        
        // Migration-related hooks
        add_action('ai_botkit_migration_started', [$this, 'invalidate_migration_caches']);
        add_action('ai_botkit_migration_completed', [$this, 'invalidate_migration_caches']);
        add_action('ai_botkit_migration_failed', [$this, 'invalidate_migration_caches']);
        
        // Settings-related hooks
        add_action('ai_botkit_settings_updated', [$this, 'invalidate_settings_caches']);
        
        // Pinecone-related hooks
        add_action('ai_botkit_pinecone_configured', [$this, 'invalidate_pinecone_caches']);
        add_action('ai_botkit_pinecone_cleared', [$this, 'invalidate_pinecone_caches']);
    }
    
    /**
     * Invalidate caches when post is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function invalidate_on_post_save($post_id, $post) {
        if (!$this->config->get_invalidation_settings()['auto_invalidate_on_post_save']) {
            return;
        }
        
        // Add delay if configured
        $delay = $this->config->get_invalidation_settings()['invalidation_delay'];
        if ($delay > 0) {
            wp_schedule_single_event(time() + $delay, 'ai_botkit_delayed_cache_invalidation', [
                'type' => 'post_save',
                'post_id' => $post_id,
                'post_type' => $post->post_type
            ]);
            return;
        }
        
        $this->invalidate_post_type_caches($post->post_type);
        $this->invalidate_document_caches();
        $this->invalidate_migration_caches();
    }
    
    /**
     * Invalidate caches when post is deleted
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function invalidate_on_post_delete($post_id, $post) {
        if (!$this->config->get_invalidation_settings()['auto_invalidate_on_post_delete']) {
            return;
        }
        
        // Add delay if configured
        $delay = $this->config->get_invalidation_settings()['invalidation_delay'];
        if ($delay > 0) {
            wp_schedule_single_event(time() + $delay, 'ai_botkit_delayed_cache_invalidation', [
                'type' => 'post_delete',
                'post_id' => $post_id,
                'post_type' => $post->post_type
            ]);
            return;
        }
        
        $this->invalidate_post_type_caches($post->post_type);
        $this->invalidate_document_caches();
        $this->invalidate_migration_caches();
    }
    
    /**
     * Invalidate document-related caches
     */
    public function invalidate_document_caches() {
        $this->cache_manager->invalidate_document_caches();
        $this->ajax_cache_manager->invalidate_knowledge_base_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('document_caches');
    }
    
    /**
     * Invalidate chatbot-related caches
     */
    public function invalidate_chatbot_caches() {
        $this->cache_manager->invalidate_chatbot_caches();
        $this->ajax_cache_manager->invalidate_chatbot_caches();
        
        // Log invalidation
        $this->log_invalidation('chatbot_caches');
    }
    
    /**
     * Invalidate migration-related caches
     */
    public function invalidate_migration_caches() {
        $this->cache_manager->invalidate_migration_caches();
        $this->ajax_cache_manager->invalidate_migration_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('migration_caches');
    }
    
    /**
     * Invalidate settings-related caches
     */
    public function invalidate_settings_caches() {
        if (!$this->config->get_invalidation_settings()['auto_invalidate_on_settings_change']) {
            return;
        }
        
        $this->cache_manager->invalidate_settings_caches();
        $this->ajax_cache_manager->invalidate_pinecone_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('settings_caches');
    }
    
    /**
     * Invalidate Pinecone-related caches
     */
    public function invalidate_pinecone_caches() {
        $this->ajax_cache_manager->invalidate_pinecone_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('pinecone_caches');
    }
    
    /**
     * Invalidate post type related caches
     * 
     * @param string|null $post_type Specific post type to invalidate
     */
    public function invalidate_post_type_caches($post_type = null) {
        $this->cache_manager->invalidate_post_type_caches($post_type);
        $this->ajax_cache_manager->invalidate_knowledge_base_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('post_type_caches', $post_type);
    }
    
    /**
     * Invalidate all caches
     */
    public function invalidate_all_caches() {
        $this->cache_manager->clear_all();
        $this->ajax_cache_manager->clear_all_caches();
        $this->migration_cache_manager->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('all_caches');
    }
    
    /**
     * Invalidate caches for specific content type
     * 
     * @param string $content_type Content type
     */
    public function invalidate_content_type_caches($content_type) {
        $this->invalidate_post_type_caches($content_type);
        $this->invalidate_document_caches();
        $this->invalidate_migration_caches();
        
        // Log invalidation
        $this->log_invalidation('content_type_caches', $content_type);
    }
    
    /**
     * Invalidate caches for specific user
     * 
     * @param int $user_id User ID
     */
    public function invalidate_user_caches($user_id) {
        // Invalidate user-specific caches
        $this->cache_manager->delete("user_data_{$user_id}");
        $this->ajax_cache_manager->invalidate_cache('ai_botkit_get_user_data', ['user_id' => $user_id]);
        
        // Log invalidation
        $this->log_invalidation('user_caches', $user_id);
    }
    
    /**
     * Invalidate caches for specific chatbot
     * 
     * @param int $chatbot_id Chatbot ID
     */
    public function invalidate_chatbot_specific_caches($chatbot_id) {
        $this->cache_manager->delete("chatbot_data_{$chatbot_id}");
        $this->ajax_cache_manager->invalidate_cache('ai_botkit_get_chatbot_data', ['chatbot_id' => $chatbot_id]);
        
        // Log invalidation
        $this->log_invalidation('chatbot_specific_caches', $chatbot_id);
    }
    
    /**
     * Invalidate caches for specific document
     * 
     * @param int $document_id Document ID
     */
    public function invalidate_document_specific_caches($document_id) {
        $this->cache_manager->delete("document_data_{$document_id}");
        $this->ajax_cache_manager->invalidate_cache('ai_botkit_get_document_data', ['document_id' => $document_id]);
        
        // Log invalidation
        $this->log_invalidation('document_specific_caches', $document_id);
    }
    
    /**
     * Schedule delayed cache invalidation
     * 
     * @param string $type Invalidation type
     * @param mixed $data Data for invalidation
     * @param int $delay Delay in seconds
     */
    public function schedule_delayed_invalidation($type, $data, $delay = 0) {
        wp_schedule_single_event(time() + $delay, 'ai_botkit_delayed_cache_invalidation', [
            'type' => $type,
            'data' => $data
        ]);
    }
    
    /**
     * Handle delayed cache invalidation
     * 
     * @param array $args Invalidation arguments
     */
    public function handle_delayed_invalidation($args) {
        $type = $args['type'] ?? '';
        $data = $args['data'] ?? null;
        
        switch ($type) {
            case 'post_save':
                $this->invalidate_post_type_caches($data['post_type'] ?? null);
                break;
            case 'post_delete':
                $this->invalidate_post_type_caches($data['post_type'] ?? null);
                break;
            case 'document_processed':
                $this->invalidate_document_caches();
                break;
            case 'migration_completed':
                $this->invalidate_migration_caches();
                break;
            case 'settings_updated':
                $this->invalidate_settings_caches();
                break;
        }
    }
    
    /**
     * Get invalidation statistics
     * 
     * @return array Invalidation statistics
     */
    public function get_invalidation_statistics() {
        return [
            'total_invalidations' => get_option('ai_botkit_cache_invalidations_total', 0),
            'invalidations_by_type' => get_option('ai_botkit_cache_invalidations_by_type', []),
            'last_invalidation' => get_option('ai_botkit_cache_last_invalidation', null),
            'auto_invalidation_enabled' => $this->config->get_invalidation_settings()['auto_invalidate_on_post_save']
        ];
    }
    
    /**
     * Log cache invalidation
     * 
     * @param string $type Invalidation type
     * @param mixed $data Additional data
     */
    private function log_invalidation($type, $data = null) {
        // Update total count
        $total = get_option('ai_botkit_cache_invalidations_total', 0);
        update_option('ai_botkit_cache_invalidations_total', $total + 1);
        
        // Update type-specific count
        $by_type = get_option('ai_botkit_cache_invalidations_by_type', []);
        $by_type[$type] = ($by_type[$type] ?? 0) + 1;
        update_option('ai_botkit_cache_invalidations_by_type', $by_type);
        
        // Update last invalidation
        update_option('ai_botkit_cache_last_invalidation', [
            'type' => $type,
            'data' => $data,
            'timestamp' => current_time('mysql')
        ]);
        
        // Log to error log if debug is enabled
        if ($this->config->get_performance_settings()['cache_debug_enabled']) {
        }
    }
    
    /**
     * Clear invalidation statistics
     */
    public function clear_invalidation_statistics() {
        delete_option('ai_botkit_cache_invalidations_total');
        delete_option('ai_botkit_cache_invalidations_by_type');
        delete_option('ai_botkit_cache_last_invalidation');
    }
}
