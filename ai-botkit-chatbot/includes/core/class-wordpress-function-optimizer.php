<?php
namespace AI_BotKit\Core;

/**
 * WordPress Function Optimizer Class
 * 
 * Provides optimized alternatives to expensive WordPress functions
 * for better performance, particularly for large datasets.
 */
class WordPress_Function_Optimizer {
    
    /**
     * Get optimized post type count
     * 
     * @param string $post_type Post type name
     * @param string $post_status Post status (default: 'publish')
     * @return int Post count
     */
    public static function get_post_type_count($post_type, $post_status = 'publish') {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = %s AND post_status = %s",
            $post_type,
            $post_status
        ));
    }
    
    /**
     * Get batch post type counts in a single query
     * 
     * @param array $post_types Array of post type names
     * @param string $post_status Post status (default: 'publish')
     * @return array Array of post type counts
     */
    public static function get_batch_post_type_counts($post_types, $post_status = 'publish') {
        global $wpdb;
        
        if (empty($post_types)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_type, COUNT(*) as count 
             FROM {$wpdb->posts} 
             WHERE post_status = %s 
             AND post_type IN ($placeholders)
             GROUP BY post_type",
            array_merge([$post_status], $post_types)
        ), ARRAY_A);
        
        $result = [];
        foreach ($counts as $count) {
            $result[$count['post_type']] = (int) $count['count'];
        }
        
        return $result;
    }
    
    /**
     * Get optimized post types with counts
     * 
     * @param array $args Arguments for get_post_types
     * @param bool $include_counts Whether to include post counts
     * @return array Array of post types with optional counts
     */
    public static function get_post_types_with_counts($args = [], $include_counts = true) {
        $post_types = get_post_types($args, 'objects');
        
        if (!$include_counts) {
            return $post_types;
        }
        
        $post_type_names = array_keys($post_types);
        $counts = self::get_batch_post_type_counts($post_type_names);
        
        $result = [];
        foreach ($post_types as $post_type => $post_type_obj) {
            $result[$post_type] = [
                'object' => $post_type_obj,
                'count' => $counts[$post_type] ?? 0
            ];
        }
        
        return $result;
    }
    
    /**
     * Get optimized post type statistics
     * 
     * @param array $post_types Array of post type names
     * @return array Post type statistics
     */
    public static function get_post_type_statistics($post_types = []) {
        global $wpdb;
        
        if (empty($post_types)) {
            $post_types = array_keys(get_post_types(['public' => true]));
        }
        
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                post_type,
                post_status,
                COUNT(*) as count
             FROM {$wpdb->posts} 
             WHERE post_type IN ($placeholders)
             GROUP BY post_type, post_status
             ORDER BY post_type, post_status",
            $post_types
        ), ARRAY_A);
        
        $result = [];
        foreach ($stats as $stat) {
            if (!isset($result[$stat['post_type']])) {
                $result[$stat['post_type']] = [];
            }
            $result[$stat['post_type']][$stat['post_status']] = (int) $stat['count'];
        }
        
        return $result;
    }
    
    /**
     * Get optimized post type enumeration for migration
     * 
     * @return array Post types with counts for migration
     */
    public static function get_migration_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $post_type_names = array_keys($post_types);
        
        // Get counts in single query
        $counts = self::get_batch_post_type_counts($post_type_names);
        
        $result = [];
        foreach ($post_types as $post_type => $post_type_obj) {
            $count = $counts[$post_type] ?? 0;
            
            if ($count > 0) {
                $result[$post_type] = [
                    'name' => $post_type_obj->labels->singular_name,
                    'count' => $count
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Check if post type has many posts (for optimization decisions)
     * 
     * @param string $post_type Post type name
     * @param int $threshold Threshold for "many posts" (default: 1000)
     * @return bool True if post type has many posts
     */
    public static function has_many_posts($post_type, $threshold = 1000) {
        $count = self::get_post_type_count($post_type);
        return $count > $threshold;
    }
    
    /**
     * Get optimized post type list for heavy installations
     * 
     * @param array $excluded_types Post types to exclude by default
     * @return array Optimized post type list
     */
    public static function get_optimized_post_types($excluded_types = []) {
        $default_exclusions = [
            'tribe_events',      // The Events Calendar
            'tribe_venue',       // The Events Calendar
            'tribe_organizer',   // The Events Calendar
            'revision',          // WordPress revisions
            'nav_menu_item',     // Navigation menu items
            'custom_css',        // Custom CSS
            'customize_changeset', // Customizer changes
        ];
        
        $excluded_types = array_merge($default_exclusions, $excluded_types);
        
        $post_types = get_post_types(['public' => true], 'objects');
        $optimized_types = [];
        
        foreach ($post_types as $post_type => $post_type_obj) {
            // Skip excluded types
            if (in_array($post_type, $excluded_types)) {
                continue;
            }
            
            // Check if it has many posts
            if (self::has_many_posts($post_type)) {
                // For heavy post types, only include if explicitly enabled
                $enabled_types = get_option('ai_botkit_enabled_post_types', []);
                if (in_array($post_type, $enabled_types)) {
                    $optimized_types[] = $post_type;
                }
            } else {
                // For light post types, include by default
                $optimized_types[] = $post_type;
            }
        }
        
        return $optimized_types;
    }
    
    /**
     * Get performance metrics for WordPress functions
     * 
     * @return array Performance metrics
     */
    public static function get_performance_metrics() {
        $metrics = [];
        
        // Test wp_count_posts performance
        $start_time = microtime(true);
        wp_count_posts('post');
        $end_time = microtime(true);
        $metrics['wp_count_posts_time'] = $end_time - $start_time;
        
        // Test optimized function performance
        $start_time = microtime(true);
        self::get_post_type_count('post');
        $end_time = microtime(true);
        $metrics['optimized_count_time'] = $end_time - $start_time;
        
        // Calculate improvement
        if ($metrics['wp_count_posts_time'] > 0) {
            $metrics['improvement_percentage'] = 
                (($metrics['wp_count_posts_time'] - $metrics['optimized_count_time']) / $metrics['wp_count_posts_time']) * 100;
        } else {
            $metrics['improvement_percentage'] = 0;
        }
        
        return $metrics;
    }
    
    /**
     * Benchmark WordPress functions vs optimized alternatives
     * 
     * @param array $post_types Post types to test
     * @param int $iterations Number of iterations to run
     * @return array Benchmark results
     */
    public static function benchmark_functions($post_types = ['post', 'page'], $iterations = 10) {
        $results = [];
        
        foreach ($post_types as $post_type) {
            $wp_count_time = 0;
            $optimized_time = 0;
            
            // Benchmark wp_count_posts
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                wp_count_posts($post_type);
                $end_time = microtime(true);
                $wp_count_time += ($end_time - $start_time);
            }
            
            // Benchmark optimized function
            for ($i = 0; $i < $iterations; $i++) {
                $start_time = microtime(true);
                self::get_post_type_count($post_type);
                $end_time = microtime(true);
                $optimized_time += ($end_time - $start_time);
            }
            
            $results[$post_type] = [
                'wp_count_posts_avg' => $wp_count_time / $iterations,
                'optimized_avg' => $optimized_time / $iterations,
                'improvement' => (($wp_count_time - $optimized_time) / $wp_count_time) * 100
            ];
        }
        
        return $results;
    }
}
