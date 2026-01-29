<?php
/**
 * PHPUnit Bootstrap for Phase 2 Unit Tests.
 *
 * Sets up the testing environment for AI BotKit Phase 2 features.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @since   2.0.0
 */

// Define test environment constants.
define( 'AI_BOTKIT_TESTING', true );
define( 'AI_BOTKIT_VERSION', '2.0.0' );
define( 'AI_BOTKIT_PLUGIN_DIR', dirname( dirname( dirname( __DIR__ ) ) ) . '/ai-botkit-chatbot/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'LEARNDASH_VERSION', '4.0.0' );

// Load Composer autoloader if available.
$autoload_file = dirname( dirname( dirname( __DIR__ ) ) ) . '/vendor/autoload.php';
if ( file_exists( $autoload_file ) ) {
    require_once $autoload_file;
}

// Load WordPress test stubs if available.
$wp_stubs = dirname( dirname( dirname( __DIR__ ) ) ) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if ( file_exists( $wp_stubs ) ) {
    require_once $wp_stubs;
}

// Initialize mock global variables.
global $wpdb, $wp_test_options, $wp_test_user, $wp_test_posts, $wp_test_transients;

$wp_test_options    = array();
$wp_test_user       = null;
$wp_test_posts      = array();
$wp_test_transients = array();

/**
 * Mock wpdb class for database operations.
 */
class MockWpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $users = 'wp_users';
    public $posts = 'wp_posts';

    private $mock_results = array();
    private $last_error = '';
    private $num_rows = 0;

    public function prepare( $query, ...$args ) {
        return vsprintf( str_replace( array( '%d', '%s', '%f' ), array( '%d', "'%s'", '%f' ), $query ), $args );
    }

    public function get_results( $query, $output = OBJECT ) {
        return $this->mock_results['results'] ?? array();
    }

    public function get_row( $query, $output = OBJECT, $y = 0 ) {
        $results = $this->get_results( $query, $output );
        return ! empty( $results ) ? $results[0] : null;
    }

    public function get_var( $query, $col_offset = 0, $row_offset = 0 ) {
        return $this->mock_results['var'] ?? null;
    }

    public function get_col( $query, $col_offset = 0 ) {
        return $this->mock_results['col'] ?? array();
    }

    public function insert( $table, $data, $format = null ) {
        $this->insert_id = rand( 1, 10000 );
        return 1;
    }

    public function update( $table, $data, $where, $format = null, $where_format = null ) {
        return 1;
    }

    public function delete( $table, $where, $where_format = null ) {
        return 1;
    }

    public function query( $query ) {
        return true;
    }

    public function esc_like( $text ) {
        return addcslashes( $text, '%_' );
    }

    public function set_mock_results( $results ) {
        $this->mock_results = $results;
    }

    public function get_charset_collate() {
        return 'utf8mb4_unicode_ci';
    }
}

$wpdb = new MockWpdb();

// Define OBJECT constant for wpdb if not defined.
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
    define( 'ARRAY_N', 'ARRAY_N' );
}

// Mock WordPress functions.
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        global $wp_test_options;
        return $wp_test_options[ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        global $wp_test_options;
        $wp_test_options[ $option ] = $value;
        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $option ) {
        global $wp_test_options;
        unset( $wp_test_options[ $option ] );
        return true;
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        global $wp_test_user;
        return $wp_test_user['ID'] ?? 0;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, ...$args ) {
        global $wp_test_user;
        $caps = $wp_test_user['capabilities'] ?? array();
        return in_array( $capability, $caps, true ) || in_array( 'manage_options', $caps, true );
    }
}

if ( ! function_exists( 'user_can' ) ) {
    function user_can( $user_id, $capability, ...$args ) {
        global $wp_test_user;
        if ( $wp_test_user['ID'] ?? 0 === $user_id ) {
            return current_user_can( $capability, ...$args );
        }
        return false;
    }
}

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $field, $value ) {
        global $wp_test_user;
        if ( $field === 'id' && ( $wp_test_user['ID'] ?? 0 ) == $value ) {
            return (object) $wp_test_user;
        }
        return false;
    }
}

