<?php
defined('ABSPATH') || exit;

// Enqueue required scripts
wp_enqueue_script('ai-botkit-chartjs', AI_BOTKIT_PLUGIN_URL . 'admin/js/chart.js', array('jquery'), '4.4.1', true);
wp_enqueue_script('ai-botkit-chartjs-adapter', AI_BOTKIT_PLUGIN_URL . 'admin/js/chartjs-adapter-date-fns.js', array('ai-botkit-chartjs'), '3.0.0', true);

// nonce check
if (!isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_GET['nonce'] ) ), 'ai_botkit_chatbots' ) ) {
    wp_die(__('Invalid request', 'ai-botkit-for-lead-generation'));
}

// Get date range - always default to 7 days for consistency
$time_range = '7 days';

if ($time_range === '7 days') {
    $start_date = date('Y-m-d', strtotime('-7 days', current_time('timestamp')));
    $end_date = date('Y-m-d 23:59:59', current_time('timestamp'));
} elseif ($time_range === '30 days') {
    $start_date = date('Y-m-d', strtotime('-30 days', current_time('timestamp')));
    $end_date = date('Y-m-d 23:59:59', current_time('timestamp'));
} elseif ($time_range === '90 days') {
    $start_date = date('Y-m-d', strtotime('-90 days', current_time('timestamp')));
    $end_date = date('Y-m-d 23:59:59', current_time('timestamp'));
} elseif ($time_range === '1 year') {
    $start_date = date('Y-m-d', strtotime('-1 year', current_time('timestamp')));
    $end_date = date('Y-m-d 23:59:59', current_time('timestamp'));
}
// Get analytics data
$analytics = new AI_BotKit\Monitoring\Analytics(new AI_BotKit\Core\Unified_Cache_Manager());
$data = $analytics->get_dashboard_data([
    'start_date' => $start_date,
    'end_date' => $end_date
]);

$overview = $data['overview'];
$time_series = $data['time_series'];
$top_queries = $data['top_queries'];
$error_rates = $data['error_rates'];
$performance = $data['performance'];

wp_localize_script('ai-botkit-chartjs', 'ai_botkitAnalytics', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('ai_botkit_admin'),
    'timeSeriesData' => $time_series,
    'performanceData' => $performance,
    'currentTimeRange' => $time_range,
    'i18n' => array(
        'totalEvents' => __('Total Events', 'ai-botkit-for-lead-generation'),
        'avgResponseTime' => __('Avg Response Time (ms)', 'ai-botkit-for-lead-generation'),
        'errorRate' => __('Error Rate (%)', 'ai-botkit-for-lead-generation'),
        'tokenUsage' => __('Token Usage', 'ai-botkit-for-lead-generation'),
        'loading' => __('Loading...', 'ai-botkit-for-lead-generation'),
        'error' => __('Error loading data', 'ai-botkit-for-lead-generation'),
    )
));
?>

