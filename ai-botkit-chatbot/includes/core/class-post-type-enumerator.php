<?php
namespace AI_BotKit\Core;

/**
 * Post Type Enumerator Class
 * 
 * Optimizes post type enumeration and filtering for improved performance,
 * especially for sites with heavy post types like The Events Calendar.
 */
class Post_Type_Enumerator {
    
    /**
     * Enhanced cache manager
     */
    private $cache_manager;
    
    /**
     * WordPress function optimizer
     */
    private $wp_optimizer;
    
    /**
     * Initialize the post type enumerator
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->wp_optimizer = new WordPress_Function_Optimizer();
    }
    
    /**
     * Get optimized post types for migration
     * 
     * @param array $excluded_types Additional types to exclude
     * @return array Optimized post types
     */
    public function get_optimized_post_types($excluded_types = []) {
        $cache_key = 'optimized_post_types_' . md5(serialize($excluded_types));
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $post_types = $this->enumerate_post_types($excluded_types);
        $this->cache_manager->set($cache_key, $post_types, 600); // 10 minutes
        
        return $post_types;
    }
    
    /**
     * Enumerate post types with optimization
     * 
     * @param array $excluded_types Additional types to exclude
     * @return array Enumerated post types
     */
    private function enumerate_post_types($excluded_types = []) {
        // Get all public post types
        $all_post_types = get_post_types(['public' => true], 'objects');
        
        // Default exclusions for heavy post types
        $default_exclusions = $this->get_default_exclusions();
        $all_exclusions = array_merge($default_exclusions, $excluded_types);
        
        $optimized_types = [];
        $excluded_count = 0;
        
        foreach ($all_post_types as $post_type => $post_type_obj) {
            // Skip excluded types
            if (in_array($post_type, $all_exclusions)) {
                $excluded_count++;
                continue;
            }
            
            // Get post count with optimized query
            $count = $this->wp_optimizer->get_post_type_count($post_type);
            
            // Only include types with content
            if ($count > 0) {
                $optimized_types[$post_type] = [
                    'name' => $post_type_obj->labels->singular_name,
                    'count' => $count,
                    'optimization_level' => $this->get_optimization_level($count),
                    'processing_strategy' => $this->get_processing_strategy($post_type, $count)
                ];
            }
        }
        
        return [
            'post_types' => $optimized_types,
            'total_types' => count($all_post_types),
            'optimized_types' => count($optimized_types),
            'excluded_types' => $excluded_count,
            'exclusions' => $all_exclusions
        ];
    }
    
    /**
     * Get default exclusions for heavy post types
     * 
     * @return array Default exclusions
     */
    private function get_default_exclusions() {
        return [
            'tribe_events',      // The Events Calendar events
            'tribe_venue',       // The Events Calendar venues
            'tribe_organizer',   // The Events Calendar organizers
            'revision',          // WordPress revisions
            'nav_menu_item',     // Navigation menu items
            'custom_css',        // Custom CSS
            'customize_changeset', // Customizer changes
            'attachment',        // Media attachments
            'product_variation', // WooCommerce variations
            'shop_order',        // WooCommerce orders
            'shop_coupon',       // WooCommerce coupons
            'shop_subscription', // WooCommerce subscriptions
            'wc_order_status',   // WooCommerce order statuses
            'wc_webhook',        // WooCommerce webhooks
            'wpcf7_contact_form', // Contact Form 7
            'acf-field-group',   // Advanced Custom Fields
            'acf-field',         // Advanced Custom Fields
            'elementor_library', // Elementor library
            'elementor_snippet', // Elementor snippets
        ];
    }
    
    /**
     * Get optimization level based on post count
     * 
     * @param int $count Post count
     * @return string Optimization level
     */
    private function get_optimization_level($count) {
        if ($count > 10000) {
            return 'high';
        } elseif ($count > 1000) {
            return 'medium';
        } elseif ($count > 100) {
            return 'low';
        } else {
            return 'minimal';
        }
    }
    
    /**
     * Get processing strategy for post type
     * 
     * @param string $post_type Post type name
     * @param int $count Post count
     * @return string Processing strategy
     */
    private function get_processing_strategy($post_type, $count) {
        if ($count > 10000) {
            return 'batch_processing_large';
        } elseif ($count > 1000) {
            return 'batch_processing_medium';
        } elseif ($count > 100) {
            return 'chunked_processing';
        } else {
            return 'standard_processing';
        }
    }
    
    /**
     * Filter heavy post types
     * 
     * @param array $post_types Post types to filter
     * @return array Filtered post types
     */
    public function filter_heavy_post_types($post_types) {
        $heavy_types = $this->get_default_exclusions();
        
        return array_filter($post_types, function($post_type) use ($heavy_types) {
            return !in_array($post_type, $heavy_types);
        });
    }
    