if ( ! function_exists( 'wp_insert_user' ) ) {
    function wp_insert_user( $userdata ) {
        global $wp_test_user;
        $user_id = rand( 1, 10000 );
        $wp_test_user = array_merge( array( 'ID' => $user_id ), $userdata );
        return $user_id;
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
        global $wp_test_posts;
        $post_id = rand( 1, 10000 );
        $wp_test_posts[ $post_id ] = array_merge( array( 'ID' => $post_id ), $postarr );
        return $post_id;
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id, $output = OBJECT, $filter = 'raw' ) {
        global $wp_test_posts;
        if ( isset( $wp_test_posts[ $post_id ] ) ) {
            return (object) $wp_test_posts[ $post_id ];
        }
        return null;
    }
}

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = array() ) {
        global $wp_test_posts;
        return array_values( array_map( function( $p ) { return (object) $p; }, $wp_test_posts ) );
    }
}

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = array() ) {
        if ( is_object( $args ) ) {
            $args = get_object_vars( $args );
        } elseif ( is_string( $args ) ) {
            parse_str( $args, $args );
        }
        return array_merge( $defaults, $args );
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) {
        if ( $type === 'mysql' ) {
            return gmdate( 'Y-m-d H:i:s' );
        }
        if ( $type === 'timestamp' ) {
            return time();
        }
        return time();
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return strip_tags( trim( $str ) );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
    }
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
    function sanitize_file_name( $filename ) {
        return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        return preg_replace( '/[^a-z0-9-]/', '', strtolower( str_replace( ' ', '-', $title ) ) );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string, $remove_breaks = false ) {
        return strip_tags( $string );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $string ) {
        return $string;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) {
        return filter_var( $url, FILTER_SANITIZE_URL );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = 'default' ) {
        return $number === 1 ? $single : $plural;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook_name, $value, ...$args ) {
        return $value;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( $hook_name, ...$args ) {
        // No-op for tests.
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
        return array(
            'path'    => sys_get_temp_dir() . '/wp-uploads',
            'url'     => 'http://example.com/wp-content/uploads',
            'subdir'  => '',
            'basedir' => sys_get_temp_dir() . '/wp-uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error'   => false,
        );
    }
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
    function wp_mkdir_p( $target ) {
        return is_dir( $target ) || mkdir( $target, 0755, true );
    }
}

if ( ! function_exists( 'wp_delete_file' ) ) {
    function wp_delete_file( $file ) {
        return @unlink( $file );
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post = 0, $leavename = false ) {
        return 'http://example.com/post/' . ( is_object( $post ) ? $post->ID : $post );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) {
        return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        $info = array(
            'name' => 'Test Site',
            'url'  => 'http://example.com',
        );
        return $info[ $show ] ?? '';
    }
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ) {
        return md5( $action . time() );
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
        return true;
    }
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
    function wp_generate_uuid4() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff )
        );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
    function wp_http_validate_url( $url ) {
        return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        return array(
            'headers'  => array(),
            'body'     => '',
            'response' => array( 'code' => 200 ),
        );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return $response['response']['code'] ?? 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return $response['body'] ?? '';
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $transient, $value, $expiration = 0 ) {
        global $wp_test_transients;
        $wp_test_transients[ $transient ] = $value;
        return true;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $transient ) {
        global $wp_test_transients;
        return $wp_test_transients[ $transient ] ?? false;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $transient ) {
        global $wp_test_transients;
        unset( $wp_test_transients[ $transient ] );
        return true;
    }
}

if ( ! function_exists( 'size_format' ) ) {
    function size_format( $bytes, $decimals = 0 ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        $bytes = max( $bytes, 0 );
        $pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow   = min( $pow, count( $units ) - 1 );
        $bytes /= pow( 1024, $pow );
        return round( $bytes, $decimals ) . ' ' . $units[ $pow ];
    }
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
    function wp_get_post_terms( $post_id, $taxonomy = 'post_tag', $args = array() ) {
        return array();
    }
}

if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args = array() ) {
        return array();
    }
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
    function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail', $icon = false ) {
        return 'http://example.com/image.jpg';
    }
}

if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
    function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
        return 'http://example.com/thumbnail.jpg';
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        return $single ? '' : array();
    }
}

if ( ! function_exists( 'wp_date' ) ) {
    function wp_date( $format, $timestamp = null, $timezone = null ) {
        return gmdate( $format, $timestamp ?? time() );
    }
}

if ( ! function_exists( 'human_time_diff' ) ) {
    function human_time_diff( $from, $to = 0 ) {
        $diff = abs( ( $to ?: time() ) - $from );
        if ( $diff < 60 ) {
            return $diff . ' secs';
        }
        if ( $diff < 3600 ) {
            return round( $diff / 60 ) . ' mins';
        }
        if ( $diff < 86400 ) {
            return round( $diff / 3600 ) . ' hours';
        }
        return round( $diff / 86400 ) . ' days';
    }
}

if ( ! function_exists( 'get_theme_mod' ) ) {
    function get_theme_mod( $name, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'get_site_icon_url' ) ) {
    function get_site_icon_url( $size = 512, $url = '', $blog_id = 0 ) {
        return $url ?: 'http://example.com/favicon.png';
    }
}

if ( ! function_exists( 'wp_get_image_editor' ) ) {
    function wp_get_image_editor( $path, $args = array() ) {
        return new WP_Error( 'image_no_editor', 'No editor available' );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( ...$args ) {
        if ( count( $args ) === 3 ) {
            $key   = $args[0];
            $value = $args[1];
            $url   = $args[2];
            return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $key . '=' . urlencode( $value );
        } elseif ( count( $args ) === 2 && is_array( $args[0] ) ) {
            $url   = $args[1];
            $query = http_build_query( $args[0] );
            return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $query;
        }
        return $args[0] ?? '';
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url, $component = -1 ) {
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
    function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
        return true;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array(), $wp_error = false ) {
        return true;
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook, $args = array() ) {
        return false;
    }
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
    function wp_unschedule_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
        return true;
    }
}

