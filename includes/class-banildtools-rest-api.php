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
        // ========== TOOL DISCOVERY (for banildmcp) ==========
        
        // List all available tools - public endpoint for MCP discovery
        register_rest_route(self::NAMESPACE, '/tools', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tools'),
            'permission_callback' => '__return_true', // Public for discovery
        ));
        
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
    
    // ========== TOOL DISCOVERY CALLBACK ==========
    
    /**
     * Returns all available BanildTools for MCP discovery
     * This allows banildmcp to dynamically load additional tools
     */
    public function get_tools($request) {
        $tools = array(
            // File Operations
            array(
                'name' => 'banildtools_read_file',
                'description' => 'Read file contents from the WordPress server filesystem. Supports offset and limit for large files.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'target_file' => array('type' => 'string', 'description' => 'Absolute path to the file to read'),
                        'offset' => array('type' => 'integer', 'description' => 'Line number to start reading from (optional)'),
                        'limit' => array('type' => 'integer', 'description' => 'Number of lines to read (optional)'),
                    ),
                    'required' => array('target_file'),
                ),
                'endpoint' => '/read',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_write_file',
                'description' => 'Write contents to a file on the WordPress server. Creates the file if it does not exist.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'file_path' => array('type' => 'string', 'description' => 'Absolute path to the file to write'),
                        'contents' => array('type' => 'string', 'description' => 'Content to write to the file'),
                    ),
                    'required' => array('file_path', 'contents'),
                ),
                'endpoint' => '/write',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_append_file',
                'description' => 'Append contents to an existing file on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'file_path' => array('type' => 'string', 'description' => 'Absolute path to the file'),
                        'contents' => array('type' => 'string', 'description' => 'Content to append'),
                    ),
                    'required' => array('file_path', 'contents'),
                ),
                'endpoint' => '/append',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_edit_file',
                'description' => 'Edit a file by replacing text (search and replace). Similar to sed.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'file_path' => array('type' => 'string', 'description' => 'Absolute path to the file'),
                        'old_string' => array('type' => 'string', 'description' => 'Text to find'),
                        'new_string' => array('type' => 'string', 'description' => 'Text to replace with'),
                        'replace_all' => array('type' => 'boolean', 'description' => 'Replace all occurrences (default: false)'),
                    ),
                    'required' => array('file_path', 'old_string', 'new_string'),
                ),
                'endpoint' => '/edit',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_delete_file',
                'description' => 'Delete a file or directory from the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'target_file' => array('type' => 'string', 'description' => 'Absolute path to the file or directory to delete'),
                    ),
                    'required' => array('target_file'),
                ),
                'endpoint' => '/delete',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_list_directory',
                'description' => 'List contents of a directory on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'target_directory' => array('type' => 'string', 'description' => 'Absolute path to the directory'),
                        'ignore_globs' => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Glob patterns to ignore'),
                    ),
                    'required' => array('target_directory'),
                ),
                'endpoint' => '/list',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_file_exists',
                'description' => 'Check if a file or directory exists on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'path' => array('type' => 'string', 'description' => 'Absolute path to check'),
                    ),
                    'required' => array('path'),
                ),
                'endpoint' => '/exists',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_file_info',
                'description' => 'Get detailed information about a file (size, permissions, modified time, etc.).',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'path' => array('type' => 'string', 'description' => 'Absolute path to the file'),
                    ),
                    'required' => array('path'),
                ),
                'endpoint' => '/info',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_create_directory',
                'description' => 'Create a new directory on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'path' => array('type' => 'string', 'description' => 'Absolute path for the new directory'),
                        'recursive' => array('type' => 'boolean', 'description' => 'Create parent directories if needed (default: true)'),
                    ),
                    'required' => array('path'),
                ),
                'endpoint' => '/mkdir',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_rename_file',
                'description' => 'Rename or move a file/directory on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'source' => array('type' => 'string', 'description' => 'Current path'),
                        'destination' => array('type' => 'string', 'description' => 'New path'),
                    ),
                    'required' => array('source', 'destination'),
                ),
                'endpoint' => '/rename',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_copy_file',
                'description' => 'Copy a file or directory on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'source' => array('type' => 'string', 'description' => 'Source path'),
                        'destination' => array('type' => 'string', 'description' => 'Destination path'),
                    ),
                    'required' => array('source', 'destination'),
                ),
                'endpoint' => '/copy',
                'method' => 'POST',
            ),
            // Search Operations
            array(
                'name' => 'banildtools_search_files',
                'description' => 'Search for text patterns in files (grep-like functionality). Supports regex.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'pattern' => array('type' => 'string', 'description' => 'Search pattern (regex supported)'),
                        'path' => array('type' => 'string', 'description' => 'Directory to search in (default: WordPress root)'),
                        'glob' => array('type' => 'string', 'description' => 'File glob pattern (e.g., "*.php")'),
                        'case_insensitive' => array('type' => 'boolean', 'description' => 'Case insensitive search'),
                        'context_lines' => array('type' => 'integer', 'description' => 'Lines of context around matches'),
                        'output_mode' => array('type' => 'string', 'description' => 'Output mode: content, files_with_matches, or count'),
                        'max_results' => array('type' => 'integer', 'description' => 'Maximum results (default: 500)'),
                    ),
                    'required' => array('pattern'),
                ),
                'endpoint' => '/search',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_glob_search',
                'description' => 'Find files matching a glob pattern on the WordPress server.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'glob_pattern' => array('type' => 'string', 'description' => 'Glob pattern (e.g., "**/*.php")'),
                        'target_directory' => array('type' => 'string', 'description' => 'Directory to search in'),
                    ),
                    'required' => array('glob_pattern'),
                ),
                'endpoint' => '/glob',
                'method' => 'POST',
            ),
            // WordPress Operations
            array(
                'name' => 'banildtools_option',
                'description' => 'Get, set, or delete WordPress options from the database.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'action' => array('type' => 'string', 'enum' => array('get', 'set', 'delete'), 'description' => 'Action to perform'),
                        'name' => array('type' => 'string', 'description' => 'Option name'),
                        'value' => array('description' => 'Option value (for set action)'),
                    ),
                    'required' => array('action', 'name'),
                ),
                'endpoint' => '/option',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_transient',
                'description' => 'Get, set, or delete WordPress transients (temporary cached data).',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'action' => array('type' => 'string', 'enum' => array('get', 'set', 'delete'), 'description' => 'Action to perform'),
                        'name' => array('type' => 'string', 'description' => 'Transient name'),
                        'value' => array('description' => 'Transient value (for set action)'),
                        'expiration' => array('type' => 'integer', 'description' => 'Expiration time in seconds (default: 3600)'),
                    ),
                    'required' => array('action', 'name'),
                ),
                'endpoint' => '/transient',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_debug_log',
                'description' => 'Read, tail, or clear the WordPress debug.log file.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'action' => array('type' => 'string', 'enum' => array('read', 'clear', 'tail'), 'description' => 'Action to perform'),
                        'lines' => array('type' => 'integer', 'description' => 'Number of lines to read (default: 100)'),
                    ),
                    'required' => array(),
                ),
                'endpoint' => '/debug-log',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_php_lint',
                'description' => 'Check PHP syntax of a file or code snippet.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'file_path' => array('type' => 'string', 'description' => 'Path to PHP file to check'),
                        'code' => array('type' => 'string', 'description' => 'PHP code to check (alternative to file_path)'),
                    ),
                    'required' => array(),
                ),
                'endpoint' => '/php-lint',
                'method' => 'POST',
            ),
            array(
                'name' => 'banildtools_clear_cache',
                'description' => 'Clear WordPress and plugin caches.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'type' => array('type' => 'string', 'description' => 'Cache type to clear: all, object, transients, rewrite'),
                    ),
                    'required' => array(),
                ),
                'endpoint' => '/clear-cache',
                'method' => 'POST',
            ),
            // Server Operations
            array(
                'name' => 'banildtools_status',
                'description' => 'Get BanildTools plugin status and health check.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array(),
                ),
                'endpoint' => '/status',
                'method' => 'GET',
            ),
            array(
                'name' => 'banildtools_server_info',
                'description' => 'Get detailed server information (PHP version, memory, disk space, etc.).',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array(),
                ),
                'endpoint' => '/server-info',
                'method' => 'GET',
            ),
            array(
                'name' => 'banildtools_db_query',
                'description' => 'Execute read-only SQL queries on the WordPress database.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query' => array('type' => 'string', 'description' => 'SQL SELECT query to execute'),
                        'limit' => array('type' => 'integer', 'description' => 'Maximum rows to return (default: 100)'),
                    ),
                    'required' => array('query'),
                ),
                'endpoint' => '/db-query',
                'method' => 'POST',
            ),
        );
        
        // Add plugin metadata
        return array(
            'plugin' => 'BanildTools',
            'version' => '2.1.0',
            'namespace' => self::NAMESPACE,
            'base_url' => rest_url(self::NAMESPACE),
            'tools_count' => count($tools),
            'tools' => $tools,
        );
    }
}
