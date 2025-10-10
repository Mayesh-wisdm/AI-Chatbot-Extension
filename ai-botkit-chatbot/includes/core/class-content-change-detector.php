<?php
namespace AI_BotKit\Core;

/**
 * Content Change Detector Class
 * 
 * Detects content changes in WordPress posts and other content types
 * to optimize content processing and cache invalidation.
 */
class Content_Change_Detector {
    
    /**
     * Enhanced cache manager
     */
    private $cache_manager;
    
    /**
     * WordPress function optimizer
     */
    private $wp_optimizer;
    
    /**
     * Initialize the content change detector
     */
    public function __construct() {
        $this->cache_manager = new Unified_Cache_Manager();
        $this->wp_optimizer = new WordPress_Function_Optimizer();
    }
    
    /**
     * Detect content changes for specified post types
     * 
     * @param array $post_types Post types to check
     * @param int $hours Hours to look back
     * @return array Content changes
     */
    public function detect_content_changes($post_types, $hours = 24) {
        $cache_key = 'content_changes_' . md5(serialize($post_types)) . '_' . $hours;
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $changes = $this->analyze_content_changes($post_types, $hours);
        $this->cache_manager->set($cache_key, $changes, 300); // 5 minutes
        
        return $changes;
    }
    
    /**
     * Analyze content changes
     * 
     * @param array $post_types Post types to analyze
     * @param int $hours Hours to look back
     * @return array Content changes analysis
     */
    private function analyze_content_changes($post_types, $hours) {
        global $wpdb;
        
        $new_posts = [];
        $updated_posts = [];
        $deleted_posts = [];
        
        foreach ($post_types as $post_type) {
            // Get new posts
            $new_posts[$post_type] = $this->get_new_posts($post_type, $hours);
            
            // Get updated posts
            $updated_posts[$post_type] = $this->get_updated_posts($post_type, $hours);
            
            // Get deleted posts (from our documents table)
            $deleted_posts[$post_type] = $this->get_deleted_posts($post_type, $hours);
        }
        
        return [
            'new_posts' => $new_posts,
            'updated_posts' => $updated_posts,
            'deleted_posts' => $deleted_posts,
            'total_changes' => $this->count_total_changes($new_posts, $updated_posts, $deleted_posts),
            'change_summary' => $this->generate_change_summary($new_posts, $updated_posts, $deleted_posts),
            'detection_time' => current_time('mysql'),
            'hours_analyzed' => $hours
        ];
    }
    
