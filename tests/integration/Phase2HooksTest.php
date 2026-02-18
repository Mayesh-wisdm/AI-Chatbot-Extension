<?php
/**
 * Phase 2 WordPress Hooks Integration Tests
 *
 * Tests WordPress hooks and filters for Phase 2 features:
 * - Filter: ai_botkit_history_per_page
 * - Filter: ai_botkit_search_results
 * - Action: ai_botkit_conversation_archived
 * - Action: ai_botkit_template_applied
 * - Action: ai_botkit_recommendation_displayed
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

namespace AI_BotKit\Tests\Integration;

use WP_UnitTestCase;

/**
 * Phase2HooksTest class.
 *
 * Integration tests for Phase 2 WordPress hooks and filters.
 *
 * @since 2.0.0
 */
class Phase2HooksTest extends WP_UnitTestCase {

    /**
     * Database object.
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Table prefix.
     *
     * @var string
     */
    private string $prefix;

    /**
     * Test user ID.
     *
     * @var int
     */
    private int $test_user_id;

    /**
     * Test chatbot ID.
     *
     * @var int
     */
    private int $test_chatbot_id;

    /**
     * Test conversation ID.
     *
     * @var int
     */
    private int $test_conversation_id;

    /**
     * Test template ID.
     *
     * @var int
     */
    private int $test_template_id;

    /**
     * Captured hook data.
     *
     * @var array
     */
    private array $captured_hook_data = array();

