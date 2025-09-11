<?php if (!defined('WPINC')) die; ?>

<?php
defined('ABSPATH') || exit;

// Ensure user has permissions
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ai-botkit-for-lead-generation'));
}

// Save settings if form is submitted
if (isset($_POST['submit'])) {
    check_admin_referer('ai_botkit_settings');
    
    // Process and save settings
    $settings = array(
        'engine',
        'openai_api_key',
        'openai_org_id',
        'anthropic_api_key',
        'google_api_key',
        'chat_model',
        'embedding_model',
        'chunk_size',
        'chunk_overlap',
        'token_bucket_limit',
        'max_requests_per_day',
        'admin_email',
        'voyageai_api_key',
        'together_api_key',
        'enable_pinecone',
        'pinecone_api_key',
        'pinecone_host'
    );

    foreach ($settings as $setting) {
        if (isset($_POST['ai_botkit_' . $setting])) {
            // Handle checkbox differently
            if ($setting === 'enable_pinecone') {
                update_option('ai_botkit_' . $setting, 1);
            } else {
                update_option(
                    'ai_botkit_' . $setting,
                    sanitize_text_field($_POST['ai_botkit_' . $setting])
                );
            }
        } else {
            // Uncheck checkbox if not set
            if ($setting === 'enable_pinecone') {
                update_option('ai_botkit_' . $setting, 0);
            }
        }
    }

    add_settings_error(
        'ai_botkit_messages',
        'ai_botkit_message',
        __('Settings Saved', 'ai-botkit-for-lead-generation'),
        'updated'
    );
}

// Get current values
$selected_engine = get_option('ai_botkit_engine', 'openai');
$openai_api_key = get_option('ai_botkit_openai_api_key');
$openai_org_id = get_option('ai_botkit_openai_org_id');
$anthropic_api_key = get_option('ai_botkit_anthropic_api_key');
$voyageai_api_key = get_option('ai_botkit_voyageai_api_key');
$google_api_key = get_option('ai_botkit_google_api_key');
$chat_model = get_option('ai_botkit_chat_model', 'gpt-4-turbo-preview');
$embedding_model = get_option('ai_botkit_embedding_model', 'text-embedding-3-small');
$chunk_size = get_option('ai_botkit_chunk_size', 1000);
$chunk_overlap = get_option('ai_botkit_chunk_overlap', 200);
$token_bucket_limit = get_option('ai_botkit_token_bucket_limit', 100000);
$max_requests_per_day = get_option('ai_botkit_max_requests_per_day', 60);
$admin_email = get_option('ai_botkit_admin_email', '');
$together_api_key = get_option('ai_botkit_together_api_key', '');
$enable_pinecone = get_option('ai_botkit_enable_pinecone', 0);
$pinecone_api_key = get_option('ai_botkit_pinecone_api_key', '');
$pinecone_host = get_option('ai_botkit_pinecone_host', '');
// Available engines and their models
$engines = $this->get_engines();
?>