    /**
     * Get new posts for post type
     * 
     * @param string $post_type Post type
     * @param int $hours Hours to look back
     * @return array New posts
     */
    private function get_new_posts($post_type, $hours) {
        global $wpdb;
        
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_date, post_modified
             FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status = 'publish'
             AND post_date > DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY post_date DESC
             LIMIT 100",
            $post_type,
            $hours
        ), ARRAY_A);
        
        return $posts ?: [];
    }
    
    /**
     * Get updated posts for post type
     * 
     * @param string $post_type Post type
     * @param int $hours Hours to look back
     * @return array Updated posts
     */
    private function get_updated_posts($post_type, $hours) {
        global $wpdb;
        
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_date, post_modified
             FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status = 'publish'
             AND post_modified > DATE_SUB(NOW(), INTERVAL %d HOUR)
             AND post_modified != post_date
             ORDER BY post_modified DESC
             LIMIT 100",
            $post_type,
            $hours
        ), ARRAY_A);
        
        return $posts ?: [];
    }
    
    /**
     * Get deleted posts for post type
     * 
     * @param string $post_type Post type
     * @param int $hours Hours to look back
     * @return array Deleted posts
     */
    private function get_deleted_posts($post_type, $hours) {
        global $wpdb;
        
        // Get posts that exist in our documents table but not in WordPress posts
        $deleted_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT d.source_id, d.title, d.updated_at
             FROM {$wpdb->prefix}ai_botkit_documents d
             LEFT JOIN {$wpdb->posts} p ON d.source_id = p.ID
             WHERE d.source_type = %s
             AND p.ID IS NULL
             AND d.updated_at > DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY d.updated_at DESC
             LIMIT 100",
            $post_type,
            $hours
        ), ARRAY_A);
        
        return $deleted_posts ?: [];
    }
    
    /**
     * Count total changes
     * 
     * @param array $new_posts New posts
     * @param array $updated_posts Updated posts
     * @param array $deleted_posts Deleted posts
     * @return int Total changes
     */
    private function count_total_changes($new_posts, $updated_posts, $deleted_posts) {
        $total = 0;
        
        foreach ($new_posts as $posts) {
            $total += count($posts);
        }
        
        foreach ($updated_posts as $posts) {
            $total += count($posts);
        }
        
        foreach ($deleted_posts as $posts) {
            $total += count($posts);
        }
        
        return $total;
    }
    
    /**
     * Generate change summary
     * 
     * @param array $new_posts New posts
     * @param array $updated_posts Updated posts
     * @param array $deleted_posts Deleted posts
     * @return array Change summary
     */
    private function generate_change_summary($new_posts, $updated_posts, $deleted_posts) {
        $summary = [];
        
        foreach ($new_posts as $post_type => $posts) {
            $summary[$post_type] = [
                'new' => count($posts),
                'updated' => count($updated_posts[$post_type] ?? []),
                'deleted' => count($deleted_posts[$post_type] ?? []),
                'total' => count($posts) + count($updated_posts[$post_type] ?? []) + count($deleted_posts[$post_type] ?? [])
            ];
        }
        
        return $summary;
    }
    
    /**
     * Detect changes for specific post
     * 
     * @param int $post_id Post ID
     * @return array Post changes
     */
    public function detect_post_changes($post_id) {
        global $wpdb;
        
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
            $post_id
        ), ARRAY_A);
        
        if (!$post) {
            return [
                'post_id' => $post_id,
                'status' => 'not_found',
                'changes' => []
            ];
        }
        
        // Check if post exists in our documents table
        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ai_botkit_documents 
             WHERE source_type = %s AND source_id = %d",
            $post['post_type'],
            $post_id
        ), ARRAY_A);
        
        $changes = [];
        
        if (!$document) {
            $changes[] = 'new_post';
        } else {
            // Compare modification times
            $post_modified = strtotime($post['post_modified']);
            $document_updated = strtotime($document['updated_at']);
            
            if ($post_modified > $document_updated) {
                $changes[] = 'content_updated';
            }
            
            // Check if post was deleted and restored
            if ($post['post_status'] === 'publish' && $document['status'] === 'deleted') {
                $changes[] = 'post_restored';
            }
        }
        
        return [
            'post_id' => $post_id,
            'post_type' => $post['post_type'],
            'status' => 'analyzed',
            'changes' => $changes,
            'post_modified' => $post['post_modified'],
            'document_updated' => $document['updated_at'] ?? null
        ];
    }
    
    /**
     * Get content change statistics
     * 
     * @param int $hours Hours to analyze
     * @return array Change statistics
     */
    public function get_change_statistics($hours = 24) {
        $cache_key = 'change_statistics_' . $hours;
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $stats = $this->generate_change_statistics($hours);
        $this->cache_manager->set($cache_key, $stats, 300); // 5 minutes
        
        return $stats;
    }
    
    /**
     * Generate change statistics
     * 
     * @param int $hours Hours to analyze
     * @return array Change statistics
     */
    private function generate_change_statistics($hours) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_changes,
                SUM(CASE WHEN source_type = 'post' THEN 1 ELSE 0 END) as post_changes,
                SUM(CASE WHEN source_type = 'file' THEN 1 ELSE 0 END) as file_changes,
                SUM(CASE WHEN source_type = 'url' THEN 1 ELSE 0 END) as url_changes,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_changes,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_changes,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_changes
            FROM {$wpdb->prefix}ai_botkit_documents
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $hours
        ), ARRAY_A);
        
        return $stats ?: [
            'total_changes' => 0,
            'post_changes' => 0,
            'file_changes' => 0,
            'url_changes' => 0,
            'processed_changes' => 0,
            'pending_changes' => 0,
            'failed_changes' => 0,
            'hours_analyzed' => $hours,
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Get content change trends
     * 
     * @param int $days Days to analyze
     * @return array Change trends
     */
    public function get_change_trends($days = 7) {
        $cache_key = 'change_trends_' . $days;
        $cached = $this->cache_manager->get($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $trends = $this->generate_change_trends($days);
        $this->cache_manager->set($cache_key, $trends, 600); // 10 minutes
        
        return $trends;
    }
    
    /**
     * Generate change trends
     * 
     * @param int $days Days to analyze
     * @return array Change trends
     */
    private function generate_change_trends($days) {
        global $wpdb;
        
        $trends = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(updated_at) as date,
                COUNT(*) as total_changes,
                SUM(CASE WHEN source_type = 'post' THEN 1 ELSE 0 END) as post_changes,
                SUM(CASE WHEN source_type = 'file' THEN 1 ELSE 0 END) as file_changes,
                SUM(CASE WHEN source_type = 'url' THEN 1 ELSE 0 END) as url_changes
            FROM {$wpdb->prefix}ai_botkit_documents
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(updated_at)
            ORDER BY date DESC",
            $days
        ), ARRAY_A);
        
        return $trends ?: [];
    }
    
    /**
     * Get content change recommendations
     * 
     * @return array Change recommendations
     */
    public function get_change_recommendations() {
        $stats = $this->get_change_statistics(24);
        $recommendations = [];
        
        // High change volume recommendation
        if ($stats['total_changes'] > 100) {
            $recommendations[] = [
                'type' => 'high_volume',
                'message' => 'High content change volume detected. Consider batch processing.',
                'total_changes' => $stats['total_changes'],
                'recommendation' => 'Enable batch processing for content changes'
            ];
        }
        
        // Failed changes recommendation
        if ($stats['failed_changes'] > 0) {
            $recommendations[] = [
                'type' => 'failed_changes',
                'message' => 'Some content changes failed to process.',
                'failed_changes' => $stats['failed_changes'],
                'recommendation' => 'Review failed changes and retry processing'
            ];
        }
        
        // Pending changes recommendation
        if ($stats['pending_changes'] > 50) {
            $recommendations[] = [
                'type' => 'pending_changes',
                'message' => 'Many content changes are pending processing.',
                'pending_changes' => $stats['pending_changes'],
                'recommendation' => 'Process pending changes to maintain data consistency'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear content change detection cache
     */
    public function clear_detection_cache() {
        $this->cache_manager->delete_many([
            'content_changes',
            'change_statistics',
            'change_trends'
        ]);
    }
    
    /**
     * Get content change detection status
     * 
     * @return array Detection status
     */
    public function get_detection_status() {
        return [
            'detection_enabled' => true,
            'caching_enabled' => true,
            'change_tracking' => true,
            'trend_analysis' => true,
            'last_detection' => current_time('mysql')
        ];
    }
}
