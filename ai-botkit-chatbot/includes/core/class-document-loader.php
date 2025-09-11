<?php
namespace AI_BotKit\Core;

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

/**
 * Document Loader class to handle loading documents from various sources
 * 
 * Migrated from: /ref/src/DocumentLoader.php
 * Improvements:
 * - WordPress file handling
 * - WP HTTP API integration
 * - WordPress security measures
 * - Support for WordPress post types
 */
class Document_Loader {
    /**
     * Load a document from a local file
     * 
     * @param string $file_path Path to the file
     * @return array Array containing document content and metadata
     * @throws \Exception If file cannot be loaded
     */
    public function load_from_file(string $file_path, int $document_id): array {
        // Verify file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            throw new \Exception(
                esc_html__('File not found or not readable: ', 'ai-botkit-for-lead-generation') . esc_html($file_path)
            );
        }

        // Security check - verify file is in allowed directory
        $upload_dir = wp_upload_dir();
        $allowed_dirs = [
            $upload_dir['basedir'],
            AI_BOTKIT_PLUGIN_DIR . 'sample_data'
        ];

        $is_allowed = false;
        foreach ($allowed_dirs as $dir) {
            if (strpos(realpath($file_path), realpath($dir)) === 0) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            throw new \Exception(
                esc_html__('File location not allowed: ', 'ai-botkit-for-lead-generation') . esc_html($file_path)
            );
        }

        // Get file content and metadata
        $content = file_get_contents($file_path); // @codingStandardsIgnoreLine not accessible via url
        $mime_type = wp_check_filetype($file_path)['type'];
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        
        return [
            'content' => $this->parse_content($content, $extension),
            'metadata' => [
                'source' => $file_path,
                'document_id' => $document_id,
                'mime_type' => $mime_type,
                'extension' => $extension,
                'size' => filesize($file_path),
                'last_modified' => filemtime($file_path)
            ]
        ];
    }

    /**
     * Load a document from a URL
     * 
     * @param string $url URL to fetch the document from
     * @return array Array containing document content and metadata
     * @throws \Exception If URL cannot be loaded
     */
    public function load_from_url(string $url, int $document_id): array {
        // Verify URL is valid
        if (!wp_http_validate_url($url)) {
            throw new \Exception(
                esc_html__('Invalid URL: ', 'ai-botkit-for-lead-generation') . esc_html($url)
            );
        }

        // Use WordPress HTTP API
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(
                esc_html__('Failed to fetch document from URL: ', 'ai-botkit-for-lead-generation') . esc_html($response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception(
                esc_html__('Failed to fetch document from URL: HTTP ', 'ai-botkit-for-lead-generation') . esc_html($response_code)
            );
        }

        $content = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Use Readability for HTML content
        if (strpos($content_type, 'text/html') !== false) {
            $readability = new Readability(new Configuration());
            try {
                $readability->parse($content);
                $content = $readability->getContent();
            } catch (ParseException $e) {
                throw new \Exception(
                    esc_html__('Failed to parse HTML document from URL: ', 'ai-botkit-for-lead-generation') . esc_html($e->getMessage())
                );
            }
        }

        $extension = $this->get_extension_from_content_type($content_type);
        
        return [
            'content' => $this->parse_content($content, $extension),
            'metadata' => [
                'source' => $url,
                'document_id' => $document_id,
                'mime_type' => $content_type,
                'extension' => $extension,
                'size' => strlen($content),
                'last_modified' => wp_remote_retrieve_header($response, 'last-modified')
            ]
        ];
    }

    /**
     * Load content from a WordPress post
     * 
     * @param int $post_id Post ID
     * @return array Array containing document content and metadata
     * @throws \Exception If post cannot be loaded
     */
    public function load_from_post(int $post_id, int $document_id): array {
        $post = get_post($post_id);
        if (!$post) {
            throw new \Exception(
                esc_html__('Post not found: ', 'ai-botkit-for-lead-generation') . esc_html($post_id)
            );
        }

        // Get post content
        $content = apply_filters('the_content', $post->post_content);
        $content = wp_strip_all_tags($content);
        $content = apply_filters('ai_botkit_post_content', $content, $post_id);

        return [
            'content' => $content,
            'metadata' => [
                'source' => 'post',
                'post_id' => $post_id,
                'document_id' => $document_id,
                'post_type' => $post->post_type,
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size' => strlen($content),
                'last_modified' => $post->post_modified
            ]
        ];
    }
    
    /**
     * Parse content based on file extension
     * 
     * @param string $content Raw content
     * @param string $extension File extension
     * @return string Parsed content
     */
    private function parse_content(string $content, string $extension): string {
        switch (strtolower($extension)) {
            case 'pdf':
                if (class_exists('\Smalot\PdfParser\Parser')) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseContent($content);
                    return $pdf->getText();
                }
                throw new \Exception(
                    esc_html__('PDF parsing requires the Smalot PDF Parser library', 'ai-botkit-for-lead-generation')
                );
            
            case 'docx':
                // DOCX parsing requires PhpOffice/PhpWord library
                // For now, return a helpful error message
                throw new \Exception(__('DOCX parsing requires additional setup. Please convert to PDF or TXT format, or contact support for DOCX support.', 'ai-botkit-for-lead-generation'));
            
            case 'html':
            case 'htm':
                return wp_strip_all_tags($content);
                
            case 'txt':
            case 'md':
            default:
                return $content;
        }
    }
    
    /**
     * Get file extension from content type
     * 
     * @param string $content_type Content type header
     * @return string File extension
     */
    private function get_extension_from_content_type(string $content_type): string {
        $map = [
            'text/html' => 'html',
            'text/plain' => 'txt',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/json' => 'json',
            'text/markdown' => 'md'
        ];
        
        $parts = explode(';', $content_type);
        $type = trim($parts[0]);
        
        return $map[$type] ?? 'txt';
    }

    /**
     * Clean up temporary files
     */
    public function cleanup_temp_files(): void {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ai-botkit/temp';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            $now = time();
            
            foreach ($files as $file) {
                // Delete files older than 24 hours
                if ($now - filemtime($file) >= 86400) {
                    wp_delete_file($file);
                }
            }
        }
    }
} 