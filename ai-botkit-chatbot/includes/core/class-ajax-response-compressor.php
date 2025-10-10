<?php
namespace AI_BotKit\Core;

/**
 * AJAX Response Compressor Class
 * 
 * Compresses AJAX responses to reduce bandwidth usage
 * and improve response times.
 */
class AJAX_Response_Compressor {
    
    /**
     * Compression statistics
     */
    private $stats = [];
    
    /**
     * Minimum size for compression (bytes)
     */
    private $min_compression_size = 1024; // 1KB
    
    /**
     * Initialize the AJAX response compressor
     */
    public function __construct() {
        $this->load_statistics();
    }
    
    /**
     * Load compression statistics
     */
    private function load_statistics() {
        $this->stats = get_option('ai_botkit_ajax_compression_stats', [
            'total_responses' => 0,
            'compressed_responses' => 0,
            'total_original_size' => 0,
            'total_compressed_size' => 0,
            'compression_ratios' => [],
            'last_updated' => null
        ]);
    }
    
    /**
     * Compress AJAX response
     * 
     * @param array $response Response data
     * @return array Compressed response
     */
    public function compress_response($response) {
        $original_size = strlen(json_encode($response));
        
        // Don't compress small responses
        if ($original_size < $this->min_compression_size) {
            $this->update_statistics($original_size, $original_size, false);
            return $response;
        }
        
        // Check if compression is supported
        if (!$this->is_compression_supported()) {
            $this->update_statistics($original_size, $original_size, false);
            return $response;
        }
        
        // Compress response
        $compressed_data = $this->compress_data($response);
        
        if ($compressed_data === false) {
            // Compression failed, return original
            $this->update_statistics($original_size, $original_size, false);
            return $response;
        }
        
        $compressed_size = strlen($compressed_data);
        $this->update_statistics($original_size, $compressed_size, true);
        
        return [
            'compressed' => true,
            'data' => $compressed_data,
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'compression_ratio' => $this->calculate_compression_ratio($original_size, $compressed_size)
        ];
    }
    
    /**
     * Compress data
     * 
     * @param mixed $data Data to compress
     * @return string|false Compressed data or false on failure
     */
    private function compress_data($data) {
        $json_data = json_encode($data);
        
        // Try gzip compression first
        if (function_exists('gzencode')) {
            $compressed = gzencode($json_data, 6); // Compression level 6
            if ($compressed !== false) {
                return base64_encode($compressed);
            }
        }
        
        // Try deflate compression
        if (function_exists('gzdeflate')) {
            $compressed = gzdeflate($json_data, 6);
            if ($compressed !== false) {
                return base64_encode($compressed);
            }
        }
        
        return false;
    }
    
    /**
     * Decompress data
     * 
     * @param string $compressed_data Compressed data
     * @return mixed Decompressed data
     */
    public function decompress_data($compressed_data) {
        $decoded_data = base64_decode($compressed_data);
        
        if ($decoded_data === false) {
            return false;
        }
        
        // Try gzip decompression first
        if (function_exists('gzdecode')) {
            $decompressed = gzdecode($decoded_data);
            if ($decompressed !== false) {
                return json_decode($decompressed, true);
            }
        }
        
        // Try deflate decompression
        if (function_exists('gzinflate')) {
            $decompressed = gzinflate($decoded_data);
            if ($decompressed !== false) {
                return json_decode($decompressed, true);
            }
        }
        
        return false;
    }
    
    /**
     * Check if compression is supported
     * 
     * @return bool True if compression is supported
     */
    private function is_compression_supported() {
        return function_exists('gzencode') || function_exists('gzdeflate');
    }
    
    /**
     * Calculate compression ratio
     * 
     * @param int $original_size Original size
     * @param int $compressed_size Compressed size
     * @return float Compression ratio
     */
    private function calculate_compression_ratio($original_size, $compressed_size) {
        if ($original_size === 0) {
            return 0;
        }
        
        return 1 - ($compressed_size / $original_size);
    }
    
