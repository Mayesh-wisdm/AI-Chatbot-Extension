<?php
namespace AI_BotKit\Integration;

use AI_BotKit\Core\RAG_Engine;

/**
 * User Authentication
 * 
 * Handles user authentication, access control, and permission management
 * for the chatbot system.
 */
class User_Authentication {
    /**
     * RAG Engine instance
     */
    private $rag_engine;

    /**
     * Default role capabilities
     */
    private const DEFAULT_CAPABILITIES = [
        'administrator' => [
            'manage_ai_botkit',
            'edit_ai_botkit_settings',
            'view_ai_botkit_analytics',
            'manage_ai_botkit_documents',
            'use_ai_botkit_chat',
            'view_ai_botkit_history',
        ],
        'editor' => [
            'manage_ai_botkit_documents',
            'use_ai_botkit_chat',
            'view_ai_botkit_history',
        ],
        'author' => [
            'use_ai_botkit_chat',
            'view_ai_botkit_history',
        ],
        'subscriber' => [
            'use_ai_botkit_chat',
        ],
    ];

    /**
     * Initialize the authentication system
     */
    public function __construct(RAG_Engine $rag_engine) {
        $this->rag_engine = $rag_engine;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Add capabilities on plugin activation
        add_action('ai_botkit_activate', [$this, 'add_capabilities']);
        
        // Remove capabilities on plugin deactivation
        add_action('ai_botkit_deactivate', [$this, 'remove_capabilities']);
        
        // User authentication hooks
        add_filter('ai_botkit_can_use_chat', [$this, 'can_use_chat'], 10, 2);
        add_filter('ai_botkit_can_view_history', [$this, 'can_view_history'], 10, 2);
        add_filter('ai_botkit_can_manage_documents', [$this, 'can_manage_documents'], 10, 2);
        add_filter('ai_botkit_can_manage_settings', [$this, 'can_manage_settings'], 10, 2);
        
        // Rate limiting hooks
        add_filter('ai_botkit_check_rate_limit', [$this, 'check_rate_limit'], 10, 2);
        
        // User session hooks
        add_action('wp_login', [$this, 'start_chat_session'], 10, 2);
        add_action('wp_logout', [$this, 'end_chat_session']);
    }

    /**
     * Add capabilities to roles
     */
    public function add_capabilities(): void {
        foreach (self::DEFAULT_CAPABILITIES as $role_name => $capabilities) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->add_cap($capability);
                }
            }
        }
    }

    /**
     * Remove capabilities from roles
     */
    public function remove_capabilities(): void {
        foreach (self::DEFAULT_CAPABILITIES as $role_name => $capabilities) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    /**
     * Check if user can use chat
     */
    public function can_use_chat(bool $default, int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Check if user has required capability
        if ($user->has_cap('use_ai_botkit_chat')) {
            // Check rate limiting
            return $this->check_rate_limit($default, $user_id);
        }

        return false;
    }

    /**
     * Check if user can view chat history
     */
    public function can_view_history(bool $default, int $user_id): bool {
        $user = get_user_by('id', $user_id);
        return $user && $user->has_cap('view_ai_botkit_history');
    }

    /**
     * Check if user can manage documents
     */
    public function can_manage_documents(bool $default, int $user_id): bool {
        $user = get_user_by('id', $user_id);
        return $user && $user->has_cap('manage_ai_botkit_documents');
    }

    /**
     * Check if user can manage settings
     */
    public function can_manage_settings(bool $default, int $user_id): bool {
        $user = get_user_by('id', $user_id);
        return $user && $user->has_cap('edit_ai_botkit_settings');
    }

    /**
     * Check rate limiting for user
     */
    public function check_rate_limit(bool $default, int $user_id): bool {
        $rate_limits = get_option('ai_botkit_rate_limits', [
            'administrator' => 0, // Unlimited
            'editor' => 100,
            'author' => 50,
            'subscriber' => 20,
        ]);

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Get user's highest role
        $roles = array_intersect(array_keys($rate_limits), $user->roles);
        if (empty($roles)) {
            return false;
        }
        $highest_role = reset($roles);

        // Get daily limit for role
        $daily_limit = $rate_limits[$highest_role];
        if ($daily_limit === 0) {
            return true; // Unlimited
        }

        // Check usage
        $usage = $this->get_daily_usage($user_id);
        return $usage < $daily_limit;
    }

    /**
     * Start chat session for user
     */
    public function start_chat_session(string $user_login, \WP_User $user): void {
        $session_id = wp_generate_uuid4();
        update_user_meta($user->ID, 'ai_botkit_chat_session', $session_id);
        
        // Clear expired sessions
        $this->cleanup_expired_sessions();
    }

    /**
     * End chat session for user
     */
    public function end_chat_session(): void {
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_user_meta($user_id, 'ai_botkit_chat_session');
        }
    }

    /**
     * Get user's daily chat usage
     */
    private function get_daily_usage(int $user_id): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_botkit_messages';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE user_id = %d 
                AND role = 'user' 
                AND created_at >= %s",
                $user_id,
                gmdate('Y-m-d 00:00:00')
            )
        );
    }

    /**
     * Cleanup expired chat sessions
     */
    private function cleanup_expired_sessions(): void {
        global $wpdb;
        $meta_key = 'ai_botkit_chat_session';
        $expiry = 24 * HOUR_IN_SECONDS;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                AND meta_value < %s",
                $meta_key,
                gmdate('Y-m-d H:i:s', time() - $expiry)
            )
        );
    }

    /**
     * Get user's active chat session
     */
    public function get_chat_session(int $user_id): ?string {
        return get_user_meta($user_id, 'ai_botkit_chat_session', true) ?: null;
    }

    /**
     * Get user's role-based settings
     */
    public function get_user_settings(int $user_id): array {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return [];
        }

        $settings = get_option('ai_botkit_user_settings', [
            'administrator' => [
                'max_tokens' => 4000,
                'temperature' => 0.7,
                'stream_response' => true,
            ],
            'editor' => [
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'stream_response' => true,
            ],
            'author' => [
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'stream_response' => true,
            ],
            'subscriber' => [
                'max_tokens' => 500,
                'temperature' => 0.7,
                'stream_response' => true,
            ],
        ]);

        // Get user's highest role with settings
        $roles = array_intersect(array_keys($settings), $user->roles);
        if (empty($roles)) {
            return $settings['subscriber']; // Default to subscriber settings
        }

        return $settings[reset($roles)];
    }

    /**
     * Get authentication statistics
     */
    public function get_stats(): array {
        global $wpdb;
        $messages_table = $wpdb->prefix . 'ai_botkit_messages';

        return [
            'total_users' => count_users()['total_users'],
            'active_sessions' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
                    WHERE meta_key = %s",
                    'ai_botkit_chat_session'
                )
            ),
            'daily_messages' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$messages_table} 
                    WHERE created_at >= %s",
                    gmdate('Y-m-d 00:00:00')
                )
            ),
            'total_conversations' => $wpdb->get_var(
                "SELECT COUNT(DISTINCT conversation_id) FROM {$messages_table}"
            ),
        ];
    }
} 