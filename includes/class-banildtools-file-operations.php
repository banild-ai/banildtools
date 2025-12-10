<?php
/**
 * File Operations Handler for BanildTools
 * 
 * @package BanildTools
 */

if (!defined('ABSPATH')) {
    exit;
}

class BanildTools_File_Operations {
    
    /**
     * Resolve and validate path
     * 
     * @param string $path Path to resolve
     * @return string|WP_Error Resolved path or error
     */
    private function resolve_path($path) {
        // Handle both forward and backslashes
        $path = str_replace('\\', '/', $path);
        
        // If path is relative, make it absolute from ABSPATH
        if (!$this->is_absolute_path($path)) {
            $path = ABSPATH . ltrim($path, '/');
        }
        
        // Normalize the path
        $path = realpath(dirname($path)) . '/' . basename($path);
        
        // Check if file exists for read operations
        // For write operations, check if parent directory exists
        $check_path = file_exists($path) ? $path : dirname($path);
        if (!file_exists($check_path)) {
            // For create operations, try to get as much of the real path as possible
            $parts = explode('/', trim($path, '/'));
            $build_path = '';
            foreach ($parts as $part) {
                $test_path = $build_path . '/' . $part;
                if (file_exists($test_path)) {
                    $build_path = realpath($test_path);
                } else {
                    $build_path .= '/' . $part;
                }
            }
            $path = $build_path;
        }
        
        // Validate against allowed paths
        $allowed_paths = get_option('banildtools_allowed_paths', ABSPATH);
        $allowed_paths = array_filter(array_map('trim', explode("\n", $allowed_paths)));
        
        $is_allowed = false;
        foreach ($allowed_paths as $allowed_path) {
            $allowed_path = str_replace('\\', '/', $allowed_path);
            $allowed_path = rtrim($allowed_path, '/');
            
            // Normalize for comparison
            $normalized_path = str_replace('\\', '/', $path);
            $normalized_allowed = str_replace('\\', '/', realpath($allowed_path) ?: $allowed_path);
            
            if (strpos($normalized_path, $normalized_allowed) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            return new WP_Error(
                'path_not_allowed',
                __('The specified path is outside the allowed directories.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        return $path;
    }
    
    /**
     * Check if path is absolute
     */
    private function is_absolute_path($path) {
        // Windows absolute path
        if (preg_match('/^[A-Za-z]:[\\/]/', $path)) {
            return true;
        }
        // Unix absolute path
        if (strpos($path, '/') === 0) {
            return true;
        }
        return false;
    }
    
    /**
     * Check if file extension is blocked
     */
    private function is_blocked_extension($path) {
        $blocked = get_option('banildtools_blocked_extensions', 'exe,dll,so,sh,bat,cmd');
        $blocked_array = array_map('trim', explode(',', strtolower($blocked)));
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, $blocked_array);
    }
    
    /**
     * Read file contents
     */
    public function read_file($target_file, $offset = null, $limit = null) {
        $path = $this->resolve_path($target_file);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (!file_exists($path)) {
            return new WP_Error(
                'file_not_found',
                __('The specified file does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $path)
            );
        }
        
        if (!is_file($path)) {
            return new WP_Error(
                'not_a_file',
                __('The specified path is not a file.', 'banildtools'),
                array('status' => 400, 'path' => $path)
            );
        }
        
        if (!is_readable($path)) {
            return new WP_Error(
                'not_readable',
                __('The file is not readable.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        // Check if it's a binary file
        $mime_type = mime_content_type($path);
        $is_binary = !preg_match('/^text\/|^application\/(json|xml|javascript|x-php|x-httpd-php)/', $mime_type);
        
        // For images, return base64 encoded content
        if (preg_match('/^image\//', $mime_type)) {
            $contents = file_get_contents($path);
            return new WP_REST_Response(array(
                'success' => true,
                'path' => $path,
                'mime_type' => $mime_type,
                'size' => filesize($path),
                'is_binary' => true,
                'is_image' => true,
                'contents_base64' => base64_encode($contents),
            ), 200);
        }
        
        if ($is_binary) {
            return new WP_REST_Response(array(
                'success' => true,
                'path' => $path,
                'mime_type' => $mime_type,
                'size' => filesize($path),
                'is_binary' => true,
                'message' => __('Binary file - contents not returned', 'banildtools'),
            ), 200);
        }
        
        $contents = file_get_contents($path);
        $lines = explode("\n", $contents);
        $total_lines = count($lines);
        
        // Handle offset and limit (1-based line numbers)
        if ($offset !== null || $limit !== null) {
            $start = ($offset !== null) ? max(0, $offset - 1) : 0;
            $length = ($limit !== null) ? $limit : null;
            $lines = array_slice($lines, $start, $length);
            
            // Add line numbers
            $numbered_lines = array();
            $line_num = $start + 1;
            foreach ($lines as $line) {
                $numbered_lines[] = sprintf('%6d|%s', $line_num, $line);
                $line_num++;
            }
            $contents = implode("\n", $numbered_lines);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'mime_type' => $mime_type,
            'size' => filesize($path),
            'total_lines' => $total_lines,
            'contents' => $contents,
        ), 200);
    }
    
    /**
     * Write file contents
     */
    public function write_file($file_path, $contents) {
        $path = $this->resolve_path($file_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if ($this->is_blocked_extension($path)) {
            return new WP_Error(
                'blocked_extension',
                __('This file extension is blocked for security reasons.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        // Create directory if it doesn't exist
        $dir = dirname($path);
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new WP_Error(
                    'mkdir_failed',
                    __('Failed to create directory.', 'banildtools'),
                    array('status' => 500, 'directory' => $dir)
                );
            }
        }
        
        // Check if directory is writable
        if (!is_writable($dir)) {
            return new WP_Error(
                'not_writable',
                __('The directory is not writable.', 'banildtools'),
                array('status' => 403, 'directory' => $dir)
            );
        }
        
        // Check if file exists and is writable
        if (file_exists($path) && !is_writable($path)) {
            return new WP_Error(
                'file_not_writable',
                __('The file exists but is not writable.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        $result = file_put_contents($path, $contents);
        
        if ($result === false) {
            return new WP_Error(
                'write_failed',
                __('Failed to write to file.', 'banildtools'),
                array('status' => 500, 'path' => $path)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'bytes_written' => $result,
            'message' => __('File written successfully.', 'banildtools'),
        ), 200);
    }
    
    /**
     * Edit file (search and replace)
     */
    public function edit_file($file_path, $old_string, $new_string, $replace_all = false) {
        $path = $this->resolve_path($file_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (!file_exists($path)) {
            return new WP_Error(
                'file_not_found',
                __('The specified file does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $path)
            );
        }
        
        if (!is_file($path)) {
            return new WP_Error(
                'not_a_file',
                __('The specified path is not a file.', 'banildtools'),
                array('status' => 400, 'path' => $path)
            );
        }
        
        if (!is_writable($path)) {
            return new WP_Error(
                'not_writable',
                __('The file is not writable.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        $contents = file_get_contents($path);
        
        // Check if old_string exists in file
        if (strpos($contents, $old_string) === false) {
            return new WP_Error(
                'string_not_found',
                __('The search string was not found in the file.', 'banildtools'),
                array('status' => 404, 'search' => $old_string)
            );
        }
        
        // Count occurrences
        $count = substr_count($contents, $old_string);
        
        // Check if unique when replace_all is false
        if (!$replace_all && $count > 1) {
            return new WP_Error(
                'string_not_unique',
                sprintf(
                    __('The search string appears %d times in the file. Use replace_all=true to replace all, or provide a more unique search string.', 'banildtools'),
                    $count
                ),
                array('status' => 400, 'occurrences' => $count)
            );
        }
        
        // Perform replacement
        if ($replace_all) {
            $new_contents = str_replace($old_string, $new_string, $contents);
            $replacements = $count;
        } else {
            // Replace only the first occurrence
            $pos = strpos($contents, $old_string);
            $new_contents = substr_replace($contents, $new_string, $pos, strlen($old_string));
            $replacements = 1;
        }
        
        // Write back
        $result = file_put_contents($path, $new_contents);
        
        if ($result === false) {
            return new WP_Error(
                'write_failed',
                __('Failed to write to file.', 'banildtools'),
                array('status' => 500, 'path' => $path)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'replacements' => $replacements,
            'bytes_written' => $result,
            'message' => sprintf(
                __('Successfully replaced %d occurrence(s).', 'banildtools'),
                $replacements
            ),
        ), 200);
    }
    
    /**
     * Delete file
     */
    public function delete_file($target_file) {
        $path = $this->resolve_path($target_file);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (!file_exists($path)) {
            return new WP_REST_Response(array(
                'success' => true,
                'path' => $path,
                'message' => __('File does not exist (already deleted or never existed).', 'banildtools'),
            ), 200);
        }
        
        if (is_dir($path)) {
            // Delete directory recursively
            if (!$this->delete_directory_recursive($path)) {
                return new WP_Error(
                    'delete_failed',
                    __('Failed to delete directory.', 'banildtools'),
                    array('status' => 500, 'path' => $path)
                );
            }
        } else {
            // Delete file
            if (!unlink($path)) {
                return new WP_Error(
                    'delete_failed',
                    __('Failed to delete file.', 'banildtools'),
                    array('status' => 500, 'path' => $path)
                );
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'message' => __('Successfully deleted.', 'banildtools'),
        ), 200);
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * List directory contents
     */
    public function list_directory($target_directory, $ignore_globs = array()) {
        $path = $this->resolve_path($target_directory);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (!file_exists($path)) {
            return new WP_Error(
                'directory_not_found',
                __('The specified directory does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $path)
            );
        }
        
        if (!is_dir($path)) {
            return new WP_Error(
                'not_a_directory',
                __('The specified path is not a directory.', 'banildtools'),
                array('status' => 400, 'path' => $path)
            );
        }
        
        if (!is_readable($path)) {
            return new WP_Error(
                'not_readable',
                __('The directory is not readable.', 'banildtools'),
                array('status' => 403, 'path' => $path)
            );
        }
        
        $items = array();
        $iterator = new DirectoryIterator($path);
        
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            
            $name = $item->getFilename();
            
            // Skip hidden files
            if (strpos($name, '.') === 0) {
                continue;
            }
            
            // Check ignore globs
            $should_ignore = false;
            foreach ($ignore_globs as $glob) {
                if (fnmatch($glob, $name) || fnmatch('**/' . $glob, $name)) {
                    $should_ignore = true;
                    break;
                }
            }
            
            if ($should_ignore) {
                continue;
            }
            
            $item_data = array(
                'name' => $name,
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->isFile() ? $item->getSize() : null,
                'modified' => date('c', $item->getMTime()),
                'permissions' => substr(sprintf('%o', $item->getPerms()), -4),
            );
            
            if ($item->isFile()) {
                $item_data['extension'] = pathinfo($name, PATHINFO_EXTENSION);
            }
            
            $items[] = $item_data;
        }
        
        // Sort: directories first, then alphabetically
        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'total_items' => count($items),
            'items' => $items,
        ), 200);
    }
    
    /**
     * Create directory
     */
    public function create_directory($dir_path, $recursive = true) {
        $path = $this->resolve_path($dir_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (file_exists($path)) {
            if (is_dir($path)) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'path' => $path,
                    'message' => __('Directory already exists.', 'banildtools'),
                ), 200);
            } else {
                return new WP_Error(
                    'path_exists',
                    __('A file with this name already exists.', 'banildtools'),
                    array('status' => 400, 'path' => $path)
                );
            }
        }
        
        if ($recursive) {
            $result = wp_mkdir_p($path);
        } else {
            $result = mkdir($path);
        }
        
        if (!$result) {
            return new WP_Error(
                'mkdir_failed',
                __('Failed to create directory.', 'banildtools'),
                array('status' => 500, 'path' => $path)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'message' => __('Directory created successfully.', 'banildtools'),
        ), 200);
    }
    
    /**
     * Rename/move file or directory
     */
    public function rename_file($source, $destination) {
        $source_path = $this->resolve_path($source);
        
        if (is_wp_error($source_path)) {
            return $source_path;
        }
        
        $dest_path = $this->resolve_path($destination);
        
        if (is_wp_error($dest_path)) {
            return $dest_path;
        }
        
        if (!file_exists($source_path)) {
            return new WP_Error(
                'source_not_found',
                __('The source file/directory does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $source_path)
            );
        }
        
        if (file_exists($dest_path)) {
            return new WP_Error(
                'destination_exists',
                __('The destination already exists.', 'banildtools'),
                array('status' => 400, 'path' => $dest_path)
            );
        }
        
        // Create destination directory if it doesn't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            if (!wp_mkdir_p($dest_dir)) {
                return new WP_Error(
                    'mkdir_failed',
                    __('Failed to create destination directory.', 'banildtools'),
                    array('status' => 500, 'directory' => $dest_dir)
                );
            }
        }
        
        if (!rename($source_path, $dest_path)) {
            return new WP_Error(
                'rename_failed',
                __('Failed to rename/move.', 'banildtools'),
                array('status' => 500)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'source' => $source_path,
            'destination' => $dest_path,
            'message' => __('Successfully renamed/moved.', 'banildtools'),
        ), 200);
    }
    
    /**
     * Copy file or directory
     */
    public function copy_file($source, $destination) {
        $source_path = $this->resolve_path($source);
        
        if (is_wp_error($source_path)) {
            return $source_path;
        }
        
        $dest_path = $this->resolve_path($destination);
        
        if (is_wp_error($dest_path)) {
            return $dest_path;
        }
        
        if (!file_exists($source_path)) {
            return new WP_Error(
                'source_not_found',
                __('The source file/directory does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $source_path)
            );
        }
        
        // Create destination directory if it doesn't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            if (!wp_mkdir_p($dest_dir)) {
                return new WP_Error(
                    'mkdir_failed',
                    __('Failed to create destination directory.', 'banildtools'),
                    array('status' => 500, 'directory' => $dest_dir)
                );
            }
        }
        
        if (is_dir($source_path)) {
            // Copy directory recursively
            if (!$this->copy_directory_recursive($source_path, $dest_path)) {
                return new WP_Error(
                    'copy_failed',
                    __('Failed to copy directory.', 'banildtools'),
                    array('status' => 500)
                );
            }
        } else {
            // Copy file
            if (!copy($source_path, $dest_path)) {
                return new WP_Error(
                    'copy_failed',
                    __('Failed to copy file.', 'banildtools'),
                    array('status' => 500)
                );
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'source' => $source_path,
            'destination' => $dest_path,
            'message' => __('Successfully copied.', 'banildtools'),
        ), 200);
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory_recursive($source, $dest) {
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true)) {
                return false;
            }
        }
        
        $dir = new DirectoryIterator($source);
        
        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }
            
            $source_path = $item->getPathname();
            $dest_path = $dest . '/' . $item->getFilename();
            
            if ($item->isDir()) {
                if (!$this->copy_directory_recursive($source_path, $dest_path)) {
                    return false;
                }
            } else {
                if (!copy($source_path, $dest_path)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Append content to file
     */
    public function append_file($file_path, $contents) {
        $path = $this->resolve_path($file_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if ($this->is_blocked_extension($path)) {
            return new WP_Error('blocked_extension', 'This file extension is blocked.', array('status' => 403));
        }
        
        // Create file if doesn't exist
        $dir = dirname($path);
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new WP_Error('mkdir_failed', 'Failed to create directory.', array('status' => 500));
            }
        }
        
        if (!file_exists($path)) {
            touch($path);
        }
        
        if (!is_writable($path)) {
            return new WP_Error('not_writable', 'File is not writable.', array('status' => 403));
        }
        
        $result = file_put_contents($path, $contents, FILE_APPEND);
        
        if ($result === false) {
            return new WP_Error('append_failed', 'Failed to append to file.', array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'bytes_appended' => $result,
            'total_size' => filesize($path),
            'message' => 'Content appended successfully.',
        ), 200);
    }
    
    /**
     * Check if file exists
     */
    public function file_exists($file_path) {
        $path = $this->resolve_path($file_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        $exists = file_exists($path);
        $type = null;
        
        if ($exists) {
            $type = is_dir($path) ? 'directory' : 'file';
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'path' => $path,
            'exists' => $exists,
            'type' => $type,
        ), 200);
    }
    
    /**
     * Get file/directory info
     */
    public function file_info($file_path) {
        $path = $this->resolve_path($file_path);
        
        if (is_wp_error($path)) {
            return $path;
        }
        
        if (!file_exists($path)) {
            return new WP_Error(
                'not_found',
                __('The specified path does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $path)
            );
        }
        
        $info = array(
            'path' => $path,
            'name' => basename($path),
            'type' => is_dir($path) ? 'directory' : 'file',
            'exists' => true,
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4),
            'modified' => date('c', filemtime($path)),
            'accessed' => date('c', fileatime($path)),
        );
        
        if (is_file($path)) {
            $info['size'] = filesize($path);
            $info['extension'] = pathinfo($path, PATHINFO_EXTENSION);
            $info['mime_type'] = mime_content_type($path);
            $info['lines'] = count(file($path));
        } else {
            // Count items in directory
            $count = 0;
            $iterator = new DirectoryIterator($path);
            foreach ($iterator as $item) {
                if (!$item->isDot()) {
                    $count++;
                }
            }
            $info['items_count'] = $count;
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'info' => $info,
        ), 200);
    }
}