<div class="ai-botkit-settings-container">

  <h2 class="ai-botkit-settings-title"><?php esc_html_e('Settings', 'ai-botkit-for-lead-generation'); ?></h2>
    <!-- Tabs Content -->
    
    <?php settings_errors('ai_botkit_messages'); ?>
    <form method="post" action="">
        <div class="ai-botkit-tabs-content">
            <?php wp_nonce_field('ai_botkit_settings'); ?>
            <input type="hidden" id="ai_botkit_migration_nonce" value="<?php echo wp_create_nonce('ai_botkit_admin'); ?>" />

            <!-- API Keys Tab -->
            <div class="ai-botkit-tab-pane ai-botkit-card" data-tab-content="api">
                    <div class="ai-botkit-card-header">
                        <h3><?php esc_html_e('API Keys', 'ai-botkit-for-lead-generation'); ?></h3>
                        <p><?php esc_html_e('Connect to AI providers', 'ai-botkit-for-lead-generation'); ?></p>
                    </div>

                    <div class="ai-botkit-card-body">
                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_engine" class="ai-botkit-select-label"><?php esc_html_e('AI Engine', 'ai-botkit-for-lead-generation'); ?></label>
                            <select id="ai_botkit_engine" class="ai-botkit-select-input" name="ai_botkit_engine">
                                <?php foreach ($engines as $engine_id => $engine): ?>
                                    <option value="<?php echo esc_attr($engine_id); ?>"
                                            <?php selected($selected_engine, $engine_id); ?>>
                                        <?php echo esc_html($engine['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ai-botkit-hint"><?php esc_html_e('Select the AI engine you want to use', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-openai" <?php echo $selected_engine !== 'openai' ? 'style="display: none;"' : ''; ?>>
                            <label for="ai_botkit_openai_api_key" class="ai-botkit-label"><?php esc_html_e('OpenAI API Key', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input type="password" id="ai_botkit_openai_api_key" name="ai_botkit_openai_api_key" placeholder="sk-..." value="<?php echo esc_attr($openai_api_key); ?>" />
                                <button class="ai-botkit-btn-outline ai_botkit_test_api"><?php esc_html_e('Verify', 'ai-botkit-for-lead-generation'); ?></button>
                            </div>
                            <div class="ai-botkit-api-test-result-container">
                                <span class="spinner" style="display: none;"></span>
                                <span class="ai-botkit-api-test-result" style="display: none;"></span>
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Required for GPT-3.5 and GPT-4 models', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-openai" <?php echo $selected_engine !== 'openai' ? 'style="display: none;"' : ''; ?>>
                            <label for="openai-org-id" class="ai-botkit-label"><?php esc_html_e('OpenAI Organization ID', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input
                                    type="text"
                                    id="ai_botkit_openai_org_id"
                                    name="ai_botkit_openai_org_id"
                                    value="<?php echo esc_attr($openai_org_id); ?>" />
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Optional: Your OpenAI organization ID.', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-anthropic" <?php echo $selected_engine !== 'anthropic' ? 'style="display: none;"' : ''; ?>>
                            <label for="ai_botkit_anthropic_api_key" class="ai-botkit-label"><?php esc_html_e('Anthropic API Key', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input type="password" id="ai_botkit_anthropic_api_key" name="ai_botkit_anthropic_api_key" placeholder="sk-ant-..." value="<?php echo esc_attr($anthropic_api_key); ?>" />
                                <button class="ai-botkit-btn-outline ai_botkit_test_api"><?php esc_html_e('Verify', 'ai-botkit-for-lead-generation'); ?></button>
                            </div>
                            <div class="ai-botkit-api-test-result-container">
                                <span class="spinner" style="display: none;"></span>
                                <span class="ai-botkit-api-test-result" style="display: none;"></span>
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Required for Claude models', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-anthropic" <?php echo $selected_engine !== 'anthropic' ? 'style="display: none;"' : ''; ?>>
                            <label for="ai_botkit_voyageai_api_key" class="ai-botkit-label"><?php esc_html_e('VoyageAI API Key', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input type="password" id="ai_botkit_voyageai_api_key" name="ai_botkit_voyageai_api_key" placeholder="sk-..." value="<?php echo esc_attr($voyageai_api_key); ?>" />
                                <!-- <button class="ai-botkit-btn-outline ai_botkit_test_api"><?php esc_html_e('Verify', 'ai-botkit-for-lead-generation'); ?></button> -->
                            </div>
                            <div class="ai-botkit-api-test-result-container">
                                <span class="spinner" style="display: none;"></span>
                                <span class="ai-botkit-api-test-result" style="display: none;"></span>
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Required for VoyageAI Embedding models', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-google" <?php echo $selected_engine !== 'google' ? 'style="display: none;"' : ''; ?>>
                            <label for="ai_botkit_google_api_key" class="ai-botkit-label"><?php esc_html_e('Google API Key', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input type="password" id="ai_botkit_google_api_key" name="ai_botkit_google_api_key" placeholder="sk-..." value="<?php echo esc_attr($google_api_key); ?>" />
                                <button class="ai-botkit-btn-outline ai_botkit_test_api"><?php esc_html_e('Verify', 'ai-botkit-for-lead-generation'); ?></button>
                            </div>
                            <div class="ai-botkit-api-test-result-container">
                                <span class="spinner" style="display: none;"></span>
                                <span class="ai-botkit-api-test-result" style="display: none;"></span>
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Required for Google models', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group engine-settings engine-together" <?php echo $selected_engine !== 'together' ? 'style="display: none;"' : ''; ?>>
                            <label for="ai_botkit_together_api_key" class="ai-botkit-label"><?php esc_html_e('Together AI API Key', 'ai-botkit-for-lead-generation'); ?></label>
                            <div class="ai-botkit-inline-input">
                                <input type="password" id="ai_botkit_together_api_key" name="ai_botkit_together_api_key" placeholder="..." value="<?php echo esc_attr($together_api_key); ?>" />
                                <button class="ai-botkit-btn-outline ai_botkit_test_api"><?php esc_html_e('Verify', 'ai-botkit-for-lead-generation'); ?></button>
                            </div>
                            <div class="ai-botkit-api-test-result-container">
                                <span class="spinner" style="display: none;"></span>
                                <span class="ai-botkit-api-test-result" style="display: none;"></span>
                            </div>
                            <p class="ai-botkit-hint"><?php esc_html_e('Required for Together AI models', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_chat_model" class="ai-botkit-select-label"><?php esc_html_e('Chat Model', 'ai-botkit-for-lead-generation'); ?></label>
                            <select id="ai_botkit_chat_model" class="ai-botkit-select-input" name="ai_botkit_chat_model">
                                <?php foreach ($engines[$selected_engine]['chat_models'] as $model_id => $model_name): ?>
                                    <option value="<?php echo esc_attr($model_id); ?>"
                                            <?php selected($chat_model, $model_id); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ai-botkit-hint"><?php esc_html_e('Select the chat model you want to use', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_embedding_model" class="ai-botkit-select-label"><?php esc_html_e('Embedding Model', 'ai-botkit-for-lead-generation'); ?></label>
                            <select id="ai_botkit_embedding_model" class="ai-botkit-select-input" name="ai_botkit_embedding_model">
                                <?php foreach ($engines[$selected_engine]['embedding_models'] as $model_id => $model_name): ?>
                                    <option value="<?php echo esc_attr($model_id); ?>"
                                            <?php selected($embedding_model, $model_id); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ai-botkit-hint"><?php esc_html_e('Select the embedding model you want to use', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>

                        <h4><?php esc_html_e('Pinecone Vector Database Settings', 'ai-botkit-for-lead-generation'); ?></h4>
                        <p class="ai-botkit-section-description"><?php esc_html_e('Configure Pinecone for vector storage and search. If disabled, the plugin will use local WordPress database for vector storage.', 'ai-botkit-for-lead-generation'); ?></p>

                        <div class="ai-botkit-form-group">
                            <label class="ai-botkit-checkbox-label">
                                <input type="checkbox" id="ai_botkit_enable_pinecone" name="ai_botkit_enable_pinecone" value="1" <?php checked($enable_pinecone, 1); ?> />
                                <span class="ai-botkit-checkbox-text"><?php esc_html_e('Enable Pinecone Vector Database', 'ai-botkit-for-lead-generation'); ?></span>
                            </label>
                            <p class="ai-botkit-hint"><?php esc_html_e('Check this box to use Pinecone for vector storage. Uncheck to use local WordPress database.', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>

                        <div id="pinecone-settings" class="ai-botkit-form-group" style="<?php echo $enable_pinecone ? '' : 'display: none;'; ?>">
                            <div class="ai-botkit-form-group">
                                <label for="ai_botkit_pinecone_api_key" class="ai-botkit-label"><?php esc_html_e('Pinecone API Key', 'ai-botkit-for-lead-generation'); ?></label>
                                <input type="password" id="ai_botkit_pinecone_api_key" name="ai_botkit_pinecone_api_key" placeholder="sk-..." value="<?php echo esc_attr($pinecone_api_key); ?>" />
                                <p class="ai-botkit-hint"><?php esc_html_e('Enter the Pinecone API key, sign up at', 'ai-botkit-for-lead-generation'); ?> <a href="https://pinecone.io" target="_blank">pinecone.io</a></p>
                            </div>

                            <div class="ai-botkit-form-group">
                                <label for="ai_botkit_pinecone_host" class="ai-botkit-label"><?php esc_html_e('Pinecone Host', 'ai-botkit-for-lead-generation'); ?></label>
                                <input type="text" id="ai_botkit_pinecone_host" name="ai_botkit_pinecone_host" placeholder="https://your-index.pinecone.io" value="<?php echo esc_attr($pinecone_host); ?>" />
                                <p class="ai-botkit-hint"><?php esc_html_e('Enter the Pinecone host URL (e.g., https://your-index.pinecone.io).', 'ai-botkit-for-lead-generation'); ?> <br /><strong><?php esc_html_e('Important: Your Pinecone host index must be configured with 1536 dimensions to work with this plugin.', 'ai-botkit-for-lead-generation'); ?></strong></p>
                            </div>
                        </div>
                    </div>
            </div>

            <!-- General Tab -->
            <div class="ai-botkit-tab-pane ai-botkit-card" data-tab-content="general">
                    <div class="ai-botkit-card-header">
                        <h3><?php esc_html_e('AI Parameters', 'ai-botkit-for-lead-generation'); ?></h3>
                        <p><?php esc_html_e('Manage your AI parameters/settings', 'ai-botkit-for-lead-generation'); ?></p>
                    </div>

                    <div class="ai-botkit-card-body">

                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_chunk_size" class="ai-botkit-label"><?php esc_html_e('Chunk Size', 'ai-botkit-for-lead-generation'); ?></label>
                            <input type="number" id="ai_botkit_chunk_size" name="ai_botkit_chunk_size" value="<?php echo esc_attr($chunk_size); ?>" min="100" max="2000" step="100" class="small-text" />
                            <p class="ai-botkit-hint"><?php esc_html_e('Enter the chunk size for document processing', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>

                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_chunk_overlap" class="ai-botkit-label"><?php esc_html_e('Chunk Overlap', 'ai-botkit-for-lead-generation'); ?></label>
                            <input type="number" id="ai_botkit_chunk_overlap" name="ai_botkit_chunk_overlap" value="<?php echo esc_attr($chunk_overlap); ?>" min="0" max="200" step="1" class="small-text" />
                            <p class="ai-botkit-hint"><?php esc_html_e('Enter the chunk overlap for document processing', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>

                        <h4><?php esc_html_e('Rate Limiting Settings', 'ai-botkit-for-lead-generation'); ?></h4>
                        <p class="ai-botkit-section-description"><?php esc_html_e('Configure rate limits for logged-in users', 'ai-botkit-for-lead-generation'); ?></p>

                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_token_bucket_limit" class="ai-botkit-label"><?php esc_html_e('Max Tokens per Conversation (Token Bucket)', 'ai-botkit-for-lead-generation'); ?></label>
                            <input type="number" id="ai_botkit_token_bucket_limit" name="ai_botkit_token_bucket_limit" value="<?php echo esc_attr($token_bucket_limit); ?>" min="10000" max="1000000" step="10000" class="small-text" />
                            <p class="ai-botkit-hint"><?php esc_html_e('Maximum number of tokens allowed per user in a 24-hour window.', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>

                        <div class="ai-botkit-form-group">
                            <label for="ai_botkit_max_requests_per_day" class="ai-botkit-label"><?php esc_html_e('Max Messages in 24 Hours', 'ai-botkit-for-lead-generation'); ?></label>
                            <input type="number" id="ai_botkit_max_requests_per_day" name="ai_botkit_max_requests_per_day" value="<?php echo esc_attr($max_requests_per_day); ?>" min="1" max="1000" step="1" class="small-text" />
                            <p class="ai-botkit-hint"><?php esc_html_e('Maximum number of messages a user can send in a day.', 'ai-botkit-for-lead-generation'); ?></p>
                        </div>
                    

                        <!-- <div class="ai-botkit-form-toggle">
                            <div>
                                <label for="auto-save">Auto-save drafts</label>
                                <p>Automatically save changes to chatbots</p>
                            </div>
                            <label class="ai-botkit-switch">
                            <input type="checkbox" id="auto-save" checked>
                            <span class="ai-botkit-slider"></span>
                            </label>
                        </div>

                        <div class="ai-botkit-form-toggle">
                            <div>
                                <label for="analytics">Enable Analytics</label>
                                <p>Track conversations and user interactions</p>
                            </div>
                            <label class="ai-botkit-switch">
                            <input type="checkbox" id="analytics" checked>
                            <span class="ai-botkit-slider"></span>
                            </label>
                        </div> -->
                    </div>
            </div>

            <!-- Notifications Tab -->
            <!-- <div class="ai-botkit-tab-pane" data-tab-content="notifications">
            <div class="ai-botkit-card">
                <div class="ai-botkit-card-header">
                <h3>Notification Settings</h3>
                <p>Manage how you receive notifications</p>
                </div>

                <div class="ai-botkit-card-body">
                <div class="ai-botkit-form-toggle">
                    <div>
                    <label for="email-notifications">Email Notifications</label>
                    <p>Receive email alerts for important events</p>
                    </div>
                    <label class="ai-botkit-switch">
                    <input type="checkbox" id="email-notifications" checked>
                    <span class="ai-botkit-slider"></span>
                    </label>
                </div>

                <div class="ai-botkit-form-toggle">
                    <div>
                    <label for="conversation-alerts">Conversation Alerts</label>
                    <p>Get notified when a user starts a new conversation</p>
                    </div>
                    <label class="ai-botkit-switch">
                    <input type="checkbox" id="conversation-alerts">
                    <span class="ai-botkit-slider"></span>
                    </label>
                </div>

                <div class="ai-botkit-form-toggle">
                    <div>
                    <label for="daily-report">Daily Summary Report</label>
                    <p>Receive a daily email with chatbot metrics</p>
                    </div>
                    <label class="ai-botkit-switch">
                    <input type="checkbox" id="daily-report" checked>
                    <span class="ai-botkit-slider"></span>
                    </label>
                </div>
                </div>

                <div class="ai-botkit-card-footer">
                <button class="ai-botkit-btn">Save Notification Settings</button>
                </div>
            </div>
            </div> -->
        </div>
        <div class="ai-botkit-card-footer">
            <input type="submit" class="ai-botkit-btn" name="submit" value="<?php esc_html_e('Save Changes', 'ai-botkit-for-lead-generation'); ?>">
        </div>
    </form>
</div>