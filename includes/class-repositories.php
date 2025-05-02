<?php
namespace GitHub_Deployer;

/**
 * Repositories Class
 * 
 * Handles repository data storage and retrieval
 */
class Repositories {
    /**
     * Get all repositories
     * 
     * @return array Array of repository objects
     */
    public function get_repositories() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists first to prevent errors
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            // Table doesn't exist yet, create it
            $this->maybe_create_table();
            return array();
        }
        
        $repositories = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY name ASC"
        );
        
        return $repositories ? $repositories : array();
    }
    
    /**
     * Get a repository by ID
     * 
     * @param int $id Repository ID
     * @return object|false Repository object or false if not found
     */
    public function get_repository($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            // Table doesn't exist yet
            $this->maybe_create_table();
            return false;
        }
        
        $repository = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $id
            )
        );
        
        return $repository;
    }
    
    /**
     * Add a new repository
     * 
     * @param string $owner Repository owner
     * @param string $name Repository name
     * @param string $branch Branch name
     * @param string $type Repository type (plugin or theme)
     * @param string $target_dir Target directory name (optional)
     * @param bool $auto_update Whether to auto-update (optional)
     * @return int|false ID of the new repository or false on failure
     */
    public function add_repository($owner, $name, $branch, $type, $target_dir = '', $auto_update = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Make sure table exists
        $this->maybe_create_table();
        
        // If target_dir is not provided, use the repository name
        if (empty($target_dir)) {
            $target_dir = $name;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'owner' => $owner,
                'name' => $name,
                'branch' => $branch,
                'type' => $type,
                'target_dir' => $target_dir,
                'auto_update' => $auto_update ? 1 : 0,
                'last_checked' => current_time('mysql'),
                'last_updated' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update a repository
     * 
     * @param int $id Repository ID
     * @param array $data Repository data
     * @return bool True on success, false on failure
     */
    public function update_repository($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        // Prepare data formats based on data keys
        $formats = array();
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'auto_update':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }
        
        // Update last_updated timestamp
        $data['last_updated'] = current_time('mysql');
        $formats[] = '%s';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a repository
     * 
     * @param int $id Repository ID
     * @return bool True on success, false on failure
     */
    public function delete_repository($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Update the last_checked timestamp for a repository
     * 
     * @param int $id Repository ID
     * @return bool True on success, false on failure
     */
    public function update_last_checked($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            array('last_checked' => current_time('mysql')),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get repositories that are set to auto-update
     * 
     * @return array Array of repository objects
     */
    public function get_auto_update_repositories() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table exists first to prevent errors
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            // Table doesn't exist yet, create it
            $this->maybe_create_table();
            return array();
        }
        
        $repositories = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE auto_update = 1"
        );
        
        return $repositories ? $repositories : array();
    }
    
    /**
     * Create the repositories table if it doesn't exist
     */
    private function maybe_create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'github_deployer_repositories';
        
        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Table doesn't exist, create it
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$table_name} (
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
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Log the table creation
            error_log('GitHub Deployer: Created repositories table from Repositories class');
            
            return true;
        }
        
        return false;
    }
} 