if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
        return true;
    }
}

if ( ! function_exists( 'is_ssl' ) ) {
    function is_ssl() {
        return false;
    }
}

if ( ! function_exists( 'is_singular' ) ) {
    function is_singular( $post_types = '' ) {
        return false;
    }
}

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() {
        return 1;
    }
}

if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( $post = null ) {
        return 'post';
    }
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
        return true;
    }
}

if ( ! function_exists( 'wp_localize_script' ) ) {
    function wp_localize_script( $handle, $object_name, $l10n ) {
        return true;
    }
}

if ( ! function_exists( 'plugins_url' ) ) {
    function plugins_url( $path = '', $plugin = '' ) {
        return 'http://example.com/wp-content/plugins/' . ltrim( $path, '/' );
    }
}

// Mock WP_Error class.
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( ! empty( $code ) ) {
                $this->errors[ $code ][] = $message;
                if ( ! empty( $data ) ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return ! empty( $codes ) ? $codes[0] : '';
        }

        public function get_error_message( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->errors[ $code ][0] ?? '';
        }

        public function get_error_data( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->error_data[ $code ] ?? null;
        }

        public function get_error_codes() {
            return array_keys( $this->errors );
        }

        public function get_error_messages( $code = '' ) {
            if ( empty( $code ) ) {
                $all_messages = array();
                foreach ( $this->errors as $messages ) {
                    $all_messages = array_merge( $all_messages, $messages );
                }
                return $all_messages;
            }
            return $this->errors[ $code ] ?? array();
        }
    }
}

// Mock WP_Query class.
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = array();
        public $post_count = 0;
        public $found_posts = 0;
        public $max_num_pages = 1;

        public function __construct( $query = '' ) {
            if ( ! empty( $query ) ) {
                $this->query( $query );
            }
        }

        public function query( $query ) {
            $this->posts = get_posts( $query );
            $this->post_count = count( $this->posts );
            $this->found_posts = $this->post_count;
            return $this->posts;
        }
    }
}

/**
 * Base WP_UnitTestCase class for tests to extend.
 */
class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Reset global state.
        global $wp_test_options, $wp_test_user, $wp_test_posts, $wp_test_transients;
        $wp_test_options    = array();
        $wp_test_user       = null;
        $wp_test_posts      = array();
        $wp_test_transients = array();
    }

    /**
     * Tear down test environment.
     */
    protected function tearDown(): void {
        // Reset global state.
        global $wp_test_options, $wp_test_user, $wp_test_posts, $wp_test_transients, $wpdb;
        $wp_test_options    = array();
        $wp_test_user       = null;
        $wp_test_posts      = array();
        $wp_test_transients = array();
        $wpdb->set_mock_results( array() );

        parent::tearDown();
    }

    /**
     * Set up a test user.
     *
     * @param array $args User arguments.
     * @return int User ID.
     */
    protected function set_current_user( array $args = array() ): int {
        global $wp_test_user;

        $defaults = array(
            'ID'           => rand( 1, 10000 ),
            'user_login'   => 'testuser',
            'user_email'   => 'test@example.com',
            'display_name' => 'Test User',
            'capabilities' => array(),
        );

        $wp_test_user = wp_parse_args( $args, $defaults );

        return $wp_test_user['ID'];
    }

    /**
     * Create a test post.
     *
     * @param array $args Post arguments.
     * @return int Post ID.
     */
    protected function create_test_post( array $args = array() ): int {
        $defaults = array(
            'post_title'   => 'Test Post',
            'post_content' => 'Test content.',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        );

        return wp_insert_post( wp_parse_args( $args, $defaults ) );
    }

    /**
     * Mock database results.
     *
     * @param array $results Results to mock.
     */
    protected function mock_db_results( array $results ): void {
        global $wpdb;
        $wpdb->set_mock_results( $results );
    }

    /**
     * Assert that a value is a WP_Error.
     *
     * @param mixed  $actual  The value to check.
     * @param string $message Optional message.
     */
    protected function assertWPError( $actual, string $message = '' ): void {
        $this->assertTrue( is_wp_error( $actual ), $message ?: 'Expected WP_Error instance' );
    }

    /**
     * Assert that a value is NOT a WP_Error.
     *
     * @param mixed  $actual  The value to check.
     * @param string $message Optional message.
     */
    protected function assertNotWPError( $actual, string $message = '' ): void {
        $this->assertFalse( is_wp_error( $actual ), $message ?: 'Expected value to not be a WP_Error' );
    }
}

// Load feature classes for testing.
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-chat-history-handler.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-search-handler.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-media-handler.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-template-manager.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-export-handler.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-recommendation-engine.php';
require_once AI_BOTKIT_PLUGIN_DIR . 'includes/features/class-browsing-tracker.php';
