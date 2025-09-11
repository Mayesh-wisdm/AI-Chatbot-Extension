<?php
defined('ABSPATH') || exit;

// Get current page and items per page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

// nonce check
if (!isset($_GET['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_GET['nonce'] ) ), 'ai_botkit_chatbots' ) ) {
    wp_die(__('Invalid request', 'ai-botkit-for-lead-generation'));
}


$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';

// Get documents
global $wpdb;
$total_documents = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_documents");
$total_pages = ceil($total_documents / $items_per_page);


if ($type !== 'all') {
	$documents = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ai_botkit_documents WHERE source_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$type,
		$items_per_page,
		$offset,
	));
} else {
	$documents = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ai_botkit_documents ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$items_per_page,
		$offset,
	));
}

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
			<h1 class="ai-botkit-knowledge-title"><?php esc_html_e('Knowledge Base', 'ai-botkit-for-lead-generation'); ?></h1>
			<p class="ai-botkit-knowledge-description"><?php esc_html_e('Manage the resources your chatbots can access', 'ai-botkit-for-lead-generation'); ?></p>
		</div>

		<div class="ai-botkit-knowledge-buttons">
			<button class="ai-botkit-btn" id="ai-botkit-add-document"><?php esc_html_e('Upload Document', 'ai-botkit-for-lead-generation'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-add-url-btn"><?php esc_html_e('Add URL', 'ai-botkit-for-lead-generation'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-wordpress-btn"><?php esc_html_e('Import from WordPress', 'ai-botkit-for-lead-generation'); ?></button>
		</div>
	</div>

	<div class="ai-botkit-knowledge-stats">
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Resources', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($total_documents); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-database"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total URLs', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($urls); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-world"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('Total Documents', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($files); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-file-text"></i>
			</div>
		</div>
		<div class="ai-botkit-knowledge-stats-item">
			<div class="ai-botkit-stats-body">
				<span class="ai-botkit-knowledge-stats-item-label"><?php esc_html_e('WP Posts', 'ai-botkit-for-lead-generation'); ?></span>
				<span class="ai-botkit-knowledge-stats-item-value"><?php echo esc_html($wp_posts); ?></span>
			</div>
			<div class="ai-botkit-stats-icon">
				<i class="ti ti-file-text"></i>
			</div>
		</div>
	</div>

	<!-- Database Migration Section -->
	<div class="ai-botkit-migration-section">
		<div class="ai-botkit-migration-header">
			<h3><?php esc_html_e('Database Management', 'ai-botkit-for-lead-generation'); ?></h3>
			<p><?php esc_html_e('Migrate data between local database and Pinecone vector storage', 'ai-botkit-for-lead-generation'); ?></p>
		</div>

		<div class="ai-botkit-migration-status">
			<h4><?php esc_html_e('Current Status', 'ai-botkit-for-lead-generation'); ?></h4>
			<div id="migration-status-display">
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Local Database:', 'ai-botkit-for-lead-generation'); ?></span>
					<span class="ai-botkit-status-value" id="local-db-status"><?php esc_html_e('Loading...', 'ai-botkit-for-lead-generation'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Pinecone Database:', 'ai-botkit-for-lead-generation'); ?></span>
					<span class="ai-botkit-status-value" id="pinecone-db-status"><?php esc_html_e('Loading...', 'ai-botkit-for-lead-generation'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Migration Status:', 'ai-botkit-for-lead-generation'); ?></span>
					<span class="ai-botkit-status-value" id="migration-status"><?php esc_html_e('Loading...', 'ai-botkit-for-lead-generation'); ?></span>
				</div>
				<div class="ai-botkit-status-item">
					<span class="ai-botkit-status-label"><?php esc_html_e('Last Migration:', 'ai-botkit-for-lead-generation'); ?></span>
					<span class="ai-botkit-status-value" id="last-migration"><?php esc_html_e('Loading...', 'ai-botkit-for-lead-generation'); ?></span>
				</div>
			</div>
		</div>

		<div class="ai-botkit-migration-controls">
			<button type="button" id="ai-botkit-migration-btn" class="ai-botkit-btn ai-botkit-btn-secondary">
				<?php esc_html_e('Start Migration', 'ai-botkit-for-lead-generation'); ?>
			</button>
			<button type="button" id="ai-botkit-refresh-status-btn" class="ai-botkit-btn ai-botkit-btn-outline">
				<?php esc_html_e('Refresh Status', 'ai-botkit-for-lead-generation'); ?>
			</button>
			<div class="ai-botkit-clear-controls">
				<button type="button" id="ai-botkit-clear-local-btn" class="ai-botkit-btn ai-botkit-btn-danger">
					<?php esc_html_e('Clear Local DB', 'ai-botkit-for-lead-generation'); ?>
				</button>
				<button type="button" id="ai-botkit-clear-pinecone-btn" class="ai-botkit-btn ai-botkit-btn-danger">
					<?php esc_html_e('Clear Pinecone', 'ai-botkit-for-lead-generation'); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Knowledge Base Table Placeholder -->
	<div class="ai-botkit-knowledge-table" id="ai-botkit-knowledge-table">
		<!-- Tabs + Search -->
		<div class="ai-botkit-knowledge-filters">
			<div class="ai-botkit-training-tabs-list ai-botkit-tabs">
				<a class="ai-botkit-knowledge-tab <?php echo $type === 'all' ? 'active' : ''; ?>" data-tab="all" style="margin-bottom: 0;" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=knowledge&type=all&nonce=' . $nonce)); ?>"><?php esc_html_e('All Resources', 'ai-botkit-for-lead-generation'); ?></a>
				<a class="ai-botkit-knowledge-tab <?php echo $type === 'file' ? 'active' : ''; ?>" data-tab="documents" style="margin-bottom: 0;" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=knowledge&type=file&nonce=' . $nonce)); ?>"><?php esc_html_e('Documents', 'ai-botkit-for-lead-generation'); ?></a>
				<a class="ai-botkit-knowledge-tab <?php echo $type === 'url' ? 'active' : ''; ?>" data-tab="urls" style="margin-bottom: 0;" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=knowledge&type=url&nonce=' . $nonce)); ?>"><?php esc_html_e('URLs', 'ai-botkit-for-lead-generation'); ?></a>
			</div>

			<div class="ai-botkit-search-wrapper">
				<input type="text" id="ai-botkit-search-input" class="ai-botkit-search-input" placeholder="<?php esc_html_e('Search resources...', 'ai-botkit-for-lead-generation'); ?>" />
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

			<!-- Table -->
			<div class="ai-botkit-table-container">
				<table class="ai-botkit-table">
					<thead>
					<tr>
						<th><?php esc_html_e('Name', 'ai-botkit-for-lead-generation'); ?></th>
						<th><?php esc_html_e('Type', 'ai-botkit-for-lead-generation'); ?></th>
						<th><?php esc_html_e('Status', 'ai-botkit-for-lead-generation'); ?></th>
						<th><?php esc_html_e('Date Added', 'ai-botkit-for-lead-generation'); ?></th>
						<th><?php esc_html_e('Size/URL', 'ai-botkit-for-lead-generation'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-botkit-for-lead-generation'); ?></th>
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
							$document_url = '<a href="' . $document->file_path . '" target="_blank">' . esc_html__('Visit URL', 'ai-botkit-for-lead-generation') . '</a>';
						} elseif ( 'file' == $document_type ) {
							$document_url = size_format( filesize( $document->file_path ), 2 );
						}
						?>
						<tr>
							<td><?php echo strlen($document_name) > 20 ? substr($document_name, 0, 20) . '...' : esc_html($document_name); ?></td>
							<td><?php echo esc_html($document_type); ?></td>
							<td><?php
								if ( 'pending' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-warning">' . esc_html__('Pending', 'ai-botkit-for-lead-generation') . '</span>';
								} elseif ( 'processing' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-info">' . esc_html__('Processing', 'ai-botkit-for-lead-generation') . '</span>';
								} elseif ( 'completed' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-success">' . esc_html__('Completed', 'ai-botkit-for-lead-generation') . '</span>';
								} elseif ( 'failed' == $document->status ) {
									echo '<span class="ai-botkit-badge ai-botkit-badge-danger">' . esc_html__('Failed', 'ai-botkit-for-lead-generation') . '</span>';
								}
							?></td>
							<td><?php echo esc_html($document_date); ?></td>
							<td><?php echo 'file' == $document_type ? esc_html($document_url) : wp_kses_post($document_url); ?></td>
							<td>
								<button class="ai-botkit-delete-btn" data-id="<?php echo esc_attr($document->id); ?>">
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
			<a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
				array(
					'page'  => 'ai-botkit',
					'tab'   => 'knowledge',
					'type'  => sanitize_text_field( $type ),
					'paged' => max( 1, $current_page - 1 ),
					'nonce' => $nonce,
				),
				admin_url( 'admin.php' )
			) ); ?>">
				<i class="ti ti-chevron-left"></i>
			</a>

			<span id="ai-botkit-page-info">
				<?php
					echo esc_html__( 'Page', 'ai-botkit-for-lead-generation' ) . ' ' .
						esc_html( $current_page ) . ' ' .
						esc_html__( 'of', 'ai-botkit-for-lead-generation' ) . ' ' .
						esc_html( $total_pages );
				?>
			</span>

			<a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
				array(
					'page'  => 'ai-botkit',
					'tab'   => 'knowledge',
					'type'  => sanitize_text_field( $type ),
					'paged' => min( $total_pages, $current_page + 1 ),
					'nonce' => $nonce,
				),
				admin_url( 'admin.php' )
			) ); ?>">
				<i class="ti ti-chevron-right"></i>
			</a>
		</div>

		<!-- Confirm Delete Modal -->
		<div id="ai-botkit-confirm-delete-modal" class="ai-botkit-modal-overlay">
			<div class="ai-botkit-kb-modal">
				<div class="ai-botkit-modal-header">
					<h3><?php esc_html_e('Confirm Deletion', 'ai-botkit-for-lead-generation'); ?></h3>
					<p><?php esc_html_e('Are you sure you want to delete this resource? This action cannot be undone.', 'ai-botkit-for-lead-generation'); ?></p>
				</div>
				<div class="ai-botkit-modal-footer">
					<button id="ai-botkit-cancel-delete" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'ai-botkit-for-lead-generation'); ?></button>
					<button id="ai-botkit-confirm-delete" class="ai-botkit-btn ai-botkit-btn-danger"><?php esc_html_e('Delete', 'ai-botkit-for-lead-generation'); ?></button>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Add URL Modal -->
