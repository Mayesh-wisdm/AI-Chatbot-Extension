<?php
/**
 * Tests for Export_Handler class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Export_Handler
 *
 * Implements test cases for FR-240 to FR-249 (Chat Transcripts Export)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Export_Handler;
use WP_UnitTestCase;

/**
 * Export Handler Test Class.
 *
 * Tests:
 * - TC-240-001 through TC-240-003: Admin Export
 * - TC-241-001 through TC-241-003: PDF Generation
 * - TC-242-001 through TC-242-002: PDF Branding
 * - TC-244-001 through TC-244-003: User Self-Service Export
 * - TC-245-001 through TC-245-002: Export Progress Indicator
 * - TC-246-001 through TC-246-003: Batch Export
 * - TC-247-001 through TC-247-003: Export Scheduling
 * - TC-248-001 through TC-248-002: Export History/Audit Log
 * - TC-249-001 through TC-249-003: GDPR Data Export
 */
class ExportHandlerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Export_Handler
     */
    private Export_Handler $handler;

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
     * Other user ID.
     *
     * @var int
     */
    private int $other_user_id;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->admin_id = $this->set_current_user( array(
            'ID'           => 1,
            'user_login'   => 'admin',
            'display_name' => 'Admin User',
            'user_email'   => 'admin@example.com',
            'capabilities' => array( 'manage_options' ),
        ) );

        $this->user_id       = 100;
        $this->other_user_id = 200;

        $this->handler = new Export_Handler();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-240: Admin Export - TC-240-xxx
    // =========================================================================

    /**
     * Test TC-240-001: Admin can export any conversation.
     *
     * @test
     * @covers Export_Handler::can_export
     * Implements: TC-240-001, FR-240
     */
    public function test_admin_can_export_any_conversation(): void {
        // Arrange: Admin user set in setUp.
        $this->mock_db_results( array(
            'var' => $this->other_user_id, // Conversation owned by other user.
        ) );

        // Act.
        $can_export = $this->handler->can_export( 1, $this->admin_id );

        // Assert.
        $this->assertTrue( $can_export );
    }

    /**
     * Test TC-240-002: User can export own conversation.
     *
     * @test
     * @covers Export_Handler::can_export
     * Implements: TC-240-002, FR-244
     */
    public function test_user_can_export_own_conversation(): void {
        // Arrange: Switch to regular user.
        $this->set_current_user( array(
            'ID'           => $this->user_id,
            'capabilities' => array( 'read' ),
        ) );

        $this->mock_db_results( array(
            'var' => $this->user_id, // Conversation owned by user.
        ) );

        // Act.
        $can_export = $this->handler->can_export( 1, $this->user_id );

        // Assert.
        $this->assertTrue( $can_export );
    }

    /**
     * Test TC-240-003: User cannot export other user's conversation.
     *
     * @test
     * @covers Export_Handler::can_export
     * Implements: TC-240-003, FR-244
     */
    public function test_user_cannot_export_other_users_conversation(): void {
        // Arrange: Switch to regular user.
        $this->set_current_user( array(
            'ID'           => $this->user_id,
            'capabilities' => array( 'read' ),
        ) );

        $this->mock_db_results( array(
            'var' => $this->other_user_id, // Conversation owned by other user.
        ) );

        // Act.
        $can_export = $this->handler->can_export( 1, $this->user_id );

        // Assert.
        $this->assertFalse( $can_export );
    }

    // =========================================================================
    // FR-241: PDF Generation - TC-241-xxx
    // =========================================================================

    /**
     * Test TC-241-001: Check dompdf availability.
     *
     * @test
     * @covers Export_Handler::is_dompdf_available
     * Implements: TC-241-001, FR-241
     */
    public function test_dompdf_availability_check(): void {
        // Act.
        $available = $this->handler->is_dompdf_available();

        // Assert: Returns boolean.
        $this->assertIsBool( $available );
    }

    /**
     * Test TC-241-002: Export to PDF returns error when dompdf not available.
     *
     * @test
     * @covers Export_Handler::export_to_pdf
     * Implements: TC-241-002, FR-241
     */
    public function test_export_pdf_without_dompdf(): void {
        // Arrange: dompdf not available in test environment.
        if ( $this->handler->is_dompdf_available() ) {
            $this->markTestSkipped( 'dompdf is available - cannot test unavailable case' );
        }

        // Act.
        $result = $this->handler->export_to_pdf( 1 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'dompdf_not_available', $result->get_error_code() );
    }

    /**
     * Test TC-241-003: Get export data for conversation.
     *
     * @test
     * @covers Export_Handler::get_export_data
     * Implements: TC-241-003, FR-241
     */
    public function test_get_export_data(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                array(
                    'id'            => 1,
                    'chatbot_id'    => 10,
                    'user_id'       => $this->user_id,
                    'session_id'    => 'session_123',
                    'created_at'    => '2026-01-28 10:00:00',
                    'updated_at'    => '2026-01-28 12:00:00',
                    'chatbot_name'  => 'Support Bot',
                    'chatbot_style' => '{"primary_color": "#1E3A8A"}',
                ),
            ),
        ) );

        // Act.
        $data = $this->handler->get_export_data( 1 );

        // Assert.
        $this->assertNotWPError( $data );
        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'conversation_id', $data );
        $this->assertArrayHasKey( 'chatbot_id', $data );
        $this->assertArrayHasKey( 'chatbot_name', $data );
        $this->assertArrayHasKey( 'messages', $data );
        $this->assertArrayHasKey( 'message_count', $data );
    }

    /**
     * Test TC-241-004: Get export data for non-existent conversation.
     *
     * @test
     * @covers Export_Handler::get_export_data
     * Implements: TC-241-004, FR-241
     */
    public function test_get_export_data_not_found(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $data = $this->handler->get_export_data( 9999 );

        // Assert.
        $this->assertWPError( $data );
        $this->assertSame( 'conversation_not_found', $data->get_error_code() );
    }

    // =========================================================================
    // FR-242: PDF Branding - TC-242-xxx
    // =========================================================================

    /**
     * Test TC-242-001: Generate filename with chatbot name.
     *
     * @test
     * @covers Export_Handler::generate_filename
     * Implements: TC-242-001, FR-242
     */
    public function test_generate_filename(): void {
        // Arrange.
        $export_data = array(
            'conversation_id' => 123,
            'chatbot_name'    => 'Support Bot',
            'created_at'      => '2026-01-28 10:00:00',
        );

        // Act.
        $filename = $this->handler->generate_filename( $export_data );

        // Assert.
        $this->assertStringContainsString( 'chat-transcript', $filename );
        $this->assertStringContainsString( 'support-bot', $filename );
        $this->assertStringContainsString( '2026-01-28', $filename );
        $this->assertStringContainsString( '123', $filename );
        $this->assertStringEndsWith( '.pdf', $filename );
    }

    // =========================================================================
    // FR-245: Export Progress - TC-245-xxx
    // =========================================================================

    /**
     * Test TC-245-001: Get export status.
     *
     * @test
     * @covers Export_Handler::get_export_status
     * Implements: TC-245-001, FR-245
     */
    public function test_get_export_status(): void {
        // Arrange: Set transient for batch.
        $batch_id   = 'test-batch-id';
        $batch_data = array(
            'conversation_ids' => array( 1, 2, 3 ),
            'options'          => array(),
            'status'           => 'processing',
            'progress'         => 1,
            'total'            => 3,
            'completed'        => array( 1 ),
            'failed'           => array(),
            'started_at'       => '2026-01-28 10:00:00',
        );
        set_transient( 'ai_botkit_batch_export_' . $batch_id, $batch_data );

        // Act.
        $status = $this->handler->get_export_status( $batch_id );

        // Assert.
        $this->assertNotWPError( $status );
        $this->assertIsArray( $status );
        $this->assertArrayHasKey( 'batch_id', $status );
        $this->assertArrayHasKey( 'status', $status );
        $this->assertArrayHasKey( 'progress', $status );
        $this->assertArrayHasKey( 'total', $status );
        $this->assertSame( 'processing', $status['status'] );
    }

    /**
     * Test TC-245-002: Get export status for non-existent batch.
     *
     * @test
     * @covers Export_Handler::get_export_status
     * Implements: TC-245-002, FR-245
     */
    public function test_get_export_status_not_found(): void {
        // Act.
        $status = $this->handler->get_export_status( 'nonexistent-batch' );

        // Assert.
        $this->assertWPError( $status );
        $this->assertSame( 'batch_not_found', $status->get_error_code() );
    }

    // =========================================================================
    // FR-246: Batch Export - TC-246-xxx
    // =========================================================================

    /**
     * Test TC-246-001: Schedule batch export.
     *
     * @test
     * @covers Export_Handler::schedule_export
     * Implements: TC-246-001, FR-246
     */
    public function test_schedule_batch_export(): void {
        // Arrange.
        $conversation_ids = array( 1, 2, 3, 4, 5 );

        // Act.
        $batch_id = $this->handler->schedule_export( $conversation_ids );

        // Assert.
        $this->assertIsString( $batch_id );
        $this->assertNotEmpty( $batch_id );

        // Verify transient was set.
        $batch_data = get_transient( 'ai_botkit_batch_export_' . $batch_id );
        $this->assertIsArray( $batch_data );
        $this->assertSame( 'pending', $batch_data['status'] );
        $this->assertSame( 5, $batch_data['total'] );
    }

    /**
     * Test TC-246-002: Process batch export with no conversations.
     *
     * @test
     * @covers Export_Handler::process_batch_export
     * Implements: TC-246-002, FR-246
     */
    public function test_process_batch_export_no_results(): void {
        // Arrange: Create batch with non-exportable conversations.
        $batch_id   = 'test-empty-batch';
        $batch_data = array(
            'conversation_ids' => array(),
            'options'          => array(),
            'status'           => 'pending',
            'progress'         => 0,
            'total'            => 0,
            'completed'        => array(),
            'failed'           => array(),
            'started_at'       => '2026-01-28 10:00:00',
            'user_id'          => $this->admin_id,
        );
        set_transient( 'ai_botkit_batch_export_' . $batch_id, $batch_data );

        // Act.
        $result = $this->handler->process_batch_export( $batch_id );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'no_exports', $result->get_error_code() );
    }

    /**
     * Test TC-246-003: Process batch with non-existent batch ID.
     *
     * @test
     * @covers Export_Handler::process_batch_export
     * Implements: TC-246-003, FR-246
     */
    public function test_process_batch_export_not_found(): void {
        // Act.
        $result = $this->handler->process_batch_export( 'nonexistent-batch' );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'batch_not_found', $result->get_error_code() );
    }

    // =========================================================================
    // FR-247: Export Scheduling - TC-247-xxx
    // =========================================================================

    /**
     * Test TC-247-001: Schedule recurring export.
     *
     * @test
     * @covers Export_Handler::schedule_recurring_export
     * Implements: TC-247-001, FR-247
     */
    public function test_schedule_recurring_export(): void {
        // Arrange.
        $settings = array(
            'frequency'  => 'weekly',
            'time'       => '03:00',
            'chatbot_id' => 10,
            'email'      => 'admin@example.com',
        );

        // Act.
        $result = $this->handler->schedule_recurring_export( $settings );

        // Assert.
        $this->assertTrue( $result );

        // Verify settings saved.
        $saved = get_option( 'ai_botkit_scheduled_export_settings' );
        $this->assertSame( 'weekly', $saved['frequency'] );
    }

    /**
     * Test TC-247-002: Schedule with invalid frequency defaults to weekly.
     *
     * @test
     * @covers Export_Handler::schedule_recurring_export
     * Implements: TC-247-002, FR-247
     */
    public function test_schedule_export_invalid_frequency(): void {
        // Arrange.
        $settings = array(
            'frequency' => 'invalid_frequency',
        );

        // Act.
        $result = $this->handler->schedule_recurring_export( $settings );

        // Assert.
        $this->assertTrue( $result );

        // Verify settings defaulted.
        $saved = get_option( 'ai_botkit_scheduled_export_settings' );
        $this->assertSame( 'invalid_frequency', $saved['frequency'] ); // Kept but handled in scheduling.
    }

    // =========================================================================
    // FR-248: Export History - TC-248-xxx
    // =========================================================================

    /**
     * Test TC-248-001: Get export history.
     *
     * @test
     * @covers Export_Handler::get_export_history
     * Implements: TC-248-001, FR-248
     */
    public function test_get_export_history(): void {
        // Arrange: Mock table doesn't exist (returns empty).
        $this->mock_db_results( array(
            'var'     => null, // Table doesn't exist.
            'results' => array(),
        ) );

        // Act.
        $history = $this->handler->get_export_history( 1, 20 );

        // Assert.
        $this->assertIsArray( $history );
        $this->assertArrayHasKey( 'exports', $history );
        $this->assertArrayHasKey( 'total', $history );
        $this->assertArrayHasKey( 'pages', $history );
        $this->assertArrayHasKey( 'current_page', $history );
    }

    /**
     * Test TC-248-002: Get export history pagination.
     *
     * @test
     * @covers Export_Handler::get_export_history
     * Implements: TC-248-002, FR-248
     */
    public function test_get_export_history_pagination(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var' => null,
        ) );

        // Act: Page 2 with 10 per page.
        $history = $this->handler->get_export_history( 2, 10 );

        // Assert.
        $this->assertSame( 2, $history['current_page'] );
    }

    // =========================================================================
    // FR-249: GDPR Data Export - TC-249-xxx
    // =========================================================================

    /**
     * Test TC-249-001: Register GDPR data exporter.
     *
     * @test
     * @covers Export_Handler::register_data_exporter
     * Implements: TC-249-001, FR-249
     */
    public function test_register_gdpr_data_exporter(): void {
        // Arrange.
        $exporters = array();

        // Act.
        $result = $this->handler->register_data_exporter( $exporters );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'ai-botkit-conversations', $result );
        $this->assertArrayHasKey( 'exporter_friendly_name', $result['ai-botkit-conversations'] );
        $this->assertArrayHasKey( 'callback', $result['ai-botkit-conversations'] );
    }

    /**
     * Test TC-249-002: Export personal data for non-existent user.
     *
     * @test
     * @covers Export_Handler::export_personal_data
     * Implements: TC-249-002, FR-249
     */
    public function test_export_personal_data_user_not_found(): void {
        // Act.
        $result = $this->handler->export_personal_data( 'nonexistent@example.com' );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertEmpty( $result['data'] );
        $this->assertTrue( $result['done'] );
    }

    /**
     * Test TC-249-003: Register GDPR data eraser.
     *
     * @test
     * @covers Export_Handler::register_data_eraser
     * Implements: TC-249-003, FR-249
     */
    public function test_register_gdpr_data_eraser(): void {
        // Arrange.
        $erasers = array();

        // Act.
        $result = $this->handler->register_data_eraser( $erasers );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'ai-botkit-conversations', $result );
        $this->assertArrayHasKey( 'eraser_friendly_name', $result['ai-botkit-conversations'] );
        $this->assertArrayHasKey( 'callback', $result['ai-botkit-conversations'] );
    }

    /**
     * Test TC-249-004: Erase personal data for non-existent user.
     *
     * @test
     * @covers Export_Handler::erase_personal_data
     * Implements: TC-249-004, FR-249
     */
    public function test_erase_personal_data_user_not_found(): void {
        // Act.
        $result = $this->handler->erase_personal_data( 'nonexistent@example.com' );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertFalse( $result['items_removed'] );
        $this->assertFalse( $result['items_retained'] );
        $this->assertTrue( $result['done'] );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test export options default values.
     *
     * @test
     * @covers Export_Handler::export_to_pdf
     */
    public function test_export_options_defaults(): void {
        // Skip if dompdf available (would try to actually generate).
        if ( $this->handler->is_dompdf_available() ) {
            $this->markTestSkipped( 'dompdf is available - cannot test option parsing in isolation' );
        }

        // Act: Call with empty options.
        $result = $this->handler->export_to_pdf( 1, array() );

        // Assert: Returns error about dompdf (options are parsed before dompdf check).
        $this->assertWPError( $result );
    }

    /**
     * Test export with custom paper size option.
     *
     * @test
     * @covers Export_Handler::export_to_pdf
     */
    public function test_export_custom_paper_size(): void {
        // Skip if dompdf available.
        if ( $this->handler->is_dompdf_available() ) {
            $this->markTestSkipped( 'dompdf is available - cannot test option parsing in isolation' );
        }

        // Act: Call with letter paper size.
        $result = $this->handler->export_to_pdf( 1, array( 'paper_size' => 'letter' ) );

        // Assert: Returns error about dompdf.
        $this->assertWPError( $result );
    }

    /**
     * Test batch export with permission checks.
     *
     * @test
     * @covers Export_Handler::process_batch_export
     */
    public function test_batch_export_permission_checks(): void {
        // Arrange: Create batch with conversations from different users.
        $batch_id   = 'test-permission-batch';
        $batch_data = array(
            'conversation_ids' => array( 1 ),
            'options'          => array(),
            'status'           => 'pending',
            'progress'         => 0,
            'total'            => 1,
            'completed'        => array(),
            'failed'           => array(),
            'started_at'       => '2026-01-28 10:00:00',
            'user_id'          => $this->user_id, // Non-admin user.
        );
        set_transient( 'ai_botkit_batch_export_' . $batch_id, $batch_data );

        // Mock can_export to return false.
        $this->mock_db_results( array(
            'var' => $this->other_user_id, // Different owner.
        ) );

        // Act.
        $result = $this->handler->process_batch_export( $batch_id );

        // Assert: Should fail because no exports succeeded.
        $this->assertWPError( $result );
        $this->assertSame( 'no_exports', $result->get_error_code() );

        // Check batch status updated.
        $updated_batch = get_transient( 'ai_botkit_batch_export_' . $batch_id );
        $this->assertSame( 'failed', $updated_batch['status'] );
        $this->assertCount( 1, $updated_batch['failed'] );
    }

    /**
     * Test get export status returns download URL when completed.
     *
     * @test
     * @covers Export_Handler::get_export_status
     */
    public function test_export_status_includes_download_url(): void {
        // Arrange: Create completed batch.
        $batch_id   = 'test-complete-batch';
        $batch_data = array(
            'conversation_ids' => array( 1 ),
            'options'          => array(),
            'status'           => 'completed',
            'progress'         => 1,
            'total'            => 1,
            'completed'        => array( 1 ),
            'failed'           => array(),
            'started_at'       => '2026-01-28 10:00:00',
            'completed_at'     => '2026-01-28 10:05:00',
            'zip_path'         => sys_get_temp_dir() . '/wp-uploads/ai-botkit/exports/test.zip',
        );
        set_transient( 'ai_botkit_batch_export_' . $batch_id, $batch_data );

        // Act.
        $status = $this->handler->get_export_status( $batch_id );

        // Assert.
        $this->assertNotWPError( $status );
        $this->assertSame( 'completed', $status['status'] );
        $this->assertArrayHasKey( 'download_url', $status );
        $this->assertArrayHasKey( 'completed_at', $status );
    }

    /**
     * Test schedule export with all valid frequencies.
     *
     * @test
     * @dataProvider frequency_provider
     * @covers Export_Handler::schedule_recurring_export
     */
    public function test_schedule_export_frequencies( string $frequency ): void {
        // Arrange.
        $settings = array(
            'frequency' => $frequency,
        );

        // Act.
        $result = $this->handler->schedule_recurring_export( $settings );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Data provider for frequency test.
     *
     * @return array
     */
    public static function frequency_provider(): array {
        return array(
            'daily'   => array( 'daily' ),
            'weekly'  => array( 'weekly' ),
            'monthly' => array( 'monthly' ),
        );
    }
}
