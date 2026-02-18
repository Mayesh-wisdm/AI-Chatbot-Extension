<?php
namespace AI_BotKit\Core;


/**
 * Class Rate_Limiter
 * 
 * Handles rate limiting for chatbot usage in the AI BotKit plugin.
 * Implements token bucket and max requests rate limiting for logged-in users.
 */
class Rate_Limiter {
    /**
     * Get the token bucket limit (max tokens per user in 24 hours)
     * 
     * @return int Token bucket limit
     */
    public function get_token_bucket_limit() {
        return (int) get_option('ai_botkit_token_bucket_limit', 100000);
    }

    /**
     * Get the maximum messages per user in 24 hours
     * 
     * @return int Maximum messages per day
     */
    public function get_max_requests_per_day() {
        return (int) get_option('ai_botkit_max_requests_per_day', 60);
    }

    /**
     * Check if a user has exceeded their rate limits
     *
     * @param int $user_id User ID (optional, for logged-in users)
     * @return bool|array True if allowed, array with error details if limited
     */
    public function check_user_limits($user_id = null) {
        try {
            // Handle both logged-in and non-logged-in users
            if (is_user_logged_in()) {
                // For logged-in users, use user_id
                if (empty($user_id)) {
                    $user_id = get_current_user_id();
                }
                
                // Get usage statistics for the past 24 hours
                $stats = $this->get_user_usage_stats($user_id);
            } else {
                // For non-logged-in users, use IP address
                $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $ip_hash = hash('sha256', $user_ip);
                
                // Get usage statistics for the past 24 hours by IP hash
                $stats = $this->get_user_usage_stats(null, $ip_hash);
            }
            
            // Get dynamic limits from options
            $token_limit = $this->get_token_bucket_limit();
            $message_limit = $this->get_max_requests_per_day();
            
            // Check token limit
            if ($stats['total_tokens'] >= $token_limit) {
                return [
                    'limited' => true,
                    'reason' => 'token_limit',
                    'message' => sprintf(
                        __('You have reached your token limit of %s for the day. Please try again tomorrow.', 'knowvault'),
                        number_format_i18n($token_limit)
                    ),
                    'usage' => $stats['total_tokens'],
                    'limit' => $token_limit,
                    'reset_time' => strtotime('tomorrow')
                ];
            }

            // Check message limit
            if ($stats['message_count'] >= $message_limit) {
                return [
                    'limited' => true,
                    'reason' => 'message_limit',
                    'message' => sprintf(
                        __('You have reached your message limit of %s for the day. Please try again tomorrow.', 'knowvault'),
                        $message_limit
                    ),
                    'usage' => $stats['message_count'],
                    'limit' => $message_limit,
                    'reset_time' => strtotime('tomorrow')
                ];
            }

            // User is within limits
            return true;
        } catch (\Exception $e) {
            // Log the error but don't block the user in case of database issues
            return true;
        }
    }

    /**
     * Get user's usage statistics for the past 24 hours
     *
     * @param int $user_id User ID (optional, for logged-in users)
     * @param string $guest_ip Hashed IP address (optional, for non-logged-in users)
     * @return array Usage statistics
     */
    public function get_user_usage_stats($user_id = null, $guest_ip = null) {
        global $wpdb;
        
        try {
            // Get timestamp for 24 hours ago
            $time_window = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            // Build query based on whether we're checking by user_id or guest_ip
            if ($user_id !== null) {
                // Query for logged-in users
                $query = $wpdb->prepare(
                    "SELECT m.* 
                    FROM {$wpdb->prefix}ai_botkit_messages AS m
                    JOIN {$wpdb->prefix}ai_botkit_conversations AS c ON m.conversation_id = c.id
                    WHERE c.user_id = %d 
                    AND m.created_at >= %s",
                    $user_id,
                    $time_window
                );
            } else if ($guest_ip !== null) {
                // Query for non-logged-in users using IP hash
                $query = $wpdb->prepare(
                    "SELECT m.* 
                    FROM {$wpdb->prefix}ai_botkit_messages AS m
                    JOIN {$wpdb->prefix}ai_botkit_conversations AS c ON m.conversation_id = c.id
                    WHERE c.guest_ip = %s 
                    AND m.created_at >= %s",
                    $guest_ip,
                    $time_window
                );
            } else {
                // No valid identification provided
                throw new \Exception('No user_id or guest_ip provided for rate limiting');
            }
            
            
            $messages = $wpdb->get_results($query);
            
            if ($wpdb->last_error) {
                throw new \Exception('Database query error: ' . $wpdb->last_error);
            }
            
            // Log the first message metadata for debugging
            if (!empty($messages) && isset($messages[0])) {
                //     'id' => $messages[0]->id,
                //     'conversation_id' => $messages[0]->conversation_id,
                //     'role' => $messages[0]->role,
                //     'metadata' => $messages[0]->metadata
                // ], true));
            }
            
            // Count messages and tokens
            $message_count = 0;
            $total_tokens = 0;
            
            foreach ($messages as $message) {
                // Only count user messages for message limit
                if ($message->role === 'user') {
                    $message_count++;
                }
                
                // Count tokens from all messages
                $metadata = json_decode($message->metadata ?? '', true) ?? [];
                if (isset($metadata['tokens'])) {
                    $total_tokens += (int) $metadata['tokens'];
                }
            }
            
            return [
                'message_count' => $message_count,
                'total_tokens' => $total_tokens,
                'time_window' => $time_window
            ];
        } catch (\Exception $e) {
            // Return safe defaults in case of error
            return [
                'message_count' => 0,
                'total_tokens' => 0,
                'time_window' => date('Y-m-d H:i:s', strtotime('-24 hours'))
            ];
        }
    }

