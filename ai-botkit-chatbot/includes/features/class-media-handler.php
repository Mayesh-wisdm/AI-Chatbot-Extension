<?php
/**
 * Media Handler
 *
 * Provides rich media support for chat messages including image uploads,
 * video embeds (YouTube/Vimeo), file attachments (PDF/DOC), and link previews.
 *
 * @package AI_BotKit\Features
 * @since   2.0.0
 *
 * Implements: FR-220 to FR-229 (Rich Media Support)
 */

namespace AI_BotKit\Features;

/**
 * Media_Handler class.
 *
 * Manages media operations including:
 * - File uploads for chat messages (images, documents)
 * - Video embed processing (YouTube, Vimeo)
 * - Link preview generation (OpenGraph)
 * - Media storage management and cleanup
 *
 * @since 2.0.0
 */
class Media_Handler {

    /**
     * Upload directory relative to wp-content/uploads.
     *
     * @var string
     */
    private const UPLOAD_DIR = 'ai-botkit/chat-media';

    /**
     * Allowed image MIME types.
     *
     * @var array
     */
    private const ALLOWED_IMAGE_TYPES = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    );

    /**
     * Allowed document MIME types.
     *
     * @var array
     */
    private const ALLOWED_DOC_TYPES = array(
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    /**
     * Default maximum file size in bytes (10MB).
     *
     * @var int
     */
    private const DEFAULT_MAX_FILE_SIZE = 10485760;

    /**
     * Media table name.
     *
     * @var string
     */
    private string $media_table;

    /**
     * Messages table name.
     *
     * @var string
     */
    private string $messages_table;

    /**
     * Maximum file size in bytes.
     *
     * @var int
     */
    private int $max_file_size;

    /**
     * Constructor.
     *
     * Initializes table names and configuration.
     *
     * @since 2.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->media_table    = $wpdb->prefix . 'ai_botkit_media';
        $this->messages_table = $wpdb->prefix . 'ai_botkit_messages';
        $this->max_file_size  = (int) get_option( 'ai_botkit_max_media_size', self::DEFAULT_MAX_FILE_SIZE );
    }

    /**
     * Upload a media file for chat attachment.
     *
     * Handles file validation, storage, thumbnail generation (for images),
     * and database record creation.
     *
     * Implements: FR-224 (Media Upload Handling)
     *
     * @since 2.0.0
     *
     * @param array    $file            $_FILES array element.
     * @param int|null $message_id      Associated message ID (optional).
     * @param int|null $conversation_id Associated conversation ID (optional).
     * @return array|\WP_Error {
     *     Media record on success, WP_Error on failure.
     *
     *     @type int    $id        Media record ID.
     *     @type string $url       Public URL to media.
     *     @type string $type      Media type (image|document).
     *     @type string $filename  Original filename.
     *     @type int    $size      File size in bytes.
     *     @type array  $metadata  Additional metadata (dimensions, etc.).
     * }
     */
    public function upload_media( array $file, ?int $message_id = null, ?int $conversation_id = null ) {
        // Validate the file.
        $validation = $this->validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Determine media type.
        $media_type = $this->get_media_type( $file['type'] );

        // Generate safe filename.
        $safe_filename = $this->generate_safe_filename( $file['name'] );

        // Create upload directory structure.
        $upload_path = $this->create_upload_directory( $media_type );
        if ( is_wp_error( $upload_path ) ) {
            return $upload_path;
        }

        // Move uploaded file.
        $file_path = $upload_path['path'] . '/' . $safe_filename;
        $file_url  = $upload_path['url'] . '/' . $safe_filename;

        // Use wp_handle_upload for proper WordPress handling.
        $upload_overrides = array(
            'test_form' => false,
            'test_type' => false,
        );

        // Manually move file since we're using custom directory.
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'upload_failed',
                __( 'Failed to move uploaded file.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        // Set proper permissions.
        chmod( $file_path, 0644 );

        // Generate metadata based on media type.
        $metadata = $this->generate_metadata( $file_path, $media_type, $file );

        // Create database record.
        $media_id = $this->create_media_record(
            $message_id,
            $conversation_id,
            $media_type,
            $safe_filename,
            $file_path,
            $file_url,
            $file['type'],
            $file['size'],
            $metadata
        );

        if ( is_wp_error( $media_id ) ) {
            // Clean up the uploaded file.
            wp_delete_file( $file_path );
            return $media_id;
        }

        /**
         * Fires after a media file is uploaded.
         *
         * @since 2.0.0
         *
         * @param int   $media_id  Media record ID.
         * @param array $file      Original file data.
         * @param int   $user_id   Uploader user ID.
         */
        do_action( 'ai_botkit_media_uploaded', $media_id, $file, get_current_user_id() );

        return array(
            'id'        => $media_id,
            'url'       => $file_url,
            'type'      => $media_type,
            'filename'  => $safe_filename,
            'size'      => $file['size'],
            'mime_type' => $file['type'],
            'metadata'  => $metadata,
        );
    }

    /**
     * Validate file for upload.
     *
     * Performs comprehensive security checks including MIME type verification,
     * file size limits, and content scanning.
     *
     * Implements: FR-228 (Media Security)
     *
     * @since 2.0.0
     *
     * @param array $file $_FILES array element.
     * @return true|\WP_Error True if valid, WP_Error otherwise.
     */
    public function validate_file( array $file ) {
        // Check for upload errors.
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error(
                'upload_error',
                $this->get_upload_error_message( $file['error'] ),
                array( 'status' => 400 )
            );
        }

        // Check file size.
        if ( $file['size'] > $this->max_file_size ) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: maximum file size */
                    __( 'File exceeds maximum size of %s.', 'knowvault' ),
                    size_format( $this->max_file_size )
                ),
                array( 'status' => 400 )
            );
        }

        // Verify MIME type using file content (not just extension).
        $finfo     = new \finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->file( $file['tmp_name'] );

        // Get allowed types.
        $allowed_types = $this->get_allowed_media_types();

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            return new \WP_Error(
                'invalid_type',
                __( 'File type not allowed. Allowed types: JPG, PNG, GIF, WebP, PDF, DOC, DOCX.', 'knowvault' ),
                array( 'status' => 400 )
            );
        }

        // Verify extension matches MIME type.
        $extension       = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $valid_extension = $this->validate_extension_mime_match( $extension, $mime_type );

        if ( ! $valid_extension ) {
            return new \WP_Error(
                'mime_mismatch',
                __( 'File extension does not match file content.', 'knowvault' ),
                array( 'status' => 400 )
            );
        }

        // Scan for PHP or executable code.
        if ( $this->contains_executable_code( $file['tmp_name'] ) ) {
            return new \WP_Error(
                'security_threat',
                __( 'File contains potentially malicious content.', 'knowvault' ),
                array( 'status' => 400 )
            );
        }

        // Additional image validation.
        if ( in_array( $mime_type, self::ALLOWED_IMAGE_TYPES, true ) ) {
            $image_info = @getimagesize( $file['tmp_name'] );
            if ( $image_info === false ) {
                return new \WP_Error(
                    'invalid_image',
                    __( 'File is not a valid image.', 'knowvault' ),
                    array( 'status' => 400 )
                );
            }
        }

        return true;
    }

    /**
     * Process video embed URL.
     *
     * Extracts video information from YouTube and Vimeo URLs and
     * returns embed data.
     *
     * Implements: FR-221 (Video Embeds)
     *
     * @since 2.0.0
     *
     * @param string $url Video URL.
     * @return array|false {
     *     Video embed data on success, false on failure.
     *
     *     @type string $provider    Video provider (youtube|vimeo).
     *     @type string $video_id    Provider video ID.
     *     @type string $embed_url   Embeddable iframe URL.
     *     @type string $thumbnail   Video thumbnail URL.
     * }
     */
    public function process_video_embed( string $url ) {
        // Check if it's a YouTube URL.
        $youtube = $this->parse_youtube_url( $url );
        if ( $youtube ) {
            return array(
                'provider'   => 'youtube',
                'video_id'   => $youtube['id'],
                'embed_url'  => 'https://www.youtube.com/embed/' . $youtube['id'],
                'thumbnail'  => 'https://img.youtube.com/vi/' . $youtube['id'] . '/maxresdefault.jpg',
                'url'        => $url,
            );
        }

        // Check if it's a Vimeo URL.
        $vimeo = $this->parse_vimeo_url( $url );
        if ( $vimeo ) {
            // Get Vimeo thumbnail via oEmbed.
            $thumbnail = $this->get_vimeo_thumbnail( $vimeo['id'] );

            return array(
                'provider'   => 'vimeo',
                'video_id'   => $vimeo['id'],
                'embed_url'  => 'https://player.vimeo.com/video/' . $vimeo['id'],
                'thumbnail'  => $thumbnail,
                'url'        => $url,
            );
        }

        return false;
    }

    /**
     * Fetch link preview data (OpenGraph).
     *
     * Retrieves metadata from a URL including title, description,
     * and image using OpenGraph tags.
     *
     * Implements: FR-223 (Rich Link Previews)
     *
     * @since 2.0.0
     *
     * @param string $url URL to fetch preview for.
     * @return array {
     *     Link preview data.
     *
     *     @type string $title       Page title.
     *     @type string $description Meta description.
     *     @type string $image       og:image URL.
     *     @type string $site_name   og:site_name.
     *     @type string $url         Canonical URL.
     *     @type string $favicon     Favicon URL.
     * }
     */
    public function get_link_preview( string $url ): array {
        // Validate URL.
        if ( ! wp_http_validate_url( $url ) ) {
            return array(
                'error' => __( 'Invalid URL.', 'knowvault' ),
            );
        }

        // Check cache first.
        $cache_key = 'ai_botkit_link_preview_' . md5( $url );
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        // Fetch the URL.
        $response = wp_remote_get(
            $url,
            array(
                'timeout'    => 5,
                'sslverify'  => true,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'headers'    => array(
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'error' => $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return array(
                'error' => sprintf(
                    /* translators: %d: HTTP response code */
                    __( 'Failed to fetch URL (HTTP %d).', 'knowvault' ),
                    $response_code
                ),
            );
        }

        $html = wp_remote_retrieve_body( $response );

        // Extract metadata.
        $preview = $this->extract_link_metadata( $html, $url );

        // Apply filter for customization.
        $preview = apply_filters( 'ai_botkit_link_preview_data', $preview, $url );

        // Cache for 1 hour.
        set_transient( $cache_key, $preview, HOUR_IN_SECONDS );

        return $preview;
    }

    /**
     * Get media record by ID.
     *
     * @since 2.0.0
     *
     * @param int $media_id Media ID.
     * @return array|null Media data or null if not found.
     */
    public function get_media( int $media_id ): ?array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $media = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->media_table} WHERE id = %d",
                $media_id
            ),
            ARRAY_A
        );

        if ( ! $media ) {
            return null;
        }

        $media['metadata'] = json_decode( $media['metadata'], true );

        return $media;
    }

    /**
     * Get media records for a message.
     *
     * @since 2.0.0
     *
     * @param int $message_id Message ID.
     * @return array List of media records.
     */
    public function get_message_media( int $message_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $media = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->media_table}
                 WHERE message_id = %d
                 AND status = 'active'
                 ORDER BY created_at ASC",
                $message_id
            ),
            ARRAY_A
        );

        foreach ( $media as &$item ) {
            $item['metadata'] = json_decode( $item['metadata'], true );
        }

        return $media;
    }

    /**
     * Get media records for a conversation.
     *
     * @since 2.0.0
     *
     * @param int $conversation_id Conversation ID.
     * @return array List of media records.
     */
    public function get_conversation_media( int $conversation_id ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $media = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->media_table}
                 WHERE conversation_id = %d
                 AND status = 'active'
                 ORDER BY created_at ASC",
                $conversation_id
            ),
            ARRAY_A
        );

        foreach ( $media as &$item ) {
            $item['metadata'] = json_decode( $item['metadata'], true );
        }

        return $media;
    }

    /**
     * Link media to a message.
     *
     * Associates an uploaded media file with a chat message.
     *
     * @since 2.0.0
     *
     * @param int $media_id   Media ID.
     * @param int $message_id Message ID.
     * @return bool True on success, false on failure.
     */
    public function link_media_to_message( int $media_id, int $message_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->media_table,
            array(
                'message_id' => $message_id,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $media_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Delete media file and record.
     *
     * Implements: FR-229 (Storage Management)
     *
     * @since 2.0.0
     *
     * @param int $media_id Media ID.
     * @return bool True on success, false on failure.
     */
    public function delete_media( int $media_id ): bool {
        global $wpdb;

        // Get media record.
        $media = $this->get_media( $media_id );
        if ( ! $media ) {
            return false;
        }

        // Delete the file.
        if ( file_exists( $media['file_path'] ) ) {
            wp_delete_file( $media['file_path'] );
        }

        // Delete thumbnail if exists.
        if ( ! empty( $media['metadata']['thumbnail_path'] ) && file_exists( $media['metadata']['thumbnail_path'] ) ) {
            wp_delete_file( $media['metadata']['thumbnail_path'] );
        }

        // Delete database record.
        $result = $wpdb->delete(
            $this->media_table,
            array( 'id' => $media_id ),
            array( '%d' )
        );

        if ( $result ) {
            /**
             * Fires after media is deleted.
             *
             * @since 2.0.0
             *
             * @param int $media_id Media ID.
             */
            do_action( 'ai_botkit_media_deleted', $media_id );

            return true;
        }

        return false;
    }

    /**
     * Cleanup orphaned media files.
     *
     * Removes media files that are not linked to any message and
     * are older than the specified number of days.
     *
     * Implements: FR-229 (Storage Management)
     *
     * @since 2.0.0
     *
     * @param int $days_old Days threshold for orphan cleanup.
     * @return int Number of files cleaned up.
     */
    public function cleanup_orphaned_media( int $days_old = 30 ): int {
        global $wpdb;

        // First, mark orphaned media (no message_id and older than 24 hours).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->media_table}
                 SET status = 'orphaned'
                 WHERE message_id IS NULL
                 AND status = 'active'
                 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )
        );

        // Get orphaned media older than specified days.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $orphans = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->media_table}
                 WHERE status = 'orphaned'
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_old
            ),
            ARRAY_A
        );

        $cleaned_count = 0;

        foreach ( $orphans as $orphan ) {
            if ( $this->delete_media( $orphan['id'] ) ) {
                $cleaned_count++;
            }
        }

        return $cleaned_count;
    }

    /**
     * Render media HTML for display.
     *
     * Generates appropriate HTML for different media types.
     *
     * Implements: FR-225 (Media Display Components)
     *
     * @since 2.0.0
     *
     * @param array $media Media record.
     * @return string HTML output.
     */
    public function render_media( array $media ): string {
        $type = $media['media_type'];

        switch ( $type ) {
            case 'image':
                return $this->render_image( $media );
            case 'video':
                return $this->render_video( $media );
            case 'document':
                return $this->render_document( $media );
            case 'link':
                return $this->render_link_preview( $media );
            default:
                return '';
        }
    }

    /**
     * Render image HTML.
     *
     * Implements: FR-220 (Image Attachments), FR-226 (Lightbox)
     *
     * @since 2.0.0
     *
     * @param array $media Media record.
     * @return string HTML output.
     */
    private function render_image( array $media ): string {
        $metadata = $media['metadata'] ?? array();
        $alt_text = $metadata['alt_text'] ?? __( 'Chat image', 'knowvault' );
        $thumb    = $metadata['thumbnail_url'] ?? $media['file_url'];

        $html  = '<div class="ai-botkit-media-image" data-media-id="' . esc_attr( $media['id'] ) . '">';
        $html .= '<a href="' . esc_url( $media['file_url'] ) . '" class="ai-botkit-lightbox-trigger" data-lightbox="chat-media">';
        $html .= '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $alt_text ) . '" loading="lazy" />';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render video embed HTML.
     *
     * Implements: FR-221 (Video Embeds)
     *
     * @since 2.0.0
     *
     * @param array $media Media record.
     * @return string HTML output.
     */
    private function render_video( array $media ): string {
        $metadata = $media['metadata'] ?? array();

        if ( empty( $metadata['embed_url'] ) ) {
            return '';
        }

        $html  = '<div class="ai-botkit-media-video" data-media-id="' . esc_attr( $media['id'] ) . '">';
        $html .= '<div class="ai-botkit-video-wrapper">';
        $html .= '<iframe src="' . esc_url( $metadata['embed_url'] ) . '" ';
        $html .= 'frameborder="0" ';
        $html .= 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
        $html .= 'allowfullscreen></iframe>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render document download card HTML.
     *
     * Implements: FR-222 (File Attachments), FR-227 (File Download)
     *
     * @since 2.0.0
     *
     * @param array $media Media record.
     * @return string HTML output.
     */
    private function render_document( array $media ): string {
        $extension = pathinfo( $media['file_name'], PATHINFO_EXTENSION );
        $icon      = $this->get_file_icon( $extension );
        $size      = size_format( $media['file_size'] );

        $download_url = add_query_arg(
            array(
                'action'   => 'ai_botkit_download_media',
                'media_id' => $media['id'],
                'nonce'    => wp_create_nonce( 'ai_botkit_download_' . $media['id'] ),
            ),
            admin_url( 'admin-ajax.php' )
        );

        $html  = '<div class="ai-botkit-media-document" data-media-id="' . esc_attr( $media['id'] ) . '">';
        $html .= '<div class="ai-botkit-file-card">';
        $html .= '<div class="ai-botkit-file-icon">' . $icon . '</div>';
        $html .= '<div class="ai-botkit-file-info">';
        $html .= '<span class="ai-botkit-file-name">' . esc_html( $this->truncate_filename( $media['file_name'] ) ) . '</span>';
        $html .= '<span class="ai-botkit-file-size">' . esc_html( strtoupper( $extension ) ) . ' - ' . esc_html( $size ) . '</span>';
        $html .= '</div>';
        $html .= '<a href="' . esc_url( $download_url ) . '" class="ai-botkit-file-download" download>';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render link preview card HTML.
     *
     * Implements: FR-223 (Rich Link Previews)
     *
     * @since 2.0.0
     *
     * @param array $media Media record.
     * @return string HTML output.
     */
    private function render_link_preview( array $media ): string {
        $metadata = $media['metadata'] ?? array();

        $html  = '<div class="ai-botkit-media-link" data-media-id="' . esc_attr( $media['id'] ) . '">';
        $html .= '<a href="' . esc_url( $metadata['url'] ?? $media['file_url'] ) . '" target="_blank" rel="noopener noreferrer" class="ai-botkit-link-card">';

        if ( ! empty( $metadata['image'] ) ) {
            $html .= '<div class="ai-botkit-link-image">';
            $html .= '<img src="' . esc_url( $metadata['image'] ) . '" alt="" loading="lazy" />';
            $html .= '</div>';
        }

        $html .= '<div class="ai-botkit-link-content">';

        if ( ! empty( $metadata['site_name'] ) ) {
            $html .= '<span class="ai-botkit-link-site">' . esc_html( $metadata['site_name'] ) . '</span>';
        }

        if ( ! empty( $metadata['title'] ) ) {
            $html .= '<span class="ai-botkit-link-title">' . esc_html( $metadata['title'] ) . '</span>';
        }

        if ( ! empty( $metadata['description'] ) ) {
            $html .= '<span class="ai-botkit-link-description">' . esc_html( $this->truncate_text( $metadata['description'], 150 ) ) . '</span>';
        }

        $html .= '</div>';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get allowed media types.
     *
     * @since 2.0.0
     *
     * @return array List of allowed MIME types.
     */
    private function get_allowed_media_types(): array {
        $types = array_merge(
            self::ALLOWED_IMAGE_TYPES,
            self::ALLOWED_DOC_TYPES
        );

        /**
         * Filters allowed media types for chat uploads.
         *
         * @since 2.0.0
         *
         * @param array $types List of allowed MIME types.
         */
        return apply_filters( 'ai_botkit_allowed_media_types', $types );
    }

    /**
     * Get media type from MIME type.
     *
     * @since 2.0.0
     *
     * @param string $mime_type MIME type.
     * @return string Media type (image|document|video|link).
     */
    private function get_media_type( string $mime_type ): string {
        if ( in_array( $mime_type, self::ALLOWED_IMAGE_TYPES, true ) ) {
            return 'image';
        }

        if ( in_array( $mime_type, self::ALLOWED_DOC_TYPES, true ) ) {
            return 'document';
        }

        return 'document';
    }

    /**
     * Generate safe filename.
     *
     * @since 2.0.0
     *
     * @param string $filename Original filename.
     * @return string Safe filename.
     */
    private function generate_safe_filename( string $filename ): string {
        // Get extension.
        $extension = pathinfo( $filename, PATHINFO_EXTENSION );
        $name      = pathinfo( $filename, PATHINFO_FILENAME );

        // Sanitize name.
        $name = sanitize_file_name( $name );
        $name = preg_replace( '/[^a-zA-Z0-9_-]/', '', $name );

        // Limit length.
        if ( strlen( $name ) > 50 ) {
            $name = substr( $name, 0, 50 );
        }

        // Add unique identifier.
        $unique = uniqid();

        return $unique . '_' . $name . '.' . $extension;
    }

    /**
     * Create upload directory structure.
     *
     * @since 2.0.0
     *
     * @param string $media_type Media type.
     * @return array|\WP_Error Path and URL on success, WP_Error on failure.
     */
    private function create_upload_directory( string $media_type ) {
        $upload_dir = wp_upload_dir();

        if ( $upload_dir['error'] ) {
            return new \WP_Error(
                'upload_dir_error',
                $upload_dir['error'],
                array( 'status' => 500 )
            );
        }

        $year  = gmdate( 'Y' );
        $month = gmdate( 'm' );

        $type_dir = ( $media_type === 'image' ) ? 'images' : 'files';

        $dir_path = $upload_dir['basedir'] . '/' . self::UPLOAD_DIR . '/' . $type_dir . '/' . $year . '/' . $month;
        $dir_url  = $upload_dir['baseurl'] . '/' . self::UPLOAD_DIR . '/' . $type_dir . '/' . $year . '/' . $month;

        // Create directory if it doesn't exist.
        if ( ! file_exists( $dir_path ) ) {
            if ( ! wp_mkdir_p( $dir_path ) ) {
                return new \WP_Error(
                    'mkdir_failed',
                    __( 'Failed to create upload directory.', 'knowvault' ),
                    array( 'status' => 500 )
                );
            }

            // Create .htaccess for security.
            $this->create_htaccess( $dir_path );
        }

        return array(
            'path' => $dir_path,
            'url'  => $dir_url,
        );
    }

    /**
     * Create .htaccess file to prevent PHP execution.
     *
     * Implements: FR-228 (Media Security)
     *
     * @since 2.0.0
     *
     * @param string $dir_path Directory path.
     */
    private function create_htaccess( string $dir_path ): void {
        $htaccess_path = dirname( dirname( dirname( dirname( $dir_path ) ) ) ) . '/.htaccess';

        if ( file_exists( $htaccess_path ) ) {
            return;
        }

        $htaccess_content = "# Disable PHP execution\n";
        $htaccess_content .= "php_flag engine off\n";
        $htaccess_content .= "AddHandler default-handler .php .php3 .php4 .php5 .phtml .pl .py .cgi\n";
        $htaccess_content .= "\n";
        $htaccess_content .= "# Disable directory listing\n";
        $htaccess_content .= "Options -Indexes\n";

        file_put_contents( $htaccess_path, $htaccess_content );
    }

    /**
     * Generate metadata for uploaded file.
     *
     * @since 2.0.0
     *
     * @param string $file_path File path.
     * @param string $media_type Media type.
     * @param array  $file Original file data.
     * @return array Metadata array.
     */
    private function generate_metadata( string $file_path, string $media_type, array $file ): array {
        $metadata = array();

        if ( $media_type === 'image' ) {
            $image_info = @getimagesize( $file_path );

            if ( $image_info ) {
                $metadata['width']       = $image_info[0];
                $metadata['height']      = $image_info[1];
                $metadata['orientation'] = ( $image_info[0] > $image_info[1] ) ? 'landscape' : 'portrait';

                // Generate thumbnail.
                $thumbnail = $this->generate_thumbnail( $file_path, $image_info[0], $image_info[1] );
                if ( $thumbnail ) {
                    $metadata['thumbnail_url']  = $thumbnail['url'];
                    $metadata['thumbnail_path'] = $thumbnail['path'];
                }
            }
        } elseif ( $media_type === 'document' ) {
            $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

            if ( $extension === 'pdf' ) {
                // Try to get PDF page count.
                $metadata['page_count'] = $this->get_pdf_page_count( $file_path );
            }
        }

        $metadata['original_name'] = $file['name'];

        return $metadata;
    }

    /**
     * Generate thumbnail for image.
     *
     * @since 2.0.0
     *
     * @param string $file_path Original file path.
     * @param int    $width Original width.
     * @param int    $height Original height.
     * @return array|false Thumbnail data or false on failure.
     */
    private function generate_thumbnail( string $file_path, int $width, int $height ) {
        // Skip if image is already small.
        if ( $width <= 400 && $height <= 400 ) {
            return false;
        }

        $editor = wp_get_image_editor( $file_path );

        if ( is_wp_error( $editor ) ) {
            return false;
        }

        // Calculate thumbnail size (max 400px).
        $max_size = 400;

        if ( $width > $height ) {
            $new_width  = $max_size;
            $new_height = intval( $height * ( $max_size / $width ) );
        } else {
            $new_height = $max_size;
            $new_width  = intval( $width * ( $max_size / $height ) );
        }

        $editor->resize( $new_width, $new_height, false );

        // Generate thumbnail filename.
        $path_info = pathinfo( $file_path );
        $thumb_path = $path_info['dirname'] . '/thumb_' . $path_info['basename'];

        $saved = $editor->save( $thumb_path );

        if ( is_wp_error( $saved ) ) {
            return false;
        }

        // Calculate URL from path.
        $upload_dir = wp_upload_dir();
        $thumb_url  = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $saved['path'] );

        return array(
            'path' => $saved['path'],
            'url'  => $thumb_url,
        );
    }

    /**
     * Get PDF page count.
     *
     * @since 2.0.0
     *
     * @param string $file_path PDF file path.
     * @return int|null Page count or null if unable to determine.
     */
    private function get_pdf_page_count( string $file_path ): ?int {
        $content = @file_get_contents( $file_path );

        if ( $content === false ) {
            return null;
        }

        // Simple regex to count pages.
        preg_match_all( '/\/Type\s*\/Page[^s]/s', $content, $matches );

        return ! empty( $matches[0] ) ? count( $matches[0] ) : null;
    }

    /**
     * Create media database record.
     *
     * @since 2.0.0
     *
     * @param int|null $message_id Message ID.
     * @param int|null $conversation_id Conversation ID.
     * @param string   $media_type Media type.
     * @param string   $file_name File name.
     * @param string   $file_path File path.
     * @param string   $file_url File URL.
     * @param string   $mime_type MIME type.
     * @param int      $file_size File size.
     * @param array    $metadata Metadata.
     * @return int|\WP_Error Media ID or error.
     */
    private function create_media_record(
        ?int $message_id,
        ?int $conversation_id,
        string $media_type,
        string $file_name,
        string $file_path,
        string $file_url,
        string $mime_type,
        int $file_size,
        array $metadata
    ) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->media_table,
            array(
                'message_id'      => $message_id,
                'conversation_id' => $conversation_id,
                'user_id'         => get_current_user_id(),
                'media_type'      => $media_type,
                'file_name'       => $file_name,
                'file_path'       => $file_path,
                'file_url'        => $file_url,
                'mime_type'       => $mime_type,
                'file_size'       => $file_size,
                'metadata'        => wp_json_encode( $metadata ),
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array(
                '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            )
        );

        if ( $result === false ) {
            return new \WP_Error(
                'db_insert_failed',
                __( 'Failed to create media record.', 'knowvault' ),
                array( 'status' => 500 )
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Validate extension matches MIME type.
     *
     * @since 2.0.0
     *
     * @param string $extension File extension.
     * @param string $mime_type MIME type.
     * @return bool True if valid match.
     */
    private function validate_extension_mime_match( string $extension, string $mime_type ): bool {
        $map = array(
            'jpg'  => array( 'image/jpeg' ),
            'jpeg' => array( 'image/jpeg' ),
            'png'  => array( 'image/png' ),
            'gif'  => array( 'image/gif' ),
            'webp' => array( 'image/webp' ),
            'pdf'  => array( 'application/pdf' ),
            'doc'  => array( 'application/msword' ),
            'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
        );

        if ( ! isset( $map[ $extension ] ) ) {
            return false;
        }

        return in_array( $mime_type, $map[ $extension ], true );
    }

    /**
     * Check if file contains executable code.
     *
     * @since 2.0.0
     *
     * @param string $file_path File path.
     * @return bool True if contains executable code.
     */
    private function contains_executable_code( string $file_path ): bool {
        $content = @file_get_contents( $file_path, false, null, 0, 1024 );

        if ( $content === false ) {
            return false;
        }

        // Check for PHP tags.
        if ( preg_match( '/<\?php|<\?=/i', $content ) ) {
            return true;
        }

        // Check for script tags.
        if ( preg_match( '/<script/i', $content ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get upload error message.
     *
     * @since 2.0.0
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message( int $error_code ): string {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the upload_max_filesize directive.', 'knowvault' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the MAX_FILE_SIZE directive.', 'knowvault' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'knowvault' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'knowvault' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'knowvault' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'knowvault' ),
            UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by extension.', 'knowvault' ),
        );

        return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'knowvault' );
    }

    /**
     * Parse YouTube URL.
     *
     * @since 2.0.0
     *
     * @param string $url URL to parse.
     * @return array|false Video data or false.
     */
    private function parse_youtube_url( string $url ) {
        // youtube.com/watch?v=VIDEO_ID
        if ( preg_match( '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
            return array( 'id' => $matches[1] );
        }

        // youtu.be/VIDEO_ID
        if ( preg_match( '/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
            return array( 'id' => $matches[1] );
        }

        // youtube.com/embed/VIDEO_ID
        if ( preg_match( '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
            return array( 'id' => $matches[1] );
        }

        return false;
    }

    /**
     * Parse Vimeo URL.
     *
     * @since 2.0.0
     *
     * @param string $url URL to parse.
     * @return array|false Video data or false.
     */
    private function parse_vimeo_url( string $url ) {
        // vimeo.com/VIDEO_ID
        if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $matches ) ) {
            return array( 'id' => $matches[1] );
        }

        // player.vimeo.com/video/VIDEO_ID
        if ( preg_match( '/player\.vimeo\.com\/video\/(\d+)/', $url, $matches ) ) {
            return array( 'id' => $matches[1] );
        }

        return false;
    }

    /**
     * Get Vimeo thumbnail via API.
     *
     * @since 2.0.0
     *
     * @param string $video_id Vimeo video ID.
     * @return string Thumbnail URL.
     */
    private function get_vimeo_thumbnail( string $video_id ): string {
        $response = wp_remote_get( 'https://vimeo.com/api/v2/video/' . $video_id . '.json' );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body[0]['thumbnail_large'] ?? '';
    }

    /**
     * Extract link metadata from HTML.
     *
     * @since 2.0.0
     *
     * @param string $html HTML content.
     * @param string $url  Original URL.
     * @return array Extracted metadata.
     */
    private function extract_link_metadata( string $html, string $url ): array {
        $metadata = array(
            'url'         => $url,
            'title'       => '',
            'description' => '',
            'image'       => '',
            'site_name'   => '',
            'favicon'     => '',
        );

        // Extract OpenGraph tags.
        if ( preg_match( '/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $metadata['title'] = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        } elseif ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
            $metadata['title'] = html_entity_decode( trim( $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        if ( preg_match( '/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $metadata['description'] = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        } elseif ( preg_match( '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $metadata['description'] = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        if ( preg_match( '/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $metadata['image'] = $matches[1];
        }

        if ( preg_match( '/<meta[^>]*property=["\']og:site_name["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $metadata['site_name'] = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        // Get domain as fallback site name.
        if ( empty( $metadata['site_name'] ) ) {
            $parsed_url            = wp_parse_url( $url );
            $metadata['site_name'] = $parsed_url['host'] ?? '';
        }

        // Get favicon.
        if ( preg_match( '/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
            $favicon = $matches[1];
            if ( strpos( $favicon, '//' ) === 0 ) {
                $favicon = 'https:' . $favicon;
            } elseif ( strpos( $favicon, '/' ) === 0 ) {
                $parsed_url = wp_parse_url( $url );
                $favicon    = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $favicon;
            }
            $metadata['favicon'] = $favicon;
        }

        return $metadata;
    }

    /**
     * Get file icon SVG.
     *
     * @since 2.0.0
     *
     * @param string $extension File extension.
     * @return string SVG icon HTML.
     */
    private function get_file_icon( string $extension ): string {
        $icons = array(
            'pdf'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
            'doc'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            'docx' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        );

        $default = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';

        return $icons[ strtolower( $extension ) ] ?? $default;
    }

    /**
     * Truncate filename for display.
     *
     * @since 2.0.0
     *
     * @param string $filename Filename.
     * @param int    $max_length Maximum length.
     * @return string Truncated filename.
     */
    private function truncate_filename( string $filename, int $max_length = 30 ): string {
        if ( strlen( $filename ) <= $max_length ) {
            return $filename;
        }

        $extension = pathinfo( $filename, PATHINFO_EXTENSION );
        $name      = pathinfo( $filename, PATHINFO_FILENAME );

        $available = $max_length - strlen( $extension ) - 4; // 4 for "..." and "."
        $name      = substr( $name, 0, $available ) . '...';

        return $name . '.' . $extension;
    }

    /**
     * Truncate text for display.
     *
     * @since 2.0.0
     *
     * @param string $text Text to truncate.
     * @param int    $length Maximum length.
     * @return string Truncated text.
     */
    private function truncate_text( string $text, int $length = 150 ): string {
        $text = wp_strip_all_tags( $text );
        $text = trim( $text );

        if ( strlen( $text ) <= $length ) {
            return $text;
        }

        return substr( $text, 0, $length ) . '...';
    }

    /**
     * Save video embed as media record.
     *
     * Creates a database record for a video embed.
     *
     * @since 2.0.0
     *
     * @param array    $video_data      Video embed data from process_video_embed().
     * @param int|null $message_id      Associated message ID.
     * @param int|null $conversation_id Associated conversation ID.
     * @return int|\WP_Error Media ID or error.
     */
    public function save_video_embed( array $video_data, ?int $message_id = null, ?int $conversation_id = null ) {
        return $this->create_media_record(
            $message_id,
            $conversation_id,
            'video',
            $video_data['video_id'] . '_' . $video_data['provider'],
            '',
            $video_data['url'],
            'video/embed',
            0,
            $video_data
        );
    }

    /**
     * Save link preview as media record.
     *
     * Creates a database record for a link preview.
     *
     * @since 2.0.0
     *
     * @param array    $preview_data    Link preview data from get_link_preview().
     * @param int|null $message_id      Associated message ID.
     * @param int|null $conversation_id Associated conversation ID.
     * @return int|\WP_Error Media ID or error.
     */
    public function save_link_preview( array $preview_data, ?int $message_id = null, ?int $conversation_id = null ) {
        if ( isset( $preview_data['error'] ) ) {
            return new \WP_Error(
                'link_preview_error',
                $preview_data['error'],
                array( 'status' => 400 )
            );
        }

        return $this->create_media_record(
            $message_id,
            $conversation_id,
            'link',
            md5( $preview_data['url'] ),
            '',
            $preview_data['url'],
            'text/html',
            0,
            $preview_data
        );
    }
}
