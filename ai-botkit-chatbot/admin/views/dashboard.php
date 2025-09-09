<?php
defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$admin_name = $current_user->first_name . ' ' . $current_user->last_name;
$admin_email = $current_user->user_email;

$is_first_install = get_option('ai_botkit_setup_completed');
if ( $is_first_install ) {
	update_option('ai_botkit_setup_completed', false);
}

// Check if chatbots exist
global $wpdb;
$total_chatbots = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chatbots");
$has_chatbots = $total_chatbots > 0;

?>
<div class="ai-botkit-welcome-container">
	<div class="ai-botkit-top-bar">
		<a class="ai-botkit-btn-outline" id="ai-botkit-ask-assistant" >
			<?php esc_html_e('Ask Assistant', 'ai-botkit-for-lead-generation'); ?>
		</a>
		<a class="ai-botkit-btn-outline" href="<?php echo esc_url("https://aibotkit.gitbook.io/documentation"); ?>" target="_blank">
			<?php esc_html_e('Documentation', 'ai-botkit-for-lead-generation'); ?>
		</a>
	</div>
	<h1 class="ai-botkit-welcome-title">
		<?php esc_html_e('Welcome to AI BotKit', 'ai-botkit-for-lead-generation'); ?>
	</h1>

	<div class="ai-botkit-feature-grid">
		<div class="ai-botkit-feature-card">
			<h3 class="ai-botkit-feature-title">
				<?php esc_html_e('Get Started', 'ai-botkit-for-lead-generation'); ?>
			</h3>
			<div class="ai-botkit-feature-wrapper">
				<h3><?php esc_html_e('1. Connect to your AI Model', 'ai-botkit-for-lead-generation'); ?></h3>
				<p class="ai-botkit-feature-desc">
					<?php esc_html_e('Visit', 'ai-botkit-for-lead-generation'); ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=settings')); ?>" target="_blank">
					<?php esc_html_e('settings', 'ai-botkit-for-lead-generation'); ?></a> <?php esc_html_e('and link an LLM to power your chatbots', 'ai-botkit-for-lead-generation'); ?>
					<a href="<?php echo esc_url("https://aibotkit.gitbook.io/documentation/guides/configure-llm"); ?>" target="_blank">
						<?php esc_html_e('Learn more', 'ai-botkit-for-lead-generation'); ?>
					</a>
				</p>
			</div>
			<div class="ai-botkit-feature-wrapper">
				<h3>
					<?php if ($has_chatbots): ?>
						<?php esc_html_e('2. Manage your chatbots', 'ai-botkit-for-lead-generation'); ?>
					<?php else: ?>
						<?php esc_html_e('2. Create your first chatbot', 'ai-botkit-for-lead-generation'); ?>
					<?php endif; ?>
				</h3>
				<p class="ai-botkit-feature-desc">
					<?php if ($has_chatbots): ?>
						<?php esc_html_e('You have', 'ai-botkit-for-lead-generation'); ?> <strong><?php echo esc_html($total_chatbots); ?></strong> <?php echo $total_chatbots === 1 ? esc_html__('chatbot', 'ai-botkit-for-lead-generation') : esc_html__('chatbots', 'ai-botkit-for-lead-generation'); ?>. <?php esc_html_e('Manage them or create new ones', 'ai-botkit-for-lead-generation'); ?>
					<?php else: ?>
						<?php esc_html_e('Build a sitewide bot or add one to any page', 'ai-botkit-for-lead-generation'); ?>
					<?php endif; ?>
					<a href="<?php echo esc_url('https://aibotkit.gitbook.io/documentation/guides/create-chatbot'); ?>" target="_blank">
						<?php esc_html_e('Learn more', 'ai-botkit-for-lead-generation'); ?>
					</a>
				</p>
			</div>
			<a id="ai-botkit-get-started" class="ai-botkit-button-lg" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots' . ($has_chatbots ? '' : '&create=1'))); ?>">
				<?php if ($has_chatbots): ?>
					<?php esc_html_e('Manage Chatbots', 'ai-botkit-for-lead-generation'); ?>
				<?php else: ?>
					<?php esc_html_e('Create Your First Chatbot', 'ai-botkit-for-lead-generation'); ?>
				<?php endif; ?>
			</a>
		</div>
		<div class="ai-botkit-feature-card">
			<h3 class="ai-botkit-feature-title">
				<?php esc_html_e('Product Support', 'ai-botkit-for-lead-generation'); ?>
			</h3>
			<div class="ai-botkit-support-wrapper">
				<i class="ti ti-info-circle ai-botkit-support-icon"></i>
				<div class="ai-botkit-support-wrapper-content">
					<h3><?php esc_html_e('Documentation', 'ai-botkit-for-lead-generation'); ?></h3>
					<p class="ai-botkit-feature-desc">
						<?php esc_html_e('Step by step guide and API reference', 'ai-botkit-for-lead-generation'); ?>
						<a href="<?php echo esc_url("https://aibotkit.gitbook.io/documentation"); ?>" target="_blank">
							<?php esc_html_e('Learn more', 'ai-botkit-for-lead-generation'); ?>
						</a>
					</p>
				</div>
			</div>
			<div class="ai-botkit-support-wrapper">
				<i class="ti ti-mail ai-botkit-support-icon"></i>
				<div class="ai-botkit-support-wrapper-content">
					<h3><?php esc_html_e('Email Support', 'ai-botkit-for-lead-generation'); ?></h3>
					<p class="ai-botkit-feature-desc">
						<?php esc_html_e('Reach us anytime at', 'ai-botkit-for-lead-generation'); ?>
						<a href="<?php echo esc_url("mailto:contact@aibotkit.io"); ?>" target="_blank">
							<?php esc_html_e('contact@aibotkit.io', 'ai-botkit-for-lead-generation'); ?>
						</a>
					</p>
				</div>
			</div>
			<div class="ai-botkit-support-wrapper">
				<i class="ti ti-message ai-botkit-support-icon"></i>
				<div class="ai-botkit-support-wrapper-content">
					<h3><?php esc_html_e('Ask Assistant', 'ai-botkit-for-lead-generation'); ?></h3>
					<p class="ai-botkit-feature-desc">
						<?php esc_html_e('Instant help from our AI assistant, trained on all our documentation', 'ai-botkit-for-lead-generation'); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<p class="ai-botkit-login-note">
		<?php esc_html_e('Already have a chatbot?', 'ai-botkit-for-lead-generation'); ?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots')); ?>" class="ai-botkit-link">
			<?php esc_html_e('Go to dashboard', 'ai-botkit-for-lead-generation'); ?>
		</a>
	</p>
