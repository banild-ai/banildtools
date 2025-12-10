<?php
/**
 * Search Handler for BanildTools
 * 
 * @package BanildTools
 */

if (!defined('ABSPATH')) {
    exit;
}

class BanildTools_Search {
    
    /**
     * Resolve and validate path
     */
    private function resolve_path($path) {
        // Handle both forward and backslashes
        $path = str_replace('\\', '/', $path);
        
        // If path is relative, make it absolute from ABSPATH
        if (!$this->is_absolute_path($path)) {
            $path = ABSPATH . ltrim($path, '/');
        }
        
        // Get real path if exists
        $real_path = realpath($path);
        if ($real_path) {
            $path = $real_path;
        }
        
        // Validate against allowed paths
        $allowed_paths = get_option('banildtools_allowed_paths', ABSPATH);
        $allowed_paths = array_filter(array_map('trim', explode("\n", $allowed_paths)));
        
        $is_allowed = false;
        foreach ($allowed_paths as $allowed_path) {
            $allowed_path = str_replace('\\', '/', $allowed_path);
            $allowed_path = rtrim($allowed_path, '/');
            
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
        if (preg_match('/^[A-Za-z]:[\\/]/', $path)) {
            return true;
        }
        if (strpos($path, '/') === 0) {
            return true;
        }
        return false;
    }
    
    /**
     * Search for pattern in files (grep-like)
     */
    public function grep($pattern, $path, $glob = null, $case_insensitive = false, $context_lines = 0, $output_mode = 'content', $max_results = 500) {
        $resolved_path = $this->resolve_path($path);
        
        if (is_wp_error($resolved_path)) {
            return $resolved_path;
        }
        
        if (!file_exists($resolved_path)) {
            return new WP_Error(
                'path_not_found',
                __('The specified path does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $resolved_path)
            );
        }
        
        // Build regex
        $regex_flags = $case_insensitive ? 'i' : '';
        $regex = '/' . $pattern . '/' . $regex_flags;
        
        // Validate regex
        if (@preg_match($regex, '') === false) {
            return new WP_Error(
                'invalid_pattern',
                __('Invalid regex pattern.', 'banildtools'),
                array('status' => 400, 'pattern' => $pattern)
            );
        }
        
        $results = array();
        $file_count = 0;
        $match_count = 0;
        $truncated = false;
        
        // Get files to search
        if (is_file($resolved_path)) {
            $files = array($resolved_path);
        } else {
            $files = $this->get_searchable_files($resolved_path, $glob);
        }
        
        foreach ($files as $file) {
            if ($match_count >= $max_results) {
                $truncated = true;
                break;
            }
            
            $file_results = $this->search_file($file, $regex, $context_lines, $output_mode, $max_results - $match_count);
            
            if (!empty($file_results['matches'])) {
                $match_count += count($file_results['matches']);
                $file_count++;
                
                $relative_path = str_replace(ABSPATH, '', str_replace('\\', '/', $file));
                
                if ($output_mode === 'files_with_matches') {
                    $results[] = $relative_path;
                } elseif ($output_mode === 'count') {
                    $results[] = array(
                        'file' => $relative_path,
                        'count' => count($file_results['matches']),
                    );
                } else {
                    $results[] = array(
                        'file' => $relative_path,
                        'matches' => $file_results['matches'],
                    );
                }
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'pattern' => $pattern,
            'path' => str_replace(ABSPATH, '', str_replace('\\', '/', $resolved_path)),
            'output_mode' => $output_mode,
            'files_searched' => count($files),
            'files_with_matches' => $file_count,
            'total_matches' => $match_count,
            'truncated' => $truncated,
            'results' => $results,
        ), 200);
    }
    
    /**
     * Get searchable files in directory
     */
    private function get_searchable_files($directory, $glob = null) {
        $files = array();
        
        // Default file patterns to search
        $default_patterns = array('*.php', '*.js', '*.css', '*.html', '*.htm', '*.txt', '*.json', '*.xml', '*.md', '*.yml', '*.yaml');
        
        // Directories to skip
        $skip_dirs = array('node_modules', 'vendor', '.git', '.svn', 'cache', 'tmp', 'uploads');
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    RecursiveDirectoryIterator::SKIP_DOTS
                ),
                function ($current, $key, $iterator) use ($skip_dirs) {
                    // Skip certain directories
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $skip_dirs);
                    }
                    return true;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $filename = $file->getFilename();
            
            // Skip hidden files
            if (strpos($filename, '.') === 0) {
                continue;
            }
            
            // Check glob pattern if provided
            if ($glob) {
                // Prepend **/ if not already a recursive pattern
                if (strpos($glob, '**/') !== 0 && strpos($glob, '/') === false) {
                    $glob_pattern = '**/' . $glob;
                } else {
                    $glob_pattern = $glob;
                }
                
                if (!fnmatch($glob, $filename) && !fnmatch(basename($glob), $filename)) {
                    continue;
                }
            } else {
                // Check default patterns
                $matches_default = false;
                foreach ($default_patterns as $pattern) {
                    if (fnmatch($pattern, $filename)) {
                        $matches_default = true;
                        break;
                    }
                }
                if (!$matches_default) {
                    continue;
                }
            }
            
            // Skip binary files
            $mime = mime_content_type($file->getPathname());
            if (!preg_match('/^text\/|^application\/(json|xml|javascript|x-php|x-httpd-php)/', $mime)) {
                continue;
            }
            
            // Skip large files (> 1MB)
            if ($file->getSize() > 1024 * 1024) {
                continue;
            }
            
            $files[] = $file->getPathname();
        }
        
