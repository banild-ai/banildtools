<?php
/**
 * Server Operations Handler for BanildTools
 * 
 * @package BanildTools
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BanildTools_Server_Ops {
    
    /**
     * Get plugin status
     */
    public function status() {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'version' => BANILDTOOLS_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'write_enabled' => get_option('banildtools_enable_write', '1') === '1',
            'delete_enabled' => get_option('banildtools_enable_delete', '1') === '1',
            'db_enabled' => get_option('banildtools_enable_db', '1') === '1',
            'allowed_paths' => array_filter(explode("\n", get_option('banildtools_allowed_paths', ABSPATH))),
            'abspath' => ABSPATH,
        ), 200);
    }
    
    /**
     * Get server information
     */
    public function server_info() {
        global $wpdb;
        
        // Disk space
        $disk_free = @disk_free_space(ABSPATH);
        $disk_total = @disk_total_space(ABSPATH);
        
        // Memory
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        // Database
        $db_size = 0;
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        if ($tables) {
            foreach ($tables as $table) {
                $db_size += $table['Data_length'] + $table['Index_length'];
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'server' => array(
                'software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
                'hostname' => gethostname(),
                'os' => PHP_OS,
                'architecture' => php_uname('m'),
            ),
            'php' => array(
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'memory_limit' => $memory_limit,
                'memory_usage' => $this->format_bytes($memory_usage),
                'memory_peak' => $this->format_bytes($memory_peak),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_time' => ini_get('max_input_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_input_vars' => ini_get('max_input_vars'),
                'extensions' => get_loaded_extensions(),
            ),
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug' => WP_DEBUG,
                'debug_log' => WP_DEBUG_LOG,
                'debug_display' => WP_DEBUG_DISPLAY,
                'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
                'memory_limit' => WP_MEMORY_LIMIT,
                'max_memory_limit' => WP_MAX_MEMORY_LIMIT,
            ),
            'database' => array(
                'type' => 'MySQL',
                'version' => $wpdb->db_version(),
                'prefix' => $wpdb->prefix,
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
                'tables_count' => count($tables),
                'size' => $this->format_bytes($db_size),
            ),
            'disk' => array(
                'free' => $disk_free ? $this->format_bytes($disk_free) : 'Unknown',
                'total' => $disk_total ? $this->format_bytes($disk_total) : 'Unknown',
                'used_percent' => ($disk_total && $disk_free) ? round((($disk_total - $disk_free) / $disk_total) * 100, 2) . '%' : 'Unknown',
            ),
            'paths' => array(
                'abspath' => ABSPATH,
                'wp_content' => WP_CONTENT_DIR,
                'plugins' => WP_PLUGIN_DIR,
                'themes' => get_theme_root(),
                'uploads' => wp_upload_dir()['basedir'],
            ),
        ), 200);
    }
    
    /**
     * Execute database query (SELECT only)
     */
    public function db_query($query, $limit = 100) {
        global $wpdb;
        
        // Security: Only allow SELECT queries
        $query = trim($query);
        $query_lower = strtolower($query);
        
        // Check if it's a SELECT query
        if (strpos($query_lower, 'select') !== 0) {
            return new WP_Error(
                'invalid_query',
                'Only SELECT queries are allowed for security reasons.',
                array('status' => 403)
            );
        }
        
        // Block dangerous keywords
        $dangerous_keywords = array('insert', 'update', 'delete', 'drop', 'truncate', 'alter', 'create', 'grant', 'revoke', 'into outfile', 'load_file');
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                return new WP_Error(
                    'forbidden_query',
                    "Query contains forbidden keyword: {$keyword}",
                    array('status' => 403)
                );
            }
        }
        
        // Add LIMIT if not present
        if (strpos($query_lower, 'limit') === false) {
            $query .= " LIMIT {$limit}";
        }
        
        // Execute query
        $start_time = microtime(true);
        $results = $wpdb->get_results($query, ARRAY_A);
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if ($results === null && $wpdb->last_error) {
            return new WP_Error(
                'query_error',
                $wpdb->last_error,
                array('status' => 400)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'query' => $query,
            'rows_returned' => count($results),
            'execution_time_ms' => $execution_time,
            'results' => $results,
        ), 200);
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