<div id="ai-botkit-add-url-modal" class="ai-botkit-modal-overlay">
  <div class="ai-botkit-training-modal">
	
	<div class="ai-botkit-training-modal-header">
	  <h3><?php esc_html_e('Add URL', 'ai-botkit-for-lead-generation'); ?></h3>
	  <button id="ai-botkit-cancel-training-url-btn"><i class="ti ti-x"></i></button>
	</div>
	<p><?php esc_html_e('Add a website URL to your Knowledge Base', 'ai-botkit-for-lead-generation'); ?></p>

	<div class="ai-botkit-modal-body" style="padding: 0; gap:0;">
		<div class="ai-botkit-form-group">
			<label for="ai-botkit-url"><?php esc_html_e('URL', 'ai-botkit-for-lead-generation'); ?></label>
			<input type="text" id="ai-botkit-url" placeholder="https://example.com/page" />
		</div>
	</div>

	<div class="ai-botkit-training-modal-footer">
	  <button class="ai-botkit-btn-outline" id="ai-botkit-cancel-url-btn"><?php esc_html_e('Cancel', 'ai-botkit-for-lead-generation'); ?></button>
	  <button class="ai-botkit-btn" id="ai-botkit-submit-url-btn"><?php esc_html_e('Add URL', 'ai-botkit-for-lead-generation'); ?></button>
	</div>

  </div>
