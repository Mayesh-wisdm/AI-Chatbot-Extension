<?php

/**
 * Content Transformer for WDM AI BotKit Extension
 *
 * Handles automatic content transformation based on license status:
 * - Downgrades LearnDash content to basic (title + description) when license expires
 * - Upgrades LearnDash content to comprehensive when license is reactivated
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wdm_Ai_Botkit_Extension_Content_Transformer {

    /**
     * License Manager instance
     */
    private $license_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
        
        // Hook into license status changes
        add_action('wdm_ai_botkit_extension_license_status_changed', [$this, 'handle_license_status_change'], 10, 2);
    }

    /**
     * Handle license status changes
     */
    public function handle_license_status_change($old_status, $new_status) {
        error_log("AI BotKit Extension: License status changed from '{$old_status}' to '{$new_status}'");
        
        // License expired/deactivated - downgrade content
        if ($old_status === 'valid' && in_array($new_status, ['expired', 'inactive'])) {
            $this->downgrade_learndash_content();
        }
        
        // License reactivated - enable upgrade option
        elseif (in_array($old_status, ['expired', 'inactive']) && $new_status === 'valid') {
            $this->enable_content_upgrade();
        }
    }

    /**
     * Downgrade LearnDash content to basic level
     */
    public function downgrade_learndash_content() {
        error_log('AI BotKit Extension: Starting LearnDash content downgrade...');
        
        try {
            global $wpdb;
            
            // Find all LearnDash course chunks
            $learndash_chunks = $wpdb->get_results(
                "SELECT c.*, d.source_id 
                FROM {$wpdb->prefix}ai_botkit_chunks c
                JOIN {$wpdb->prefix}ai_botkit_documents d ON c.document_id = d.id
                WHERE d.source_type = 'post' 
                AND d.source_id IN (
                    SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'sfwd-courses' 
                    AND post_status = 'publish'
                )"
            );
            
            if (empty($learndash_chunks)) {
                error_log('AI BotKit Extension: No LearnDash course chunks found for downgrade');
                return;
            }
            
            $downgraded_count = 0;
            $errors = [];
            
            foreach ($learndash_chunks as $chunk) {
                try {
                    $this->downgrade_single_course($chunk);
                    $downgraded_count++;
                } catch (Exception $e) {
                    $errors[] = "Course ID {$chunk->source_id}: " . $e->getMessage();
                    error_log("AI BotKit Extension: Error downgrading course {$chunk->source_id}: " . $e->getMessage());
                }
            }
            
            error_log("AI BotKit Extension: Content downgrade completed. Processed: {$downgraded_count}, Errors: " . count($errors));
            
            // Store downgrade completion status
            update_option('wdm_ai_botkit_extension_content_downgraded', [
                'timestamp' => current_time('mysql'),
                'processed_count' => $downgraded_count,
                'error_count' => count($errors),
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            error_log('AI BotKit Extension: Fatal error during content downgrade: ' . $e->getMessage());
        }
    }

    /**
     * Downgrade a single course to basic content
     */
    private function downgrade_single_course($chunk) {
        global $wpdb;
        
        // Get the original course post
        $course = get_post($chunk->source_id);
        if (!$course || $course->post_type !== 'sfwd-courses') {
            throw new Exception('Invalid course post');
        }
        
        // Create basic content (title + description only)
        $basic_content = $this->create_basic_course_content($course);
        
        // Update the chunk content
        $wpdb->update(
            "{$wpdb->prefix}ai_botkit_chunks",
            [
                'content' => $basic_content,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $chunk->id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Regenerate embeddings for the basic content
        $this->regenerate_embeddings_for_chunk($chunk->id, $basic_content);
        
        error_log("AI BotKit Extension: Downgraded course '{$course->post_title}' to basic content");
    }

    /**
     * Create basic course content (title + description only)
     */
    private function create_basic_course_content($course) {
        $content = [];
        
        // Course title
        $content[] = "Course: " . $course->post_title;
        
        // Course description
        $content[] = "Description: " . wp_strip_all_tags($course->post_content);
        
        return implode("\n\n", $content);
    }

    /**
     * Regenerate embeddings for a chunk
     */
    private function regenerate_embeddings_for_chunk($chunk_id, $content) {
        try {
            // Check if AI BotKit is available
            if (!class_exists('AI_BotKit\Core\Embeddings_Generator')) {
                error_log('AI BotKit Extension: Embeddings_Generator not available');
                return;
            }
            
            // Get embeddings generator
            $embeddings_generator = new AI_BotKit\Core\Embeddings_Generator();
            
            // Generate new embeddings
            $embeddings = $embeddings_generator->generate_embeddings([$content]);
            
            if (!empty($embeddings)) {
                // Update embeddings in database
                $this->update_chunk_embeddings($chunk_id, $embeddings[0]);
            }
            
        } catch (Exception $e) {
            error_log("AI BotKit Extension: Error regenerating embeddings for chunk {$chunk_id}: " . $e->getMessage());
        }
    }

    /**
     * Update embeddings for a chunk
     */
    private function update_chunk_embeddings($chunk_id, $embedding) {
        global $wpdb;
        
        // Update local embeddings
        $wpdb->update(
            "{$wpdb->prefix}ai_botkit_embeddings",
            [
                'embedding' => json_encode($embedding),
                'updated_at' => current_time('mysql')
            ],
            ['chunk_id' => $chunk_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Update Pinecone if configured
        $this->update_pinecone_embedding($chunk_id, $embedding);
    }

    /**
     * Update Pinecone embedding
     */
    private function update_pinecone_embedding($chunk_id, $embedding) {
        try {
            // Check if Pinecone is configured
            if (!class_exists('AI_BotKit\Core\Pinecone_Database')) {
                return;
            }
            
            $pinecone_db = new AI_BotKit\Core\Pinecone_Database();
            if (!$pinecone_db->is_configured()) {
                return;
            }
            
            // Get chunk metadata
            global $wpdb;
            $chunk = $wpdb->get_row($wpdb->prepare(
                "SELECT c.*, d.source_type, d.source_id 
                FROM {$wpdb->prefix}ai_botkit_chunks c
                JOIN {$wpdb->prefix}ai_botkit_documents d ON c.document_id = d.id
                WHERE c.id = %d",
                $chunk_id
            ));
            
            if ($chunk) {
                // Upsert to Pinecone
                $pinecone_db->upsert_vectors([[
                    'id' => (string)$chunk_id,
                    'values' => $embedding,
                    'metadata' => [
                        'chunk_id' => $chunk_id,
                        'document_id' => $chunk->document_id,
                        'source_type' => $chunk->source_type,
                        'source_id' => $chunk->source_id,
                        'content' => $chunk->content,
                        'created_at' => $chunk->created_at
                    ]
                ]]);
            }
            
        } catch (Exception $e) {
            error_log("AI BotKit Extension: Error updating Pinecone embedding for chunk {$chunk_id}: " . $e->getMessage());
        }
    }

    /**
     * Enable content upgrade option
     */
    public function enable_content_upgrade() {
        error_log('AI BotKit Extension: License reactivated - enabling content upgrade option');
        
        // Store upgrade availability
        update_option('wdm_ai_botkit_extension_upgrade_available', [
            'timestamp' => current_time('mysql'),
            'status' => 'available'
        ]);
        
        // Add admin notice
        add_action('admin_notices', [$this, 'show_upgrade_notice']);
    }

    /**
     * Show upgrade notice
     */
    public function show_upgrade_notice() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'ai-botkit') !== false) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>AI BotKit Extension:</strong> Your license has been reactivated! ';
            echo 'Your LearnDash content is currently at basic level. ';
            echo '<a href="' . admin_url('admin.php?page=ai-botkit&tab=extension-license') . '">Click here to upgrade to comprehensive content</a>.</p>';
            echo '</div>';
        }
    }

    /**
     * Check if content upgrade is available
     */
    public function is_upgrade_available() {
        $upgrade_data = get_option('wdm_ai_botkit_extension_upgrade_available', false);
        return $upgrade_data && $upgrade_data['status'] === 'available';
    }

    /**
     * Mark upgrade as completed
     */
    public function mark_upgrade_completed() {
        update_option('wdm_ai_botkit_extension_upgrade_available', [
            'timestamp' => current_time('mysql'),
            'status' => 'completed'
        ]);
    }

    /**
     * Get content transformation status
     */
    public function get_transformation_status() {
        $downgrade_data = get_option('wdm_ai_botkit_extension_content_downgraded', false);
        $upgrade_data = get_option('wdm_ai_botkit_extension_upgrade_available', false);
        
        return [
            'downgraded' => $downgrade_data,
            'upgrade_available' => $upgrade_data && $upgrade_data['status'] === 'available',
            'upgrade_completed' => $upgrade_data && $upgrade_data['status'] === 'completed'
        ];
    }
}
