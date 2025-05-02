<?php
namespace GitHub_Deployer;

/**
 * Backup Manager class
 * 
 * Handles backups and rollbacks for deployed repositories
 */
class Backup_Manager {
    private $backup_dir;
    
    public function __construct() {
        // Set up backup directory in wp-content/github-deployer-backups
        $this->backup_dir = WP_CONTENT_DIR . '/github-deployer-backups';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            
            // Create .htaccess file to prevent direct access
            $htaccess_file = $this->backup_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "Deny from all\n");
            }
            
            // Create index.php file for security
            $index_file = $this->backup_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.");
            }
        }
        
        // Register hooks
        add_action('github_deployer_before_deploy', array($this, 'create_backup'), 10, 3);
        add_action('admin_post_github_deployer_rollback', array($this, 'handle_rollback'));
    }
    
    /**
     * Get available backups for a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $type Deployment type (plugin or theme)
     * @return array List of available backups
     */
    public function get_backups($owner, $repo, $type) {
        $backups = array();
        $backup_subdir = sanitize_file_name($owner . '-' . $repo);
        $backup_path = $this->backup_dir . '/' . $backup_subdir;
        
        if (!file_exists($backup_path)) {
            return $backups;
        }
        
        $files = glob($backup_path . '/*.zip');
        
        foreach ($files as $file) {
            $filename = basename($file);
            // Extract timestamp from filename (format: backup-{timestamp}.zip)
            if (preg_match('/backup-(\d+)\.zip/', $filename, $matches)) {
                $timestamp = $matches[1];
                $backups[] = array(
                    'file' => $filename,
                    'path' => $file,
                    'timestamp' => $timestamp,
                    'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                    'size' => size_format(filesize($file))
                );
            }
        }
        
        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Create a backup before deployment
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $type Deployment type (plugin or theme)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function create_backup($owner, $repo, $ref, $type) {
        // Get the directory to backup
        $dir_to_backup = $this->get_directory_to_backup($owner, $repo, $type);
        
        if (!file_exists($dir_to_backup)) {
            // Nothing to backup - first deployment
            return true;
        }
        
        // Ensure backup subdirectory exists
        $backup_subdir = sanitize_file_name($owner . '-' . $repo);
        $backup_path = $this->backup_dir . '/' . $backup_subdir;
        
        if (!file_exists($backup_path)) {
            wp_mkdir_p($backup_path);
        }
        
        // Create backup file
        $timestamp = time();
        $backup_file = $backup_path . '/backup-' . $timestamp . '.zip';
        
        // Create ZIP archive
        if (!class_exists('ZipArchive')) {
            return new \WP_Error(
                'zip_missing',
                __('ZipArchive PHP extension is required for backups.', 'github-deployer')
            );
        }
        
        $zip = new \ZipArchive();
        
        if ($zip->open($backup_file, \ZipArchive::CREATE) !== true) {
            return new \WP_Error(
                'zip_creation_failed',
                __('Failed to create backup ZIP file.', 'github-deployer')
            );
        }
        
        // Add files to ZIP
        $files = $this->get_files_recursive($dir_to_backup);
        
        foreach ($files as $file) {
            $relative_path = str_replace($dir_to_backup, '', $file);
            $zip->addFile($file, $relative_path);
        }
        
        // Add metadata
        $metadata = array(
            'owner' => $owner,
            'repo' => $repo,
            'ref' => $ref,
            'type' => $type,
            'directory' => $dir_to_backup,
            'timestamp' => $timestamp,
            'wordpress_version' => get_bloginfo('version')
        );
        
        $zip->addFromString('metadata.json', json_encode($metadata));
        
        $zip->close();
        
        // Clean up old backups (keep only last 5)
        $this->cleanup_old_backups($owner, $repo, $type);
        
        return true;
    }
    
    /**
     * Get all files recursively in a directory
     * 
     * @param string $dir Directory to scan
     * @return array Array of file paths
     */
    private function get_files_recursive($dir) {
        $files = array();
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $dir_iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::LEAVES_ONLY);
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Clean up old backups, keeping only the most recent ones
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $type Deployment type (plugin or theme)
     * @param int $keep_count Number of backups to keep
     */
    private function cleanup_old_backups($owner, $repo, $type, $keep_count = 5) {
        $backups = $this->get_backups($owner, $repo, $type);
        
        if (count($backups) <= $keep_count) {
            return;
        }
        
        // Remove oldest backups
        $to_remove = array_slice($backups, $keep_count);
        
        foreach ($to_remove as $backup) {
            @unlink($backup['path']);
        }
    }
    
    /**
     * Get directory to backup based on repository and type
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $type Deployment type (plugin or theme)
     * @return string Directory path
     */
    private function get_directory_to_backup($owner, $repo, $type) {
        if ($type === 'plugin') {
            return WP_PLUGIN_DIR . '/' . $repo;
        } else {
            return get_theme_root() . '/' . $repo;
        }
    }
    
    /**
     * Perform rollback from a backup
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $backup_file Backup filename
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function rollback($owner, $repo, $backup_file) {
        $backup_subdir = sanitize_file_name($owner . '-' . $repo);
        $backup_path = $this->backup_dir . '/' . $backup_subdir . '/' . $backup_file;
        
        if (!file_exists($backup_path)) {
            return new \WP_Error(
                'backup_not_found',
                __('Backup file not found.', 'github-deployer')
            );
        }
        
        // Extract metadata to determine deployment type and directory
        $zip = new \ZipArchive();
        
        if ($zip->open($backup_path) !== true) {
            return new \WP_Error(
                'zip_open_failed',
                __('Failed to open backup file.', 'github-deployer')
            );
        }
        
        $metadata_content = $zip->getFromName('metadata.json');
        $zip->close();
        
        if (!$metadata_content) {
            return new \WP_Error(
                'metadata_missing',
                __('Backup metadata missing.', 'github-deployer')
            );
        }
        
        $metadata = json_decode($metadata_content, true);
        
        if (!$metadata || !isset($metadata['type']) || !isset($metadata['directory'])) {
            return new \WP_Error(
                'metadata_invalid',
                __('Backup metadata is invalid.', 'github-deployer')
            );
        }
        
        $target_dir = $metadata['directory'];
        
        // Check if target directory exists
        if (!file_exists($target_dir)) {
            return new \WP_Error(
                'target_missing',
                __('Target directory no longer exists.', 'github-deployer')
            );
        }
        
        // First, create a backup of the current state (rollback from rollback support)
        $backup_result = $this->create_backup($owner, $repo, 'rollback', $metadata['type']);
        
        if (is_wp_error($backup_result)) {
            return $backup_result;
        }
        
        // Delete current files
        $this->remove_directory_contents($target_dir);
        
        // Extract backup to target directory
        $zip = new \ZipArchive();
        
        if ($zip->open($backup_path) !== true) {
            return new \WP_Error(
                'zip_open_failed',
                __('Failed to open backup file for extraction.', 'github-deployer')
            );
        }
        
        $zip->extractTo($target_dir);
        $zip->close();
        
        // Update repository status to match backup
        $this->update_repo_status_from_backup($owner, $repo, $metadata);
        
        return true;
    }
    
    /**
     * Remove all contents from a directory while preserving the directory itself
     * 
     * @param string $dir Directory to clean
     */
    private function remove_directory_contents($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->remove_directory_contents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
    
    /**
     * Update repository status after rollback
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param array $metadata Backup metadata
     */
    private function update_repo_status_from_backup($owner, $repo, $metadata) {
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $key => $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                // Update to match backup metadata
                $deployed_repos[$key]['ref'] = isset($metadata['ref']) ? $metadata['ref'] : $deployed_repo['ref'];
                $deployed_repos[$key]['last_updated'] = time();
                $deployed_repos[$key]['rollback_performed'] = true;
                $deployed_repos[$key]['rollback_timestamp'] = time();
                break;
            }
        }
        
        update_option('github_deployer_deployed_repos', $deployed_repos);
    }
    
    /**
     * Handle rollback action from admin
     */
    public function handle_rollback() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_rollback');
        
        $owner = sanitize_text_field($_POST['owner']);
        $repo = sanitize_text_field($_POST['repo']);
        $backup_file = sanitize_text_field($_POST['backup_file']);
        
        $result = $this->rollback($owner, $repo, $backup_file);
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'tab' => 'repositories',
                'rollback_error' => '1',
                'error_message' => urlencode($result->get_error_message())
            ), admin_url('admin.php')));
            exit;
        }
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'rollback_success' => '1',
            'repo' => $repo
        ), admin_url('admin.php')));
        exit;
    }
} 