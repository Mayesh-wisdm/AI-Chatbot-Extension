<?php
defined('ABSPATH') || exit;

$chatbot = new AI_BotKit\Models\Chatbot($bot_id);
$chatbot_data = $chatbot->get_data();

global $wpdb;
$session = $wpdb->get_row(
	$wpdb->prepare("
		SELECT * FROM {$wpdb->prefix}ai_botkit_conversations
		WHERE id = %d", $chat_session_id)
	);

// get sessions
$messages = $wpdb->get_results(
	$wpdb->prepare("
		SELECT * FROM {$wpdb->prefix}ai_botkit_messages
		WHERE conversation_id = %d", $chat_session_id)
	);
$total_messages = count($messages);
// calculate duration
$date = gmdate('Y-m-d', strtotime($session->created_at));
$time = gmdate('H:i:s', strtotime($session->created_at));

// Calculate total tokens used in this conversation
$total_tokens = 0;
foreach ($messages as $message) {
	$metadata = json_decode($message->metadata, true);
	if (isset($metadata['tokens'])) {
		$total_tokens += (int) $metadata['tokens'];
	}
}

$user_name = get_user_meta($session->user_id, 'first_name', true) . ' ' . get_user_meta($session->user_id, 'last_name', true);
$chatbot_name = $wpdb->get_var(
	$wpdb->prepare("
		SELECT name FROM {$wpdb->prefix}ai_botkit_chatbots
		WHERE id = %d", $session->chatbot_id)
	);

$nonce = wp_create_nonce('ai_botkit_chatbots');
?>

<div class="ai-botkit-knowledge-container">

	<!-- Page Heading and Upload Buttons -->
	<div class="ai-botkit-knowledge-header">
		<div class="ai-botkit-knowledge-header-left">
			<a class="ai-botkit-btn-outline ai-botkit-btn-sm" id="ai-botkit-chatbot-wizard-back" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots&bot_id=' . $session->chatbot_id . '&nonce=' . $nonce)); ?>">
                <i class="ti ti-arrow-left"></i>
				<?php esc_html_e('Back to Chat Sessions', 'ai-botkit-for-lead-generation'); ?>
			</a>
			<h1 class="ai-botkit-knowledge-title"><?php esc_html_e('Conversation', 'ai-botkit-for-lead-generation'); ?></h1>
		</div>
	</div>

	<div class="ai-botkit-chat-session-container">
		<div class="ai-botkit-chat-session-details">
			<div class="ai-botkit-chat-session-details-item">
				<h2><?php esc_html_e('Session Details', 'ai-botkit-for-lead-generation'); ?></h2>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('User', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-user"></i> <?php echo esc_html($user_name); ?></p>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('Chatbot', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-robot"></i> <?php echo esc_html($chatbot_name); ?></p>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('Date', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-calendar"></i> <?php echo esc_html($date); ?></p>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('Time', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-clock"></i> <?php echo esc_html($time); ?></p>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('Messages', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-message"></i> <?php echo esc_html($total_messages); ?></p>
			</div>
			<div class="ai-botkit-chat-session-details-item">
				<p><?php esc_html_e('Tokens Utilized', 'ai-botkit-for-lead-generation'); ?></p>
				<p><i class="ti ti-ticket"></i> <?php echo esc_html(number_format_i18n($total_tokens)); ?></p>
			</div>
		</div>
		<div class="ai-botkit-chat-session-messages">
			<div class="ai-botkit-chat-session-messages-item">
				<span class="ai-botkit-chat-session-messages-item-label"><?php esc_html_e('Messages', 'ai-botkit-for-lead-generation'); ?></span>
				<div class="ai-botkit-chat-session-messages-item-chat">
					<div class="ai-botkit-chat-header">
						<!-- <div class="ai-botkit-chat-avatar">
							<span>🤖</span>
						</div> -->
						<div>
							<p class="ai-botkit-chat-title" id="ai-botkit-bot-name"><?php echo esc_html($chatbot_name); ?></p>
						</div>
					</div>

					<div class="ai-botkit-chat-body" id="ai-botkit-chat-body" style="min-height: 500px; overflow-y: auto;">
						<?php foreach ($messages as $message) {
							if ($message->role == 'user') {
								echo '<div class="ai-botkit-chat-msg user-msg">';
							} else {
								echo '<div class="ai-botkit-chat-msg bot-msg">';
							}
							echo '<p>' . esc_html($message->content) . '</p>';
							echo '</div>';
						} ?>
					</div>
				</div>
			</div>
			
		</div>
	</div>
</div>
