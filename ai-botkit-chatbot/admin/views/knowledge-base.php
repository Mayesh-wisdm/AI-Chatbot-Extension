<?php
defined('ABSPATH') || exit;

use AI_BotKit\Utils\Table_Helper;

// Get current page and items per page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

// nonce check
if (!isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_GET['nonce'] ) ), 'ai_botkit_chatbots' ) ) {
    wp_die(__('Invalid request', 'knowvault'));
}


$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';

// Get documents
global $wpdb;

// Calculate total documents based on filter type
if ($type !== 'all') {
	$total_documents = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents WHERE source_type = %s",
		$type
	));
	$documents = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ai_botkit_documents WHERE source_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$type,
		$items_per_page,
		$offset,
	));
} else {
	$total_documents = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents");
	$documents = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ai_botkit_documents ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$items_per_page,
		$offset,
	));
}

$total_pages = ceil($total_documents / $items_per_page);

$stats = $wpdb->get_results("SELECT source_type, COUNT(*) AS total
FROM {$wpdb->prefix}ai_botkit_documents
GROUP BY source_type;");

$wp_posts = 0;
$urls = 0;
$files = 0;

foreach ($stats as $stat) {
	if ( 'post' == $stat->source_type ) {
		$wp_posts = $stat->total;
	} elseif ( 'url' == $stat->source_type ) {
		$urls = $stat->total;
	} elseif ( 'file' == $stat->source_type ) {
		$files = $stat->total;
	}
}

// Get all public post types
$post_types = get_post_types(['public' => true], 'objects');

$nonce = wp_create_nonce('ai_botkit_chatbots');
?>

<!-- Hidden nonce for migration AJAX -->
<input type="hidden" id="ai_botkit_migration_nonce" value="<?php echo wp_create_nonce('ai_botkit_admin'); ?>" />

<div class="ai-botkit-knowledge-container">

	<!-- Page Heading and Upload Buttons -->
	<div class="ai-botkit-knowledge-header">
		<div class="ai-botkit-knowledge-header-left">
			<h1 class="ai-botkit-knowledge-title"><?php esc_html_e('Knowledge Base', 'knowvault'); ?></h1>
			<p class="ai-botkit-knowledge-description"><?php esc_html_e('Manage the resources your chatbots can access', 'knowvault'); ?></p>
		</div>

		<div class="ai-botkit-knowledge-buttons">
			<button class="ai-botkit-btn" id="ai-botkit-add-document"><?php esc_html_e('Upload Document', 'knowvault'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-add-url-btn"><?php esc_html_e('Add URL', 'knowvault'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-wordpress-btn"><?php esc_html_e('Import from WordPress', 'knowvault'); ?></button>
		</div>
	</div>

	<div class="ai-botkit-knowledge-stats">
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Resources', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($total_documents); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-database"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total URLs', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($urls); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-world"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Documents', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($files); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-file-text"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('WP Posts', 'knowvault'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($wp_posts); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-file-text"></i>
			</div>
		</div>
	</div>

	<!-- Database Migration Section -->
	<?php 
	// Only show migration section if Pinecone API key and host exist
	$pinecone_api_key = get_option('ai_botkit_pinecone_api_key', '');
	$pinecone_host = get_option('ai_botkit_pinecone_host', '');
	$pinecone_configured = !empty($pinecone_api_key) && !empty($pinecone_host);
	?>
	<?php if ($pinecone_configured): ?>
	<div class="ai-botkit-migration-section">
		<div class="ai-botkit-migration-header">
			<h3><?php esc_html_e('Database Management', 'knowvault'); ?></h3>
			<p><?php esc_html_e('Migrate data between local database and Pinecone vector storage', 'knowvault'); ?></p>
		</div>

		<div class="ai-botkit-migration-status">
			<h4><?php esc_html_e('Current Status', 'knowvault'); ?></h4>
			<div id="migration-status-display">
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Local Database:', 'knowvault'); ?></span>
					<span class="ai-botkit-status-value" id="local-db-status"><?php esc_html_e('Loading...', 'knowvault'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Pinecone Database:', 'knowvault'); ?></span>
					<span class="ai-botkit-status-value" id="pinecone-db-status"><?php esc_html_e('Loading...', 'knowvault'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Pinecone Connection:', 'knowvault'); ?></span>
					<span class="ai-botkit-status-value" id="pinecone-connection-status"><?php esc_html_e('Testing...', 'knowvault'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Migration Status:', 'knowvault'); ?></span>
					<span class="ai-botkit-status-value" id="migration-status"><?php esc_html_e('Loading...', 'knowvault'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Last Migration:', 'knowvault'); ?></span>
					<span class="ai-botkit-status-value" id="last-migration"><?php esc_html_e('Loading...', 'knowvault'); ?></span>
				</div>
			</div>
		</div>

		<div class="ai-botkit-migration-controls">
			<button type="button" id="ai-botkit-migration-btn" class="ai-botkit-btn ai-botkit-btn-secondary">
				<?php esc_html_e('Start Migration', 'knowvault'); ?>
			</button>
			<button type="button" id="ai-botkit-refresh-status-btn" class="ai-botkit-btn ai-botkit-btn-outline">
				<?php esc_html_e('Refresh Status', 'knowvault'); ?>
			</button>
			<div class="ai-botkit-clear-controls">
				<button type="button" id="ai-botkit-clear-local-btn" class="ai-botkit-btn ai-botkit-btn-warning" title="<?php esc_attr_e('Clear only vector data (chunks & embeddings). Preserves document metadata for knowledge base display.', 'knowvault'); ?>">
					<?php esc_html_e('Clear Vector Data', 'knowvault'); ?>
				</button>
				<button type="button" id="ai-botkit-clear-pinecone-btn" class="ai-botkit-btn ai-botkit-btn-warning" title="<?php esc_attr_e('Clear all data from Pinecone vector database.', 'knowvault'); ?>">
					<?php esc_html_e('Clear Pinecone', 'knowvault'); ?>
				</button>
				<button type="button" id="ai-botkit-clear-knowledge-base-btn" class="ai-botkit-btn ai-botkit-btn-danger" title="<?php esc_attr_e('Clear entire knowledge base including all documents, chunks, embeddings, and chatbot associations. This will remove everything from the knowledge base.', 'knowvault'); ?>">
					<?php esc_html_e('Clear Knowledge Base', 'knowvault'); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Knowledge Base Table Placeholder -->
	<div class="ai-botkit-knowledge-table" id="ai-botkit-knowledge-table">
		<!-- Tabs + Search -->
		<div class="ai-botkit-knowledge-filters">
			<div class="ai-botkit-training-tabs-list ai-botkit-tabs">
				<button class="ai-botkit-knowledge-tab <?php echo $type === 'all' ? 'active' : ''; ?>" data-tab="all" data-type="all" style="margin-bottom: 0;"><?php esc_html_e('All Resources', 'knowvault'); ?></button>
				<button class="ai-botkit-knowledge-tab <?php echo $type === 'file' ? 'active' : ''; ?>" data-tab="documents" data-type="file" style="margin-bottom: 0;"><?php esc_html_e('Documents', 'knowvault'); ?></button>
				<button class="ai-botkit-knowledge-tab <?php echo $type === 'url' ? 'active' : ''; ?>" data-tab="urls" data-type="url" style="margin-bottom: 0;"><?php esc_html_e('URLs', 'knowvault'); ?></button>
			</div>

			<div class="ai-botkit-search-wrapper">
				<input type="text" id="ai-botkit-search-input" class="ai-botkit-search-input" placeholder="<?php esc_html_e('Search resources...', 'knowvault'); ?>" />
			</div>

		</div>
		<!-- Knowledge Base Table will load here -->
		<!-- Knowledge Base Table -->
		<div class="ai-botkit-knowledge-table-wrapper">
			<!-- If no data (show this placeholder) -->
			<?php if (empty($documents)) { ?>
				<div id="ai-botkit-table-empty" class="ai-botkit-table-empty">
					<p>No resources found. Add documents or URLs to your knowledge base.</p>
				</div>
			<?php } else { ?>

			<!-- Bulk Actions Toolbar -->
			<div class="ai-botkit-bulk-actions-toolbar" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; display: none;">
				<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
					<span id="ai-botkit-selected-count" style="font-weight: 600; color: #2271b1;">0 <?php esc_html_e('selected', 'knowvault'); ?></span>

					<select id="ai-botkit-bulk-action" class="ai-botkit-input" style="width: auto;">
						<option value=""><?php esc_html_e('Bulk Actions', 'knowvault'); ?></option>
						<option value="delete"><?php esc_html_e('Delete', 'knowvault'); ?></option>
						<option value="reprocess"><?php esc_html_e('Reprocess', 'knowvault'); ?></option>
						<option value="add_to_bot"><?php esc_html_e('Add to Chatbot', 'knowvault'); ?></option>
						<option value="export"><?php esc_html_e('Export List', 'knowvault'); ?></option>
					</select>

					<div id="ai-botkit-bot-select-container" style="display: none;">
						<select id="ai-botkit-target-bot" class="ai-botkit-input" style="width: 200px;">
							<option value=""><?php esc_html_e('Select Chatbot', 'knowvault'); ?></option>
							<?php
							// Get all chatbots for the "Add to Bot" dropdown.
							$chatbots_table = Table_Helper::get_table_name('chatbots');
							$chatbots = $wpdb->get_results("SELECT id, name FROM {$chatbots_table} ORDER BY name ASC");
							foreach ($chatbots as $bot) {
								echo '<option value="' . esc_attr($bot->id) . '">' . esc_html($bot->name) . '</option>';
							}
							?>
						</select>
					</div>

					<button type="button" id="ai-botkit-apply-bulk-action" class="ai-botkit-button ai-botkit-button-primary">
						<?php esc_html_e('Apply', 'knowvault'); ?>
					</button>

					<button type="button" id="ai-botkit-clear-selection" class="ai-botkit-button" style="margin-left: auto;">
						<?php esc_html_e('Clear Selection', 'knowvault'); ?>
					</button>
				</div>
			</div>

			<!-- Table -->
			<div class="ai-botkit-table-container">
				<table class="ai-botkit-table" id="ai-botkit-knowledge-table">
					<thead>
					<tr>
						<th style="width: 40px;">
							<input type="checkbox" id="ai-botkit-select-all" title="<?php esc_attr_e('Select All', 'knowvault'); ?>">
						</th>
						<th><?php esc_html_e('Name', 'knowvault'); ?></th>
						<th><?php esc_html_e('Type', 'knowvault'); ?></th>
						<th><?php esc_html_e('Status', 'knowvault'); ?></th>
						<th><?php esc_html_e('Date Added', 'knowvault'); ?></th>
						<th><?php esc_html_e('Size/URL', 'knowvault'); ?></th>
						<th><?php esc_html_e('Actions', 'knowvault'); ?></th>
					</tr>
					</thead>
					<tbody id="ai-botkit-table-body">
					<?php foreach ($documents as $document) {
						$document_type = $document->source_type;
						$document_name = $document->title;
						$document_date = $document->created_at;
						if ( 'post' == $document_type ) {
							$document_url = '<a href="' . get_permalink($document->source_id) . '" target="_blank">' . get_the_title($document->source_id) . '</a>';
						} elseif ( 'url' == $document_type ) {
							$document_url = '<a href="' . $document->file_path . '" target="_blank">' . esc_html__('Visit URL', 'knowvault') . '</a>';
						} elseif ( 'file' == $document_type ) {
							$document_url = size_format( filesize( $document->file_path ), 2 );
						} else {
							// Handle other document types (LearnDash courses, WooCommerce products, etc.)
							$document_url = !empty($document->source_id) ? '<a href="' . get_permalink($document->source_id) . '" target="_blank">' . esc_html__('View', 'knowvault') . '</a>' : esc_html__('N/A', 'knowvault');
						}
						?>
						<tr data-document-id="<?php echo esc_attr($document->id); ?>">
							<td style="text-align: center;">
								<input type="checkbox" class="ai-botkit-document-checkbox" value="<?php echo esc_attr($document->id); ?>">
							</td>
							<td><?php echo strlen($document_name) > 20 ? substr($document_name, 0, 20) . '...' : esc_html($document_name); ?></td>
							<td><?php echo esc_html($document_type); ?></td>
							<td><?php
								if ( 'pending' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-warning">' . esc_html__('Pending', 'knowvault') . '</span>';
								} elseif ( 'processing' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-info">' . esc_html__('Processing', 'knowvault') . '</span>';
								} elseif ( 'completed' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-success">' . esc_html__('Completed', 'knowvault') . '</span>';
								} elseif ( 'failed' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-danger ai-botkit-error-clickable" data-document-id="' . esc_attr($document->id) . '" style="cursor: pointer;" title="Click to view error details">' . esc_html__('Failed', 'knowvault') . '</span>';
								} else {
									// Default case for NULL or unknown status
									echo '<span class="ai-botkit-badge ai-botkit-badge-secondary">' . esc_html__('Unknown', 'knowvault') . '</span>';
								}
							?></td>
							<td><?php echo esc_html($document_date); ?></td>
							<td><?php echo 'file' == $document_type ? esc_html($document_url) : wp_kses_post($document_url); ?></td>
							<td>
								<?php if ( 'completed' == $document->status ) { 
									// Set appropriate reprocess label based on document type
									$reprocess_title = '';
									if ( 'file' == $document_type ) {
										$reprocess_title = esc_attr__('Reprocess file', 'knowvault');
									} elseif ( 'post' == $document_type ) {
										$reprocess_title = esc_attr__('Reprocess post', 'knowvault');
									} elseif ( 'url' == $document_type ) {
										$reprocess_title = esc_attr__('Reprocess URL', 'knowvault');
									} else {
										$reprocess_title = esc_attr__('Reprocess document', 'knowvault');
									}
								?>
									<button class="ai-botkit-reprocess-btn" data-id="<?php echo esc_attr($document->id); ?>" data-type="<?php echo esc_attr($document_type); ?>" title="<?php echo $reprocess_title; ?>">
										<i class="ti ti-refresh"></i>
									</button>
								<?php } ?>
								<button class="ai-botkit-delete-btn" data-id="<?php echo esc_attr($document->id); ?>" title="<?php esc_attr_e('Delete document', 'knowvault'); ?>">
									<i class="ti ti-trash"></i>
								</button>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php } ?>
		</div>

		<div class="ai-botkit-pagination" id="ai-botkit-pagination">
			<button class="ai-botkit-btn-outline" id="ai-botkit-prev-page" data-page="<?php echo max(1, $current_page - 1); ?>" <?php echo $current_page <= 1 ? 'disabled' : ''; ?>>
				<i class="ti ti-chevron-left"></i>
			</button>

			<span id="ai-botkit-page-info">
				<?php
					echo esc_html__( 'Page', 'knowvault' ) . ' ' .
						esc_html( $current_page ) . ' ' .
						esc_html__( 'of', 'knowvault' ) . ' ' .
						esc_html( $total_pages );
				?>
			</span>

			<button class="ai-botkit-btn-outline" id="ai-botkit-next-page" data-page="<?php echo min($total_pages, $current_page + 1); ?>" <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>>
				<i class="ti ti-chevron-right"></i>
			</button>
		</div>

		<!-- Confirm Delete Modal -->
		<div id="ai-botkit-confirm-delete-modal" class="ai-botkit-modal-overlay">
			<div class="ai-botkit-kb-modal ai-botkit-delete-modal">
				<div class="ai-botkit-modal-header">
					<div class="ai-botkit-modal-icon">
						<i class="ti ti-alert-triangle"></i>
					</div>
					<h3><?php esc_html_e('Confirm Deletion', 'knowvault'); ?></h3>
				</div>
				<div class="ai-botkit-modal-body">
					<p><?php esc_html_e('Are you sure you want to delete this resource? This action cannot be undone.', 'knowvault'); ?></p>
				</div>
				<div class="ai-botkit-modal-footer">
					<button id="ai-botkit-cancel-delete" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
					<button id="ai-botkit-confirm-delete" class="ai-botkit-btn ai-botkit-btn-danger"><?php esc_html_e('Delete', 'knowvault'); ?></button>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Add URL Modal -->
<div id="ai-botkit-add-url-modal" class="ai-botkit-modal-overlay">
  <div class="ai-botkit-training-modal">
	
	<div class="ai-botkit-training-modal-header">
	  <h3><?php esc_html_e('Add URL', 'knowvault'); ?></h3>
	  <button id="ai-botkit-cancel-training-url-btn"><i class="ti ti-x"></i></button>
	</div>
	<p><?php esc_html_e('Add a website URL to your Knowledge Base', 'knowvault'); ?></p>

	<div class="ai-botkit-modal-body" style="padding: 0; gap:0;">
		<div class="ai-botkit-form-group">
			<label for="ai-botkit-url"><?php esc_html_e('URL', 'knowvault'); ?></label>
			<input type="text" id="ai-botkit-url" placeholder="https://example.com/page" />
		</div>
		<div class="ai-botkit-form-group">
			<label for="ai-botkit-url-title"><?php esc_html_e('Title (Optional)', 'knowvault'); ?></label>
			<input type="text" id="ai-botkit-url-title" placeholder="<?php esc_attr_e('Leave empty to auto-detect from page', 'knowvault'); ?>" />
			<small class="ai-botkit-help-text"><?php esc_html_e('If left empty, the page title will be automatically extracted from the URL.', 'knowvault'); ?></small>
		</div>
	</div>

	<div class="ai-botkit-training-modal-footer">
	  <button class="ai-botkit-btn-outline" id="ai-botkit-cancel-url-btn"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
	  <button class="ai-botkit-btn" id="ai-botkit-submit-url-btn">
		<span class="ai-botkit-btn-text"><?php esc_html_e('Add URL', 'knowvault'); ?></span>
		<span class="ai-botkit-btn-loading" style="display: none;">
			<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
			<?php esc_html_e('Adding...', 'knowvault'); ?>
		</span>
	  </button>
	</div>

  </div>
</div>

<div id="ai-botkit-wordpress-modal" class="ai-botkit-modal-overlay">
	<div class="ai-botkit-training-modal ai-botkit-training-wp-modal">
		
		<div class="ai-botkit-training-modal-header ai-botkit-wp-header">
			<h3><?php esc_html_e('WordPress', 'knowvault'); ?></h3>
			<button id="ai-botkit-cancel-training-wordpress-btn"><i class="ti ti-x"></i></button>
		</div>
		<div class="ai-botkit-training-modal-header ai-botkit-wp-header-back">
			<h3><i class="ti ti-chevron-left"></i> <?php esc_html_e('All', 'knowvault'); ?> <span class="ai-botkit-wp-header-post-title"><?php esc_html_e('Posts', 'knowvault'); ?></span></h3>
		</div>
		<p class="ai-botkit-training-modal-subtext"><?php esc_html_e('Add from your WordPress data to train your chatbot', 'knowvault'); ?></p>


		<div class="ai-botkit-training-modal-body ai-botkit-wp-types-modal">
		<?php foreach ($post_types as $post_type) {
			$posts = get_posts(array(
				'post_type' => $post_type->name,
				'posts_per_page' => -1,
			));
			if ( empty($posts) ) {
				continue;
			}
			?>
			<div class="ai-botkit-style-section">
				<div class="ai-botkit-style-header" data-type="<?php echo esc_attr($post_type->labels->singular_name); ?>">
					<label class="ai-botkit-wp-header-post-label" for="<?php echo esc_attr($post_type->name); ?>">
						<input type="checkbox" class="ai-botkit-checkbox ai-botkit-wp-checkbox" id="<?php echo esc_attr($post_type->name); ?>" value="<?php echo esc_attr($post_type->name); ?>" />
						<?php echo esc_html_e('All', 'knowvault'); ?> <?php echo esc_html($post_type->labels->singular_name); ?>
					</label>
					<div class="ai-botkit-wp-count-container">
						<div class="ai-botkit-wp-count" style="display: none;">
							<span class="ai-botkit-wp-count-number" data-type="<?php echo esc_attr($post_type->name); ?>">0</span>
							<span class="ai-botkit-wp-count-text"><?php esc_html_e('Selected', 'knowvault'); ?></span>
						</div>
						<i class="ti ti-chevron-right"></i>
					</div>
					
				</div>
				<div class="ai-botkit-style-content collapsed">
					<?php
					if ( empty($posts) ) {
						?>
						<div class="ai-botkit-notice">
							<?php esc_html_e('No posts found', 'knowvault'); ?>
						</div>
						<?php
					} else {
						?>
						<input
							class="ai-botkit-input ai-botkit-wp-search"
							placeholder="Search resources..."
						/>
						<div class="ai-botkit-kb-content-scroll">
							<table class="ai-botkit-table">
								
								<tbody id="ai-botkit-table-body">
									<?php
										foreach ($posts as $post) {
											$post_name = $post->post_title;
											$post_date = $post->post_date;
											?>
											<tr>
												<td>
													<input type="checkbox" class="ai-botkit-checkbox ai-botkit-wp-data-import" data-type="<?php echo esc_attr($post_type->name); ?>" value="<?php echo esc_attr($post->ID); ?>">
												</td>
												<td><?php echo esc_html($post_name); ?></td>
												<td><?php echo esc_html($post_date); ?></td>
											</tr>
											<?php
										}
									?>
								</tbody>
							</table>
						</div>
						<?php
						}
						?>
				</div>
			</div>
			<?php
			}
			?>
		</div>
		<div class="ai-botkit-training-modal-footer">
			<div class="ai-botkit-error-message" id="ai-botkit-wp-error-message"></div>
			<button class="ai-botkit-btn-outline" id="ai-botkit-cancel-training-wordpress-modal"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-import-wp">
				<span class="ai-botkit-btn-text"><?php esc_html_e('Add Data', 'knowvault'); ?></span>
				<span class="ai-botkit-btn-loading" style="display: none;">
					<i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
					<?php esc_html_e('Importing...', 'knowvault'); ?>
				</span>
			</button>
			<button class="ai-botkit-btn ai-botkit-wp-header-back" ><?php esc_html_e('Add Selected', 'knowvault'); ?></button>
		</div>
	</div>
</div>
<!-- Upload Modal -->
<div id="ai-botkit-upload-modal" class="ai-botkit-modal-overlay">
  <div class="ai-botkit-training-modal">

    <div class="ai-botkit-training-modal-header">
      <h3><?php esc_html_e('Upload Documents', 'knowvault'); ?></h3>
	  <button id="ai-botkit-cancel-training-document-btn"><i class="ti ti-x"></i></button>
    </div>
	<p><?php esc_html_e('Upload PDF documents to your Knowledge Base', 'knowvault'); ?></p>


    <div class="ai-botkit-modal-body" style="padding: 0;">
		<form id="ai-botkit-file-form">
			<div class="ai-botkit-upload-box" id="ai-botkit-document-upload-box">
				<label for="ai-botkit-submit-upload" class="ai-botkit-training-pdf-upload">
					<i class="ti ti-upload"></i>
					<?php esc_html_e('Upload a File', 'knowvault'); ?>
					<input
					id="ai-botkit-submit-upload"
					type="file"
					accept=".pdf"
					class="sr-only"
					/>
				</label>
				<p class="ai-botkit-help-text">
					<?php esc_html_e('Currently, you can upload PDF only. The PDF you upload will serve as the data source. Your chatbot will only train on the text contained within the PDF; images or GIFs will not be utilized.', 'knowvault'); ?>
				</p>
			</div>
			<div class="ai-botkit-upload-box hidden" id="ai-botkit-document-uploading">
				<p class="ai-botkit-training-pdf-upload"><i class="ti ti-loader-2 ai-botkit-loading-icon"></i> Uploading...</p>
			</div>
			<div class="ai-botkit-upload-box hidden" id="ai-botkit-document-uploaded">
				<p class="ai-botkit-training-pdf-upload"><i class="ti ti-circle-check"></i> Uploaded</p>
			</div>

		</form>
    </div>
  </div>
</div>

<!-- Migration Wizard Modal -->
<?php if ($pinecone_configured): ?>
<div id="ai-botkit-migration-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content">
        <div class="ai-botkit-modal-header">
            <h3><?php esc_html_e('Database Migration Wizard', 'knowvault'); ?></h3>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>
        
        <div class="ai-botkit-modal-body">
            <!-- Step 1: Migration Direction -->
            <div class="ai-botkit-migration-step" data-step="1">
                <h4><?php esc_html_e('Step 1: Choose Migration Direction', 'knowvault'); ?></h4>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_direction" value="to_pinecone" checked>
                        <span class="ai-botkit-radio-text">
                            <?php esc_html_e('Local to Pinecone', 'knowvault'); ?>
                            <br><?php esc_html_e('Migrate data from local database to Pinecone', 'knowvault'); ?>
                        </span>
                    </label>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_direction" value="to_local">
                        <span class="ai-botkit-radio-text">
                            <?php esc_html_e('Pinecone to Local', 'knowvault'); ?>
                            <br><?php esc_html_e('Migrate data from Pinecone to local database', 'knowvault'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Step 2: Migration Scope -->
            <div class="ai-botkit-migration-step" data-step="2" style="display: none;">
                <h4><?php esc_html_e('Step 2: Choose Migration Scope', 'knowvault'); ?></h4>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_scope" value="all" checked>
                        <span class="ai-botkit-radio-text">
                            <?php esc_html_e('All Data', 'knowvault'); ?>
                            <br><?php esc_html_e('Migrate all available data', 'knowvault'); ?>
                        </span>
                    </label>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_scope" value="by_type">
                        <span class="ai-botkit-radio-text">
                            <?php esc_html_e('By Content Type', 'knowvault'); ?>
                            <br><?php esc_html_e('Select specific content types to migrate', 'knowvault'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Step 3: Content Type Selection -->
            <div class="ai-botkit-migration-step" data-step="3" style="display: none;">
                <h4><?php esc_html_e('Step 3: Select Content Types', 'knowvault'); ?></h4>
                <div id="content-types-selection">
                    <p><?php esc_html_e('Loading content types...', 'knowvault'); ?></p>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="ai-botkit-migration-step" data-step="4" style="display: none;">
                <h4><?php esc_html_e('Step 4: Confirm Migration', 'knowvault'); ?></h4>
                <div id="migration-summary">
                    <p><?php esc_html_e('Review your migration settings:', 'knowvault'); ?></p>
                    <ul id="migration-summary-list"></ul>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-checkbox-label">
                        <input type="checkbox" id="migration_confirm" required>
                        <span class="ai-botkit-checkbox-text"><?php esc_html_e('I understand that this migration may take some time and I have backed up my data', 'knowvault'); ?></span>
                    </label>
                </div>
            </div>

            <!-- Progress Step -->
            <div class="ai-botkit-migration-step" data-step="progress" style="display: none;">
                <h4><?php esc_html_e('Migration in Progress', 'knowvault'); ?></h4>
                <div class="ai-botkit-progress-container">
                    <div class="ai-botkit-progress-bar">
                        <div class="ai-botkit-progress-fill" id="migration-progress-fill"></div>
                    </div>
                    <div class="ai-botkit-progress-text" id="migration-progress-text"><?php esc_html_e('Starting migration...', 'knowvault'); ?></div>
                </div>
                <div id="migration-log" class="ai-botkit-migration-log"></div>
            </div>
        </div>
        
        <div class="ai-botkit-modal-footer">
            <button type="button" id="ai-botkit-migration-prev" class="ai-botkit-btn ai-botkit-btn-outline" style="display: none;">
                <?php esc_html_e('Previous', 'knowvault'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-next" class="ai-botkit-btn">
                <?php esc_html_e('Next', 'knowvault'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-start" class="ai-botkit-btn ai-botkit-btn-primary" style="display: none;">
                <?php esc_html_e('Start Migration', 'knowvault'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-close" class="ai-botkit-btn ai-botkit-btn-outline" style="display: none;">
                <?php esc_html_e('Close', 'knowvault'); ?>
            </button>
    </div>
  </div>
</div>
<?php endif; ?>

