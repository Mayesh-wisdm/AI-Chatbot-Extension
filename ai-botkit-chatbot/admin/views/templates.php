<?php
/**
 * Templates Admin Page View
 *
 * Renders the template management interface including list view,
 * template editor, and import/export functionality.
 *
 * @package AI_BotKit\Admin
 * @since   2.0.0
 *
 * Implements: FR-231 (Admin Template List View)
 * Implements: FR-232 (Template Builder/Editor)
 * Implements: FR-239 (Template Import/Export)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verify admin access.
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have permission to access this page.', 'knowvault' ) );
}

// Get chatbots for apply template dropdown.
global $wpdb;
$chatbots = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}ai_botkit_chatbots ORDER BY name ASC",
    ARRAY_A
);

// Get categories for filter.
$categories = \AI_BotKit\Features\Template_Manager::get_categories();
?>

<div class="wrap ai-botkit-wrap">
    <h1 class="wp-heading-inline">
        <i class="ti ti-template"></i>
        <?php esc_html_e( 'Conversation Templates', 'knowvault' ); ?>
    </h1>

    <a href="#" class="page-title-action ai-botkit-add-template-btn">
        <i class="ti ti-plus"></i>
        <?php esc_html_e( 'Add New Template', 'knowvault' ); ?>
    </a>

    <a href="#" class="page-title-action ai-botkit-import-template-btn">
        <i class="ti ti-upload"></i>
        <?php esc_html_e( 'Import Template', 'knowvault' ); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="ai-botkit-templates-filters">
        <div class="ai-botkit-filter-group">
            <label for="ai-botkit-filter-category"><?php esc_html_e( 'Category:', 'knowvault' ); ?></label>
            <select id="ai-botkit-filter-category" class="ai-botkit-filter-select">
                <option value=""><?php esc_html_e( 'All Categories', 'knowvault' ); ?></option>
                <?php foreach ( $categories as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ai-botkit-filter-group">
            <label for="ai-botkit-filter-type"><?php esc_html_e( 'Type:', 'knowvault' ); ?></label>
            <select id="ai-botkit-filter-type" class="ai-botkit-filter-select">
                <option value=""><?php esc_html_e( 'All', 'knowvault' ); ?></option>
                <option value="system"><?php esc_html_e( 'System', 'knowvault' ); ?></option>
                <option value="custom"><?php esc_html_e( 'Custom', 'knowvault' ); ?></option>
            </select>
        </div>

        <div class="ai-botkit-filter-group">
            <label for="ai-botkit-sort-by"><?php esc_html_e( 'Sort by:', 'knowvault' ); ?></label>
            <select id="ai-botkit-sort-by" class="ai-botkit-filter-select">
                <option value="name"><?php esc_html_e( 'Name', 'knowvault' ); ?></option>
                <option value="usage_count"><?php esc_html_e( 'Usage', 'knowvault' ); ?></option>
                <option value="created_at"><?php esc_html_e( 'Date Created', 'knowvault' ); ?></option>
            </select>
        </div>
    </div>

    <!-- Template Grid -->
    <div class="ai-botkit-templates-grid" id="ai-botkit-templates-grid">
        <div class="ai-botkit-loading">
            <span class="spinner is-active"></span>
            <?php esc_html_e( 'Loading templates...', 'knowvault' ); ?>
        </div>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="ai-botkit-template-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content ai-botkit-modal-large">
        <div class="ai-botkit-modal-header">
            <h2 id="ai-botkit-modal-title"><?php esc_html_e( 'New Template', 'knowvault' ); ?></h2>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>

        <div class="ai-botkit-modal-body">
            <form id="ai-botkit-template-form">
                <input type="hidden" id="template-id" name="template_id" value="0">

                <!-- Basic Info Tab -->
                <div class="ai-botkit-template-tabs">
                    <button type="button" class="ai-botkit-tab-btn active" data-tab="basic">
                        <?php esc_html_e( 'Basic Info', 'knowvault' ); ?>
                    </button>
                    <button type="button" class="ai-botkit-tab-btn" data-tab="messages">
                        <?php esc_html_e( 'Messages', 'knowvault' ); ?>
                    </button>
                    <button type="button" class="ai-botkit-tab-btn" data-tab="style">
                        <?php esc_html_e( 'Style', 'knowvault' ); ?>
                    </button>
                    <button type="button" class="ai-botkit-tab-btn" data-tab="model">
                        <?php esc_html_e( 'Model Settings', 'knowvault' ); ?>
                    </button>
                    <button type="button" class="ai-botkit-tab-btn" data-tab="starters">
                        <?php esc_html_e( 'Conversation Starters', 'knowvault' ); ?>
                    </button>
                </div>

                <!-- Basic Info Panel -->
                <div class="ai-botkit-tab-panel active" data-panel="basic">
                    <div class="ai-botkit-form-row">
                        <label for="template-name"><?php esc_html_e( 'Template Name', 'knowvault' ); ?> <span class="required">*</span></label>
                        <input type="text" id="template-name" name="name" required maxlength="255" placeholder="<?php esc_attr_e( 'My Custom Template', 'knowvault' ); ?>">
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-description"><?php esc_html_e( 'Description', 'knowvault' ); ?></label>
                        <textarea id="template-description" name="description" rows="3" placeholder="<?php esc_attr_e( 'Describe what this template is for...', 'knowvault' ); ?>"></textarea>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-category"><?php esc_html_e( 'Category', 'knowvault' ); ?></label>
                        <select id="template-category" name="category">
                            <?php foreach ( $categories as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label>
                            <input type="checkbox" id="template-active" name="is_active" value="1" checked>
                            <?php esc_html_e( 'Active', 'knowvault' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Inactive templates will not appear in the template selection list.', 'knowvault' ); ?></p>
                    </div>
                </div>

                <!-- Messages Panel -->
                <div class="ai-botkit-tab-panel" data-panel="messages">
                    <div class="ai-botkit-form-row">
                        <label for="template-personality"><?php esc_html_e( 'System Prompt / Personality', 'knowvault' ); ?></label>
                        <textarea id="template-personality" name="messages_template[personality]" rows="5" placeholder="<?php esc_attr_e( 'You are a helpful assistant...', 'knowvault' ); ?>"></textarea>
                        <p class="description"><?php esc_html_e( 'Use {{site_name}} and {{user_name}} as placeholders.', 'knowvault' ); ?></p>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-greeting"><?php esc_html_e( 'Greeting Message', 'knowvault' ); ?></label>
                        <textarea id="template-greeting" name="messages_template[greeting]" rows="3" placeholder="<?php esc_attr_e( 'Hello! How can I help you today?', 'knowvault' ); ?>"></textarea>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-fallback"><?php esc_html_e( 'Fallback Message', 'knowvault' ); ?></label>
                        <textarea id="template-fallback" name="messages_template[fallback]" rows="3" placeholder="<?php esc_attr_e( "I'm not sure about that. Can you rephrase?", 'knowvault' ); ?>"></textarea>
                        <p class="description"><?php esc_html_e( 'Shown when the bot cannot find a relevant answer.', 'knowvault' ); ?></p>
                    </div>
                </div>

                <!-- Style Panel -->
                <div class="ai-botkit-tab-panel" data-panel="style">
                    <div class="ai-botkit-form-row ai-botkit-form-row-inline">
                        <div>
                            <label for="template-primary-color"><?php esc_html_e( 'Primary Color', 'knowvault' ); ?></label>
                            <input type="color" id="template-primary-color" name="style[primary_color]" value="#4F46E5">
                        </div>
                        <div>
                            <label for="template-header-bg-color"><?php esc_html_e( 'Header Background', 'knowvault' ); ?></label>
                            <input type="color" id="template-header-bg-color" name="style[header_bg_color]" value="#4F46E5">
                        </div>
                        <div>
                            <label for="template-header-color"><?php esc_html_e( 'Header Text', 'knowvault' ); ?></label>
                            <input type="color" id="template-header-color" name="style[header_color]" value="#FFFFFF">
                        </div>
                    </div>

                    <div class="ai-botkit-form-row ai-botkit-form-row-inline">
                        <div>
                            <label for="template-body-bg-color"><?php esc_html_e( 'Body Background', 'knowvault' ); ?></label>
                            <input type="color" id="template-body-bg-color" name="style[body_bg_color]" value="#FFFFFF">
                        </div>
                        <div>
                            <label for="template-ai-msg-bg"><?php esc_html_e( 'AI Message BG', 'knowvault' ); ?></label>
                            <input type="color" id="template-ai-msg-bg" name="style[ai_msg_bg_color]" value="#F3F4F6">
                        </div>
                        <div>
                            <label for="template-user-msg-bg"><?php esc_html_e( 'User Message BG', 'knowvault' ); ?></label>
                            <input type="color" id="template-user-msg-bg" name="style[user_msg_bg_color]" value="#4F46E5">
                        </div>
                    </div>

                    <div class="ai-botkit-form-row ai-botkit-form-row-inline">
                        <div>
                            <label for="template-ai-msg-font"><?php esc_html_e( 'AI Message Text', 'knowvault' ); ?></label>
                            <input type="color" id="template-ai-msg-font" name="style[ai_msg_font_color]" value="#1F2937">
                        </div>
                        <div>
                            <label for="template-user-msg-font"><?php esc_html_e( 'User Message Text', 'knowvault' ); ?></label>
                            <input type="color" id="template-user-msg-font" name="style[user_msg_font_color]" value="#FFFFFF">
                        </div>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-font-family"><?php esc_html_e( 'Font Family', 'knowvault' ); ?></label>
                        <select id="template-font-family" name="style[font_family]">
                            <option value="system-ui, -apple-system, sans-serif"><?php esc_html_e( 'System Default', 'knowvault' ); ?></option>
                            <option value="Arial, sans-serif">Arial</option>
                            <option value="'Helvetica Neue', Helvetica, sans-serif">Helvetica</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="'Times New Roman', Times, serif">Times New Roman</option>
                        </select>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-position"><?php esc_html_e( 'Widget Position', 'knowvault' ); ?></label>
                        <select id="template-position" name="style[position]">
                            <option value="bottom-right"><?php esc_html_e( 'Bottom Right', 'knowvault' ); ?></option>
                            <option value="bottom-left"><?php esc_html_e( 'Bottom Left', 'knowvault' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Model Settings Panel -->
                <div class="ai-botkit-tab-panel" data-panel="model">
                    <div class="ai-botkit-form-row">
                        <label for="template-model"><?php esc_html_e( 'AI Model', 'knowvault' ); ?></label>
                        <select id="template-model" name="model_config[model]">
                            <option value="gpt-4o-mini">GPT-4o Mini</option>
                            <option value="gpt-4o">GPT-4o</option>
                            <option value="gpt-4-turbo">GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                            <option value="claude-3-haiku">Claude 3 Haiku</option>
                            <option value="claude-3-sonnet">Claude 3 Sonnet</option>
                            <option value="claude-3-opus">Claude 3 Opus</option>
                        </select>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-temperature"><?php esc_html_e( 'Temperature', 'knowvault' ); ?></label>
                        <input type="range" id="template-temperature" name="model_config[temperature]" min="0" max="1" step="0.1" value="0.5">
                        <span id="temperature-value">0.5</span>
                        <p class="description"><?php esc_html_e( 'Lower = more focused, Higher = more creative.', 'knowvault' ); ?></p>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-max-tokens"><?php esc_html_e( 'Max Tokens', 'knowvault' ); ?></label>
                        <input type="number" id="template-max-tokens" name="model_config[max_tokens]" min="100" max="4000" value="800">
                        <p class="description"><?php esc_html_e( 'Maximum response length (100-4000).', 'knowvault' ); ?></p>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-context-length"><?php esc_html_e( 'Context Length', 'knowvault' ); ?></label>
                        <input type="number" id="template-context-length" name="model_config[context_length]" min="1" max="20" value="5">
                        <p class="description"><?php esc_html_e( 'Number of previous messages to include for context.', 'knowvault' ); ?></p>
                    </div>

                    <div class="ai-botkit-form-row">
                        <label for="template-tone"><?php esc_html_e( 'Tone', 'knowvault' ); ?></label>
                        <select id="template-tone" name="model_config[tone]">
                            <option value="professional"><?php esc_html_e( 'Professional', 'knowvault' ); ?></option>
                            <option value="friendly"><?php esc_html_e( 'Friendly', 'knowvault' ); ?></option>
                            <option value="empathetic"><?php esc_html_e( 'Empathetic', 'knowvault' ); ?></option>
                            <option value="enthusiastic"><?php esc_html_e( 'Enthusiastic', 'knowvault' ); ?></option>
                            <option value="casual"><?php esc_html_e( 'Casual', 'knowvault' ); ?></option>
                            <option value="formal"><?php esc_html_e( 'Formal', 'knowvault' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Conversation Starters Panel -->
                <div class="ai-botkit-tab-panel" data-panel="starters">
                    <p class="description"><?php esc_html_e( 'Add suggested prompts that users can click to start a conversation.', 'knowvault' ); ?></p>

                    <div id="conversation-starters-list">
                        <!-- Starters will be added dynamically via JS -->
                    </div>

                    <button type="button" class="button" id="add-starter-btn">
                        <i class="ti ti-plus"></i>
                        <?php esc_html_e( 'Add Conversation Starter', 'knowvault' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="ai-botkit-modal-footer">
            <div class="ai-botkit-modal-footer-left">
                <span id="template-system-notice" class="ai-botkit-notice-inline" style="display: none;">
                    <i class="ti ti-info-circle"></i>
                    <?php esc_html_e( 'System templates cannot be edited. Use "Save as Copy" to create an editable version.', 'knowvault' ); ?>
                </span>
            </div>
            <div class="ai-botkit-modal-footer-right">
                <button type="button" class="button ai-botkit-modal-cancel"><?php esc_html_e( 'Cancel', 'knowvault' ); ?></button>
                <button type="button" class="button" id="save-as-copy-btn" style="display: none;"><?php esc_html_e( 'Save as Copy', 'knowvault' ); ?></button>
                <button type="button" class="button button-primary" id="save-template-btn"><?php esc_html_e( 'Save Template', 'knowvault' ); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Apply Template Modal -->
<div id="ai-botkit-apply-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content">
        <div class="ai-botkit-modal-header">
            <h2><?php esc_html_e( 'Apply Template to Chatbot', 'knowvault' ); ?></h2>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>

        <div class="ai-botkit-modal-body">
            <input type="hidden" id="apply-template-id" value="0">

            <div class="ai-botkit-form-row">
                <label for="apply-chatbot-select"><?php esc_html_e( 'Select Chatbot', 'knowvault' ); ?></label>
                <select id="apply-chatbot-select">
                    <option value=""><?php esc_html_e( '-- Select a chatbot --', 'knowvault' ); ?></option>
                    <?php foreach ( $chatbots as $chatbot ) : ?>
                        <option value="<?php echo esc_attr( $chatbot['id'] ); ?>"><?php echo esc_html( $chatbot['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ai-botkit-form-row">
                <label><?php esc_html_e( 'Apply Mode', 'knowvault' ); ?></label>
                <div class="ai-botkit-radio-group">
                    <label>
                        <input type="radio" name="apply_mode" value="merge" checked>
                        <?php esc_html_e( 'Merge', 'knowvault' ); ?>
                        <span class="description"><?php esc_html_e( 'Only apply settings not already configured in the chatbot.', 'knowvault' ); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="apply_mode" value="replace">
                        <?php esc_html_e( 'Replace All', 'knowvault' ); ?>
                        <span class="description"><?php esc_html_e( 'Overwrite all chatbot settings with template values.', 'knowvault' ); ?></span>
                    </label>
                </div>
            </div>

            <div class="ai-botkit-notice ai-botkit-notice-warning">
                <i class="ti ti-alert-triangle"></i>
                <?php esc_html_e( 'This action will modify the selected chatbot\'s configuration. This cannot be undone.', 'knowvault' ); ?>
            </div>
        </div>

        <div class="ai-botkit-modal-footer">
            <button type="button" class="button ai-botkit-modal-cancel"><?php esc_html_e( 'Cancel', 'knowvault' ); ?></button>
            <button type="button" class="button button-primary" id="apply-template-btn"><?php esc_html_e( 'Apply Template', 'knowvault' ); ?></button>
        </div>
    </div>
</div>

<!-- Import Template Modal -->
<div id="ai-botkit-import-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content">
        <div class="ai-botkit-modal-header">
            <h2><?php esc_html_e( 'Import Template', 'knowvault' ); ?></h2>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>

        <div class="ai-botkit-modal-body">
            <div class="ai-botkit-form-row">
                <label for="import-file"><?php esc_html_e( 'Template File', 'knowvault' ); ?></label>
                <input type="file" id="import-file" accept=".json">
                <p class="description"><?php esc_html_e( 'Select a .json template file to import.', 'knowvault' ); ?></p>
            </div>

            <div class="ai-botkit-form-row">
                <label><?php esc_html_e( 'If template name exists:', 'knowvault' ); ?></label>
                <div class="ai-botkit-radio-group">
                    <label>
                        <input type="radio" name="conflict_mode" value="error" checked>
                        <?php esc_html_e( 'Show error', 'knowvault' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="conflict_mode" value="copy">
                        <?php esc_html_e( 'Import as copy', 'knowvault' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="conflict_mode" value="replace">
                        <?php esc_html_e( 'Replace existing', 'knowvault' ); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="ai-botkit-modal-footer">
            <button type="button" class="button ai-botkit-modal-cancel"><?php esc_html_e( 'Cancel', 'knowvault' ); ?></button>
            <button type="button" class="button button-primary" id="import-template-btn"><?php esc_html_e( 'Import', 'knowvault' ); ?></button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="ai-botkit-delete-modal" class="ai-botkit-modal" style="display: none;">
    <div class="ai-botkit-modal-content ai-botkit-modal-small">
        <div class="ai-botkit-modal-header">
            <h2><?php esc_html_e( 'Delete Template', 'knowvault' ); ?></h2>
            <button type="button" class="ai-botkit-modal-close">&times;</button>
        </div>

        <div class="ai-botkit-modal-body">
            <input type="hidden" id="delete-template-id" value="0">
            <p><?php esc_html_e( 'Are you sure you want to delete this template? This action cannot be undone.', 'knowvault' ); ?></p>
            <p class="ai-botkit-template-name-confirm"></p>
        </div>

        <div class="ai-botkit-modal-footer">
            <button type="button" class="button ai-botkit-modal-cancel"><?php esc_html_e( 'Cancel', 'knowvault' ); ?></button>
            <button type="button" class="button button-danger" id="confirm-delete-btn"><?php esc_html_e( 'Delete', 'knowvault' ); ?></button>
        </div>
    </div>
</div>

<!-- Template Card Template -->
<script type="text/template" id="template-card-template">
    <div class="ai-botkit-template-card" data-id="{{id}}">
        <div class="ai-botkit-template-card-header" style="background-color: {{primary_color}};">
            <span class="ai-botkit-template-badge ai-botkit-badge-{{category}}">{{category_label}}</span>
            {{#is_system}}
            <span class="ai-botkit-template-badge ai-botkit-badge-system"><?php esc_html_e( 'System', 'knowvault' ); ?></span>
            {{/is_system}}
        </div>
        <div class="ai-botkit-template-card-body">
            <h3 class="ai-botkit-template-name">{{name}}</h3>
            <p class="ai-botkit-template-description">{{description}}</p>
            <div class="ai-botkit-template-meta">
                <span class="ai-botkit-template-usage">
                    <i class="ti ti-chart-bar"></i>
                    {{usage_count}} <?php esc_html_e( 'uses', 'knowvault' ); ?>
                </span>
            </div>
        </div>
        <div class="ai-botkit-template-card-actions">
            <button type="button" class="button ai-botkit-edit-template" title="<?php esc_attr_e( 'Edit', 'knowvault' ); ?>">
                <i class="ti ti-edit"></i>
            </button>
            <button type="button" class="button ai-botkit-duplicate-template" title="<?php esc_attr_e( 'Duplicate', 'knowvault' ); ?>">
                <i class="ti ti-copy"></i>
            </button>
            <button type="button" class="button ai-botkit-apply-template" title="<?php esc_attr_e( 'Apply to Chatbot', 'knowvault' ); ?>">
                <i class="ti ti-rocket"></i>
            </button>
            <button type="button" class="button ai-botkit-export-template" title="<?php esc_attr_e( 'Export', 'knowvault' ); ?>">
                <i class="ti ti-download"></i>
            </button>
            {{^is_system}}
            <button type="button" class="button ai-botkit-delete-template" title="<?php esc_attr_e( 'Delete', 'knowvault' ); ?>">
                <i class="ti ti-trash"></i>
            </button>
            {{/is_system}}
        </div>
    </div>
</script>

<!-- Conversation Starter Item Template -->
<script type="text/template" id="starter-item-template">
    <div class="ai-botkit-starter-item" data-index="{{index}}">
        <div class="ai-botkit-starter-item-content">
            <input type="text" name="conversation_starters[{{index}}][text]" value="{{text}}" placeholder="<?php esc_attr_e( 'Enter starter prompt...', 'knowvault' ); ?>">
            <select name="conversation_starters[{{index}}][icon]">
                <option value="help-circle" {{#is_help}}selected{{/is_help}}><?php esc_html_e( 'Help', 'knowvault' ); ?></option>
                <option value="search" {{#is_search}}selected{{/is_search}}><?php esc_html_e( 'Search', 'knowvault' ); ?></option>
                <option value="info" {{#is_info}}selected{{/is_info}}><?php esc_html_e( 'Info', 'knowvault' ); ?></option>
                <option value="shopping-bag" {{#is_shopping}}selected{{/is_shopping}}><?php esc_html_e( 'Shopping', 'knowvault' ); ?></option>
                <option value="user" {{#is_user}}selected{{/is_user}}><?php esc_html_e( 'User', 'knowvault' ); ?></option>
                <option value="message" {{#is_message}}selected{{/is_message}}><?php esc_html_e( 'Message', 'knowvault' ); ?></option>
            </select>
        </div>
        <button type="button" class="button ai-botkit-remove-starter" title="<?php esc_attr_e( 'Remove', 'knowvault' ); ?>">
            <i class="ti ti-x"></i>
        </button>
    </div>
</script>