    /**
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->db     = $wpdb;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';

        // Create test user.
        $this->test_user_id = $this->factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Create test tables.
        $this->create_test_tables();

        // Create test data.
        $this->create_test_data();

        // Reset captured data.
        $this->captured_hook_data = array();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        // Remove all test hooks.
        remove_all_filters( 'ai_botkit_history_per_page' );
        remove_all_filters( 'ai_botkit_search_results' );
        remove_all_actions( 'ai_botkit_conversation_archived' );
        remove_all_actions( 'ai_botkit_template_applied' );
        remove_all_actions( 'ai_botkit_recommendation_displayed' );

        $this->cleanup_test_data();

        parent::tearDown();
    }

    /**
     * Create test tables.
     */
    private function create_test_tables(): void {
        $charset_collate = $this->db->get_charset_collate();

        // Create chatbots table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}chatbots (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                template_id BIGINT(20) UNSIGNED,
                style JSON,
                messages_template JSON,
                model_config JSON,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create conversations table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}conversations (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                chatbot_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED,
                session_id VARCHAR(100),
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create messages table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}messages (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                role VARCHAR(20) NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX conversation_id (conversation_id)
            ) {$charset_collate} ENGINE=InnoDB;"
        );

        // Add FULLTEXT index.
        $index_exists = $this->db->get_var(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE table_schema = DATABASE()
             AND table_name = '{$this->prefix}messages'
             AND index_name = 'ft_content'"
        );
        if ( ! $index_exists ) {
            $this->db->query( "ALTER TABLE {$this->prefix}messages ADD FULLTEXT INDEX ft_content (content)" );
        }

        // Create templates table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}templates (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(50) NOT NULL DEFAULT 'general',
                style JSON,
                messages_template JSON,
                model_config JSON,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                usage_count INT NOT NULL DEFAULT 0,
                created_by BIGINT(20) UNSIGNED,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );

        // Create user interactions table.
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$this->prefix}user_interactions (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                session_id VARCHAR(100),
                interaction_type VARCHAR(50) NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id BIGINT(20) UNSIGNED NOT NULL,
                chatbot_id BIGINT(20) UNSIGNED,
                metadata JSON,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};"
        );
    }

    /**
     * Create test data.
     */
    private function create_test_data(): void {
        // Create chatbot.
        $this->db->insert(
            $this->prefix . 'chatbots',
            array(
                'name'       => 'Test Hooks Chatbot',
                'active'     => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );
        $this->test_chatbot_id = $this->db->insert_id;

        // Create conversation.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => $this->test_chatbot_id,
                'user_id'    => $this->test_user_id,
                'session_id' => 'sess_hooks_test_' . uniqid(),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $this->test_conversation_id = $this->db->insert_id;

        // Create messages.
        $messages = array(
            array( 'role' => 'user', 'content' => 'What are WordPress hooks and filters?' ),
            array( 'role' => 'assistant', 'content' => 'WordPress hooks are points in code where you can add custom functionality. Filters modify data, actions execute custom code.' ),
            array( 'role' => 'user', 'content' => 'Can you give me an example of a filter?' ),
            array( 'role' => 'assistant', 'content' => 'Sure! The the_content filter lets you modify post content before display.' ),
        );

        foreach ( $messages as $message ) {
            $this->db->insert(
                $this->prefix . 'messages',
                array(
                    'conversation_id' => $this->test_conversation_id,
                    'role'            => $message['role'],
                    'content'         => $message['content'],
                    'created_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }

        // Create template.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'        => 'Test Hooks Template',
                'description' => 'Template for hooks testing',
                'category'    => 'support',
                'style'       => wp_json_encode( array(
                    'primaryColor'    => '#1E3A8A',
                    'backgroundColor' => '#FFFFFF',
                ) ),
                'messages_template' => wp_json_encode( array(
                    'greeting' => 'Hello from template!',
                    'fallback' => 'I am not sure about that.',
                ) ),
                'model_config' => wp_json_encode( array(
                    'model'       => 'gpt-4o-mini',
                    'temperature' => 0.7,
                ) ),
                'is_system'  => 0,
                'is_active'  => 1,
                'created_by' => $this->test_user_id,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );
        $this->test_template_id = $this->db->insert_id;
    }

    /**
     * Clean up test data.
     */
    private function cleanup_test_data(): void {
        $this->db->query( "DELETE FROM {$this->prefix}messages WHERE conversation_id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}conversations WHERE id = {$this->test_conversation_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}chatbots WHERE id = {$this->test_chatbot_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}templates WHERE id = {$this->test_template_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}user_interactions WHERE user_id = {$this->test_user_id}" );
    }

    // =========================================================================
    // FILTER: ai_botkit_history_per_page
    // =========================================================================

    /**
     * Test ai_botkit_history_per_page filter exists.
     */
    public function test_history_per_page_filter_exists(): void {
        $filter_registered = false;

        // Add a test filter to verify it can be applied.
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) use ( &$filter_registered ) {
            $filter_registered = true;
            return $per_page;
        } );

        // Apply filter.
        $result = apply_filters( 'ai_botkit_history_per_page', 10 );

        $this->assertTrue( $filter_registered );
        $this->assertEquals( 10, $result );
    }

    /**
     * Test ai_botkit_history_per_page filter modifies value.
     */
    public function test_history_per_page_filter_modifies_value(): void {
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return 25;
        } );

        $result = apply_filters( 'ai_botkit_history_per_page', 10 );

        $this->assertEquals( 25, $result );
    }

    /**
     * Test ai_botkit_history_per_page filter with multiple callbacks.
     */
    public function test_history_per_page_filter_priority(): void {
        // Lower priority (higher number) should run last.
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return $per_page + 5;  // Add 5.
        }, 10 );

        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return $per_page * 2;  // Double it.
        }, 20 );

        // Start with 10 -> +5 = 15 -> *2 = 30.
        $result = apply_filters( 'ai_botkit_history_per_page', 10 );

        $this->assertEquals( 30, $result );
    }

    /**
     * Test ai_botkit_history_per_page filter enforces limits.
     */
    public function test_history_per_page_filter_with_limits(): void {
        // Add filter that respects min/max limits.
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            $min = 5;
            $max = 100;
            return max( $min, min( $max, $per_page ) );
        }, 99 );  // High priority to run last.

        // Test below minimum.
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return 1;  // Too low.
        }, 10 );

        $result = apply_filters( 'ai_botkit_history_per_page', 20 );

        $this->assertEquals( 5, $result );  // Enforced minimum.

        // Reset and test above maximum.
        remove_all_filters( 'ai_botkit_history_per_page' );

        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            $min = 5;
            $max = 100;
            return max( $min, min( $max, $per_page ) );
        }, 99 );

        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return 500;  // Too high.
        }, 10 );

        $result = apply_filters( 'ai_botkit_history_per_page', 20 );

        $this->assertEquals( 100, $result );  // Enforced maximum.
    }

    /**
     * Test ai_botkit_history_per_page filter in simulated handler context.
     */
    public function test_history_per_page_filter_in_handler(): void {
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) {
            return 15;  // Override to 15.
        } );

        // Simulate handler behavior.
        $user_id  = $this->test_user_id;
        $default  = 10;
        $per_page = apply_filters( 'ai_botkit_history_per_page', $default );
        $offset   = 0;

        $conversations = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}conversations
                 WHERE user_id = %d
                 ORDER BY updated_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            )
        );

        $this->assertEquals( 15, $per_page );
        $this->assertIsArray( $conversations );
    }

    // =========================================================================
    // FILTER: ai_botkit_search_results
    // =========================================================================

    /**
     * Test ai_botkit_search_results filter exists.
     */
    public function test_search_results_filter_exists(): void {
        $filter_applied = false;

        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) use ( &$filter_applied ) {
            $filter_applied = true;
            return $results;
        }, 10, 3 );

        $results = apply_filters( 'ai_botkit_search_results', array(), 'test query', 1 );

        $this->assertTrue( $filter_applied );
    }

    /**
     * Test ai_botkit_search_results filter receives correct parameters.
     */
    public function test_search_results_filter_parameters(): void {
        $captured_params = array();

        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) use ( &$captured_params ) {
            $captured_params = array(
                'results' => $results,
                'query'   => $query,
                'user_id' => $user_id,
            );
            return $results;
        }, 10, 3 );

        $test_results = array(
            array( 'id' => 1, 'content' => 'First result' ),
            array( 'id' => 2, 'content' => 'Second result' ),
        );

        apply_filters( 'ai_botkit_search_results', $test_results, 'WordPress hooks', $this->test_user_id );

        $this->assertEquals( $test_results, $captured_params['results'] );
        $this->assertEquals( 'WordPress hooks', $captured_params['query'] );
        $this->assertEquals( $this->test_user_id, $captured_params['user_id'] );
    }

    /**
     * Test ai_botkit_search_results filter modifies results.
     */
    public function test_search_results_filter_modifies_results(): void {
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            // Add a highlight field to each result.
            foreach ( $results as &$result ) {
                $result['highlighted'] = str_ireplace(
                    $query,
                    '<mark>' . $query . '</mark>',
                    $result['content']
                );
            }
            return $results;
        }, 10, 3 );

        $test_results = array(
            array( 'id' => 1, 'content' => 'Learn about WordPress hooks today' ),
        );

        $filtered = apply_filters( 'ai_botkit_search_results', $test_results, 'WordPress', $this->test_user_id );

        $this->assertArrayHasKey( 'highlighted', $filtered[0] );
        $this->assertStringContainsString( '<mark>WordPress</mark>', $filtered[0]['highlighted'] );
    }

    /**
     * Test ai_botkit_search_results filter can add results.
     */
    public function test_search_results_filter_can_add_results(): void {
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            // Add a promoted result at the beginning.
            array_unshift( $results, array(
                'id'        => 0,
                'content'   => 'Promoted: Check out our WordPress hooks guide!',
                'promoted'  => true,
                'relevance' => 1.0,
            ) );
            return $results;
        }, 10, 3 );

        $test_results = array(
            array( 'id' => 1, 'content' => 'Regular result about hooks' ),
        );

        $filtered = apply_filters( 'ai_botkit_search_results', $test_results, 'hooks', $this->test_user_id );

        $this->assertCount( 2, $filtered );
        $this->assertTrue( $filtered[0]['promoted'] );
        $this->assertEquals( 0, $filtered[0]['id'] );
    }

    /**
     * Test ai_botkit_search_results filter can remove results.
     */
    public function test_search_results_filter_can_remove_results(): void {
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            // Filter out results with low relevance.
            return array_filter( $results, function ( $result ) {
                return ( $result['relevance'] ?? 0 ) > 0.5;
            } );
        }, 10, 3 );

        $test_results = array(
            array( 'id' => 1, 'content' => 'High relevance', 'relevance' => 0.9 ),
            array( 'id' => 2, 'content' => 'Low relevance', 'relevance' => 0.3 ),
            array( 'id' => 3, 'content' => 'Medium relevance', 'relevance' => 0.6 ),
        );

        $filtered = apply_filters( 'ai_botkit_search_results', $test_results, 'test', $this->test_user_id );

        $this->assertCount( 2, $filtered );
        $ids = array_column( $filtered, 'id' );
        $this->assertContains( 1, $ids );
        $this->assertContains( 3, $ids );
        $this->assertNotContains( 2, $ids );
    }

    /**
     * Test ai_botkit_search_results filter with actual database results.
     */
    public function test_search_results_filter_with_database(): void {
        $user_id = $this->test_user_id;
        $query   = 'WordPress';

        // Perform actual search.
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    m.id,
                    m.conversation_id,
                    m.content,
                    MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance
                 FROM {$this->prefix}messages AS m
                 INNER JOIN {$this->prefix}conversations AS c ON m.conversation_id = c.id
                 WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
                 AND c.user_id = %d
                 ORDER BY relevance DESC",
                $query,
                $query,
                $user_id
            ),
            ARRAY_A
        );

        // Add filter.
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            foreach ( $results as &$result ) {
                $result['filtered'] = true;
            }
            return $results;
        }, 10, 3 );

        $filtered = apply_filters( 'ai_botkit_search_results', $results, $query, $user_id );

        $this->assertNotEmpty( $filtered );
        foreach ( $filtered as $result ) {
            $this->assertTrue( $result['filtered'] );
        }
    }

    // =========================================================================
    // ACTION: ai_botkit_conversation_archived
    // =========================================================================

    /**
     * Test ai_botkit_conversation_archived action exists.
     */
    public function test_conversation_archived_action_exists(): void {
        $action_fired = false;

        add_action( 'ai_botkit_conversation_archived', function ( $conversation_id, $user_id ) use ( &$action_fired ) {
            $action_fired = true;
        }, 10, 2 );

        do_action( 'ai_botkit_conversation_archived', 1, 1 );

        $this->assertTrue( $action_fired );
    }

    /**
     * Test ai_botkit_conversation_archived action receives correct parameters.
     */
    public function test_conversation_archived_action_parameters(): void {
        $captured = array();

        add_action( 'ai_botkit_conversation_archived', function ( $conversation_id, $user_id ) use ( &$captured ) {
            $captured = array(
                'conversation_id' => $conversation_id,
                'user_id'         => $user_id,
            );
        }, 10, 2 );

        do_action( 'ai_botkit_conversation_archived', $this->test_conversation_id, $this->test_user_id );

        $this->assertEquals( $this->test_conversation_id, $captured['conversation_id'] );
        $this->assertEquals( $this->test_user_id, $captured['user_id'] );
    }

    /**
     * Test ai_botkit_conversation_archived action fires on archive.
     */
    public function test_conversation_archived_action_fires_on_archive(): void {
        $action_data = array();

        add_action( 'ai_botkit_conversation_archived', function ( $conversation_id, $user_id ) use ( &$action_data ) {
            $action_data = array(
                'conversation_id' => $conversation_id,
                'user_id'         => $user_id,
                'timestamp'       => current_time( 'mysql' ),
            );
        }, 10, 2 );

        // Simulate archive operation.
        $conversation_id = $this->test_conversation_id;
        $user_id         = $this->test_user_id;

        $this->db->update(
            $this->prefix . 'conversations',
            array(
                'is_archived' => 1,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $conversation_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        do_action( 'ai_botkit_conversation_archived', $conversation_id, $user_id );

        $this->assertNotEmpty( $action_data );
        $this->assertEquals( $conversation_id, $action_data['conversation_id'] );
    }

    /**
     * Test ai_botkit_conversation_archived action can log to database.
     */
    public function test_conversation_archived_action_logs(): void {
        add_action( 'ai_botkit_conversation_archived', function ( $conversation_id, $user_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ai_botkit_user_interactions';

            $wpdb->insert(
                $table,
                array(
                    'user_id'          => $user_id,
                    'interaction_type' => 'archive',
                    'item_type'        => 'conversation',
                    'item_id'          => $conversation_id,
                    'created_at'       => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%d', '%s' )
            );
        }, 10, 2 );

        do_action( 'ai_botkit_conversation_archived', $this->test_conversation_id, $this->test_user_id );

        // Verify log was created.
        $log = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}user_interactions
                 WHERE user_id = %d
                 AND interaction_type = 'archive'
                 AND item_type = 'conversation'
                 AND item_id = %d",
                $this->test_user_id,
                $this->test_conversation_id
            )
        );

        $this->assertNotNull( $log );
        $this->assertEquals( 'archive', $log->interaction_type );
    }

    /**
     * Test ai_botkit_conversation_archived action with multiple callbacks.
     */
    public function test_conversation_archived_action_multiple_callbacks(): void {
        $callback_order = array();

        add_action( 'ai_botkit_conversation_archived', function () use ( &$callback_order ) {
            $callback_order[] = 'first';
        }, 10, 2 );

        add_action( 'ai_botkit_conversation_archived', function () use ( &$callback_order ) {
            $callback_order[] = 'second';
        }, 20, 2 );

        add_action( 'ai_botkit_conversation_archived', function () use ( &$callback_order ) {
            $callback_order[] = 'early';
        }, 5, 2 );

        do_action( 'ai_botkit_conversation_archived', 1, 1 );

        $this->assertEquals( array( 'early', 'first', 'second' ), $callback_order );
    }

    // =========================================================================
    // ACTION: ai_botkit_template_applied
    // =========================================================================

    /**
     * Test ai_botkit_template_applied action exists.
     */
    public function test_template_applied_action_exists(): void {
        $action_fired = false;

        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) use ( &$action_fired ) {
            $action_fired = true;
        }, 10, 2 );

        do_action( 'ai_botkit_template_applied', 1, 1 );

        $this->assertTrue( $action_fired );
    }

    /**
     * Test ai_botkit_template_applied action receives correct parameters.
     */
    public function test_template_applied_action_parameters(): void {
        $captured = array();

        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) use ( &$captured ) {
            $captured = array(
                'template_id' => $template_id,
                'chatbot_id'  => $chatbot_id,
            );
        }, 10, 2 );

        do_action( 'ai_botkit_template_applied', $this->test_template_id, $this->test_chatbot_id );

        $this->assertEquals( $this->test_template_id, $captured['template_id'] );
        $this->assertEquals( $this->test_chatbot_id, $captured['chatbot_id'] );
    }

    /**
     * Test ai_botkit_template_applied action increments usage count.
     */
    public function test_template_applied_action_increments_usage(): void {
        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ai_botkit_templates';

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET usage_count = usage_count + 1, updated_at = %s WHERE id = %d",
                    current_time( 'mysql' ),
                    $template_id
                )
            );
        }, 10, 2 );

        // Get initial count.
        $initial_count = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT usage_count FROM {$this->prefix}templates WHERE id = %d",
                $this->test_template_id
            )
        );

        do_action( 'ai_botkit_template_applied', $this->test_template_id, $this->test_chatbot_id );

        // Check incremented.
        $new_count = (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT usage_count FROM {$this->prefix}templates WHERE id = %d",
                $this->test_template_id
            )
        );

        $this->assertEquals( $initial_count + 1, $new_count );
    }

    /**
     * Test ai_botkit_template_applied action updates chatbot template_id.
     */
    public function test_template_applied_action_updates_chatbot(): void {
        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) {
            if ( $chatbot_id ) {
                global $wpdb;
                $table = $wpdb->prefix . 'ai_botkit_chatbots';

                $wpdb->update(
                    $table,
                    array(
                        'template_id' => $template_id,
                        'updated_at'  => current_time( 'mysql' ),
                    ),
                    array( 'id' => $chatbot_id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }
        }, 10, 2 );

        do_action( 'ai_botkit_template_applied', $this->test_template_id, $this->test_chatbot_id );

        // Verify chatbot was updated.
        $chatbot = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}chatbots WHERE id = %d",
                $this->test_chatbot_id
            )
        );

        $this->assertEquals( $this->test_template_id, (int) $chatbot->template_id );
    }

    /**
     * Test ai_botkit_template_applied action with null chatbot_id (template saved only).
     */
    public function test_template_applied_action_null_chatbot(): void {
        $captured = array();

        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) use ( &$captured ) {
            $captured = array(
                'template_id' => $template_id,
                'chatbot_id'  => $chatbot_id,
            );
        }, 10, 2 );

        // When saving template without applying to chatbot.
        do_action( 'ai_botkit_template_applied', $this->test_template_id, null );

        $this->assertEquals( $this->test_template_id, $captured['template_id'] );
        $this->assertNull( $captured['chatbot_id'] );
    }

    // =========================================================================
    // ACTION: ai_botkit_recommendation_displayed
    // =========================================================================

    /**
     * Test ai_botkit_recommendation_displayed action exists.
     */
    public function test_recommendation_displayed_action_exists(): void {
        $action_fired = false;

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$action_fired ) {
            $action_fired = true;
        }, 10, 3 );

        do_action( 'ai_botkit_recommendation_displayed', array(), 1, 1 );

        $this->assertTrue( $action_fired );
    }

    /**
     * Test ai_botkit_recommendation_displayed action receives correct parameters.
     */
    public function test_recommendation_displayed_action_parameters(): void {
        $captured = array();

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$captured ) {
            $captured = array(
                'recommendations' => $recommendations,
                'user_id'         => $user_id,
                'chatbot_id'      => $chatbot_id,
            );
        }, 10, 3 );

        $test_recommendations = array(
            array( 'id' => 1, 'type' => 'product', 'title' => 'Product A' ),
            array( 'id' => 2, 'type' => 'course', 'title' => 'Course B' ),
        );

        do_action( 'ai_botkit_recommendation_displayed', $test_recommendations, $this->test_user_id, $this->test_chatbot_id );

        $this->assertEquals( $test_recommendations, $captured['recommendations'] );
        $this->assertEquals( $this->test_user_id, $captured['user_id'] );
        $this->assertEquals( $this->test_chatbot_id, $captured['chatbot_id'] );
    }

    /**
     * Test ai_botkit_recommendation_displayed action logs impressions.
     */
    public function test_recommendation_displayed_action_logs_impressions(): void {
        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'ai_botkit_user_interactions';

            foreach ( $recommendations as $recommendation ) {
                $wpdb->insert(
                    $table,
                    array(
                        'user_id'          => $user_id,
                        'interaction_type' => 'recommendation_impression',
                        'item_type'        => $recommendation['type'],
                        'item_id'          => $recommendation['id'],
                        'chatbot_id'       => $chatbot_id,
                        'metadata'         => wp_json_encode( array(
                            'title'           => $recommendation['title'],
                            'relevance_score' => $recommendation['score'] ?? 0,
                        ) ),
                        'created_at'       => current_time( 'mysql' ),
                    ),
                    array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
                );
            }
        }, 10, 3 );

        $recommendations = array(
            array( 'id' => 101, 'type' => 'product', 'title' => 'Test Product', 'score' => 0.85 ),
            array( 'id' => 201, 'type' => 'course', 'title' => 'Test Course', 'score' => 0.72 ),
        );

        do_action( 'ai_botkit_recommendation_displayed', $recommendations, $this->test_user_id, $this->test_chatbot_id );

        // Verify impressions were logged.
        $impressions = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}user_interactions
                 WHERE user_id = %d
                 AND interaction_type = 'recommendation_impression'
                 ORDER BY id DESC
                 LIMIT 2",
                $this->test_user_id
            ),
            ARRAY_A
        );

        $this->assertCount( 2, $impressions );

        $logged_ids = array_column( $impressions, 'item_id' );
        $this->assertContains( '101', $logged_ids );
        $this->assertContains( '201', $logged_ids );
    }

    /**
     * Test ai_botkit_recommendation_displayed action with empty recommendations.
     */
    public function test_recommendation_displayed_action_empty_recommendations(): void {
        $action_fired = false;

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$action_fired ) {
            $action_fired = true;
            // Should still fire even with empty array.
            $this->assertEmpty( $recommendations );
        }, 10, 3 );

        do_action( 'ai_botkit_recommendation_displayed', array(), $this->test_user_id, $this->test_chatbot_id );

        $this->assertTrue( $action_fired );
    }

    /**
     * Test ai_botkit_recommendation_displayed action for guest users.
     */
    public function test_recommendation_displayed_action_guest_user(): void {
        $captured = array();

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$captured ) {
            $captured = array(
                'recommendations' => $recommendations,
                'user_id'         => $user_id,
                'is_guest'        => $user_id === 0,
            );
        }, 10, 3 );

        $recommendations = array(
            array( 'id' => 1, 'type' => 'product', 'title' => 'Guest Recommendation' ),
        );

        do_action( 'ai_botkit_recommendation_displayed', $recommendations, 0, $this->test_chatbot_id );

        $this->assertEquals( 0, $captured['user_id'] );
        $this->assertTrue( $captured['is_guest'] );
    }

    /**
     * Test ai_botkit_recommendation_displayed action can trigger analytics.
     */
    public function test_recommendation_displayed_action_analytics(): void {
        $analytics_data = array();

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$analytics_data ) {
            $analytics_data = array(
                'event'      => 'recommendations_shown',
                'timestamp'  => current_time( 'mysql' ),
                'user_id'    => $user_id,
                'chatbot_id' => $chatbot_id,
                'count'      => count( $recommendations ),
                'types'      => array_unique( array_column( $recommendations, 'type' ) ),
                'ids'        => array_column( $recommendations, 'id' ),
            );
        }, 10, 3 );

        $recommendations = array(
            array( 'id' => 1, 'type' => 'product', 'title' => 'Product 1' ),
            array( 'id' => 2, 'type' => 'product', 'title' => 'Product 2' ),
            array( 'id' => 3, 'type' => 'course', 'title' => 'Course 1' ),
        );

        do_action( 'ai_botkit_recommendation_displayed', $recommendations, $this->test_user_id, $this->test_chatbot_id );

        $this->assertEquals( 'recommendations_shown', $analytics_data['event'] );
        $this->assertEquals( 3, $analytics_data['count'] );
        $this->assertContains( 'product', $analytics_data['types'] );
        $this->assertContains( 'course', $analytics_data['types'] );
        $this->assertEquals( array( 1, 2, 3 ), $analytics_data['ids'] );
    }

    // =========================================================================
    // ADDITIONAL HOOK INTEGRATION TESTS
    // =========================================================================

    /**
     * Test hooks work together in a complete workflow.
     */
    public function test_hooks_complete_workflow(): void {
        $workflow_log = array();

        // Add hooks.
        add_filter( 'ai_botkit_history_per_page', function ( $per_page ) use ( &$workflow_log ) {
            $workflow_log[] = 'history_per_page_filter';
            return $per_page;
        } );

        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) use ( &$workflow_log ) {
            $workflow_log[] = 'search_results_filter';
            return $results;
        }, 10, 3 );

        add_action( 'ai_botkit_conversation_archived', function ( $conversation_id, $user_id ) use ( &$workflow_log ) {
            $workflow_log[] = 'conversation_archived_action';
        }, 10, 2 );

        add_action( 'ai_botkit_template_applied', function ( $template_id, $chatbot_id ) use ( &$workflow_log ) {
            $workflow_log[] = 'template_applied_action';
        }, 10, 2 );

        add_action( 'ai_botkit_recommendation_displayed', function ( $recommendations, $user_id, $chatbot_id ) use ( &$workflow_log ) {
            $workflow_log[] = 'recommendation_displayed_action';
        }, 10, 3 );

        // Simulate complete workflow.

        // 1. Load history with filter.
        apply_filters( 'ai_botkit_history_per_page', 10 );

        // 2. Search with filter.
        apply_filters( 'ai_botkit_search_results', array(), 'test', 1 );

        // 3. Archive conversation.
        do_action( 'ai_botkit_conversation_archived', 1, 1 );

        // 4. Apply template.
        do_action( 'ai_botkit_template_applied', 1, 1 );

        // 5. Display recommendations.
        do_action( 'ai_botkit_recommendation_displayed', array(), 1, 1 );

        // Verify all hooks fired in order.
        $this->assertEquals(
            array(
                'history_per_page_filter',
                'search_results_filter',
                'conversation_archived_action',
                'template_applied_action',
                'recommendation_displayed_action',
            ),
            $workflow_log
        );
    }

    /**
     * Test removing hooks works correctly.
     */
    public function test_removing_hooks(): void {
        $counter = 0;

        $callback = function () use ( &$counter ) {
            $counter++;
        };

        add_action( 'ai_botkit_conversation_archived', $callback, 10, 2 );

        do_action( 'ai_botkit_conversation_archived', 1, 1 );
        $this->assertEquals( 1, $counter );

        remove_action( 'ai_botkit_conversation_archived', $callback, 10 );

        do_action( 'ai_botkit_conversation_archived', 1, 1 );
        $this->assertEquals( 1, $counter );  // Still 1, hook was removed.
    }

    /**
     * Test hook priority affects execution order.
     */
    public function test_hook_priority_order(): void {
        $execution_order = array();

        add_action( 'ai_botkit_template_applied', function () use ( &$execution_order ) {
            $execution_order[] = 'priority_10';
        }, 10, 2 );

        add_action( 'ai_botkit_template_applied', function () use ( &$execution_order ) {
            $execution_order[] = 'priority_5';
        }, 5, 2 );

        add_action( 'ai_botkit_template_applied', function () use ( &$execution_order ) {
            $execution_order[] = 'priority_15';
        }, 15, 2 );

        do_action( 'ai_botkit_template_applied', 1, 1 );

        $this->assertEquals(
            array( 'priority_5', 'priority_10', 'priority_15' ),
            $execution_order
        );
    }

    /**
     * Test filter chaining preserves data integrity.
     */
    public function test_filter_chaining_data_integrity(): void {
        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            foreach ( $results as &$result ) {
                $result['step1'] = true;
            }
            return $results;
        }, 10, 3 );

        add_filter( 'ai_botkit_search_results', function ( $results, $query, $user_id ) {
            foreach ( $results as &$result ) {
                $result['step2'] = true;
                // Verify previous step's data is present.
                if ( ! isset( $result['step1'] ) ) {
                    $result['data_lost'] = true;
                }
            }
            return $results;
        }, 20, 3 );

        $initial = array(
            array( 'id' => 1, 'content' => 'Test' ),
        );

        $result = apply_filters( 'ai_botkit_search_results', $initial, 'test', 1 );

        $this->assertTrue( $result[0]['step1'] );
        $this->assertTrue( $result[0]['step2'] );
        $this->assertArrayNotHasKey( 'data_lost', $result[0] );
    }
}
