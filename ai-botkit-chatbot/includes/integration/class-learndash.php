<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\RAG_Engine;
use AI_BotKit\Core\Document_Loader;

/**
 * LearnDash Integration
 * 
 * Handles integration with LearnDash LMS content including courses,
 * lessons, topics, quizzes, and assignments.
 */
class LearnDash {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Document Loader instance
     */
    private $document_loader;

    /**
     * Integration status
     */
    private $is_active = false;

    /**
     * Initialize the integration
     */
    public function __construct(RAG_Engine $rag_engine, Document_Loader $document_loader) {
        $this->rag_engine = $rag_engine;
        $this->document_loader = $document_loader;
        
        if ($this->check_learndash()) {
            $this->is_active = true;
            $this->init_hooks();
        }
    }

    /**
     * Check if LearnDash is active
     */
    private function check_learndash(): bool {
        return defined('LEARNDASH_VERSION');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Course hooks
        add_action('save_post_sfwd-courses', [$this, 'handle_course_update'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_course_delete']);
        add_action('wp_trash_post', [$this, 'handle_course_trash']);
        add_action('untrash_post', [$this, 'handle_course_untrash']);

        // Lesson hooks
        add_action('save_post_sfwd-lessons', [$this, 'handle_lesson_update'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_lesson_delete']);

        // Topic hooks
        add_action('save_post_sfwd-topic', [$this, 'handle_topic_update'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_topic_delete']);

        // Quiz hooks
        add_action('save_post_sfwd-quiz', [$this, 'handle_quiz_update'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_quiz_delete']);

        // Question hooks
        add_action('save_post_sfwd-question', [$this, 'handle_question_update'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_question_delete']);

        // Process queue
        add_action('ai_botkit_process_queue', [$this, 'process_content_queue']);
    }

    /**
     * Handle course updates
     */
    public function handle_course_update(int $course_id, \WP_Post $post, bool $update): void {
        if (wp_is_post_revision($course_id) || wp_is_post_autosave($course_id)) {
            return;
        }

        $this->queue_content_for_processing($course_id, 'course', 'update');
    }

    /**
     * Handle course deletion
     */
    public function handle_course_delete(int $course_id): void {
        if (get_post_type($course_id) !== 'sfwd-courses') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_documents';

        $wpdb->delete(
            $table_name,
            [
                'source_type' => 'learndash_course',
                'source_id' => $course_id
            ],
            ['%s', '%d']
        );
    }

    /**
     * Handle course being moved to trash
     */
    public function handle_course_trash(int $course_id): void {
        if (get_post_type($course_id) !== 'sfwd-courses') {
            return;
        }

        $this->queue_content_for_processing($course_id, 'course', 'delete');
    }

    /**
     * Handle course being restored from trash
     */
    public function handle_course_untrash(int $course_id): void {
        if (get_post_type($course_id) !== 'sfwd-courses') {
            return;
        }

        $this->queue_content_for_processing($course_id, 'course', 'update');
    }

    /**
     * Handle lesson updates
     */
    public function handle_lesson_update(int $lesson_id, \WP_Post $post, bool $update): void {
        if (wp_is_post_revision($lesson_id) || wp_is_post_autosave($lesson_id)) {
            return;
        }

        $this->queue_content_for_processing($lesson_id, 'lesson', 'update');
    }

    /**
     * Handle lesson deletion
     */
    public function handle_lesson_delete(int $lesson_id): void {
        if (get_post_type($lesson_id) !== 'sfwd-lessons') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_documents';

        $wpdb->delete(
            $table_name,
            [
                'source_type' => 'learndash_lesson',
                'source_id' => $lesson_id
            ],
            ['%s', '%d']
        );
    }

    /**
     * Process content in the queue
     */
    public function process_content_queue(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_content_queue';

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE source_type LIKE %s 
                AND status = 'pending' 
                ORDER BY created_at ASC 
                LIMIT 10",
                'learndash_%'
            )
        );

        foreach ($items as $item) {
            try {
                switch ($item->source_type) {
                    case 'learndash_course':
                        $this->process_course($item->source_id);
                        break;
                    case 'learndash_lesson':
                        $this->process_lesson($item->source_id);
                        break;
                    case 'learndash_topic':
                        $this->process_topic($item->source_id);
                        break;
                    case 'learndash_quiz':
                        $this->process_quiz($item->source_id);
                        break;
                }

                $this->update_content_status($item->id, 'completed');
            } catch (\Exception $e) {
                $this->update_content_status($item->id, 'error');
            }
        }
    }

    /**
     * Process a course and its content
     */
    private function process_course(int $course_id): void {
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'sfwd-courses') {
            throw new \Exception('Invalid course ID');
        }

        // Build course content
        $content = $this->build_course_content($course);

        // Process with RAG engine
        $this->rag_engine->process_document(
            $content,
            'learndash_course',
            [
                'source_id' => $course_id,
                'title' => $course->post_title,
                'type' => 'course',
                'url' => get_permalink($course_id)
            ]
        );
    }

    /**
     * Build complete course content including lessons, topics, and quizzes
     */
    private function build_course_content(\WP_Post $course): string {
        $content = [];

        // Course information
        $content[] = "Course: " . $course->post_title;
        $content[] = "Description: " . wp_strip_all_tags($course->post_content);

        // Course meta
        $course_meta = get_post_meta($course->ID);
        $content[] = "Level: " . ($course_meta['_sfwd-courses_course_level'][0] ?? 'Not specified');
        $content[] = "Points: " . ($course_meta['_sfwd-courses_course_points'][0] ?? '0');

        // Get lessons
        $lessons = learndash_get_course_lessons_list($course);
        foreach ($lessons as $lesson) {
            $content[] = "\nLesson: " . $lesson['post']->post_title;
            $content[] = wp_strip_all_tags($lesson['post']->post_content);

            // Get topics
            $topics = learndash_get_topic_list($lesson['post']->ID, $course->ID);
            foreach ($topics as $topic) {
                $content[] = "\nTopic: " . $topic->post_title;
                $content[] = wp_strip_all_tags($topic->post_content);
            }

            // Get quizzes
            $quizzes = learndash_get_lesson_quiz_list($lesson['post']->ID, get_current_user_id(), $course->ID);
            foreach ($quizzes as $quiz) {
                $content[] = "\nQuiz: " . $quiz['post']->post_title;
                $content[] = wp_strip_all_tags($quiz['post']->post_content);
            }
        }

        return implode("\n\n", $content);
    }

    /**
     * Queue content for processing
     */
    private function queue_content_for_processing(int $content_id, string $type, string $action): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_content_queue';

        // Check if already queued
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} 
                WHERE source_type = %s 
                AND source_id = %d 
                AND status = 'pending'",
                'learndash_' . $type,
                $content_id
            )
        );

        if ($existing) {
            return;
        }

        // Add to queue
        $wpdb->insert(
            $table_name,
            [
                'source_type' => 'learndash_' . $type,
                'source_id' => $content_id,
                'action' => $action,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Update content processing status
     */
    private function update_content_status(int $queue_id, string $status): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_content_queue';

        $wpdb->update(
            $table_name,
            [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $queue_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Get integration statistics
     */
    public function get_stats(): array {
        global $wpdb;
        $documents_table = $wpdb->prefix . 'ai_botkit_documents';
        $queue_table = $wpdb->prefix . 'ai_botkit_content_queue';

        return [
            'total_courses' => wp_count_posts('sfwd-courses')->publish,
            'total_lessons' => wp_count_posts('sfwd-lessons')->publish,
            'total_topics' => wp_count_posts('sfwd-topic')->publish,
            'total_quizzes' => wp_count_posts('sfwd-quiz')->publish,
            'processed_content' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$documents_table} 
                    WHERE source_type LIKE %s",
                    'learndash_%'
                )
            ),
            'pending_items' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$queue_table} 
                    WHERE source_type LIKE %s 
                    AND status = 'pending'",
                    'learndash_%'
                )
            ),
            'is_active' => $this->is_active
        ];
    }
} 