    /**
     * Update compression statistics
     * 
     * @param int $original_size Original size
     * @param int $compressed_size Compressed size
     * @param bool $compressed Whether compression was applied
     */
    private function update_statistics($original_size, $compressed_size, $compressed) {
        $this->stats['total_responses']++;
        $this->stats['total_original_size'] += $original_size;
        $this->stats['total_compressed_size'] += $compressed_size;
        
        if ($compressed) {
            $this->stats['compressed_responses']++;
            $this->stats['compression_ratios'][] = $this->calculate_compression_ratio($original_size, $compressed_size);
            
            // Keep only last 1000 compression ratios
            if (count($this->stats['compression_ratios']) > 1000) {
                $this->stats['compression_ratios'] = array_slice($this->stats['compression_ratios'], -1000);
            }
        }
        
        $this->stats['last_updated'] = current_time('mysql');
        $this->save_statistics();
    }
    
    /**
     * Save compression statistics
     */
    private function save_statistics() {
        update_option('ai_botkit_ajax_compression_stats', $this->stats);
    }
    
    /**
     * Get compression statistics
     * 
     * @return array Compression statistics
     */
    public function get_compression_statistics() {
        $total_responses = $this->stats['total_responses'];
        $compressed_responses = $this->stats['compressed_responses'];
        $total_original_size = $this->stats['total_original_size'];
        $total_compressed_size = $this->stats['total_compressed_size'];
        
        $compression_rate = $total_responses > 0 ? $compressed_responses / $total_responses : 0;
        $average_compression_ratio = !empty($this->stats['compression_ratios']) ? 
            array_sum($this->stats['compression_ratios']) / count($this->stats['compression_ratios']) : 0;
        
        return [
            'total_responses' => $total_responses,
            'compressed_responses' => $compressed_responses,
            'compression_rate' => $compression_rate,
            'total_original_size' => $total_original_size,
            'total_compressed_size' => $total_compressed_size,
            'average_compression_ratio' => $average_compression_ratio,
            'compression_ratios' => $this->stats['compression_ratios'],
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Get compression ratio
     * 
     * @return float Compression ratio
     */
    public function get_compression_ratio() {
        $stats = $this->get_compression_statistics();
        return $stats['average_compression_ratio'];
    }
    
    /**
     * Get compression recommendations
     * 
     * @return array Compression recommendations
     */
    public function get_compression_recommendations() {
        $stats = $this->get_compression_statistics();
        $recommendations = [];
        
        // Compression rate recommendations
        if ($stats['compression_rate'] < 0.5) {
            $recommendations[] = [
                'type' => 'compression_rate',
                'message' => 'Low compression rate (' . round($stats['compression_rate'] * 100, 1) . '%). Consider lowering minimum compression size.',
                'action' => 'lower_min_compression_size'
            ];
        }
        
        // Compression ratio recommendations
        if ($stats['average_compression_ratio'] < 0.3) {
            $recommendations[] = [
                'type' => 'compression_ratio',
                'message' => 'Low compression ratio (' . round($stats['average_compression_ratio'] * 100, 1) . '%). Consider optimizing response data.',
                'action' => 'optimize_response_data'
            ];
        }
        
        // Compression support recommendations
        if (!$this->is_compression_supported()) {
            $recommendations[] = [
                'type' => 'compression_support',
                'message' => 'Compression is not supported on this server. Consider enabling gzip or deflate.',
                'action' => 'enable_compression_support'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get compression status
     * 
     * @return array Compression status
     */
    public function get_compression_status() {
        return [
            'compression_enabled' => true,
            'compression_supported' => $this->is_compression_supported(),
            'min_compression_size' => $this->min_compression_size,
            'compression_rate' => $this->get_compression_statistics()['compression_rate'],
            'average_compression_ratio' => $this->get_compression_ratio(),
            'last_updated' => $this->stats['last_updated']
        ];
    }
    
    /**
     * Clear compression statistics
     */
    public function clear_compression_statistics() {
        $this->stats = [
            'total_responses' => 0,
            'compressed_responses' => 0,
            'total_original_size' => 0,
            'total_compressed_size' => 0,
            'compression_ratios' => [],
            'last_updated' => null
        ];
        
        $this->save_statistics();
    }
}
