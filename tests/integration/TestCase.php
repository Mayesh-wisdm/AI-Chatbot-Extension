<?php
/**
 * Base Test Case for Phase 2 Integration Tests
 *
 * Provides common setup and utility methods for all integration tests.
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

namespace AI_BotKit\Tests\Integration;

use WP_UnitTestCase;

/**
 * TestCase base class.
 *
 * Extended by all Phase 2 integration test classes.
 *
 * @since 2.0.0
 */
abstract class TestCase extends WP_UnitTestCase {

    /**
     * Database object.
     *
     * @var \wpdb
     */
    protected $db;

    /**
     * Table prefix for AI BotKit tables.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->db     = $wpdb;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';
    }

    /**
     * Create a test user with specified role.
     *
     * @param string $role User role.
     * @return int User ID.
     */
    protected function create_test_user( string $role = 'subscriber' ): int {
        return $this->factory->user->create( array(
            'role'       => $role,
            'user_login' => 'testuser_' . uniqid(),
            'user_email' => 'testuser_' . uniqid() . '@example.com',
        ) );
    }

    /**
     * Create a test chatbot.
     *
     * @param array $data Optional data overrides.
     * @return int Chatbot ID.
     */
    protected function create_test_chatbot( array $data = array() ): int {
        $defaults = array(
            'name'       => 'Test Chatbot ' . uniqid(),
            'active'     => 1,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $data = array_merge( $defaults, $data );

        $this->db->insert(
            $this->prefix . 'chatbots',
            $data,
            array( '%s', '%d', '%s', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Create a test conversation.
     *
     * @param int   $chatbot_id Chatbot ID.
     * @param int   $user_id    User ID.
     * @param array $data       Optional data overrides.
     * @return int Conversation ID.
     */
    protected function create_test_conversation( int $chatbot_id, int $user_id, array $data = array() ): int {
        $defaults = array(
            'chatbot_id'  => $chatbot_id,
            'user_id'     => $user_id,
            'session_id'  => 'sess_' . uniqid(),
            'is_archived' => 0,
            'is_favorite' => 0,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        );

        $data = array_merge( $defaults, $data );

        $this->db->insert(
            $this->prefix . 'conversations',
            $data,
            array( '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Create a test message.
     *
     * @param int    $conversation_id Conversation ID.
     * @param string $role            Message role (user/assistant).
     * @param string $content         Message content.
     * @return int Message ID.
     */
    protected function create_test_message( int $conversation_id, string $role, string $content ): int {
        $this->db->insert(
            $this->prefix . 'messages',
            array(
                'conversation_id' => $conversation_id,
                'role'            => $role,
                'content'         => $content,
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Create a test template.
     *
     * @param array $data Optional data overrides.
     * @return int Template ID.
     */
    protected function create_test_template( array $data = array() ): int {
        $defaults = array(
            'name'        => 'Test Template ' . uniqid(),
            'description' => 'Test template description',
            'category'    => 'general',
            'style'       => wp_json_encode( array(
                'primaryColor' => '#1E3A8A',
            ) ),
            'is_system'  => 0,
            'is_active'  => 1,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $data = array_merge( $defaults, $data );

        $this->db->insert(
            $this->prefix . 'templates',
            $data,
            array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Create a test media record.
     *
     * @param int   $user_id User ID.
     * @param array $data    Optional data overrides.
     * @return int Media ID.
     */
    protected function create_test_media( int $user_id, array $data = array() ): int {
        $defaults = array(
            'user_id'    => $user_id,
            'media_type' => 'image',
            'file_name'  => 'test_' . uniqid() . '.jpg',
            'file_path'  => '/path/to/test_' . uniqid() . '.jpg',
            'file_url'   => 'https://example.com/test_' . uniqid() . '.jpg',
            'mime_type'  => 'image/jpeg',
            'file_size'  => 100000,
            'status'     => 'active',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $data = array_merge( $defaults, $data );

        $this->db->insert(
            $this->prefix . 'media',
            $data,
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Create a test user interaction.
     *
     * @param int    $user_id User ID.
     * @param string $type    Interaction type.
     * @param string $item_type Item type.
     * @param int    $item_id Item ID.
     * @return int Interaction ID.
     */
    protected function create_test_interaction( int $user_id, string $type, string $item_type, int $item_id ): int {
        $this->db->insert(
            $this->prefix . 'user_interactions',
            array(
                'user_id'          => $user_id,
                'session_id'       => 'sess_' . uniqid(),
                'interaction_type' => $type,
                'item_type'        => $item_type,
                'item_id'          => $item_id,
                'created_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        return $this->db->insert_id;
    }

    /**
     * Assert that a database table exists.
     *
     * @param string $table_name Table name (without prefix).
     */
    protected function assertTableExists( string $table_name ): void {
        $full_table_name = $this->prefix . $table_name;
        $exists          = $this->db->get_var(
            $this->db->prepare( 'SHOW TABLES LIKE %s', $full_table_name )
        );

        $this->assertNotNull( $exists, "Table {$full_table_name} should exist" );
    }

    /**
     * Assert that a column exists in a table.
     *
     * @param string $table_name  Table name (without prefix).
     * @param string $column_name Column name.
     */
    protected function assertColumnExists( string $table_name, string $column_name ): void {
        $full_table_name = $this->prefix . $table_name;

        $column = $this->db->get_var(
            $this->db->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $full_table_name,
                $column_name
            )
        );

        $this->assertNotNull( $column, "Column {$column_name} should exist in table {$full_table_name}" );
    }

    /**
     * Assert that an index exists on a table.
     *
     * @param string $table_name Table name (without prefix).
     * @param string $index_name Index name.
     */
    protected function assertIndexExists( string $table_name, string $index_name ): void {
        $full_table_name = $this->prefix . $table_name;

        $index = $this->db->get_var(
            $this->db->prepare(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME,
                $full_table_name,
                $index_name
            )
        );

        $this->assertNotNull( $index, "Index {$index_name} should exist on table {$full_table_name}" );
    }

    /**
     * Create a mock nonce for testing.
     *
     * @param string $action Nonce action.
     * @return string Nonce value.
     */
    protected function create_nonce( string $action ): string {
        return wp_create_nonce( $action );
    }

    /**
     * Set up a mock AJAX request.
     *
     * @param string $action AJAX action.
     * @param array  $data   POST data.
     */
    protected function setup_ajax_request( string $action, array $data = array() ): void {
        $_POST            = $data;
        $_POST['action']  = $action;
        $_REQUEST         = $_POST;
    }

    /**
     * Clean up AJAX request.
     */
    protected function cleanup_ajax_request(): void {
        $_POST    = array();
        $_REQUEST = array();
    }
}
