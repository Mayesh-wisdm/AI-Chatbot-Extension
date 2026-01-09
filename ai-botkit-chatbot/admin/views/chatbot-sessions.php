<?php
defined('ABSPATH') || exit;

// Get current page and items per page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

$chatbot = new AI_BotKit\Models\Chatbot($bot_id);
$chatbot_data = $chatbot->get_data();

global $wpdb;
$total_sessions = $wpdb->get_var(
	$wpdb->prepare("
		SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_conversations
		WHERE chatbot_id = %d", $bot_id)
	);
$total_pages = ceil($total_sessions / $items_per_page);


// get sessions
$sessions = AI_BotKit\Models\Conversation::get_by_chatbot($bot_id, $items_per_page, $offset);

// Get all public post types
$post_types = get_post_types(['public' => true], 'objects');

global $wpdb;
$total_messages = $wpdb->get_var(
	$wpdb->prepare("
		SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_messages
		JOIN {$wpdb->prefix}ai_botkit_conversations ON {$wpdb->prefix}ai_botkit_messages.conversation_id = {$wpdb->prefix}ai_botkit_conversations.id
		WHERE {$wpdb->prefix}ai_botkit_conversations.chatbot_id = %d", $bot_id)
	);

$nonce = wp_create_nonce('ai_botkit_chatbots');
?>

<div class="ai-botkit-knowledge-container">

	<!-- Page Heading and Upload Buttons -->
	<div class="ai-botkit-knowledge-header">
		<div class="ai-botkit-knowledge-header-left">
			<a class="ai-botkit-btn-outline ai-botkit-btn-sm" id="ai-botkit-chatbot-wizard-back" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots&nonce=' . $nonce)); ?>">
                <i class="ti ti-arrow-left"></i>
				<?php esc_html_e('Back to Chatbots', 'knowvault'); ?>
			</a>
			<h1 class="ai-botkit-knowledge-title"><?php echo esc_html($chatbot_data['name']) . ' ' . esc_html__('Sessions', 'knowvault'); ?></h1>
			<p class="ai-botkit-knowledge-description"><?php esc_html_e('View user interactions with this chatbot', 'knowvault'); ?></p>
		</div>
	</div>

	<div class="ai-botkit-knowledge-stats">
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Sessions', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html(count($sessions)); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-users"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Messages', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($total_messages); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-message"></i>
			</div>
		</div>
	</div>

	<!-- Knowledge Base Table Placeholder -->
	<div class="ai-botkit-knowledge-table" id="ai-botkit-knowledge-table">
		<!-- Tabs + Search -->
		<div class="ai-botkit-knowledge-filters">
			<div class="ai-botkit-search-wrapper">
				<input type="text" id="ai-botkit-search-input" class="ai-botkit-search-input" placeholder="Search resources..." />
			</div>
		</div>
		<!-- Knowledge Base Table will load here -->
		<!-- Knowledge Base Table -->
		<div class="ai-botkit-knowledge-table-wrapper">

			<!-- If no data (show this placeholder) -->
			<?php if (empty($sessions)) { ?>
				<div id="ai-botkit-table-empty" class="ai-botkit-table-empty">
					<p><?php esc_html_e('No sessions found. Start a new conversation to get started.', 'knowvault'); ?></p>
				</div>
			<?php } else { ?>

			<!-- Table -->
			<div class="ai-botkit-table-container">
				<table class="ai-botkit-table">
					<thead>
					<tr>
						<th><?php esc_html_e('User', 'knowvault'); ?></th>
						<th><?php esc_html_e('Last Message', 'knowvault'); ?></th>
						<th><?php esc_html_e('Messages', 'knowvault'); ?></th>
						<th><?php esc_html_e('Actions', 'knowvault'); ?></th>
					</tr>
					</thead>
					<tbody id="ai-botkit-table-body">
					<?php foreach ($sessions as $session) {
						$user_id = $session['user_id'];
						$user_name = get_user_meta($user_id, 'first_name', true) . ' ' . get_user_meta($user_id, 'last_name', true);
						$last_message = $session['updated_at'];
						$message_count = $wpdb->get_var($wpdb->prepare("
							SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_messages
							WHERE conversation_id = %d", $session['id']));
						$session_url = admin_url('admin.php?page=ai-botkit&tab=chatbots&bot_id=' . $bot_id . '&chat_session_id=' . $session['id'] . '&nonce=' . $nonce);
						?>
						<tr>
							<td><?php echo esc_html($user_name); ?></td>
							<td><?php echo esc_html($last_message); ?></td>
							<td><?php echo esc_html($message_count); ?></td>
							<td>
								<a class="ai-botkit-btn-outline" href="<?php echo esc_url($session_url); ?>">
									<i class="ti ti-eye"></i>
								</a>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php } ?>
		</div>

		<div class="ai-botkit-pagination" id="ai-botkit-pagination">
    <a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
        array(
            'page'    => 'ai-botkit',
            'tab'     => 'chatbots',
            'bot_id'  => $bot_id,
            'paged'   => max(1, $current_page - 1),
			'nonce'   => $nonce,
        ),
        admin_url('admin.php')
    ) ); ?>">
        <i class="ti ti-chevron-left"></i>
    </a>
    <span id="ai-botkit-page-info"><?php echo esc_html__('Page', 'knowvault') . ' ' . esc_html($current_page) . ' ' . esc_html__('of', 'knowvault') . ' ' . esc_html($total_pages); ?></span>
    <a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
        array(
            'page'    => 'ai-botkit',
            'tab'     => 'chatbots',
            'bot_id'  => $bot_id,
            'paged'   => min($total_pages, $current_page + 1),
			'nonce'   => $nonce,
        ),
        admin_url('admin.php')
    ) ); ?>">
        <i class="ti ti-chevron-right"></i>
    </a>
</div>
	</div>
</div>
