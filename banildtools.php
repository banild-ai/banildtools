<?php
/**
 * Plugin Name: BanildTools
 * Plugin URI: https://banild.com/plugins/banildtools
 * Description: Cursor-like file operation tools exposed via WordPress REST API. Full server management through AI assistants.
 * Version: 2.1.0
 * Author: Banild
 * Author URI: https://banild.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: banildtools
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BANILDTOOLS_VERSION', '2.1.0');
define('BANILDTOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BANILDTOOLS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the REST API classes
require_once BANILDTOOLS_PLUGIN_DIR . 'includes/class-banildtools-rest-api.php';
require_once BANILDTOOLS_PLUGIN_DIR . 'includes/class-banildtools-file-operations.php';
require_once BANILDTOOLS_PLUGIN_DIR . 'includes/class-banildtools-search.php';
require_once BANILDTOOLS_PLUGIN_DIR . 'includes/class-banildtools-wordpress-ops.php';
require_once BANILDTOOLS_PLUGIN_DIR . 'includes/class-banildtools-server-ops.php';

/**
 * Main plugin class
 */
class BanildTools {
    
    private static $instance = null;
    private $rest_api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'init_rest_api'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init_rest_api() {
        $this->rest_api = new BanildTools_REST_API();
        $this->rest_api->register_routes();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('BanildTools', 'banildtools'),
            __('BanildTools', 'banildtools'),
            'manage_options',
            'banildtools',
            array($this, 'render_settings_page'),
            'dashicons-admin-tools',
            80
        );
    }
    
    public function register_settings() {
        register_setting('banildtools_settings', 'banildtools_allowed_paths');
        register_setting('banildtools_settings', 'banildtools_blocked_extensions');
        register_setting('banildtools_settings', 'banildtools_enable_write');
        register_setting('banildtools_settings', 'banildtools_enable_delete');
        register_setting('banildtools_settings', 'banildtools_enable_db');
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'banildtools') === false) {
            return;
        }
        
        wp_enqueue_style(
            'banildtools-admin',
            BANILDTOOLS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BANILDTOOLS_VERSION
        );
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $allowed_paths = get_option('banildtools_allowed_paths', ABSPATH);
        $blocked_extensions = get_option('banildtools_blocked_extensions', 'exe,dll,so,sh,bat,cmd');
        $enable_write = get_option('banildtools_enable_write', '1');
        $enable_delete = get_option('banildtools_enable_delete', '1');
        $enable_db = get_option('banildtools_enable_db', '1');
        
        // Get stats
        $total_tools = 24;
        $active_tools = ($enable_write === '1' ? 8 : 0) + ($enable_delete === '1' ? 1 : 0) + ($enable_db === '1' ? 2 : 0) + 13;
        
        ?>
        <div class="banildtools-app">
            <!-- Sidebar -->
            <aside class="banildtools-sidebar">
                <div class="banildtools-logo">
                    <img src="<?php echo esc_url(BANILDTOOLS_PLUGIN_URL . 'assets/logo.png'); ?>" alt="Banild" width="32" height="32">
                    <span>Banild</span>
                </div>
                <nav class="banildtools-nav">
                    <a href="#dashboard" class="nav-item active" data-tab="dashboard">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="14" y="14" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="#settings" class="nav-item" data-tab="settings">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        Settings
                    </a>
                    <a href="#api" class="nav-item" data-tab="api">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        API Reference
                    </a>
                    <a href="#tools" class="nav-item" data-tab="tools">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                        Tools (<?php echo $total_tools; ?>)
                    </a>
                </nav>
                <div class="banildtools-version">
                    v<?php echo BANILDTOOLS_VERSION; ?>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="banildtools-main">
                <!-- Dashboard Tab -->
                <div class="tab-content active" id="dashboard">
                    <div class="banildtools-header">
                        <h1>Dashboard</h1>
                        <p class="subtitle">Cursor-like file operations for WordPress</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo $total_tools; ?></span>
                                <span class="stat-label">Total Tools</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo $active_tools; ?></span>
                                <span class="stat-label">Active Tools</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon purple">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo $enable_write === '1' ? 'ON' : 'OFF'; ?></span>
                                <span class="stat-label">Write Access</span>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon <?php echo $enable_delete === '1' ? 'red' : 'gray'; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value"><?php echo $enable_delete === '1' ? 'ON' : 'OFF'; ?></span>
                                <span class="stat-label">Delete Access</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-card-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            <h3>API Endpoint</h3>
                        </div>
                        <code class="api-url"><?php echo esc_url(rest_url('banildtools/v1/')); ?></code>
                        <p class="info-text">Use WordPress Application Passwords for authentication.<br>Go to <strong>Users → Your Profile → Application Passwords</strong></p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-card-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                            <h3>Server Info</h3>
                        </div>
                        <div class="server-info-grid">
                            <div class="server-info-item">
                                <span class="label">WordPress</span>
                                <span class="value"><?php echo get_bloginfo('version'); ?></span>
                            </div>
                            <div class="server-info-item">
                                <span class="label">PHP</span>
                                <span class="value"><?php echo PHP_VERSION; ?></span>
                            </div>
                            <div class="server-info-item">
                                <span class="label">Memory Limit</span>
                                <span class="value"><?php echo ini_get('memory_limit'); ?></span>
                            </div>
                            <div class="server-info-item">
                                <span class="label">Max Upload</span>
                                <span class="value"><?php echo ini_get('upload_max_filesize'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Tab -->
                <div class="tab-content" id="settings">
                    <div class="banildtools-header">
                        <h1>Settings</h1>
                        <p class="subtitle">Configure security and permissions</p>
                    </div>
                    
                    <form method="post" action="options.php" class="settings-form">
                        <?php settings_fields('banildtools_settings'); ?>
                        
                        <div class="form-card">
                            <h3>Security</h3>
                            
                            <div class="form-group">
                                <label for="banildtools_allowed_paths">Allowed Paths</label>
                                <textarea name="banildtools_allowed_paths" id="banildtools_allowed_paths" rows="4" placeholder="One path per line..."><?php echo esc_textarea($allowed_paths); ?></textarea>
                                <p class="form-help">Only files within these paths can be accessed. Default: WordPress root.</p>
                            </div>
                            
                            <div class="form-group">
                                <label for="banildtools_blocked_extensions">Blocked Extensions</label>
                                <input type="text" name="banildtools_blocked_extensions" id="banildtools_blocked_extensions" value="<?php echo esc_attr($blocked_extensions); ?>" placeholder="exe,dll,so...">
                                <p class="form-help">Comma-separated list of blocked file extensions.</p>
                            </div>
                        </div>
                        
                        <div class="form-card">
                            <h3>Permissions</h3>
                            
                            <div class="toggle-group">
                                <label class="toggle">
                                    <input type="checkbox" name="banildtools_enable_write" value="1" <?php checked($enable_write, '1'); ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">
                                        <strong>Write Operations</strong>
                                        <span>Allow write, edit, append, mkdir operations</span>
                                    </span>
                                </label>
                                
                                <label class="toggle">
                                    <input type="checkbox" name="banildtools_enable_delete" value="1" <?php checked($enable_delete, '1'); ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">
                                        <strong>Delete Operations</strong>
                                        <span>Allow file and directory deletion</span>
                                    </span>
                                </label>
                                
                                <label class="toggle">
                                    <input type="checkbox" name="banildtools_enable_db" value="1" <?php checked($enable_db, '1'); ?>>
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">
                                        <strong>Database Operations</strong>
                                        <span>Allow read-only SQL queries</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Save Settings
                        </button>
                    </form>
                </div>
                
                <!-- API Reference Tab -->
                <div class="tab-content" id="api">
                    <div class="banildtools-header">
                        <h1>API Reference</h1>
                        <p class="subtitle">Complete endpoint documentation</p>
                    </div>
                    
                    <div class="api-section">
                        <h3>Authentication</h3>
                        <pre class="code-block"><code>curl -X POST "<?php echo esc_url(rest_url('banildtools/v1/read')); ?>" \
  -u "username:application-password" \
  -H "Content-Type: application/json" \
  -d '{"target_file": "wp-config.php"}'</code></pre>
                    </div>
                    
                    <div class="api-section">
                        <h3>File Operations</h3>
                        <div class="endpoint-list">
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/read</code>
                                <span class="desc">Read file contents</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/write</code>
                                <span class="desc">Create or overwrite file</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/append</code>
                                <span class="desc">Append to file</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/edit</code>
                                <span class="desc">Search and replace</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/delete</code>
                                <span class="desc">Delete file/directory</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/list</code>
                                <span class="desc">List directory contents</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/exists</code>
                                <span class="desc">Check if path exists</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/info</code>
                                <span class="desc">Get file/directory info</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/mkdir</code>
                                <span class="desc">Create directory</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/copy</code>
                                <span class="desc">Copy file/directory</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/rename</code>
                                <span class="desc">Rename/move file</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Search Operations</h3>
                        <div class="endpoint-list">
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/search</code>
                                <span class="desc">Grep-like pattern search</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/glob</code>
                                <span class="desc">Find files by pattern</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>WordPress Operations</h3>
                        <div class="endpoint-list">
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/option</code>
                                <span class="desc">Get/set WordPress options</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/transient</code>
                                <span class="desc">Get/set/delete transients</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/debug-log</code>
                                <span class="desc">Read/clear debug.log</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/php-lint</code>
                                <span class="desc">Check PHP syntax</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="api-section">
                        <h3>Server Operations</h3>
                        <div class="endpoint-list">
                            <div class="endpoint">
                                <span class="method get">GET</span>
                                <code>/status</code>
                                <span class="desc">Plugin status check</span>
                            </div>
                            <div class="endpoint">
                                <span class="method get">GET</span>
                                <code>/server-info</code>
                                <span class="desc">Server information</span>
                            </div>
                            <div class="endpoint">
                                <span class="method post">POST</span>
                                <code>/db-query</code>
                                <span class="desc">Execute SELECT query</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tools Tab -->
                <div class="tab-content" id="tools">
                    <div class="banildtools-header">
                        <h1>Available Tools</h1>
                        <p class="subtitle"><?php echo $total_tools; ?> tools for complete server management</p>
                    </div>
                    
                    <div class="tools-grid">
                        <?php
                        $tools = array(
                            array('name' => 'read_file', 'icon' => '📄', 'desc' => 'Read file contents with line range'),
                            array('name' => 'write_file', 'icon' => '✍️', 'desc' => 'Create or overwrite files'),
                            array('name' => 'append_file', 'icon' => '➕', 'desc' => 'Append content to files'),
                            array('name' => 'edit_file', 'icon' => '🔄', 'desc' => 'Search and replace in files'),
                            array('name' => 'delete_file', 'icon' => '🗑️', 'desc' => 'Delete files or directories'),
                            array('name' => 'list_dir', 'icon' => '📁', 'desc' => 'List directory contents'),
                            array('name' => 'file_exists', 'icon' => '❓', 'desc' => 'Check if path exists'),
                            array('name' => 'file_info', 'icon' => 'ℹ️', 'desc' => 'Get detailed file info'),
                            array('name' => 'mkdir', 'icon' => '📂', 'desc' => 'Create directories'),
                            array('name' => 'copy', 'icon' => '📋', 'desc' => 'Copy files/directories'),
                            array('name' => 'rename', 'icon' => '📝', 'desc' => 'Rename/move files'),
                            array('name' => 'grep', 'icon' => '🔍', 'desc' => 'Search patterns in files'),
                            array('name' => 'glob_search', 'icon' => '🎯', 'desc' => 'Find files by pattern'),
                            array('name' => 'get_option', 'icon' => '⚙️', 'desc' => 'Get WordPress option'),
                            array('name' => 'set_option', 'icon' => '💾', 'desc' => 'Set WordPress option'),
                            array('name' => 'get_transient', 'icon' => '⏱️', 'desc' => 'Get transient value'),
                            array('name' => 'set_transient', 'icon' => '⏳', 'desc' => 'Set transient value'),
                            array('name' => 'delete_transient', 'icon' => '🧹', 'desc' => 'Delete transient'),
                            array('name' => 'debug_log', 'icon' => '🐛', 'desc' => 'Read/clear debug.log'),
                            array('name' => 'php_lint', 'icon' => '✅', 'desc' => 'Check PHP syntax'),
                            array('name' => 'server_info', 'icon' => '🖥️', 'desc' => 'Get server information'),
                            array('name' => 'db_query', 'icon' => '🗄️', 'desc' => 'Execute SELECT query'),
                            array('name' => 'status', 'icon' => '💚', 'desc' => 'Plugin status check'),
                            array('name' => 'clear_cache', 'icon' => '🧹', 'desc' => 'Clear object cache'),
                        );
                        
                        foreach ($tools as $tool) {
                            echo '<div class="tool-card">';
                            echo '<span class="tool-icon">' . $tool['icon'] . '</span>';
                            echo '<div class="tool-info">';
                            echo '<span class="tool-name">' . esc_html($tool['name']) . '</span>';
                            echo '<span class="tool-desc">' . esc_html($tool['desc']) . '</span>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </main>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            const tabContents = document.querySelectorAll('.tab-content');
            
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    
                    navItems.forEach(nav => nav.classList.remove('active'));
                    tabContents.forEach(tab => tab.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
        </script>
        <?php
    }
    
    public function activate() {
        add_option('banildtools_allowed_paths', ABSPATH);
        add_option('banildtools_blocked_extensions', 'exe,dll,so,sh,bat,cmd');
        add_option('banildtools_enable_write', '1');
        add_option('banildtools_enable_delete', '1'); // Enabled by default now
        add_option('banildtools_enable_db', '1');
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
BanildTools::get_instance();
