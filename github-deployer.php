<?php
/**
 * Plugin Name: GitHub Deployer for WordPress
 * Description: Directly deploy plugins and themes from GitHub to your WordPress site. Now with full private repository support!
 * Version: 2.1.0
 * Author: Ndosa
 * Author URI: https://ndosa.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: github-deployer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GITHUB_DEPLOYER_VERSION', '2.1.0');
define('GITHUB_DEPLOYER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GITHUB_DEPLOYER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GITHUB_DEPLOYER_FILE', __FILE__);

// Include class files
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-settings.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-github-api.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-deployer.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-installer.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-repository-manager.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-repositories.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-auto-updater.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-ajax.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-plugin.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/helpers.php';

// Include new enhancement classes
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-backup-manager.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-diff-viewer.php';
require_once GITHUB_DEPLOYER_PLUGIN_DIR . 'includes/class-notification-manager.php';

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array('GitHub_Deployer\\Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('GitHub_Deployer\\Plugin', 'deactivate'));

// Initialize the plugin immediately to ensure settings are registered early
GitHub_Deployer\Plugin::get_instance();

// Initialize components after plugins are loaded
add_action('plugins_loaded', function() {
    // Initialize enhanced components
    new GitHub_Deployer\Backup_Manager();
    new GitHub_Deployer\Diff_Viewer();
    new GitHub_Deployer\Notification_Manager();
    
    // Add GitHub Deployer admin bar menu for quick access
    add_action('admin_bar_menu', function($admin_bar) {
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            return;
        }
        
        $admin_bar->add_node(array(
            'id'    => 'github-deployer',
            'title' => 'GitHub Deployer',
            'href'  => admin_url('admin.php?page=github-deployer'),
        ));
        
        // Add submenu items
        $admin_bar->add_node(array(
            'id'     => 'github-deployer-deploy',
            'parent' => 'github-deployer',
            'title'  => __('Deploy New', 'github-deployer'),
            'href'   => admin_url('admin.php?page=github-deployer'),
        ));
        
        $admin_bar->add_node(array(
            'id'     => 'github-deployer-repos',
            'parent' => 'github-deployer',
            'title'  => __('Repositories', 'github-deployer'),
            'href'   => admin_url('admin.php?page=github-deployer&tab=repositories'),
        ));
        
        $admin_bar->add_node(array(
            'id'     => 'github-deployer-settings',
            'parent' => 'github-deployer',
            'title'  => __('Settings', 'github-deployer'),
            'href'   => admin_url('admin.php?page=github-deployer&tab=settings'),
        ));
    }, 100);
}); 