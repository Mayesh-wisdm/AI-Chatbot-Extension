<?php
/**
 * PHPUnit Bootstrap for Phase 2 Integration Tests
 *
 * This file sets up the WordPress test environment for integration testing.
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

// Define test mode.
define( 'AI_BOTKIT_TESTING', true );

// Attempt to find WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward compatibility for PHPUnit 9.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Load plugin.
    $plugin_dir = dirname( dirname( dirname( __FILE__ ) ) );

    // Check for main plugin file.
    $plugin_files = array(
        $plugin_dir . '/ai-botkit-chatbot/ai-botkit-chatbot.php',
        $plugin_dir . '/ai-botkit-chatbot.php',
    );

    foreach ( $plugin_files as $plugin_file ) {
        if ( file_exists( $plugin_file ) ) {
            require $plugin_file;
            break;
        }
    }
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Set up test options.
 */
tests_add_filter( 'pre_option_ai_botkit_recommendation_enabled', function () {
    return true;
} );

tests_add_filter( 'pre_option_ai_botkit_recommendation_limit', function () {
    return 5;
} );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Load test base classes.
require_once dirname( __FILE__ ) . '/TestCase.php';

echo 'AI BotKit Phase 2 Integration Tests Initialized' . PHP_EOL;