    /**
     * Get post types with counts
     * 
     * @param array $post_types Post types to get counts for
     * @return array Post types with counts
     */
    public function get_post_types_with_counts($post_types) {
        $cache_key = 'post_types_with_counts_' . md5(serialize($post_types));
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Get counts in single optimized query
        $counts = $this->wp_optimizer->get_batch_post_type_counts($post_types);
        
        $result = [];
        foreach ($post_types as $post_type) {
            $result[$post_type] = [
                'count' => $counts[$post_type] ?? 0,
                'optimization_level' => $this->get_optimization_level($counts[$post_type] ?? 0)
            ];
        }
        
        $this->cache_manager->set($cache_key, $result, 300); // 5 minutes
        
        return $result;
    }
    
    /**
     * Get post type statistics
     * 
     * @return array Post type statistics
     */
    public function get_post_type_statistics() {
        $cache_key = 'post_type_statistics';
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_post_type_statistics();
        $this->cache_manager->set($cache_key, $stats, 600); // 10 minutes
        
        return $stats;
    }
    
    /**
     * Generate post type statistics
     * 
     * @return array Post type statistics
     */
    private function generate_post_type_statistics() {
        $all_post_types = get_post_types(['public' => true]);
        $optimized_types = $this->get_optimized_post_types();
        $excluded_types = $this->get_default_exclusions();
        
        $total_posts = 0;
        $optimized_posts = 0;
        $excluded_posts = 0;
        
        // Get counts for all types
        $all_counts = $this->wp_optimizer->get_batch_post_type_counts($all_post_types);
        
        foreach ($all_counts as $post_type => $count) {
            $total_posts += $count;
            
            if (in_array($post_type, $excluded_types)) {
                $excluded_posts += $count;
            } else {
                $optimized_posts += $count;
            }
        }
        
        return [
            'total_post_types' => count($all_post_types),
            'optimized_post_types' => count($optimized_types['post_types']),
            'excluded_post_types' => count($excluded_types),
            'total_posts' => $total_posts,
            'optimized_posts' => $optimized_posts,
            'excluded_posts' => $excluded_posts,
            'optimization_ratio' => $total_posts > 0 ? ($optimized_posts / $total_posts) * 100 : 0,
            'exclusion_ratio' => $total_posts > 0 ? ($excluded_posts / $total_posts) * 100 : 0,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get post type performance metrics
     * 
     * @return array Performance metrics
     */
    public function get_performance_metrics() {
        $start_time = microtime(true);
        
        $optimized_types = $this->get_optimized_post_types();
        
        $end_time = microtime(true);
        $enumeration_time = $end_time - $start_time;
        
        return [
            'enumeration_time' => $enumeration_time,
            'types_enumerated' => count($optimized_types['post_types']),
            'excluded_types' => count($optimized_types['exclusions']),
            'performance_score' => $this->calculate_performance_score($enumeration_time),
            'optimization_effectiveness' => $this->calculate_optimization_effectiveness($optimized_types)
        ];
    }
    
    /**
     * Calculate performance score
     * 
     * @param float $enumeration_time Enumeration time in seconds
     * @return int Performance score (0-100)
     */
    private function calculate_performance_score($enumeration_time) {
        // Score based on enumeration time
        if ($enumeration_time < 0.1) {
            return 100;
        } elseif ($enumeration_time < 0.5) {
            return 80;
        } elseif ($enumeration_time < 1.0) {
            return 60;
        } else {
            return 40;
        }
    }
    
    /**
     * Calculate optimization effectiveness
     * 
     * @param array $optimized_types Optimized types data
     * @return float Optimization effectiveness percentage
     */
    private function calculate_optimization_effectiveness($optimized_types) {
        $total_types = $optimized_types['total_types'];
        $optimized_count = $optimized_types['optimized_types'];
        
        if ($total_types === 0) {
            return 0;
        }
        
        return ($optimized_count / $total_types) * 100;
    }
    
    /**
     * Get post type recommendations
     * 
     * @return array Post type recommendations
     */
    public function get_post_type_recommendations() {
        $optimized_types = $this->get_optimized_post_types();
        $recommendations = [];
        
        // Check for heavy post types
        foreach ($optimized_types['post_types'] as $post_type => $data) {
            if ($data['count'] > 10000) {
                $recommendations[] = [
                    'type' => 'heavy_post_type',
                    'post_type' => $post_type,
                    'count' => $data['count'],
                    'message' => "Post type '{$post_type}' has {$data['count']} posts. Consider batch processing.",
                    'recommendation' => 'Enable batch processing for this post type'
                ];
            }
        }
        
        // Check for excluded types
        if (count($optimized_types['exclusions']) > 0) {
            $recommendations[] = [
                'type' => 'excluded_types',
                'excluded_count' => count($optimized_types['exclusions']),
                'message' => count($optimized_types['exclusions']) . ' post types are excluded by default for performance.',
                'recommendation' => 'Review excluded types if manual processing is needed'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear post type enumeration cache
     */
    public function clear_enumeration_cache() {
        $this->cache_manager->delete_many([
            'optimized_post_types',
            'post_types_with_counts',
            'post_type_statistics'
        ]);
    }
    
    /**
     * Get post type enumeration status
     * 
     * @return array Enumeration status
     */
    public function get_enumeration_status() {
        return [
            'optimization_enabled' => true,
            'caching_enabled' => true,
            'heavy_types_excluded' => true,
            'batch_processing_available' => true,
            'last_enumeration' => current_time('mysql')
        ];
    }
}
