<?php
/**
 * Export Handler
 *
 * Handles PDF export of chat conversation transcripts.
 * Provides admin bulk export and user self-service export capabilities.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-240 to FR-249 (Chat Transcripts Export Feature)
 */

namespace AI_BotKit\Features;

use AI_BotKit\Models\Conversation;
use AI_BotKit\Models\Chatbot;

/**
 * Export_Handler class.
 *
 * Manages PDF export operations including:
 * - Single conversation PDF export
 * - Batch export with ZIP packaging
 * - Site branding application
 * - Export scheduling
 * - GDPR data export integration
 *
 * @since 2.0.0
 */
class Export_Handler {

	/**
	 * Conversations table name.
	 *
	 * @var string
	 */
	private string $conversations_table;

	/**
	 * Messages table name.
	 *
	 * @var string
	 */
	private string $messages_table;

	/**
	 * Chatbots table name.
	 *
	 * @var string
	 */
	private string $chatbots_table;

	/**
	 * Export logs table name.
	 *
	 * @var string
	 */
	private string $export_logs_table;

	/**
	 * Maximum messages per PDF page before pagination.
	 *
	 * @var int
	 */
	private const MAX_MESSAGES_PER_PAGE = 100;

	/**
	 * Batch export transient prefix.
	 *
	 * @var string
	 */
	private const BATCH_TRANSIENT_PREFIX = 'ai_botkit_batch_export_';

	/**
	 * Constructor.
	 *
	 * Initializes table names and sets up hooks.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
		$this->messages_table      = $wpdb->prefix . 'ai_botkit_messages';
		$this->chatbots_table      = $wpdb->prefix . 'ai_botkit_chatbots';
		$this->export_logs_table   = $wpdb->prefix . 'ai_botkit_export_logs';

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks(): void {
		// GDPR data export integration (FR-249).
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// Scheduled export hook.
		add_action( 'ai_botkit_scheduled_export', array( $this, 'run_scheduled_export' ), 10, 2 );
	}

	/**
	 * Initialize WordPress Filesystem API.
	 *
	 * Uses WP_Filesystem for secure file operations instead of direct PHP functions.
	 * This ensures proper permission handling and compatibility across hosting environments.
	 *
	 * @since 2.0.0
	 *
	 * @return \WP_Filesystem_Base|false WP_Filesystem instance or false on failure.
	 */
	private function init_filesystem() {
		global $wp_filesystem;

		// Load the filesystem API if not already loaded.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize filesystem without credentials for non-interactive context.
		// In admin context with FTP requirements, this may need credentials.
		if ( ! WP_Filesystem() ) {
			// Filesystem could not be initialized (may need credentials).
			return false;
		}

		return $wp_filesystem;
	}

	/**
	 * Check if dompdf library is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if dompdf is available.
	 */
	public function is_dompdf_available(): bool {
		$autoload_path = dirname( __FILE__, 2 ) . '/vendor/autoload.php';

		if ( file_exists( $autoload_path ) ) {
			require_once $autoload_path;
		}

		return class_exists( 'Dompdf\Dompdf' );
	}

