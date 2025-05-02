<?php
namespace GitHub_Deployer;

/**
 * Main Plugin Class
 * 
 * Handles plugin initialization, hooks, and core functionality
 */
class Plugin {
    /**
     * @var Plugin Singleton instance
     */
    private static $instance = null;
    
    /**
     * @var GitHub_API GitHub API instance
     */
    private $github_api = null;
    
    /**
     * @var Settings Settings instance
     */
    private $settings = null;
    
    /**
     * @var Deployer Deployer instance
     */
    private $deployer = null;
    
    /**
     * @var Repository_Manager Repository Manager instance
     */
    private $repository_manager = null;
    
    /**
     * @var Auto_Updater Auto Updater instance
     */
    private $auto_updater = null;
    
    /**
     * @var AJAX AJAX handler instance
     */
    private $ajax = null;
    
    /**
     * @var Webhook_Handler Webhook Handler instance
     */
    private $webhook_handler = null;
    
    /**
     * @var Backup_Manager Backup Manager instance
     */
    private $backup_manager = null;
    
    /**
     * @var Diff_Viewer Diff Viewer instance
     */
    private $diff_viewer = null;
    
    /**
     * @var Notification_Manager Notification Manager instance
     */
    private $notification_manager = null;
    
    /**
     * Get the singleton instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct creation
     */
    private function __construct() {
        // Initialize Settings right away to ensure settings are registered in time
        $this->settings = new Settings();
        
        // Initialize webhook handler early with plugin instance
        $this->webhook_handler = new Webhook_Handler($this);
        
        // Continue with other hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Text domain
        \add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin menu
        \add_action('admin_menu', array($this->settings, 'add_admin_menu'));
        
        // Admin scripts
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        \load_plugin_textdomain('github-deployer', false, dirname(\plugin_basename(GITHUB_DEPLOYER_FILE)) . '/languages');
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize GitHub API
        $settings = get_option('github_deployer_settings', array());
        $this->github_api = new GitHub_API(isset($settings['token']) ? $settings['token'] : '');
        
        // Initialize Deployer
        $this->deployer = new Deployer($this->github_api);
        
        // Initialize Repository Manager
        $this->repository_manager = new Repository_Manager();
        
        // Initialize Auto Updater
        $this->auto_updater = new Auto_Updater($this);
        
        // Initialize AJAX
        $this->ajax = new AJAX();
        
        // Initialize new components
        $this->backup_manager = new Backup_Manager();
        $this->diff_viewer = new Diff_Viewer($this->github_api);
        $this->notification_manager = new Notification_Manager();
    }
    
    /**
     * Get the GitHub API instance
     * 
     * @return GitHub_API
     */
    public function get_github_api() {
        return $this->github_api;
    }
    
    /**
     * Get the Deployer instance
     * 
     * @return Deployer
     */
    public function get_deployer() {
        return $this->deployer;
    }
    
    /**
     * Get the Repository Manager instance
     * 
     * @return Repository_Manager
     */
    public function get_repository_manager() {
        return $this->repository_manager;
    }
    
    /**
     * Get the Auto Updater instance
     * 
     * @return Auto_Updater
     */
    public function get_auto_updater() {
        return $this->auto_updater;
    }
    
    /**
     * Get the AJAX instance
     * 
     * @return AJAX
     */
    public function get_ajax() {
        return $this->ajax;
    }
    
    /**
     * Get the Webhook Handler instance
     * 
     * @return Webhook_Handler
     */
    public function get_webhook_handler() {
        return $this->webhook_handler;
    }
    
    /**
     * Get the Settings instance
     * 
     * @return Settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get the Backup Manager instance
     * 
     * @return Backup_Manager
     */
    public function get_backup_manager() {
        return $this->backup_manager;
    }
    
    /**
     * Get the Diff Viewer instance
     * 
     * @return Diff_Viewer
     */
    public function get_diff_viewer() {
        return $this->diff_viewer;
    }
    
    /**
     * Get the Notification Manager instance
     * 
     * @return Notification_Manager
     */
    public function get_notification_manager() {
        return $this->notification_manager;
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        $installer = new Installer();
        $installer->create_tables();
        
        // Set up scheduler for updates
        if (!\wp_next_scheduled('github_deployer_check_updates')) {
            \wp_schedule_event(time(), 'twicedaily', 'github_deployer_check_updates');
        }
        
        // Try to auto-connect to the official repository
        self::maybe_connect_official_repo();
        
        \do_action('github_deployer_activated');
    }
    
    /**
     * Try to automatically connect to the official github-deployer repository
     */
    private static function maybe_connect_official_repo() {
        // Only try if we have no repositories yet
        $deployed_repos = \get_option('github_deployer_deployed_repos', array());
        
        if (empty($deployed_repos)) {
            // Get plugin instance
            $plugin = self::get_instance();
            
            // Get repository manager
            $repo_manager = $plugin->get_repository_manager();
            
            // Try to connect to the official repository
            $repo_manager->connect_to_specific_repository('https://github.com/Homeboy20/wordpress-gitdeploy');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        $installer = new Installer();
        $installer->deactivate();
        
        // Unschedule the auto-update cron event
        $timestamp = wp_next_scheduled('github_deployer_check_updates');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'github_deployer_check_updates');
        }
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Connect to the WordPress GitDeploy repository
     * 
     * Ensures the Homeboy20/wordpress-gitdeploy repository is connected
     * regardless of the URL format the user enters
     * 
     * @param string $url Optional URL that the user provided
     * @return array|WP_Error Connection result or error
     */
    public function connect_to_wordpress_gitdeploy($url = 'https://github.com/Homeboy20/wordpress-gitdeploy') {
        // Get repository manager
        $repo_manager = $this->get_repository_manager();
        
        // Look for existing repository first
        $deployed_repos = $repo_manager->get_deployed_repositories();
        foreach ($deployed_repos as $repo) {
            if ($repo['owner'] === 'Homeboy20' && $repo['repo'] === 'wordpress-gitdeploy') {
                // Already connected
                return array(
                    'owner' => 'Homeboy20',
                    'repo' => 'wordpress-gitdeploy',
                    'branch' => isset($repo['ref']) ? $repo['ref'] : 'main',
                    'type' => isset($repo['type']) ? $repo['type'] : 'plugin',
                    'connected' => isset($repo['connected']) ? $repo['connected'] : true,
                    'already_connected' => true
                );
            }
        }
        
        // Not found, try to connect using the standard URL
        $std_url = 'https://github.com/Homeboy20/wordpress-gitdeploy';
        
        // If the user provided a URL that seems to point to the same repo but in a different format
        if (!empty($url) && (
            stripos($url, 'homeboy20/wordpress-gitdeploy') !== false || 
            stripos($url, 'wordpress-gitdeploy') !== false
        )) {
            // Use their URL instead
            return $repo_manager->connect_to_specific_repository($url);
        }
        
        // Use the standard URL
        return $repo_manager->connect_to_specific_repository($std_url);
    }
} 