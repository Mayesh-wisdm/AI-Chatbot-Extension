<?php
namespace AI_BotKit\Admin;
/**
 * Class Rate_Limiter
 * 
 * Handles rate limiting for API requests in the AI BotKit plugin.
 * Uses WordPress transients for storing rate limit data.
 */
class Rate_Limiter {
    /**
     * The default rate limit window in seconds
     */
    const DEFAULT_WINDOW = 60;

    /**
     * The default number of requests allowed per window
     */
    const DEFAULT_MAX_REQUESTS = 60;

    /**
     * Get the rate limit key for a specific user and action
     *
     * @param int    $user_id User ID
     * @param string $action  The action being rate limited
     * @return string
     */
    private function get_rate_limit_key($user_id, $action) {
        return "ai_botkit_rate_limit_{$action}_{$user_id}";
    }

    /**
     * Check if a request should be rate limited
     *
     * @param int    $user_id User ID
     * @param string $action  The action being rate limited
     * @return bool|WP_Error True if allowed, WP_Error if limited
     */
    public function check_rate_limit($user_id, $action) {
        // Get user-specific or default limits
        $window = $this->get_window_size($user_id, $action);
        $max_requests = $this->get_max_requests($user_id, $action);
        
        $key = $this->get_rate_limit_key($user_id, $action);
        $requests = get_transient($key);

        if (false === $requests) {
            $requests = array();
        }

        // Remove expired timestamps
        $now = time();
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });

        // Check if limit exceeded
        if (count($requests) >= $max_requests) {
            $reset_time = min($requests) + $window;
            $wait_time = $reset_time - $now;
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Please wait %d seconds.', 'knowvault'),
                    $wait_time
                ),
                array(
                    'status' => 429,
                    'reset_time' => $reset_time,
                    'wait_time' => $wait_time
                )
            );
        }

        // Add current request
        $requests[] = $now;
        set_transient($key, $requests, $window);

        return true;
    }

    /**
     * Get the window size for rate limiting
     *
     * @param int    $user_id User ID
     * @param string $action  The action being rate limited
     * @return int Window size in seconds
     */
    public function get_window_size($user_id, $action) {
        $user_window = get_user_meta($user_id, "ai_botkit_{$action}_window", true);
        return !empty($user_window) ? (int) $user_window : self::DEFAULT_WINDOW;
    }

    /**
     * Get the maximum number of requests allowed
     *
     * @param int    $user_id User ID
     * @param string $action  The action being rate limited
     * @return int Maximum number of requests
     */
    public function get_max_requests($user_id, $action) {
        $user_max = get_user_meta($user_id, "ai_botkit_{$action}_max_requests", true);
        return !empty($user_max) ? (int) $user_max : self::DEFAULT_MAX_REQUESTS;
    }

    /**
     * Set custom rate limits for a user
     *
     * @param int    $user_id      User ID
     * @param string $action       The action being rate limited
     * @param int    $window       Window size in seconds
     * @param int    $max_requests Maximum number of requests allowed
     * @return bool
     */
    public function set_user_rate_limits($user_id, $action, $window, $max_requests) {
        update_user_meta($user_id, "ai_botkit_{$action}_window", $window);
        update_user_meta($user_id, "ai_botkit_{$action}_max_requests", $max_requests);
        return true;
    }

    /**
     * Reset rate limit for a user
     *
     * @param int    $user_id User ID
     * @param string $action  The action being rate limited
     * @return bool
     */
    public function reset_rate_limit($user_id, $action) {
        $key = $this->get_rate_limit_key($user_id, $action);
        return delete_transient($key);
    }
} 