    /**
     * Get user's remaining limits
     *
     * @param int $user_id User ID (optional, for logged-in users)
     * @return array Remaining limits
     */
    public function get_remaining_limits($user_id = null) {
        try {
            // Handle both logged-in and non-logged-in users
            if (is_user_logged_in() || $user_id !== null) {
                // For logged-in users, use user_id
                if (empty($user_id) && is_user_logged_in()) {
                    $user_id = get_current_user_id();
                }
                $stats = $this->get_user_usage_stats($user_id);
            } else {
                // For non-logged-in users, use IP address
                $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $ip_hash = hash('sha256', $user_ip);
                $stats = $this->get_user_usage_stats(null, $ip_hash);
            }
            
            $token_limit = $this->get_token_bucket_limit();
            $message_limit = $this->get_max_requests_per_day();
            
            return [
                'remaining_tokens' => max(0, $token_limit - $stats['total_tokens']),
                'remaining_messages' => max(0, $message_limit - $stats['message_count']),
                'usage' => $stats
            ];
        } catch (\Exception $e) {
            // Return safe defaults in case of error
            return [
                'remaining_tokens' => $this->get_token_bucket_limit(),
                'remaining_messages' => $this->get_max_requests_per_day(),
                'usage' => [
                    'message_count' => 0,
                    'total_tokens' => 0,
                    'time_window' => date('Y-m-d H:i:s', strtotime('-24 hours'))
                ]
            ];
        }
    }
    
    /**
     * Debug function to check database tables
     * 
     * @return array Database table information
     */
    public function debug_check_tables() {
        global $wpdb;
        
        $results = [];
        
        try {
            // Check conversations table
            $conversations_table = $wpdb->prefix . 'ai_botkit_conversations';
            $conversations_exists = $wpdb->get_var("SHOW TABLES LIKE '{$conversations_table}'") === $conversations_table;
            $results['conversations_table_exists'] = $conversations_exists;
            
            if ($conversations_exists) {
                $conversations_columns = $wpdb->get_results("DESCRIBE {$conversations_table}");
                $results['conversations_columns'] = array_map(function($col) {
                    return [
                        'field' => $col->Field,
                        'type' => $col->Type,
                        'key' => $col->Key
                    ];
                }, $conversations_columns);
                
                $conversations_count = $wpdb->get_var("SELECT COUNT(*) FROM {$conversations_table}");
                $results['conversations_count'] = $conversations_count;
                
                // Check for guest_ip column
                $has_guest_ip = false;
                foreach ($results['conversations_columns'] as $column) {
                    if ($column['field'] === 'guest_ip') {
                        $has_guest_ip = true;
                        break;
                    }
                }
                $results['has_guest_ip_column'] = $has_guest_ip;
                
                // Count conversations with guest_ip
                if ($has_guest_ip) {
                    $guest_ip_count = $wpdb->get_var("SELECT COUNT(*) FROM {$conversations_table} WHERE guest_ip IS NOT NULL");
                    $results['guest_ip_count'] = $guest_ip_count;
                }
            }
            
            // Check messages table
            $messages_table = $wpdb->prefix . 'ai_botkit_messages';
            $messages_exists = $wpdb->get_var("SHOW TABLES LIKE '{$messages_table}'") === $messages_table;
            $results['messages_table_exists'] = $messages_exists;
            
            if ($messages_exists) {
                $messages_columns = $wpdb->get_results("DESCRIBE {$messages_table}");
                $results['messages_columns'] = array_map(function($col) {
                    return [
                        'field' => $col->Field,
                        'type' => $col->Type,
                        'key' => $col->Key
                    ];
                }, $messages_columns);
                
                $messages_count = $wpdb->get_var("SELECT COUNT(*) FROM {$messages_table}");
                $results['messages_count'] = $messages_count;
                
                // Check sample message
                if ($messages_count > 0) {
                    $sample_message = $wpdb->get_row("SELECT * FROM {$messages_table} LIMIT 1");
                    $results['sample_message'] = [
                        'id' => $sample_message->id,
                        'conversation_id' => $sample_message->conversation_id,
                        'role' => $sample_message->role,
                        'metadata_type' => gettype($sample_message->metadata),
                        'metadata_sample' => substr($sample_message->metadata, 0, 100)
                    ];
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check limits for embeddings and other non-user-specific operations
     * This is used by the LLM_Client for operations that don't have a specific user context
     * 
     * @throws \Exception if rate limit is exceeded
     */
    public function check_limits() {
        // This is a simpler rate limiter for non-user operations
        // It's currently not implemented
    }
} 