<div class="ai-botkit-knowledge-container">
    <div class="ai-botkit-knowledge-header">
		<div class="ai-botkit-knowledge-header-left">
            <h1 class="ai-botkit-knowledge-title"><?php esc_html_e('Analytics Dashboard', 'ai-botkit-for-lead-generation'); ?></h1>
            <p class="ai-botkit-knowledge-description"><?php esc_html_e('Track performance metrics for your chatbots', 'ai-botkit-for-lead-generation'); ?></p>
		</div>

		<div class="ai-botkit-knowledge-buttons">
            <div class="ai-botkit-form-group">
                <select id="ai_botkit_analytics_time_range">
                    <option value="7 days" <?php selected($time_range, '7 days'); ?>><?php esc_html_e('7 Days', 'ai-botkit-for-lead-generation'); ?></option>
                    <option value="30 days" <?php selected($time_range, '30 days'); ?>><?php esc_html_e('30 Days', 'ai-botkit-for-lead-generation'); ?></option>
                    <option value="90 days" <?php selected($time_range, '90 days'); ?>><?php esc_html_e('90 Days', 'ai-botkit-for-lead-generation'); ?></option>
                    <option value="1 year" <?php selected($time_range, '1 year'); ?>><?php esc_html_e('1 Year', 'ai-botkit-for-lead-generation'); ?></option>
                </select>
            </div>
		</div>
	</div>

    <div class="ai-botkit-knowledge-stats">
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Interactions', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html(number_format_i18n($overview['total_interactions'])); ?></span>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Conversations', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html(number_format_i18n($overview['total_conversations'])); ?></span>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Unique Users', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html(number_format_i18n($overview['total_users'])); ?></span>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Tokens Used', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html(number_format_i18n($overview['total_tokens'])); ?></span>
			</div>
		</div>
	</div>

    <div class="ai-botkit-charts-grid">
        <div class="ai-botkit-chart-card">
            <h3><?php esc_html_e('Daily Usage', 'ai-botkit-for-lead-generation'); ?></h3>
            <canvas id="usageChart"></canvas>
        </div>
        <div class="ai-botkit-chart-card">
            <h3><?php esc_html_e('Response Times', 'ai-botkit-for-lead-generation'); ?></h3>
            <canvas id="responseTimeChart"></canvas>
        </div>
        <div class="ai-botkit-chart-card">
            <h3><?php esc_html_e('Error Rates', 'ai-botkit-for-lead-generation'); ?></h3>
            <canvas id="errorChart"></canvas>
        </div>
        <div class="ai-botkit-chart-card">
            <h3><?php esc_html_e('Token Usage', 'ai-botkit-for-lead-generation'); ?></h3>
            <canvas id="tokenChart"></canvas>
        </div>
    </div>

    <div class="ai-botkit-data-grid">
        <div class="ai-botkit-data-card">
            <h3><?php esc_html_e('Top Query Types', 'ai-botkit-for-lead-generation'); ?></h3>
            <div class="ai-botkit-kb-content-scroll">
                <table class="ai-botkit-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Query Type', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Frequency', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Avg. Quality', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Avg. Response Time', 'ai-botkit-for-lead-generation'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ai-botkit-top-queries-table-body">
                        <?php if( !empty($top_queries) ){
                            foreach ($top_queries as $query): ?>
                                <tr>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $query['query_type']))); ?></td>
                                <td><?php echo esc_html(number_format_i18n($query['frequency'])); ?></td>
                                <td><?php echo esc_html(number_format($query['avg_quality'] * 100, 1)) . '%'; ?></td>
                                <td><?php echo esc_html(number_format($query['avg_response_time'], 2)) . 'ms'; ?></td>
                            </tr>
                            <?php endforeach;
                        } else {
                            ?>
                            <tr><td colspan="4" style="text-align:center;"><?php esc_html_e('No data found', 'ai-botkit-for-lead-generation'); ?></td></tr>
                            <?php
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ai-botkit-data-card">
            <h3><?php esc_html_e('Recent Errors', 'ai-botkit-for-lead-generation'); ?></h3>
            <div class="ai-botkit-kb-content-scroll">
                <table class="ai-botkit-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Error Type', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Count', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Component', 'ai-botkit-for-lead-generation'); ?></th>
                            <th><?php esc_html_e('Last Occurrence', 'ai-botkit-for-lead-generation'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if( !empty($error_rates) ){
                            foreach ($error_rates as $error): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $error['error_type']))); ?></td>
                                <td><?php echo esc_html(number_format_i18n($error['count'])); ?></td>
                                <td><?php echo esc_html($error['component']); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($error['last_occurrence']))); ?> ago</td>
                            </tr>
                            <?php endforeach;
                        } else {
                            ?>
                            <tr><td colspan="4" style="text-align:center;"><?php esc_html_e('No data found', 'ai-botkit-for-lead-generation'); ?></td></tr>
                            <?php
                        } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
