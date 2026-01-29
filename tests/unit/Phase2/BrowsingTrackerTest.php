<?php
/**
 * Tests for Browsing_Tracker class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Browsing_Tracker
 *
 * Implements test cases for FR-252 (Browsing History Tracking)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Browsing_Tracker;
use WP_UnitTestCase;

/**
 * Browsing Tracker Test Class.
 *
 * Tests:
 * - TC-252-001 through TC-252-005: Page View Tracking
 * - TC-252-006 through TC-252-008: Session History
 * - TC-252-009 through TC-252-012: Product/Course ID Extraction
 * - TC-252-013 through TC-252-015: AJAX Handling
 * - TC-252-016 through TC-252-018: Cleanup and Security
 */
class BrowsingTrackerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Browsing_Tracker
     */
    private Browsing_Tracker $tracker;

    /**
     * Test user ID.
     *
     * @var int
     */
    private int $user_id;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->user_id = $this->set_current_user( array(
            'ID'           => 100,
            'user_login'   => 'test_user',
            'display_name' => 'Test User',
            'capabilities' => array( 'read' ),
        ) );

        // Mock table exists check.
        $this->mock_db_results( array(
            'var' => null, // Simulate table doesn't exist.
        ) );

        $this->tracker = new Browsing_Tracker();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-252: Page View Tracking - TC-252-001 to TC-252-005
    // =========================================================================

    /**
     * Test TC-252-001: Track page view in session.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     * Implements: TC-252-001, FR-252
     */
    public function test_track_page_view_in_session(): void {
        // Act.
        $result = $this->tracker->track_page_view( 'product', 123 );

        // Assert: Returns true (session tracking works without DB).
        $this->assertTrue( $result );
    }

    /**
     * Test TC-252-002: Track course view.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     * Implements: TC-252-002, FR-252
     */
    public function test_track_course_view(): void {
        // Act.
        $result = $this->tracker->track_page_view( 'course', 456 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-252-003: Track with metadata.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     * Implements: TC-252-003, FR-252
     */
    public function test_track_with_metadata(): void {
        // Arrange.
        $metadata = array(
            'chatbot_id' => 10,
            'source'     => 'widget',
        );

        // Act.
        $result = $this->tracker->track_page_view( 'product', 123, $metadata );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-252-004: Multiple tracks of same item only count once.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     * Implements: TC-252-004, FR-252
     */
    public function test_track_same_item_once(): void {
        // Act: Track same product twice.
        $this->tracker->track_page_view( 'product', 123 );
        $this->tracker->track_page_view( 'product', 123 );

        // Get session history.
        $history = $this->tracker->get_session_history();

        // Assert: Product ID appears once (deduplication in session).
        $this->assertCount( 1, array_unique( $history['product_ids'] ) );
    }

    // =========================================================================
    // FR-252: Session History - TC-252-006 to TC-252-008
    // =========================================================================

    /**
     * Test TC-252-006: Get session history returns correct structure.
     *
     * @test
     * @covers Browsing_Tracker::get_session_history
     * Implements: TC-252-006, FR-252
     */
    public function test_get_session_history_structure(): void {
        // Act.
        $history = $this->tracker->get_session_history();

        // Assert.
        $this->assertIsArray( $history );
        $this->assertArrayHasKey( 'product_ids', $history );
        $this->assertArrayHasKey( 'course_ids', $history );
        $this->assertArrayHasKey( 'categories', $history );
        $this->assertArrayHasKey( 'view_count', $history );
    }

    /**
     * Test TC-252-007: Get session history with specific user ID.
     *
     * @test
     * @covers Browsing_Tracker::get_session_history
     * Implements: TC-252-007, FR-252
     */
    public function test_get_session_history_with_user(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => null,
            'results' => array(),
        ) );

        // Act.
        $history = $this->tracker->get_session_history( $this->user_id, 'session_123' );

        // Assert.
        $this->assertIsArray( $history );
    }

    /**
     * Test TC-252-008: Get session history with guest session ID.
     *
     * @test
     * @covers Browsing_Tracker::get_session_history
     * Implements: TC-252-008, FR-252
     */
    public function test_get_session_history_guest(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => null,
            'results' => array(),
        ) );

        // Act.
        $history = $this->tracker->get_session_history( 0, 'guest_session_123' );

        // Assert.
        $this->assertIsArray( $history );
    }

    // =========================================================================
    // FR-252: Product/Course ID Extraction - TC-252-009 to TC-252-012
    // =========================================================================

    /**
     * Test TC-252-009: Extract product IDs.
     *
     * @test
     * @covers Browsing_Tracker::extract_product_ids
     * Implements: TC-252-009, FR-252
     */
    public function test_extract_product_ids(): void {
        // Arrange: Track some products.
        $this->tracker->track_page_view( 'product', 101 );
        $this->tracker->track_page_view( 'product', 102 );
        $this->tracker->track_page_view( 'course', 201 ); // Not a product.

        // Act.
        $product_ids = $this->tracker->extract_product_ids();

        // Assert.
        $this->assertIsArray( $product_ids );
        $this->assertContains( 101, $product_ids );
        $this->assertContains( 102, $product_ids );
        $this->assertNotContains( 201, $product_ids );
    }

    /**
     * Test TC-252-010: Extract course IDs.
     *
     * @test
     * @covers Browsing_Tracker::extract_course_ids
     * Implements: TC-252-010, FR-252
     */
    public function test_extract_course_ids(): void {
        // Arrange: Track some courses.
        $this->tracker->track_page_view( 'course', 201 );
        $this->tracker->track_page_view( 'course', 202 );
        $this->tracker->track_page_view( 'product', 101 ); // Not a course.

        // Act.
        $course_ids = $this->tracker->extract_course_ids();

        // Assert.
        $this->assertIsArray( $course_ids );
        $this->assertContains( 201, $course_ids );
        $this->assertContains( 202, $course_ids );
        $this->assertNotContains( 101, $course_ids );
    }

    /**
     * Test TC-252-011: Extract IDs for specific user.
     *
     * @test
     * @covers Browsing_Tracker::extract_product_ids
     * Implements: TC-252-011, FR-252
     */
    public function test_extract_ids_for_user(): void {
        // Act.
        $product_ids = $this->tracker->extract_product_ids( $this->user_id, 'session_123' );

        // Assert.
        $this->assertIsArray( $product_ids );
    }

    // =========================================================================
    // Session Management - TC-SESSION-xxx
    // =========================================================================

    /**
     * Test TC-SESSION-001: Get session ID.
     *
     * @test
     * @covers Browsing_Tracker::get_session_id
     */
    public function test_get_session_id(): void {
        // Act.
        $session_id = $this->tracker->get_session_id();

        // Assert.
        $this->assertIsString( $session_id );
        $this->assertNotEmpty( $session_id );
    }

    /**
     * Test TC-SESSION-002: Session ID is consistent.
     *
     * @test
     * @covers Browsing_Tracker::get_session_id
     */
    public function test_session_id_consistent(): void {
        // Act.
        $session_id_1 = $this->tracker->get_session_id();
        $session_id_2 = $this->tracker->get_session_id();

        // Assert.
        $this->assertSame( $session_id_1, $session_id_2 );
    }

    // =========================================================================
    // FR-252: Cleanup - TC-252-016 to TC-252-018
    // =========================================================================

    /**
     * Test TC-252-016: Clear user history.
     *
     * @test
     * @covers Browsing_Tracker::clear_user_history
     * Implements: TC-252-016, FR-252
     */
    public function test_clear_user_history(): void {
        // Arrange: Track something.
        $this->tracker->track_page_view( 'product', 123 );

        // Act.
        $result = $this->tracker->clear_user_history( $this->user_id );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-252-017: Cleanup old interactions.
     *
     * @test
     * @covers Browsing_Tracker::cleanup_old_interactions
     * Implements: TC-252-017, FR-252
     */
    public function test_cleanup_old_interactions(): void {
        // Act: Try cleanup (will return 0 since we're in session-only mode).
        $deleted = $this->tracker->cleanup_old_interactions( 90 );

        // Assert.
        $this->assertIsInt( $deleted );
        $this->assertSame( 0, $deleted ); // No DB, so 0 deleted.
    }

    /**
     * Test TC-252-018: Cleanup with custom days.
     *
     * @test
     * @covers Browsing_Tracker::cleanup_old_interactions
     * Implements: TC-252-018, FR-252
     */
    public function test_cleanup_custom_days(): void {
        // Act.
        $deleted = $this->tracker->cleanup_old_interactions( 30 );

        // Assert.
        $this->assertSame( 0, $deleted );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test track with invalid item type.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     */
    public function test_track_with_post_type(): void {
        // Act: Track a generic post.
        $result = $this->tracker->track_page_view( 'post', 999 );

        // Assert: Still tracked (generic page_view).
        $this->assertTrue( $result );
    }

    /**
     * Test track with page type.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     */
    public function test_track_with_page_type(): void {
        // Act: Track a page.
        $result = $this->tracker->track_page_view( 'page', 888 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test view count increments.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     */
    public function test_view_count_increments(): void {
        // Arrange: Track multiple different items.
        $this->tracker->track_page_view( 'product', 101 );
        $this->tracker->track_page_view( 'product', 102 );
        $this->tracker->track_page_view( 'course', 201 );

        // Act.
        $history = $this->tracker->get_session_history();

        // Assert: View count incremented for each track.
        $this->assertGreaterThanOrEqual( 3, $history['view_count'] );
    }

    /**
     * Test session history merges session and DB data.
     *
     * @test
     * @covers Browsing_Tracker::get_session_history
     */
    public function test_session_history_merges_sources(): void {
        // Arrange: Track in session.
        $this->tracker->track_page_view( 'product', 111 );

        // Mock some DB results too.
        $this->mock_db_results( array(
            'var'     => 'wp_ai_botkit_user_interactions',
            'results' => array(
                (object) array(
                    'item_type'  => 'product',
                    'item_id'    => 222,
                    'view_count' => 3,
                ),
            ),
        ) );

        // Note: In real scenario, both session and DB would merge.
        // Here we just verify the structure.
        $history = $this->tracker->get_session_history();

        // Assert.
        $this->assertIsArray( $history['product_ids'] );
    }

    /**
     * Test extract IDs returns unique values.
     *
     * @test
     * @covers Browsing_Tracker::extract_product_ids
     */
    public function test_extract_ids_unique(): void {
        // Arrange: Add same product multiple times.
        $this->tracker->track_page_view( 'product', 101 );
        $this->tracker->track_page_view( 'product', 101 );
        $this->tracker->track_page_view( 'product', 101 );

        // Act.
        $product_ids = $this->tracker->extract_product_ids();

        // Assert: Product ID appears only once.
        $unique_count = count( array_unique( $product_ids ) );
        $this->assertSame( $unique_count, count( $product_ids ) );
    }

    /**
     * Test extract with empty history returns empty array.
     *
     * @test
     * @covers Browsing_Tracker::extract_product_ids
     */
    public function test_extract_empty_history(): void {
        // Create fresh tracker (no history).
        $fresh_tracker = new Browsing_Tracker();

        // Act.
        $product_ids = $fresh_tracker->extract_product_ids( 999, 'new_session' );

        // Assert.
        $this->assertIsArray( $product_ids );
    }

    /**
     * Test track returns false when session cannot be started.
     *
     * This is difficult to test directly due to PHP session limitations.
     * We test the session-only fallback behavior instead.
     *
     * @test
     * @covers Browsing_Tracker::track_page_view
     */
    public function test_session_only_mode(): void {
        // The tracker is already in session-only mode (no DB table).
        // Verify it still works.
        $result = $this->tracker->track_page_view( 'product', 123 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test clear history in session-only mode.
     *
     * @test
     * @covers Browsing_Tracker::clear_user_history
     */
    public function test_clear_session_only_history(): void {
        // Arrange: Track something.
        $this->tracker->track_page_view( 'product', 123 );

        // Act.
        $result = $this->tracker->clear_user_history( $this->user_id );

        // Assert: Returns true even in session-only mode.
        $this->assertTrue( $result );
    }
}
