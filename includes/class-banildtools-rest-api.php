<?php
/**
 * REST API Handler for BanildTools
 * 
 * @package BanildTools
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BanildTools_REST_API {
    
    const NAMESPACE = 'banildtools/v1';
    
    private $file_ops;
    private $search;
    private $wp_ops;
    private $server_ops;
    
    public function __construct() {
        $this->file_ops = new BanildTools_File_Operations();
        $this->search = new BanildTools_Search();
        $this->wp_ops = new BanildTools_WordPress_Ops();
        $this->server_ops = new BanildTools_Server_Ops();
    }
    
    public function register_routes() {
        // ========== FILE OPERATIONS ==========
        
        // Read file
        register_rest_route(self::NAMESPACE, '/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'read_file'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'target_file' => array('required' => true, 'type' => 'string'),
                'offset' => array('required' => false, 'type' => 'integer'),
                'limit' => array('required' => false, 'type' => 'integer'),
            ),
        ));
        
        // Write file
        register_rest_route(self::NAMESPACE, '/write', array(
            'methods' => 'POST',
            'callback' => array($this, 'write_file'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'file_path' => array('required' => true, 'type' => 'string'),
                'contents' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // Append to file (NEW)
        register_rest_route(self::NAMESPACE, '/append', array(
            'methods' => 'POST',
            'callback' => array($this, 'append_file'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'file_path' => array('required' => true, 'type' => 'string'),
                'contents' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // Edit file (search and replace)
        register_rest_route(self::NAMESPACE, '/edit', array(
            'methods' => 'POST',
            'callback' => array($this, 'edit_file'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'file_path' => array('required' => true, 'type' => 'string'),
                'old_string' => array('required' => true, 'type' => 'string'),
                'new_string' => array('required' => true, 'type' => 'string'),
                'replace_all' => array('required' => false, 'type' => 'boolean', 'default' => false),
            ),
        ));
        
        // Delete file
        register_rest_route(self::NAMESPACE, '/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_file'),
            'permission_callback' => array($this, 'check_delete_permission'),
            'args' => array(
                'target_file' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // List directory
        register_rest_route(self::NAMESPACE, '/list', array(
            'methods' => 'POST',
            'callback' => array($this, 'list_directory'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'target_directory' => array('required' => true, 'type' => 'string'),
                'ignore_globs' => array('required' => false, 'type' => 'array'),
            ),
        ));
        
        // Check if file exists (NEW)
        register_rest_route(self::NAMESPACE, '/exists', array(
            'methods' => 'POST',
            'callback' => array($this, 'file_exists'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'path' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // File info
        register_rest_route(self::NAMESPACE, '/info', array(
            'methods' => 'POST',
            'callback' => array($this, 'file_info'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'path' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // Create directory
        register_rest_route(self::NAMESPACE, '/mkdir', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_directory'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'path' => array('required' => true, 'type' => 'string'),
                'recursive' => array('required' => false, 'type' => 'boolean', 'default' => true),
            ),
        ));
        
        // Rename/move
        register_rest_route(self::NAMESPACE, '/rename', array(
            'methods' => 'POST',
            'callback' => array($this, 'rename_file'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'source' => array('required' => true, 'type' => 'string'),
                'destination' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // Copy
        register_rest_route(self::NAMESPACE, '/copy', array(
            'methods' => 'POST',
            'callback' => array($this, 'copy_file'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'source' => array('required' => true, 'type' => 'string'),
                'destination' => array('required' => true, 'type' => 'string'),
            ),
        ));
        
        // ========== SEARCH OPERATIONS ==========
        
        // Search files (grep-like)
        register_rest_route(self::NAMESPACE, '/search', array(
            'methods' => 'POST',
            'callback' => array($this, 'search_files'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'pattern' => array('required' => true, 'type' => 'string'),
                'path' => array('required' => false, 'type' => 'string'),
                'glob' => array('required' => false, 'type' => 'string'),
                'case_insensitive' => array('required' => false, 'type' => 'boolean', 'default' => false),
                'context_lines' => array('required' => false, 'type' => 'integer', 'default' => 0),
                'output_mode' => array('required' => false, 'type' => 'string', 'default' => 'content'),
                'max_results' => array('required' => false, 'type' => 'integer', 'default' => 500),
            ),
        ));
        
        // Glob file search
        register_rest_route(self::NAMESPACE, '/glob', array(
            'methods' => 'POST',
            'callback' => array($this, 'glob_search'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'glob_pattern' => array('required' => true, 'type' => 'string'),
                'target_directory' => array('required' => false, 'type' => 'string'),
            ),
        ));
        
        // ========== WORDPRESS OPERATIONS ==========
        
        // Get/Set WordPress option (NEW)
        register_rest_route(self::NAMESPACE, '/option', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_option'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'action' => array('required' => true, 'type' => 'string', 'enum' => array('get', 'set', 'delete')),
                'name' => array('required' => true, 'type' => 'string'),
                'value' => array('required' => false),
            ),
        ));
        
        // Get/Set/Delete transient (NEW)
        register_rest_route(self::NAMESPACE, '/transient', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_transient'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'action' => array('required' => true, 'type' => 'string', 'enum' => array('get', 'set', 'delete')),
                'name' => array('required' => true, 'type' => 'string'),
                'value' => array('required' => false),
                'expiration' => array('required' => false, 'type' => 'integer', 'default' => 3600),
            ),
        ));
        
        // Debug log (NEW)
        register_rest_route(self::NAMESPACE, '/debug-log', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_debug_log'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'action' => array('required' => false, 'type' => 'string', 'default' => 'read', 'enum' => array('read', 'clear', 'tail')),
                'lines' => array('required' => false, 'type' => 'integer', 'default' => 100),
            ),
        ));
        
        // PHP Lint (NEW)
        register_rest_route(self::NAMESPACE, '/php-lint', array(
            'methods' => 'POST',
            'callback' => array($this, 'php_lint'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'file_path' => array('required' => false, 'type' => 'string'),
                'code' => array('required' => false, 'type' => 'string'),
            ),
        ));
        
        // Clear cache (NEW)
        register_rest_route(self::NAMESPACE, '/clear-cache', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_cache'),
            'permission_callback' => array($this, 'check_write_permission'),
            'args' => array(
                'type' => array('required' => false, 'type' => 'string', 'default' => 'all'),
            ),
        ));
        
        // ========== SERVER OPERATIONS ==========
        
        // Status/health check
        register_rest_route(self::NAMESPACE, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'status'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Server info (NEW)
        register_rest_route(self::NAMESPACE, '/server-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'server_info'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Database query (NEW - read only)
        register_rest_route(self::NAMESPACE, '/db-query', array(
            'methods' => 'POST',
            'callback' => array($this, 'db_query'),
            'permission_callback' => array($this, 'check_db_permission'),
            'args' => array(
                'query' => array('required' => true, 'type' => 'string'),
                'limit' => array('required' => false, 'type' => 'integer', 'default' => 100),
            ),
        ));
    }
    
    // ========== PERMISSION CALLBACKS ==========
    
    public function check_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', 'Authentication required.', array('status' => 401));
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', 'Insufficient permissions.', array('status' => 403));
        }
        return true;
    }
    
    public function check_write_permission($request) {
        $base_check = $this->check_permission($request);
        if (is_wp_error($base_check)) return $base_check;
        
        if (get_option('banildtools_enable_write', '1') !== '1') {
            return new WP_Error('rest_forbidden', 'Write operations disabled.', array('status' => 403));
        }
        return true;
    }
    
    public function check_delete_permission($request) {
        $base_check = $this->check_permission($request);
        if (is_wp_error($base_check)) return $base_check;
        
        if (get_option('banildtools_enable_delete', '1') !== '1') {
            return new WP_Error('rest_forbidden', 'Delete operations disabled.', array('status' => 403));
        }
        return true;
    }
    
    public function check_db_permission($request) {
        $base_check = $this->check_permission($request);
        if (is_wp_error($base_check)) return $base_check;
        
        if (get_option('banildtools_enable_db', '1') !== '1') {
            return new WP_Error('rest_forbidden', 'Database operations disabled.', array('status' => 403));
        }
        return true;
    }
    
    // ========== FILE OPERATION CALLBACKS ==========
    
    public function read_file($request) {
        return $this->file_ops->read_file(
            $request->get_param('target_file'),
            $request->get_param('offset'),
            $request->get_param('limit')
        );
    }
    
    public function write_file($request) {
        return $this->file_ops->write_file(
            $request->get_param('file_path'),
            $request->get_param('contents')
        );
    }
    
    public function append_file($request) {
        return $this->file_ops->append_file(
            $request->get_param('file_path'),
            $request->get_param('contents')
        );
    }
    
    public function edit_file($request) {
        return $this->file_ops->edit_file(
            $request->get_param('file_path'),
            $request->get_param('old_string'),
            $request->get_param('new_string'),
            $request->get_param('replace_all')
        );
    }
    
    public function delete_file($request) {
        return $this->file_ops->delete_file($request->get_param('target_file'));
    }
    
    public function list_directory($request) {
        return $this->file_ops->list_directory(
            $request->get_param('target_directory'),
            $request->get_param('ignore_globs') ?: array()
        );
    }
    
    public function file_exists($request) {
        return $this->file_ops->file_exists($request->get_param('path'));
    }
    
    public function file_info($request) {
        return $this->file_ops->file_info($request->get_param('path'));
    }
    
    public function create_directory($request) {
        return $this->file_ops->create_directory(
            $request->get_param('path'),
            $request->get_param('recursive')
        );
    }
    
    public function rename_file($request) {
        return $this->file_ops->rename_file(
            $request->get_param('source'),
            $request->get_param('destination')
        );
    }
    
    public function copy_file($request) {
        return $this->file_ops->copy_file(
            $request->get_param('source'),
            $request->get_param('destination')
        );
    }
    
    // ========== SEARCH CALLBACKS ==========
    
    public function search_files($request) {
        return $this->search->grep(
            $request->get_param('pattern'),
            $request->get_param('path') ?: ABSPATH,
            $request->get_param('glob'),
            $request->get_param('case_insensitive'),
            $request->get_param('context_lines'),
            $request->get_param('output_mode'),
            $request->get_param('max_results')
        );
    }
    
    public function glob_search($request) {
        return $this->search->glob_search(
            $request->get_param('glob_pattern'),
            $request->get_param('target_directory') ?: ABSPATH
        );
    }
    
    // ========== WORDPRESS OPERATION CALLBACKS ==========
    
    public function handle_option($request) {
        return $this->wp_ops->handle_option(
            $request->get_param('action'),
            $request->get_param('name'),
            $request->get_param('value')
        );
    }
    
    public function handle_transient($request) {
        return $this->wp_ops->handle_transient(
            $request->get_param('action'),
            $request->get_param('name'),
            $request->get_param('value'),
            $request->get_param('expiration')
        );
    }
    
    public function handle_debug_log($request) {
        return $this->wp_ops->handle_debug_log(
            $request->get_param('action'),
            $request->get_param('lines')
        );
    }
    
    public function php_lint($request) {
        return $this->wp_ops->php_lint(
            $request->get_param('file_path'),
            $request->get_param('code')
        );
    }
    
    public function clear_cache($request) {
        return $this->wp_ops->clear_cache($request->get_param('type'));
    }
    
    // ========== SERVER OPERATION CALLBACKS ==========
    
    public function status($request) {
        return $this->server_ops->status();
    }
    
    public function server_info($request) {
        return $this->server_ops->server_info();
    }
    
    public function db_query($request) {
        return $this->server_ops->db_query(
            $request->get_param('query'),
            $request->get_param('limit')
        );
    }
}
