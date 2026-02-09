<?php
defined('ABSPATH') || exit;

// Get current page and items per page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$items_per_page = 4;
$offset = ($current_page - 1) * $items_per_page;

$create_chatbot = isset($_GET['create']) ? true : false;

// Get chatbots
global $wpdb;
$total_chatbots = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ai_botkit_chatbots" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe
$total_pages = ceil($total_chatbots / $items_per_page);

$chatbots = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ai_botkit_chatbots 
    ORDER BY name ASC 
    LIMIT %d OFFSET %d",
    $items_per_page,
    $offset
));

// Get all public post types
$post_types = get_post_types(['public' => true], 'objects');

$nonce = wp_create_nonce('ai_botkit_chatbots');
?>
<div class="ai-botkit-dashboard-wrapper" style="display: <?php echo $create_chatbot ? 'none' : 'block'; ?>">
    <div class="ai-botkit-dashboard-container">
        <div class="ai-botkit-dashboard-header">
            <h1 class="ai-botkit-dashboard-title"><?php esc_html_e('My Chatbots', 'knowvault'); ?></h1>

            <button id="ai-botkit-create-bot-btn" class="ai-botkit-create-btn">
                <i class="ti ti-plus"></i> <?php esc_html_e('Create New Bot', 'knowvault'); ?>
            </button>
        </div>

        <div class="ai-botkit-chatbot-grid">

            <?php
            if ( empty($chatbots) ) {
                ?>
                <!-- If there are no bots -->
                <div class="ai-botkit-empty-state">
                    <p class="ai-botkit-empty-text">
                    <?php esc_html_e('You don\'t have any chatbots yet.', 'knowvault'); ?>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <!-- Else, if there are bots -->
                <div class="ai-botkit-grid-wrapper">
                    <?php foreach ($chatbots as $chatbot) {
                        $model_config = json_decode($chatbot->model_config);
                        $style = json_decode($chatbot->style);
                        $messages_template = json_decode($chatbot->messages_template);

                        // Validate and set defaults for style properties
                        if (!$style || !is_object($style)) {
                            $style = (object) array(
                                'primary_color' => '#007cba',
                                'avatar' => AI_BOTKIT_PLUGIN_URL . 'public/images/bot.png'
                            );
                        } else {
                            // Ensure required properties exist with defaults
                            if (!isset($style->primary_color)) {
                                $style->primary_color = '#007cba';
                            }
                            if (!isset($style->avatar)) {
                                $style->avatar = AI_BOTKIT_PLUGIN_URL . 'public/images/bot.png';
                            }
                        }

                        // Validate and set defaults for model_config properties
                        if (!$model_config || !is_object($model_config)) {
                            $model_config = (object) array(
                                'tone' => 'professional'
                            );
                        } else {
                            // Ensure required properties exist with defaults
                            if (!isset($model_config->tone)) {
                                $model_config->tone = 'professional';
                            }
                        }

                        $sessions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ai_botkit_conversations WHERE chatbot_id = %d", $chatbot->id));
                        $convo_ids = array_column($sessions, 'id');

                        // Sanitize and ensure all are integers
                        $convo_ids = array_map('intval', $convo_ids);
                        if( empty($convo_ids) ) {
                            $convo_ids = array(0);
                        }

                        // Create placeholders based on count
                        $placeholders = implode(', ', array_fill(0, count($convo_ids), '%d'));

                        // Run query
                        $messages = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}ai_botkit_messages WHERE conversation_id IN ($placeholders)",
                            ...$convo_ids
                            ));
                        ?>
                    <div class="ai-botkit-chatbot-card">
                        <div class="ai-botkit-chatbot-header">
                            <div class="ai-botkit-chatbot-avatar" style="background-color: <?php echo esc_attr($style->primary_color); ?>;">
                                <img src="<?php echo esc_url($style->avatar); ?>" alt="<?php echo esc_attr( sprintf( __( '%s chatbot avatar', 'knowvault' ), $chatbot->name ) ); ?>" class="ai-botkit-avatar-img" />
                            </div>
                            <div class="ai-botkit-chatbot-details">
                                <div class="ai-botkit-chatbot-badges">
                                    <h3 class="ai-botkit-chatbot-title"><?php echo esc_html($chatbot->name); ?></h3>
                                </div>
                                <div class="ai-botkit-chatbot-badges">
                                    <span class="ai-botkit-badge ai-botkit-badge-status <?php echo 1 == $chatbot->active ? 'ai-botkit-status-active' : 'ai-botkit-status-inactive'; ?>"><?php echo 1 == $chatbot->active ? esc_html__( 'Active', 'knowvault' ) : esc_html__( 'Inactive', 'knowvault' ); ?></span>
                                    <?php if( intval($chatbot->id) === intval(get_option('ai_botkit_chatbot_sitewide_enabled', 0)) ) { ?>
                                        <span class="ai-botkit-badge ai-botkit-badge-sitewide"><?php esc_html_e( 'Sitewide', 'knowvault' ); ?></span>
                                    <?php } ?>
                                    <span class="ai-botkit-badge ai-botkit-badge-outline"><?php echo esc_html( ucfirst( $model_config->tone ) ); ?></span>
                                </div>
                                <div class="ai-botkit-chatbot-content">
                                    <div class="ai-botkit-chatbot-info">
                                        <div>
                                            <p class="ai-botkit-info-label"><?php esc_html_e('Messages', 'knowvault'); ?></p>
                                            <p class="ai-botkit-info-value"><?php echo esc_html(count($messages)); ?></p>
                                        </div>
                                    </div>
                                    <div class="ai-botkit-chatbot-info">
                                        <div>
                                            <p class="ai-botkit-info-label"><?php esc_html_e('Sessions', 'knowvault'); ?></p>
                                            <p class="ai-botkit-info-value"><?php echo esc_html(count($sessions)); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ai-botkit-chatbot-footer">
                            <button class="ai-botkit-btn-outline ai-botkit-edit-bot ai-masters-show-title" data-title="<?php esc_html_e('Edit Bot', 'knowvault'); ?>" data-chatbot-id="<?php echo esc_attr($chatbot->id); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                <i class="ti ti-edit" aria-hidden="true"></i>
                            </button>
                            <a class="ai-botkit-btn-outline ai-botkit-view-chat" href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots&bot_id=' . $chatbot->id . '&nonce=' . $nonce)); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View conversations for %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                <i class="ti ti-users" aria-hidden="true"></i> <?php esc_html_e('View Conversations', 'knowvault'); ?>
                            </a>
                            <div class="ai-botkit-copy-code-wrapper">
                                <div class="ai-botkit-shortcode-wrapper" style="display: none;">
                                    <button class="ai-botkit-copy-shortcode" data-chatbot-id="<?php echo esc_attr($chatbot->id); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy shortcode for %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                        <i class="ti ti-copy" aria-hidden="true"></i> <?php esc_html_e('Copy Shortcode', 'knowvault'); ?>
                                    </button>
                                    <button class="ai-botkit-copy-widget-code" data-chatbot-id="<?php echo esc_attr($chatbot->id); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy widget code for %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                        <i class="ti ti-copy" aria-hidden="true"></i> <?php esc_html_e('Copy Widget Code', 'knowvault'); ?>
                                    </button>
                                </div>
                                <button class="ai-botkit-btn-outline ai-botkit-copy-code" data-chatbot-id="<?php echo esc_attr($chatbot->id); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy embed code for %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                    <i class="ti ti-copy" aria-hidden="true"></i> <?php esc_html_e('Copy Code', 'knowvault'); ?>
                                </button>
                            </div>
                            <button class="ai-botkit-btn-outline ai-botkit-delete-bot ai-masters-show-title" data-title="<?php esc_html_e('Delete Bot', 'knowvault'); ?>" data-chatbot-id="<?php echo esc_attr($chatbot->id); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'knowvault' ), $chatbot->name ) ); ?>">
                                <i class="ti ti-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php
            }
            ?>

            <!-- Pagination controls -->
            <?php if ( $total_pages > 1 ) { ?>
                <div class="ai-botkit-pagination" id="ai-botkit-pagination">
                    <a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
                        array(
                            'page'    => 'ai-botkit',
                            'tab'     => 'chatbots',
                            'bot_id'  => intval( $bot_id ),
                            'paged'   => max( 1, $current_page - 1 ),
                            'nonce'   => $nonce,
                        ),
                        admin_url( 'admin.php' )
                    ) ); ?>">
                        <i class="ti ti-chevron-left"></i>
                    </a>

                    <span id="ai-botkit-page-info">
                        <?php
                            echo esc_html__( 'Page', 'knowvault' ) . ' ' .
                                esc_html( $current_page ) . ' ' .
                                esc_html__( 'of', 'knowvault' ) . ' ' .
                                esc_html( $total_pages );
                        ?>
                    </span>

                    <a class="ai-botkit-btn-outline" href="<?php echo esc_url( add_query_arg(
                        array(
                            'page'    => 'ai-botkit',
                            'tab'     => 'chatbots',
                            'bot_id'  => intval( $bot_id ),
                            'paged'   => min( $total_pages, $current_page + 1 ),
                            'nonce'   => $nonce,
                        ),
                        admin_url( 'admin.php' )
                    ) ); ?>">
                        <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
    <!-- Confirm Delete Modal -->
    <div id="ai-botkit-confirm-delete-chatbot-modal" class="ai-botkit-modal-overlay">
        <div class="ai-botkit-kb-modal">
            <div class="ai-botkit-modal-header">
                <h3><?php esc_html_e('Confirm Deletion', 'knowvault'); ?></h3>
                <p><?php esc_html_e('Are you sure you want to delete this chatbot? This action cannot be undone.', 'knowvault'); ?></p>
            </div>
            <div class="ai-botkit-modal-footer">
                <button id="ai-botkit-cancel-delete-chatbot" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
                <button id="ai-botkit-confirm-delete-chatbot" data-chatbot-id="" class="ai-botkit-btn ai-botkit-btn-danger"><?php esc_html_e('Delete', 'knowvault'); ?></button>
            </div>
        </div>
    </div>
