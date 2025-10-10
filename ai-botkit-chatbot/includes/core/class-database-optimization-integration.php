<?php
namespace AI_BotKit\Core;

/**
 * Database Optimization Integration Class
 * 
 * Integrates database optimization with WordPress hooks and activation
 */
class Database_Optimization_Integration {
    
    /**
     * Initialize the integration
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin activation hook
        add_action('ai_botkit_plugin_activated', [$this, 'run_database_optimization']);
        
        // Admin init hook for manual optimization
        add_action('admin_init', [$this, 'check_database_optimization']);
        
        // AJAX hook for manual optimization
        add_action('wp_ajax_ai_botkit_optimize_database', [$this, 'handle_ajax_optimization']);
    }
    
    /**
     * Run database optimization on plugin activation
     */
    public function run_database_optimization() {
        $migration = new Database_Migration();
        
        if ($migration->is_migration_needed()) {
            $result = $migration->run_migrations();
            
            if ($result['success']) {
            } else {
            }
        }
    }
    
    /**
     * Check database optimization status
     */
    public function check_database_optimization() {
        // Only run for admin users
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $migration = new Database_Migration();
        
        if ($migration->is_migration_needed()) {
            // Add admin notice
            add_action('admin_notices', [$this, 'show_optimization_notice']);
        }
    }
    
    /**
     * Show optimization notice
     */
    public function show_optimization_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>AI BotKit:</strong> Database optimization is available. 
                <a href="#" id="ai-botkit-optimize-database" class="button button-primary">Optimize Database</a>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ai-botkit-optimize-database').on('click', function(e) {
                e.preventDefault();
                
                $(this).prop('disabled', true).text('Optimizing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_botkit_optimize_database',
                        nonce: '<?php echo wp_create_nonce('ai_botkit_optimize_database'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Database optimization completed successfully!');
                            location.reload();
                        } else {
                            alert('Database optimization failed: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Database optimization failed due to an error.');
                    },
                    complete: function() {
                        $('#ai-botkit-optimize-database').prop('disabled', false).text('Optimize Database');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX optimization request
     */
    public function handle_ajax_optimization() {
        check_ajax_referer('ai_botkit_optimize_database', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        try {
            $migration = new Database_Migration();
            $result = $migration->run_migrations();
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Database optimization completed successfully',
                    'migrations_run' => $result['migrations_run'],
                    'version' => $result['version']
                ]);
            } else {
                wp_send_json_error([
                    'message' => 'Database optimization failed',
                    'errors' => $result['errors']
                ]);
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get optimization status
     * 
     * @return array Optimization status
     */
    public function get_optimization_status() {
        $migration = new Database_Migration();
        $optimizer = new Database_Optimizer();
        
        return [
            'migration_status' => $migration->get_migration_status(),
            'index_status' => $optimizer->check_index_status(),
            'performance_metrics' => $optimizer->get_performance_metrics()
        ];
    }
    
    /**
     * Run performance benchmark
     * 
     * @return array Benchmark results
     */
    public function run_performance_benchmark() {
        $optimizer = new WordPress_Function_Optimizer();
        
        return [
            'performance_metrics' => $optimizer->get_performance_metrics(),
            'benchmark_results' => $optimizer->benchmark_functions(['post', 'page'], 5)
        ];
    }
}
