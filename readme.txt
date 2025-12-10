=== BanildTools ===
Contributors: banild
Tags: rest-api, file-manager, developer-tools, automation, cursor
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cursor-like file operation tools exposed via WordPress REST API. Perfect for automation and AI-assisted development.

== Description ==

BanildTools provides a comprehensive set of REST API endpoints for file operations, similar to the tools available in Cursor IDE. This plugin is designed for developers who need programmatic access to file operations on their WordPress installation.

**Features:**

* **Read Files** - Read file contents with optional line offset and limit
* **Write Files** - Create or overwrite files with specified contents
* **Edit Files** - Search and replace within files (single or all occurrences)
* **Delete Files** - Remove files or directories (with safety controls)
* **List Directories** - Get directory contents with filtering support
* **Search Files** - Grep-like pattern matching across files
* **Glob Search** - Find files by glob patterns
* **Create Directories** - Make new directories recursively
* **Rename/Move** - Rename or move files and directories
* **Copy** - Duplicate files or directories
* **File Info** - Get detailed information about files/directories

**Security Features:**

* Uses WordPress Application Passwords for authentication
* Path restrictions to allowed directories only
* Blocked file extensions for write operations
* Separate toggles for write and delete operations
* Administrator-only access

== Installation ==

1. Upload the `banildtools` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > BanildTools to configure allowed paths and security settings
4. Create an Application Password in Users > Your Profile > Application Passwords
5. Use the Application Password with your username for API authentication

== Authentication ==

This plugin uses WordPress Application Passwords for authentication. To use the API:

1. Go to Users > Your Profile in WordPress admin
2. Scroll down to "Application Passwords"
3. Enter a name for the application and click "Add New Application Password"
4. Use the generated password with your WordPress username for HTTP Basic Auth

Example with curl:
`curl -X POST "https://yoursite.com/wp-json/banildtools/v1/read" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"target_file": "wp-content/plugins/myplugin/file.php"}'`

== API Endpoints ==

**Base URL:** `/wp-json/banildtools/v1/`

= POST /read =
Read file contents.

Parameters:
* `target_file` (required): Path to the file
* `offset` (optional): Starting line number
* `limit` (optional): Number of lines to read

= POST /write =
Create or overwrite a file.

Parameters:
* `file_path` (required): Path to the file
* `contents` (required): File contents

= POST /edit =
Search and replace within a file.

Parameters:
* `file_path` (required): Path to the file
* `old_string` (required): Text to find
* `new_string` (required): Replacement text
* `replace_all` (optional): Replace all occurrences (default: false)

= POST /delete =
Delete a file or directory.

Parameters:
* `target_file` (required): Path to delete

= POST /list =
List directory contents.

Parameters:
* `target_directory` (required): Path to list
* `ignore_globs` (optional): Array of patterns to ignore

= POST /search =
Search for patterns in files (grep-like).

Parameters:
* `pattern` (required): Regex pattern
* `path` (optional): Directory to search
* `glob` (optional): File pattern filter
* `case_insensitive` (optional): Case-insensitive search
* `context_lines` (optional): Lines of context around matches
* `output_mode` (optional): content, files_with_matches, or count
* `max_results` (optional): Maximum results (default: 500)

= POST /glob =
Find files matching a glob pattern.

Parameters:
* `glob_pattern` (required): Pattern to match
* `target_directory` (optional): Directory to search

= POST /mkdir =
Create a directory.

Parameters:
* `path` (required): Directory path
* `recursive` (optional): Create parent directories (default: true)

= POST /rename =
Rename or move a file/directory.

Parameters:
* `source` (required): Source path
* `destination` (required): Destination path

= POST /copy =
Copy a file or directory.

Parameters:
* `source` (required): Source path
* `destination` (required): Destination path

= POST /info =
Get file or directory information.

Parameters:
* `path` (required): Path to get info for

= GET /status =
Get plugin status and configuration.

== Frequently Asked Questions ==

= Is this plugin secure? =

The plugin includes multiple security measures:
- Requires WordPress Application Password authentication
- Only users with `manage_options` capability can access endpoints
- Path restrictions prevent access outside allowed directories
- Blocked file extensions prevent writing dangerous files
- Delete operations are disabled by default

= Can I restrict which directories can be accessed? =

Yes, go to Settings > BanildTools and specify allowed paths (one per line).

= How do I enable delete operations? =

Go to Settings > BanildTools and check "Enable Delete Operations". Use with caution!

== Changelog ==

= 1.0.0 =
* Initial release
* Read, write, edit, delete file operations
* Directory listing with filtering
* Grep-like file search
* Glob pattern file search
* Directory creation, rename, copy operations
* File information endpoint
* Security controls and path restrictions

== Upgrade Notice ==

= 1.0.0 =
Initial release of BanildTools.