</div>
<div class="ai-botkit-wizard-container" style="display: <?php echo $create_chatbot ? 'block' : 'none'; ?>">
    <!-- Progress Bar -->
    <div class="ai-botkit-dashboard-header">
        <div class="ai-botkit-dashboard-title-wrapper">
            <button class="ai-botkit-btn-outline ai-botkit-btn-sm" id="ai-botkit-chatbot-wizard-back">
                <i class="ti ti-arrow-left"></i>
            </button>
            <h1 class="ai-botkit-chatbot-wizard-title"><?php esc_html_e('Create New Bot', 'knowvault'); ?></h1>
            <span class="ai-botkit-badge" id="ai-botkit-chatbot-wizard-status"></span>
            <span class="ai-botkit-badge ai-botkit-badge-sitewide" id="ai-botkit-chatbot-wizard-sitewide"><?php esc_html_e('Sitewide', 'knowvault'); ?></span>

        </div>
        
        <div class="ai-botkit-save-chatbot-container">
            <div class="ai-botkit-save-chatbot-status" style="display: none;"></div>
            <button type="submit" id="ai-botkit-save-btn" class="ai-botkit-btn-primary">
            <?php esc_html_e('Create Bot', 'knowvault'); ?>
            </button>
        </div>
    </div>
    <div class="ai-botkit-progress-wrapper">
        <div class="ai-botkit-progress-text">
        <span id="ai-botkit-step-indicator"><?php esc_html_e('Step', 'knowvault'); ?> 1 <?php esc_html_e('of', 'knowvault'); ?> 5</span>
        <span id="ai-botkit-completion-indicator"><?php esc_html_e('0% Complete', 'knowvault'); ?></span>
        </div>
        <div class="ai-botkit-progress-bar">
        <div id="ai-botkit-progress-fill" class="ai-botkit-progress-fill" style="width: 0%;"></div>
        </div>
    </div>
    <!-- Step Tabs -->
    <div class="ai-botkit-navigation-wrapper">
        <div class="ai-botkit-tabs">
            <div class="ai-botkit-tabs-list">
            <button class="ai-botkit-tab active" data-step="0"><?php esc_html_e('1. General', 'knowvault'); ?></button>
            <button class="ai-botkit-tab" data-step="1"><?php esc_html_e('2. Training', 'knowvault'); ?></button>
            <button class="ai-botkit-tab" data-step="2"><?php esc_html_e('3. Interface', 'knowvault'); ?></button>
            <button class="ai-botkit-tab" data-step="3"><?php esc_html_e('4. Model', 'knowvault'); ?></button>
            <button class="ai-botkit-tab" data-step="4"><?php esc_html_e('5. Styles', 'knowvault'); ?></button>
            <button class="ai-botkit-tab hidden" data-step="5"><?php esc_html_e('6. Publish', 'knowvault'); ?></button>
            <p class="ai-botkit-chatbot-hide-preview" id="ai-botkit-chatbot-hide-preview"><i class="ti ti-eye"></i><?php esc_html_e('Hide Preview', 'knowvault'); ?></p>

            </div>
        </div>
        <!-- Navigation Buttons -->
        <div class="ai-botkit-navigation-buttons">
            <button id="ai-botkit-prev-btn" class="ai-botkit-btn-outline">
            <i class="ti ti-arrow-left"></i> <?php esc_html_e('Previous', 'knowvault'); ?>
            </button>
            <button id="ai-botkit-next-btn" class="ai-botkit-btn-primary">
            <?php esc_html_e('Next Step', 'knowvault'); ?> <i class="ti ti-arrow-right"></i>
            </button>
        </div>
    </div>
    <!-- Step Content -->
     <div class="ai-botkit-card-wrapper">
        <div class="ai-botkit-card">
            <form id="ai-botkit-chatbot-form" method="post">
                <div id="ai-botkit-steps-container">
                    <div class="ai-botkit-step-content active" data-step="0">
                        <div class="ai-botkit-step-container">
                            <div>
                                <h3 class="ai-botkit-step-title"><?php esc_html_e('General', 'knowvault'); ?></h3>
                                <p class="ai-botkit-step-subtext">
                                    <?php esc_html_e('Let\'s start by setting up the basic information for your chatbot.', 'knowvault'); ?>
                                </p>
                            </div>
                            <input type="hidden" id="ai-botkit-chatbot-id" name="chatbot_id" value="">
                            <!-- Start from Template -->
                            <div class="ai-botkit-form-group" id="ai-botkit-template-selector-group">
                                <label for="ai-botkit-template-selector" class="ai-botkit-label"><?php esc_html_e('Start from Template', 'knowvault'); ?> <span class="ai-botkit-optional-label">(<?php esc_html_e('Optional', 'knowvault'); ?>)</span></label>
                                <select id="ai-botkit-template-selector" class="ai-botkit-select-input">
                                    <option value=""><?php esc_html_e('-- Select a template or start from scratch --', 'knowvault'); ?></option>
                                </select>
                                <p class="ai-botkit-help-text"><?php esc_html_e('Choose a pre-built template to quickly configure your chatbot with recommended settings.', 'knowvault'); ?></p>
                            </div>
                            <!-- Bot Name -->
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_name" class="ai-botkit-label"><?php esc_html_e('Bot Name', 'knowvault'); ?></label>
                                <input
                                    id="chatbot_name"
                                    class="ai-botkit-input"
                                    type="text"
                                    name="name"
                                    placeholder="e.g. Support Assistant"
                                />
                                <p class="ai-botkit-help-text"><?php esc_html_e('This name will be visible to your website visitors.', 'knowvault'); ?></p>
                            </div>
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_personality" class="ai-botkit-label"><?php esc_html_e('Bot Introductions', 'knowvault'); ?></label>
                                <textarea
                                    id="chatbot_personality"
                                    name="personality"
                                    class="ai-botkit-textarea"
                                    rows="3"
                                    placeholder="<?php esc_html_e('You are a helpful support assistant that can answer questions and help with tasks.', 'knowvault'); ?>"
                                ><?php esc_html_e('helpful website assistant', 'knowvault'); ?></textarea>
                                <p class="ai-botkit-help-text">
                                    <?php esc_html_e('Chatbot personality and behavior.', 'knowvault'); ?>
                                </p>
                            </div>
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_active" class="ai-botkit-label">
                                    <?php esc_html_e('Activate Chatbot', 'knowvault'); ?>
                                </label>
                                <label class="ai-botkit-switch">
                                    <input
                                        id="chatbot_active"
                                        class="ai-botkit-input"
                                        type="checkbox"
                                        value="1"
                                        checked
                                    />
                                    <span class="ai-botkit-slider"></span>
                                </label>
                                <p class="ai-botkit-help-text"><?php esc_html_e('This will determine if the chatbot is active on your website. (This syncs with Publish tab)', 'knowvault'); ?></p>
                            </div>
                            <!-- Bot Tone -->
                            <div class="ai-botkit-form-group">
                                <label class="ai-botkit-label"><?php esc_html_e('Bot Tone', 'knowvault'); ?></label>
                                <div class="ai-botkit-radio-group">
                                <label class="ai-botkit-radio-option">
                                    <input type="radio" name="tone" value="professional" checked />
                                    <span><?php esc_html_e('Professional - Formal and business-like', 'knowvault'); ?></span>
                                </label>
                                <label class="ai-botkit-radio-option">
                                    <input type="radio" name="tone" value="friendly" />
                                    <span><?php esc_html_e('Friendly - Warm and approachable', 'knowvault'); ?></span>
                                </label>
                                <label class="ai-botkit-radio-option">
                                    <input type="radio" name="tone" value="casual" />
                                    <span><?php esc_html_e('Casual - Relaxed and conversational', 'knowvault'); ?></span>
                                </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ai-botkit-step-content" data-step="1">
                        <div class="ai-botkit-style-section">
                            <div class="ai-botkit-style-header">
                                <?php esc_html_e('Import from existing knowledge base', 'knowvault'); ?>
                                <i class="ti ti-chevron-down"></i>
                            </div>
                            <div class="ai-botkit-style-content collapsed">
                                <?php
                                global $wpdb;
                                $documents = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ai_botkit_documents ORDER BY created_at DESC");
                                if ( empty($documents) ) {
                                    ?>
                                    <div class="ai-botkit-notice">
                                        <?php esc_html_e('No documents found', 'knowvault'); ?>
                                    </div>
                                    <?php
                                } else {
                                    ?>
                                    <div class="ai-botkit-training-tabs-list">
                                        <button class="ai-botkit-training-tab active" data-tab="all"></i> <?php esc_html_e('All', 'knowvault'); ?></button>
                                        <button class="ai-botkit-training-tab" data-tab="post"></i> <?php esc_html_e('WordPress', 'knowvault'); ?></button>
                                        <button class="ai-botkit-training-tab" data-tab="file"></i> <?php esc_html_e('Documents', 'knowvault'); ?></button>
                                        <button class="ai-botkit-training-tab" data-tab="url"></i> <?php esc_html_e('URLs', 'knowvault'); ?></button>
                                    </div>
                                    <input
                                        id="ai-botkit-kb-search"
                                        class="ai-botkit-input"
                                        placeholder="Search resources..."
                                    />
                                    <div class="ai-botkit-kb-content-scroll">
                                        <table class="ai-botkit-table">
                                            <thead>
                                            <tr>
                                                <th><input type="checkbox" id="ai-botkit-existing-kb-select-all" class="ai-botkit-checkbox" /></th>
                                                <th><?php esc_html_e('Name', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Type', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Status', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Size/URL', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Actions', 'knowvault'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody id="ai-botkit-existing-kb-table-body">
                                                <?php
                                                    foreach ($documents as $document) {
                                                        $document_type = $document->source_type;
                                                        $document_name = $document->title;
                                                        $document_date = $document->created_at;
                                                        if ( 'post' == $document_type ) {
                                                            $document_url = '<a href="' . get_permalink($document->source_id) . '" target="_blank">' . esc_html__('Visit URL', 'knowvault') . '</a>';
                                                        } elseif ( 'url' == $document_type ) {
                                                            $document_url = '<a href="' . $document->file_path . '" target="_blank">' . esc_html__('Visit URL', 'knowvault') . '</a>';
                                                        } elseif ( 'file' == $document_type ) {
                                                            $document_url = size_format( filesize( $document->file_path ), 2 );
                                                        }
                                                        ?>
                                                        <tr data-type="<?php echo esc_attr($document_type); ?>">
                                                            <td>
                                                                <input type="checkbox" class="ai-botkit-checkbox" data-id="<?php echo esc_attr($document->id); ?>">
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
                                                                    echo '<span class="ai-botkit-badge ai-botkit-badge-danger">' . esc_html__('Failed', 'knowvault') . '</span>';
                                                                }
                                                            ?></td>
                                                            <td><?php echo 'file' == $document_type ? esc_html($document_url) : wp_kses_post($document_url); ?></td>
                                                            <td>
                                                                <button class="ai-botkit-delete-btn" data-id="<?php echo esc_attr($document->id); ?>">
                                                                    <i class="ti ti-trash" style="color:red;"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="ai-botkit-confirm-delete-modal" class="ai-botkit-modal-overlay">
                                        <div class="ai-botkit-kb-modal">
                                            <div class="ai-botkit-modal-header">
                                                <h3><?php esc_html_e('Confirm Deletion', 'knowvault'); ?></h3>
                                                <p><?php esc_html_e('Are you sure you want to delete this resource? This action cannot be undone.', 'knowvault'); ?></p>
                                            </div>
                                            <div class="ai-botkit-modal-footer">
                                                <button id="ai-botkit-cancel-delete" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
                                                <button id="ai-botkit-confirm-delete" class="ai-botkit-btn ai-botkit-btn-danger"><?php esc_html_e('Delete', 'knowvault'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ai-botkit-kb-footer">
                                        <button class="ai-botkit-btn-primary" id="ai-botkit-add-from-kb">Add Data</button>
                                    </div>
                                    <?php
                                    }
                                    ?>
                            </div>
                        </div>
                        <div class="ai-botkit-step-container" style="margin-top: 1.5rem;">
                            <!-- Header -->
                            <div class="ai-botkit-step-header">
                                <div class="ai-botkit-step-header-left">
                                    <h3 class="ai-botkit-step-title"><?php esc_html_e('Training', 'knowvault'); ?></h3>
                                    <p class="ai-botkit-step-subtext">
                                        <?php esc_html_e('Upload documents or add website URLs to train your chatbot with specific knowledge.', 'knowvault'); ?>
                                    </p>
                                </div>
                                <div class="ai-botkit-add-data-btn">
                                    <span class="ai-botkit-btn-primary" id="ai-botkit-add-data-btn">
                                        <i class="ti ti-plus"></i><?php esc_html_e('Add Data', 'knowvault'); ?>
                                    </span>
                                    <div class="ai-botkit-add-data-items">
                                        <li class="ai-botkit-add-data-item" id="ai-botkit-add-training-document-btn">
                                            <i class="ti ti-file-export"></i>
                                            <span><?php esc_html_e('Add Document', 'knowvault'); ?></span>
                                        </li>
                                        <li class="ai-botkit-add-data-item" id="ai-botkit-add-training-url-btn">
                                            <i class="ti ti-link"></i>
                                            <span><?php esc_html_e('Add URL', 'knowvault'); ?></span>
                                        </li>
                                        <li class="ai-botkit-add-data-item" id="ai-botkit-add-training-wordpress-btn">
                                            <i class="ti ti-brand-wordpress"></i>
                                            <span><?php esc_html_e('Add from WordPress', 'knowvault'); ?></span>
                                        </li>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tabs -->
                            <div class="ai-botkit-training-data-container">

                                <input type="hidden" id="ai-botkit-imports" name="imports" value="">

                                <div class="ai-botkit-no-training-docs">
                                    <h3><?php esc_html_e('No training data added', 'knowvault'); ?></h3>
                                    <p><?php esc_html_e('Please use the "Add Data button above to upload training data. You can add documents, URL links, as well as content from WordPress pages, templates, posts, and more', 'knowvault'); ?></p>
                                </div>

                                <div class="ai-botkit-training-data ai-botkit-training-data-post">
                                    <span class="ai-botkit-table-heading"><?php esc_html_e('WordPress', 'knowvault'); ?></span>
                                    <div class="ai-botkit-kb-content-scroll">
                                        <table class="ai-botkit-table">
                                            <thead>
                                            <tr>
                                                <th><?php esc_html_e('Name', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Type', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Status', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Actions', 'knowvault'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody >
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="ai-botkit-training-data ai-botkit-training-data-url">
                                    <span class="ai-botkit-table-heading"><?php esc_html_e('URLs', 'knowvault'); ?></span>
                                    <div class="ai-botkit-kb-content-scroll">
                                        <table class="ai-botkit-table">
                                            <thead>
                                            <tr>
                                                <th><?php esc_html_e('Name', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Type', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Status', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Actions', 'knowvault'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody >
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="ai-botkit-training-data ai-botkit-training-data-file">
                                    <span class="ai-botkit-table-heading"><?php esc_html_e('Documents', 'knowvault'); ?></span>
                                    <div class="ai-botkit-kb-content-scroll">
                                        <table class="ai-botkit-table">
                                            <thead>
                                            <tr>
                                                <th><?php esc_html_e('Name', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Type', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Status', 'knowvault'); ?></th>
                                                <th><?php esc_html_e('Actions', 'knowvault'); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody >
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Add URL Modal -->
                                <div id="ai-botkit-add-training-url-modal" class="ai-botkit-modal-overlay">
                                    <div class="ai-botkit-training-modal">
                                        
                                        <div class="ai-botkit-training-modal-header">
                                            <h3><?php esc_html_e('Add URL', 'knowvault'); ?></h3>
                                            <button id="ai-botkit-cancel-training-url-btn"><i class="ti ti-x"></i></button>
                                        </div>
                                        <p><?php esc_html_e('Add a website URL to your chatbot training data', 'knowvault'); ?></p>

                                        <div class="ai-botkit-modal-body" style="padding: 0; gap:0;">
                                            <div class="ai-botkit-form-group">
                                                <label for="ai-botkit-url-input"><?php esc_html_e('URL', 'knowvault'); ?></label>
                                                <input type="text" id="ai-botkit-url-input" placeholder="https://example.com/page" />
                                            </div>
                                            <div class="ai-botkit-form-group">
                                                <label for="ai-botkit-url-title-input"><?php esc_html_e('Title (Optional)', 'knowvault'); ?></label>
                                                <input type="text" id="ai-botkit-url-title-input" placeholder="<?php esc_attr_e('Leave empty to auto-detect from page', 'knowvault'); ?>" />
                                                <small class="ai-botkit-help-text"><?php esc_html_e('If left empty, the page title will be automatically extracted from the URL.', 'knowvault'); ?></small>
                                        </div>
                                        </div>

                                        <div class="ai-botkit-training-modal-footer">
                                            <button class="ai-botkit-btn-outline" id="ai-botkit-cancel-url-training-btn"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
                                            <button class="ai-botkit-btn" id="ai-botkit-add-url">
                                                <span class="ai-botkit-btn-text"><?php esc_html_e('Add URL', 'knowvault'); ?></span>
                                                <span class="ai-botkit-btn-loading" style="display: none;">
                                                    <i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
                                                    <?php esc_html_e('Adding...', 'knowvault'); ?>
                                                </span>
                                            </button>
                                        </div>

                                    </div>
                                </div>

                                <div id="ai-botkit-add-training-document-modal" class="ai-botkit-modal-overlay">
                                    <div class="ai-botkit-training-modal">
                                        
                                        <div class="ai-botkit-training-modal-header">
                                            <h3><?php esc_html_e('Add Document', 'knowvault'); ?></h3>
                                            <button id="ai-botkit-cancel-training-document-btn"><i class="ti ti-x"></i></button>
                                        </div>
                                        <p class="ai-botkit-training-modal-subtext"><?php esc_html_e('Upload your PDF file to train your chatbot. Pro tip: add a Q&A file for top results', 'knowvault'); ?></p>


                                        <div class="ai-botkit-training-modal-body">
                                            <div class="ai-botkit-upload-box" id="ai-botkit-document-upload-box">
                                                <label for="ai-botkit-pdf-upload" class="ai-botkit-training-pdf-upload">
                                                    <i class="ti ti-upload"></i>
                                                    <?php esc_html_e('Upload a File', 'knowvault'); ?>
                                                    <input
                                                    id="ai-botkit-pdf-upload"
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
                                        </div>
                                        <!-- <button class="ai-botkit-btn" id="ai-botkit-add-training-document"><i class="ti ti-plus"></i> <?php esc_html_e('Add', 'knowvault'); ?></button> -->
                                    </div>
                                </div>

                                <div id="ai-botkit-add-training-wordpress-modal" class="ai-botkit-modal-overlay">
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
                                            <button class="ai-botkit-btn" id="ai-botkit-add-training-wordpress"><?php esc_html_e('Add Data', 'knowvault'); ?></button>
                                            <button class="ai-botkit-btn ai-botkit-wp-header-back" ><?php esc_html_e('Add Selected', 'knowvault'); ?></button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Tab Content -->
                                <div class="ai-botkit-training-tab-content" data-tab="upload" style="display: none;">
                                    <label class="ai-botkit-label"><?php esc_html_e('Upload PDF Documents', 'knowvault'); ?></label>
                                    <div class="ai-botkit-upload-box">
                                        <p><?php esc_html_e('Drag and drop your PDF files here, or click to browse', 'knowvault'); ?></p>
                                        <label for="ai-botkit-pdf-upload" class="ai-botkit-upload-btn">
                                            <?php esc_html_e('Browse Files', 'knowvault'); ?>
                                            <input
                                            id="ai-botkit-pdf-upload"
                                            type="file"
                                            accept=".pdf"
                                            class="sr-only"
                                            />
                                        </label>
                                        <p class="ai-botkit-help-text">
                                            <?php esc_html_e('Max 10 MB per file. Supported format: PDF', 'knowvault'); ?>
                                        </p>
                                    </div>

                                    <div id="ai-botkit-file-list" class="ai-botkit-file-list"></div>

                                    <label class="ai-botkit-label"><?php esc_html_e('Add Website URLs', 'knowvault'); ?></label>
                                    <p class="ai-botkit-help-text"><?php esc_html_e('Add URLs to specific pages that contain relevant info.', 'knowvault'); ?></p>
                                    <div class="ai-botkit-url-input-group">
                                        <input
                                            id="ai-botkit-url-input"
                                            class="ai-botkit-input"
                                            placeholder="https://example.com/page"
                                        />
                                    <button type="button" id="ai-botkit-add-url" class="ai-botkit-btn"><?php esc_html_e('Add', 'knowvault'); ?></button>
                                    </div>
                                    <div id="ai-botkit-url-list" class="ai-botkit-url-list"></div>
                                </div>

                                <!-- Knowledge Base Tab Content -->
                                <div class="ai-botkit-training-tab-content" data-tab="knowledge" style="display: none;">
                                    <label class="ai-botkit-label"><?php esc_html_e('Select from Knowledge Base', 'knowvault'); ?></label>

                                    <div id="ai-botkit-kb-list" class="ai-botkit-kb-list">
                                    <!-- Knowledge base items inserted here via JS -->
                                    </div>
                                </div>

                                <!-- WordPress Tab Content -->
                                <div class="ai-botkit-training-tab-content active" data-tab="wordpress" style="display: none;">
                                    <div class="ai-botkit-wp-header">
                                        <h4>Select Content Types</h4>
                                        <div class="ai-botkit-wp-controls">
                                            <button type="button" id="ai-botkit-select-all" class="ai-botkit-btn-sm">Select All</button>
                                            <button type="button" id="ai-botkit-deselect-all" class="ai-botkit-btn-sm">Deselect All</button>
                                        </div>
                                    </div>

                                    <div id="ai-botkit-wp-types" class="ai-botkit-wp-types">
                                    <?php foreach ($post_types as $post_type) {
                                        $count = wp_count_posts($post_type->name);
                                        ?>
                                        <label>
                                            <input type="checkbox" value="<?php echo esc_attr($post_type->name); ?>" />
                                            <?php echo esc_html($post_type->labels->singular_name); ?> (<?php echo esc_html(number_format_i18n($count->publish)); ?>)
                                        </label>
                                    <?php } ?>
                                    </div>

                                    <div class="ai-botkit-wp-filters">
                                        <!-- <label class="ai-botkit-label">Search</label>
                                        <input
                                            type="text"
                                            class="ai-botkit-input"
                                            placeholder="Search content..."
                                        />
                                        <div class="ai-botkit-wp-dates">
                                            <div class="ai-botkit-wp-date">
                                                <label class="ai-botkit-label">Start Date</label>
                                                <input type="date" id="ai-botkit-start-date" class="ai-botkit-input" />
                                            </div>
                                            <div class="ai-botkit-wp-date">
                                                <label class="ai-botkit-label">End Date</label>
                                                <input type="date" id="ai-botkit-end-date" class="ai-botkit-input" />
                                            </div>
                                        </div> -->
                                        <button type="button" id="ai-botkit-preview-wp" class="ai-botkit-btn w-full"><?php esc_html_e('Preview Content', 'knowvault'); ?></button>
                                    </div>

                                    <div id="ai-botkit-wp-list" class="ai-botkit-kb-list">
                                    <!-- Knowledge base items inserted here via JS -->
                                    </div>
                                    <div class="ai-botkit-notice" id="ai-botkit-kb-wp-notice"></div>
                                    <div class="ai-botkit-wp-filters">
                                        <button type="button" id="ai-botkit-import-wp" class="ai-botkit-btn w-full" style="display: none;"><?php esc_html_e('Import Content', 'knowvault'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="ai-botkit-step-content" data-step="2">
                        <div class="ai-botkit-step-container">
                            <!-- Header -->
                            <div>
                                <h3 class="ai-botkit-step-title"><?php esc_html_e('Interface', 'knowvault'); ?></h3>
                                <p class="ai-botkit-step-subtext">
                                    <?php esc_html_e('Customize how your chatbot interacts with visitors.', 'knowvault'); ?>
                                </p>
                            </div>
                            <!-- Greeting -->
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_greeting" class="ai-botkit-label"><?php esc_html_e('Initial Greeting Message', 'knowvault'); ?></label>
                                <textarea
                                    id="chatbot_greeting"
                                    name="greeting"
                                    class="ai-botkit-textarea"
                                    rows="3"
                                    placeholder="<?php esc_html_e('Hi there! How can I help you today?', 'knowvault'); ?>"
                                ><?php esc_html_e('Hi there! How can I help you today?', 'knowvault'); ?></textarea>
                                <p class="ai-botkit-help-text">
                                    <?php esc_html_e('This is the first message your chatbot will display when a user opens the chat.', 'knowvault'); ?>
                                </p>
                            </div>
                            <!-- Fallback -->
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_fallback" class="ai-botkit-label"><?php esc_html_e('Fallback Message', 'knowvault'); ?></label>
                                <textarea
                                    id="chatbot_fallback"
                                    name="fallback"
                                    class="ai-botkit-textarea"
                                    rows="3"
                                    placeholder="<?php esc_html_e("I do not have an answer for that. Can you rephrase your question?", 'knowvault'); ?>"
                                ><?php esc_html_e("I do not have an answer for that. Can you rephrase your question?", 'knowvault'); ?></textarea>
                                <p class="ai-botkit-help-text">
                                    <?php esc_html_e('This message will be displayed when the chatbot can\'t find an answer to the user\'s question.', 'knowvault'); ?>
                                </p>
                            </div>
                            <div class="ai-botkit-form-toggle">
                                <div>
                                    <label for="chatbot_feedback" class="ai-botkit-label">Enable Feedbacks</label>
                                    <p class="ai-botkit-help-text">Allow users to provide feedback on the chatbot's responses. When enabled, users can provide feedback on the chatbot's responses.</p>
                                </div>
                                <label class="ai-botkit-switch">
                                    <input type="checkbox" id="chatbot_feedback" name="enable_feedback" checked>
                                    <span class="ai-botkit-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="ai-botkit-step-content" data-step="3">
                        <div class="ai-botkit-step-container">
                            <!-- Header -->
                            <div>
                                <h3 class="ai-botkit-step-title"><?php esc_html_e('Select LLM Model', 'knowvault'); ?></h3>
                                <p class="ai-botkit-step-subtext">
                                    <?php esc_html_e('Choose the AI model that will power your chatbot.', 'knowvault'); ?>
                                </p>
                            </div>
                            <?php
                            $selected_engine = get_option('ai_botkit_engine', 'openai');
                            $all_engines = $this->get_engines();

                            $models = $all_engines[$selected_engine]['chat_models'];
                            ?>
                            <div class="ai-botkit-form-row">
                                <div class="ai-botkit-form-group">
                                    <label for="ai_botkit_engine" class="ai-botkit-select-label"><?php esc_html_e('AI Engine', 'knowvault'); ?></label>
                                    <select id="ai_botkit_engine" class="ai-botkit-select-input" name="engine">
                                        <?php foreach ($all_engines as $engine_id => $engine): ?>
                                            <?php $has_api_key = get_option('ai_botkit_'.$engine_id.'_api_key', false); ?>
                                            <option value="<?php echo esc_attr($engine_id); ?>"
                                                    <?php selected($selected_engine, $engine_id); ?>
                                                    <?php echo $has_api_key ? '' : 'disabled'; ?>>
                                                <?php echo esc_html($engine['name']); ?>
                                                <?php if (!$has_api_key): ?>
                                                    (API Key Required)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="ai-botkit-hint"><?php esc_html_e('Select the AI engine you want to use', 'knowvault'); ?></p>
                                </div>
                                <div class="ai-botkit-form-group">
                                    <label for="ai_botkit_engine" class="ai-botkit-select-label"><?php esc_html_e('Chat Model', 'knowvault'); ?></label>
                                    <select id="ai_botkit_chat_model" class="ai-botkit-select-input" name="model">
                                        <?php foreach ($models as $model_id => $model_name): ?>
                                            <option value="<?php echo esc_attr($model_id); ?>">
                                                <?php echo esc_html($model_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="ai-botkit-hint"><?php esc_html_e('Select the chat model you want to use', 'knowvault'); ?></p>
                                </div>
                            </div>

                            <!-- Model selection notes -->
                            <div class="ai-botkit-model-notes">
                                <p><strong><?php esc_html_e('Model selection considerations:', 'knowvault'); ?></strong></p>
                                <ul>
                                    <li><?php esc_html_e('More capable models typically cost more but provide better responses', 'knowvault'); ?></li>
                                    <li><?php esc_html_e('Consider your response time requirements and budget', 'knowvault'); ?></li>
                                    <li><?php esc_html_e('You can change the model later if needed', 'knowvault'); ?></li>
                                </ul>
                            </div>
                            <div class="ai-botkit-form-row">
                                <div class="ai-botkit-form-group">
                                    <label for="max_messages" class="ai-botkit-label"><?php esc_html_e('Max Messages', 'knowvault'); ?></label>
                                    <input
                                        id="max_messages"
                                        class="ai-botkit-input"
                                        type="text"
                                        name="max_messages"
                                        placeholder="e.g. 5"
                                        value="5"
                                    />
                                    <p class="ai-botkit-help-text"><?php esc_html_e('This is the number of messages from the chat history that the model can use to generate a response.', 'knowvault'); ?></p>
                                </div>
                                <div class="ai-botkit-form-group">
                                    <label for="context_length" class="ai-botkit-label"><?php esc_html_e('Max Context Chunks', 'knowvault'); ?></label>
                                    <input
                                        id="context_length"
                                        class="ai-botkit-input"
                                        type="text"
                                        name="context_length"
                                        placeholder="e.g. 3"
                                        value="3"
                                    />
                                    <p class="ai-botkit-help-text"><?php esc_html_e('This is the number of reference documents that the model can use to generate a response.', 'knowvault'); ?></p>
                                </div>
                            </div>
                            <div class="ai-botkit-form-row">
                                <div class="ai-botkit-form-group">
                                    <label for="max_tokens" class="ai-botkit-label"><?php esc_html_e('Max Tokens', 'knowvault'); ?></label>
                                    <input
                                        id="max_tokens"
                                        class="ai-botkit-input"
                                        type="text"
                                        name="max_tokens"
                                        placeholder="e.g. 1000"
                                        value="1000"
                                    />
                                    <p class="ai-botkit-help-text"><?php esc_html_e('This is the maximum number of tokens that the model can use in a single response.', 'knowvault'); ?></p>
                                </div>
                                <div class="ai-botkit-form-group">
                                    <label for="model_temperature" class="ai-botkit-label"><?php esc_html_e('Model Temperature', 'knowvault'); ?></label>
                                    <input
                                        id="model_temperature"
                                        class="ai-botkit-input"
                                        type="text"
                                        name="model_temperature"
                                        placeholder="e.g. 0.7"
                                        value="0.7"
                                        max="1"
                                        min="0"
                                        step="0.1"
                                    />
                                    <p class="ai-botkit-help-text"><?php esc_html_e('This is the temperature of the model. The higher the temperature, the more creative the model will be.', 'knowvault'); ?></p>
                                </div>
                                <div class="ai-botkit-form-group">
                                    <label for="min_chunk_relevance" class="ai-botkit-label"><?php esc_html_e('Minimum Similarity Threshold', 'knowvault'); ?></label>
                                    <input
                                        id="min_chunk_relevance"
                                        class="ai-botkit-input"
                                        type="number"
                                        name="min_chunk_relevance"
                                        placeholder="e.g. 0.0"
                                        value="0.0"
                                        max="1"
                                        min="0"
                                        step="0.1"
                                    />
                                    <p class="ai-botkit-help-text"><?php esc_html_e('Minimum similarity score for content to be considered relevant. Lower values (0.0-0.3) allow more matches, higher values (0.7-1.0) are more strict.', 'knowvault'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ai-botkit-step-content" data-step="4">
                        <div class="ai-botkit-step-container">
                            <!-- Heading -->
                            <div>
                                <h3 class="ai-botkit-step-title"><?php esc_html_e('Styles', 'knowvault'); ?></h3>
                                <p class="ai-botkit-step-subtext">
                                    <?php esc_html_e('Customize how your chatbot looks on your website.', 'knowvault'); ?>
                                </p>
                            </div>

                            <div class="ai-botkit-style-accordion">
                                <!-- Theme Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Theme Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <!-- Header Background Color -->
                                        <div class="ai-botkit-form-group">
                                            <label class="ai-botkit-label"><?php esc_html_e('Bot Avatar', 'knowvault'); ?></label>
                                            <div class="ai-botkit-bot-theme">
                                                <div class="ai-botkit-bot-theme-item active" data-theme="theme-1">
                                                    <span class="check">
                                                        <i class="ti ti-check"></i>
                                                    </span>
                                                    <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/theme-1.png'); ?>" alt="Bot Avatar" />
                                                </div>
                                                <div class="ai-botkit-bot-theme-item" data-theme="theme-2" data-image="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/chatbot-bg.svg'); ?>">
                                                    <span class="check">
                                                        <i class="ti ti-check"></i>
                                                    </span>
                                                    <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/theme-2.png'); ?>" alt="Bot Avatar" />
                                                </div>
                                                <div class="ai-botkit-bot-theme-item" data-theme="theme-3">
                                                    <span class="check">
                                                        <i class="ti ti-check"></i>
                                                    </span>
                                                    <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/theme-3.png'); ?>" alt="Bot Avatar" />
                                                </div>
                                                <div class="ai-botkit-bot-theme-item" data-theme="theme-4" data-image="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/chatbot-bg.svg'); ?>">
                                                    <span class="check">
                                                        <i class="ti ti-check"></i>
                                                    </span>
                                                    <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/theme-4.png'); ?>" alt="Bot Avatar" />
                                                </div>
                                                    <input type="hidden" id="chatbot_theme" name="chatbot_theme" value="theme-1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- General Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('General Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <!-- Bot Avatar -->
                                        <div class="ai-botkit-form-group">
                                            <label class="ai-botkit-label"><?php esc_html_e('Bot Avatar', 'knowvault'); ?></label>

                                            <div id="ai-botkit-avatar-preview" class="ai-botkit-avatar-upload">
                                                <div class="ai-botkit-bot-avatar-icon active" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-avatar-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-2.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-2.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-avatar-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-3.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-3.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-avatar-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-4.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-4.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <label for="ai-botkit-avatar-input" class="ai-botkit-avatar-label" id="ai-botkit-avatar-label">
                                                    <span class="ai-botkit-upload-icon"><i class="ti ti-upload"></i><span class="ai-botkit-upload-text"><?php esc_html_e('Upload', 'knowvault'); ?></span></span>
                                                    <input id="ai-botkit-avatar-input" type="file" accept="image/*" class="sr-only ai-botkit-upload-bot-icon" data-type="avatar" />
                                                </label>
                                                <div class="ai-botkit-bot-avatar-icon ai-botkit-loading hidden" id="ai-botkit-loading-avatar">
                                                    <i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
                                                </div>
                                                <div class="ai-botkit-bot-avatar-icon ai-botkit-avatar-icon-preview hidden" data-icon="">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <button id="ai-botkit-remove-avatar" class="ai-botkit-btn-remove hidden ai-botkit-remove-bot-icon" data-type="avatar">
                                                    <i class="ti ti-trash"></i> <?php esc_html_e('Remove', 'knowvault'); ?>
                                                </button>
                                                <input type="hidden" id="ai-botkit-avatar-value" name="chatbot_avatar" value="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.svg'); ?>">
                                            </div>
                                        </div>

                                        <!-- Widget icon -->
                                        <div class="ai-botkit-form-group">
                                            <label class="ai-botkit-label"><?php esc_html_e('Widget Icon', 'knowvault'); ?></label>

                                            <div id="ai-botkit-widget-preview" class="ai-botkit-avatar-upload">
                                                <div class="ai-botkit-bot-widget-icon active" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-widget-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-2.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-2.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-widget-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-3.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-3.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-widget-icon" data-icon="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-4.svg'); ?>">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-4.svg'); ?>" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <label for="ai-botkit-widget-input" class="ai-botkit-avatar-label" id="ai-botkit-widget-label">
                                                    <span class="ai-botkit-upload-icon"><i class="ti ti-upload"></i><span class="ai-botkit-upload-text"><?php esc_html_e('Upload', 'knowvault'); ?></span></span>
                                                    <input id="ai-botkit-widget-input" type="file" accept="image/*" class="sr-only ai-botkit-upload-bot-icon" data-type="widget" />
                                                </label>
                                                <div class="ai-botkit-bot-widget-icon ai-botkit-widget-icon-preview hidden" data-icon="">
                                                    <div class="ai-botkit-bot-avatar-icon-image">
                                                        <img src="" alt="Bot Avatar" />
                                                    </div>
                                                </div>
                                                <div class="ai-botkit-bot-avatar-icon ai-botkit-loading hidden" id="ai-botkit-loading-widget">
                                                    <i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
                                                </div>
                                                <button id="ai-botkit-remove-widget" class="ai-botkit-btn-remove hidden ai-botkit-remove-bot-icon" data-type="widget">
                                                    <i class="ti ti-trash"></i> <?php esc_html_e('Remove', 'knowvault'); ?>
                                                </button>
                                                <input type="hidden" id="ai-botkit-widget-value" name="chatbot_widget" value="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.svg'); ?>">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="ai-botkit-label"><?php esc_html_e('Chat Color', 'ai-botkit'); ?></label>
                                            <div class="ai-botkit-color-swatches">
                                                <!-- Predefined color buttons -->
                                                <div class="ai-botkit-color-circle" data-color="#1E3A8A" style="background-color:#1E3A8A"></div>
                                                <div class="ai-botkit-color-circle" data-color="#6917A7" style="background-color:#6917A7"></div>
                                                <div class="ai-botkit-color-circle" data-color="#2040A7" style="background-color:#2040A7"></div>
                                                <div class="ai-botkit-color-circle" data-color="#BF2873" style="background-color:#BF2873"></div>
                                                <div class="ai-botkit-color-circle" data-color="#EA3323" style="background-color:#EA3323"></div>
                                                <div class="ai-botkit-color-circle" data-color="#EE7C30" style="background-color:#EE7C30"></div>
                                                <div class="ai-botkit-color-preview">
                                                    <label class="custom-picker" for="ai-botkit-color-picker">
                                                        <div class="ai-botkit-color-circle" id="ai-botkit-color-preview" ><span class="ai-botkit-color-picker-icon">+</span></div>
                                                        <input type="color" id="ai-botkit-color-picker" class="sr-only" />
                                                    </label>
                                                    <span id="ai-botkit-color-picker-value">#FFFFFF</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="primary_color" name="chatbot_primary_color" value="#1E3A8A">
                                        </div>

                                        <div class="ai-botkit-form-group">
                                            <label for="enable_gradient" class="ai-botkit-label">
                                                <input
                                                    id="enable_gradient"
                                                    class="ai-botkit-input"
                                                    type="checkbox"
                                                    name="enable_gradient"
                                                    value="1"
                                                />
                                                <?php esc_html_e('Add gradient to primary color', 'knowvault'); ?>
                                            </label>
                                        </div>
                                        <div class="ai-botkit-form-row-color gradient-color-container">
                                            <div class="ai-botkit-form-group">
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#21C58B"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#21C58B" 
                                                            data-target="#gradient_color_1" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#21C58B</span>
                                                </div>
                                            </div>
                                            <div class="ai-botkit-form-group">
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#038E5D"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#038E5D" 
                                                            data-target="#gradient_color_2" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#038E5D</span>
                                                </div>
                                            </div>

                                            <input type="hidden" id="gradient_color_1" name="chatbot_gradient_color_1" value="#21C58B" data-target=".ai-botkit-chat-bubble" data-key="background-gradient" >
                                            <input type="hidden" id="gradient_color_2" name="chatbot_gradient_color_2" value="#038E5D" data-target=".ai-botkit-chat-bubble" data-key="background-gradient">
                                        </div>

                                        <!-- Font Family -->
                                         <div class="ai-botkit-form-row">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Font Family', 'knowvault'); ?></label>
                                                <select class="ai-botkit-select-input ai-botkit-style-input" id="font_family" name="chatbot_font_family" data-target=".ai-botkit-chat-msg p" data-key="font-family">
                                                    <option value="Inter">Inter</option>
                                                    <option value="Roboto">Roboto</option>
                                                    <option value="Open Sans">Open Sans</option>
                                                    <option value="Lato">Lato</option>
                                                </select>
                                            </div>

                                            <!-- Font Size -->
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Font Size', 'knowvault'); ?></label>
                                                <div class="ai-botkit-size-input">
                                                    <input type="number" id="font_size" name="chatbot_font_size" value="14" min="12" max="20" class="ai-botkit-style-input" data-target=".ai-botkit-chat-msg p" data-key="font-size">
                                                    <span>px</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Header Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Header Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                    <div class="ai-botkit-form-row-color">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Background Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#FFFFFF"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#FFFFFF" 
                                                            data-target="#header_bg_color" />
                                                    </label>
                                                        <span class="ai-botkit-color-picker-value">#FFFFFF</span>
                                                </div>
                                            </div>
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Text Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#333333"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#333333" 
                                                            data-target="#header_font_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#333333</span>
                                                </div>
                                            </div>
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Icon Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#888888"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#888888" 
                                                            data-target="#header_icon_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#888888</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="header_bg_color" name="chatbot_header_bg_color" value="#FFFFFF" data-target=".ai-botkit-chat-header" data-key="background-color">
                                            <input type="hidden" id="header_font_color" name="chatbot_header_font_color" value="#333333" data-target=".ai-botkit-chat-title" data-key="color">
                                            <input type="hidden" id="header_icon_color" name="chatbot_header_icon_color" value="#888888" data-target=".ai-botkit-chat-header-btn" data-key="color">
                                        </div>
                                    </div>
                                </div>

                                <!-- Popup ChatBox Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Popup ChatBox Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <!-- Width -->
                                        <div class="ai-botkit-form-row">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Width', 'knowvault'); ?></label>
                                                <div class="ai-botkit-size-input">
                                                    <input type="number" id="chat_width" name="chatbot_width" value="424" min="300" max="500" class="ai-botkit-style-input" data-target=".ai-botkit-chat-widget" data-key="width">
                                                    <span>px</span>
                                                </div>
                                            </div>

                                            <!-- Max Height -->
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Max Height', 'knowvault'); ?></label>
                                                <div class="ai-botkit-size-input">
                                                    <input type="number" id="chat_max_height" name="chatbot_max_height" value="700" min="400" max="800" class="ai-botkit-style-input" data-target=".ai-botkit-chat-widget" data-key="max-height">
                                                    <span>px</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chat Window Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Chat Window Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <!-- Background Image -->
                                        <label class="ai-botkit-label"><?php esc_html_e('Background Image', 'knowvault'); ?></label>
                                        <div class="ai-botkit-form-group ai-botkit-background-image-container">
                                            <div class="ai-botkit-background-image-preview">
                                                <img src="" alt="Background Image" />
                                            </div>
                                            <label class="ai-botkit-background-image-label" for="chat_bg_image_placeholder"><i class="ti ti-photo-plus"></i><?php esc_html_e('Replace Image', 'knowvault'); ?></label>
                                            <input type="file" id="chat_bg_image_placeholder" class="sr-only">
                                            <div class="ai-botkit-bot-avatar-icon ai-botkit-loading hidden" id="ai-botkit-loading-image">
                                                <i class="ti ti-loader-2 ai-botkit-loading-icon"></i>
                                            </div>
                                            <span class="ai-botkit-background-image-remove" id="ai-botkit-remove-image"><i class="ti ti-trash"></i><?php esc_html_e('Remove Image', 'knowvault'); ?></span>
                                        </div>
                                        
                                        <!-- Background Color -->
                                        <div class="ai-botkit-form-group">
                                            <label class="ai-botkit-label"><?php esc_html_e('Background Color', 'knowvault'); ?></label>
                                            <div class="ai-botkit-gradient-color-preview">
                                                <label class="custom-picker">
                                                    <div class="ai-botkit-color-circle" style="background-color:#FFFFFF"></div>
                                                    <input type="color" class="ai-botkit-color-picker sr-only" value="#FFFFFF" 
                                                        data-target="#chat_bg_color" />
                                                </label>
                                                <span class="ai-botkit-color-picker-value">#FFFFFF</span>
                                            </div>
                                        </div>
                                        <input type="hidden" id="chat_bg_color" name="chatbot_bg_color" value="#FFFFFF" data-target=".ai-botkit-chat-body" data-key="background-color">

                                        <!-- DUPLICATE REMOVED: Header color fields are already defined above in the Header section (lines 1253-1255) -->

                                        <label class="ai-botkit-label"><?php esc_html_e('AI Message', 'knowvault'); ?></label>
                                        <div class="ai-botkit-form-row-color border">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('AI Message Background Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#F5F5F5"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#F5F5F5" 
                                                            data-target="#ai_msg_bg_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#F5F5F5</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="ai_msg_bg_color" name="chatbot_ai_msg_bg_color" value="#F5F5F5" data-target=".ai-botkit-chat-msg.bot-msg" data-key="background-color">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('AI Message Font Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#333333"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#333333" 
                                                            data-target="#ai_msg_font_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#333333</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="ai_msg_font_color" name="chatbot_ai_msg_font_color" value="#333333" data-target=".ai-botkit-chat-msg.bot-msg" data-key="color">
                                        </div>
                                        
                                        <label class="ai-botkit-label"><?php esc_html_e('User Message', 'knowvault'); ?></label>
                                        <div class="ai-botkit-form-row-color border">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('User Message Background Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#1E3A8A"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#1E3A8A" 
                                                            data-target="#user_msg_bg_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#1E3A8A</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="user_msg_bg_color" name="chatbot_user_msg_bg_color" value="#1E3A8A" data-target=".ai-botkit-chat-msg.user-msg" data-key="background-color">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('User Message Font Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#FFFFFF"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#FFFFFF" 
                                                            data-target="#user_msg_font_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#FFFFFF</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="user_msg_font_color" name="chatbot_user_msg_font_color" value="#FFFFFF" data-target=".ai-botkit-chat-msg.user-msg" data-key="color">
                                        </div>

                                        <label class="ai-botkit-label"><?php esc_html_e('Initiate Message', 'knowvault'); ?></label>
                                        <div class="ai-botkit-form-row-color border">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Message Background Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#FFFFFF"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#FFFFFF" 
                                                            data-target="#initiate_msg_bg_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#FFFFFF</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="initiate_msg_bg_color" name="chatbot_initiate_msg_bg_color" value="#FFFFFF" data-target=".ai-botkit-chat-msg.initiate-msg" data-key="background-color">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Message Border', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#E7E7E7"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#E7E7E7" 
                                                            data-target="#initiate_msg_border_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#E7E7E7</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="initiate_msg_border_color" name="chatbot_initiate_msg_border_color" value="#E7E7E7" data-target=".ai-botkit-chat-msg.initiate-msg" data-key="border-color">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Message Font Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#283B3C"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#283B3C" 
                                                            data-target="#initiate_msg_font_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#283B3C</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="initiate_msg_font_color" name="chatbot_initiate_msg_font_color" value="#283B3C" data-target=".ai-botkit-chat-msg.initiate-msg" data-key="color">
                                        </div>
                                    </div>
                                </div>

                                <!-- Chat Widget Bubble Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Chat Widget Bubble Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <div class="ai-botkit-form-row">
                                            <!-- Bubble Height -->
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Height', 'knowvault'); ?></label>
                                                <div class="ai-botkit-size-input">
                                                    <input type="number" id="bubble_height" name="chatbot_bubble_height" value="55" min="40" max="80" class="ai-botkit-style-input" data-target=".ai-botkit-chat-bubble" data-key="height">
                                                    <span>px</span>
                                                </div>
                                            </div>

                                            <!-- Bubble Width -->
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Width', 'knowvault'); ?></label>
                                                <div class="ai-botkit-size-input">
                                                    <input type="number" id="bubble_width" name="chatbot_bubble_width" value="55" min="40" max="80" class="ai-botkit-style-input" data-target=".ai-botkit-chat-bubble" data-key="width">
                                                    <span>px</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <!-- Recommendation Settings -->
                                <div class="ai-botkit-style-section">
                                    <div class="ai-botkit-style-header">
                                        <?php esc_html_e('Recommendation Settings', 'knowvault'); ?>
                                        <i class="ti ti-chevron-down"></i>
                                    </div>
                                    <div class="ai-botkit-style-content collapsed">
                                        <div class="ai-botkit-form-row-color border">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Title Color', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#555555"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#555555" 
                                                            data-target="#suggestion_title_color" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#555555</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="suggestion_title_color" name="chatbot_suggestion_title_color" value="#555555">
                                            <div class="ai-botkit-form-group border-right">
                                                <label class="ai-botkit-label"><?php esc_html_e('Card Background', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#FFFFFF"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#FFFFFF" 
                                                            data-target="#suggestion_card_bg" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#FFFFFF</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="suggestion_card_bg" name="chatbot_suggestion_card_bg" value="#FFFFFF">
                                            <div class="ai-botkit-form-group">
                                                <label class="ai-botkit-label"><?php esc_html_e('Card Border', 'knowvault'); ?></label>
                                                <div class="ai-botkit-gradient-color-preview">
                                                    <label class="custom-picker">
                                                        <div class="ai-botkit-color-circle" style="background-color:#E7E7E7"></div>
                                                        <input type="color" class="ai-botkit-color-picker sr-only" value="#E7E7E7" 
                                                            data-target="#suggestion_card_border" />
                                                    </label>
                                                    <span class="ai-botkit-color-picker-value">#E7E7E7</span>
                                                </div>
                                            </div>
                                            <input type="hidden" id="suggestion_card_border" name="chatbot_suggestion_card_border" value="#E7E7E7">
                                        </div>
                                    </div>
                                </div>

                            <!-- Position (keeping the existing position selector) -->
                            <div class="ai-botkit-form-group">
                                <label class="ai-botkit-label"><?php esc_html_e('Position on Screen(Only for widget)', 'knowvault'); ?></label>
                                <div class="ai-botkit-appearance-radio-group">
                                    <label><input type="radio" name="location" value="bottom-left" /> <?php esc_html_e('Bottom Left', 'knowvault'); ?></label>
                                    <label><input type="radio" name="location" value="bottom-right" checked /> <?php esc_html_e('Bottom Right', 'knowvault'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ai-botkit-step-content" data-step="5">
                        <div class="ai-botkit-step-container">
                            <!-- Heading -->
                            <div>
                                <h3 class="ai-botkit-step-title"><?php esc_html_e('Publish', 'knowvault'); ?></h3>
                                <p class="ai-botkit-step-subtext">
                                    <?php esc_html_e('Customize how your chatbot looks on your website..', 'knowvault'); ?>
                                </p>
                            </div>
                            <input type="hidden" id="saved_chatbot_id" name="saved_chatbot_id" value="1">
                            <div class="ai-botkit-form-group">
                                <label for="chatbot_active_publish" class="ai-botkit-label">
                                    <?php esc_html_e('Activate Chatbot', 'knowvault'); ?>
                                </label>
                                <label class="ai-botkit-switch">
                                    <input
                                        id="chatbot_active_publish"
                                        name="active"
                                        class="ai-botkit-input"
                                        type="checkbox"
                                        value="1"
                                        checked
                                    />
                                    <span class="ai-botkit-slider"></span>
                                </label>
                                <p class="ai-botkit-help-text"><?php esc_html_e('This will determine if the chatbot is active on your website.', 'knowvault'); ?></p>
                            </div>
                            <div class="ai-botkit-form-group ai-bot-kit-show-if-publish">
                                <label for="chatbot_active_sitewide" class="ai-botkit-label">
                                    <?php esc_html_e('Site wide chatbot', 'knowvault'); ?>
                                </label>
                                <label class="ai-botkit-switch">
                                    <input
                                        id="chatbot_active_sitewide"
                                        class="ai-botkit-input"
                                        type="checkbox"
                                        value="1"
                                    />
                                    <span class="ai-botkit-slider"></span>
                                </label>
                                <p class="ai-botkit-help-text"><?php esc_html_e('It will enable the chatbot into your whole website. No need to copy paste the code.', 'knowvault'); ?></p>
                            </div>
                            <div class="ai-botkit-form-group ai-bot-kit-show-if-publish">
                                <label for="chatbot_active_widget" class="ai-botkit-label">
                                    <?php esc_html_e('Widget code', 'knowvault'); ?>
                                </label>
                                <div class="ai-botkit-widget-code-container">
                                    <input
                                        id="chatbot_active_widget"
                                        type="text"
                                        value="[ai-botkit-widget id='1']"
                                        readonly
                                    />
                                    <button class="ai-botkit-btn-primary ai-botkit-copy-shortcode-btn ai-botkit-copy-widget-code" id="widget-code-btn" data-chatbot-id="1"><i class="ti ti-copy"></i> <?php esc_html_e('Copy', 'knowvault'); ?></button>
                                </div>
                            </div>
                            <div class="ai-botkit-form-group ai-bot-kit-show-if-publish">
                                <label for="chatbot_active_shortcode" class="ai-botkit-label">
                                    <?php esc_html_e('Shortcode', 'knowvault'); ?>
                                </label>
                                <div class="ai-botkit-widget-code-container">
                                    <input
                                        id="chatbot_active_shortcode"
                                        type="text"
                                        value="[ai-botkit-chat id='1']"
                                        readonly
                                    />
                                    <button class="ai-botkit-btn-primary ai-botkit-copy-shortcode-btn ai-botkit-copy-shortcode" id="widget-code-btn" data-chatbot-id="1"><i class="ti ti-copy"></i> <?php esc_html_e('Copy', 'knowvault'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
         <!-- preview content -->
        <div class="ai-botkit-chat-page">
            <div class="ai-botkit-chat-page-title"><?php echo esc_html_e('Preview', 'knowvault'); ?></div>
            <div class="ai-botkit-chat-widget" id="ai-botkit-chat-widget">
                <div class="ai-botkit-chat-header">
                    <div class="ai-botkit-chat-header-left">
                        <div class="ai-botkit-chat-avatar">
                            <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/bot-1.svg'); ?>" alt="Bot Avatar" />
                        </div>
                        <p class="ai-botkit-chat-title" id="ai-botkit-bot-name"><?php esc_html_e('AI Assistant', 'knowvault'); ?></p>
                    </div>
                    <div class="ai-botkit-chat-header-btn-container">
                        <button id="ai-botkit-reset-chat" class="ai-botkit-chat-header-btn"><i class="ti ti-refresh"></i></button>
                        <button id="ai-botkit-close-chat" class="ai-botkit-chat-header-btn"><i class="ti ti-x"></i></button>
                    </div>
                </div>

                <div class="ai-botkit-chat-body" id="ai-botkit-chat-body" data-target="chatbot_body">
                    <div class="ai-botkit-chat-msg bot-msg" data-target="chatbot_ai_msg">
                        <p><?php esc_html_e('Hello! How can I help you today?', 'knowvault'); ?></p>
                    </div>
                    <div class="ai-botkit-chat-msg user-msg" data-target="chatbot_user_msg">
                        <p><?php esc_html_e('I want to know about the product', 'knowvault'); ?></p>
                    </div>
                </div>

                <div id="ai-botkit-chat-form" class="ai-botkit-chat-form">
                    <input
                    type="text"
                    id="ai-botkit-message-input"
                    placeholder="Type your message..."
                    />
                    <button><i class="ti ti-send"></i></button>
                </div>
            </div>
            <!-- Bubble -->
            <div class="ai-botkit-chat-bubble bottom-right" id="ai-botkit-chat-bubble" data-target="chatbot_bubble">
                <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . '/public/images/widget-1.svg'); ?>" alt="Bot Avatar" />
            </div>
        </div>
    </div>
</div>
<div id="ai-botkit-saved-chatbot-modal" class="ai-botkit-modal-overlay">
    <div class="ai-botkit-kb-modal">
        <div class="ai-botkit-modal-header">
            <h3 class="success-message"><?php esc_html_e('Chatbot Saved Successfully', 'knowvault'); ?></h3>
        </div>
        <div class="ai-botkit-modal-content">
            
            <div class="ai-botkit-form-toggle">
                <div>
                    <label for="enable_chatbot_sitewide" class="ai-botkit-label"><?php esc_html_e('Enable Chatbot Sitewide', 'knowvault'); ?></label>
                    <p class="ai-botkit-help-text"><?php esc_html_e('Enable the chatbot to be displayed on all pages and posts.', 'knowvault'); ?></p>
                </div>
                <label class="ai-botkit-switch">
                    <input type="checkbox" id="enable_chatbot_sitewide" name="enable_chatbot_sitewide">
                    <span class="ai-botkit-slider"></span>
                </label>
            </div>
            <p><?php esc_html_e('Copy the shortcode and paste it in page or post to display the chatbot.', 'knowvault'); ?></p>
            <div class="ai-botkit-shortcode-container">
                <pre class="widget-code-text">[ai-botkit-widget id='1']</pre>
                <button class="ai-botkit-btn-outline ai-botkit-copy-shortcode-btn ai-botkit-copy-widget-code" id="widget-code-btn" data-chatbot-id="1"><i class="ti ti-copy"></i> <?php esc_html_e('Copy Widget Code', 'knowvault'); ?></button>
            </div>
            <div class="ai-botkit-shortcode-container">
                <pre class="shortcode-text">[ai-botkit-chat id='1']</pre>
                <button class="ai-botkit-btn-outline ai-botkit-copy-shortcode-btn ai-botkit-copy-shortcode" id="shortcode-btn" data-chatbot-id="1"><i class="ti ti-copy"></i> <?php esc_html_e('Copy Shortcode', 'knowvault'); ?></button>
            </div>
        </div>
        <div class="ai-botkit-modal-footer">
            <button id="ai-botkit-close-saved-chatbot-modal" class="ai-botkit-btn-outline"><?php esc_html_e('Close', 'knowvault'); ?></button>
        </div>
    </div>
</div>
