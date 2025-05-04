<?php
namespace GitHub_Deployer;

class Deployer {
    private $github_api;
    private $repository_manager;
    
    public function __construct($github_api = null) {
        if ($github_api === null) {
            $settings = get_option('github_deployer_settings', array());
            $token = isset($settings['token']) ? $settings['token'] : '';
            $this->github_api = new GitHub_API($token);
        } else {
            $this->github_api = $github_api;
        }
        
        $this->repository_manager = new Repository_Manager();
        
        add_action('admin_post_github_deployer_deploy', array($this, 'handle_deployment'));
    }
    
    /**
     * Deploy a repository by ID or by parameters
     *
     * @param mixed $repo_id_or_owner Repository ID or owner string
     * @param string|null $repo Repository name (if first param is owner)
     * @param string|null $ref Branch or tag reference
     * @param string $type Plugin or theme type
     * @param bool $update_existing Whether to update existing installation
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function deploy($repo_id_or_owner, $repo = null, $ref = null, $type = 'plugin', $update_existing = false) {
        // Check if first parameter is a repository ID
        if (is_numeric($repo_id_or_owner) && $repo === null) {
            return $this->deploy_repository($repo_id_or_owner);
        }
        
        // Otherwise, use the traditional parameters
        $owner = $repo_id_or_owner;
        
        // Validate type
        if (!in_array($type, array('plugin', 'theme'))) {
            return new \WP_Error('invalid_type', __('Invalid deployment type', 'github-deployer'));
        }
        
        // Get repository info
        $repo_info = $this->github_api->get_repo($owner, $repo);
        if (is_wp_error($repo_info)) {
            return $repo_info;
        }
        
        // Get download URL
        $download_url = $this->github_api->get_zip_url($owner, $repo, $ref);
        
        // Handle private repository downloads
        if (strpos($download_url, 'github-api://') === 0) {
            // Extract repository information from the URL
            $repo_parts = str_replace('github-api://', '', $download_url);
            list($owner, $repo, $ref) = explode('/', $repo_parts, 3);
            
            // Get authenticated download URL for private repositories
            $auth_download_url = $this->github_api->get_archive_download_url($owner, $repo, $ref);
            
            if (is_wp_error($auth_download_url)) {
                // Include the original API error message for better debugging
                $original_error_message = '(' . $auth_download_url->get_error_code() . ') ' . $auth_download_url->get_error_message();
                return new \WP_Error(
                    'private_repo_access_error',
                    sprintf(
                        __('Failed to access private repository. Please check your GitHub token permissions. [%s]', 'github-deployer'),
                        $original_error_message
                    ),
                    $auth_download_url // Pass original error object as data
                );
            }
            
            $download_url = $auth_download_url;
        }
        
        // Download the package
        $tmp_file = download_url($download_url);
        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }
        
        // Extract the package
        $extract_to = $type === 'plugin' ? WP_PLUGIN_DIR : get_theme_root();
        
        // Check if a destination with the same name already exists
        $final_dir = $repo;
        $final_path = trailingslashit($extract_to) . $final_dir;
        $already_exists = file_exists($final_path);
        
        // If updating existing installation, perform backup first
        if ($already_exists && $update_existing) {
            $backup_result = $this->backup_existing($final_path);
            if (is_wp_error($backup_result)) {
                // Clean up
                @unlink($tmp_file);
                return $backup_result;
            }
        } elseif ($already_exists && !$update_existing) {
            // Clean up
            @unlink($tmp_file);
            return new \WP_Error(
                'already_exists', 
                sprintf(
                    __('%s already exists. Use update mode to overwrite it.', 'github-deployer'),
                    $type === 'plugin' ? __('Plugin', 'github-deployer') : __('Theme', 'github-deployer')
                )
            );
        }
        
        $result = $this->extract_zip($tmp_file, $extract_to, $repo);
        
        // Clean up
        @unlink($tmp_file);
        
        // Check for incompatible archive error
        if (is_wp_error($result) && $result->get_error_code() === 'incompatible_archive') {
            // Add more helpful information to the error message
            $error_message = $result->get_error_message();
            $error_message .= ' ' . sprintf(
                __('Repository: %s/%s (Branch/Tag: %s). Make sure the repository follows WordPress %s structure guidelines.', 'github-deployer'),
                esc_html($owner),
                esc_html($repo),
                esc_html($ref),
                $type === 'plugin' ? __('plugin', 'github-deployer') : __('theme', 'github-deployer')
            );
            
            // Add reference links based on type
            if ($type === 'plugin') {
                $error_message .= ' ' . sprintf(
                    __('See %s for plugin structure requirements.', 'github-deployer'),
                    '<a href="https://developer.wordpress.org/plugins/plugin-basics/header-requirements/" target="_blank">WordPress.org plugin requirements</a>'
                );
            } else {
                $error_message .= ' ' . sprintf(
                    __('See %s for theme structure requirements.', 'github-deployer'),
                    '<a href="https://developer.wordpress.org/themes/basics/organizing-theme-files/" target="_blank">WordPress.org theme requirements</a>'
                );
            }
            
            return new \WP_Error('incompatible_archive', $error_message);
        }
        
        // If it was an update, fire appropriate actions
        if ($update_existing && !is_wp_error($result)) {
            if ($type === 'plugin') {
                do_action('github_deployer_plugin_updated', $repo, $owner, $ref);
            } else {
                do_action('github_deployer_theme_updated', $repo, $owner, $ref);
            }
        }
        
        // Store the latest commit SHA for this repository
        $latest_commit = $this->github_api->get_latest_commit($owner, $repo, $ref);
        if (!is_wp_error($latest_commit) && isset($latest_commit['sha'])) {
            update_option('github_deployer_last_commit_' . $repo, $latest_commit['sha']);
        }
        
        // Log the successful deployment
        error_log('GitHub Deployer: Successfully deployed ' . $repo . ' (' . $ref . ')');
        
        return $result;
    }
    
    private function extract_zip($zip_file, $destination, $repo_name) {
        // Log entry point
        error_log("GitHub Deployer extract_zip: Starting extraction for {$repo_name} to {$destination}");

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        // Check if WP_Filesystem initialized correctly
        if ( ! $wp_filesystem ) {
            error_log("GitHub Deployer extract_zip: WP_Filesystem failed to initialize.");
            return new \WP_Error('filesystem_error', __('Could not initialize WordPress filesystem.', 'github-deployer'));
        }

        $unzip_result = unzip_file($zip_file, $destination);
        
        // Log unzip result
        if (is_wp_error($unzip_result)) {
            error_log("GitHub Deployer extract_zip: unzip_file failed for {$repo_name}. Error: " . $unzip_result->get_error_message());
            return $unzip_result;
        } else {
             error_log("GitHub Deployer extract_zip: unzip_file completed for {$repo_name}. Result: " . print_r($unzip_result, true));
        }
        
        // GitHub zip files have a root directory with the ref in the name
        // We need to find that directory and rename it to the proper plugin/theme name
        $temp_dir = null;
        
        // Ensure destination exists and is readable before listing
        if (!$wp_filesystem->exists($destination) || !$wp_filesystem->is_readable($destination)) {
             error_log("GitHub Deployer extract_zip: Destination directory {$destination} does not exist or is not readable after unzip.");
             return new \WP_Error('extraction_failed', __('Extraction destination directory not found or not readable after unzip.', 'github-deployer'));
        }
        
        $files = $wp_filesystem->dirlist($destination);
        
        // Log the files found after extraction
        error_log("GitHub Deployer extract_zip: Files found in {$destination} after unzip for {$repo_name}: " . print_r($files, true));
        
        if ( ! $files ) {
            error_log("GitHub Deployer extract_zip: dirlist returned false or empty for {$destination}. Possible permission issue?");
        } else {
            foreach ($files as $file) {
                // Check if it's a directory and starts with the repo name
                if (isset($file['type']) && $file['type'] === 'd' && isset($file['name']) && strpos($file['name'], $repo_name) === 0) {
                    $temp_dir = $file['name'];
                    error_log("GitHub Deployer extract_zip: Found potential temp directory: {$temp_dir}");
                    break;
                }
            }
        }
        
        if (!$temp_dir) {
            error_log("GitHub Deployer extract_zip: Failed to find temp directory starting with '{$repo_name}' in {$destination}");
            return new \WP_Error('extraction_failed', __('Could not locate extracted files. The archive might have an unexpected structure.', 'github-deployer'));
        }
        
        // Log found temp directory
        error_log("GitHub Deployer extract_zip: Using temp directory: {$temp_dir}");

        // Validate the repository structure for WordPress plugin/theme
        $extracted_path = trailingslashit($destination) . $temp_dir;
        $is_valid = $this->validate_repository_structure($extracted_path);
        
        if (is_wp_error($is_valid)) {
            error_log("GitHub Deployer extract_zip: Invalid repository structure for {$extracted_path}. Error: " . $is_valid->get_error_message());
            // Clean up the extracted directory
            $wp_filesystem->delete($extracted_path, true);
            return $is_valid;
        }
        
        $final_dir = $repo_name;
        $final_path = trailingslashit($destination) . $final_dir;
        
        // Log paths before moving
        error_log("GitHub Deployer extract_zip: Moving from {$extracted_path} to {$final_path}");

        // If destination exists, remove it first
        if ($wp_filesystem->exists($final_path)) {
             error_log("GitHub Deployer extract_zip: Removing existing directory at {$final_path}");
            if (!$wp_filesystem->delete($final_path, true)) {
                 error_log("GitHub Deployer extract_zip: Failed to remove existing directory at {$final_path}");
                 return new \WP_Error('delete_failed', __('Could not remove existing directory before update.', 'github-deployer'));
            }
        }
        
        // Rename the directory
        $rename_result = $wp_filesystem->move(
            $extracted_path, // Source
            $final_path // Destination
        );
        
        if (!$rename_result) {
             error_log("GitHub Deployer extract_zip: Failed to move {$extracted_path} to {$final_path}");
            return new \WP_Error('rename_failed', __('Failed to rename extracted directory', 'github-deployer'));
        }

        error_log("GitHub Deployer extract_zip: Successfully extracted and moved {$repo_name}.");
        return true;
    }
    
    /**
     * Validate the repository structure to ensure it follows WordPress plugin/theme guidelines
     *
     * @param string $directory Path to the extracted directory
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_repository_structure($directory) {
        global $wp_filesystem;
        
        // For plugins: Check if there's at least one PHP file with a plugin header
        $plugin_files = $wp_filesystem->dirlist($directory, false, '*.php');
        $has_plugin_header = false;
        
        // Check if it has a style.css for themes
        $has_theme_style = $wp_filesystem->exists($directory . '/style.css');
        
        // For themes, also check for templates dir in block themes
        $has_templates_dir = $wp_filesystem->exists($directory . '/templates') && $wp_filesystem->is_dir($directory . '/templates');
        
        if (empty($plugin_files) && !$has_theme_style) {
            return new \WP_Error('incompatible_archive', __('The repository does not appear to be a valid WordPress plugin or theme. No PHP files or style.css found.', 'github-deployer'));
        }
        
        // If we have PHP files, check for a plugin header in at least one
        if (!empty($plugin_files)) {
            foreach ($plugin_files as $file) {
                $file_path = $directory . '/' . $file['name'];
                $file_content = $wp_filesystem->get_contents($file_path);
                
                // Check for plugin header
                if ($file_content && strpos($file_content, 'Plugin Name:') !== false) {
                    $has_plugin_header = true;
                    break;
                }
            }
        }
        
        // If it has a style.css file, check if it contains theme header
        $has_theme_header = false;
        if ($has_theme_style) {
            $style_content = $wp_filesystem->get_contents($directory . '/style.css');
            if ($style_content && strpos($style_content, 'Theme Name:') !== false) {
                $has_theme_header = true;
            }
        }
        
        // For block themes, check if index.html exists
        $has_index_html = $wp_filesystem->exists($directory . '/index.html');
        
        // If neither a valid plugin nor a valid theme, return error
        if (!$has_plugin_header && !$has_theme_header && !($has_templates_dir && $has_index_html)) {
            return new \WP_Error('incompatible_archive', __('The repository does not have the required WordPress plugin or theme structure. Please ensure it has a proper plugin header in a PHP file, or a theme header in style.css.', 'github-deployer'));
        }
        
        return true;
    }
    
    /**
     * Backup an existing plugin or theme directory
     * 
     * @param string $path Path to the directory to backup
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function backup_existing($path) {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        WP_Filesystem();
        global $wp_filesystem;
        
        // Create backups directory if it doesn't exist
        $backup_dir = WP_CONTENT_DIR . '/github-deployer-backups';
        if (!$wp_filesystem->exists($backup_dir)) {
            if (!$wp_filesystem->mkdir($backup_dir)) {
                return new \WP_Error('backup_failed', __('Could not create backups directory', 'github-deployer'));
            }
        }
        
        // Generate backup filename
        $dirname = basename($path);
        $backup_file = $backup_dir . '/' . $dirname . '-' . date('Y-m-d-H-i-s') . '.zip';
        
        // Create backup zip
        if (!class_exists('ZipArchive')) {
            return new \WP_Error('backup_failed', __('ZipArchive extension is not available', 'github-deployer'));
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($backup_file, \ZipArchive::CREATE) !== true) {
            return new \WP_Error('backup_failed', __('Could not create backup archive', 'github-deployer'));
        }
        
        // Add files to zip
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $base_path_length = strlen($path) + 1;
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, $base_path_length);
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        return true;
    }
    
    /**
     * Handle deployment form submission
     */
    public function handle_deployment() {
        // Check for nonce
        if (!isset($_POST['github_deployer_deploy_nonce']) || 
            !wp_verify_nonce($_POST['github_deployer_deploy_nonce'], 'github_deployer_deploy')) {
            wp_die(__('Security check failed', 'github-deployer'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action', 'github-deployer'));
        }
        
        // Handle both repository ID and direct parameters
        if (isset($_POST['repo_id']) && !empty($_POST['repo_id'])) {
            // Repository ID mode (for auto-updater)
            $repo_id = absint($_POST['repo_id']);
        
            $result = $this->deploy($repo_id);
        
        if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array(
                    'page' => 'github-deployer',
                    'error' => 'deployment_failed',
                    'message' => urlencode($result->get_error_message())
                ), admin_url('admin.php')));
                exit;
            }
            
            // Handle auto-update option
            $enable_auto_update = isset($_POST['enable_auto_update']) && $_POST['enable_auto_update'] === '1';
            
            if ($enable_auto_update) {
                // Get the Auto_Updater instance from the Plugin
                $plugin = Plugin::get_instance();
                $auto_updater = $plugin->get_auto_updater();
                
                // Enable auto-update for this repository
                $auto_updater->enable_auto_update($repo_id);
            }
            
            // Redirect to the main page with a success message
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'deployed' => '1'
            ), admin_url('admin.php')));
            exit;
        } else {
            // Traditional mode (direct owner/repo/ref parameters)
            // Get and sanitize form data
            $owner = isset($_POST['owner']) ? sanitize_text_field($_POST['owner']) : '';
            $repo = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';
            $ref = isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : 'main';
            $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'plugin';
            $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
            $enable_auto_update = isset($_POST['enable_auto_update']) && $_POST['enable_auto_update'] === '1';
            
            if (empty($owner) || empty($repo)) {
                wp_redirect(add_query_arg(array(
                    'page' => 'github-deployer',
                    'error' => __('Repository owner and name are required.', 'github-deployer')
                ), admin_url('admin.php')));
                exit;
            }
            
            // Deploy the repository
            $result = $this->deploy($owner, $repo, $ref, $type, $update_existing);
            
            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array(
                    'page' => 'github-deployer',
                    'error' => urlencode($result->get_error_message())
                ), admin_url('admin.php')));
                exit;
            }
            
            // If auto-update is requested, register the repository for auto-updates
            if ($enable_auto_update) {
                // First, add the repository to the database if not already there
                $repositories = new Repositories();
                $target_dir = $repo; // Use repository name as target directory
                
                // Check if repository already exists
                $existing_repos = $repositories->get_repositories();
                $repo_exists = false;
                $repo_id = 0;
                
                foreach ($existing_repos as $existing_repo) {
                    if ($existing_repo->owner === $owner && $existing_repo->name === $repo) {
                        $repo_exists = true;
                        $repo_id = $existing_repo->id;
                        break;
                    }
                }
                
                if (!$repo_exists) {
                    // Add new repository
                    $repo_id = $repositories->add_repository($owner, $repo, $ref, $type, $target_dir, true);
                } else {
                    // Update existing repository
                    $repositories->update_repository($repo_id, array(
                        'branch' => $ref,
                        'auto_update' => 1
                    ));
                }
                
                // Store the latest commit SHA for this repository
                $latest_commit = $this->github_api->get_latest_commit($owner, $repo, $ref);
                if (!is_wp_error($latest_commit) && isset($latest_commit['sha'])) {
                    update_option('github_deployer_last_commit_' . $repo_id, $latest_commit['sha']);
                }
                
                $auto_update_enabled = true;
            } else {
                $auto_update_enabled = false;
            }
            
            // Redirect with success message
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'tab' => 'deploy',
                'success' => '1',
                'repo' => $repo,
                'type' => $type,
                'updated' => $update_existing ? '1' : '0',
                'auto_update_enabled' => $auto_update_enabled ? '1' : '0'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Deploy a repository by ID
     *
     * @param string $repo_id Repository ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function deploy_repository($repo_id) {
        // Get repository data from the database
        $repo_db = new Repositories();
        $repository = $repo_db->get_repository($repo_id);
        
        if (!$repository) {
            return new \WP_Error('repository_not_found', __('Repository not found in database', 'github-deployer'));
        }
        
        // Extract repository information from the database object
        $owner = $repository->owner;
        $repo = $repository->name;
        $branch = !empty($repository->branch) ? $repository->branch : 'main';
        $type = !empty($repository->type) ? $repository->type : 'plugin';
        
        // Deploy the repository
        $result = $this->deploy($owner, $repo, $branch, $type, true); // Always update existing for auto-updates
        
        if (!is_wp_error($result)) {
            // Store the latest commit SHA for this repository
            $latest_commit = $this->github_api->get_latest_commit($owner, $repo, $branch);
            if (!is_wp_error($latest_commit) && isset($latest_commit['sha'])) {
                update_option('github_deployer_last_commit_' . $repo_id, $latest_commit['sha']);
            }
            
            // Log the successful deployment
            error_log('GitHub Deployer: Successfully deployed ' . $owner . '/' . $repo . ' (' . $branch . ')');
            
            // Update last updated timestamp in database
            $repo_db->update_repository($repo_id, array(
                'last_checked' => current_time('mysql'),
                'last_updated' => current_time('mysql')
            ));
        } else {
            // Log deployment error
            error_log('GitHub Deployer: Failed to deploy ' . $owner . '/' . $repo . ': ' . $result->get_error_message());
        }
        
        return $result;
    }
} 