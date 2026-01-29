<?php
if (!defined('WPINC')) die;

$is_widget = isset($args['widget']) && $args['widget'];
$container_class = $is_widget ? 'ai-botkit-widget-container' : 'ai-botkit-chat-container';

// Ensure required style properties exist with defaults
if (!isset($styles) || !is_array($styles)) {
    $styles = array();
}
$styles = array_merge(array(
    'avatar' => AI_BOTKIT_PLUGIN_URL . 'public/images/bot.png'
), $styles);
?>

<div class="<?php echo esc_attr($container_class); ?>" id="<?php echo esc_attr($chat_id); ?>" data-widget="<?php echo $is_widget ? 'true' : 'false'; ?>">
    <div class="ai-botkit-chat">
        <!-- Chat Header -->
        <div class="ai-botkit-chat-header">
            <div class="ai-botkit-chat-header-left">
                <div class="ai-botkit-chat-avatar">
                    <img src="<?php echo esc_url($styles['avatar']); ?>" alt="Chatbot avatar" class="ai-botkit-avatar-img" />
                </div>
                <h3><?php echo esc_html($chatbot_data['name']); ?></h3>
            </div>
            <div class="ai-botkit-chat-actions">
                <?php if ( is_user_logged_in() ) : ?>
                    <button type="button" class="ai-botkit-history-toggle" aria-label="<?php esc_attr_e( 'Chat history', 'knowvault' ); ?>" aria-expanded="false">
                        <i class="ti ti-history"></i>
                    </button>
                <?php endif; ?>
                <button type="button" class="ai-botkit-clear" aria-label="<?php esc_attr_e( 'Clear chat', 'knowvault' ); ?>">
                    <i class="ti ti-refresh"></i>
                </button>
                <?php if ( $is_widget ) : ?>
                    <button type="button" class="ai-botkit-minimize" aria-label="<?php esc_attr_e( 'Minimize chat', 'knowvault' ); ?>">
                        <i class="ti ti-x"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="ai-botkit-chat-messages" aria-live="polite">
            <!-- Welcome Message -->
            <div class="ai-botkit-message assistant">
                <div class="ai-botkit-message-content">
                    <div class="ai-botkit-message-text">
                        <?php 
                        $messages_template = json_decode($chatbot_data['messages_template'], true);
                        $greeting = $messages_template['greeting'] ?? __('Hello! How can I help you today?', 'knowvault');
                        echo wp_kses_post($greeting); 
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="ai-botkit-chat-input">
            <input 
                class="ai-botkit-input"
                id="ai-botkit-chat-input"
                placeholder="<?php esc_attr_e('Type your message...', 'knowvault'); ?>"
                required
                aria-label="<?php esc_attr_e('Message input', 'knowvault'); ?>"
            >
            <button
                class="ai-botkit-send-button"
                aria-label="<?php esc_attr_e('Send message', 'knowvault'); ?>"
            >
                <i class="ti ti-send"></i>
            </button>
        </div>
    </div>
        
    <!-- Message Template -->
    <template id="<?php echo esc_attr($chat_id); ?>-message-template">
        <div class="ai-botkit-message">
            <div class="ai-botkit-message-content">
                <div class="ai-botkit-message-text"></div>
                <?php if ( 1 == $chatbot_data['feedback'] ) { ?>
                <div class="ai-botkit-message-feedback">
                        <i class="ti ti-thumb-up ai-botkit-message-feedback-up-button"></i>
                        <i class="ti ti-thumb-down ai-botkit-message-feedback-down-button"></i>
                    </div>
                <?php } ?>
            </div>
        </div>
    </template>

    <!-- Loading Template -->
    <template id="<?php echo esc_attr( $chat_id ); ?>-typing-template">
        <div class="ai-botkit-message assistant">
            <div class="ai-botkit-typing">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </template>

    <?php
    /**
     * Phase 2: Chat History Panel (FR-201 to FR-209)
     * Only rendered for logged-in users.
     *
     * @since 2.0.0
     */
    if ( is_user_logged_in() ) :
    ?>
    <!-- Chat History Panel -->
    <div class="ai-botkit-history-panel" aria-hidden="true" aria-label="<?php esc_attr_e( 'Chat History', 'knowvault' ); ?>">
        <!-- History Header -->
        <div class="ai-botkit-history-header">
            <h3><?php esc_html_e( 'Chat History', 'knowvault' ); ?></h3>
            <button type="button" class="ai-botkit-history-close" aria-label="<?php esc_attr_e( 'Close history', 'knowvault' ); ?>">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <!-- Quick Filters -->
        <div class="ai-botkit-quick-filters">
            <button type="button" class="ai-botkit-quick-filter" data-filter="today">
                <?php esc_html_e( 'Today', 'knowvault' ); ?>
            </button>
            <button type="button" class="ai-botkit-quick-filter" data-filter="week">
                <?php esc_html_e( 'This Week', 'knowvault' ); ?>
            </button>
            <button type="button" class="ai-botkit-quick-filter" data-filter="favorites">
                <i class="ti ti-star"></i>
                <?php esc_html_e( 'Favorites', 'knowvault' ); ?>
            </button>
        </div>

        <!-- Filter Form (Collapsible) -->
        <div class="ai-botkit-history-filters" style="display: none;">
            <form class="ai-botkit-history-filter-form">
                <div class="ai-botkit-filter-group">
                    <label for="ai-botkit-filter-start-date"><?php esc_html_e( 'From', 'knowvault' ); ?></label>
                    <input type="date" id="ai-botkit-filter-start-date" name="start_date">
                </div>
                <div class="ai-botkit-filter-group">
                    <label for="ai-botkit-filter-end-date"><?php esc_html_e( 'To', 'knowvault' ); ?></label>
                    <input type="date" id="ai-botkit-filter-end-date" name="end_date">
                </div>
                <div class="ai-botkit-filter-group">
                    <label for="ai-botkit-filter-favorite"><?php esc_html_e( 'Status', 'knowvault' ); ?></label>
                    <select id="ai-botkit-filter-favorite" name="is_favorite">
                        <option value=""><?php esc_html_e( 'All', 'knowvault' ); ?></option>
                        <option value="true"><?php esc_html_e( 'Favorites', 'knowvault' ); ?></option>
                    </select>
                </div>
                <div class="ai-botkit-filter-actions">
                    <button type="submit" class="ai-botkit-filter-btn ai-botkit-filter-btn-apply">
                        <?php esc_html_e( 'Apply', 'knowvault' ); ?>
                    </button>
                    <button type="button" class="ai-botkit-filter-btn ai-botkit-filter-btn-clear ai-botkit-clear-filters">
                        <?php esc_html_e( 'Clear', 'knowvault' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Conversation List -->
        <div class="ai-botkit-conversation-list-wrapper">
            <!-- Loading Indicator -->
            <div class="ai-botkit-history-loading">
                <div class="ai-botkit-history-spinner"></div>
                <div class="ai-botkit-history-loading-text"><?php esc_html_e( 'Loading conversations...', 'knowvault' ); ?></div>
            </div>

            <!-- Empty State -->
            <div class="ai-botkit-history-empty" style="display: none;">
                <div class="ai-botkit-history-empty-icon">
                    <i class="ti ti-messages"></i>
                </div>
                <h4><?php esc_html_e( 'No conversations yet', 'knowvault' ); ?></h4>
                <p><?php esc_html_e( 'Start a conversation and it will appear here.', 'knowvault' ); ?></p>
            </div>

            <!-- Conversation List Container -->
            <div class="ai-botkit-conversation-list"></div>

            <!-- Load More Button -->
            <div class="ai-botkit-load-more-wrapper" style="display: none;">
                <button type="button" class="ai-botkit-load-more">
                    <i class="ti ti-chevron-down"></i>
                    <?php esc_html_e( 'Load more', 'knowvault' ); ?>
                </button>
            </div>
        </div>

        <!-- History Footer -->
        <div class="ai-botkit-history-footer">
            <span class="ai-botkit-history-count"></span>
            <button type="button" class="ai-botkit-new-conversation-btn">
                <i class="ti ti-plus"></i>
                <?php esc_html_e( 'New conversation', 'knowvault' ); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>