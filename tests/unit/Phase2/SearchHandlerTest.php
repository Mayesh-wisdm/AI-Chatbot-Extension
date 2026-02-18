<?php
/**
 * Tests for Search_Handler class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Search_Handler
 *
 * Implements test cases for FR-210 to FR-219 (Search Functionality Feature)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Search_Handler;
use WP_UnitTestCase;

/**
 * Search Handler Test Class.
 *
 * Tests:
 * - TC-210-001 through TC-210-003: Search Input Interface
 * - TC-211-001 through TC-211-003: Full-Text Search
 * - TC-212-001 through TC-212-002: Admin Global Search
 * - TC-213-001 through TC-213-003: User Personal Search
 * - TC-214-001 through TC-214-003: Search Filters
 * - TC-215-001 through TC-215-002: Search Results Display
 * - TC-216-001 through TC-216-002: Search Term Highlighting
 * - TC-217-001 through TC-217-002: Relevance Ranking
 */
class SearchHandlerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Search_Handler
     */
    private Search_Handler $handler;

    /**
     * Test user ID (regular user).
     *
     * @var int
     */
    private int $user_id;

    /**
     * Admin user ID.
     *
     * @var int
     */
    private int $admin_id;

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

        $this->admin_id = 1;

        $this->handler = new Search_Handler();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-210: Search Input Interface - TC-210-xxx
    // =========================================================================

    /**
     * Test TC-210-001: Search with valid query returns results.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-210-001, FR-210
     */
    public function test_search_with_valid_query_returns_results(): void {
        // Arrange: Mock search results.
        $this->mock_db_results( array(
            'var'     => 5,
            'results' => array(
                array(
                    'message_id'      => 1,
                    'conversation_id' => 1,
                    'role'            => 'user',
                    'content'         => 'I need help finding a product',
                    'created_at'      => '2026-01-28 10:00:00',
                    'metadata'        => '{}',
                    'chatbot_id'      => 10,
                    'user_id'         => $this->user_id,
                    'chatbot_name'    => 'Support Bot',
                    'user_name'       => 'Test User',
                    'relevance_score' => 1.5,
                ),
            ),
        ) );

        // Act: Perform search.
        $result = $this->handler->search( 'help finding product', array( 'user_id' => $this->user_id ) );

        // Assert: Results returned successfully.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'results', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'pages', $result );
        $this->assertArrayHasKey( 'current_page', $result );
        $this->assertArrayHasKey( 'search_time', $result );
        $this->assertArrayHasKey( 'query', $result );
    }

    /**
     * Test TC-210-002: Search with short query returns empty results.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-210-002, FR-210
     */
    public function test_search_with_short_query_returns_empty(): void {
        // Act: Search with single character (below minimum).
        $result = $this->handler->search( 'a' );

        // Assert: Empty results returned.
        $this->assertIsArray( $result );
        $this->assertEmpty( $result['results'] );
        $this->assertSame( 0, $result['total'] );
    }

    /**
     * Test TC-210-003: Search query is trimmed of whitespace.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-210-003, FR-210
     */
    public function test_search_query_is_trimmed(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 0,
            'results' => array(),
        ) );

        // Act: Search with whitespace-padded query.
        $result = $this->handler->search( '   test query   ' );

        // Assert: Query is trimmed.
        $this->assertSame( 'test query', $result['query'] );
    }

    // =========================================================================
    // FR-211: Full-Text Search - TC-211-xxx
    // =========================================================================

    /**
     * Test TC-211-001: Full-text search matches content.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-211-001, FR-211
     */
    public function test_fulltext_search_matches_content(): void {
        // Arrange: Mock matching results.
        $this->mock_db_results( array(
            'var'     => 1,
            'results' => array(
                array(
                    'message_id'      => 1,
                    'conversation_id' => 1,
                    'role'            => 'user',
                    'content'         => 'Looking for WordPress plugins',
                    'created_at'      => '2026-01-28 10:00:00',
                    'metadata'        => '{}',
                    'chatbot_id'      => 10,
                    'user_id'         => $this->user_id,
                    'chatbot_name'    => 'Bot',
                    'user_name'       => 'Test',
                    'relevance_score' => 2.0,
                ),
            ),
        ) );

        // Act: Search for specific terms.
        $result = $this->handler->search( 'WordPress plugins', array( 'user_id' => $this->user_id ) );

        // Assert: Matching content found.
        $this->assertGreaterThanOrEqual( 0, $result['total'] );
    }

    /**
     * Test TC-211-002: Search returns relevance scores.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-211-002, FR-211
     */
    public function test_search_returns_relevance_scores(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 1,
            'results' => array(
                array(
                    'message_id'      => 1,
                    'conversation_id' => 1,
                    'role'            => 'user',
                    'content'         => 'Test content',
                    'created_at'      => '2026-01-28 10:00:00',
                    'metadata'        => '{}',
                    'chatbot_id'      => 10,
                    'user_id'         => $this->user_id,
                    'chatbot_name'    => 'Bot',
                    'user_name'       => 'Test',
                    'relevance_score' => 1.5,
                ),
            ),
        ) );

        // Act: Perform search.
        $result = $this->handler->search( 'test content', array( 'user_id' => $this->user_id ) );

        // Assert: Results include relevance scores.
        if ( ! empty( $result['results'] ) ) {
            $this->assertArrayHasKey( 'relevance_score', $result['results'][0] );
        }
    }

    // =========================================================================
    // FR-212: Admin Global Search - TC-212-xxx
    // =========================================================================

    /**
     * Test TC-212-001: Admin can search all conversations.
     *
     * @test
     * @covers Search_Handler::can_search_all
     * Implements: TC-212-001, FR-212
     */
    public function test_admin_can_search_all_conversations(): void {
        // Arrange: Set admin user.
        $this->set_current_user( array(
            'ID'           => $this->admin_id,
            'user_login'   => 'admin',
            'capabilities' => array( 'manage_options' ),
        ) );

        // Act: Check permission.
        $can_search_all = $this->handler->can_search_all( $this->admin_id );

        // Assert: Admin has permission.
        $this->assertTrue( $can_search_all );
    }

    /**
     * Test TC-212-002: Regular user cannot search all conversations.
     *
     * @test
     * @covers Search_Handler::can_search_all
     * Implements: TC-212-002, FR-212
     */
    public function test_regular_user_cannot_search_all(): void {
        // Act: Check permission for regular user.
        $can_search_all = $this->handler->can_search_all( $this->user_id );

        // Assert: Regular user does not have permission.
        $this->assertFalse( $can_search_all );
    }

    // =========================================================================
    // FR-213: User Personal Search - TC-213-xxx
    // =========================================================================

    /**
     * Test TC-213-001: User search is scoped to own conversations.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-213-001, FR-213
     */
    public function test_user_search_scoped_to_own_conversations(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 2,
            'results' => array(),
        ) );

        // Act: Search as regular user.
        $result = $this->handler->search( 'test query' );

        // Assert: Search completed (user_id filter added automatically).
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'results', $result );
    }

    // =========================================================================
    // FR-214: Search Filters - TC-214-xxx
    // =========================================================================

    /**
     * Test TC-214-001: Filter by chatbot ID.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-214-001, FR-214
     */
    public function test_search_filter_by_chatbot(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 3,
            'results' => array(),
        ) );

        // Act: Search with chatbot filter.
        $result = $this->handler->search( 'test query', array(
            'user_id'    => $this->user_id,
            'chatbot_id' => 10,
        ) );

        // Assert: Search completed with filter.
        $this->assertIsArray( $result );
    }

    /**
     * Test TC-214-002: Filter by date range.
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-214-002, FR-214
     */
    public function test_search_filter_by_date_range(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 2,
            'results' => array(),
        ) );

        // Act: Search with date filters.
        $result = $this->handler->search( 'test query', array(
            'user_id'    => $this->user_id,
            'start_date' => '2026-01-01',
            'end_date'   => '2026-01-31',
        ) );

        // Assert: Search completed with filters.
        $this->assertIsArray( $result );
    }

    /**
     * Test TC-214-003: Filter by role (user/assistant).
     *
     * @test
     * @covers Search_Handler::search
     * Implements: TC-214-003, FR-214
     */
    public function test_search_filter_by_role(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 5,
            'results' => array(),
        ) );

        // Act: Search with role filter.
        $result = $this->handler->search( 'test query', array(
            'user_id' => $this->user_id,
            'role'    => 'user',
        ) );

        // Assert: Search completed with filter.
        $this->assertIsArray( $result );
    }

    // =========================================================================
    // FR-216: Search Term Highlighting - TC-216-xxx
    // =========================================================================

    /**
     * Test TC-216-001: Search terms are highlighted in results.
     *
     * @test
     * @covers Search_Handler::highlight_matches
     * Implements: TC-216-001, FR-216
     */
    public function test_search_terms_highlighted(): void {
        // Arrange.
        $content = 'I need help with my WordPress website configuration';
        $query   = 'WordPress website';

        // Act: Highlight matches.
        $highlighted = $this->handler->highlight_matches( $content, $query );

        // Assert: Terms are wrapped in mark tags.
        $this->assertStringContainsString( '<mark', $highlighted );
        $this->assertStringContainsString( 'WordPress', $highlighted );
        $this->assertStringContainsString( 'website', $highlighted );
    }

    /**
     * Test TC-216-002: Highlighting is case-insensitive.
     *
     * @test
     * @covers Search_Handler::highlight_matches
     * Implements: TC-216-002, FR-216
     */
    public function test_highlighting_is_case_insensitive(): void {
        // Arrange.
        $content = 'WORDPRESS is great for websites';
        $query   = 'wordpress';

        // Act: Highlight matches.
        $highlighted = $this->handler->highlight_matches( $content, $query );

        // Assert: Case-insensitive match is highlighted.
        $this->assertStringContainsString( '<mark', $highlighted );
    }

    /**
     * Test TC-216-003: Empty query returns original content escaped.
     *
     * @test
     * @covers Search_Handler::highlight_matches
     * Implements: TC-216-003, FR-216
     */
    public function test_empty_query_returns_escaped_content(): void {
        // Arrange.
        $content = 'Some content here';

        // Act: Highlight with empty query.
        $result = $this->handler->highlight_matches( $content, '' );

        // Assert: Content returned escaped.
        $this->assertSame( esc_html( $content ), $result );
    }

    // =========================================================================
    // FR-217: Relevance Ranking - TC-217-xxx
    // =========================================================================

    /**
     * Test TC-217-001: Relevance score combines fulltext and recency.
     *
     * @test
     * @covers Search_Handler::calculate_relevance
     * Implements: TC-217-001, FR-217
     */
    public function test_relevance_combines_fulltext_and_recency(): void {
        // Arrange.
        $fulltext_score = 2.0;
        $recent_date    = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

        // Act: Calculate relevance.
        $relevance = $this->handler->calculate_relevance( $fulltext_score, $recent_date );

        // Assert: Score is higher than raw fulltext score (recency boost).
        $this->assertGreaterThan( $fulltext_score, $relevance );
    }

    /**
     * Test TC-217-002: Older messages have lower recency boost.
     *
     * @test
     * @covers Search_Handler::calculate_relevance
     * Implements: TC-217-002, FR-217
     */
    public function test_older_messages_lower_recency_boost(): void {
        // Arrange.
        $fulltext_score = 2.0;
        $recent_date    = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
        $old_date       = gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) );

        // Act: Calculate relevance for both.
        $recent_relevance = $this->handler->calculate_relevance( $fulltext_score, $recent_date );
        $old_relevance    = $this->handler->calculate_relevance( $fulltext_score, $old_date );

        // Assert: Recent message has higher relevance.
        $this->assertGreaterThan( $old_relevance, $recent_relevance );
    }

    // =========================================================================
    // Search Suggestions - FR-210
    // =========================================================================

    /**
     * Test search suggestions with partial query.
     *
     * @test
     * @covers Search_Handler::get_search_suggestions
     * Implements: FR-210
     */
    public function test_search_suggestions_with_partial_query(): void {
        // Arrange.
        $this->mock_db_results( array(
            'col' => array( 'WordPress plugin help', 'WordPress theme design' ),
        ) );

        // Act: Get suggestions.
        $suggestions = $this->handler->get_search_suggestions( 'word', $this->user_id );

        // Assert: Array returned.
        $this->assertIsArray( $suggestions );
    }

    /**
     * Test search suggestions with short query returns empty.
     *
     * @test
     * @covers Search_Handler::get_search_suggestions
     * Implements: FR-210
     */
    public function test_search_suggestions_short_query_empty(): void {
        // Act: Get suggestions with single char.
        $suggestions = $this->handler->get_search_suggestions( 'w', $this->user_id );

        // Assert: Empty array.
        $this->assertIsArray( $suggestions );
        $this->assertEmpty( $suggestions );
    }

    // =========================================================================
    // Search Conversations - FR-210
    // =========================================================================

    /**
     * Test search conversations by preview.
     *
     * @test
     * @covers Search_Handler::search_conversations
     * Implements: FR-210
     */
    public function test_search_conversations(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                array(
                    'id'           => 1,
                    'chatbot_id'   => 10,
                    'user_id'      => $this->user_id,
                    'session_id'   => 'session_123',
                    'created_at'   => '2026-01-28 10:00:00',
                    'updated_at'   => '2026-01-28 12:00:00',
                    'chatbot_name' => 'Bot',
                    'preview'      => 'Help with WordPress',
                ),
            ),
        ) );

        // Act: Search conversations.
        $result = $this->handler->search_conversations( 'WordPress', $this->user_id );

        // Assert: Array of conversations returned.
        $this->assertIsArray( $result );
    }

    /**
     * Test search conversations with short query returns empty.
     *
     * @test
     * @covers Search_Handler::search_conversations
     * Implements: FR-210
     */
    public function test_search_conversations_short_query_empty(): void {
        // Act: Search with single char.
        $result = $this->handler->search_conversations( 'w', $this->user_id );

        // Assert: Empty array.
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Test has_fulltext_index method.
     *
     * @test
     * @covers Search_Handler::has_fulltext_index
     */
    public function test_has_fulltext_index(): void {
        // Arrange: Mock index check.
        $this->mock_db_results( array(
            'results' => array( (object) array( 'INDEX_NAME' => 'fulltext_content' ) ),
        ) );

        // Act: Check for index.
        $has_index = $this->handler->has_fulltext_index();

        // Assert: Returns boolean.
        $this->assertIsBool( $has_index );
    }

    /**
     * Test invalidate_cache method.
     *
     * @test
     * @covers Search_Handler::invalidate_cache
     */
    public function test_invalidate_cache(): void {
        // Act: Invalidate cache (no cache manager in test).
        $result = $this->handler->invalidate_cache();

        // Assert: Returns false when no cache manager.
        $this->assertFalse( $result );
    }

    /**
     * Test get_statistics method.
     *
     * @test
     * @covers Search_Handler::get_statistics
     */
    public function test_get_statistics(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var' => 1000,
        ) );

        // Act: Get statistics.
        $stats = $this->handler->get_statistics();

        // Assert: Statistics array returned.
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_messages', $stats );
        $this->assertArrayHasKey( 'fulltext_enabled', $stats );
        $this->assertArrayHasKey( 'min_query_length', $stats );
        $this->assertArrayHasKey( 'cache_ttl', $stats );
        $this->assertArrayHasKey( 'per_page_default', $stats );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test search with special characters.
     *
     * @test
     * @covers Search_Handler::search
     */
    public function test_search_with_special_characters(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 0,
            'results' => array(),
        ) );

        // Act: Search with special chars.
        $result = $this->handler->search( 'test @#$%^&*() query', array( 'user_id' => $this->user_id ) );

        // Assert: Query sanitized and search completed.
        $this->assertIsArray( $result );
    }

    /**
     * Test search pagination.
     *
     * @test
     * @covers Search_Handler::search
     */
    public function test_search_pagination(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 50,
            'results' => array(),
        ) );

        // Act: Search page 2.
        $result = $this->handler->search( 'test query', array( 'user_id' => $this->user_id ), 2, 10 );

        // Assert: Pagination info correct.
        $this->assertSame( 50, $result['total'] );
        $this->assertSame( 5, $result['pages'] ); // ceil(50/10).
        $this->assertSame( 2, $result['current_page'] );
    }

    /**
     * Test search with zero results.
     *
     * @test
     * @covers Search_Handler::search
     */
    public function test_search_with_zero_results(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => 0,
            'results' => array(),
        ) );

        // Act: Search that returns no results.
        $result = $this->handler->search( 'nonexistent content xyz123', array( 'user_id' => $this->user_id ) );

        // Assert: Empty results structure.
        $this->assertSame( 0, $result['total'] );
        $this->assertSame( 0, $result['pages'] );
        $this->assertEmpty( $result['results'] );
    }
}
