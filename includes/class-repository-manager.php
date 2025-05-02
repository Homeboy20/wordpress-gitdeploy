<?php
namespace GitHub_Deployer;

/**
 * Repository Manager class
 * 
 * Manages GitHub repositories, their connection status and metadata
 */
class Repository_Manager {
    private $github_api;
    
    public function __construct() {
        $settings = get_option('github_deployer_settings', array());
        $token = isset($settings['token']) ? $settings['token'] : '';
        $this->github_api = new GitHub_API($token);
        
        // Register hooks
        add_action('admin_post_github_deployer_connect_repo', array($this, 'handle_connect_repo'));
        add_action('admin_post_github_deployer_disconnect_repo', array($this, 'handle_disconnect_repo'));
        add_action('admin_post_github_deployer_refresh_status', array($this, 'handle_refresh_status'));
        add_action('admin_post_github_deployer_enable_auto_update', array($this, 'handle_enable_auto_update'));
        add_action('admin_post_github_deployer_disable_auto_update', array($this, 'handle_disable_auto_update'));
    }
    
    /**
     * Get all deployed repositories and their connection status
     * 
     * @return array List of deployed repositories with status
     */
    public function get_deployed_repositories() {
        // First, sync repositories to ensure consistency
        $this->sync_repositories();
        
        // Now get the synced data
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        $tracked_repos = get_option('github_deployer_tracked_repos', array());
        
        // Merge tracking status into deployed repos
        foreach ($deployed_repos as $key => $repo) {
            $deployed_repos[$key]['is_tracked'] = false;
            
            foreach ($tracked_repos as $tracked_repo) {
                if ($tracked_repo['owner'] === $repo['owner'] && $tracked_repo['repo'] === $repo['repo']) {
                    $deployed_repos[$key]['is_tracked'] = true;
                    $deployed_repos[$key]['last_updated'] = $tracked_repo['last_updated'];
                    break;
                }
            }
        }
        
        return $deployed_repos;
    }
    
    /**
     * Get repositories from GitHub
     * 
     * @param string $type Type of repositories to fetch (user, org, search)
     * @param string $query Query string for search or username for user/org repos
     * @param int $page Page number
     * @return array|WP_Error List of repositories or error
     */
    public function get_github_repositories($type = 'user', $query = '', $page = 1) {
        switch ($type) {
            case 'user':
                return $this->github_api->get_user_repos($page);
            case 'public':
                return $this->github_api->get_user_public_repos($query, $page);
            case 'org':
                return $this->github_api->get_org_repos($query, $page);
            case 'search':
                return $this->github_api->search_repos($query, $page);
            default:
                return new \WP_Error('invalid_type', __('Invalid repository type', 'github-deployer'));
        }
    }
    
    /**
     * Check connection status for all deployed repositories
     * 
     * @return array Updated list of deployed repositories
     */
    public function check_all_connection_status() {
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $key => $repo) {
            $status = $this->github_api->check_connection_status($repo['owner'], $repo['repo']);
            $deployed_repos[$key]['connected'] = !is_wp_error($status) && $status === true;
            $deployed_repos[$key]['last_checked'] = time();
            
            if (is_wp_error($status)) {
                $deployed_repos[$key]['error'] = $status->get_error_message();
            } else {
                unset($deployed_repos[$key]['error']);
            }
        }
        
        update_option('github_deployer_deployed_repos', $deployed_repos);
        
