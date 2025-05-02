<?php
namespace GitHub_Deployer;

class Installer {
    public function activate() {
        // Set default options if they don't exist
        if (!get_option('github_deployer_settings')) {
            update_option('github_deployer_settings', array(
                'token' => ''
            ));
        }
        
        // Set version option for future upgrades
        update_option('github_deployer_version', GITHUB_DEPLOYER_VERSION);
        
        // Create database tables
        $this->create_tables();
        
        // Add capabilities if needed
        $this->add_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create the necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                owner varchar(255) NOT NULL,
                name varchar(255) NOT NULL,
                branch varchar(255) NOT NULL DEFAULT 'main',
                type varchar(50) NOT NULL DEFAULT 'plugin',
                target_dir varchar(255) NOT NULL,
                auto_update tinyint(1) NOT NULL DEFAULT 0,
                last_checked datetime DEFAULT NULL,
                last_updated datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY owner_name (owner, name)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Log the table creation
            error_log('GitHub Deployer: Created repositories table');
        }
    }
    
    private function add_capabilities() {
        // Administrator can manage everything
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_github_deployments');
        }
    }
    
    public function uninstall() {
        // Clean up options
        delete_option('github_deployer_settings');
        delete_option('github_deployer_version');
        
        // Drop database tables
        $this->drop_tables();
        
        // Remove capabilities
        $this->remove_capabilities();
    }
    
    /**
     * Drop the plugin's database tables
     */
    private function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}github_deployer_repositories");
    }
    
    private function remove_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap('manage_github_deployments');
        }
    }
} 