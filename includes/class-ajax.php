<?php
namespace GitHub_Deployer;

class AJAX {
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_github_deployer_repo_info', array($this, 'get_repo_info'));
        add_action('wp_ajax_github_deployer_check_existing', array($this, 'check_existing'));
        add_action('wp_ajax_github_deployer_check_tracked', array($this, 'check_tracked'));
        add_action('wp_ajax_github_deployer_fetch_repositories', array($this, 'fetch_repositories'));
        add_action('wp_ajax_github_deployer_check_connection', array($this, 'check_connection'));
        add_action('wp_ajax_github_deployer_get_repos', array($this, 'get_repos'));
    }
    
    /**
     * Get repository information
     */
    public function get_repo_info() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $owner = isset($_GET['owner']) ? sanitize_text_field($_GET['owner']) : '';
        $repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error(array('message' => __('Owner and repository name are required.', 'github-deployer')));
        }
        
        // Get repository information
        $repo_info = get_github_repo_info($owner, $repo);
        
        if (is_wp_error($repo_info)) {
            wp_send_json_error(array('message' => format_error_message($repo_info)));
        }
        
        // Get branches and tags
        $branches = get_github_branches($owner, $repo);
        $tags = get_github_tags($owner, $repo);
        
        // Prepare response data
        $data = (array) $repo_info;
        $data['branches'] = is_wp_error($branches) ? array() : $branches;
        $data['tags'] = is_wp_error($tags) ? array() : $tags;
        
        wp_send_json_success($data);
    }
    
    /**
     * Check if a plugin or theme already exists
     */
    public function check_existing() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'plugin';
        
        if (empty($repo)) {
            wp_send_json_error(array('message' => __('Repository name is required.', 'github-deployer')));
        }
        
        $exists = false;
        
        if ($type === 'plugin') {
            // Check if plugin exists
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $plugins = get_plugins();
            foreach ($plugins as $plugin_file => $plugin_data) {
                // Check if plugin directory matches the repo name
                $plugin_dir = dirname($plugin_file);
                if ($plugin_dir === $repo || $plugin_file === $repo . '.php') {
                    $exists = true;
                    break;
                }
            }
        } else {
            // Check if theme exists
            $theme_dir = get_theme_root() . '/' . $repo;
            $exists = file_exists($theme_dir) && is_dir($theme_dir);
        }
        
        wp_send_json_success(array(
            'exists' => $exists,
            'repo' => $repo,
            'type' => $type
        ));
    }

    /**
     * Check if a repository is already tracked for auto-updates
     */
    public function check_tracked() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
        
        if (empty($repo)) {
            wp_send_json_error(array('message' => __('Repository name is required.', 'github-deployer')));
        }
        
        // Check if repo is already tracked
        $tracked_repos = get_option('github_deployer_tracked_repos', array());
        $tracked = false;
        $repo_data = null;
        
        foreach ($tracked_repos as $tracked_repo) {
            if ($tracked_repo['repo'] === $repo) {
                $tracked = true;
                $repo_data = $tracked_repo;
                break;
            }
        }
        
        wp_send_json_success(array(
            'tracked' => $tracked,
            'repo' => $repo,
            'repo_data' => $repo_data
        ));
    }

    /**
     * Fetch GitHub repositories
     */
    public function fetch_repositories() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'user';
        $query = isset($_GET['query']) ? sanitize_text_field($_GET['query']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        
        // Get repository manager
        $repo_manager = new Repository_Manager();
        
        // Fetch repositories
        $result = $repo_manager->get_github_repositories($type, $query, $page);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => format_error_message($result)));
        }
        
        // Format response data
        $data = array(
            'repositories' => $type === 'search' ? $result->items : $result,
            'total_count' => $type === 'search' ? $result->total_count : count($result),
            'page' => $page,
            'query' => $query,
            'type' => $type
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Check repository connection status
     */
    public function check_connection() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $owner = isset($_GET['owner']) ? sanitize_text_field($_GET['owner']) : '';
        $repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error(array('message' => __('Owner and repository name are required.', 'github-deployer')));
        }
        
        // Get GitHub API
        $settings = get_option('github_deployer_settings', array());
        $token = isset($settings['token']) ? $settings['token'] : '';
        $github_api = new GitHub_API($token);
        
        // Check connection status
        $status = $github_api->check_connection_status($owner, $repo);
        
        if (is_wp_error($status)) {
            wp_send_json_error(array(
                'message' => format_error_message($status),
                'connected' => false,
                'owner' => $owner,
                'repo' => $repo
            ));
        }
        
        wp_send_json_success(array(
            'connected' => $status,
            'owner' => $owner,
            'repo' => $repo
        ));
    }

    /**
     * Get repositories from GitHub - Alternative endpoint used by the newer UI
     */
    public function get_repos() {
        // Check nonce
        if (!check_ajax_referer('github_deployer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Check permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get request parameters
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'user';
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        // Get repository manager
        $repo_manager = new Repository_Manager();
        
        // Fetch repositories
        $result = $repo_manager->get_github_repositories($type, $query, $page);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => format_error_message($result)));
            return;
        }
        
        // Prepare repositories data with deploy URLs
        $repositories = array();
        
        if ($type === 'search' && isset($result->items)) {
            $items = $result->items;
            $total_count = $result->total_count;
        } else {
            $items = $result;
            $total_count = count($result);
        }
        
        foreach ($items as $repo) {
            // Add deploy URL
            $repo->deploy_url = admin_url('admin.php?page=github-deployer&tab=deploy') . 
                               '&owner=' . urlencode($repo->owner->login) . 
                               '&repo=' . urlencode($repo->name) . 
                               '&branch=' . urlencode($repo->default_branch);
            
            $repositories[] = $repo;
        }
        
        // Format response data
        $data = array(
            'repositories' => $repositories,
            'total_count' => $total_count,
            'page' => $page,
            'total_pages' => ceil($total_count / 30) // 30 is default per_page in GitHub API
        );
        
        wp_send_json_success($data);
    }
} 