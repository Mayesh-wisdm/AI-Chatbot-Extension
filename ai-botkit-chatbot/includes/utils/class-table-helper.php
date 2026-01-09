<?php
namespace AI_BotKit\Utils;

/**
 * Table Helper Class
 * 
 * Provides backward compatibility for table names.
 * Supports both old (ai_botkit) and new (knowvault) table prefixes.
 */
class Table_Helper {
    
    /**
     * Get the table prefix to use
     * 
     * @return string Table prefix (either 'ai_botkit_' or 'knowvault_')
     */
    public static function get_table_prefix(): string {
        global $wpdb;
        
        // Check if migration to new tables has been completed
        $migration_completed = get_option('knowvault_db_migration_completed', false);
        
        if ($migration_completed) {
            // Use new table prefix
            return $wpdb->prefix . 'knowvault_';
        }
        
        // Check if old tables exist
        $old_tables_exist = self::check_old_tables_exist();
        
        if ($old_tables_exist) {
            // Use old table prefix for backward compatibility
            return $wpdb->prefix . 'ai_botkit_';
        }
        
        // New installation - use new table prefix
        return $wpdb->prefix . 'knowvault_';
    }
    
    /**
     * Get table name with appropriate prefix
     * 
     * @param string $table_name Table name without prefix (e.g., 'documents', 'chatbots')
     * @return string Full table name with prefix
     */
    public static function get_table_name(string $table_name): string {
        return self::get_table_prefix() . $table_name;
    }
    
    /**
     * Check if old tables exist
     * 
     * @return bool True if old tables exist
     */
    public static function check_old_tables_exist(): bool {
        global $wpdb;
        
        $old_prefix = $wpdb->prefix . 'ai_botkit_';
        $required_tables = [
            'documents',
            'chatbots',
            'chunks',
            'embeddings',
            'conversations',
            'messages'
        ];
        
        foreach ($required_tables as $table) {
            $table_name = $old_prefix . $table;
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            
            if ($table_exists) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if new tables exist
     * 
     * @return bool True if new tables exist
     */
    public static function check_new_tables_exist(): bool {
        global $wpdb;
        
        $new_prefix = $wpdb->prefix . 'knowvault_';
        $required_tables = [
            'documents',
            'chatbots',
            'chunks',
            'embeddings',
            'conversations',
            'messages'
        ];
        
        foreach ($required_tables as $table) {
            $table_name = $new_prefix . $table;
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            
            if ($table_exists) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get old table name
     * 
     * @param string $table_name Table name without prefix
     * @return string Full old table name
     */
    public static function get_old_table_name(string $table_name): string {
        global $wpdb;
        return $wpdb->prefix . 'ai_botkit_' . $table_name;
    }
    
    /**
     * Get new table name
     * 
     * @param string $table_name Table name without prefix
     * @return string Full new table name
     */
    public static function get_new_table_name(string $table_name): string {
        global $wpdb;
        return $wpdb->prefix . 'knowvault_' . $table_name;
    }
}

