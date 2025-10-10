<?php
namespace AI_BotKit\Core;

/**
 * AJAX Request Optimizer Class
 * 
 * Optimizes AJAX requests by filtering parameters, validating data,
 * and preparing requests for optimal processing.
 */
class AJAX_Request_Optimizer {
    
    /**
     * Essential parameters that should be kept
     */
    private $essential_params = [
        'type',
        'page',
        'search',
        'id',
        'action',
        'nonce'
    ];
    
    /**
     * Parameters to exclude from optimization
     */
    private $excluded_params = [
        'unnecessary_param',
        'debug',
        'test',
        'temp'
    ];
    
    /**
     * Initialize the AJAX request optimizer
     */
    public function __construct() {
        // Allow customization of essential and excluded parameters
        $this->essential_params = apply_filters('ai_botkit_ajax_essential_params', $this->essential_params);
        $this->excluded_params = apply_filters('ai_botkit_ajax_excluded_params', $this->excluded_params);
    }
    
    /**
     * Optimize request parameters
     * 
     * @param array $params Request parameters
     * @return array Optimized parameters
     */
    public function optimize_request_params($params) {
        $optimized = [];
        
        foreach ($params as $key => $value) {
            // Skip excluded parameters
            if (in_array($key, $this->excluded_params)) {
                continue;
            }
            
            // Keep essential parameters
            if (in_array($key, $this->essential_params)) {
                $optimized[$key] = $this->sanitize_parameter($key, $value);
                continue;
            }
            
            // Keep other parameters if they have meaningful values
            if ($this->is_meaningful_parameter($key, $value)) {
                $optimized[$key] = $this->sanitize_parameter($key, $value);
            }
        }
        
        return $optimized;
    }
    
    /**
     * Sanitize parameter value
     * 
     * @param string $key Parameter key
     * @param mixed $value Parameter value
     * @return mixed Sanitized value
     */
    private function sanitize_parameter($key, $value) {
        switch ($key) {
            case 'nonce':
                return sanitize_text_field($value);
                
            case 'type':
            case 'page':
            case 'id':
                return absint($value);
                
            case 'search':
                return sanitize_text_field($value);
                
            case 'action':
                return sanitize_text_field($value);
                
            default:
                if (is_string($value)) {
                    return sanitize_text_field($value);
                } elseif (is_numeric($value)) {
                    return is_float($value) ? floatval($value) : intval($value);
                } elseif (is_bool($value)) {
                    return (bool) $value;
                } elseif (is_array($value)) {
                    return array_map([$this, 'sanitize_parameter'], array_keys($value), $value);
                } else {
                    return $value;
                }
        }
    }
    
    /**
     * Check if parameter is meaningful
     * 
     * @param string $key Parameter key
     * @param mixed $value Parameter value
     * @return bool True if meaningful
     */
    private function is_meaningful_parameter($key, $value) {
        // Skip empty values
        if (empty($value) && $value !== 0 && $value !== '0') {
            return false;
        }
        
        // Skip default values
        if ($this->is_default_value($key, $value)) {
            return false;
        }
        
        // Skip very long strings (potential security risk)
        if (is_string($value) && strlen($value) > 1000) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if value is a default value
     * 
     * @param string $key Parameter key
     * @param mixed $value Parameter value
     * @return bool True if default value
     */
    private function is_default_value($key, $value) {
        $defaults = [
            'page' => 1,
            'per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => 'publish'
        ];
        
        return isset($defaults[$key]) && $defaults[$key] === $value;
    }
    
    /**
     * Validate request parameters
     * 
     * @param array $params Request parameters
     * @return array Validation result
     */
    public function validate_request_params($params) {
        $errors = [];
        $warnings = [];
        
        // Validate nonce
        if (isset($params['nonce']) && !wp_verify_nonce($params['nonce'], 'ai_botkit_nonce')) {
            $errors[] = 'Invalid nonce';
        }
        
        // Validate page parameter
        if (isset($params['page']) && (!is_numeric($params['page']) || $params['page'] < 1)) {
            $errors[] = 'Invalid page parameter';
        }
        
        // Validate type parameter
        if (isset($params['type']) && !$this->is_valid_type($params['type'])) {
            $errors[] = 'Invalid type parameter';
        }
        
        // Validate search parameter
        if (isset($params['search']) && strlen($params['search']) > 100) {
            $warnings[] = 'Search parameter is too long';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Check if type is valid
     * 
     * @param string $type Type parameter
     * @return bool True if valid
     */
    private function is_valid_type($type) {
        $valid_types = [
            'all',
            'post',
            'page',
            'file',
            'url',
            'document'
        ];
        
        return in_array($type, $valid_types);
    }
    
    /**
     * Get request optimization statistics
     * 
     * @return array Optimization statistics
     */
    public function get_optimization_statistics() {
        return [
            'essential_params' => $this->essential_params,
            'excluded_params' => $this->excluded_params,
            'optimization_enabled' => true,
            'validation_enabled' => true,
            'sanitization_enabled' => true
        ];
    }
    
    /**
     * Get request optimization recommendations
     * 
     * @return array Optimization recommendations
     */
    public function get_optimization_recommendations() {
        $recommendations = [];
        
        // Check if essential parameters are properly configured
        if (count($this->essential_params) < 3) {
            $recommendations[] = [
                'type' => 'essential_params',
                'message' => 'Consider adding more essential parameters for better optimization.',
                'action' => 'review_essential_params'
            ];
        }
        
        // Check if excluded parameters are properly configured
        if (count($this->excluded_params) < 2) {
            $recommendations[] = [
                'type' => 'excluded_params',
                'message' => 'Consider adding more excluded parameters for better optimization.',
                'action' => 'review_excluded_params'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get request optimization status
     * 
     * @return array Optimization status
     */
    public function get_optimization_status() {
        return [
            'parameter_optimization' => true,
            'parameter_validation' => true,
            'parameter_sanitization' => true,
            'essential_params_count' => count($this->essential_params),
            'excluded_params_count' => count($this->excluded_params),
            'last_optimization' => current_time('mysql')
        ];
    }
}
