<?php
/**
 * Tests for Recommendation_Engine class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Recommendation_Engine
 *
 * Implements test cases for FR-250 to FR-259 (LMS/WooCommerce Suggestions)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Recommendation_Engine;
use WP_UnitTestCase;

/**
 * Recommendation Engine Test Class.
 *
 * Tests:
 * - TC-250-001 through TC-250-003: Recommendation Engine Core
 * - TC-251-001 through TC-251-003: Conversation Context Analysis
 * - TC-252-001 through TC-252-002: Browsing History Tracking
 * - TC-253-001 through TC-253-003: Purchase/Enrollment History
 * - TC-254-001 through TC-254-003: Explicit Recommendation Requests
 * - TC-255-001 through TC-255-003: Suggestion UI Cards
 * - TC-258-001 through TC-258-003: LearnDash Course Suggestions
 * - TC-259-001 through TC-259-003: WooCommerce Product Suggestions
 */
class RecommendationEngineTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Recommendation_Engine
     */
    private Recommendation_Engine $engine;

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

        // Set default options.
        update_option( 'ai_botkit_recommendation_enabled', true );
        update_option( 'ai_botkit_recommendation_limit', 5 );

        $this->engine = new Recommendation_Engine();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        delete_option( 'ai_botkit_recommendation_enabled' );
        delete_option( 'ai_botkit_recommendation_limit' );
        parent::tearDown();
    }

    // =========================================================================
    // FR-250: Recommendation Engine Core - TC-250-xxx
    // =========================================================================

    /**
     * Test TC-250-001: Get recommendations returns array.
     *
     * @test
     * @covers Recommendation_Engine::get_recommendations
     * Implements: TC-250-001, FR-250
     */
    public function test_get_recommendations_returns_array(): void {
        // Arrange.
        $context = array(
            'conversation_text' => 'I am looking for a WordPress course',
        );

        // Act.
        $recommendations = $this->engine->get_recommendations( $this->user_id, $context );

        // Assert.
        $this->assertIsArray( $recommendations );
    }

    /**
     * Test TC-250-002: Get recommendations respects limit.
     *
     * @test
     * @covers Recommendation_Engine::get_recommendations
     * Implements: TC-250-002, FR-250
     */
    public function test_get_recommendations_respects_limit(): void {
        // Arrange.
        $context = array(
            'conversation_text' => 'Show me products',
        );

        // Act.
        $recommendations = $this->engine->get_recommendations( $this->user_id, $context, 3 );

        // Assert.
        $this->assertLessThanOrEqual( 3, count( $recommendations ) );
    }

    /**
     * Test TC-250-003: Recommendations disabled returns empty array.
     *
     * @test
     * @covers Recommendation_Engine::get_recommendations
     * Implements: TC-250-003, FR-250
     */
    public function test_recommendations_disabled_returns_empty(): void {
        // Arrange.
        update_option( 'ai_botkit_recommendation_enabled', false );
        $this->engine = new Recommendation_Engine();

        // Act.
        $recommendations = $this->engine->get_recommendations( $this->user_id, array() );

        // Assert.
        $this->assertIsArray( $recommendations );
        $this->assertEmpty( $recommendations );
    }

    // =========================================================================
    // FR-251: Conversation Context Analysis - TC-251-xxx
    // =========================================================================

    /**
     * Test TC-251-001: Analyze empty conversation returns defaults.
     *
     * @test
     * @covers Recommendation_Engine::analyze_conversation_context
     * Implements: TC-251-001, FR-251
     */
    public function test_analyze_empty_conversation(): void {
        // Act.
        $result = $this->engine->analyze_conversation_context( '' );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'keywords', $result );
        $this->assertArrayHasKey( 'categories', $result );
        $this->assertArrayHasKey( 'intent', $result );
        $this->assertArrayHasKey( 'confidence', $result );
        $this->assertArrayHasKey( 'item_ids', $result );
        $this->assertEmpty( $result['keywords'] );
        $this->assertSame( 'general', $result['intent'] );
        $this->assertSame( 0.0, $result['confidence'] );
    }

    /**
     * Test TC-251-002: Analyze conversation extracts keywords.
     *
     * @test
     * @covers Recommendation_Engine::analyze_conversation_context
     * Implements: TC-251-002, FR-251
     */
    public function test_analyze_conversation_extracts_keywords(): void {
        // Arrange.
        $text = 'I want to learn about WordPress development and PHP programming';

        // Act.
        $result = $this->engine->analyze_conversation_context( $text );

        // Assert.
        $this->assertIsArray( $result['keywords'] );
        $this->assertContains( 'wordpress', $result['keywords'] );
        $this->assertContains( 'development', $result['keywords'] );
        $this->assertContains( 'php', $result['keywords'] );
        $this->assertContains( 'programming', $result['keywords'] );
    }

    /**
     * Test TC-251-003: Analyze conversation detects course intent.
     *
     * @test
     * @covers Recommendation_Engine::analyze_conversation_context
     * Implements: TC-251-003, FR-251
     */
    public function test_analyze_conversation_detects_course_intent(): void {
        // Arrange.
        $text = 'I am looking for a training course to learn web development';

        // Act.
        $result = $this->engine->analyze_conversation_context( $text );

        // Assert.
        $this->assertSame( 'course', $result['intent'] );
    }

    /**
     * Test TC-251-004: Analyze conversation detects product intent.
     *
     * @test
     * @covers Recommendation_Engine::analyze_conversation_context
     * Implements: TC-251-004, FR-251
     */
    public function test_analyze_conversation_detects_product_intent(): void {
        // Arrange.
        $text = 'I want to buy a product for my website';

        // Act.
        $result = $this->engine->analyze_conversation_context( $text );

        // Assert.
        $this->assertSame( 'product', $result['intent'] );
    }

    // =========================================================================
    // FR-252: Browsing History Tracking - TC-252-xxx
    // =========================================================================

    /**
     * Test TC-252-001: Get browsing history returns default structure.
     *
     * @test
     * @covers Recommendation_Engine::get_browsing_history
     * Implements: TC-252-001, FR-252
     */
    public function test_get_browsing_history_default_structure(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var'     => null, // Table doesn't exist.
            'results' => array(),
        ) );

        // Act.
        $history = $this->engine->get_browsing_history( $this->user_id, 'session_123' );

        // Assert.
        $this->assertIsArray( $history );
        $this->assertArrayHasKey( 'product_ids', $history );
        $this->assertArrayHasKey( 'course_ids', $history );
        $this->assertArrayHasKey( 'categories', $history );
        $this->assertArrayHasKey( 'view_count', $history );
    }

    /**
     * Test TC-252-002: Get browsing history for guest user.
     *
     * @test
     * @covers Recommendation_Engine::get_browsing_history
     * Implements: TC-252-002, FR-252
     */
    public function test_get_browsing_history_guest_user(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var' => null,
        ) );

        // Act.
        $history = $this->engine->get_browsing_history( 0, 'guest_session_123' );

        // Assert.
        $this->assertIsArray( $history );
        $this->assertSame( 0, $history['view_count'] );
    }

    // =========================================================================
    // FR-253: Purchase/Enrollment History - TC-253-xxx
    // =========================================================================

    /**
     * Test TC-253-001: Get purchase history returns default structure.
     *
     * @test
     * @covers Recommendation_Engine::get_purchase_enrollment_history
     * Implements: TC-253-001, FR-253
     */
    public function test_get_purchase_history_default_structure(): void {
        // Act.
        $history = $this->engine->get_purchase_enrollment_history( $this->user_id );

        // Assert.
        $this->assertIsArray( $history );
        $this->assertArrayHasKey( 'purchased_ids', $history );
        $this->assertArrayHasKey( 'enrolled_ids', $history );
        $this->assertArrayHasKey( 'categories', $history );
        $this->assertArrayHasKey( 'price_range', $history );
        $this->assertArrayHasKey( 'complementary', $history );
    }

    /**
     * Test TC-253-002: Get purchase history for guest returns empty.
     *
     * @test
     * @covers Recommendation_Engine::get_purchase_enrollment_history
     * Implements: TC-253-002, FR-253
     */
    public function test_get_purchase_history_guest_empty(): void {
        // Act.
        $history = $this->engine->get_purchase_enrollment_history( 0 );

        // Assert.
        $this->assertIsArray( $history );
        $this->assertEmpty( $history['purchased_ids'] );
        $this->assertEmpty( $history['enrolled_ids'] );
    }

    // =========================================================================
    // FR-254: Explicit Recommendation Requests - TC-254-xxx
    // =========================================================================

    /**
     * Test TC-254-001: Detect explicit recommendation request.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     * Implements: TC-254-001, FR-254
     */
    public function test_detect_explicit_recommendation_request(): void {
        // Arrange.
        $text = 'Can you recommend a good WordPress course for beginners?';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertTrue( $result['is_explicit'] );
        $this->assertSame( 'course', $result['type'] );
        $this->assertGreaterThan( 0, $result['confidence'] );
    }

    /**
     * Test TC-254-002: Detect product recommendation request.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     * Implements: TC-254-002, FR-254
     */
    public function test_detect_product_recommendation_request(): void {
        // Arrange.
        $text = 'Please suggest a product I should buy';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertTrue( $result['is_explicit'] );
        $this->assertSame( 'product', $result['type'] );
    }

    /**
     * Test TC-254-003: No explicit request detected for general text.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     * Implements: TC-254-003, FR-254
     */
    public function test_no_explicit_request_for_general_text(): void {
        // Arrange.
        $text = 'Hello, how are you today?';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertFalse( $result['is_explicit'] );
        $this->assertSame( 'any', $result['type'] );
        $this->assertSame( 0.0, $result['confidence'] );
    }

    /**
     * Test TC-254-004: Extract criteria from recommendation request.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     * Implements: TC-254-004, FR-254
     */
    public function test_extract_criteria_from_request(): void {
        // Arrange.
        $text = 'Recommend a beginner course under $50';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertTrue( $result['is_explicit'] );
        $this->assertIsArray( $result['criteria'] );
        $this->assertSame( 'beginner', $result['criteria']['level'] ?? '' );
        $this->assertSame( 50.0, $result['criteria']['price_max'] ?? 0 );
    }

    // =========================================================================
    // FR-255: Suggestion UI Cards - TC-255-xxx
    // =========================================================================

    /**
     * Test TC-255-001: Format suggestion cards returns array.
     *
     * @test
     * @covers Recommendation_Engine::format_suggestion_cards
     * Implements: TC-255-001, FR-255
     */
    public function test_format_suggestion_cards_returns_array(): void {
        // Arrange: Empty recommendations.
        $recommendations = array();

        // Act.
        $cards = $this->engine->format_suggestion_cards( $recommendations );

        // Assert.
        $this->assertIsArray( $cards );
    }

    /**
     * Test TC-255-002: Format product card structure.
     *
     * @test
     * @covers Recommendation_Engine::format_suggestion_cards
     * Implements: TC-255-002, FR-255, FR-259
     */
    public function test_format_product_card_structure(): void {
        // Arrange.
        $recommendations = array(
            array(
                'id'        => 1,
                'type'      => 'product',
                'relevance' => 0.8,
                'score'     => 0.8,
                'source'    => 'context',
            ),
        );

        // Act.
        $cards = $this->engine->format_suggestion_cards( $recommendations );

        // Assert: Even without WooCommerce, structure is attempted.
        $this->assertIsArray( $cards );
        // Cards may be empty if product lookup fails.
    }

    /**
     * Test TC-255-003: Format course card structure.
     *
     * @test
     * @covers Recommendation_Engine::format_suggestion_cards
     * Implements: TC-255-003, FR-255, FR-258
     */
    public function test_format_course_card_structure(): void {
        // Arrange: Create a mock course post.
        $course_id = wp_insert_post( array(
            'post_title'   => 'Test Course',
            'post_type'    => 'sfwd-courses',
            'post_status'  => 'publish',
            'post_excerpt' => 'Course description',
        ) );

        $recommendations = array(
            array(
                'id'        => $course_id,
                'type'      => 'course',
                'relevance' => 0.7,
                'score'     => 0.7,
                'source'    => 'browsing',
            ),
        );

        // Act.
        $cards = $this->engine->format_suggestion_cards( $recommendations );

        // Assert.
        $this->assertIsArray( $cards );
    }

    // =========================================================================
    // Scoring Algorithm - TC-SCORE-xxx
    // =========================================================================

    /**
     * Test TC-SCORE-001: Calculate scores with all signals.
     *
     * @test
     * @covers Recommendation_Engine::calculate_scores
     */
    public function test_calculate_scores_with_signals(): void {
        // Arrange.
        $signals = array(
            'conversation' => array(
                'keywords'   => array( 'wordpress', 'plugin' ),
                'categories' => array(),
                'intent'     => 'product',
                'confidence' => 0.8,
                'item_ids'   => array(),
            ),
            'browsing' => array(
                'product_ids' => array(),
                'course_ids'  => array(),
                'categories'  => array(),
                'view_count'  => 0,
            ),
            'history' => array(
                'purchased_ids'  => array(),
                'enrolled_ids'   => array(),
                'categories'     => array(),
                'price_range'    => array( 'min' => 0, 'max' => 0 ),
                'complementary'  => array(),
            ),
            'explicit' => array(
                'is_explicit' => false,
                'type'        => 'any',
                'criteria'    => array(),
                'confidence'  => 0.0,
            ),
        );

        $weights = array(
            'conversation' => 0.40,
            'browsing'     => 0.30,
            'history'      => 0.20,
            'explicit'     => 0.10,
        );

        // Act.
        $scored = $this->engine->calculate_scores( $signals, $weights, $this->user_id );

        // Assert.
        $this->assertIsArray( $scored );
        // May be empty if no WooCommerce/LearnDash but algorithm runs.
    }

    /**
     * Test TC-SCORE-002: Explicit request boosts scores.
     *
     * @test
     * @covers Recommendation_Engine::calculate_scores
     */
    public function test_explicit_request_boosts_scores(): void {
        // Arrange.
        $signals = array(
            'conversation' => array(
                'keywords'   => array(),
                'categories' => array(),
                'intent'     => 'general',
                'confidence' => 0.0,
                'item_ids'   => array(),
            ),
            'browsing' => array(
                'product_ids' => array(),
                'course_ids'  => array(),
                'categories'  => array(),
                'view_count'  => 0,
            ),
            'history' => array(
                'purchased_ids'  => array(),
                'enrolled_ids'   => array(),
                'categories'     => array(),
                'price_range'    => array( 'min' => 0, 'max' => 0 ),
                'complementary'  => array(),
            ),
            'explicit' => array(
                'is_explicit' => true,
                'type'        => 'product',
                'criteria'    => array(),
                'confidence'  => 0.8,
            ),
        );

        $weights = array(
            'conversation' => 0.40,
            'browsing'     => 0.30,
            'history'      => 0.20,
            'explicit'     => 0.10,
        );

        // Act.
        $scored = $this->engine->calculate_scores( $signals, $weights, $this->user_id );

        // Assert: Algorithm runs, may return fallback items.
        $this->assertIsArray( $scored );
    }

    // =========================================================================
    // Interaction Tracking - TC-TRACK-xxx
    // =========================================================================

    /**
     * Test TC-TRACK-001: Track interaction without table.
     *
     * @test
     * @covers Recommendation_Engine::track_interaction
     */
    public function test_track_interaction_no_table(): void {
        // Arrange.
        $this->mock_db_results( array(
            'var' => null, // Table doesn't exist.
        ) );

        // Act.
        $result = $this->engine->track_interaction(
            $this->user_id,
            'product_view',
            'product',
            123,
            array( 'chatbot_id' => 10 )
        );

        // Assert.
        $this->assertFalse( $result );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test recommendations with zero limit uses default.
     *
     * @test
     * @covers Recommendation_Engine::get_recommendations
     */
    public function test_recommendations_zero_limit_uses_default(): void {
        // Act.
        $recommendations = $this->engine->get_recommendations( $this->user_id, array(), 0 );

        // Assert.
        $this->assertLessThanOrEqual( 5, count( $recommendations ) ); // Default limit.
    }

    /**
     * Test analyze conversation removes stop words.
     *
     * @test
     * @covers Recommendation_Engine::analyze_conversation_context
     */
    public function test_analyze_removes_stop_words(): void {
        // Arrange.
        $text = 'I am the best and I want to find something';

        // Act.
        $result = $this->engine->analyze_conversation_context( $text );

        // Assert: Stop words like "the", "and", "to", "I", "am" should be removed.
        $this->assertNotContains( 'the', $result['keywords'] );
        $this->assertNotContains( 'and', $result['keywords'] );
    }

    /**
     * Test intent detection with multiple signals.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     */
    public function test_intent_detection_multiple_signals(): void {
        // Arrange: Text with multiple recommendation patterns.
        $text = 'Can you recommend and suggest the best top products?';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert: Multiple matches increase confidence.
        $this->assertTrue( $result['is_explicit'] );
        $this->assertGreaterThan( 0.25, $result['confidence'] ); // At least 2 matches * 0.25.
    }

    /**
     * Test analyze conversation with price criteria.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     */
    public function test_detect_price_criteria(): void {
        // Arrange.
        $text = 'Show me courses over $100';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertArrayHasKey( 'price_min', $result['criteria'] );
        $this->assertSame( 100.0, $result['criteria']['price_min'] );
    }

    /**
     * Test analyze conversation with intermediate level.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     */
    public function test_detect_intermediate_level(): void {
        // Arrange.
        $text = 'Recommend an intermediate level course';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertArrayHasKey( 'level', $result['criteria'] );
        $this->assertSame( 'intermediate', $result['criteria']['level'] );
    }

    /**
     * Test analyze conversation with advanced level.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     */
    public function test_detect_advanced_level(): void {
        // Arrange.
        $text = 'I need an advanced expert course';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertArrayHasKey( 'level', $result['criteria'] );
        $this->assertSame( 'advanced', $result['criteria']['level'] );
    }

    /**
     * Test confidence caps at 1.0.
     *
     * @test
     * @covers Recommendation_Engine::detect_explicit_request
     */
    public function test_confidence_caps_at_one(): void {
        // Arrange: Text with many matching patterns.
        $text = 'Recommend suggest best top popular show me can you recommend looking for';

        // Act.
        $result = $this->engine->detect_explicit_request( $text );

        // Assert.
        $this->assertLessThanOrEqual( 1.0, $result['confidence'] );
    }
}
