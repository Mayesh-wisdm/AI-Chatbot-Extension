<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 *
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/admin
 * @author     WisdmLabs <support@wisdmlabs.com>
 */
class Wdm_Ai_Botkit_Extension_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		
		// Register AJAX handlers
		add_action( 'wp_ajax_wdm_ai_botkit_extension_license_action', array( $this, 'process_license_ajax' ) );
		add_action( 'wp_ajax_learndash_sync_courses', array( $this, 'handle_learndash_sync_ajax' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wdm_Ai_Botkit_Extension_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wdm_Ai_Botkit_Extension_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wdm-ai-botkit-extension-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wdm_Ai_Botkit_Extension_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wdm_Ai_Botkit_Extension_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wdm-ai-botkit-extension-admin.js', array( 'jquery' ), $this->version, false );
		
		// Localize script for AJAX variables
		wp_localize_script( $this->plugin_name, 'wdm_ai_botkit_extension_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wdm_extension_license_nonce' )
		) );
	}

	/**
	 * Add extension sidebar menu item using action hook
	 */
	public function add_extension_sidebar_menu() {
		$nonce = wp_create_nonce('ai_botkit_chatbots');
		$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
		?>
		<li>
			<a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=extension-license&nonce=' . $nonce)); ?>" 
			   class="ai-botkit-sidebar-link <?php echo $current_tab === 'extension-license' ? 'active' : ''; ?>">
				<i class="ti ti-key"></i>
				<?php esc_html_e('Extension License', 'wdm-ai-botkit-extension'); ?>
			</a>
		</li>
		<?php
	}

	/**
	 * Add extension tab content using action hook
	 */
	public function add_extension_tab_content($tab) {
		if ($tab === 'extension-license') {
			// Check if license manager class exists
			if (!class_exists('Wdm_Ai_Botkit_Extension_License_Manager')) {
				echo '<div class="notice notice-error"><p>License Manager class not found!</p></div>';
				return;
			}
			
			require_once plugin_dir_path(__FILE__) . 'partials/wdm-ai-botkit-extension-license-settings.php';
		}
	}

	/**
	 * Register extension tab with AI BotKit
	 */
	public function register_extension_tab($tabs) {
		$tabs['extension-license'] = array(
			'title' => __('Extension License', 'wdm-ai-botkit-extension'),
			'capability' => 'manage_options'
		);
		return $tabs;
	}

	/**
	 * AJAX handler for LearnDash course sync
	 */
	public function handle_learndash_sync_ajax() {
		check_ajax_referer('learndash_sync_courses', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}
		
		// Check if LearnDash is active
		if (!defined('LEARNDASH_VERSION')) {
			wp_send_json_error(['message' => 'LearnDash is not active']);
		}
		
		// Check if license is valid
		$license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
		if ($license_manager->get_extension_license_status() !== 'valid') {
			wp_send_json_error(['message' => 'Extension license is not valid']);
		}
		
		$action = sanitize_text_field($_POST['sync_action'] ?? 'start');
		$bot_id = intval($_POST['bot_id'] ?? 0);
		
		if ($action === 'start') {
			$result = $this->start_learndash_sync($bot_id);
		} elseif ($action === 'process') {
			$result = $this->process_learndash_sync_batch();
		} else {
			wp_send_json_error(['message' => 'Invalid sync action']);
		}
		
		if ($result['success']) {
			wp_send_json_success($result);
		} else {
			wp_send_json_error($result);
		}
	}

	/**
	 * Start LearnDash sync process
	 */
	private function start_learndash_sync($bot_id = 0) {
		global $wpdb;
		
		// If no bot_id provided, get the first available bot
		if ($bot_id === 0) {
			$bot_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}ai_botkit_chatbots LIMIT 1");
			if (!$bot_id) {
				return [
					'success' => false,
					'message' => 'No chatbots found. Please create a chatbot first.'
				];
			}
		}
		
		// Get LearnDash courses that are already in the bot's knowledge base
		$courses = $wpdb->get_col($wpdb->prepare(
			"SELECT d.source_id 
			 FROM {$wpdb->prefix}ai_botkit_documents d
			 JOIN {$wpdb->prefix}ai_botkit_content_relationships cr ON cr.target_id = d.id
			 WHERE cr.source_type = 'chatbot' 
			 AND cr.source_id = %d 
			 AND cr.relationship_type = 'knowledge_base'
			 AND d.source_type = 'post'
			 AND d.source_id IN (
				 SELECT ID FROM {$wpdb->posts} 
				 WHERE post_type = 'sfwd-courses' 
				 AND post_status = 'publish'
			 )",
			$bot_id
		));
		
		if (empty($courses)) {
			return [
				'success' => false,
				'message' => 'No LearnDash courses found in your knowledge base. Please add LearnDash courses to your chatbot\'s knowledge base first.'
			];
		}
		
		// Store sync data in transient
		set_transient('learndash_sync_data', [
			'courses' => $courses,
			'current_index' => 0,
			'total' => count($courses),
			'processed' => 0,
			'errors' => []
		], HOUR_IN_SECONDS);
		
		return [
			'success' => true,
			'total_courses' => count($courses),
			'bot_id' => $bot_id,
			'message' => sprintf('Found %d LearnDash courses in your knowledge base to upgrade', count($courses))
		];
	}

	/**
	 * Process a batch of LearnDash courses
	 */
	private function process_learndash_sync_batch() {
		$sync_data = get_transient('learndash_sync_data');
		
		if (!$sync_data) {
			return [
				'success' => false,
				'message' => 'Sync session expired. Please start again.'
			];
		}
		
		$batch_size = 3; // Process 3 courses at a time
		$processed = 0;
		$errors = [];
		
		// Process batch
		for ($i = 0; $i < $batch_size && $sync_data['current_index'] < $sync_data['total']; $i++) {
			$course_id = $sync_data['courses'][$sync_data['current_index']];
			
			try {
				$this->sync_learndash_course($course_id);
				$processed++;
			} catch (Exception $e) {
				$errors[] = [
					'course_id' => $course_id,
					'error' => $e->getMessage()
				];
			}
			
			$sync_data['current_index']++;
		}
		
		$sync_data['processed'] += $processed;
		$sync_data['errors'] = array_merge($sync_data['errors'], $errors);
		
		// Update transient
		set_transient('learndash_sync_data', $sync_data, HOUR_IN_SECONDS);
		
		$is_complete = $sync_data['current_index'] >= $sync_data['total'];
		
		// If sync is complete, mark upgrade as completed
		if ($is_complete) {
			$this->mark_upgrade_completed();
		}
		
		return [
			'success' => true,
			'processed' => $processed,
			'total_processed' => $sync_data['processed'],
			'total_courses' => $sync_data['total'],
			'current_index' => $sync_data['current_index'],
			'is_complete' => $is_complete,
			'errors' => $errors,
			'message' => $is_complete ? 
				sprintf('Sync completed! Processed %d courses', $sync_data['processed']) :
				sprintf('Processed %d courses (%d/%d)', $processed, $sync_data['current_index'], $sync_data['total'])
		];
	}

	/**
	 * Sync a single LearnDash course
	 */
	private function sync_learndash_course($course_id) {
		// Check if AI BotKit is available
		if (!class_exists('AI_BotKit\Core\RAG_Engine')) {
			throw new Exception('AI BotKit is not available');
		}
		
		// Get course data
		$course = get_post($course_id);
		if (!$course || $course->post_type !== 'sfwd-courses') {
			throw new Exception('Invalid course ID');
		}
		
		// Build comprehensive course content
		$content = $this->build_learndash_course_content($course);
		
		// Process with RAG engine (this will trigger the cleanup and reprocessing)
		global $wpdb;
		
		// Create or update document record
		$document_id = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}ai_botkit_documents 
			WHERE source_type = 'post' AND source_id = %d",
			$course_id
		));
		
		if (!$document_id) {
			// Create new document record
			$wpdb->insert(
				$wpdb->prefix . 'ai_botkit_documents',
				[
					'source_type' => 'post',
					'source_id' => $course_id,
					'status' => 'pending',
					'created_at' => current_time('mysql')
				],
				['%s', '%d', '%s', '%s']
			);
			$document_id = $wpdb->insert_id;
		} else {
			// Update existing document to trigger reprocessing
			$wpdb->update(
				$wpdb->prefix . 'ai_botkit_documents',
				['status' => 'pending'],
				['id' => $document_id],
				['%s'],
				['%d']
			);
		}
		
		// Trigger the RAG engine to process this document
		// This will use the new cleanup functionality we implemented
		do_action('ai_botkit_process_queue');
		
		// Mark upgrade as completed for this course
		$this->mark_course_upgraded($course_id);
	}

	/**
	 * Mark a course as upgraded
	 */
	private function mark_course_upgraded($course_id) {
		$upgraded_courses = get_option('wdm_ai_botkit_extension_upgraded_courses', []);
		$upgraded_courses[] = $course_id;
		update_option('wdm_ai_botkit_extension_upgraded_courses', array_unique($upgraded_courses));
	}

	/**
	 * Mark upgrade as completed
	 */
	private function mark_upgrade_completed() {
		// Mark upgrade as completed in content transformer
		if (class_exists('Wdm_Ai_Botkit_Extension_Content_Transformer')) {
			$transformer = new Wdm_Ai_Botkit_Extension_Content_Transformer();
			$transformer->mark_upgrade_completed();
		}
		
		// Clear upgraded courses list
		delete_option('wdm_ai_botkit_extension_upgraded_courses');
	}

	/**
	 * Build comprehensive LearnDash course content
	 */
	private function build_learndash_course_content($course) {
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
	 * AJAX handler for processing license actions
	 */
	public function process_license_ajax() {
		check_ajax_referer('wdm_extension_license_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}
		
		$license_action = sanitize_text_field($_POST['license_action']);
		$license_key = sanitize_text_field($_POST['wdm_ai_botkit_extension_license_key']);
		
		$license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
		
		if ($license_action === 'activate') {
			$result = $license_manager->activate_extension_license($license_key);
		} elseif ($license_action === 'deactivate') {
			$result = $license_manager->deactivate_extension_license($license_key);
		} else {
			wp_send_json_error(['message' => 'Invalid action']);
		}
		
		if ($result['success']) {
			// Get updated status display for immediate UI update
			$status_display = $license_manager->get_license_status_display();
			
			wp_send_json_success([
				'message' => $result['message'],
				'status_display' => $status_display
			]);
		} else {
			wp_send_json_error(['message' => $result['message']]);
		}
	}

	/**
	 * Add settings link to plugins page
	 *
	 * @param array $links Plugin action links
	 * @return array Modified plugin action links
	 */
	public function add_plugin_action_links( $links ) {
		// Check if AI BotKit is active before showing settings link
		if ( Wdm_Ai_Botkit_Extension_License_Manager::is_ai_botkit_active() ) {
			$settings_link = '<a href="' . admin_url( 'admin.php?page=ai-botkit&tab=extension-license' ) . '">' . __( 'Settings', 'wdm-ai-botkit-extension' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * Pass the user enrolled course data.
	 *
	 * @param mixed $bool
	 * @param mixed $bot_id
	 */
	public function wdm_ai_botkit_user_aware_context( $bool, $bot_id ) {
		// Runtime license check - ensures license is still valid when filter runs
		$license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
		if ( ! $license_manager->is_extension_licensed_runtime() ) {
			return $bool; // Return original value if not licensed
		}
		
		$current_user_id  = get_current_user_id();
		$enrolled_courses = learndash_user_get_enrolled_courses( $current_user_id );
		return $enrolled_courses;
	}

	/**
	 * Add Learndash post content.
	 *
	 * @param mixed $content
	 * @param mixed $post_id
	 */
	public function wdm_ai_botkit_post_content( $content, $post_id ) {
		// Runtime license check - ensures license is still valid when filter runs
		$license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();
		if ( ! $license_manager->is_extension_licensed_runtime() ) {
			return $content; // Return original content if not licensed
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return 'Invalid post ID.';
		}

		$post_type = get_post_type( $post_id );

		switch ( $post_type ) {

			case 'sfwd-courses':
				$course_title   = get_the_title( $post_id );
				$course_content = $this->clean_sentences( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );

				$lessons_output = array();
				$lessons        = learndash_get_course_lessons_list( $post_id );

				$nested_quizzes = array();

				foreach ( $lessons as $lesson_data ) {
					$lesson         = $lesson_data['post'];
					$lesson_title   = get_the_title( $lesson->ID );
					$lesson_content = $this->clean_sentences( apply_filters( 'the_content', $lesson->post_content ) );

					$quizzes_in_lesson = learndash_course_get_quizzes( $post_id, $lesson->ID );
					if ( $quizzes_in_lesson ) {
						$nested_quizzes = array_merge( $nested_quizzes, $quizzes_in_lesson );
					}

					$topics_output = array();
					$topics        = learndash_get_topic_list( $lesson->ID, $post_id );

					foreach ( $topics as $topic ) {
						$quizzes_in_topic = learndash_course_get_quizzes( $post_id, $topic->ID );
						if ( $quizzes_in_topic ) {
							$nested_quizzes = array_merge( $nested_quizzes, $quizzes_in_topic );
						}
						$topic_title     = get_the_title( $topic->ID );
						$topic_content   = $this->clean_sentences( apply_filters( 'the_content', $topic->post_content ) );
						$topics_output[] = "  Topic: {$topic_title}\n  Content:\n  {$topic_content}";
					}

					$lessons_output[] = "Lesson: {$lesson_title}\nContent:\n{$lesson_content}" . ( $topics_output ? "\n" . implode( "\n", $topics_output ) : '' );
				}

				$quizzes_output = array();
				$quizzes        = learndash_course_get_quizzes( $post_id, $post_id );
				if ( $nested_quizzes ) {
					$quizzes = array_merge( $quizzes, $nested_quizzes );
				}

				foreach ( $quizzes as $quiz ) {
					$quizzes_output[] = $this->get_quiz_details( $quiz->ID );
				}

				return "Course: {$course_title}\n\nContent:\n{$course_content}\n\n" .
					( $lessons_output ? "Lessons:\n" . implode( "\n\n", $lessons_output ) . "\n\n" : '' ) .
					( $quizzes_output ? "Quizzes:\n" . implode( "\n\n", $quizzes_output ) : '' );

			case 'sfwd-lessons':
				$lesson_title   = get_the_title( $post_id );
				$lesson_content = $this->clean_sentences( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );

				$topics_output  = array();
				$quizzes_output = array();
				$nested_quizzes = array();

				$topics = learndash_get_topic_list( $post_id );

				foreach ( $topics as $topic ) {
					$topic_title   = get_the_title( $topic->ID );
					$topic_content = $this->clean_sentences( apply_filters( 'the_content', $topic->post_content ) );

					$topics_output[] = "  Topic: {$topic_title}\n  Content:\n  {$topic_content}";

					$quizzes_in_topic = learndash_course_get_quizzes( null, $topic->ID );
					if ( $quizzes_in_topic ) {
						$nested_quizzes = array_merge( $nested_quizzes, $quizzes_in_topic );
					}
				}

				$quizzes = learndash_course_get_quizzes( null, $post_id );

				if ( $nested_quizzes ) {
					$quizzes = array_merge( $quizzes, $nested_quizzes );
				}

				foreach ( $quizzes as $quiz ) {
					$quizzes_output[] = $this->get_quiz_details( $quiz->ID );
				}

				return "Lesson: {$lesson_title}\n\nContent:\n{$lesson_content}\n\n" .
			( $topics_output ? "Topics:\n" . implode( "\n\n", $topics_output ) . "\n\n" : '' ) .
			( $quizzes_output ? "Quizzes:\n" . implode( "\n\n", $quizzes_output ) : '' );

			case 'sfwd-topic':
				$topic_title   = get_the_title( $post_id );
				$topic_content = $this->clean_sentences( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );

				$quizzes = learndash_course_get_quizzes( null, $post_id );

				$quizzes_output = array();
				foreach ( $quizzes as $quiz ) {
					$quizzes_output[] = $this->get_quiz_details( $quiz->ID );
				}

				return "Topic: {$topic_title}\n\nContent:\n{$topic_content}\n\n" .
			( $quizzes_output ? "Quizzes:\n" . implode( "\n\n", $quizzes_output ) : '' );

			case 'sfwd-quiz':
				return $this->get_quiz_details( $post_id );

			default:
				return $content;
		}
	}

	/**
	 * Getting Questions inside the quiz.
	 *
	 * @param mixed $quiz_id
	 * @return string
	 */
	private function get_quiz_details( $quiz_id ) {
		if ( ! $quiz_id ) {
			return '';
		}

		$quiz_title   = get_the_title( $quiz_id );
		$quiz_content = $this->clean_sentences( apply_filters( 'the_content', get_post_field( 'post_content', $quiz_id ) ) );

		$questions_output = '';
		$questions        = maybe_unserialize( get_post_meta( $quiz_id, 'ld_quiz_questions', true ) );
		$question_count   = is_array( $questions ) ? count( $questions ) : 0;

		if ( is_array( $questions ) ) {
			foreach ( array_keys( $questions ) as $question_id ) {
				$question_post = get_post( $question_id );
				if ( $question_post ) {
					$question_title    = $question_post->post_title;
					$question_content  = $this->clean_sentences( apply_filters( 'the_content', $question_post->post_content ) );
					$questions_output .= "Question: {$question_title}\nContent: {$question_content}\n\n";
				}
			}
		}

		return "Quiz: {$quiz_title}\n\nContent:\n{$quiz_content}\n\nQuestions:\n{$questions_output}";
	}

	/**
	 * Striping shortcodes and html tags.
	 *
	 * @param string $content
	 * @return string
	 */
	private function clean_sentences( $content ) {
		return trim( strip_shortcodes( wp_strip_all_tags( $content ) ) );
	}
}