	/**
	 * Check if user can export a conversation.
	 *
	 * Admin can export any conversation.
	 * Users can only export their own conversations.
	 *
	 * Implements: FR-240 (Admin Export), FR-244 (User Self-Service)
	 *
	 * @since 2.0.0
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 * @return bool True if user can export.
	 */
	public function can_export( int $conversation_id, int $user_id ): bool {
		// Admins can export any conversation.
		if ( current_user_can( 'manage_options' ) ) {
			/**
			 * Filters whether a user can export a conversation.
			 *
			 * @since 2.0.0
			 *
			 * @param bool $can_export      Whether the user can export.
			 * @param int  $conversation_id Conversation ID.
			 * @param int  $user_id         User ID.
			 */
			return apply_filters( 'ai_botkit_can_export', true, $conversation_id, $user_id );
		}

		// Check ownership.
		global $wpdb;
		$owner_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$this->conversations_table} WHERE id = %d",
				$conversation_id
			)
		);

		$can_export = ( (int) $owner_id === $user_id );

		/** This filter is documented above */
		return apply_filters( 'ai_botkit_can_export', $can_export, $conversation_id, $user_id );
	}

	/**
	 * Export a single conversation to PDF.
	 *
	 * Implements: FR-241 (PDF Generation)
	 *
	 * @since 2.0.0
	 *
	 * @param int   $conversation_id Conversation ID.
	 * @param array $options {
	 *     Export options.
	 *
	 *     @type bool   $include_metadata Include timestamps, user info. Default true.
	 *     @type bool   $include_branding  Include site logo/colors. Default true.
	 *     @type string $paper_size        Paper size ('letter' or 'a4'). Default 'a4'.
	 * }
	 * @return string|\WP_Error PDF file path or error.
	 */
	public function export_to_pdf( int $conversation_id, array $options = array() ) {
		// Check if dompdf is available.
		if ( ! $this->is_dompdf_available() ) {
			return new \WP_Error(
				'dompdf_not_available',
				__( 'PDF export requires the dompdf library. Please run "composer require dompdf/dompdf" in the plugin includes directory.', 'knowvault' ),
				array( 'status' => 500 )
			);
		}

		// Parse options.
		$defaults = array(
			'include_metadata' => true,
			'include_branding' => true,
			'paper_size'       => 'a4',
		);
		$options  = wp_parse_args( $options, $defaults );

		// Get export data.
		$export_data = $this->get_export_data( $conversation_id );

		if ( is_wp_error( $export_data ) ) {
			return $export_data;
		}

		// Generate HTML content.
		$html = $this->generate_pdf_html( $export_data, $options );

		// Generate filename.
		$filename = $this->generate_filename( $export_data );

		try {
			// Create dompdf instance.
			$dompdf_options = new \Dompdf\Options();
			$dompdf_options->set( 'isRemoteEnabled', true );
			$dompdf_options->set( 'isHtml5ParserEnabled', true );
			$dompdf_options->set( 'isJavascriptEnabled', false );
			$dompdf_options->set( 'defaultFont', 'DejaVu Sans' );

			$dompdf = new \Dompdf\Dompdf( $dompdf_options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( $options['paper_size'], 'portrait' );
			$dompdf->render();

			// Create upload directory.
			$upload_dir = wp_upload_dir();
			$export_dir = $upload_dir['basedir'] . '/ai-botkit/exports/';

			if ( ! file_exists( $export_dir ) ) {
				wp_mkdir_p( $export_dir );
			}

			// Initialize WP_Filesystem for secure file operations.
			$wp_filesystem = $this->init_filesystem();

			if ( ! $wp_filesystem ) {
				return new \WP_Error(
					'filesystem_init_failed',
					__( 'Could not initialize filesystem. Please check file permissions or contact your hosting provider.', 'knowvault' ),
					array( 'status' => 500 )
				);
			}

			// Add htaccess protection using WP_Filesystem.
			$htaccess_file = $export_dir . '.htaccess';
			if ( ! $wp_filesystem->exists( $htaccess_file ) ) {
				$htaccess_written = $wp_filesystem->put_contents(
					$htaccess_file,
					"Options -Indexes\nDeny from all",
					FS_CHMOD_FILE
				);

				if ( ! $htaccess_written ) {
					// Log warning but continue - htaccess is protective but not critical.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[AI BotKit] Warning: Could not write .htaccess protection file to exports directory.' );
					}
				}
			}

			// Save PDF file using WP_Filesystem.
			$file_path  = $export_dir . $filename;
			$pdf_output = $dompdf->output();

			$file_written = $wp_filesystem->put_contents( $file_path, $pdf_output, FS_CHMOD_FILE );

			if ( ! $file_written ) {
				return new \WP_Error(
					'pdf_save_failed',
					__( 'Could not save PDF file. Please check file permissions or contact your hosting provider.', 'knowvault' ),
					array( 'status' => 500 )
				);
			}

			// Log export activity (FR-248).
			$this->log_export( $conversation_id, get_current_user_id(), $filename );

			/**
			 * Fires after a PDF is exported.
			 *
			 * @since 2.0.0
			 *
			 * @param int    $conversation_id Conversation ID.
			 * @param string $file_path       Generated PDF file path.
			 * @param int    $user_id         User who performed the export.
			 */
			do_action( 'ai_botkit_pdf_exported', $conversation_id, $file_path, get_current_user_id() );

			return $file_path;

		} catch ( \Exception $e ) {
			// Log detailed error for debugging (admin only).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[AI BotKit] PDF export failed for conversation %d: %s in %s:%d',
						$conversation_id,
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					)
				);
			}

			// Return sanitized error message without exposing internal paths or technical details.
			return new \WP_Error(
				'pdf_generation_failed',
				__( 'PDF generation failed. Please try again or contact the site administrator if the problem persists.', 'knowvault' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Stream PDF download to browser.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $conversation_id Conversation ID.
	 * @param array $options         Export options.
	 */
	public function stream_pdf( int $conversation_id, array $options = array() ): void {
		// Check if dompdf is available.
		if ( ! $this->is_dompdf_available() ) {
			wp_die(
				esc_html__( 'PDF export requires the dompdf library. Please contact the site administrator.', 'knowvault' ),
				esc_html__( 'Export Error', 'knowvault' ),
				array( 'response' => 500 )
			);
		}

		// Parse options.
		$defaults = array(
			'include_metadata' => true,
			'include_branding' => true,
			'paper_size'       => 'a4',
		);
		$options  = wp_parse_args( $options, $defaults );

		// Get export data.
		$export_data = $this->get_export_data( $conversation_id );

		if ( is_wp_error( $export_data ) ) {
			wp_die(
				esc_html( $export_data->get_error_message() ),
				esc_html__( 'Export Error', 'knowvault' ),
				array( 'response' => $export_data->get_error_data()['status'] ?? 500 )
			);
		}

		// Generate HTML content.
		$html = $this->generate_pdf_html( $export_data, $options );

		// Generate filename.
		$filename = $this->generate_filename( $export_data );

		try {
			// Create dompdf instance.
			$dompdf_options = new \Dompdf\Options();
			$dompdf_options->set( 'isRemoteEnabled', true );
			$dompdf_options->set( 'isHtml5ParserEnabled', true );
			$dompdf_options->set( 'isJavascriptEnabled', false );
			$dompdf_options->set( 'defaultFont', 'DejaVu Sans' );

			$dompdf = new \Dompdf\Dompdf( $dompdf_options );
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( $options['paper_size'], 'portrait' );
			$dompdf->render();

			// Log export activity (FR-248).
			$this->log_export( $conversation_id, get_current_user_id(), $filename );

			/** This action is documented above */
			do_action( 'ai_botkit_pdf_exported', $conversation_id, 'streamed', get_current_user_id() );

			// Stream to browser.
			$dompdf->stream( $filename, array( 'Attachment' => true ) );
			exit;

		} catch ( \Exception $e ) {
			// Log detailed error for debugging (admin only).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[AI BotKit] PDF stream failed for conversation %d: %s in %s:%d',
						$conversation_id,
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					)
				);
			}

			// Display sanitized error message without exposing internal paths or technical details.
			wp_die(
				esc_html__( 'PDF generation failed. Please try again or contact the site administrator if the problem persists.', 'knowvault' ),
				esc_html__( 'Export Error', 'knowvault' ),
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Get export data for a conversation.
	 *
	 * @since 2.0.0
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array|\WP_Error Export data or error.
	 */
	public function get_export_data( int $conversation_id ) {
		global $wpdb;

		// Get conversation details.
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, cb.name AS chatbot_name, cb.style AS chatbot_style
				 FROM {$this->conversations_table} AS c
				 LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
				 WHERE c.id = %d",
				$conversation_id
			),
			ARRAY_A
		);

		if ( ! $conversation ) {
			return new \WP_Error(
				'conversation_not_found',
				__( 'Conversation not found.', 'knowvault' ),
				array( 'status' => 404 )
			);
		}

		// Get messages.
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->messages_table}
				 WHERE conversation_id = %d
				 ORDER BY created_at ASC",
				$conversation_id
			),
			ARRAY_A
		);

		// Get user info.
		$user_info = null;
		if ( ! empty( $conversation['user_id'] ) ) {
			$user      = get_user_by( 'id', $conversation['user_id'] );
			$user_info = $user ? array(
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
			) : null;
		}

		// Parse chatbot style for branding colors.
		$chatbot_style = array();
		if ( ! empty( $conversation['chatbot_style'] ) ) {
			$chatbot_style = json_decode( $conversation['chatbot_style'], true );
		}

		return array(
			'conversation_id' => $conversation_id,
			'chatbot_id'      => (int) $conversation['chatbot_id'],
			'chatbot_name'    => $conversation['chatbot_name'] ?? __( 'AI Assistant', 'knowvault' ),
			'chatbot_style'   => $chatbot_style,
			'user'            => $user_info,
			'session_id'      => $conversation['session_id'],
			'created_at'      => $conversation['created_at'],
			'updated_at'      => $conversation['updated_at'],
			'messages'        => $messages,
			'message_count'   => count( $messages ),
		);
	}

	/**
	 * Generate HTML content for PDF.
	 *
	 * @since 2.0.0
	 *
	 * @param array $export_data Export data from get_export_data().
	 * @param array $options     Export options.
	 * @return string HTML content for PDF.
	 */
	private function generate_pdf_html( array $export_data, array $options ): string {
		// Get branding if enabled.
		$branding = $options['include_branding'] ? $this->get_branding( $export_data['chatbot_style'] ?? array() ) : array();

		// Get PDF template.
		$template_path = dirname( __FILE__ ) . '/templates/pdf-transcript.php';

		if ( ! file_exists( $template_path ) ) {
			// Fallback to inline template.
			return $this->get_fallback_template( $export_data, $options, $branding );
		}

		// Extract variables for template.
		$site_name     = $branding['site_name'] ?? get_bloginfo( 'name' );
		$logo_url      = $branding['logo_url'] ?? '';
		$primary_color = $branding['primary_color'] ?? '#1E3A8A';
		$export_date   = current_time( 'F j, Y, g:i a' );

		// Prepare messages with formatting.
		$messages = array();
		foreach ( $export_data['messages'] as $message ) {
			$messages[] = array(
				'role'       => $message['role'],
				'content'    => wp_kses_post( $message['content'] ),
				'timestamp'  => gmdate( 'M j, Y g:i a', strtotime( $message['created_at'] ) ),
				'created_at' => $message['created_at'],
			);
		}

		// Buffer template output.
		ob_start();
		include $template_path;
		$html = ob_get_clean();

		/**
		 * Filters the PDF HTML template.
		 *
		 * @since 2.0.0
		 *
		 * @param string $html        The HTML content.
		 * @param array  $export_data Export data.
		 */
		return apply_filters( 'ai_botkit_pdf_template', $html, $export_data );
	}

	/**
	 * Get fallback HTML template when template file is missing.
	 *
	 * @since 2.0.0
	 *
	 * @param array $export_data Export data.
	 * @param array $options     Export options.
	 * @param array $branding    Branding data.
	 * @return string HTML content.
	 */
	private function get_fallback_template( array $export_data, array $options, array $branding ): string {
		$site_name     = $branding['site_name'] ?? get_bloginfo( 'name' );
		$logo_url      = $branding['logo_url'] ?? '';
		$primary_color = $branding['primary_color'] ?? '#1E3A8A';
		$export_date   = current_time( 'F j, Y, g:i a' );

		$css = $this->get_pdf_styles( $primary_color );

		/**
		 * Filters the PDF CSS styles.
		 *
		 * @since 2.0.0
		 *
		 * @param string $css The CSS styles.
		 */
		$css = apply_filters( 'ai_botkit_pdf_styles', $css );

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';

		// Header.
		$html .= '<div class="header">';
		if ( $options['include_branding'] && ! empty( $logo_url ) ) {
			$html .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" class="logo">';
		}
		$html .= '<h1>' . esc_html__( 'Chat Transcript', 'knowvault' ) . '</h1>';
		$html .= '<p class="chatbot-name">' . esc_html( $export_data['chatbot_name'] ) . '</p>';
		$html .= '</div>';

		// Metadata.
		if ( $options['include_metadata'] ) {
			$html .= '<div class="metadata">';
			$html .= '<p><strong>' . esc_html__( 'Export Date:', 'knowvault' ) . '</strong> ' . esc_html( $export_date ) . '</p>';
			$html .= '<p><strong>' . esc_html__( 'Conversation Started:', 'knowvault' ) . '</strong> ' . esc_html( gmdate( 'F j, Y, g:i a', strtotime( $export_data['created_at'] ) ) ) . '</p>';
			if ( ! empty( $export_data['user'] ) ) {
				$html .= '<p><strong>' . esc_html__( 'User:', 'knowvault' ) . '</strong> ' . esc_html( $export_data['user']['display_name'] ) . '</p>';
			}
			$html .= '<p><strong>' . esc_html__( 'Total Messages:', 'knowvault' ) . '</strong> ' . esc_html( $export_data['message_count'] ) . '</p>';
			$html .= '</div>';
		}

		// Messages.
		$html .= '<div class="messages">';
		foreach ( $export_data['messages'] as $message ) {
			$role_class = 'message ' . esc_attr( $message['role'] );
			$role_label = 'user' === $message['role'] ? __( 'You', 'knowvault' ) : $export_data['chatbot_name'];

			$html .= '<div class="' . $role_class . '">';
			$html .= '<div class="role-label">' . esc_html( $role_label ) . '</div>';
			$html .= '<div class="content">' . wp_kses_post( $message['content'] ) . '</div>';
			if ( $options['include_metadata'] ) {
				$html .= '<div class="timestamp">' . esc_html( gmdate( 'M j, Y g:i a', strtotime( $message['created_at'] ) ) ) . '</div>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';

		// Footer.
		$html .= '<div class="footer">';
		$html .= '<p>' . esc_html__( 'Generated by AI BotKit', 'knowvault' ) . '</p>';
		$html .= '</div>';

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Get PDF CSS styles.
	 *
	 * @since 2.0.0
	 *
	 * @param string $primary_color Primary brand color.
	 * @return string CSS styles.
	 */
	private function get_pdf_styles( string $primary_color ): string {
		return '
			@page {
				margin: 50px 40px;
			}
			body {
				font-family: "DejaVu Sans", Arial, sans-serif;
				font-size: 11pt;
				line-height: 1.5;
				color: #333;
				margin: 0;
				padding: 0;
			}
			.header {
				text-align: center;
				border-bottom: 2px solid ' . esc_attr( $primary_color ) . ';
				padding-bottom: 15px;
				margin-bottom: 20px;
			}
			.header .logo {
				max-width: 150px;
				max-height: 60px;
				margin-bottom: 10px;
			}
			.header h1 {
				font-size: 20pt;
				color: ' . esc_attr( $primary_color ) . ';
				margin: 0 0 5px 0;
			}
			.header .chatbot-name {
				font-size: 12pt;
				color: #666;
				margin: 0;
			}
			.metadata {
				background: #f5f5f5;
				padding: 15px;
				border-radius: 5px;
				margin-bottom: 20px;
			}
			.metadata p {
				margin: 5px 0;
				font-size: 10pt;
			}
			.messages {
				margin-bottom: 30px;
			}
			.message {
				margin-bottom: 15px;
				padding: 12px 15px;
				border-radius: 8px;
			}
			.message.user {
				background: ' . esc_attr( $primary_color ) . ';
				color: #fff;
				margin-left: 50px;
			}
			.message.assistant {
				background: #f0f0f0;
				color: #333;
				margin-right: 50px;
			}
			.message .role-label {
				font-weight: bold;
				font-size: 9pt;
				margin-bottom: 5px;
				text-transform: uppercase;
			}
			.message.user .role-label {
				color: rgba(255,255,255,0.8);
			}
			.message.assistant .role-label {
				color: #666;
			}
			.message .content {
				font-size: 11pt;
				line-height: 1.6;
			}
			.message .timestamp {
				font-size: 8pt;
				margin-top: 8px;
				opacity: 0.7;
			}
			.footer {
				position: fixed;
				bottom: 0;
				left: 0;
				right: 0;
				text-align: center;
				font-size: 9pt;
				color: #999;
				border-top: 1px solid #ddd;
				padding-top: 10px;
			}
		';
	}

	/**
	 * Get site branding for PDF.
	 *
	 * Implements: FR-242 (PDF Branding)
	 *
	 * @since 2.0.0
	 *
	 * @param array $chatbot_style Chatbot style settings.
	 * @return array {
	 *     Branding data.
	 *
	 *     @type string $logo_url      Site logo URL.
	 *     @type string $site_name     Site name.
	 *     @type string $primary_color Brand primary color.
	 * }
	 */
	private function get_branding( array $chatbot_style = array() ): array {
		// Get logo URL.
		$logo_url = '';

		// Try custom logo first.
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
		}

		// Fallback to site icon.
		if ( empty( $logo_url ) ) {
			$logo_url = get_site_icon_url( 128 );
		}

		// Get primary color from chatbot style or use default.
		$primary_color = '#1E3A8A';
		if ( ! empty( $chatbot_style['primary_color'] ) ) {
			$primary_color = $chatbot_style['primary_color'];
		}

		return array(
			'logo_url'      => $logo_url,
			'site_name'     => get_bloginfo( 'name' ),
			'primary_color' => $primary_color,
		);
	}

	/**
	 * Generate descriptive filename for export.
	 *
	 * @since 2.0.0
	 *
	 * @param array $export_data Export data.
	 * @return string Generated filename.
	 */
	public function generate_filename( array $export_data ): string {
		$chatbot_slug = sanitize_title( $export_data['chatbot_name'] );
		$date         = gmdate( 'Y-m-d', strtotime( $export_data['created_at'] ) );
		$id           = $export_data['conversation_id'];

		$filename = sprintf(
			'chat-transcript-%s-%s-%d.pdf',
			$chatbot_slug,
			$date,
			$id
		);

		/**
		 * Filters the export filename.
		 *
		 * @since 2.0.0
		 *
		 * @param string $filename        Generated filename.
		 * @param int    $conversation_id Conversation ID.
		 */
		return apply_filters( 'ai_botkit_export_filename', $filename, $export_data['conversation_id'] );
	}

	/**
	 * Schedule batch export for processing.
	 *
	 * Implements: FR-246 (Batch Export)
	 *
	 * @since 2.0.0
	 *
	 * @param array $conversation_ids Array of conversation IDs to export.
	 * @param array $options          Export options.
	 * @return string Batch ID for tracking progress.
	 */
	public function schedule_export( array $conversation_ids, array $options = array() ): string {
		$batch_id = wp_generate_uuid4();

		// Store batch data in transient.
		$batch_data = array(
			'conversation_ids' => $conversation_ids,
			'options'          => $options,
			'status'           => 'pending',
			'progress'         => 0,
			'total'            => count( $conversation_ids ),
			'completed'        => array(),
			'failed'           => array(),
			'started_at'       => current_time( 'mysql' ),
			'user_id'          => get_current_user_id(),
		);

		set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );

		// Schedule background processing.
		wp_schedule_single_event( time(), 'ai_botkit_process_batch_export', array( $batch_id ) );

		return $batch_id;
	}

	/**
	 * Process batch export.
	 *
	 * @since 2.0.0
	 *
	 * @param string $batch_id Batch ID.
	 * @return string|\WP_Error ZIP file path or error.
	 */
	public function process_batch_export( string $batch_id ) {
		$batch_data = get_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id );

		if ( ! $batch_data ) {
			return new \WP_Error(
				'batch_not_found',
				__( 'Batch export not found or expired.', 'knowvault' ),
				array( 'status' => 404 )
			);
		}

		// Update status to processing.
		$batch_data['status'] = 'processing';
		set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );

		// Create temp directory for PDFs.
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/ai-botkit/temp/' . $batch_id . '/';
		wp_mkdir_p( $temp_dir );

		$pdf_files = array();

		foreach ( $batch_data['conversation_ids'] as $index => $conversation_id ) {
			// Check if user can export this conversation.
			if ( ! $this->can_export( $conversation_id, $batch_data['user_id'] ) ) {
				$batch_data['failed'][] = array(
					'conversation_id' => $conversation_id,
					'error'           => __( 'Permission denied.', 'knowvault' ),
				);
				continue;
			}

			// Export to PDF.
			$pdf_path = $this->export_to_pdf( $conversation_id, $batch_data['options'] );

			if ( is_wp_error( $pdf_path ) ) {
				$batch_data['failed'][] = array(
					'conversation_id' => $conversation_id,
					'error'           => $pdf_path->get_error_message(),
				);
			} else {
				$batch_data['completed'][] = $conversation_id;
				$pdf_files[]               = $pdf_path;
			}

			// Update progress.
			$batch_data['progress'] = $index + 1;
			set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );
		}

		// Create ZIP file.
		if ( empty( $pdf_files ) ) {
			$batch_data['status'] = 'failed';
			set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );

			return new \WP_Error(
				'no_exports',
				__( 'No conversations were successfully exported.', 'knowvault' ),
				array( 'status' => 500 )
			);
		}

		$zip_path = $this->create_zip_archive( $pdf_files, $batch_id );

		if ( is_wp_error( $zip_path ) ) {
			$batch_data['status'] = 'failed';
			set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );
			return $zip_path;
		}

		// Update batch data with result.
		$batch_data['status']       = 'completed';
		$batch_data['zip_path']     = $zip_path;
		$batch_data['completed_at'] = current_time( 'mysql' );
		set_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id, $batch_data, DAY_IN_SECONDS );

		// Cleanup temp directory.
		$this->cleanup_temp_files( $temp_dir );

		return $zip_path;
	}

	/**
	 * Create ZIP archive from PDF files.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $pdf_files Array of PDF file paths.
	 * @param string $batch_id  Batch ID for naming.
	 * @return string|\WP_Error ZIP file path or error.
	 */
	private function create_zip_archive( array $pdf_files, string $batch_id ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error(
				'zip_not_available',
				__( 'ZIP extension is not available on this server.', 'knowvault' ),
				array( 'status' => 500 )
			);
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/ai-botkit/exports/';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$zip_filename = 'chat-transcripts-' . gmdate( 'Y-m-d' ) . '-' . substr( $batch_id, 0, 8 ) . '.zip';
		$zip_path     = $export_dir . $zip_filename;

		$zip = new \ZipArchive();

		if ( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			return new \WP_Error(
				'zip_create_failed',
				__( 'Failed to create ZIP archive.', 'knowvault' ),
				array( 'status' => 500 )
			);
		}

		foreach ( $pdf_files as $pdf_path ) {
			if ( file_exists( $pdf_path ) ) {
				$zip->addFile( $pdf_path, basename( $pdf_path ) );
			}
		}

		$zip->close();

		return $zip_path;
	}

	/**
	 * Get batch export status.
	 *
	 * Implements: FR-245 (Export Progress Indicator)
	 *
	 * @since 2.0.0
	 *
	 * @param string $batch_id Batch ID.
	 * @return array|\WP_Error Status data or error.
	 */
	public function get_export_status( string $batch_id ) {
		$batch_data = get_transient( self::BATCH_TRANSIENT_PREFIX . $batch_id );

		if ( ! $batch_data ) {
			return new \WP_Error(
				'batch_not_found',
				__( 'Batch export not found or expired.', 'knowvault' ),
				array( 'status' => 404 )
			);
		}

		$response = array(
			'batch_id'       => $batch_id,
			'status'         => $batch_data['status'],
			'progress'       => $batch_data['progress'],
			'total'          => $batch_data['total'],
			'completed'      => count( $batch_data['completed'] ),
			'failed'         => count( $batch_data['failed'] ),
			'failed_details' => $batch_data['failed'],
			'started_at'     => $batch_data['started_at'],
		);

		if ( 'completed' === $batch_data['status'] && ! empty( $batch_data['zip_path'] ) ) {
			$upload_dir             = wp_upload_dir();
			$response['download_url'] = str_replace(
				$upload_dir['basedir'],
				$upload_dir['baseurl'],
				$batch_data['zip_path']
			);
			$response['completed_at'] = $batch_data['completed_at'];
		}

		return $response;
	}

	/**
	 * Log export activity.
	 *
	 * Implements: FR-248 (Export History/Audit Log)
	 *
	 * @since 2.0.0
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $user_id         User who performed export.
	 * @param string $filename        Generated filename.
	 */
	private function log_export( int $conversation_id, int $user_id, string $filename ): void {
		global $wpdb;

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->export_logs_table
			)
		);

		if ( ! $table_exists ) {
			// Create table if it doesn't exist.
			$this->create_export_logs_table();
		}

		$wpdb->insert(
			$this->export_logs_table,
			array(
				'conversation_id' => $conversation_id,
				'user_id'         => $user_id,
				'filename'        => $filename,
				'export_type'     => 'pdf',
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create export logs table.
	 *
	 * @since 2.0.0
	 */
	private function create_export_logs_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->export_logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			filename varchar(255) NOT NULL,
			export_type varchar(20) NOT NULL DEFAULT 'pdf',
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get export history.
	 *
	 * @since 2.0.0
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @return array Export history with pagination.
	 */
	public function get_export_history( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->export_logs_table
			)
		);

		if ( ! $table_exists ) {
			return array(
				'exports'      => array(),
				'total'        => 0,
				'pages'        => 0,
				'current_page' => $page,
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->export_logs_table}" );

		$exports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT el.*, u.display_name AS user_name
				 FROM {$this->export_logs_table} AS el
				 LEFT JOIN {$wpdb->users} AS u ON el.user_id = u.ID
				 ORDER BY el.created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'exports'      => $exports,
			'total'        => $total,
			'pages'        => (int) ceil( $total / $per_page ),
			'current_page' => $page,
		);
	}

	/**
	 * Register GDPR data exporter.
	 *
	 * Implements: FR-249 (GDPR Data Export Support)
	 *
	 * @since 2.0.0
	 *
	 * @param array $exporters Existing exporters.
	 * @return array Modified exporters.
	 */
	public function register_data_exporter( array $exporters ): array {
		$exporters['ai-botkit-conversations'] = array(
			'exporter_friendly_name' => __( 'AI BotKit Conversations', 'knowvault' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Export personal data for GDPR.
	 *
	 * @since 2.0.0
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number.
	 * @return array Export data.
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;
		$per_page = 100;
		$offset   = ( $page - 1 ) * $per_page;

		// Get user's conversations.
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, cb.name AS chatbot_name
				 FROM {$this->conversations_table} AS c
				 LEFT JOIN {$this->chatbots_table} AS cb ON c.chatbot_id = cb.id
				 WHERE c.user_id = %d
				 ORDER BY c.created_at ASC
				 LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$export_items = array();

		foreach ( $conversations as $conversation ) {
			// Get messages for this conversation.
			$messages = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT role, content, created_at
					 FROM {$this->messages_table}
					 WHERE conversation_id = %d
					 ORDER BY created_at ASC",
					$conversation['id']
				),
				ARRAY_A
			);

			$message_data = array();
			foreach ( $messages as $message ) {
				$message_data[] = array(
					'name'  => sprintf(
						/* translators: 1: message role, 2: timestamp */
						__( '%1$s at %2$s', 'knowvault' ),
						ucfirst( $message['role'] ),
						$message['created_at']
					),
					'value' => $message['content'],
				);
			}

			$export_items[] = array(
				'group_id'          => 'ai-botkit-conversations',
				'group_label'       => __( 'AI BotKit Conversations', 'knowvault' ),
				'group_description' => __( 'Your conversations with AI chatbots.', 'knowvault' ),
				'item_id'           => 'conversation-' . $conversation['id'],
				'data'              => array_merge(
					array(
						array(
							'name'  => __( 'Chatbot', 'knowvault' ),
							'value' => $conversation['chatbot_name'] ?? __( 'Unknown', 'knowvault' ),
						),
						array(
							'name'  => __( 'Started', 'knowvault' ),
							'value' => $conversation['created_at'],
						),
					),
					$message_data
				),
			);
		}

		// Check if there are more pages.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->conversations_table} WHERE user_id = %d",
				$user->ID
			)
		);

		return array(
			'data' => $export_items,
			'done' => ( $offset + count( $conversations ) ) >= $total,
		);
	}

	/**
	 * Register GDPR data eraser.
	 *
	 * @since 2.0.0
	 *
	 * @param array $erasers Existing erasers.
	 * @return array Modified erasers.
	 */
	public function register_data_eraser( array $erasers ): array {
		$erasers['ai-botkit-conversations'] = array(
			'eraser_friendly_name' => __( 'AI BotKit Conversations', 'knowvault' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Erase personal data for GDPR.
	 *
	 * @since 2.0.0
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number.
	 * @return array Erasure result.
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$per_page = 100;
		$offset   = ( $page - 1 ) * $per_page;

		// Get user's conversations to delete.
		$conversation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$this->conversations_table}
				 WHERE user_id = %d
				 LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			)
		);

		$items_removed = 0;

		foreach ( $conversation_ids as $conversation_id ) {
			// Delete messages.
			$wpdb->delete(
				$this->messages_table,
				array( 'conversation_id' => $conversation_id ),
				array( '%d' )
			);

			// Delete conversation.
			$deleted = $wpdb->delete(
				$this->conversations_table,
				array( 'id' => $conversation_id ),
				array( '%d' )
			);

			if ( $deleted ) {
				++$items_removed;
			}
		}

		// Check if there are more pages.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->conversations_table} WHERE user_id = %d",
				$user->ID
			)
		);

		return array(
			'items_removed'  => $items_removed > 0,
			'items_retained' => false,
			'messages'       => array(
				sprintf(
					/* translators: %d: number of conversations deleted */
					_n(
						'Deleted %d conversation.',
						'Deleted %d conversations.',
						$items_removed,
						'knowvault'
					),
					$items_removed
				),
			),
			'done'           => ( $offset + count( $conversation_ids ) ) >= $total || 0 === $total,
		);
	}

	/**
	 * Schedule recurring export.
	 *
	 * Implements: FR-247 (Export Scheduling)
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings {
	 *     Schedule settings.
	 *
	 *     @type string $frequency   Frequency (daily, weekly, monthly).
	 *     @type string $time        Time of day (HH:MM format).
	 *     @type int    $chatbot_id  Optional chatbot filter.
	 *     @type string $email       Email to send exports to.
	 * }
	 * @return bool Success.
	 */
	public function schedule_recurring_export( array $settings ): bool {
		$defaults = array(
			'frequency'  => 'weekly',
			'time'       => '00:00',
			'chatbot_id' => 0,
			'email'      => get_option( 'admin_email' ),
		);
		$settings = wp_parse_args( $settings, $defaults );

		// Calculate next run time.
		$time_parts = explode( ':', $settings['time'] );
		$hour       = isset( $time_parts[0] ) ? (int) $time_parts[0] : 0;
		$minute     = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

		$next_run = strtotime( 'tomorrow ' . $hour . ':' . str_pad( $minute, 2, '0', STR_PAD_LEFT ) . ':00' );

		// Save settings.
		update_option( 'ai_botkit_scheduled_export_settings', $settings );

		// Clear existing schedule.
		$timestamp = wp_next_scheduled( 'ai_botkit_scheduled_export' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_botkit_scheduled_export' );
		}

		// Schedule new event.
		$recurrence = $settings['frequency'];
		if ( ! in_array( $recurrence, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			$recurrence = 'weekly';
		}

		// Register custom schedule for monthly if needed.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['monthly'] = array(
					'interval' => 30 * DAY_IN_SECONDS,
					'display'  => __( 'Monthly', 'knowvault' ),
				);
				return $schedules;
			}
		);

		return wp_schedule_event( $next_run, $recurrence, 'ai_botkit_scheduled_export', array( $settings ) ) !== false;
	}

	/**
	 * Run scheduled export.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings Export settings.
	 */
	public function run_scheduled_export( array $settings ): void {
		global $wpdb;

		// Get conversations from the last period.
		$start_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 ' . $settings['frequency'] ) );

		$where_conditions = array( 'c.created_at >= %s' );
		$where_params     = array( $start_date );

		if ( ! empty( $settings['chatbot_id'] ) ) {
			$where_conditions[] = 'c.chatbot_id = %d';
			$where_params[]     = $settings['chatbot_id'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$conversation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$this->conversations_table} AS c WHERE {$where_clause}",
				$where_params
			)
		);

		if ( empty( $conversation_ids ) ) {
			return;
		}

		// Process batch export.
		$batch_id = $this->schedule_export( $conversation_ids, array( 'include_branding' => true ) );
		$result   = $this->process_batch_export( $batch_id );

		// Send email notification.
		if ( ! is_wp_error( $result ) && ! empty( $settings['email'] ) ) {
			$this->send_export_notification( $settings['email'], $result, count( $conversation_ids ) );
		}
	}

	/**
	 * Send export notification email.
	 *
	 * @since 2.0.0
	 *
	 * @param string $email    Recipient email.
	 * @param string $zip_path Path to ZIP file.
	 * @param int    $count    Number of conversations exported.
	 */
	private function send_export_notification( string $email, string $zip_path, int $count ): void {
		$upload_dir   = wp_upload_dir();
		$download_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $zip_path );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Scheduled Chat Export Ready', 'knowvault' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: number of conversations, 2: download URL */
			__( "Your scheduled chat export is ready.\n\n%1\$d conversations have been exported.\n\nDownload your export: %2\$s\n\nThis link will expire in 24 hours.", 'knowvault' ),
			$count,
			$download_url
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Clean up temporary files.
	 *
	 * @since 2.0.0
	 *
	 * @param string $directory Directory to clean.
	 */
	private function cleanup_temp_files( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = glob( $directory . '*' );

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		rmdir( $directory );
	}
}
