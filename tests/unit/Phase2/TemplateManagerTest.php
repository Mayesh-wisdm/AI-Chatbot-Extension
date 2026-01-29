<?php
/**
 * Tests for Template_Manager class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Template_Manager
 *
 * Implements test cases for FR-230 to FR-239 (Conversation Templates)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Template_Manager;
use WP_UnitTestCase;

/**
 * Template Manager Test Class.
 *
 * Tests:
 * - TC-230-001 through TC-230-003: Template Library Access
 * - TC-231-001 through TC-231-003: Template CRUD Operations
 * - TC-232-001 through TC-232-003: Template Variables
 * - TC-233-001 through TC-233-003: Template Categories
 * - TC-234-001 through TC-234-004: Quick Replies
 * - TC-235-001 through TC-235-003: Admin Template Management
 */
class TemplateManagerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Template_Manager
     */
    private Template_Manager $manager;

    /**
     * Admin user ID.
     *
     * @var int
     */
    private int $admin_id;

    /**
     * Regular user ID.
     *
     * @var int
     */
    private int $user_id;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->admin_id = $this->set_current_user( array(
            'ID'           => 1,
            'user_login'   => 'admin',
            'display_name' => 'Admin User',
            'capabilities' => array( 'manage_options' ),
        ) );

        $this->user_id = 100;

        $this->manager = new Template_Manager();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-230: Template Library Access - TC-230-xxx
    // =========================================================================

    /**
     * Test TC-230-001: Get templates for chatbot.
     *
     * @test
     * @covers Template_Manager::get_templates
     * Implements: TC-230-001, FR-230
     */
    public function test_get_templates_for_chatbot(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 3,
            'results' => array(
                array(
                    'id'              => 1,
                    'chatbot_id'      => 10,
                    'name'            => 'Welcome Template',
                    'title'           => 'Welcome',
                    'content'         => 'Hello! How can I help you today?',
                    'category'        => 'greetings',
                    'variables'       => '[]',
                    'quick_replies'   => '[]',
                    'is_active'       => 1,
                    'is_default'      => 0,
                    'created_by'      => $this->admin_id,
                    'created_at'      => '2026-01-28 10:00:00',
                    'updated_at'      => '2026-01-28 10:00:00',
                    'usage_count'     => 100,
                    'creator_name'    => 'Admin User',
                ),
            ),
        ) );

        // Act.
        $result = $this->manager->get_templates( 10 );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'templates', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'pages', $result );
    }

    /**
     * Test TC-230-002: Get templates by category.
     *
     * @test
     * @covers Template_Manager::get_templates
     * Implements: TC-230-002, FR-230
     */
    public function test_get_templates_by_category(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 2,
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->get_templates( 10, 'greetings' );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'templates', $result );
    }

    /**
     * Test TC-230-003: Get all templates without chatbot filter.
     *
     * @test
     * @covers Template_Manager::get_templates
     * Implements: TC-230-003, FR-230
     */
    public function test_get_all_templates(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 10,
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->get_templates( 0 );

        // Assert.
        $this->assertIsArray( $result );
    }

    // =========================================================================
    // FR-231: Template CRUD - TC-231-xxx
    // =========================================================================

    /**
     * Test TC-231-001: Create new template.
     *
     * @test
     * @covers Template_Manager::create_template
     * Implements: TC-231-001, FR-231
     */
    public function test_create_new_template(): void {
        // Arrange.
        $template_data = array(
            'chatbot_id'    => 10,
            'name'          => 'Greeting Template',
            'title'         => 'Welcome Message',
            'content'       => 'Hello {{user_name}}, how can I help you?',
            'category'      => 'greetings',
            'variables'     => array( 'user_name' ),
            'quick_replies' => array(
                array( 'text' => 'Help', 'value' => 'I need help' ),
            ),
            'is_active'     => true,
        );

        // Act.
        $result = $this->manager->create_template( $template_data );

        // Assert.
        $this->assertNotWPError( $result );
        $this->assertIsInt( $result );
        $this->assertGreaterThan( 0, $result );
    }

    /**
     * Test TC-231-002: Create template with missing required fields.
     *
     * @test
     * @covers Template_Manager::create_template
     * Implements: TC-231-002, FR-231
     */
    public function test_create_template_missing_required_fields(): void {
        // Arrange: Missing 'name' and 'content'.
        $template_data = array(
            'chatbot_id' => 10,
            'category'   => 'greetings',
        );

        // Act.
        $result = $this->manager->create_template( $template_data );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * Test TC-231-003: Get single template.
     *
     * @test
     * @covers Template_Manager::get_template
     * Implements: TC-231-003, FR-231
     */
    public function test_get_single_template(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'              => 1,
                    'chatbot_id'      => 10,
                    'name'            => 'Test Template',
                    'title'           => 'Test',
                    'content'         => 'Test content',
                    'category'        => 'general',
                    'variables'       => '[]',
                    'quick_replies'   => '[]',
                    'is_active'       => 1,
                    'is_default'      => 0,
                    'created_by'      => 1,
                    'created_at'      => '2026-01-28 10:00:00',
                    'updated_at'      => '2026-01-28 10:00:00',
                    'usage_count'     => 0,
                ),
            ),
        ) );

        // Act.
        $template = $this->manager->get_template( 1 );

        // Assert.
        $this->assertIsArray( $template );
        $this->assertArrayHasKey( 'id', $template );
        $this->assertArrayHasKey( 'name', $template );
        $this->assertArrayHasKey( 'content', $template );
        $this->assertArrayHasKey( 'variables', $template );
        $this->assertArrayHasKey( 'quick_replies', $template );
    }

    /**
     * Test TC-231-004: Get non-existent template returns null.
     *
     * @test
     * @covers Template_Manager::get_template
     * Implements: TC-231-004, FR-231
     */
    public function test_get_nonexistent_template_returns_null(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $template = $this->manager->get_template( 9999 );

        // Assert.
        $this->assertNull( $template );
    }

    /**
     * Test TC-231-005: Update template.
     *
     * @test
     * @covers Template_Manager::update_template
     * Implements: TC-231-005, FR-231
     */
    public function test_update_template(): void {
        // Arrange: Mock existing template.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'              => 1,
                    'chatbot_id'      => 10,
                    'name'            => 'Old Name',
                    'title'           => 'Old Title',
                    'content'         => 'Old content',
                    'category'        => 'general',
                    'variables'       => '[]',
                    'quick_replies'   => '[]',
                    'is_active'       => 1,
                    'is_default'      => 0,
                    'created_by'      => 1,
                    'created_at'      => '2026-01-28 10:00:00',
                    'updated_at'      => '2026-01-28 10:00:00',
                    'usage_count'     => 0,
                ),
            ),
        ) );

        $update_data = array(
            'name'    => 'Updated Name',
            'content' => 'Updated content',
        );

        // Act.
        $result = $this->manager->update_template( 1, $update_data );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-231-006: Update non-existent template returns error.
     *
     * @test
     * @covers Template_Manager::update_template
     * Implements: TC-231-006, FR-231
     */
    public function test_update_nonexistent_template_returns_error(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->update_template( 9999, array( 'name' => 'New Name' ) );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'template_not_found', $result->get_error_code() );
    }

    /**
     * Test TC-231-007: Delete template.
     *
     * @test
     * @covers Template_Manager::delete_template
     * Implements: TC-231-007, FR-231
     */
    public function test_delete_template(): void {
        // Arrange: Mock existing template.
        $this->mock_db_results( array(
            'results' => array(
                (object) array( 'id' => 1 ),
            ),
        ) );

        // Act.
        $result = $this->manager->delete_template( 1 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-231-008: Delete non-existent template returns error.
     *
     * @test
     * @covers Template_Manager::delete_template
     * Implements: TC-231-008, FR-231
     */
    public function test_delete_nonexistent_template_returns_error(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->delete_template( 9999 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'template_not_found', $result->get_error_code() );
    }

    // =========================================================================
    // FR-232: Template Variables - TC-232-xxx
    // =========================================================================

    /**
     * Test TC-232-001: Process template with variables.
     *
     * @test
     * @covers Template_Manager::process_template
     * Implements: TC-232-001, FR-232
     */
    public function test_process_template_with_variables(): void {
        // Arrange.
        $template = array(
            'content'   => 'Hello {{user_name}}, welcome to {{site_name}}!',
            'variables' => array( 'user_name', 'site_name' ),
        );
        $context = array(
            'user_name' => 'John Doe',
            'site_name' => 'Test Site',
        );

        // Act.
        $result = $this->manager->process_template( $template, $context );

        // Assert.
        $this->assertSame( 'Hello John Doe, welcome to Test Site!', $result );
    }

    /**
     * Test TC-232-002: Missing variable uses placeholder.
     *
     * @test
     * @covers Template_Manager::process_template
     * Implements: TC-232-002, FR-232
     */
    public function test_process_template_missing_variable(): void {
        // Arrange.
        $template = array(
            'content'   => 'Hello {{user_name}}!',
            'variables' => array( 'user_name' ),
        );
        $context = array(); // No variables provided.

        // Act.
        $result = $this->manager->process_template( $template, $context );

        // Assert: Variable placeholder remains.
        $this->assertStringContainsString( '{{user_name}}', $result );
    }

    /**
     * Test TC-232-003: XSS in variables is escaped.
     *
     * @test
     * @covers Template_Manager::process_template
     * Implements: TC-232-003, FR-232
     */
    public function test_process_template_escapes_xss(): void {
        // Arrange.
        $template = array(
            'content'   => 'Hello {{user_name}}!',
            'variables' => array( 'user_name' ),
        );
        $context = array(
            'user_name' => '<script>alert("XSS")</script>',
        );

        // Act.
        $result = $this->manager->process_template( $template, $context );

        // Assert: Script tag is escaped.
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringContainsString( '&lt;script&gt;', $result );
    }

    // =========================================================================
    // FR-233: Template Categories - TC-233-xxx
    // =========================================================================

    /**
     * Test TC-233-001: Get default categories.
     *
     * @test
     * @covers Template_Manager::get_categories
     * Implements: TC-233-001, FR-233
     */
    public function test_get_default_categories(): void {
        // Arrange.
        $this->mock_db_results( array(
            'col' => array(),
        ) );

        // Act.
        $categories = $this->manager->get_categories( 0 );

        // Assert.
        $this->assertIsArray( $categories );
        $this->assertContains( 'greetings', $categories );
        $this->assertContains( 'support', $categories );
        $this->assertContains( 'sales', $categories );
        $this->assertContains( 'custom', $categories );
    }

    /**
     * Test TC-233-002: Get categories for chatbot includes used categories.
     *
     * @test
     * @covers Template_Manager::get_categories
     * Implements: TC-233-002, FR-233
     */
    public function test_get_categories_for_chatbot(): void {
        // Arrange: Mock custom category.
        $this->mock_db_results( array(
            'col' => array( 'custom_category' ),
        ) );

        // Act.
        $categories = $this->manager->get_categories( 10 );

        // Assert.
        $this->assertIsArray( $categories );
        $this->assertContains( 'custom_category', $categories );
    }

    // =========================================================================
    // FR-234: Quick Replies - TC-234-xxx
    // =========================================================================

    /**
     * Test TC-234-001: Get quick replies for template.
     *
     * @test
     * @covers Template_Manager::get_quick_replies
     * Implements: TC-234-001, FR-234
     */
    public function test_get_quick_replies_for_template(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'            => 1,
                    'quick_replies' => '[{"text":"Yes","value":"yes"},{"text":"No","value":"no"}]',
                ),
            ),
        ) );

        // Act.
        $replies = $this->manager->get_quick_replies( 1 );

        // Assert.
        $this->assertIsArray( $replies );
        $this->assertCount( 2, $replies );
        $this->assertSame( 'Yes', $replies[0]['text'] );
    }

    /**
     * Test TC-234-002: Get quick replies for non-existent template returns empty.
     *
     * @test
     * @covers Template_Manager::get_quick_replies
     * Implements: TC-234-002, FR-234
     */
    public function test_get_quick_replies_nonexistent_returns_empty(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $replies = $this->manager->get_quick_replies( 9999 );

        // Assert.
        $this->assertIsArray( $replies );
        $this->assertEmpty( $replies );
    }

    // =========================================================================
    // FR-235: Admin Template Management - TC-235-xxx
    // =========================================================================

    /**
     * Test TC-235-001: Admin can toggle template active status.
     *
     * @test
     * @covers Template_Manager::toggle_active
     * Implements: TC-235-001, FR-235
     */
    public function test_admin_can_toggle_template_active(): void {
        // Arrange: Mock existing template.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'        => 1,
                    'is_active' => 1,
                ),
            ),
        ) );

        // Act.
        $result = $this->manager->toggle_active( 1 );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 1, $result['id'] );
        $this->assertFalse( $result['is_active'] ); // Toggled from 1 to 0.
    }

    /**
     * Test TC-235-002: Toggle active on non-existent template returns error.
     *
     * @test
     * @covers Template_Manager::toggle_active
     * Implements: TC-235-002, FR-235
     */
    public function test_toggle_active_nonexistent_returns_error(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->toggle_active( 9999 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'template_not_found', $result->get_error_code() );
    }

    /**
     * Test TC-235-003: Set default template.
     *
     * @test
     * @covers Template_Manager::set_default
     * Implements: TC-235-003, FR-235
     */
    public function test_set_default_template(): void {
        // Arrange: Mock existing template with chatbot_id.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'         => 1,
                    'chatbot_id' => 10,
                ),
            ),
        ) );

        // Act.
        $result = $this->manager->set_default( 1 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-235-004: Set default on non-existent template returns error.
     *
     * @test
     * @covers Template_Manager::set_default
     * Implements: TC-235-004, FR-235
     */
    public function test_set_default_nonexistent_returns_error(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $result = $this->manager->set_default( 9999 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'template_not_found', $result->get_error_code() );
    }

    // =========================================================================
    // FR-236: Template Usage Tracking - TC-236-xxx
    // =========================================================================

    /**
     * Test TC-236-001: Increment usage count.
     *
     * @test
     * @covers Template_Manager::increment_usage
     * Implements: TC-236-001, FR-236
     */
    public function test_increment_usage_count(): void {
        // Act.
        $result = $this->manager->increment_usage( 1 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-236-002: Get template usage statistics.
     *
     * @test
     * @covers Template_Manager::get_usage_statistics
     * Implements: TC-236-002, FR-236
     */
    public function test_get_usage_statistics(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 5,
            'results' => array(
                (object) array(
                    'category'    => 'greetings',
                    'total_count' => 100,
                ),
                (object) array(
                    'category'    => 'support',
                    'total_count' => 50,
                ),
            ),
        ) );

        // Act.
        $stats = $this->manager->get_usage_statistics( 10 );

        // Assert.
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_templates', $stats );
        $this->assertArrayHasKey( 'by_category', $stats );
    }

    // =========================================================================
    // FR-237: Default Templates - TC-237-xxx
    // =========================================================================

    /**
     * Test TC-237-001: Get default template for chatbot.
     *
     * @test
     * @covers Template_Manager::get_default_template
     * Implements: TC-237-001, FR-237
     */
    public function test_get_default_template_for_chatbot(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'              => 1,
                    'chatbot_id'      => 10,
                    'name'            => 'Default',
                    'title'           => 'Default Template',
                    'content'         => 'Welcome!',
                    'category'        => 'greetings',
                    'variables'       => '[]',
                    'quick_replies'   => '[]',
                    'is_active'       => 1,
                    'is_default'      => 1,
                    'created_by'      => 1,
                    'created_at'      => '2026-01-28 10:00:00',
                    'updated_at'      => '2026-01-28 10:00:00',
                    'usage_count'     => 50,
                ),
            ),
        ) );

        // Act.
        $template = $this->manager->get_default_template( 10 );

        // Assert.
        $this->assertIsArray( $template );
        $this->assertSame( 10, $template['chatbot_id'] );
    }

    /**
     * Test TC-237-002: Get default template when none set returns null.
     *
     * @test
     * @covers Template_Manager::get_default_template
     * Implements: TC-237-002, FR-237
     */
    public function test_get_default_template_none_set(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $template = $this->manager->get_default_template( 10 );

        // Assert.
        $this->assertNull( $template );
    }

    // =========================================================================
    // FR-238: Template Import/Export - TC-238-xxx
    // =========================================================================

    /**
     * Test TC-238-001: Export templates.
     *
     * @test
     * @covers Template_Manager::export_templates
     * Implements: TC-238-001, FR-238
     */
    public function test_export_templates(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'              => 1,
                    'chatbot_id'      => 10,
                    'name'            => 'Template 1',
                    'title'           => 'Title 1',
                    'content'         => 'Content 1',
                    'category'        => 'general',
                    'variables'       => '[]',
                    'quick_replies'   => '[]',
                    'is_active'       => 1,
                    'is_default'      => 0,
                    'created_by'      => 1,
                    'created_at'      => '2026-01-28 10:00:00',
                    'updated_at'      => '2026-01-28 10:00:00',
                    'usage_count'     => 0,
                ),
            ),
        ) );

        // Act.
        $export = $this->manager->export_templates( 10 );

        // Assert.
        $this->assertIsArray( $export );
        $this->assertArrayHasKey( 'version', $export );
        $this->assertArrayHasKey( 'exported_at', $export );
        $this->assertArrayHasKey( 'templates', $export );
        $this->assertSame( '1.0', $export['version'] );
    }

    /**
     * Test TC-238-002: Import templates.
     *
     * @test
     * @covers Template_Manager::import_templates
     * Implements: TC-238-002, FR-238
     */
    public function test_import_templates(): void {
        // Arrange.
        $import_data = array(
            'version'     => '1.0',
            'exported_at' => '2026-01-28 10:00:00',
            'templates'   => array(
                array(
                    'name'          => 'Imported Template',
                    'title'         => 'Imported',
                    'content'       => 'Hello!',
                    'category'      => 'greetings',
                    'variables'     => array(),
                    'quick_replies' => array(),
                    'is_active'     => true,
                ),
            ),
        );

        // Act.
        $result = $this->manager->import_templates( $import_data, 10 );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'imported', $result );
        $this->assertArrayHasKey( 'skipped', $result );
        $this->assertArrayHasKey( 'errors', $result );
    }

    /**
     * Test TC-238-003: Import templates with invalid version.
     *
     * @test
     * @covers Template_Manager::import_templates
     * Implements: TC-238-003, FR-238
     */
    public function test_import_templates_invalid_version(): void {
        // Arrange.
        $import_data = array(
            'version'   => '99.0', // Unsupported version.
            'templates' => array(),
        );

        // Act.
        $result = $this->manager->import_templates( $import_data, 10 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'invalid_import_version', $result->get_error_code() );
    }

    /**
     * Test TC-238-004: Import templates with missing templates array.
     *
     * @test
     * @covers Template_Manager::import_templates
     * Implements: TC-238-004, FR-238
     */
    public function test_import_templates_missing_templates_array(): void {
        // Arrange.
        $import_data = array(
            'version' => '1.0',
            // Missing 'templates' key.
        );

        // Act.
        $result = $this->manager->import_templates( $import_data, 10 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'invalid_import_format', $result->get_error_code() );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test empty content template creation fails.
     *
     * @test
     * @covers Template_Manager::create_template
     */
    public function test_create_template_empty_content_fails(): void {
        // Arrange.
        $template_data = array(
            'chatbot_id' => 10,
            'name'       => 'Empty Template',
            'content'    => '', // Empty content.
        );

        // Act.
        $result = $this->manager->create_template( $template_data );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'missing_required_field', $result->get_error_code() );
    }

    /**
     * Test template with very long content.
     *
     * @test
     * @covers Template_Manager::create_template
     */
    public function test_create_template_long_content(): void {
        // Arrange.
        $template_data = array(
            'chatbot_id' => 10,
            'name'       => 'Long Template',
            'content'    => str_repeat( 'This is a long template content. ', 100 ),
        );

        // Act.
        $result = $this->manager->create_template( $template_data );

        // Assert.
        $this->assertNotWPError( $result );
        $this->assertIsInt( $result );
    }

    /**
     * Test process template with special characters in variable names.
     *
     * @test
     * @covers Template_Manager::process_template
     */
    public function test_process_template_special_variable_names(): void {
        // Arrange.
        $template = array(
            'content'   => 'Value: {{special.variable}}',
            'variables' => array( 'special.variable' ),
        );
        $context = array(
            'special.variable' => 'test value',
        );

        // Act.
        $result = $this->manager->process_template( $template, $context );

        // Assert.
        $this->assertSame( 'Value: test value', $result );
    }
}
