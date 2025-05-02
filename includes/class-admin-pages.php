<?php
namespace GitHub_Deployer;

class Admin_Pages {
    /**
     * Render the main admin page
     */
    public function render_main_page() {
        // Check if a specific tab is selected
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'deploy';
        
        // Define available tabs
        $tabs = array(
            'deploy' => __('Deploy', 'github-deployer'),
            'manage' => __('Manage Repositories', 'github-deployer'),
            'settings' => __('Settings', 'github-deployer')
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('GitHub Deployer', 'github-deployer'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_id => $tab_name) : ?>
                    <a href="<?php echo admin_url('admin.php?page=github-deployer&tab=' . $tab_id); ?>" 
                       class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_name); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            
            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'manage':
                        $this->render_manage_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'deploy':
                    default:
                        $this->render_deploy_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render the deploy tab content
     */
    private function render_deploy_tab() {
        // Get repositories for deployment
        $repositories = new Repositories();
        $repos = $repositories->get_repositories();
        
        ?>
        <div class="github-deployer-deploy-tab">
            <h2><?php _e('Deploy from GitHub', 'github-deployer'); ?></h2>
            
            <?php if (empty($repos)) : ?>
                <div class="notice notice-info">
                    <p><?php _e('No repositories found. Please add a repository first.', 'github-deployer'); ?></p>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=github-deployer&tab=manage'); ?>" class="button">
                        <?php _e('Add Repository', 'github-deployer'); ?>
                    </a>
                </p>
            <?php else : ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="github_deployer_deploy">
                    <?php wp_nonce_field('github_deployer_deploy', 'github_deployer_deploy_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="repo_id"><?php _e('Repository', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <select name="repo_id" id="repo_id" required>
                                    <option value=""><?php _e('-- Select Repository --', 'github-deployer'); ?></option>
                                    <?php foreach ($repos as $repo) : ?>
                                        <option value="<?php echo esc_attr($repo->id); ?>">
                                            <?php echo esc_html($repo->name); ?> (<?php echo esc_html($repo->owner); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="enable_auto_update"><?php _e('Auto Update', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_auto_update" id="enable_auto_update" value="1">
                                    <?php _e('Enable automatic updates for this repository', 'github-deployer'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('If enabled, the plugin will check for updates hourly and deploy automatically.', 'github-deployer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" 
                               value="<?php _e('Deploy Now', 'github-deployer'); ?>">
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render the manage repositories tab content
     */
    private function render_manage_tab() {
        // Get repositories
        $repositories = new Repositories();
        $repos = $repositories->get_repositories();
        
        ?>
        <div class="github-deployer-manage-tab">
            <h2><?php _e('Manage Repositories', 'github-deployer'); ?></h2>
            
            <div class="github-deployer-add-repo">
                <h3><?php _e('Add New Repository', 'github-deployer'); ?></h3>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="github_deployer_add_repo">
                    <?php wp_nonce_field('github_deployer_add_repo', 'github_deployer_add_repo_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="owner"><?php _e('Owner/Organization', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="owner" id="owner" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="name"><?php _e('Repository Name', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="name" id="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="branch"><?php _e('Branch', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="branch" id="branch" class="regular-text" value="main" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="type"><?php _e('Type', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <select name="type" id="type" required>
                                    <option value="plugin"><?php _e('Plugin', 'github-deployer'); ?></option>
                                    <option value="theme"><?php _e('Theme', 'github-deployer'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="target_dir"><?php _e('Target Directory', 'github-deployer'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="target_dir" id="target_dir" class="regular-text">
                                <p class="description">
                                    <?php _e('Optional. If left empty, repository name will be used.', 'github-deployer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" 
                               value="<?php _e('Add Repository', 'github-deployer'); ?>">
                    </p>
                </form>
            </div>
            
            <?php if (!empty($repos)) : ?>
                <div class="github-deployer-repos-list">
                    <h3><?php _e('Existing Repositories', 'github-deployer'); ?></h3>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'github-deployer'); ?></th>
                                <th><?php _e('Owner', 'github-deployer'); ?></th>
                                <th><?php _e('Branch', 'github-deployer'); ?></th>
                                <th><?php _e('Type', 'github-deployer'); ?></th>
                                <th><?php _e('Auto Update', 'github-deployer'); ?></th>
                                <th><?php _e('Last Updated', 'github-deployer'); ?></th>
                                <th><?php _e('Actions', 'github-deployer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repos as $repo) : ?>
                                <tr>
                                    <td><?php echo esc_html($repo->name); ?></td>
                                    <td><?php echo esc_html($repo->owner); ?></td>
                                    <td><?php echo esc_html($repo->branch); ?></td>
                                    <td><?php echo esc_html($repo->type); ?></td>
                                    <td>
                                        <?php echo $repo->auto_update ? 
                                            '<span class="dashicons dashicons-yes" style="color:green;"></span>' : 
                                            '<span class="dashicons dashicons-no" style="color:red;"></span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html(
                                            date_i18n(
                                                get_option('date_format') . ' ' . get_option('time_format'),
                                                strtotime($repo->last_updated)
                                            )
                                        ); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=github-deployer-update&repo_id=' . $repo->id); ?>" 
                                           class="button button-small">
                                            <?php _e('Update', 'github-deployer'); ?>
                                        </a>
                                        
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=github_deployer_delete_repo&repo_id=' . $repo->id),
                                            'github_deployer_delete_repo_' . $repo->id,
                                            'github_deployer_delete_repo_nonce'
                                        ); ?>" class="button button-small" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this repository?', 'github-deployer'); ?>');">
                                            <?php _e('Delete', 'github-deployer'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render the settings tab content
     */
    private function render_settings_tab() {
        ?>
        <div class="github-deployer-settings-tab">
            <h2><?php _e('GitHub Deployer Settings', 'github-deployer'); ?></h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('github_deployer_settings');
                do_settings_sections('github_deployer_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        $this->render_settings_tab();
    }
    
    /**
     * Handle repository updates submission
     */
    public function handle_repo_update() {
        // Check for nonce
        if (!isset($_POST['github_deployer_update_nonce']) || 
            !wp_verify_nonce($_POST['github_deployer_update_nonce'], 'github_deployer_update')) {
            wp_die(__('Security check failed', 'github-deployer'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action', 'github-deployer'));
        }
        
        // Get and sanitize form data
        $repo_id = isset($_POST['repo_id']) ? absint($_POST['repo_id']) : 0;
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $file_content = isset($_POST['file_content']) ? $_POST['file_content'] : ''; // Content will be sanitized by GitHub API
        $commit_message = isset($_POST['commit_message']) ? sanitize_text_field($_POST['commit_message']) : '';
        $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : 'main';
        
        if (empty($repo_id) || empty($file_path) || empty($file_content)) {
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'tab' => 'manage',
                'error' => 'missing_fields'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Get repository data
        $repositories = new Repositories();
        $repository = $repositories->get_repository($repo_id);
        
        if (!$repository) {
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'tab' => 'manage',
                'error' => 'invalid_repository'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Initialize GitHub API with token
        $github_api = new GitHub_API(get_option('github_deployer_access_token'));
        
        // Prepare file updates
        $file_updates = array(
            $file_path => array(
                'content' => $file_content,
                'message' => $commit_message
            )
        );
        
        // Commit and push the changes
        $result = $github_api->commit_and_push(
            $repository->owner,
            $repository->name,
            $file_updates,
            $branch,
            $commit_message
        );
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array(
                'page' => 'github-deployer',
                'tab' => 'manage',
                'repo_id' => $repo_id,
                'error' => 'update_failed',
                'message' => urlencode($result->get_error_message())
            ), admin_url('admin.php')));
            exit;
        }
        
        // Success
        wp_redirect(add_query_arg(array(
            'page' => 'github-deployer',
            'tab' => 'manage',
            'repo_id' => $repo_id,
            'updated' => '1'
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Render the update repository form
     */
    public function render_update_form($repo_id) {
        $repositories = new Repositories();
        $repository = $repositories->get_repository($repo_id);
        
        if (!$repository) {
            echo '<div class="notice notice-error"><p>' . __('Invalid repository', 'github-deployer') . '</p></div>';
            return;
        }
        
        $github_api = new GitHub_API(get_option('github_deployer_access_token'));
        $branches = $github_api->get_branches($repository->owner, $repository->name);
        
        if (is_wp_error($branches)) {
            echo '<div class="notice notice-error"><p>' . 
                sprintf(__('Error loading branches: %s', 'github-deployer'), $branches->get_error_message()) . 
                '</p></div>';
            $branches = array();
        }
        
        ?>
        <div class="wrap">
            <h2><?php printf(__('Update Repository: %s', 'github-deployer'), esc_html($repository->name)); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="github_deployer_update_repo">
                <input type="hidden" name="repo_id" value="<?php echo esc_attr($repo_id); ?>">
                <?php wp_nonce_field('github_deployer_update', 'github_deployer_update_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="branch"><?php _e('Branch', 'github-deployer'); ?></label></th>
                        <td>
                            <select name="branch" id="branch">
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo esc_attr($branch->name); ?>">
                                        <?php echo esc_html($branch->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="file_path"><?php _e('File Path', 'github-deployer'); ?></label></th>
                        <td>
                            <input type="text" name="file_path" id="file_path" class="regular-text" 
                                   placeholder="path/to/file.php" required>
                            <p class="description">
                                <?php _e('Path to the file in the repository you want to update', 'github-deployer'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="file_content"><?php _e('File Content', 'github-deployer'); ?></label></th>
                        <td>
                            <textarea name="file_content" id="file_content" rows="10" class="large-text code" required></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="commit_message"><?php _e('Commit Message', 'github-deployer'); ?></label></th>
                        <td>
                            <input type="text" name="commit_message" id="commit_message" class="regular-text" 
                                   placeholder="Update file.php" required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                           value="<?php _e('Commit & Push Changes', 'github-deployer'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
} 