</div>
<div id="ai-botkit-activation-modal" class="ai-botkit-modal-overlay" style="display: <?php echo $is_first_install ? 'block' : 'block'; ?>;">
	<div class="ai-botkit-kb-modal">
		<div class="ai-botkit-modal-header">
			<h3><?php esc_html_e('Welcome to AI BotKit Plugin', 'ai-botkit-for-lead-generation'); ?></h3>
			<p><?php esc_html_e('Thanks for activating the plugin. To personalize your experience and provide better support, we’d like to collect basic site and admin info. We do not collect any sensitive or customer data.', 'ai-botkit-for-lead-generation'); ?></p>
		</div>
		<div class="ai-botkit-modal-body">
		<iframe data-tally-src="https://tally.so/embed/woNQr1?alignLeft=1&hideTitle=1&transparentBackground=1&dynamicHeight=1&site=<?php echo esc_url(get_site_url()); ?>" loading="lazy" width="100%" height="200" frameborder="0" marginheight="0" marginwidth="0" title="Feedback"></iframe>
			<script>var d=document,w="https://tally.so/widgets/embed.js",v=function(){"undefined"!=typeof Tally?Tally.loadEmbeds():d.querySelectorAll("iframe[data-tally-src]:not([src])").forEach((function(e){e.src=e.dataset.tallySrc}))};if("undefined"!=typeof Tally)v();else if(d.querySelector('script[src="'+w+'"]')==null){var s=d.createElement("script");s.src=w,s.onload=v,s.onerror=v,d.body.appendChild(s);}</script>
		</div>
		<div class="ai-botkit-modal-footer">
			<button id="ai-botkit-cancel-activation" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'ai-botkit-for-lead-generation'); ?></button>
		</div>
	</div>
</div>
