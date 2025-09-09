<?php
namespace AI_BotKit\Core;

/**
 * Text Chunker class for splitting documents into chunks
 * 
 * Features:
 * - Intelligent text splitting
 * - Context preservation
 * - Overlap handling
 * - Metadata tracking
 * - Size optimization
 * - UTF-8 character boundary awareness
 */
class Text_Chunker {
    /**
     * Default chunk size in characters
     */
    private $chunk_size;

    /**
     * Overlap size between chunks
     */
    private $chunk_overlap;

    /**
     * Minimum chunk size to prevent tiny chunks
     */
    private $min_chunk_size;

    /**
     * Splitting strategies
     */
    private const SPLIT_PATTERNS = [
        'paragraph' => '/\n\s*\n/',
        'sentence' => '/(?<=[.!?])\s+/',
        'heading' => '/\n#{1,6}\s+|\n[A-Z][^\n]+\n(?:[-=]{2,})\n/',
    ];

    /**
     * Initialize the chunker
     * 
     * @param int $chunk_size Maximum chunk size in characters
     * @param int $chunk_overlap Number of characters to overlap between chunks
     * @param int $min_chunk_size Minimum chunk size in characters
     */
    public function __construct(int $chunk_size = 1000, int $chunk_overlap = 200, int $min_chunk_size = 700) {
        $this->chunk_size = $chunk_size;
        $this->chunk_overlap = min($chunk_overlap, $chunk_size / 2);
        $this->min_chunk_size = $min_chunk_size;
    }

    /**
     * Split text into chunks
     * 
     * @param string $text Text to split
     * @param array $metadata Optional metadata to track
     * @return array Array of chunks with metadata
     */
    public function split_text(string $text, array $metadata = []): array {
        // Clean and normalize text
        $text = $this->normalize_text($text);

        // First try paragraph-based splitting
        $chunks = $this->split_by_pattern($text, self::SPLIT_PATTERNS['paragraph']);

        // If chunks are too large, split by sentences
        $chunks = $this->optimize_chunks($chunks, self::SPLIT_PATTERNS['sentence']);

        // Merge small chunks and add overlaps
        $chunks = $this->merge_small_chunks($chunks);
        return $this->process_chunks($chunks, $metadata);
    }

    /**
     * Split text by pattern
     * 
     * @param string $text Text to split
     * @param string $pattern Regular expression pattern
     * @return array Array of text chunks
     */
    private function split_by_pattern(string $text, string $pattern): array {
        $chunks = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('trim', $chunks);
    }

    /**
     * Optimize chunks to meet size requirements
     * 
     * @param array $chunks Array of text chunks
     * @param string $pattern Pattern to use for further splitting
     * @return array Optimized chunks
     */
    private function optimize_chunks(array $chunks, string $pattern): array {
        $optimized = [];

        foreach ($chunks as $chunk) {
            if ($this->mb_strlen($chunk) <= $this->chunk_size) {
                $optimized[] = $chunk;
                continue;
            }

            // Split large chunks
            $sub_chunks = $this->split_by_pattern($chunk, $pattern);
            $current_chunk = '';

            foreach ($sub_chunks as $sub_chunk) {
                $current_length = $this->mb_strlen($current_chunk);
                $sub_length = $this->mb_strlen($sub_chunk);
                
                if ($current_length + $sub_length <= $this->chunk_size) {
                    $current_chunk .= ($current_chunk ? ' ' : '') . $sub_chunk;
                } else {
                    if ($current_chunk) {
                        $optimized[] = $current_chunk;
                    }
                    $current_chunk = $sub_chunk;
                }
            }

            if ($current_chunk) {
                $optimized[] = $current_chunk;
            }
        }

        return $optimized;
    }

