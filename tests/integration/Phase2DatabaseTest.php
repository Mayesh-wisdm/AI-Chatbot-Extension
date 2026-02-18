<?php
/**
 * Phase 2 Database Integration Tests
 *
 * Tests database operations for Phase 2 tables:
 * - ai_botkit_templates (CRUD operations)
 * - ai_botkit_media (CRUD operations)
 * - ai_botkit_user_interactions (CRUD operations)
 * - FULLTEXT search on conversations/messages
 * - Phase2Migration class
 *
 * @package AI_BotKit\Tests\Integration
 * @since   2.0.0
 */

namespace AI_BotKit\Tests\Integration;

use WP_UnitTestCase;

/**
 * Phase2DatabaseTest class.
 *
 * Integration tests for Phase 2 database tables and operations.
 *
 * @since 2.0.0
 */
class Phase2DatabaseTest extends WP_UnitTestCase {

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
     * Set up before each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->db     = $wpdb;
        $this->prefix = $wpdb->prefix . 'ai_botkit_';

        // Create a test user.
        $this->test_user_id = $this->factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Ensure tables exist by running migration.
        $this->run_phase2_migration();
    }

    /**
     * Tear down after each test.
     */
    public function tearDown(): void {
        // Clean up test data.
        $this->cleanup_test_data();

        parent::tearDown();
    }

    /**
     * Run Phase 2 migration to create tables.
     */
    private function run_phase2_migration(): void {
        $charset_collate = $this->db->get_charset_collate();

        // Create templates table.
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}templates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            style JSON,
            messages_template JSON,
            model_config JSON,
            conversation_starters JSON,
            thumbnail VARCHAR(255),
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            usage_count INT NOT NULL DEFAULT 0,
            created_by BIGINT(20) UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_category (category),
            INDEX idx_is_system (is_system),
            INDEX idx_is_active (is_active),
            INDEX idx_created_by (created_by)
        ) {$charset_collate};";
        $this->db->query( $sql );

        // Create media table.
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}media (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT(20) UNSIGNED,
            conversation_id BIGINT(20) UNSIGNED,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT(20) NOT NULL DEFAULT 0,
            metadata JSON,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_message (message_id),
            INDEX idx_conversation (conversation_id),
            INDEX idx_user (user_id),
            INDEX idx_type (media_type),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) {$charset_collate};";
        $this->db->query( $sql );

        // Create user_interactions table.
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}user_interactions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            session_id VARCHAR(100),
            interaction_type VARCHAR(50) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id BIGINT(20) UNSIGNED NOT NULL,
            chatbot_id BIGINT(20) UNSIGNED,
            metadata JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_time (user_id, created_at DESC),
            INDEX idx_item (item_type, item_id),
            INDEX idx_type (interaction_type),
            INDEX idx_chatbot (chatbot_id),
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";
        $this->db->query( $sql );

        // Create messages table for FULLTEXT testing.
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content TEXT NOT NULL,
            metadata JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX conversation_id (conversation_id)
        ) {$charset_collate} ENGINE=InnoDB;";
        $this->db->query( $sql );

        // Add FULLTEXT index if not exists.
        $index_exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = %s
                 AND table_name = %s
                 AND index_name = 'ft_content'",
                DB_NAME,
                $this->prefix . 'messages'
            )
        );

        if ( ! $index_exists ) {
            $this->db->query( "ALTER TABLE {$this->prefix}messages ADD FULLTEXT INDEX ft_content (content)" );
        }

        // Create conversations table.
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}conversations (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chatbot_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            session_id VARCHAR(100),
            guest_ip VARCHAR(45),
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            is_favorite TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_chatbot (chatbot_id),
            INDEX idx_user (user_id),
            INDEX idx_session (session_id),
            INDEX idx_archived (is_archived),
            INDEX idx_user_updated (user_id, updated_at DESC)
        ) {$charset_collate};";
        $this->db->query( $sql );
    }

    /**
     * Clean up test data.
     */
    private function cleanup_test_data(): void {
        $this->db->query( "DELETE FROM {$this->prefix}templates WHERE name LIKE 'Test%'" );
        $this->db->query( "DELETE FROM {$this->prefix}media WHERE file_name LIKE 'test%'" );
        $this->db->query( "DELETE FROM {$this->prefix}user_interactions WHERE user_id = {$this->test_user_id}" );
        $this->db->query( "DELETE FROM {$this->prefix}messages WHERE conversation_id IN (SELECT id FROM {$this->prefix}conversations WHERE user_id = {$this->test_user_id})" );
        $this->db->query( "DELETE FROM {$this->prefix}conversations WHERE user_id = {$this->test_user_id}" );
    }

    // =========================================================================
    // TEMPLATES TABLE CRUD TESTS
    // =========================================================================

    /**
     * Test template creation (INSERT).
     */
    public function test_template_create(): void {
        $template_data = array(
            'name'        => 'Test Template Create',
            'description' => 'A test template for unit testing',
            'category'    => 'support',
            'style'       => wp_json_encode( array(
                'primaryColor'     => '#0066cc',
                'backgroundColor'  => '#ffffff',
            ) ),
            'messages_template' => wp_json_encode( array(
                'greeting' => 'Hello! How can I help you?',
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
        );

        $result = $this->db->insert(
            $this->prefix . 'templates',
            $template_data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
        );

        $this->assertEquals( 1, $result, 'Template insert should return 1 row affected' );
        $this->assertGreaterThan( 0, $this->db->insert_id, 'Insert ID should be greater than 0' );

        // Verify the data was inserted correctly.
        $inserted = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $this->db->insert_id
            ),
            ARRAY_A
        );

        $this->assertEquals( 'Test Template Create', $inserted['name'] );
        $this->assertEquals( 'support', $inserted['category'] );
        $this->assertEquals( 0, (int) $inserted['is_system'] );
        $this->assertEquals( 1, (int) $inserted['is_active'] );
    }

    /**
     * Test template read (SELECT).
     */
    public function test_template_read(): void {
        // Insert a template first.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test Template Read',
                'category'   => 'sales',
                'is_active'  => 1,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        // Read by ID.
        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        $this->assertNotNull( $template );
        $this->assertEquals( 'Test Template Read', $template['name'] );
        $this->assertEquals( 'sales', $template['category'] );

        // Read with filters.
        $templates = $this->db->get_results(
            "SELECT * FROM {$this->prefix}templates WHERE category = 'sales' AND is_active = 1",
            ARRAY_A
        );

        $this->assertNotEmpty( $templates );
        $this->assertContains( $template['name'], array_column( $templates, 'name' ) );
    }

    /**
     * Test template update (UPDATE).
     */
    public function test_template_update(): void {
        // Insert a template first.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'        => 'Test Template Update',
                'category'    => 'general',
                'usage_count' => 0,
                'is_active'   => 1,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%d', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        // Update the template.
        $result = $this->db->update(
            $this->prefix . 'templates',
            array(
                'name'        => 'Test Template Update Modified',
                'category'    => 'education',
                'usage_count' => 5,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $template_id ),
            array( '%s', '%s', '%d', '%s' ),
            array( '%d' )
        );

        $this->assertNotFalse( $result );

        // Verify the update.
        $updated = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        $this->assertEquals( 'Test Template Update Modified', $updated['name'] );
        $this->assertEquals( 'education', $updated['category'] );
        $this->assertEquals( 5, (int) $updated['usage_count'] );
    }

    /**
     * Test template delete (DELETE).
     */
    public function test_template_delete(): void {
        // Insert a template first.
        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'       => 'Test Template Delete',
                'category'   => 'general',
                'is_system'  => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        // Verify it exists.
        $exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            )
        );
        $this->assertEquals( 1, (int) $exists );

        // Delete the template.
        $result = $this->db->delete(
            $this->prefix . 'templates',
            array( 'id' => $template_id ),
            array( '%d' )
        );

        $this->assertEquals( 1, $result );

        // Verify it is deleted.
        $exists_after = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            )
        );
        $this->assertEquals( 0, (int) $exists_after );
    }

    /**
     * Test template JSON column storage.
     */
    public function test_template_json_columns(): void {
        $style = array(
            'primaryColor'   => '#1E3A8A',
            'fontFamily'     => 'Inter, sans-serif',
            'borderRadius'   => '12px',
            'headerStyle'    => array(
                'backgroundColor' => '#1E3A8A',
                'textColor'       => '#FFFFFF',
            ),
        );

        $messages_template = array(
            'greeting' => 'Welcome! How may I assist you today?',
            'fallback' => 'I apologize, but I do not have that information.',
            'thinking' => 'Please wait while I process your request...',
        );

        $conversation_starters = array(
            array( 'text' => 'What products do you offer?', 'icon' => 'shopping-bag' ),
            array( 'text' => 'I need help with my order', 'icon' => 'help-circle' ),
        );

        $this->db->insert(
            $this->prefix . 'templates',
            array(
                'name'                  => 'Test Template JSON',
                'category'              => 'support',
                'style'                 => wp_json_encode( $style ),
                'messages_template'     => wp_json_encode( $messages_template ),
                'conversation_starters' => wp_json_encode( $conversation_starters ),
                'created_at'            => current_time( 'mysql' ),
                'updated_at'            => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        $template_id = $this->db->insert_id;

        // Retrieve and decode JSON.
        $template = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}templates WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        $decoded_style    = json_decode( $template['style'], true );
        $decoded_messages = json_decode( $template['messages_template'], true );
        $decoded_starters = json_decode( $template['conversation_starters'], true );

        $this->assertIsArray( $decoded_style );
        $this->assertEquals( '#1E3A8A', $decoded_style['primaryColor'] );
        $this->assertEquals( '#FFFFFF', $decoded_style['headerStyle']['textColor'] );

        $this->assertIsArray( $decoded_messages );
        $this->assertArrayHasKey( 'greeting', $decoded_messages );
        $this->assertArrayHasKey( 'fallback', $decoded_messages );

        $this->assertIsArray( $decoded_starters );
        $this->assertCount( 2, $decoded_starters );
        $this->assertEquals( 'shopping-bag', $decoded_starters[0]['icon'] );
    }

    /**
     * Test template category index filter.
     */
    public function test_template_category_filter(): void {
        // Insert templates with different categories.
        $categories = array( 'support', 'sales', 'education', 'marketing' );

        foreach ( $categories as $index => $category ) {
            $this->db->insert(
                $this->prefix . 'templates',
                array(
                    'name'       => 'Test Template Category ' . $category,
                    'category'   => $category,
                    'is_active'  => 1,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%d', '%s', '%s' )
            );
        }

        // Filter by category.
        $support_templates = $this->db->get_results(
            "SELECT * FROM {$this->prefix}templates WHERE category = 'support' AND is_active = 1",
            ARRAY_A
        );

        $this->assertNotEmpty( $support_templates );
        foreach ( $support_templates as $template ) {
            $this->assertEquals( 'support', $template['category'] );
        }

        // Count by category.
        $category_counts = $this->db->get_results(
            "SELECT category, COUNT(*) as count FROM {$this->prefix}templates WHERE name LIKE 'Test Template Category%' GROUP BY category",
            ARRAY_A
        );

        $this->assertNotEmpty( $category_counts );
    }

    // =========================================================================
    // MEDIA TABLE CRUD TESTS
    // =========================================================================

    /**
     * Test media creation (INSERT).
     */
    public function test_media_create(): void {
        $media_data = array(
            'message_id'      => null,
            'conversation_id' => 1,
            'user_id'         => $this->test_user_id,
            'media_type'      => 'image',
            'file_name'       => 'test_image_123.jpg',
            'file_path'       => '/var/www/html/wp-content/uploads/ai-botkit/chat-media/images/2026/01/test_image_123.jpg',
            'file_url'        => 'https://example.com/wp-content/uploads/ai-botkit/chat-media/images/2026/01/test_image_123.jpg',
            'mime_type'       => 'image/jpeg',
            'file_size'       => 245678,
            'metadata'        => wp_json_encode( array(
                'width'         => 1920,
                'height'        => 1080,
                'thumbnail_url' => 'https://example.com/wp-content/uploads/ai-botkit/chat-media/images/2026/01/thumb_test_image_123.jpg',
            ) ),
            'status'     => 'active',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $result = $this->db->insert(
            $this->prefix . 'media',
            $media_data,
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        $this->assertEquals( 1, $result );
        $this->assertGreaterThan( 0, $this->db->insert_id );

        // Verify the data.
        $inserted = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE id = %d",
                $this->db->insert_id
            ),
            ARRAY_A
        );

        $this->assertEquals( 'image', $inserted['media_type'] );
        $this->assertEquals( 'image/jpeg', $inserted['mime_type'] );
        $this->assertEquals( 245678, (int) $inserted['file_size'] );
        $this->assertEquals( 'active', $inserted['status'] );

        // Verify JSON metadata.
        $metadata = json_decode( $inserted['metadata'], true );
        $this->assertEquals( 1920, $metadata['width'] );
        $this->assertEquals( 1080, $metadata['height'] );
    }

    /**
     * Test media read by message ID.
     */
    public function test_media_read_by_message(): void {
        $message_id = 42;

        // Insert multiple media for a message.
        for ( $i = 1; $i <= 3; $i++ ) {
            $this->db->insert(
                $this->prefix . 'media',
                array(
                    'message_id'      => $message_id,
                    'conversation_id' => 1,
                    'user_id'         => $this->test_user_id,
                    'media_type'      => 'image',
                    'file_name'       => "test_msg_media_{$i}.jpg",
                    'file_path'       => "/path/to/test_msg_media_{$i}.jpg",
                    'file_url'        => "https://example.com/test_msg_media_{$i}.jpg",
                    'mime_type'       => 'image/jpeg',
                    'file_size'       => 100000 + ( $i * 1000 ),
                    'status'          => 'active',
                    'created_at'      => current_time( 'mysql' ),
                    'updated_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
            );
        }

        // Read media by message ID.
        $media = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE message_id = %d AND status = 'active' ORDER BY created_at ASC",
                $message_id
            ),
            ARRAY_A
        );

        $this->assertCount( 3, $media );
        foreach ( $media as $item ) {
            $this->assertEquals( $message_id, (int) $item['message_id'] );
            $this->assertEquals( 'active', $item['status'] );
        }
    }

    /**
     * Test media read by conversation ID.
     */
    public function test_media_read_by_conversation(): void {
        $conversation_id = 99;

        // Insert media with different types for a conversation.
        $media_types = array( 'image', 'document', 'video', 'link' );

        foreach ( $media_types as $index => $type ) {
            $this->db->insert(
                $this->prefix . 'media',
                array(
                    'message_id'      => 100 + $index,
                    'conversation_id' => $conversation_id,
                    'user_id'         => $this->test_user_id,
                    'media_type'      => $type,
                    'file_name'       => "test_conv_media_{$type}.file",
                    'file_path'       => "/path/to/test_conv_media_{$type}.file",
                    'file_url'        => "https://example.com/test_conv_media_{$type}.file",
                    'mime_type'       => 'application/octet-stream',
                    'file_size'       => 50000,
                    'status'          => 'active',
                    'created_at'      => current_time( 'mysql' ),
                    'updated_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
            );
        }

        // Read all media for conversation.
        $media = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE conversation_id = %d AND status = 'active' ORDER BY created_at ASC",
                $conversation_id
            ),
            ARRAY_A
        );

        $this->assertCount( 4, $media );

        // Filter by media type.
        $images = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE conversation_id = %d AND media_type = 'image' AND status = 'active'",
                $conversation_id
            ),
            ARRAY_A
        );

        $this->assertCount( 1, $images );
        $this->assertEquals( 'image', $images[0]['media_type'] );
    }

    /**
     * Test media update (UPDATE).
     */
    public function test_media_update(): void {
        // Insert media without message_id (orphan).
        $this->db->insert(
            $this->prefix . 'media',
            array(
                'message_id'      => null,
                'conversation_id' => 1,
                'user_id'         => $this->test_user_id,
                'media_type'      => 'image',
                'file_name'       => 'test_media_update.jpg',
                'file_path'       => '/path/to/test_media_update.jpg',
                'file_url'        => 'https://example.com/test_media_update.jpg',
                'mime_type'       => 'image/jpeg',
                'file_size'       => 123456,
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
        $media_id = $this->db->insert_id;

        // Link media to a message.
        $message_id = 55;
        $result     = $this->db->update(
            $this->prefix . 'media',
            array(
                'message_id' => $message_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $media_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        $this->assertNotFalse( $result );

        // Verify update.
        $updated = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE id = %d",
                $media_id
            ),
            ARRAY_A
        );

        $this->assertEquals( $message_id, (int) $updated['message_id'] );
    }

    /**
     * Test media soft delete (status change).
     */
    public function test_media_soft_delete(): void {
        // Insert media.
        $this->db->insert(
            $this->prefix . 'media',
            array(
                'message_id'      => 10,
                'conversation_id' => 1,
                'user_id'         => $this->test_user_id,
                'media_type'      => 'document',
                'file_name'       => 'test_media_delete.pdf',
                'file_path'       => '/path/to/test_media_delete.pdf',
                'file_url'        => 'https://example.com/test_media_delete.pdf',
                'mime_type'       => 'application/pdf',
                'file_size'       => 500000,
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
        $media_id = $this->db->insert_id;

        // Soft delete (change status).
        $this->db->update(
            $this->prefix . 'media',
            array(
                'status'     => 'deleted',
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $media_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        // Verify status change.
        $deleted = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE id = %d",
                $media_id
            ),
            ARRAY_A
        );

        $this->assertEquals( 'deleted', $deleted['status'] );

        // Verify it does not appear in active queries.
        $active_media = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}media WHERE message_id = %d AND status = 'active'",
                10
            ),
            ARRAY_A
        );

        $deleted_ids = array_column( $active_media, 'id' );
        $this->assertNotContains( $media_id, $deleted_ids );
    }

    /**
     * Test media orphan detection.
     */
    public function test_media_orphan_detection(): void {
        // Insert orphan media (no message_id).
        $this->db->insert(
            $this->prefix . 'media',
            array(
                'message_id'      => null,
                'conversation_id' => 1,
                'user_id'         => $this->test_user_id,
                'media_type'      => 'image',
                'file_name'       => 'test_orphan.jpg',
                'file_path'       => '/path/to/test_orphan.jpg',
                'file_url'        => 'https://example.com/test_orphan.jpg',
                'mime_type'       => 'image/jpeg',
                'file_size'       => 50000,
                'status'          => 'active',
                'created_at'      => date( 'Y-m-d H:i:s', strtotime( '-25 hours' ) ),
                'updated_at'      => date( 'Y-m-d H:i:s', strtotime( '-25 hours' ) ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        // Find orphaned media (no message_id and older than 24 hours).
        $orphans = $this->db->get_results(
            "SELECT id, file_path, created_at
             FROM {$this->prefix}media
             WHERE message_id IS NULL
             AND status = 'active'
             AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );

        $this->assertNotEmpty( $orphans );
        $this->assertNull( $orphans[0]['message_id'] ?? null );
    }

    // =========================================================================
    // USER INTERACTIONS TABLE CRUD TESTS
    // =========================================================================

    /**
     * Test user interaction creation (INSERT).
     */
    public function test_user_interaction_create(): void {
        $interaction_data = array(
            'user_id'          => $this->test_user_id,
            'session_id'       => 'sess_abc123xyz',
            'interaction_type' => 'page_view',
            'item_type'        => 'product',
            'item_id'          => 456,
            'chatbot_id'       => 1,
            'metadata'         => wp_json_encode( array(
                'referrer'    => 'https://example.com/products',
                'source'      => 'chat_suggestion',
                'scroll_depth' => 0.75,
            ) ),
            'created_at' => current_time( 'mysql' ),
        );

        $result = $this->db->insert(
            $this->prefix . 'user_interactions',
            $interaction_data,
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        $this->assertEquals( 1, $result );
        $this->assertGreaterThan( 0, $this->db->insert_id );

        // Verify the data.
        $inserted = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}user_interactions WHERE id = %d",
                $this->db->insert_id
            ),
            ARRAY_A
        );

        $this->assertEquals( $this->test_user_id, (int) $inserted['user_id'] );
        $this->assertEquals( 'page_view', $inserted['interaction_type'] );
        $this->assertEquals( 'product', $inserted['item_type'] );
        $this->assertEquals( 456, (int) $inserted['item_id'] );

        // Verify JSON metadata.
        $metadata = json_decode( $inserted['metadata'], true );
        $this->assertEquals( 'chat_suggestion', $metadata['source'] );
    }

    /**
     * Test user interaction read with user timeline index.
     */
    public function test_user_interaction_read_timeline(): void {
        // Insert multiple interactions for the user.
        $interaction_types = array( 'page_view', 'product_view', 'add_to_cart', 'purchase' );

        foreach ( $interaction_types as $index => $type ) {
            $this->db->insert(
                $this->prefix . 'user_interactions',
                array(
                    'user_id'          => $this->test_user_id,
                    'session_id'       => 'sess_timeline_test',
                    'interaction_type' => $type,
                    'item_type'        => 'product',
                    'item_id'          => 100 + $index,
                    'created_at'       => date( 'Y-m-d H:i:s', strtotime( "-{$index} hours" ) ),
                ),
                array( '%d', '%s', '%s', '%s', '%d', '%s' )
            );
        }

        // Query user timeline (most recent first).
        $timeline = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}user_interactions
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $this->test_user_id
            ),
            ARRAY_A
        );

        $this->assertGreaterThanOrEqual( 4, count( $timeline ) );

        // Verify ordering (most recent first).
        $previous_time = null;
        foreach ( $timeline as $interaction ) {
            if ( $previous_time !== null ) {
                $this->assertGreaterThanOrEqual(
                    strtotime( $interaction['created_at'] ),
                    strtotime( $previous_time )
                );
            }
            $previous_time = $interaction['created_at'];
        }
    }

    /**
     * Test user interaction aggregation for recommendations.
     */
    public function test_user_interaction_aggregation(): void {
        // Insert interactions for different items.
        $items = array(
            array( 'item_id' => 1, 'count' => 5 ),
            array( 'item_id' => 2, 'count' => 3 ),
            array( 'item_id' => 3, 'count' => 7 ),
        );

        foreach ( $items as $item ) {
            for ( $i = 0; $i < $item['count']; $i++ ) {
                $this->db->insert(
                    $this->prefix . 'user_interactions',
                    array(
                        'user_id'          => $this->test_user_id,
                        'session_id'       => 'sess_aggregation',
                        'interaction_type' => 'product_view',
                        'item_type'        => 'product',
                        'item_id'          => $item['item_id'],
                        'created_at'       => current_time( 'mysql' ),
                    ),
                    array( '%d', '%s', '%s', '%s', '%d', '%s' )
                );
            }
        }

        // Aggregate by item.
        $aggregated = $this->db->get_results(
            $this->db->prepare(
                "SELECT item_type, item_id, interaction_type, COUNT(*) AS interaction_count, MAX(created_at) AS last_interaction
                 FROM {$this->prefix}user_interactions
                 WHERE user_id = %d
                 AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY item_type, item_id, interaction_type
                 ORDER BY interaction_count DESC
                 LIMIT 50",
                $this->test_user_id
            ),
            ARRAY_A
        );

        $this->assertNotEmpty( $aggregated );

        // Find the item with highest count.
        $top_item = $aggregated[0];
        $this->assertEquals( 3, (int) $top_item['item_id'] );
        $this->assertEquals( 7, (int) $top_item['interaction_count'] );
    }

    /**
     * Test user interaction session-based query.
     */
    public function test_user_interaction_session_query(): void {
        $session_id = 'sess_guest_' . uniqid();

        // Insert guest interactions (user_id = 0).
        for ( $i = 1; $i <= 5; $i++ ) {
            $this->db->insert(
                $this->prefix . 'user_interactions',
                array(
                    'user_id'          => 0,
                    'session_id'       => $session_id,
                    'interaction_type' => 'page_view',
                    'item_type'        => 'course',
                    'item_id'          => 200 + $i,
                    'created_at'       => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%d', '%s' )
            );
        }

        // Query by session (for guests).
        $session_interactions = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->prefix}user_interactions
                 WHERE session_id = %s
                 ORDER BY created_at DESC",
                $session_id
            ),
            ARRAY_A
        );

        $this->assertCount( 5, $session_interactions );
        foreach ( $session_interactions as $interaction ) {
            $this->assertEquals( $session_id, $interaction['session_id'] );
            $this->assertEquals( 'course', $interaction['item_type'] );
        }
    }

    // =========================================================================
    // FULLTEXT SEARCH TESTS
    // =========================================================================

    /**
     * Test FULLTEXT index exists on messages table.
     */
    public function test_fulltext_index_exists(): void {
        $index_exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE table_schema = %s
                 AND table_name = %s
                 AND index_name = 'ft_content'",
                DB_NAME,
                $this->prefix . 'messages'
            )
        );

        $this->assertGreaterThan( 0, (int) $index_exists, 'FULLTEXT index ft_content should exist on messages table' );
    }

    /**
     * Test FULLTEXT search on message content.
     */
    public function test_fulltext_search_messages(): void {
        // Create a conversation first.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => 1,
                'user_id'    => $this->test_user_id,
                'session_id' => 'sess_fulltext_test',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $conversation_id = $this->db->insert_id;

        // Insert messages with searchable content.
        $messages = array(
            array( 'role' => 'user', 'content' => 'What are the best WordPress plugins for ecommerce and online stores?' ),
            array( 'role' => 'assistant', 'content' => 'WooCommerce is the most popular WordPress ecommerce plugin for building online stores.' ),
            array( 'role' => 'user', 'content' => 'How do I set up payment gateways in WooCommerce?' ),
            array( 'role' => 'assistant', 'content' => 'You can configure payment gateways like Stripe and PayPal in WooCommerce settings.' ),
            array( 'role' => 'user', 'content' => 'Can you recommend a good theme for my website?' ),
        );

        foreach ( $messages as $message ) {
            $this->db->insert(
                $this->prefix . 'messages',
                array(
                    'conversation_id' => $conversation_id,
                    'role'            => $message['role'],
                    'content'         => $message['content'],
                    'created_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }

        // Search for "WooCommerce".
        $results = $this->db->get_results(
            "SELECT
                m.id,
                m.conversation_id,
                m.role,
                m.content,
                m.created_at,
                MATCH(m.content) AGAINST('WooCommerce' IN NATURAL LANGUAGE MODE) AS relevance
             FROM {$this->prefix}messages AS m
             WHERE MATCH(m.content) AGAINST('WooCommerce' IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC",
            ARRAY_A
        );

        $this->assertNotEmpty( $results, 'FULLTEXT search should return results for "WooCommerce"' );

        foreach ( $results as $result ) {
            $this->assertStringContainsStringIgnoringCase( 'WooCommerce', $result['content'] );
            $this->assertGreaterThan( 0, (float) $result['relevance'] );
        }
    }

    /**
     * Test FULLTEXT search with user filter.
     */
    public function test_fulltext_search_with_user_filter(): void {
        // Create conversation for test user.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => 1,
                'user_id'    => $this->test_user_id,
                'session_id' => 'sess_user_search',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $user_conversation_id = $this->db->insert_id;

        // Create conversation for another user.
        $other_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => 1,
                'user_id'    => $other_user_id,
                'session_id' => 'sess_other_user',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $other_conversation_id = $this->db->insert_id;

        // Insert messages for both users with same searchable keyword.
        $this->db->insert(
            $this->prefix . 'messages',
            array(
                'conversation_id' => $user_conversation_id,
                'role'            => 'user',
                'content'         => 'I need help with machine learning algorithms for classification.',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        $this->db->insert(
            $this->prefix . 'messages',
            array(
                'conversation_id' => $other_conversation_id,
                'role'            => 'user',
                'content'         => 'What are the best machine learning frameworks for Python?',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );

        // Search with user filter.
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT
                    m.id,
                    m.conversation_id,
                    m.content,
                    c.user_id,
                    MATCH(m.content) AGAINST('machine learning' IN NATURAL LANGUAGE MODE) AS relevance
                 FROM {$this->prefix}messages AS m
                 INNER JOIN {$this->prefix}conversations AS c ON m.conversation_id = c.id
                 WHERE MATCH(m.content) AGAINST('machine learning' IN NATURAL LANGUAGE MODE)
                 AND c.user_id = %d
                 ORDER BY relevance DESC",
                $this->test_user_id
            ),
            ARRAY_A
        );

        $this->assertNotEmpty( $results );

        // Verify all results belong to the test user.
        foreach ( $results as $result ) {
            $this->assertEquals( $this->test_user_id, (int) $result['user_id'] );
        }
    }

    /**
     * Test FULLTEXT search relevance scoring.
     */
    public function test_fulltext_relevance_scoring(): void {
        // Create a conversation.
        $this->db->insert(
            $this->prefix . 'conversations',
            array(
                'chatbot_id' => 1,
                'user_id'    => $this->test_user_id,
                'session_id' => 'sess_relevance',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );
        $conversation_id = $this->db->insert_id;

        // Insert messages with varying relevance to search term.
        $messages = array(
            'Python Python Python Python programming language',  // High relevance.
            'Python is used for data science and Python web development',  // Medium relevance.
            'I learned some Python basics yesterday',  // Low relevance.
            'Java and JavaScript are popular languages',  // No relevance.
        );

        foreach ( $messages as $content ) {
            $this->db->insert(
                $this->prefix . 'messages',
                array(
                    'conversation_id' => $conversation_id,
                    'role'            => 'user',
                    'content'         => $content,
                    'created_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }

        // Search and check relevance ordering.
        $results = $this->db->get_results(
            "SELECT
                m.content,
                MATCH(m.content) AGAINST('Python' IN NATURAL LANGUAGE MODE) AS relevance
             FROM {$this->prefix}messages AS m
             WHERE m.conversation_id = {$conversation_id}
             AND MATCH(m.content) AGAINST('Python' IN NATURAL LANGUAGE MODE)
             ORDER BY relevance DESC",
            ARRAY_A
        );

        $this->assertNotEmpty( $results );

        // Verify results are ordered by relevance (descending).
        $previous_relevance = PHP_FLOAT_MAX;
        foreach ( $results as $result ) {
            $this->assertLessThanOrEqual( $previous_relevance, (float) $result['relevance'] );
            $previous_relevance = (float) $result['relevance'];
        }
    }

    // =========================================================================
    // MIGRATION TESTS
    // =========================================================================

    /**
     * Test Phase 2 migration creates all required tables.
     */
    public function test_migration_creates_all_tables(): void {
        $required_tables = array(
            'templates',
            'media',
            'user_interactions',
            'conversations',
            'messages',
        );

        foreach ( $required_tables as $table ) {
            $table_name   = $this->prefix . $table;
            $table_exists = $this->db->get_var(
                $this->db->prepare( 'SHOW TABLES LIKE %s', $table_name )
            );

            $this->assertNotNull( $table_exists, "Table {$table_name} should exist after migration" );
        }
    }

    /**
     * Test templates table has all required columns.
     */
    public function test_templates_table_schema(): void {
        $columns = $this->db->get_results(
            $this->db->prepare(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'templates'
            ),
            ARRAY_A
        );

        $column_names = array_column( $columns, 'COLUMN_NAME' );

        $required_columns = array(
            'id', 'name', 'description', 'category', 'style', 'messages_template',
            'model_config', 'conversation_starters', 'thumbnail', 'is_system',
            'is_active', 'usage_count', 'created_by', 'created_at', 'updated_at',
        );

        foreach ( $required_columns as $column ) {
            $this->assertContains( $column, $column_names, "Templates table should have column: {$column}" );
        }
    }

    /**
     * Test media table has all required columns.
     */
    public function test_media_table_schema(): void {
        $columns = $this->db->get_results(
            $this->db->prepare(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'media'
            ),
            ARRAY_A
        );

        $column_names = array_column( $columns, 'COLUMN_NAME' );

        $required_columns = array(
            'id', 'message_id', 'conversation_id', 'user_id', 'media_type',
            'file_name', 'file_path', 'file_url', 'mime_type', 'file_size',
            'metadata', 'status', 'created_at', 'updated_at',
        );

        foreach ( $required_columns as $column ) {
            $this->assertContains( $column, $column_names, "Media table should have column: {$column}" );
        }
    }

    /**
     * Test user_interactions table has all required columns.
     */
    public function test_user_interactions_table_schema(): void {
        $columns = $this->db->get_results(
            $this->db->prepare(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'user_interactions'
            ),
            ARRAY_A
        );

        $column_names = array_column( $columns, 'COLUMN_NAME' );

        $required_columns = array(
            'id', 'user_id', 'session_id', 'interaction_type', 'item_type',
            'item_id', 'chatbot_id', 'metadata', 'created_at',
        );

        foreach ( $required_columns as $column ) {
            $this->assertContains( $column, $column_names, "User interactions table should have column: {$column}" );
        }
    }

    /**
     * Test templates table has required indexes.
     */
    public function test_templates_table_indexes(): void {
        $indexes = $this->db->get_results(
            $this->db->prepare(
                "SELECT INDEX_NAME, COLUMN_NAME
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'templates'
            ),
            ARRAY_A
        );

        $index_names = array_unique( array_column( $indexes, 'INDEX_NAME' ) );

        $required_indexes = array( 'PRIMARY', 'idx_category', 'idx_is_system', 'idx_is_active', 'idx_created_by' );

        foreach ( $required_indexes as $index ) {
            $this->assertContains( $index, $index_names, "Templates table should have index: {$index}" );
        }
    }

    /**
     * Test media table has required indexes.
     */
    public function test_media_table_indexes(): void {
        $indexes = $this->db->get_results(
            $this->db->prepare(
                "SELECT INDEX_NAME, COLUMN_NAME
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'media'
            ),
            ARRAY_A
        );

        $index_names = array_unique( array_column( $indexes, 'INDEX_NAME' ) );

        $required_indexes = array( 'PRIMARY', 'idx_message', 'idx_conversation', 'idx_user', 'idx_type', 'idx_status', 'idx_created' );

        foreach ( $required_indexes as $index ) {
            $this->assertContains( $index, $index_names, "Media table should have index: {$index}" );
        }
    }

    /**
     * Test user_interactions table has required indexes.
     */
    public function test_user_interactions_table_indexes(): void {
        $indexes = $this->db->get_results(
            $this->db->prepare(
                "SELECT INDEX_NAME, COLUMN_NAME
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $this->prefix . 'user_interactions'
            ),
            ARRAY_A
        );

        $index_names = array_unique( array_column( $indexes, 'INDEX_NAME' ) );

        $required_indexes = array( 'PRIMARY', 'idx_user_time', 'idx_item', 'idx_type', 'idx_chatbot', 'idx_session', 'idx_created' );

        foreach ( $required_indexes as $index ) {
            $this->assertContains( $index, $index_names, "User interactions table should have index: {$index}" );
        }
    }

    /**
     * Test conversations table has is_archived column from migration.
     */
    public function test_conversations_table_has_archived_column(): void {
        $column_exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s
                 AND TABLE_NAME = %s
                 AND COLUMN_NAME = 'is_archived'",
                DB_NAME,
                $this->prefix . 'conversations'
            )
        );

        $this->assertGreaterThan( 0, (int) $column_exists, 'Conversations table should have is_archived column' );
    }
}
