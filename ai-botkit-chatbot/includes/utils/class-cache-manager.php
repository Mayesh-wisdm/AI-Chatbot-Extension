<?php
namespace AI_BotKit\Utils;

/**
 * Cache Manager class for handling caching operations.
 *
 * @package AI_BotKit
 * @subpackage Utils
 * @since 1.0.0
 */

/**
 * Class Cache_Manager
 *
 * Manages caching operations for improved performance.
 *
 * @since 1.0.0
 */
class Cache_Manager {
    /**
     * Cache prefix for all stored items.
     *
     * @since 1.0.0
     * @var string
     */
    private $prefix = 'ai_botkit_';
    
    /**
     * Statistics for cache operations.
     *
     * @since 1.0.0
     * @var array
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    );
    
    /**
     * Get a value from cache.
     *
     * @since 1.0.0
     * @param string $key     The cache key.
     * @param mixed  $default Default value if key not found.
     * @return mixed The cached value or default.
     */
    public function get($key, $default = false) {
        $value = wp_cache_get($this->prefix . $key);
        
        if (false === $value) {
            $this->stats['misses']++;
            return $default;
        }
        
        $this->stats['hits']++;
        return $value;
    }
    
    /**
     * Set a value in cache.
     *
     * @since 1.0.0
     * @param string $key     The cache key.
     * @param mixed  $value   The value to cache.
     * @param int    $expires Expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function set($key, $value, $expires = 0) {
        $this->stats['writes']++;
        return wp_cache_set($this->prefix . $key, $value, '', $expires);
    }
    
    /**
     * Delete a value from cache.
     *
     * @since 1.0.0
     * @param string $key The cache key.
     * @return bool True on success, false on failure.
     */
    public function delete($key) {
        $this->stats['deletes']++;
        return wp_cache_delete($this->prefix . $key);
    }
    
    /**
     * Clear all cached items with plugin prefix.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    public function clear_all() {
        global $wp_object_cache;
        
        if (!is_object($wp_object_cache)) {
            return false;
        }

        $this->stats['deletes']++;
        return wp_cache_flush();
    }
    
    /**
     * Get multiple cache values at once.
     *
     * @since 1.0.0
     * @param array $keys    Array of cache keys.
     * @param mixed $default Default value for missing keys.
     * @return array Array of cached values.
     */
    public function get_many($keys, $default = false) {
        $values = array();
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        
        return $values;
    }
    
    /**
     * Set multiple cache values at once.
     *
     * @since 1.0.0
     * @param array $values  Array of key-value pairs.
     * @param int   $expires Expiration time in seconds.
     * @return bool True if all values were set successfully.
     */
    public function set_many($values, $expires = 0) {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $expires)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete multiple cache values at once.
     *
     * @since 1.0.0
     * @param array $keys Array of cache keys.
     * @return bool True if all values were deleted successfully.
     */
    public function delete_many($keys) {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Check if a key exists in cache.
     *
     * @since 1.0.0
     * @param string $key The cache key.
     * @return bool True if key exists, false otherwise.
     */
    public function has($key) {
        return false !== wp_cache_get($this->prefix . $key);
    }
    
    /**
     * Get cache statistics.
     *
     * @since 1.0.0
     * @return array Array of cache statistics.
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Remember a value in cache.
     *
     * @since 1.0.0
     * @param string   $key     The cache key.
     * @param callable $callback Function to generate value if not found.
     * @param int      $expires Expiration time in seconds.
     * @return mixed The cached or generated value.
     */
    public function remember($key, $callback, $expires = 0) {
        $value = $this->get($key);
        
        if (false !== $value) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $expires);
        
        return $value;
    }
    
    /**
     * Increment a numeric value in cache.
     *
     * @since 1.0.0
     * @param string $key   The cache key.
     * @param int    $value Amount to increment.
     * @return int|bool New value on success, false on failure.
     */
    public function increment($key, $value = 1) {
        return wp_cache_incr($this->prefix . $key, $value);
    }
    
    /**
     * Decrement a numeric value in cache.
     *
     * @since 1.0.0
     * @param string $key   The cache key.
     * @param int    $value Amount to decrement.
     * @return int|bool New value on success, false on failure.
     */
    public function decrement($key, $value = 1) {
        return wp_cache_decr($this->prefix . $key, $value);
    }
} 