    /**
     * Merge small chunks to meet minimum size requirements
     * 
     * @param array $chunks Array of text chunks
     * @return array Merged chunks
     */
    private function merge_small_chunks(array $chunks): array {
        if (empty($chunks)) {
            return $chunks;
        }

        $merged = [];
        $current_chunk = '';

        foreach ($chunks as $index => $chunk) {
            $current_length = $this->mb_strlen($current_chunk);
            $chunk_length = $this->mb_strlen($chunk);
            $is_last_chunk = ($index === count($chunks) - 1);

            // If current chunk is already large enough, add it to results
            if ($current_length >= $this->min_chunk_size) {
                $merged[] = $current_chunk;
                $current_chunk = $chunk;
                continue;
            }

            // Try to merge with current chunk
            if ($current_length + $chunk_length <= $this->chunk_size) {
                $current_chunk .= ($current_chunk ? ' ' : '') . $chunk;
            } else {
                // Current chunk is too small but can't merge more
                if ($current_chunk) {
                    $merged[] = $current_chunk;
                }
                $current_chunk = $chunk;
            }
        }

        // Handle the last chunk - if it's too small, try to merge with previous
        if ($current_chunk) {
            $last_chunk_size = $this->mb_strlen($current_chunk);
            
            if ($last_chunk_size < $this->min_chunk_size && !empty($merged)) {
                // Last chunk is too small, try to merge with the previous chunk
                $previous_chunk = array_pop($merged);
                $previous_size = $this->mb_strlen($previous_chunk);
                $combined_size = $previous_size + $last_chunk_size;
                
                // Only merge if the combined size doesn't exceed chunk_size (allow some flexibility)
                if ($combined_size <= $this->chunk_size ) {
                    $merged[] = $previous_chunk . ' ' . $current_chunk;
                } else {
                    // Can't merge, add both chunks back
                    $merged[] = $previous_chunk;
                    $merged[] = $current_chunk;
                }
            } else {
                // Last chunk is fine, add it
                $merged[] = $current_chunk;
            }
        }

        return $merged;
    }

    /**
     * Process chunks with overlaps and metadata
     * 
     * @param array $chunks Array of text chunks
     * @param array $metadata Metadata to include
     * @return array Processed chunks with metadata
     */
    private function process_chunks(array $chunks, array $metadata): array {
        $processed = [];
        $total_chunks = count($chunks);

        for ($i = 0; $i < $total_chunks; $i++) {
            $chunk_text = $chunks[$i];
            $final_chunk = $chunk_text;

            // Calculate overlaps before adding them
            $prev_overlap = '';
            $next_overlap = '';

            // Add overlap from previous chunk
            if ($i > 0) {
                $prev_text = $chunks[$i - 1];
                $overlap_size = min($this->chunk_overlap, $this->mb_strlen($prev_text));
                if ($overlap_size > 0) {
                    $prev_overlap = $this->mb_substr($prev_text, -$overlap_size);
                }
            }

            // Add overlap from next chunk
            if ($i < $total_chunks - 1) {
                $next_text = $chunks[$i + 1];
                $overlap_size = min($this->chunk_overlap, $this->mb_strlen($next_text));
                if ($overlap_size > 0) {
                    $next_overlap = $this->mb_substr($next_text, 0, $overlap_size);
                }
            }

            // Build final chunk with overlaps
            $final_chunk = '';
            if ($prev_overlap) {
                $final_chunk .= $prev_overlap . "\n";
            }
            $final_chunk .= $chunk_text;
            if ($next_overlap) {
                $final_chunk .= "\n" . $next_overlap;
            }

            // Validate final chunk size
            $final_size = $this->mb_strlen($final_chunk);
            if ($final_size > $this->chunk_size * 1.5) {
                // If chunk is too large, remove overlaps and split further
                $final_chunk = $chunk_text;
            }

            $processed[] = [
                'content' => $final_chunk,
                'metadata' => array_merge($metadata, [
                    'chunk_index' => $i,
                    'total_chunks' => $total_chunks,
                    'has_previous' => $i > 0,
                    'has_next' => $i < $total_chunks - 1,
                    'size' => $this->mb_strlen($final_chunk),
                    'original_size' => $this->mb_strlen($chunk_text),
                    'has_overlap_prev' => !empty($prev_overlap),
                    'has_overlap_next' => !empty($next_overlap),
                ])
            ];
        }

        return $processed;
    }

    /**
     * Normalize text for consistent processing
     * 
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalize_text(string $text): string {
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8');
        }

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim whitespace
        return trim($text);
    }

    /**
     * Multi-byte safe string length
     * 
     * @param string $string String to measure
     * @return int Character length
     */
    private function mb_strlen(string $string): int {
        return mb_strlen($string, 'UTF-8');
    }

    /**
     * Multi-byte safe substring
     * 
     * @param string $string String to extract from
     * @param int $start Start position
     * @param int|null $length Length to extract
     * @return string Substring
     */
    private function mb_substr(string $string, int $start, ?int $length = null): string {
        if ($length !== null) {
            return mb_substr($string, $start, $length, 'UTF-8');
        }
        return mb_substr($string, $start, null, 'UTF-8');
    }

    /**
     * Get current settings
     * 
     * @return array Current chunker settings
     */
    public function get_settings(): array {
        return [
            'chunk_size' => $this->chunk_size,
            'chunk_overlap' => $this->chunk_overlap,
            'min_chunk_size' => $this->min_chunk_size,
        ];
    }
} 