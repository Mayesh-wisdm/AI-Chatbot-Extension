<?php
namespace AI_BotKit\Utils;

/**
 * Migration Logger
 * 
 * Handles logging for migration operations with timestamped log files
 */
class Migration_Logger {
    /**
     * Log directory path
     */
    private $log_dir;

    /**
     * Current log file path
     */
    private $log_file;

    /**
     * Log file handle
     */
    private $file_handle;

    /**
     * Migration direction
     */
    private $direction;

    /**
     * Initialize the logger
     * 
     * @param string $direction Migration direction (to_pinecone or to_local)
     */
    public function __construct($direction = 'to_pinecone') {
        $this->direction = $direction;
        $this->log_dir = WP_CONTENT_DIR . '/ai-botkit-logs';
        $this->ensure_log_directory();
        $this->create_log_file();
    }

    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Add .htaccess to protect log files
            $htaccess_file = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, 'Deny from all');
            }
            
            // Add index.php to prevent directory listing
            $index_file = $this->log_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }
    }

    /**
     * Create log file with timestamp
     */
    private function create_log_file() {
        // Format: migration-pine-to-local-10-oct-2025-20-58.log
        $direction_name = $this->direction === 'to_pinecone' ? 'local-to-pine' : 'pine-to-local';
        $timestamp = current_time('d-M-Y-H-i');
        $filename = sprintf('migration-%s-%s.log', $direction_name, strtolower($timestamp));
        
        $this->log_file = $this->log_dir . '/' . $filename;
        $this->file_handle = fopen($this->log_file, 'a');
        
        if ($this->file_handle) {
            $this->write_header();
        }
    }

    /**
     * Write log file header
     */
    private function write_header() {
        $header = sprintf(
            "=================================================================\n" .
            "AI BotKit Migration Log\n" .
            "Direction: %s\n" .
            "Started: %s\n" .
            "=================================================================\n\n",
            $this->direction === 'to_pinecone' ? 'Local to Pinecone' : 'Pinecone to Local',
            current_time('Y-m-d H:i:s')
        );
        
        fwrite($this->file_handle, $header);
    }

    /**
     * Write log entry
     * 
     * @param string $level Log level (INFO, WARNING, ERROR, SUCCESS)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function log($level, $message, $context = []) {
        if (!$this->file_handle) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] [%s] %s", $timestamp, $level, $message);
        
        if (!empty($context)) {
            $log_entry .= "\n  Context: " . wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        
        $log_entry .= "\n";
        fwrite($this->file_handle, $log_entry);
        
        // Flush to ensure data is written immediately
        fflush($this->file_handle);
    }

    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log success message
     */
    public function success($message, $context = []) {
        $this->log('SUCCESS', $message, $context);
    }

    /**
     * Write summary at the end of migration
     */
    public function write_summary($migrated_count, $error_count, $duration = null) {
        if (!$this->file_handle) {
            return;
        }

        $summary = sprintf(
            "\n=================================================================\n" .
            "Migration Summary\n" .
            "=================================================================\n" .
            "Items Migrated: %d\n" .
            "Errors: %d\n" .
            "Status: %s\n",
            $migrated_count,
            $error_count,
            $error_count === 0 ? 'SUCCESS' : ($migrated_count > 0 ? 'PARTIAL SUCCESS' : 'FAILED')
        );

        if ($duration !== null) {
            $summary .= sprintf("Duration: %s\n", $duration);
        }

        $summary .= sprintf(
            "Completed: %s\n" .
            "=================================================================\n",
            current_time('Y-m-d H:i:s')
        );

        fwrite($this->file_handle, $summary);
        fflush($this->file_handle);
    }

    /**
     * Get the log file path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }

    /**
     * Get the log file URL for download
     */
    public function get_log_file_url() {
        if (!$this->log_file) {
            return '';
        }
        
        // Return relative path from wp-content
        $relative_path = str_replace(WP_CONTENT_DIR, '', $this->log_file);
        return basename($this->log_file);
    }

    /**
     * Close the log file
     */
    public function close() {
        if ($this->file_handle) {
            fclose($this->file_handle);
            $this->file_handle = null;
        }
    }

    /**
     * Destructor - ensure file is closed
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Get all migration log files
     */
    public static function get_all_logs() {
        $log_dir = WP_CONTENT_DIR . '/ai-botkit-logs';
        
        if (!file_exists($log_dir)) {
            return [];
        }

        $log_files = glob($log_dir . '/migration-*.log');
        
        if (empty($log_files)) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $logs = [];
        foreach ($log_files as $file) {
            $logs[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }

        return $logs;
    }
}