</div>

<div id="ai-botkit-wordpress-modal" class="ai-botkit-modal-overlay">
	<div class="ai-botkit-training-modal ai-botkit-training-wp-modal">
		
		<div class="ai-botkit-training-modal-header ai-botkit-wp-header">
			<h3><?php esc_html_e('WordPress', 'ai-botkit-for-lead-generation'); ?></h3>
			<button id="ai-botkit-cancel-training-wordpress-btn"><i class="ti ti-x"></i></button>
		</div>
		<div class="ai-botkit-training-modal-header ai-botkit-wp-header-back">
			<h3><i class="ti ti-chevron-left"></i> <?php esc_html_e('All', 'ai-botkit-for-lead-generation'); ?> <span class="ai-botkit-wp-header-post-title"><?php esc_html_e('Posts', 'ai-botkit-for-lead-generation'); ?></span></h3>
		</div>
		<p class="ai-botkit-training-modal-subtext"><?php esc_html_e('Add from your WordPress data to train your chat bhot', 'ai-botkit-for-lead-generation'); ?></p>


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
						<?php echo esc_html_e('All', 'ai-botkit-for-lead-generation'); ?> <?php echo esc_html($post_type->labels->singular_name); ?>
					</label>
					<div class="ai-botkit-wp-count-container">
						<div class="ai-botkit-wp-count" style="display: none;">
							<span class="ai-botkit-wp-count-number" data-type="<?php echo esc_attr($post_type->name); ?>">0</span>
							<span class="ai-botkit-wp-count-text"><?php esc_html_e('Selected', 'ai-botkit-for-lead-generation'); ?></span>
						</div>
						<i class="ti ti-chevron-right"></i>
					</div>
					
				</div>
				<div class="ai-botkit-style-content collapsed">
					<?php
					if ( empty($posts) ) {
						?>
						<div class="ai-botkit-notice">
							<?php esc_html_e('No posts found', 'ai-botkit-for-lead-generation'); ?>
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
			<button class="ai-botkit-btn-outline" id="ai-botkit-cancel-training-wordpress-modal"><?php esc_html_e('Cancel', 'ai-botkit-for-lead-generation'); ?></button>
			<button class="ai-botkit-btn" id="ai-botkit-import-wp"><?php esc_html_e('Add Data', 'ai-botkit-for-lead-generation'); ?></button>
			<button class="ai-botkit-btn ai-botkit-wp-header-back" ><?php esc_html_e('Add Selected', 'ai-botkit-for-lead-generation'); ?></button>
		</div>
	</div>
</div>
<!-- Upload Modal -->
<div id="ai-botkit-upload-modal" class="ai-botkit-modal-overlay">
  <div class="ai-botkit-training-modal">

    <div class="ai-botkit-training-modal-header">
      <h3><?php esc_html_e('Upload Documents', 'ai-botkit-for-lead-generation'); ?></h3>
	  <button id="ai-botkit-cancel-training-document-btn"><i class="ti ti-x"></i></button>
    </div>
	<p><?php esc_html_e('Upload PDF documents to your Knowledge Base', 'ai-botkit-for-lead-generation'); ?></p>


    <div class="ai-botkit-modal-body" style="padding: 0;">
		<form id="ai-botkit-file-form">
			<div class="ai-botkit-upload-box" id="ai-botkit-document-upload-box">
				<label for="ai-botkit-submit-upload" class="ai-botkit-training-pdf-upload">
					<i class="ti ti-upload"></i>
					<?php esc_html_e('Upload a File', 'ai-botkit-for-lead-generation'); ?>
					<input
					id="ai-botkit-submit-upload"
					type="file"
					accept=".pdf"
					class="sr-only"
					/>
				</label>
				<p class="ai-botkit-help-text">
					<?php esc_html_e('Currently, you can upload PDF only. The PDF you upload will serve as the data source. Your chatbot will only train on the text contained within the PDF; images or GIFs will not be utilized.', 'ai-botkit-for-lead-generation'); ?>
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
<div id="ai-botkit-migration-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content">
        <div class="ai-botkit-modal-header">
            <h3><?php esc_html_e('Database Migration Wizard', 'ai-botkit-for-lead-generation'); ?></h3>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>
        
        <div class="ai-botkit-modal-body">
            <!-- Step 1: Migration Direction -->
            <div class="ai-botkit-migration-step" data-step="1">
                <h4><?php esc_html_e('Step 1: Choose Migration Direction', 'ai-botkit-for-lead-generation'); ?></h4>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_direction" value="to_pinecone" checked>
                        <span class="ai-botkit-radio-text">
                            <strong><?php esc_html_e('Local to Pinecone', 'ai-botkit-for-lead-generation'); ?></strong>
                            <br><?php esc_html_e('Migrate data from local database to Pinecone', 'ai-botkit-for-lead-generation'); ?>
                        </span>
                    </label>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_direction" value="to_local">
                        <span class="ai-botkit-radio-text">
                            <strong><?php esc_html_e('Pinecone to Local', 'ai-botkit-for-lead-generation'); ?></strong>
                            <br><?php esc_html_e('Migrate data from Pinecone to local database', 'ai-botkit-for-lead-generation'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Step 2: Migration Scope -->
            <div class="ai-botkit-migration-step" data-step="2" style="display: none;">
                <h4><?php esc_html_e('Step 2: Choose Migration Scope', 'ai-botkit-for-lead-generation'); ?></h4>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_scope" value="all" checked>
                        <span class="ai-botkit-radio-text">
                            <strong><?php esc_html_e('All Data', 'ai-botkit-for-lead-generation'); ?></strong>
                            <br><?php esc_html_e('Migrate all available data', 'ai-botkit-for-lead-generation'); ?>
                        </span>
                    </label>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_scope" value="by_type">
                        <span class="ai-botkit-radio-text">
                            <strong><?php esc_html_e('By Content Type', 'ai-botkit-for-lead-generation'); ?></strong>
                            <br><?php esc_html_e('Select specific content types to migrate', 'ai-botkit-for-lead-generation'); ?>
                        </span>
                    </label>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-radio-label">
                        <input type="radio" name="migration_scope" value="by_date">
                        <span class="ai-botkit-radio-text">
                            <strong><?php esc_html_e('By Date Range', 'ai-botkit-for-lead-generation'); ?></strong>
                            <br><?php esc_html_e('Migrate data from a specific date range', 'ai-botkit-for-lead-generation'); ?>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Step 3: Content Type Selection -->
            <div class="ai-botkit-migration-step" data-step="3" style="display: none;">
                <h4><?php esc_html_e('Step 3: Select Content Types', 'ai-botkit-for-lead-generation'); ?></h4>
                <div id="content-types-selection">
                    <p><?php esc_html_e('Loading content types...', 'ai-botkit-for-lead-generation'); ?></p>
                </div>
            </div>

            <!-- Step 4: Date Range Selection -->
            <div class="ai-botkit-migration-step" data-step="4" style="display: none;">
                <h4><?php esc_html_e('Step 4: Select Date Range', 'ai-botkit-for-lead-generation'); ?></h4>
                <div class="ai-botkit-form-group">
                    <label for="migration_date_start"><?php esc_html_e('Start Date', 'ai-botkit-for-lead-generation'); ?></label>
                    <input type="date" id="migration_date_start" name="migration_date_start">
                </div>
                <div class="ai-botkit-form-group">
                    <label for="migration_date_end"><?php esc_html_e('End Date', 'ai-botkit-for-lead-generation'); ?></label>
                    <input type="date" id="migration_date_end" name="migration_date_end">
                </div>
            </div>

            <!-- Step 5: Confirmation -->
            <div class="ai-botkit-migration-step" data-step="5" style="display: none;">
                <h4><?php esc_html_e('Step 5: Confirm Migration', 'ai-botkit-for-lead-generation'); ?></h4>
                <div id="migration-summary">
                    <p><?php esc_html_e('Review your migration settings:', 'ai-botkit-for-lead-generation'); ?></p>
                    <ul id="migration-summary-list"></ul>
                </div>
                <div class="ai-botkit-form-group">
                    <label class="ai-botkit-checkbox-label">
                        <input type="checkbox" id="migration_confirm" required>
                        <span class="ai-botkit-checkbox-text"><?php esc_html_e('I understand that this migration may take some time and I have backed up my data', 'ai-botkit-for-lead-generation'); ?></span>
                    </label>
                </div>
            </div>

            <!-- Progress Step -->
            <div class="ai-botkit-migration-step" data-step="progress" style="display: none;">
                <h4><?php esc_html_e('Migration in Progress', 'ai-botkit-for-lead-generation'); ?></h4>
                <div class="ai-botkit-progress-container">
                    <div class="ai-botkit-progress-bar">
                        <div class="ai-botkit-progress-fill" id="migration-progress-fill"></div>
                    </div>
                    <div class="ai-botkit-progress-text" id="migration-progress-text"><?php esc_html_e('Starting migration...', 'ai-botkit-for-lead-generation'); ?></div>
                </div>
                <div id="migration-log" class="ai-botkit-migration-log"></div>
            </div>
        </div>
        
        <div class="ai-botkit-modal-footer">
            <button type="button" id="ai-botkit-migration-prev" class="ai-botkit-btn ai-botkit-btn-outline" style="display: none;">
                <?php esc_html_e('Previous', 'ai-botkit-for-lead-generation'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-next" class="ai-botkit-btn">
                <?php esc_html_e('Next', 'ai-botkit-for-lead-generation'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-start" class="ai-botkit-btn ai-botkit-btn-primary" style="display: none;">
                <?php esc_html_e('Start Migration', 'ai-botkit-for-lead-generation'); ?>
            </button>
            <button type="button" id="ai-botkit-migration-close" class="ai-botkit-btn ai-botkit-btn-outline" style="display: none;">
                <?php esc_html_e('Close', 'ai-botkit-for-lead-generation'); ?>
            </button>
        </div>
    </div>
</div>

