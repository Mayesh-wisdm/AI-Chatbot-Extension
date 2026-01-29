<?php
/**
 * PDF Transcript Template
 *
 * Template for generating PDF exports of chat conversations.
 * Used by Export_Handler::generate_pdf_html().
 *
 * @package AI_BotKit\Features\Templates
 * @since   2.0.0
 *
 * Available variables:
 * @var string $site_name     Site name.
 * @var string $logo_url      Site logo URL.
 * @var string $primary_color Primary brand color (hex).
 * @var string $export_date   Export date formatted string.
 * @var array  $export_data   {
 *     Export data.
 *
 *     @type int    $conversation_id Conversation ID.
 *     @type int    $chatbot_id      Chatbot ID.
 *     @type string $chatbot_name    Chatbot name.
 *     @type array  $chatbot_style   Chatbot style settings.
 *     @type array  $user            User info (display_name, email).
 *     @type string $session_id      Session ID.
 *     @type string $created_at      Conversation start time.
 *     @type string $updated_at      Last update time.
 *     @type array  $messages        Array of messages.
 *     @type int    $message_count   Total message count.
 * }
 * @var array  $messages      Formatted messages array.
 * @var array  $options       Export options (include_metadata, include_branding, paper_size).
 *
 * Implements: FR-241 (PDF Generation), FR-242 (PDF Branding)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Calculate some display values.
$user_display_name = ! empty( $export_data['user'] ) ? $export_data['user']['display_name'] : __( 'Guest User', 'knowvault' );
$conversation_date = gmdate( 'F j, Y', strtotime( $export_data['created_at'] ) );
$conversation_time = gmdate( 'g:i a', strtotime( $export_data['created_at'] ) );

// Calculate if we need pagination warning.
$needs_pagination = count( $messages ) > 100;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( __( 'Chat Transcript - %s', 'knowvault' ), $export_data['chatbot_name'] ) ); ?></title>
	<style>
		/* Base Styles */
		@page {
			margin: 60px 50px 80px 50px;
		}

		* {
			box-sizing: border-box;
		}

		body {
			font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
			font-size: 11pt;
			line-height: 1.6;
			color: #333333;
			margin: 0;
			padding: 0;
			background: #ffffff;
		}

		/* Header Section */
		.header {
			text-align: center;
			padding-bottom: 20px;
			margin-bottom: 25px;
			border-bottom: 3px solid <?php echo esc_attr( $primary_color ); ?>;
		}

		.header .logo {
			max-width: 120px;
			max-height: 50px;
			margin-bottom: 12px;
		}

		.header h1 {
			font-size: 22pt;
			font-weight: 700;
			color: <?php echo esc_attr( $primary_color ); ?>;
			margin: 0 0 8px 0;
			letter-spacing: -0.5px;
		}

		.header .subtitle {
			font-size: 13pt;
			color: #666666;
			margin: 0;
			font-weight: 400;
		}

		.header .chatbot-badge {
			display: inline-block;
			background: <?php echo esc_attr( $primary_color ); ?>;
			color: #ffffff;
			padding: 4px 12px;
			border-radius: 12px;
			font-size: 10pt;
			margin-top: 10px;
		}

		/* Metadata Section */
		.metadata {
			background: #f8f9fa;
			border: 1px solid #e9ecef;
			border-radius: 8px;
			padding: 18px 20px;
			margin-bottom: 25px;
		}

		.metadata-grid {
			display: table;
			width: 100%;
		}

		.metadata-row {
			display: table-row;
		}

		.metadata-cell {
			display: table-cell;
			padding: 5px 15px 5px 0;
			font-size: 10pt;
			vertical-align: top;
			width: 50%;
		}

		.metadata-label {
			font-weight: 600;
			color: #495057;
		}

		.metadata-value {
			color: #6c757d;
		}

		/* Messages Container */
		.messages {
			margin-bottom: 40px;
		}

		.messages-header {
			font-size: 12pt;
			font-weight: 600;
			color: <?php echo esc_attr( $primary_color ); ?>;
			border-bottom: 1px solid #dee2e6;
			padding-bottom: 8px;
			margin-bottom: 20px;
		}

		/* Message Bubble Styles */
		.message {
			margin-bottom: 18px;
			page-break-inside: avoid;
			clear: both;
		}

		.message-container {
			max-width: 85%;
			padding: 14px 18px;
			border-radius: 16px;
			position: relative;
		}

		/* User Message */
		.message.user .message-container {
			float: right;
			background: <?php echo esc_attr( $primary_color ); ?>;
			color: #ffffff;
			border-bottom-right-radius: 4px;
		}

		.message.user .role-label {
			color: rgba(255, 255, 255, 0.85);
		}

		.message.user .timestamp {
			color: rgba(255, 255, 255, 0.7);
		}

		/* Assistant Message */
		.message.assistant .message-container {
			float: left;
			background: #f1f3f4;
			color: #333333;
			border-bottom-left-radius: 4px;
		}

		.message.assistant .role-label {
			color: #666666;
		}

		.message.assistant .timestamp {
			color: #999999;
		}

		/* Message Content */
		.role-label {
			font-size: 9pt;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 6px;
		}

		.message-content {
			font-size: 11pt;
			line-height: 1.7;
			word-wrap: break-word;
		}

		.message-content p {
			margin: 0 0 10px 0;
		}

		.message-content p:last-child {
			margin-bottom: 0;
		}

		.message-content ul,
		.message-content ol {
			margin: 8px 0;
			padding-left: 20px;
		}

		.message-content li {
			margin-bottom: 4px;
		}

		.message-content code {
			background: rgba(0, 0, 0, 0.1);
			padding: 2px 6px;
			border-radius: 3px;
			font-family: "DejaVu Sans Mono", "Courier New", monospace;
			font-size: 10pt;
		}

		.message-content pre {
			background: rgba(0, 0, 0, 0.08);
			padding: 12px;
			border-radius: 6px;
			overflow-x: auto;
			font-family: "DejaVu Sans Mono", "Courier New", monospace;
			font-size: 9pt;
			line-height: 1.4;
		}

		.message-content a {
			color: inherit;
			text-decoration: underline;
		}

		.timestamp {
			font-size: 8pt;
			margin-top: 8px;
			text-align: right;
		}

		/* Clearfix */
		.message::after {
			content: "";
			display: table;
			clear: both;
		}

		/* Pagination Notice */
		.pagination-notice {
			background: #fff3cd;
			border: 1px solid #ffc107;
			border-radius: 6px;
			padding: 12px 15px;
			margin-bottom: 20px;
			font-size: 10pt;
			color: #856404;
		}

		/* Footer */
		.footer {
			position: fixed;
			bottom: -40px;
			left: 0;
			right: 0;
			text-align: center;
			font-size: 9pt;
			color: #999999;
			padding-top: 15px;
			border-top: 1px solid #e9ecef;
		}

		.footer-content {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.footer-left {
			text-align: left;
		}

		.footer-center {
			text-align: center;
		}

		.footer-right {
			text-align: right;
		}

		/* Print Optimizations */
		@media print {
			body {
				print-color-adjust: exact;
				-webkit-print-color-adjust: exact;
			}

			.message {
				page-break-inside: avoid;
			}
		}
	</style>
</head>
<body>
	<!-- Header -->
	<div class="header">
		<?php if ( $options['include_branding'] && ! empty( $logo_url ) ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="logo">
		<?php endif; ?>

		<h1><?php esc_html_e( 'Chat Transcript', 'knowvault' ); ?></h1>

		<p class="subtitle"><?php echo esc_html( $site_name ); ?></p>

		<span class="chatbot-badge">
			<?php echo esc_html( $export_data['chatbot_name'] ); ?>
		</span>
	</div>

	<!-- Metadata -->
	<?php if ( $options['include_metadata'] ) : ?>
		<div class="metadata">
			<div class="metadata-grid">
				<div class="metadata-row">
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'Export Date:', 'knowvault' ); ?></span>
						<span class="metadata-value"><?php echo esc_html( $export_date ); ?></span>
					</div>
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'Conversation ID:', 'knowvault' ); ?></span>
						<span class="metadata-value">#<?php echo esc_html( $export_data['conversation_id'] ); ?></span>
					</div>
				</div>
				<div class="metadata-row">
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'Started:', 'knowvault' ); ?></span>
						<span class="metadata-value"><?php echo esc_html( $conversation_date . ' ' . __( 'at', 'knowvault' ) . ' ' . $conversation_time ); ?></span>
					</div>
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'User:', 'knowvault' ); ?></span>
						<span class="metadata-value"><?php echo esc_html( $user_display_name ); ?></span>
					</div>
				</div>
				<div class="metadata-row">
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'Total Messages:', 'knowvault' ); ?></span>
						<span class="metadata-value"><?php echo esc_html( $export_data['message_count'] ); ?></span>
					</div>
					<div class="metadata-cell">
						<span class="metadata-label"><?php esc_html_e( 'Chatbot:', 'knowvault' ); ?></span>
						<span class="metadata-value"><?php echo esc_html( $export_data['chatbot_name'] ); ?></span>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Pagination Notice for Large Conversations -->
	<?php if ( $needs_pagination ) : ?>
		<div class="pagination-notice">
			<?php
			printf(
				/* translators: %d: message count */
				esc_html__( 'This conversation contains %d messages. Some pages may have extended content.', 'knowvault' ),
				count( $messages )
			);
			?>
		</div>
	<?php endif; ?>

	<!-- Messages -->
	<div class="messages">
		<div class="messages-header">
			<?php esc_html_e( 'Conversation', 'knowvault' ); ?>
		</div>

		<?php foreach ( $messages as $message ) : ?>
			<?php
			$role_class = esc_attr( $message['role'] );
			$role_label = 'user' === $message['role']
				? __( 'You', 'knowvault' )
				: $export_data['chatbot_name'];
			?>
			<div class="message <?php echo $role_class; ?>">
				<div class="message-container">
					<div class="role-label"><?php echo esc_html( $role_label ); ?></div>
					<div class="message-content">
						<?php echo wp_kses_post( $message['content'] ); ?>
					</div>
					<?php if ( $options['include_metadata'] ) : ?>
						<div class="timestamp"><?php echo esc_html( $message['timestamp'] ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Footer -->
	<div class="footer">
		<table style="width: 100%;">
			<tr>
				<td style="width: 33%; text-align: left;">
					<?php echo esc_html( $site_name ); ?>
				</td>
				<td style="width: 34%; text-align: center;">
					<?php esc_html_e( 'Generated by AI BotKit', 'knowvault' ); ?>
				</td>
				<td style="width: 33%; text-align: right;">
					<?php echo esc_html( $export_date ); ?>
				</td>
			</tr>
		</table>
	</div>
</body>
</html>