        return $files;
    }
    
    /**
     * Search within a single file
     */
    private function search_file($file_path, $regex, $context_lines = 0, $output_mode = 'content', $max_results = 500) {
        $matches = array();
        
        $lines = file($file_path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return array('matches' => array());
        }
        
        $total_lines = count($lines);
        
        foreach ($lines as $line_num => $line) {
            if (count($matches) >= $max_results) {
                break;
            }
            
            if (preg_match($regex, $line)) {
                if ($output_mode === 'files_with_matches' || $output_mode === 'count') {
                    $matches[] = array('line' => $line_num + 1);
                } else {
                    $match_data = array(
                        'line_number' => $line_num + 1,
                        'content' => $line,
                    );
                    
                    // Add context lines if requested
                    if ($context_lines > 0) {
                        $context_before = array();
                        $context_after = array();
                        
                        // Before context
                        for ($i = max(0, $line_num - $context_lines); $i < $line_num; $i++) {
                            $context_before[] = array(
                                'line_number' => $i + 1,
                                'content' => $lines[$i],
                            );
                        }
                        
                        // After context
                        for ($i = $line_num + 1; $i <= min($total_lines - 1, $line_num + $context_lines); $i++) {
                            $context_after[] = array(
                                'line_number' => $i + 1,
                                'content' => $lines[$i],
                            );
                        }
                        
                        $match_data['context_before'] = $context_before;
                        $match_data['context_after'] = $context_after;
                    }
                    
                    $matches[] = $match_data;
                }
            }
        }
        
        return array('matches' => $matches);
    }
    
    /**
     * Find files matching glob pattern
     */
    public function glob_search($glob_pattern, $target_directory) {
        $resolved_path = $this->resolve_path($target_directory);
        
        if (is_wp_error($resolved_path)) {
            return $resolved_path;
        }
        
        if (!file_exists($resolved_path)) {
            return new WP_Error(
                'directory_not_found',
                __('The specified directory does not exist.', 'banildtools'),
                array('status' => 404, 'path' => $resolved_path)
            );
        }
        
        if (!is_dir($resolved_path)) {
            return new WP_Error(
                'not_a_directory',
                __('The specified path is not a directory.', 'banildtools'),
                array('status' => 400, 'path' => $resolved_path)
            );
        }
        
        // Directories to skip
        $skip_dirs = array('node_modules', 'vendor', '.git', '.svn', 'cache', 'tmp');
        
        $files = array();
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $resolved_path,
                    RecursiveDirectoryIterator::SKIP_DOTS
                ),
                function ($current, $key, $iterator) use ($skip_dirs) {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $skip_dirs);
                    }
                    return true;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        // Normalize glob pattern
        $base_pattern = basename($glob_pattern);
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            
            $filename = $file->getFilename();
            
            // Skip hidden files
            if (strpos($filename, '.') === 0) {
                continue;
            }
            
            // Check if matches glob pattern
            if (fnmatch($base_pattern, $filename) || fnmatch($glob_pattern, $filename)) {
                $relative_path = str_replace(ABSPATH, '', str_replace('\\', '/', $file->getPathname()));
                
                $files[] = array(
                    'path' => $relative_path,
                    'name' => $filename,
                    'size' => $file->getSize(),
                    'modified' => date('c', $file->getMTime()),
                );
            }
        }
        
        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return new WP_REST_Response(array(
            'success' => true,
            'pattern' => $glob_pattern,
            'directory' => str_replace(ABSPATH, '', str_replace('\\', '/', $resolved_path)),
            'total_files' => count($files),
            'files' => $files,
        ), 200);
    }
}

