<?php
/**
 * WordPress Operations Handler for BanildTools
 * 
 * @package BanildTools
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BanildTools_WordPress_Ops {
    
    /**
     * Handle WordPress options
     */
    public function handle_option($action, $name, $value = null) {
        switch ($action) {
            case 'get':
                $option_value = get_option($name);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'value' => $option_value,
                    'exists' => $option_value !== false,
                ), 200);
                
            case 'set':
                if ($value === null) {
                    return new WP_Error('missing_value', 'Value is required for set action.', array('status' => 400));
                }
                $result = update_option($name, $value);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'value' => $value,
                    'updated' => $result,
                ), 200);
                
            case 'delete':
                $result = delete_option($name);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'deleted' => $result,
                ), 200);
                
            default:
                return new WP_Error('invalid_action', 'Invalid action. Use: get, set, delete', array('status' => 400));
        }
    }
    
    /**
     * Handle WordPress transients
     */
    public function handle_transient($action, $name, $value = null, $expiration = 3600) {
        switch ($action) {
            case 'get':
                $transient_value = get_transient($name);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'value' => $transient_value,
                    'exists' => $transient_value !== false,
                ), 200);
                
            case 'set':
                if ($value === null) {
                    return new WP_Error('missing_value', 'Value is required for set action.', array('status' => 400));
                }
                $result = set_transient($name, $value, $expiration);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'value' => $value,
                    'expiration' => $expiration,
                    'set' => $result,
                ), 200);
                
            case 'delete':
                $result = delete_transient($name);
                return new WP_REST_Response(array(
                    'success' => true,
                    'name' => $name,
                    'deleted' => $result,
                ), 200);
                
            default:
                return new WP_Error('invalid_action', 'Invalid action. Use: get, set, delete', array('status' => 400));
        }
    }
    
    /**
     * Handle debug.log operations
     */
    public function handle_debug_log($action = 'read', $lines = 100) {
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        
        switch ($action) {
            case 'read':
            case 'tail':
                if (!file_exists($debug_log_path)) {
                    return new WP_REST_Response(array(
                        'success' => true,
                        'exists' => false,
                        'contents' => '',
                        'message' => 'Debug log does not exist.',
                    ), 200);
                }
                
                if ($action === 'tail' || $lines > 0) {
                    // Read last N lines
                    $file_lines = file($debug_log_path, FILE_IGNORE_NEW_LINES);
                    if ($file_lines === false) {
                        return new WP_Error('read_failed', 'Failed to read debug log.', array('status' => 500));
                    }
                    $total_lines = count($file_lines);
                    $file_lines = array_slice($file_lines, -$lines);
                    $contents = implode("\n", $file_lines);
                } else {
                    $contents = file_get_contents($debug_log_path);
                    $total_lines = substr_count($contents, "\n") + 1;
                }
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'exists' => true,
                    'path' => $debug_log_path,
                    'size' => filesize($debug_log_path),
                    'total_lines' => $total_lines,
                    'lines_returned' => min($lines, $total_lines),
                    'contents' => $contents,
                ), 200);
                
            case 'clear':
                if (file_exists($debug_log_path)) {
                    $result = file_put_contents($debug_log_path, '');
                    if ($result === false) {
                        return new WP_Error('clear_failed', 'Failed to clear debug log.', array('status' => 500));
                    }
                }
                return new WP_REST_Response(array(
                    'success' => true,
                    'cleared' => true,
                    'message' => 'Debug log cleared.',
                ), 200);
                
            default:
                return new WP_Error('invalid_action', 'Invalid action. Use: read, tail, clear', array('status' => 400));
        }
    }
    
    /**
     * PHP syntax check
     */
    public function php_lint($file_path = null, $code = null) {
        if ($file_path) {
            // Resolve path
            $path = str_replace('\\', '/', $file_path);
            if (strpos($path, '/') !== 0 && !preg_match('/^[A-Za-z]:[\\/]/', $path)) {
                $path = ABSPATH . ltrim($path, '/');
            }
            
            if (!file_exists($path)) {
                return new WP_Error('file_not_found', 'File not found.', array('status' => 404));
            }
            
            $code = file_get_contents($path);
        }
        
        if (!$code) {
            return new WP_Error('missing_input', 'Provide file_path or code.', array('status' => 400));
        }
        
        // Create temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'php_lint_');
        file_put_contents($temp_file, $code);
        
        // Run php -l
        $output = array();
        $return_var = 0;
        exec('php -l ' . escapeshellarg($temp_file) . ' 2>&1', $output, $return_var);
        
        unlink($temp_file);
        
        $output_text = implode("\n", $output);
        $has_errors = $return_var !== 0;
        
        // Parse errors
        $errors = array();
        if ($has_errors) {
            preg_match_all('/Parse error:.*on line (\d+)/', $output_text, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $i => $match) {
                    $errors[] = array(
                        'message' => $match,
                        'line' => isset($matches[1][$i]) ? (int)$matches[1][$i] : null,
                    );
                }
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'valid' => !$has_errors,
            'errors' => $errors,
            'output' => $output_text,
            'file' => $file_path,
        ), 200);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache($type = 'all') {
        $cleared = array();
        
        if ($type === 'all' || $type === 'object') {
            wp_cache_flush();
            $cleared[] = 'object_cache';
        }
        
        if ($type === 'all' || $type === 'transients') {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
            $cleared[] = 'transients';
        }
        
        if ($type === 'all' || $type === 'rewrite') {
            flush_rewrite_rules();
            $cleared[] = 'rewrite_rules';
        }
        
        // Try to clear popular cache plugins
        if ($type === 'all' || $type === 'page') {
            // WP Super Cache
            if (function_exists('wp_cache_clear_cache')) {
                wp_cache_clear_cache();
                $cleared[] = 'wp_super_cache';
            }
            
            // W3 Total Cache
            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
                $cleared[] = 'w3_total_cache';
            }
            
            // WP Fastest Cache
            if (function_exists('wpfc_clear_all_cache')) {
                wpfc_clear_all_cache();
                $cleared[] = 'wp_fastest_cache';
            }
            
            // LiteSpeed Cache
            if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
                LiteSpeed_Cache_API::purge_all();
                $cleared[] = 'litespeed_cache';
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'type' => $type,
            'cleared' => $cleared,
            'message' => 'Cache cleared successfully.',
        ), 200);
    }
}

