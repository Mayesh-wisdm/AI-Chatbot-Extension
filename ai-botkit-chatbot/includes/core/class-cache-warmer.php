<?php
namespace AI_BotKit\Core;

/**
 * Cache Warmer Class
 * 
 * Preloads frequently accessed data into cache to improve
 * performance and reduce cache misses.
 */
class Cache_Warmer {
    
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
     * Initialize the cache warmer
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->ajax_cache_manager = new Unified_Cache_Manager();
        $this->migration_cache_manager = new Unified_Cache_Manager();
        $this->config = Cache_Configuration::get_instance();
    }
    
    /**
     * Warm cache with frequently accessed data
     * 
     * @return array Warming result
     */
    public function warm_cache() {
        if (!$this->config->get_performance_settings()['cache_warming_enabled']) {
            return [
                'success' => false,
                'message' => 'Cache warming is disabled',
                'items_warmed' => 0
            ];
        }
        
        $items_warmed = 0;
        $errors = [];
        
        try {
            // Warm post type counts
            $items_warmed += $this->warm_post_type_counts();
            
            // Warm public post types
            $items_warmed += $this->warm_public_post_types();
            
            // Warm document statistics
            $items_warmed += $this->warm_document_statistics();
            
            // Warm chatbot statistics
            $items_warmed += $this->warm_chatbot_statistics();
            
            // Warm migration status
            $items_warmed += $this->warm_migration_status();
            
            // Warm AJAX responses
            $items_warmed += $this->warm_ajax_responses();
            
            // Warm content types
            $items_warmed += $this->warm_content_types();
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'items_warmed' => $items_warmed,
            'errors' => $errors
        ];
    }
    
    /**
     * Warm post type counts
     * 
     * @return int Number of items warmed
     */
    private function warm_post_type_counts() {
        $items_warmed = 0;
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            $this->cache_manager->get_post_type_count($post_type, function() use ($post_type) {
                return WordPress_Function_Optimizer::get_post_type_count($post_type);
            });
            $items_warmed++;
        }
        
        return $items_warmed;
    }
    
    /**
     * Warm public post types
     * 
     * @return int Number of items warmed
     */
    private function warm_public_post_types() {
        $this->cache_manager->get_public_post_types(function() {
            return get_post_types(['public' => true], 'objects');
        });
        
        return 1;
    }
    
    /**
     * Warm document statistics
     * 
     * @return int Number of items warmed
     */
    private function warm_document_statistics() {
        $this->cache_manager->get_document_statistics(function() {
            $optimizer = new Database_Optimizer();
            return $optimizer->get_document_statistics();
        });
        
        return 1;
    }
    
    /**
     * Warm chatbot statistics
     * 
     * @return int Number of items warmed
     */
    private function warm_chatbot_statistics() {
        $this->cache_manager->get_chatbot_statistics(function() {
            $optimizer = new Database_Optimizer();
            return $optimizer->get_chatbot_statistics();
        });
        
        return 1;
    }
    
    /**
     * Warm migration status
     * 
     * @return int Number of items warmed
     */
    private function warm_migration_status() {
        $this->migration_cache_manager->get_migration_status(function() {
            return $this->migration_cache_manager->get_migration_status();
        });
        
        return 1;
    }
    
    /**
     * Warm AJAX responses
     * 
     * @return int Number of items warmed
     */
    private function warm_ajax_responses() {
        $items_warmed = 0;
        
        // Warm knowledge base data
        $this->ajax_cache_manager->cache_knowledge_base_data(['type' => 'all', 'page' => 1], function() {
            return ['documents' => [], 'total_documents' => 0, 'total_pages' => 0];
        });
        $items_warmed++;
        
        // Warm migration status
        $this->ajax_cache_manager->cache_migration_status([], function() {
            return ['local_stats' => [], 'pinecone_stats' => [], 'content_types' => []];
        });
        $items_warmed++;
        
        // Warm content types
        $this->ajax_cache_manager->cache_content_types([], function() {
            return [];
        });
        $items_warmed++;
        
        return $items_warmed;
    }
    
    /**
     * Warm content types
     * 
     * @return int Number of items warmed
     */
    private function warm_content_types() {
        $this->migration_cache_manager->get_content_types(function() {
            return $this->migration_cache_manager->get_content_types();
        });
        
        return 1;
    }
    
    /**
     * Warm cache for specific user
     * 
     * @param int $user_id User ID
     * @return array Warming result
     */
    public function warm_user_cache($user_id) {
        $items_warmed = 0;
        $errors = [];
        
        try {
            // Warm user-specific data
            $this->cache_manager->remember("user_data_{$user_id}", function() use ($user_id) {
                return get_userdata($user_id);
            }, 300);
            $items_warmed++;
            
            // Warm user's chatbots
            $this->cache_manager->remember("user_chatbots_{$user_id}", function() use ($user_id) {
                global $wpdb;
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_botkit_chatbots WHERE user_id = %d",
                    $user_id
                ), ARRAY_A);
            }, 300);
            $items_warmed++;
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'items_warmed' => $items_warmed,
            'errors' => $errors
        ];
    }
    
    /**
     * Warm cache for specific chatbot
     * 
     * @param int $chatbot_id Chatbot ID
     * @return array Warming result
     */
    public function warm_chatbot_cache($chatbot_id) {
        $items_warmed = 0;
        $errors = [];
        
        try {
            // Warm chatbot data
            $this->cache_manager->remember("chatbot_data_{$chatbot_id}", function() use ($chatbot_id) {
                global $wpdb;
                return $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_botkit_chatbots WHERE id = %d",
                    $chatbot_id
                ), ARRAY_A);
            }, 300);
            $items_warmed++;
            
            // Warm chatbot documents
            $this->cache_manager->remember("chatbot_documents_{$chatbot_id}", function() use ($chatbot_id) {
                global $wpdb;
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT d.* FROM {$wpdb->prefix}ai_botkit_documents d
                     JOIN {$wpdb->prefix}ai_botkit_content_relationships r ON d.id = r.target_id
                     WHERE r.source_id = %d AND r.source_type = 'chatbot'",
                    $chatbot_id
                ), ARRAY_A);
            }, 300);
            $items_warmed++;
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'items_warmed' => $items_warmed,
            'errors' => $errors
        ];
    }
    
    /**
     * Warm cache for specific content type
     * 
     * @param string $content_type Content type
     * @return array Warming result
     */
    public function warm_content_type_cache($content_type) {
        $items_warmed = 0;
        $errors = [];
        
        try {
            // Warm content type count
            $this->cache_manager->get_post_type_count($content_type, function() use ($content_type) {
                return WordPress_Function_Optimizer::get_post_type_count($content_type);
            });
            $items_warmed++;
            
            // Warm content type documents
            $this->cache_manager->remember("content_type_documents_{$content_type}", function() use ($content_type) {
                global $wpdb;
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_botkit_documents WHERE source_type = %s",
                    $content_type
                ), ARRAY_A);
            }, 300);
            $items_warmed++;
            
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return [
            'success' => empty($errors),
            'items_warmed' => $items_warmed,
            'errors' => $errors
        ];
    }
    
    /**
     * Schedule cache warming
     * 
     * @param string $when When to warm cache ('immediate', 'hourly', 'daily')
     * @return bool True on success, false on failure
     */
    public function schedule_cache_warming($when = 'immediate') {
        switch ($when) {
            case 'immediate':
                return $this->warm_cache()['success'];
                
            case 'hourly':
                if (!wp_next_scheduled('ai_botkit_hourly_cache_warming')) {
                    return wp_schedule_event(time(), 'hourly', 'ai_botkit_hourly_cache_warming');
                }
                break;
                
            case 'daily':
                if (!wp_next_scheduled('ai_botkit_daily_cache_warming')) {
                    return wp_schedule_event(time(), 'daily', 'ai_botkit_daily_cache_warming');
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Get cache warming statistics
     * 
     * @return array Warming statistics
     */
    public function get_warming_statistics() {
        return [
            'last_warming' => get_option('ai_botkit_cache_last_warming', null),
            'total_warmings' => get_option('ai_botkit_cache_total_warmings', 0),
            'items_warmed_last' => get_option('ai_botkit_cache_items_warmed_last', 0),
            'warming_enabled' => $this->config->get_performance_settings()['cache_warming_enabled'],
            'scheduled_warmings' => [
                'hourly' => wp_next_scheduled('ai_botkit_hourly_cache_warming') !== false,
                'daily' => wp_next_scheduled('ai_botkit_daily_cache_warming') !== false
            ]
        ];
    }
    
    /**
     * Clear cache warming statistics
     */
    public function clear_warming_statistics() {
        delete_option('ai_botkit_cache_last_warming');
        delete_option('ai_botkit_cache_total_warmings');
        delete_option('ai_botkit_cache_items_warmed_last');
    }
    
    /**
     * Update warming statistics
     * 
     * @param int $items_warmed Number of items warmed
     */
    private function update_warming_statistics($items_warmed) {
        update_option('ai_botkit_cache_last_warming', current_time('mysql'));
        
        $total = get_option('ai_botkit_cache_total_warmings', 0);
        update_option('ai_botkit_cache_total_warmings', $total + 1);
        
        update_option('ai_botkit_cache_items_warmed_last', $items_warmed);
    }
}
