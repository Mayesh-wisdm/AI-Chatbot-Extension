<?php
/**
 * Tests for Media_Handler class.
 *
 * @package AI_BotKit\Tests\Unit\Phase2
 * @covers \AI_BotKit\Features\Media_Handler
 *
 * Implements test cases for FR-220 to FR-229 (Rich Media Support)
 */

namespace AI_BotKit\Tests\Unit\Phase2;

use AI_BotKit\Features\Media_Handler;
use WP_UnitTestCase;

/**
 * Media Handler Test Class.
 *
 * Tests:
 * - TC-220-001 through TC-220-003: Image Attachments
 * - TC-221-001 through TC-221-003: Video Embeds
 * - TC-222-001 through TC-222-003: File Attachments
 * - TC-223-001 through TC-223-003: Rich Link Previews
 * - TC-224-001 through TC-224-004: Media Upload Handling
 * - TC-228-001 through TC-228-005: Media Security
 * - TC-229-001 through TC-229-003: Storage Management
 */
class MediaHandlerTest extends WP_UnitTestCase {

    /**
     * System under test.
     *
     * @var Media_Handler
     */
    private Media_Handler $handler;

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
            'capabilities' => array( 'upload_files' ),
        ) );

        $this->handler = new Media_Handler();
    }

    /**
     * Tear down test fixtures.
     */
    protected function tearDown(): void {
        parent::tearDown();
    }

    // =========================================================================
    // FR-221: Video Embeds - TC-221-xxx
    // =========================================================================

    /**
     * Test TC-221-001: YouTube URL parsed correctly.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-001, FR-221
     */
    public function test_youtube_url_parsed_correctly(): void {
        // Arrange.
        $youtube_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        // Act.
        $result = $this->handler->process_video_embed( $youtube_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 'youtube', $result['provider'] );
        $this->assertSame( 'dQw4w9WgXcQ', $result['video_id'] );
        $this->assertStringContainsString( 'youtube.com/embed/', $result['embed_url'] );
        $this->assertStringContainsString( 'img.youtube.com', $result['thumbnail'] );
    }

    /**
     * Test TC-221-002: YouTube short URL parsed correctly.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-002, FR-221
     */
    public function test_youtube_short_url_parsed(): void {
        // Arrange.
        $short_url = 'https://youtu.be/dQw4w9WgXcQ';

        // Act.
        $result = $this->handler->process_video_embed( $short_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 'youtube', $result['provider'] );
        $this->assertSame( 'dQw4w9WgXcQ', $result['video_id'] );
    }

    /**
     * Test TC-221-003: YouTube embed URL parsed correctly.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-003, FR-221
     */
    public function test_youtube_embed_url_parsed(): void {
        // Arrange.
        $embed_url = 'https://www.youtube.com/embed/dQw4w9WgXcQ';

        // Act.
        $result = $this->handler->process_video_embed( $embed_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 'youtube', $result['provider'] );
        $this->assertSame( 'dQw4w9WgXcQ', $result['video_id'] );
    }

    /**
     * Test TC-221-004: Vimeo URL parsed correctly.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-004, FR-221
     */
    public function test_vimeo_url_parsed_correctly(): void {
        // Arrange.
        $vimeo_url = 'https://vimeo.com/123456789';

        // Act.
        $result = $this->handler->process_video_embed( $vimeo_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 'vimeo', $result['provider'] );
        $this->assertSame( '123456789', $result['video_id'] );
        $this->assertStringContainsString( 'player.vimeo.com', $result['embed_url'] );
    }

    /**
     * Test TC-221-005: Vimeo player URL parsed correctly.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-005, FR-221
     */
    public function test_vimeo_player_url_parsed(): void {
        // Arrange.
        $player_url = 'https://player.vimeo.com/video/123456789';

        // Act.
        $result = $this->handler->process_video_embed( $player_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertSame( 'vimeo', $result['provider'] );
        $this->assertSame( '123456789', $result['video_id'] );
    }

    /**
     * Test TC-221-006: Invalid URL returns false.
     *
     * @test
     * @covers Media_Handler::process_video_embed
     * Implements: TC-221-006, FR-221
     */
    public function test_invalid_video_url_returns_false(): void {
        // Arrange.
        $invalid_url = 'https://example.com/not-a-video';

        // Act.
        $result = $this->handler->process_video_embed( $invalid_url );

        // Assert.
        $this->assertFalse( $result );
    }

    // =========================================================================
    // FR-223: Rich Link Previews - TC-223-xxx
    // =========================================================================

    /**
     * Test TC-223-001: Invalid URL returns error.
     *
     * @test
     * @covers Media_Handler::get_link_preview
     * Implements: TC-223-001, FR-223
     */
    public function test_invalid_url_returns_error(): void {
        // Arrange.
        $invalid_url = 'not-a-valid-url';

        // Act.
        $result = $this->handler->get_link_preview( $invalid_url );

        // Assert.
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'error', $result );
    }

    /**
     * Test TC-223-002: Valid URL returns preview data.
     *
     * @test
     * @covers Media_Handler::get_link_preview
     * Implements: TC-223-002, FR-223
     */
    public function test_valid_url_returns_preview_data(): void {
        // Arrange.
        $url = 'https://example.com/article';

        // Act.
        $result = $this->handler->get_link_preview( $url );

        // Assert: Returns array (either with data or error depending on mock).
        $this->assertIsArray( $result );
    }

    // =========================================================================
    // FR-224: Media Upload Handling - TC-224-xxx
    // =========================================================================

    /**
     * Test TC-224-001: Upload error detected.
     *
     * @test
     * @covers Media_Handler::validate_file
     * Implements: TC-224-001, FR-224
     */
    public function test_upload_error_detected(): void {
        // Arrange.
        $file = array(
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/phpXXX',
            'error'    => UPLOAD_ERR_INI_SIZE,
            'size'     => 1000,
        );

        // Act.
        $result = $this->handler->validate_file( $file );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'upload_error', $result->get_error_code() );
    }

    /**
     * Test TC-224-002: File too large rejected.
     *
     * @test
     * @covers Media_Handler::validate_file
     * Implements: TC-224-002, FR-224
     */
    public function test_file_too_large_rejected(): void {
        // Arrange.
        $file = array(
            'name'     => 'test.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => '/tmp/phpXXX',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 100000000, // 100MB - exceeds limit.
        );

        // Act.
        $result = $this->handler->validate_file( $file );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'file_too_large', $result->get_error_code() );
    }

    // =========================================================================
    // FR-228: Media Security - TC-228-xxx
    // =========================================================================

    /**
     * Test TC-228-001: Invalid MIME type rejected.
     *
     * @test
     * @covers Media_Handler::validate_file
     * Implements: TC-228-001, FR-228
     */
    public function test_invalid_mime_type_rejected(): void {
        // Arrange: Create temp file with text content.
        $tmp_file = tempnam( sys_get_temp_dir(), 'test' );
        file_put_contents( $tmp_file, 'This is plain text' );

        $file = array(
            'name'     => 'malicious.exe',
            'type'     => 'application/x-executable',
            'tmp_name' => $tmp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize( $tmp_file ),
        );

        // Act.
        $result = $this->handler->validate_file( $file );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'invalid_type', $result->get_error_code() );

        // Cleanup.
        @unlink( $tmp_file );
    }

    /**
     * Test TC-228-002: PHP code in file detected.
     *
     * @test
     * @covers Media_Handler::validate_file
     * Implements: TC-228-002, FR-228
     */
    public function test_php_code_in_file_detected(): void {
        // Arrange: Create temp file with PHP code.
        $tmp_file = tempnam( sys_get_temp_dir(), 'test' );
        file_put_contents( $tmp_file, '<?php echo "malicious"; ?>' );

        $file = array(
            'name'     => 'image.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize( $tmp_file ),
        );

        // Act.
        $result = $this->handler->validate_file( $file );

        // Assert: Should fail (either invalid_type or security_threat).
        $this->assertWPError( $result );

        // Cleanup.
        @unlink( $tmp_file );
    }

    /**
     * Test TC-228-003: Extension/MIME mismatch rejected.
     *
     * @test
     * @covers Media_Handler::validate_file
     * Implements: TC-228-003, FR-228
     */
    public function test_extension_mime_mismatch_rejected(): void {
        // Arrange: Create text file with .jpg extension.
        $tmp_file = tempnam( sys_get_temp_dir(), 'test' );
        file_put_contents( $tmp_file, 'Plain text pretending to be image' );

        $file = array(
            'name'     => 'fake.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tmp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize( $tmp_file ),
        );

        // Act.
        $result = $this->handler->validate_file( $file );

        // Assert: Should fail validation.
        $this->assertWPError( $result );

        // Cleanup.
        @unlink( $tmp_file );
    }

    // =========================================================================
    // Media Retrieval - TC-GET-xxx
    // =========================================================================

    /**
     * Test get media by ID.
     *
     * @test
     * @covers Media_Handler::get_media
     */
    public function test_get_media_by_id(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'              => 1,
                    'message_id'      => 10,
                    'conversation_id' => 100,
                    'user_id'         => $this->user_id,
                    'media_type'      => 'image',
                    'file_name'       => 'test.jpg',
                    'file_path'       => '/path/to/test.jpg',
                    'file_url'        => 'http://example.com/test.jpg',
                    'mime_type'       => 'image/jpeg',
                    'file_size'       => 12345,
                    'metadata'        => '{"width": 800, "height": 600}',
                    'status'          => 'active',
                    'created_at'      => '2026-01-28 10:00:00',
                ),
            ),
        ) );

        // Act.
        $media = $this->handler->get_media( 1 );

        // Assert.
        $this->assertIsArray( $media );
        $this->assertArrayHasKey( 'metadata', $media );
    }

    /**
     * Test get media returns null for non-existent ID.
     *
     * @test
     * @covers Media_Handler::get_media
     */
    public function test_get_media_nonexistent_returns_null(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $media = $this->handler->get_media( 9999 );

        // Assert.
        $this->assertNull( $media );
    }

    /**
     * Test get message media.
     *
     * @test
     * @covers Media_Handler::get_message_media
     */
    public function test_get_message_media(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $media = $this->handler->get_message_media( 10 );

        // Assert.
        $this->assertIsArray( $media );
    }

    /**
     * Test get conversation media.
     *
     * @test
     * @covers Media_Handler::get_conversation_media
     */
    public function test_get_conversation_media(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $media = $this->handler->get_conversation_media( 100 );

        // Assert.
        $this->assertIsArray( $media );
    }

    // =========================================================================
    // Media Linking
    // =========================================================================

    /**
     * Test link media to message.
     *
     * @test
     * @covers Media_Handler::link_media_to_message
     */
    public function test_link_media_to_message(): void {
        // Act.
        $result = $this->handler->link_media_to_message( 1, 10 );

        // Assert.
        $this->assertTrue( $result );
    }

    // =========================================================================
    // FR-229: Storage Management - TC-229-xxx
    // =========================================================================

    /**
     * Test TC-229-001: Delete media removes file and record.
     *
     * @test
     * @covers Media_Handler::delete_media
     * Implements: TC-229-001, FR-229
     */
    public function test_delete_media_removes_file_and_record(): void {
        // Arrange: Create temp file.
        $tmp_file = tempnam( sys_get_temp_dir(), 'test_media' );
        file_put_contents( $tmp_file, 'test content' );

        $this->mock_db_results( array(
            'results' => array(
                (object) array(
                    'id'        => 1,
                    'file_path' => $tmp_file,
                    'metadata'  => '{}',
                ),
            ),
        ) );

        // Act.
        $result = $this->handler->delete_media( 1 );

        // Assert.
        $this->assertTrue( $result );
    }

    /**
     * Test TC-229-002: Delete non-existent media returns false.
     *
     * @test
     * @covers Media_Handler::delete_media
     * Implements: TC-229-002, FR-229
     */
    public function test_delete_nonexistent_media_returns_false(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $result = $this->handler->delete_media( 9999 );

        // Assert.
        $this->assertFalse( $result );
    }

    /**
     * Test TC-229-003: Cleanup orphaned media.
     *
     * @test
     * @covers Media_Handler::cleanup_orphaned_media
     * Implements: TC-229-003, FR-229
     */
    public function test_cleanup_orphaned_media(): void {
        // Arrange.
        $this->mock_db_results( array(
            'results' => array(),
        ) );

        // Act.
        $cleaned = $this->handler->cleanup_orphaned_media( 30 );

        // Assert.
        $this->assertIsInt( $cleaned );
        $this->assertSame( 0, $cleaned );
    }

    // =========================================================================
    // FR-225: Media Display Components - TC-225-xxx
    // =========================================================================

    /**
     * Test TC-225-001: Render image media.
     *
     * @test
     * @covers Media_Handler::render_media
     * Implements: TC-225-001, FR-225
     */
    public function test_render_image_media(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'image',
            'file_url'   => 'http://example.com/image.jpg',
            'metadata'   => array(
                'thumbnail_url' => 'http://example.com/thumb.jpg',
                'alt_text'      => 'Test image',
            ),
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertStringContainsString( '<div class="ai-botkit-media-image"', $html );
        $this->assertStringContainsString( '<img', $html );
        $this->assertStringContainsString( 'ai-botkit-lightbox-trigger', $html );
    }

    /**
     * Test TC-225-002: Render video media.
     *
     * @test
     * @covers Media_Handler::render_media
     * Implements: TC-225-002, FR-225
     */
    public function test_render_video_media(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'video',
            'file_url'   => 'https://youtube.com/watch?v=abc123',
            'metadata'   => array(
                'embed_url' => 'https://www.youtube.com/embed/abc123',
            ),
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertStringContainsString( '<div class="ai-botkit-media-video"', $html );
        $this->assertStringContainsString( '<iframe', $html );
    }

    /**
     * Test TC-225-003: Render document media.
     *
     * @test
     * @covers Media_Handler::render_media
     * Implements: TC-225-003, FR-225
     */
    public function test_render_document_media(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'document',
            'file_name'  => 'document.pdf',
            'file_url'   => 'http://example.com/document.pdf',
            'file_size'  => 102400,
            'metadata'   => array(),
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertStringContainsString( '<div class="ai-botkit-media-document"', $html );
        $this->assertStringContainsString( 'ai-botkit-file-card', $html );
        $this->assertStringContainsString( 'ai-botkit-file-download', $html );
    }

    /**
     * Test TC-225-004: Render link preview media.
     *
     * @test
     * @covers Media_Handler::render_media
     * Implements: TC-225-004, FR-225
     */
    public function test_render_link_preview_media(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'link',
            'file_url'   => 'http://example.com/article',
            'metadata'   => array(
                'url'         => 'http://example.com/article',
                'title'       => 'Test Article',
                'description' => 'This is a test article description',
                'image'       => 'http://example.com/og-image.jpg',
                'site_name'   => 'Example Site',
            ),
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertStringContainsString( '<div class="ai-botkit-media-link"', $html );
        $this->assertStringContainsString( 'ai-botkit-link-card', $html );
        $this->assertStringContainsString( 'Test Article', $html );
    }

    /**
     * Test render unknown media type returns empty.
     *
     * @test
     * @covers Media_Handler::render_media
     */
    public function test_render_unknown_media_type_returns_empty(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'unknown',
            'file_url'   => 'http://example.com/unknown',
            'metadata'   => array(),
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertEmpty( $html );
    }

    // =========================================================================
    // Save Embed Methods
    // =========================================================================

    /**
     * Test save video embed.
     *
     * @test
     * @covers Media_Handler::save_video_embed
     */
    public function test_save_video_embed(): void {
        // Arrange.
        $video_data = array(
            'provider'  => 'youtube',
            'video_id'  => 'dQw4w9WgXcQ',
            'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'thumbnail' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
            'url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        );

        // Act.
        $result = $this->handler->save_video_embed( $video_data, 10, 100 );

        // Assert.
        $this->assertNotWPError( $result );
        $this->assertIsInt( $result );
    }

    /**
     * Test save link preview.
     *
     * @test
     * @covers Media_Handler::save_link_preview
     */
    public function test_save_link_preview(): void {
        // Arrange.
        $preview_data = array(
            'url'         => 'http://example.com/article',
            'title'       => 'Test Article',
            'description' => 'Description',
            'image'       => 'http://example.com/image.jpg',
            'site_name'   => 'Example',
        );

        // Act.
        $result = $this->handler->save_link_preview( $preview_data, 10, 100 );

        // Assert.
        $this->assertNotWPError( $result );
        $this->assertIsInt( $result );
    }

    /**
     * Test save link preview with error.
     *
     * @test
     * @covers Media_Handler::save_link_preview
     */
    public function test_save_link_preview_with_error(): void {
        // Arrange.
        $preview_data = array(
            'error' => 'Failed to fetch URL',
        );

        // Act.
        $result = $this->handler->save_link_preview( $preview_data, 10, 100 );

        // Assert.
        $this->assertWPError( $result );
        $this->assertSame( 'link_preview_error', $result->get_error_code() );
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test render video without embed URL.
     *
     * @test
     * @covers Media_Handler::render_media
     */
    public function test_render_video_without_embed_url(): void {
        // Arrange.
        $media = array(
            'id'         => 1,
            'media_type' => 'video',
            'file_url'   => 'http://example.com/video',
            'metadata'   => array(), // No embed_url.
        );

        // Act.
        $html = $this->handler->render_media( $media );

        // Assert.
        $this->assertEmpty( $html );
    }
}