        return $deployed_repos;
    }
    
    /**
     * Register a repository as deployed
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit
     * @param string $type Plugin or theme
     * @return bool Success status
     */
    public function register_deployed_repository($owner, $repo, $ref, $type) {
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        // Check if already deployed
        foreach ($deployed_repos as $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                return false; // Already deployed
            }
        }
        
        // Add repository to deployed list
        $deployed_repos[] = array(
            'owner' => $owner,
            'repo' => $repo,
            'ref' => $ref,
            'type' => $type,
            'deployed_at' => time(),
            'connected' => true,
            'last_checked' => time()
        );
        
        update_option('github_deployer_deployed_repos', $deployed_repos);
        
        return true;
    }
    
    /**
     * Handle repository connection action
     */
    public function handle_connect_repo() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_connect_repo');
        
        $owner = sanitize_text_field($_POST['owner']);
        $repo = sanitize_text_field($_POST['repo']);
        
        // Update in options table
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $key => $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                // Check connection status
                $status = $this->github_api->check_connection_status($owner, $repo);
                $deployed_repos[$key]['connected'] = !is_wp_error($status) && $status === true;
                $deployed_repos[$key]['last_checked'] = time();
                
                if (is_wp_error($status)) {
                    $deployed_repos[$key]['error'] = $status->get_error_message();
                } else {
                    unset($deployed_repos[$key]['error']);
                }
                
                break;
            }
        }
        
        update_option('github_deployer_deployed_repos', $deployed_repos);
        
        // Make sure database is also updated
        $this->sync_repositories();
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'status_refreshed' => '1'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle repository disconnection action
     */
    public function handle_disconnect_repo() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_disconnect_repo');
        
        $owner = sanitize_text_field($_POST['owner']);
        $repo = sanitize_text_field($_POST['repo']);
        
        // Update in options table
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $key => $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                $deployed_repos[$key]['connected'] = false;
                $deployed_repos[$key]['last_checked'] = time();
                break;
            }
        }
        
        update_option('github_deployer_deployed_repos', $deployed_repos);
        
        // Also update database table
        // Find repository in database
        $db_repos = new Repositories();
        $db_results = $db_repos->get_repositories();
        
        foreach ($db_results as $db_repo) {
            if ($db_repo->owner === $owner && $db_repo->name === $repo) {
                // Found the repo, update its auto_update status to off
                $db_repos->update_repository($db_repo->id, array(
                    'auto_update' => 0
                ));
                break;
            }
        }
        
        // Make sure everything is in sync
        $this->sync_repositories();
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'status_refreshed' => '1'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle repository refresh status action
     */
    public function handle_refresh_status() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_refresh_status');
        
        $this->check_all_connection_status();
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'status_refreshed' => '1'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle enabling auto-update for a repository
     */
    public function handle_enable_auto_update() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_enable_auto_update');
        
        $owner = sanitize_text_field($_POST['owner']);
        $repo = sanitize_text_field($_POST['repo']);
        $ref = isset($_POST['ref']) ? sanitize_text_field($_POST['ref']) : 'main';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'plugin';
        
        // Update in options table
        $tracked_repos = get_option('github_deployer_tracked_repos', array());
        $already_tracked = false;
        
        // Check if already tracked
        foreach ($tracked_repos as $key => $tracked_repo) {
            if ($tracked_repo['owner'] === $owner && $tracked_repo['repo'] === $repo) {
                $tracked_repos[$key]['last_updated'] = time();
                $already_tracked = true;
                break;
            }
        }
        
        // Add to tracked repos if not already tracked
        if (!$already_tracked) {
            $tracked_repos[] = array(
                'owner' => $owner,
                'repo' => $repo,
                'ref' => $ref,
                'type' => $type,
                'last_updated' => time()
            );
        }
        
        update_option('github_deployer_tracked_repos', $tracked_repos);
        
        // Update database table
        $db_repos = new Repositories();
        $db_results = $db_repos->get_repositories();
        $repo_id = 0;
        $exists = false;
        
        foreach ($db_results as $db_repo) {
            if ($db_repo->owner === $owner && $db_repo->name === $repo) {
                $repo_id = $db_repo->id;
                $exists = true;
                break;
            }
        }
        
        if ($exists) {
            // Update existing repo
            $db_repos->update_repository($repo_id, array(
                'auto_update' => 1
            ));
        } else {
            // Add new repo
            $repo_id = $db_repos->add_repository(
                $owner,
                $repo,
                $ref,
                $type,
                $repo, // target_dir same as repo name
                true // auto_update enabled
            );
        }
        
        // Make sure everything is in sync
        $this->sync_repositories();
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'auto_update' => 'enabled',
            'repo' => $repo
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Handle disabling auto-update for a repository
     */
    public function handle_disable_auto_update() {
        if (!current_user_can('install_plugins') || !current_user_can('install_themes')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'github-deployer'));
        }
        
        check_admin_referer('github_deployer_disable_auto_update');
        
        $owner = sanitize_text_field($_POST['owner']);
        $repo = sanitize_text_field($_POST['repo']);
        
        // Update in options table
        $tracked_repos = get_option('github_deployer_tracked_repos', array());
        
        foreach ($tracked_repos as $key => $tracked_repo) {
            if ($tracked_repo['owner'] === $owner && $tracked_repo['repo'] === $repo) {
                // Remove from tracked repos
                unset($tracked_repos[$key]);
                break;
            }
        }
        
        // Re-index array after removal
        $tracked_repos = array_values($tracked_repos);
        update_option('github_deployer_tracked_repos', $tracked_repos);
        
        // Update database table
        $db_repos = new Repositories();
        $db_results = $db_repos->get_repositories();
        
        foreach ($db_results as $db_repo) {
            if ($db_repo->owner === $owner && $db_repo->name === $repo) {
                // Found the repo, update its auto_update status
                $db_repos->update_repository($db_repo->id, array(
                    'auto_update' => 0
                ));
                break;
            }
        }
        
        // Make sure everything is in sync
        $this->sync_repositories();
        
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'repositories',
            'auto_update' => 'disabled',
            'repo' => $repo
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Synchronize repositories between options table and database
     * 
     * This ensures consistent data between old options storage and
     * newer database table storage.
     * 
     * @return array Updated repositories
     */
    public function sync_repositories() {
        // Get repositories from both sources
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        $tracked_repos = get_option('github_deployer_tracked_repos', array());
        
        // Get repositories from database
        $db_repos = new Repositories();
        $database_repos = $db_repos->get_repositories();
        
        // First update database from options
        foreach ($deployed_repos as $deployed_repo) {
            $exists = false;
            $repo_id = 0;
            
            // Check if already in database
            foreach ($database_repos as $db_repo) {
                if ($db_repo->owner === $deployed_repo['owner'] && $db_repo->name === $deployed_repo['repo']) {
                    $exists = true;
                    $repo_id = $db_repo->id;
                    break;
                }
            }
            
            // Set auto_update status
            $auto_update = false;
            foreach ($tracked_repos as $tracked_repo) {
                if ($tracked_repo['owner'] === $deployed_repo['owner'] && $tracked_repo['repo'] === $deployed_repo['repo']) {
                    $auto_update = true;
                    break;
                }
            }
            
            // Add to database if not exists
            if (!$exists) {
                $branch = isset($deployed_repo['ref']) ? $deployed_repo['ref'] : 'main';
                $type = isset($deployed_repo['type']) ? $deployed_repo['type'] : 'plugin';
                $target_dir = $deployed_repo['repo'];
                
                $db_repos->add_repository(
                    $deployed_repo['owner'],
                    $deployed_repo['repo'],
                    $branch,
                    $type,
                    $target_dir,
                    $auto_update
                );
            } else {
                // Update existing
                $db_repos->update_repository($repo_id, array(
                    'auto_update' => $auto_update ? 1 : 0
                ));
            }
        }
        
        // Now update options based on database
        $updated_database_repos = $db_repos->get_repositories();
        $new_deployed_repos = array();
        $new_tracked_repos = array();
        
        foreach ($updated_database_repos as $db_repo) {
            // Add to deployed repos
            $new_deployed_repos[] = array(
                'owner' => $db_repo->owner,
                'repo' => $db_repo->name,
                'ref' => $db_repo->branch,
                'type' => $db_repo->type,
                'deployed_at' => strtotime($db_repo->created_at),
                'connected' => $this->check_repo_connection($db_repo->owner, $db_repo->name),
                'last_checked' => time()
            );
            
            // Add to tracked repos if auto_update is enabled
            if ($db_repo->auto_update) {
                $new_tracked_repos[] = array(
                    'owner' => $db_repo->owner,
                    'repo' => $db_repo->name,
                    'ref' => $db_repo->branch,
                    'type' => $db_repo->type,
                    'last_updated' => strtotime($db_repo->last_updated)
                );
            }
        }
        
        // Update options
        update_option('github_deployer_deployed_repos', $new_deployed_repos);
        update_option('github_deployer_tracked_repos', $new_tracked_repos);
        
        return $new_deployed_repos;
    }
    
    /**
     * Check repository connection status
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return bool Connection status
     */
    private function check_repo_connection($owner, $repo) {
        $status = $this->github_api->check_connection_status($owner, $repo);
        return (!is_wp_error($status) && $status === true);
    }
    
    /**
     * Connect to a specific GitHub repository URL
     * 
     * @param string $repo_url GitHub repository URL
     * @return array|WP_Error Connection result or error
     */
    public function connect_to_specific_repository($repo_url) {
        // Parse the GitHub URL to extract owner and repo name
        if (preg_match('#https?://github\.com/([^/]+)/([^/]+)/?.*#', $repo_url, $matches)) {
            $owner = $matches[1];
            $repo = $matches[2];
            
            // Remove .git suffix if present
            $repo = preg_replace('/\.git$/', '', $repo);
            
            // Check if repository exists and is accessible
            $repo_info = $this->github_api->get_repo($owner, $repo);
            
            if (is_wp_error($repo_info)) {
                return $repo_info;
            }
            
            // Get default branch from repo info
            $default_branch = isset($repo_info->default_branch) ? $repo_info->default_branch : 'main';
            
            // Determine repository type based on contents
            $type = $this->determine_repository_type($owner, $repo);
            
            // Register as deployed repository
            $this->register_deployed_repository($owner, $repo, $default_branch, $type);
            
            // Sync repositories to ensure consistency
            $this->sync_repositories();
            
            return array(
                'owner' => $owner,
                'repo' => $repo,
                'branch' => $default_branch,
                'type' => $type,
                'connected' => true
            );
        }
        
        return new \WP_Error(
            'invalid_github_url',
            __('Invalid GitHub repository URL. Please provide a URL in the format: https://github.com/username/repository', 'github-deployer')
        );
    }
    
    /**
     * Determine if a repository is a plugin or theme
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return string 'plugin' or 'theme'
     */
    private function determine_repository_type($owner, $repo) {
        // Check for style.css at the root (indicates a theme)
        $style_file = $this->github_api->get_file_contents($owner, $repo, 'style.css');
        if (!is_wp_error($style_file) && isset($style_file->decoded_content)) {
            // Look for Theme Name: in the file
            if (strpos($style_file->decoded_content, 'Theme Name:') !== false) {
                return 'theme';
            }
        }
        
        // Check for main plugin file (indicates a plugin)
        $plugin_file = $this->github_api->get_file_contents($owner, $repo, $repo . '.php');
        if (!is_wp_error($plugin_file) && isset($plugin_file->decoded_content)) {
            // Look for Plugin Name: in the file
            if (strpos($plugin_file->decoded_content, 'Plugin Name:') !== false) {
                return 'plugin';
            }
        }
        
        // Default to plugin if we can't determine
        return 'plugin';
    }
    
    /**
     * Deploy a connected repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name
     * @param string $type Repository type (plugin or theme)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function deploy_connected_repository($owner, $repo, $branch, $type) {
        // Get the plugin instance
        $plugin = \GitHub_Deployer\Plugin::get_instance();
        $deployer = $plugin->get_deployer();
        
        // Use the Deployer to deploy the repository
        $result = $deployer->deploy($owner, $repo, $branch, $type);
        
        if (\is_wp_error($result)) {
            return $result;
        }
        
        // Update the repository in the database
        $db_repos = new \GitHub_Deployer\Repositories();
        $db_results = $db_repos->get_repositories();
        $repo_id = 0;
        
        foreach ($db_results as $db_repo) {
            if ($db_repo->owner === $owner && $db_repo->name === $repo) {
                $repo_id = $db_repo->id;
                break;
            }
        }
        
        if ($repo_id > 0) {
            // Update last_deployed timestamp
            $db_repos->update_repository($repo_id, array(
                'last_deployed' => current_time('mysql')
            ));
        }
        
        return true;
    }
} 