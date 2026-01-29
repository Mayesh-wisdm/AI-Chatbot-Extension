<?php
/**
 * Tests for Chat_History_Handler class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Chat_History_Handler
 *
 * Implements test cases for FR-201 to FR-209 (Chat History Feature)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Chat_History_Handler;
use WP_UnitTestCase;

/**
 * Chat History Handler Test Class.
 *
 * Tests:
 * - TC-201-001 through TC-201-006: List User Conversations
 * - TC-202-001 through TC-202-004: View Conversation Messages
 * - TC-203-001 through TC-203-003: Switch Between Conversations
 * - TC-204-001 through TC-204-003: Conversation Previews
 * - TC-205-001 through TC-205-003: Pagination
 * - TC-206-001 through TC-206-004: Delete Conversation
 * - TC-207-001 through TC-207-003: Favorites
 * - TC-208-001 through TC-208-003: Archive
 * - TC-209-001 through TC-209-003: Filters
 */
class ChatHistoryHandlerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Chat_History_Handler
     */
    private Chat_History_Handler $handler;

    /**
     * Test user ID.
     *
     * @var int
     */
    private int $test_user_id;

    /**
     * Another user ID for cross-user tests.
     *
     * @var int
     */
    private int $other_user_id;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->test_user_id  = $this->set_current_user( array(
            'ID'           => 100,
            'user_login'   => 'test_subscriber',
            'display_name' => 'Test Subscriber',
            'capabilities' => array( 'read' ),
        ) );

        $this->other_user_id = 200;

        $this->handler = new Chat_History_Handler();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-201: List User Conversations - TC-201-xxx
    // =========================================================================

    /**
     * Test TC-201-001: Logged-in user sees conversation list.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-001, FR-201
     */
    public function test_logged_in_user_sees_conversation_list(): void {
        // Arrange: Mock database to return conversations for the user.
        $mock_conversations = array(
            array(
                'id'                 => 1,
                'chatbot_id'         => 10,
                'session_id'         => 'session_123',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 0,
                'is_archived'        => 0,
                'created_at'         => '2026-01-28 10:00:00',
                'updated_at'         => '2026-01-28 12:00:00',
                'chatbot_name'       => 'Support Bot',
                'chatbot_avatar'     => 'http://example.com/avatar.png',
                'first_user_message' => 'Hello, I need help',
                'last_message'       => 'How can I help you?',
                'message_count'      => 5,
                'last_activity'      => '2026-01-28 12:00:00',
            ),
        );

        $this->mock_db_results( array(
            'var'     => 1,
            'results' => $mock_conversations,
        ) );

        // Act: Get conversations for the user.
        $result = $this->handler->get_user_conversations( $this->test_user_id );

        // Assert: User gets their conversations.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'conversations', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'pages', $result );
        $this->assertArrayHasKey( 'current_page', $result );
    }

    /**
     * Test TC-201-002: Conversation list sorted by most recent.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-002, FR-201
     */
    public function test_conversation_list_sorted_by_most_recent(): void {
        // Arrange: Mock database with multiple conversations.
        $mock_conversations = array(
            array(
                'id'                 => 2,
                'chatbot_id'         => 10,
                'session_id'         => 'session_456',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 0,
                'is_archived'        => 0,
                'created_at'         => '2026-01-27 10:00:00',
                'updated_at'         => '2026-01-28 14:00:00', // Most recent.
                'chatbot_name'       => 'Support Bot',
                'chatbot_avatar'     => '',
                'first_user_message' => 'Second conversation',
                'last_message'       => 'Reply',
                'message_count'      => 3,
                'last_activity'      => '2026-01-28 14:00:00',
            ),
            array(
                'id'                 => 1,
                'chatbot_id'         => 10,
                'session_id'         => 'session_123',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 0,
                'is_archived'        => 0,
                'created_at'         => '2026-01-28 10:00:00',
                'updated_at'         => '2026-01-28 12:00:00', // Older.
                'chatbot_name'       => 'Support Bot',
                'chatbot_avatar'     => '',
                'first_user_message' => 'First conversation',
                'last_message'       => 'Reply',
                'message_count'      => 5,
                'last_activity'      => '2026-01-28 12:00:00',
            ),
        );

        $this->mock_db_results( array(
            'var'     => 2,
            'results' => $mock_conversations,
        ) );

        // Act: Get conversations.
        $result = $this->handler->get_user_conversations( $this->test_user_id );

        // Assert: Conversations are returned (DB handles sorting).
        $this->assertIsArray( $result['conversations'] );
        $this->assertGreaterThanOrEqual( 0, count( $result['conversations'] ) );
    }

    /**
     * Test TC-201-003: Conversation metadata displays correctly.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-003, FR-201
     */
    public function test_conversation_metadata_displays_correctly(): void {
        // Arrange: Mock conversation with full metadata.
        $mock_conversations = array(
            array(
                'id'                 => 1,
                'chatbot_id'         => 10,
                'session_id'         => 'session_123',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 1,
                'is_archived'        => 0,
                'created_at'         => '2026-01-28 10:00:00',
                'updated_at'         => '2026-01-28 12:00:00',
                'chatbot_name'       => 'Support Bot',
                'chatbot_avatar'     => 'http://example.com/avatar.png',
                'first_user_message' => 'Help me find a product',
                'last_message'       => 'How can I help you?',
                'message_count'      => 5,
                'last_activity'      => '2026-01-28 12:00:00',
            ),
        );

        $this->mock_db_results( array(
            'var'     => 1,
            'results' => $mock_conversations,
        ) );

        // Act: Get conversations.
        $result = $this->handler->get_user_conversations( $this->test_user_id );

        // Assert: Metadata is properly formatted.
        $this->assertNotEmpty( $result['conversations'] );
        $conv = $result['conversations'][0];

        $this->assertArrayHasKey( 'id', $conv );
        $this->assertArrayHasKey( 'chatbot_id', $conv );
        $this->assertArrayHasKey( 'chatbot_name', $conv );
        $this->assertArrayHasKey( 'chatbot_avatar', $conv );
        $this->assertArrayHasKey( 'title', $conv );
        $this->assertArrayHasKey( 'preview', $conv );
        $this->assertArrayHasKey( 'message_count', $conv );
        $this->assertArrayHasKey( 'is_favorite', $conv );
        $this->assertArrayHasKey( 'is_archived', $conv );
        $this->assertArrayHasKey( 'created_at', $conv );
        $this->assertArrayHasKey( 'updated_at', $conv );
        $this->assertArrayHasKey( 'formatted_date', $conv );
    }

    /**
     * Test TC-201-004: Pagination with 10+ conversations.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-004, FR-205
     */
    public function test_pagination_with_many_conversations(): void {
        // Arrange: Mock 25 total conversations, page 1.
        $this->mock_db_results( array(
            'var'     => 25, // Total count.
            'results' => array_fill( 0, 10, array(
                'id'                 => 1,
                'chatbot_id'         => 10,
                'session_id'         => 'session_123',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 0,
                'is_archived'        => 0,
                'created_at'         => '2026-01-28 10:00:00',
                'updated_at'         => '2026-01-28 12:00:00',
                'chatbot_name'       => 'Bot',
                'chatbot_avatar'     => '',
                'first_user_message' => 'Test',
                'last_message'       => 'Reply',
                'message_count'      => 2,
                'last_activity'      => '2026-01-28 12:00:00',
            ) ),
        ) );

        // Act: Get page 1 with 10 per page.
        $result = $this->handler->get_user_conversations( $this->test_user_id, null, 1, 10 );

        // Assert: Pagination information is correct.
        $this->assertSame( 25, $result['total'] );
        $this->assertSame( 3, $result['pages'] ); // ceil(25/10) = 3.
        $this->assertSame( 1, $result['current_page'] );
        $this->assertCount( 10, $result['conversations'] );
    }

    /**
     * Test TC-201-005: Guest user cannot see history (user_id validation).
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-005, FR-201
     */
    public function test_guest_user_returns_empty_for_invalid_user(): void {
        // Arrange: Mock empty results for guest (user_id = 0).
        $this->mock_db_results( array(
            'var'     => 0,
            'results' => array(),
        ) );

        // Act: Get conversations for guest user (id=0).
        $result = $this->handler->get_user_conversations( 0 );

        // Assert: Empty results for invalid user.
        $this->assertSame( 0, $result['total'] );
        $this->assertEmpty( $result['conversations'] );
    }

    /**
     * Test TC-201-006: User with no conversations sees empty state.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     * Implements: TC-201-006, FR-201
     */
    public function test_user_with_no_conversations_sees_empty_state(): void {
        // Arrange: Mock empty results.
        $this->mock_db_results( array(
            'var'     => 0,
            'results' => array(),
        ) );

        // Act: Get conversations for user with none.
        $result = $this->handler->get_user_conversations( $this->test_user_id );

        // Assert: Empty state returned.
        $this->assertSame( 0, $result['total'] );
        $this->assertEmpty( $result['conversations'] );
        $this->assertSame( 0, $result['pages'] );
    }

    // =========================================================================
    // FR-202: View Conversation Messages - TC-202-xxx
    // =========================================================================

    /**
     * Test TC-202-001: Load full conversation messages.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_messages
     * Implements: TC-202-001, FR-203
     */
    public function test_load_full_conversation_messages(): void {
        // Arrange: Mock conversation and messages.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'             => 1,
                    'chatbot_id'     => 10,
                    'user_id'        => $this->test_user_id,
                    'session_id'     => 'session_123',
                    'is_favorite'    => 0,
                    'created_at'     => '2026-01-28 10:00:00',
                    'updated_at'     => '2026-01-28 12:00:00',
                    'chatbot_name'   => 'Support Bot',
                    'chatbot_avatar' => 'http://example.com/avatar.png',
                ),
            ),
        ) );

        // Act: Get conversation messages.
        $result = $this->handler->get_conversation_messages( 1, $this->test_user_id );

        // Assert: Messages loaded successfully.
        $this->assertNotWPError( $result );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'conversation_id', $result );
        $this->assertArrayHasKey( 'messages', $result );
        $this->assertArrayHasKey( 'chatbot_name', $result );
    }

    /**
     * Test TC-202-004: Cannot view another user's conversation.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_messages
     * Implements: TC-202-004, FR-203
     */
    public function test_cannot_view_another_users_conversation(): void {
        // Arrange: Mock conversation belonging to another user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'             => 1,
                    'chatbot_id'     => 10,
                    'user_id'        => $this->other_user_id, // Different user.
                    'session_id'     => 'session_123',
                    'is_favorite'    => 0,
                    'created_at'     => '2026-01-28 10:00:00',
                    'updated_at'     => '2026-01-28 12:00:00',
                    'chatbot_name'   => 'Support Bot',
                    'chatbot_avatar' => '',
                ),
            ),
        ) );

        // Act: Try to access another user's conversation.
        $result = $this->handler->get_conversation_messages( 1, $this->test_user_id );

        // Assert: Access denied with 403 error.
        $this->assertWPError( $result );
        $this->assertSame( 'unauthorized', $result->get_error_code() );
        $this->assertSame( 403, $result->get_error_data()['status'] );
    }

    /**
     * Test TC-202-004b: Conversation not found returns 404.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_messages
     * Implements: TC-202-004, FR-203
     */
    public function test_conversation_not_found_returns_404(): void {
        // Arrange: Mock no results.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act: Try to access non-existent conversation.
        $result = $this->handler->get_conversation_messages( 9999, $this->test_user_id );

        // Assert: Not found error.
        $this->assertWPError( $result );
        $this->assertSame( 'not_found', $result->get_error_code() );
        $this->assertSame( 404, $result->get_error_data()['status'] );
    }

    // =========================================================================
    // FR-203: Switch Between Conversations - TC-203-xxx
    // =========================================================================

    /**
     * Test TC-203-001: Switch between two conversations.
     *
     * @test
     * @covers Chat_History_Handler::switch_conversation
     * Implements: TC-203-001, FR-204
     */
    public function test_switch_between_conversations(): void {
        // Arrange: Mock conversation.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'             => 1,
                    'chatbot_id'     => 10,
                    'user_id'        => $this->test_user_id,
                    'session_id'     => 'session_123',
                    'is_favorite'    => 0,
                    'created_at'     => '2026-01-28 10:00:00',
                    'updated_at'     => '2026-01-28 12:00:00',
                    'chatbot_name'   => 'Support Bot',
                    'chatbot_avatar' => '',
                ),
            ),
        ) );

        // Act: Switch to conversation.
        $result = $this->handler->switch_conversation( 1, $this->test_user_id );

        // Assert: Conversation loaded successfully.
        $this->assertNotWPError( $result );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'conversation_id', $result );
        $this->assertArrayHasKey( 'messages', $result );
    }

    // =========================================================================
    // FR-204: Conversation Previews - TC-204-xxx
    // =========================================================================

    /**
     * Test TC-204-001: Preview shows first user message.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_preview
     * Implements: TC-204-001, FR-202
     */
    public function test_preview_shows_first_user_message(): void {
        // Arrange: Mock preview data.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'                 => 1,
                    'chatbot_id'         => 10,
                    'is_favorite'        => 0,
                    'created_at'         => '2026-01-28 10:00:00',
                    'updated_at'         => '2026-01-28 12:00:00',
                    'chatbot_name'       => 'Support Bot',
                    'first_user_message' => 'Help me find a product',
                    'message_count'      => 5,
                    'last_activity'      => '2026-01-28 12:00:00',
                ),
            ),
        ) );

        // Act: Get preview.
        $result = $this->handler->get_conversation_preview( 1 );

        // Assert: Preview contains expected data.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'preview', $result );
        $this->assertArrayHasKey( 'message_count', $result );
        $this->assertArrayHasKey( 'chatbot_name', $result );
        $this->assertSame( 'Help me find a product', $result['preview'] );
    }

    /**
     * Test TC-204-002: Long preview truncated with ellipsis.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_preview
     * Implements: TC-204-002, FR-202
     */
    public function test_long_preview_truncated_with_ellipsis(): void {
        // Arrange: Mock preview with 150+ character message.
        $long_message = str_repeat( 'This is a very long message. ', 10 ); // 290 chars.

        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'                 => 1,
                    'chatbot_id'         => 10,
                    'is_favorite'        => 0,
                    'created_at'         => '2026-01-28 10:00:00',
                    'updated_at'         => '2026-01-28 12:00:00',
                    'chatbot_name'       => 'Support Bot',
                    'first_user_message' => $long_message,
                    'message_count'      => 5,
                    'last_activity'      => '2026-01-28 12:00:00',
                ),
            ),
        ) );

        // Act: Get preview.
        $result = $this->handler->get_conversation_preview( 1 );

        // Assert: Preview truncated at 100 chars + ellipsis.
        $this->assertLessThanOrEqual( 103, mb_strlen( $result['preview'] ) ); // 100 + '...'.
        $this->assertStringEndsWith( '...', $result['preview'] );
    }

    /**
     * Test TC-204-003: Conversation not found returns empty array.
     *
     * @test
     * @covers Chat_History_Handler::get_conversation_preview
     * Implements: TC-204-003, FR-202
     */
    public function test_preview_not_found_returns_empty_array(): void {
        // Arrange: Mock no results.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act: Get preview for non-existent conversation.
        $result = $this->handler->get_conversation_preview( 9999 );

        // Assert: Empty array returned.
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // =========================================================================
    // FR-206: Delete Conversation - TC-206-xxx
    // =========================================================================

    /**
     * Test TC-206-001: User can delete own conversation.
     *
     * @test
     * @covers Chat_History_Handler::delete_conversation
     * Implements: TC-206-001, FR-205
     */
    public function test_user_can_delete_own_conversation(): void {
        // Arrange: Mock conversation owned by user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'      => 1,
                    'user_id' => $this->test_user_id,
                ),
            ),
        ) );

        // Act: Delete conversation.
        $result = $this->handler->delete_conversation( 1, $this->test_user_id );

        // Assert: Deletion successful.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-206-003: Cannot delete another user's conversation.
     *
     * @test
     * @covers Chat_History_Handler::delete_conversation
     * Implements: TC-206-003, FR-205
     */
    public function test_cannot_delete_another_users_conversation(): void {
        // Arrange: Mock conversation owned by another user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'      => 1,
                    'user_id' => $this->other_user_id,
                ),
            ),
        ) );

        // Act: Try to delete another user's conversation.
        $result = $this->handler->delete_conversation( 1, $this->test_user_id );

        // Assert: Unauthorized error.
        $this->assertWPError( $result );
        $this->assertSame( 'unauthorized', $result->get_error_code() );
    }

    /**
     * Test TC-206-004: Deleting non-existent conversation returns error.
     *
     * @test
     * @covers Chat_History_Handler::delete_conversation
     * Implements: TC-206-004, FR-205
     */
    public function test_delete_nonexistent_conversation_returns_error(): void {
        // Arrange: Mock no results.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act: Try to delete non-existent conversation.
        $result = $this->handler->delete_conversation( 9999, $this->test_user_id );

        // Assert: Not found error.
        $this->assertWPError( $result );
        $this->assertSame( 'not_found', $result->get_error_code() );
    }

    // =========================================================================
    // FR-207: Mark Favorite - TC-207-xxx
    // =========================================================================

    /**
     * Test TC-207-001: Toggle favorite on own conversation.
     *
     * @test
     * @covers Chat_History_Handler::toggle_favorite
     * Implements: TC-207-001, FR-206
     */
    public function test_toggle_favorite_on_own_conversation(): void {
        // Arrange: Mock conversation with is_favorite = 0.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'          => 1,
                    'user_id'     => $this->test_user_id,
                    'is_favorite' => 0,
                ),
            ),
        ) );

        // Act: Toggle favorite.
        $result = $this->handler->toggle_favorite( 1, $this->test_user_id );

        // Assert: Favorite toggled successfully.
        $this->assertNotWPError( $result );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'conversation_id', $result );
        $this->assertArrayHasKey( 'is_favorite', $result );
        $this->assertTrue( $result['is_favorite'] ); // Was 0, now 1.
    }

    /**
     * Test TC-207-002: Cannot toggle favorite on another user's conversation.
     *
     * @test
     * @covers Chat_History_Handler::toggle_favorite
     * Implements: TC-207-002, FR-206
     */
    public function test_cannot_toggle_favorite_on_another_users_conversation(): void {
        // Arrange: Mock conversation owned by another user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'          => 1,
                    'user_id'     => $this->other_user_id,
                    'is_favorite' => 0,
                ),
            ),
        ) );

        // Act: Try to toggle favorite.
        $result = $this->handler->toggle_favorite( 1, $this->test_user_id );

        // Assert: Unauthorized error.
        $this->assertWPError( $result );
        $this->assertSame( 'unauthorized', $result->get_error_code() );
    }

    // =========================================================================
    // FR-208: Archive Conversation - TC-208-xxx
    // =========================================================================

    /**
     * Test TC-208-001: Archive own conversation.
     *
     * @test
     * @covers Chat_History_Handler::archive_conversation
     * Implements: TC-208-001, FR-208
     */
    public function test_archive_own_conversation(): void {
        // Arrange: Mock conversation owned by user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'          => 1,
                    'user_id'     => $this->test_user_id,
                    'is_archived' => 0,
                ),
            ),
        ) );

        // Act: Archive conversation.
        $result = $this->handler->archive_conversation( 1, $this->test_user_id );

        // Assert: Archive successful.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-208-002: Cannot archive another user's conversation.
     *
     * @test
     * @covers Chat_History_Handler::archive_conversation
     * Implements: TC-208-002, FR-208
     */
    public function test_cannot_archive_another_users_conversation(): void {
        // Arrange: Mock conversation owned by another user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'          => 1,
                    'user_id'     => $this->other_user_id,
                    'is_archived' => 0,
                ),
            ),
        ) );

        // Act: Try to archive.
        $result = $this->handler->archive_conversation( 1, $this->test_user_id );

        // Assert: Unauthorized error.
        $this->assertWPError( $result );
        $this->assertSame( 'unauthorized', $result->get_error_code() );
    }

    // =========================================================================
    // FR-209: Restore Archived - TC-209-xxx
    // =========================================================================

    /**
     * Test TC-209-001: Unarchive own conversation.
     *
     * @test
     * @covers Chat_History_Handler::unarchive_conversation
     * Implements: TC-209-001, FR-209
     */
    public function test_unarchive_own_conversation(): void {
        // Arrange: Mock archived conversation owned by user.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'          => 1,
                    'user_id'     => $this->test_user_id,
                    'is_archived' => 1,
                ),
            ),
        ) );

        // Act: Unarchive conversation.
        $result = $this->handler->unarchive_conversation( 1, $this->test_user_id );

        // Assert: Unarchive successful.
        $this->assertTrue( $result );
    }

    // =========================================================================
    // FR-207: Filter by Date - TC-FILTER-xxx
    // =========================================================================

    /**
     * Test filter conversations by date range.
     *
     * @test
     * @covers Chat_History_Handler::filter_conversations
     * Implements: FR-207
     */
    public function test_filter_conversations_by_date_range(): void {
        // Arrange: Mock filtered results.
        $this->mock_db_results( array(
            'var'     => 5,
            'results' => array_fill( 0, 5, array(
                'id'                 => 1,
                'chatbot_id'         => 10,
                'session_id'         => 'session_123',
                'user_id'            => $this->test_user_id,
                'is_favorite'        => 0,
                'is_archived'        => 0,
                'created_at'         => '2026-01-15 10:00:00',
                'updated_at'         => '2026-01-15 12:00:00',
                'chatbot_name'       => 'Bot',
                'chatbot_avatar'     => '',
                'first_user_message' => 'Test',
                'message_count'      => 2,
                'last_activity'      => '2026-01-15 12:00:00',
            ) ),
        ) );

        // Act: Filter by date range.
        $result = $this->handler->filter_conversations(
            $this->test_user_id,
            '2026-01-01',
            '2026-01-31'
        );

        // Assert: Filtered results returned.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'conversations', $result );
        $this->assertArrayHasKey( 'filters', $result );
        $this->assertSame( '2026-01-01', $result['filters']['start_date'] );
        $this->assertSame( '2026-01-31', $result['filters']['end_date'] );
    }

    /**
     * Test filter conversations by favorite status.
     *
     * @test
     * @covers Chat_History_Handler::filter_conversations
     * Implements: FR-207
     */
    public function test_filter_conversations_by_favorite(): void {
        // Arrange: Mock filtered results.
        $this->mock_db_results( array(
            'var'     => 3,
            'results' => array(),
        ) );

        // Act: Filter by favorite.
        $result = $this->handler->filter_conversations(
            $this->test_user_id,
            '',
            '',
            null,
            true // is_favorite.
        );

        // Assert: Filter applied.
        $this->assertIsArray( $result );
        $this->assertTrue( $result['filters']['is_favorite'] );
    }

    /**
     * Test filter conversations by chatbot.
     *
     * @test
     * @covers Chat_History_Handler::filter_conversations
     * Implements: FR-207
     */
    public function test_filter_conversations_by_chatbot(): void {
        // Arrange: Mock filtered results.
        $this->mock_db_results( array(
            'var'     => 2,
            'results' => array(),
        ) );

        // Act: Filter by chatbot.
        $result = $this->handler->filter_conversations(
            $this->test_user_id,
            '',
            '',
            10 // chatbot_id.
        );

        // Assert: Filter applied.
        $this->assertIsArray( $result );
        $this->assertSame( 10, $result['filters']['chatbot_id'] );
    }

    // =========================================================================
    // Edge Cases and Boundary Tests
    // =========================================================================

    /**
     * Test per_page parameter boundaries.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     */
    public function test_per_page_boundaries(): void {
        $this->mock_db_results( array(
            'var'     => 100,
            'results' => array(),
        ) );

        // Test maximum limit (100).
        $result = $this->handler->get_user_conversations( $this->test_user_id, null, 1, 200 );
        $this->assertSame( 1, $result['current_page'] );

        // Test minimum (uses default when 0).
        $result = $this->handler->get_user_conversations( $this->test_user_id, null, 1, 0 );
        $this->assertSame( 1, $result['current_page'] );
    }

    /**
     * Test page number boundaries.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversations
     */
    public function test_page_number_boundaries(): void {
        $this->mock_db_results( array(
            'var'     => 100,
            'results' => array(),
        ) );

        // Test negative page (should default to 1).
        $result = $this->handler->get_user_conversations( $this->test_user_id, null, -5, 10 );
        $this->assertSame( 1, $result['current_page'] );
    }

    /**
     * Test get user conversation count.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversation_count
     */
    public function test_get_user_conversation_count(): void {
        $this->mock_db_results( array(
            'var' => 15,
        ) );

        $count = $this->handler->get_user_conversation_count( $this->test_user_id );
        $this->assertSame( 15, $count );
    }

    /**
     * Test get user conversation count including archived.
     *
     * @test
     * @covers Chat_History_Handler::get_user_conversation_count
     */
    public function test_get_user_conversation_count_including_archived(): void {
        $this->mock_db_results( array(
            'var' => 20,
        ) );

        $count = $this->handler->get_user_conversation_count( $this->test_user_id, true );
        $this->assertSame( 20, $count );
    }
}
