<?php
/**
 * Deploy tab template
 */

// Check for update mode
$update_mode = isset($_GET['update']) && $_GET['update'] === '1';
$prefill_owner = isset($_GET['owner']) ? sanitize_text_field($_GET['owner']) : '';
$prefill_repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
$prefill_ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
$prefill_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$github_api = new GitHub_Deployer\GitHub_API(get_option('github_deployer_access_token', ''));
$auto_updater = new GitHub_Deployer\Auto_Updater(GitHub_Deployer\Plugin::get_instance());

// Get auto-updating repositories
$tracked_repos = array();
try {
    $tracked_repos = $auto_updater->get_tracked_repos();
} catch (Exception $e) {
    // Handle exception silently - the table might not exist yet
    error_log('GitHub Deployer: ' . $e->getMessage());
}

// Check if the repositories table exists
global $wpdb;
$table_exists = false;
$table_name = $wpdb->prefix . 'github_deployer_repositories';
try {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
} catch (Exception $e) {
    // Handle exception silently
    error_log('GitHub Deployer: Error checking table existence: ' . $e->getMessage());
}

// Create table if it doesn't exist
if (!$table_exists) {
    $installer = new GitHub_Deployer\Installer();
    // Call the installer's activate method to create tables
    $installer->activate();
}
?>

<div class="github-deployer-container">
    <?php if (!GitHub_Deployer\is_github_deployer_configured()): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('Please configure your GitHub Personal Access Token in the Settings tab before deploying.', 'github-deployer'); ?></p>
            <p><a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'settings'), admin_url('admin.php'))); ?>" class="button button-primary"><?php esc_html_e('Go to Settings', 'github-deployer'); ?></a></p>
        </div>
    <?php endif; ?>
    
    <?php 
    // Display success message if deployment was successful
    if (isset($_GET['success']) && $_GET['success'] === '1') {
        $repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        $auto_update_enabled = isset($_GET['auto_update_enabled']) && $_GET['auto_update_enabled'] === '1';
        
        echo '<div class="notice notice-success is-dismissible"><p>';
        
        if ($updated) {
            printf(
                __('Successfully updated %s "%s" from GitHub.', 'github-deployer'),
                $type,
                '<strong>' . esc_html($repo) . '</strong>'
            );
        } else {
            printf(
                __('Successfully deployed %s "%s" from GitHub.', 'github-deployer'),
                $type,
                '<strong>' . esc_html($repo) . '</strong>'
            );
        }
        
        if ($auto_update_enabled) {
            echo ' ' . __('Auto-updates have been enabled for this repository.', 'github-deployer');
        }
        
        echo '</p></div>';
    }
    
    // Display error message if there was an error
    if (isset($_GET['error'])) {
        $error = sanitize_text_field($_GET['error']);
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html($error);
        echo '</p></div>';
    }
    ?>
    
    <div class="github-deployer-deploy">
        <h2><?php 
            if ($update_mode) {
                esc_html_e('Update from GitHub', 'github-deployer');
            } else {
                esc_html_e('Deploy from GitHub', 'github-deployer');
            }
        ?></h2>
        
        <?php if (GitHub_Deployer\is_github_deployer_configured()): ?>
        <div class="github-deployer-info notice notice-info">
            <p><?php esc_html_e('Now with private repository support! Enter your repository details below to deploy.', 'github-deployer'); ?></p>
            <p><?php esc_html_e('Note: For private repositories, make sure your GitHub token has the "repo" scope.', 'github-deployer'); ?></p>
        </div>
        <?php endif; ?>
        
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="github-deployer-form">
            <input type="hidden" name="action" value="github_deployer_deploy">
            <?php wp_nonce_field('github_deployer_deploy', 'github_deployer_deploy_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="github-deployer-owner"><?php esc_html_e('Repository Owner', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="github-deployer-owner" name="owner" required class="regular-text" value="<?php echo esc_attr($prefill_owner); ?>">
                        <p class="description"><?php esc_html_e('GitHub username or organization name', 'github-deployer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github-deployer-repo"><?php esc_html_e('Repository Name', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="github-deployer-repo" name="repo" required class="regular-text" value="<?php echo esc_attr($prefill_repo); ?>">
                        <p class="description"><?php esc_html_e('Name of the GitHub repository', 'github-deployer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github-deployer-ref"><?php esc_html_e('Branch/Tag/Commit', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="github-deployer-ref" name="ref" value="<?php echo !empty($prefill_ref) ? esc_attr($prefill_ref) : 'main'; ?>" required class="regular-text">
                        <p class="description"><?php esc_html_e('Branch name, tag name, or commit hash to deploy', 'github-deployer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github-deployer-type"><?php esc_html_e('Deployment Type', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <select id="github-deployer-type" name="type" required>
                            <option value="plugin" <?php selected($prefill_type, 'plugin'); ?>><?php esc_html_e('Plugin', 'github-deployer'); ?></option>
                            <option value="theme" <?php selected($prefill_type, 'theme'); ?>><?php esc_html_e('Theme', 'github-deployer'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Whether to deploy as a plugin or theme', 'github-deployer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="github-deployer-update"><?php esc_html_e('Update Existing', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="github-deployer-update" name="update_existing" value="1" <?php checked($update_mode, true); ?>>
                            <?php esc_html_e('Update if already installed', 'github-deployer'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('When checked, will update the plugin/theme if it already exists', 'github-deployer'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div id="github-deployer-repo-info" style="display: none;">
                <div class="loading"><?php esc_html_e('Loading repository information...', 'github-deployer'); ?></div>
                <div class="content"></div>
            </div>
            
            <?php submit_button($update_mode ? __('Update', 'github-deployer') : __('Deploy', 'github-deployer'), 'primary', 'submit', true, array('id' => 'github-deployer-submit')); ?>
            
            <div class="auto-update-option">
                <label>
                    <input type="checkbox" id="github-deployer-auto-update" name="enable_auto_update" value="1">
                    <?php esc_html_e('Enable auto-updates for this repository', 'github-deployer'); ?>
                </label>
                <p class="description"><?php esc_html_e('When checked, this plugin/theme will be automatically updated when changes are detected in the GitHub repository.', 'github-deployer'); ?></p>
            </div>
        </form>
    </div>
    
    <?php if (!empty($tracked_repos)): ?>
    <div class="github-deployer-tracked-repos">
        <h2><?php esc_html_e('Repositories with Auto-Updates Enabled', 'github-deployer'); ?></h2>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Repository', 'github-deployer'); ?></th>
                    <th><?php esc_html_e('Branch/Tag', 'github-deployer'); ?></th>
                    <th><?php esc_html_e('Type', 'github-deployer'); ?></th>
                    <th><?php esc_html_e('Last Updated', 'github-deployer'); ?></th>
                    <th><?php esc_html_e('Actions', 'github-deployer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tracked_repos as $repo_data): ?>
                <tr>
                    <td><?php echo esc_html($repo_data['owner'] . '/' . $repo_data['repo']); ?></td>
                    <td><?php echo esc_html($repo_data['ref']); ?></td>
                    <td><?php echo esc_html($repo_data['type']); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $repo_data['last_updated'])); ?></td>
                    <td>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                            <input type="hidden" name="action" value="github_deployer_disable_auto_update">
                            <input type="hidden" name="owner" value="<?php echo esc_attr($repo_data['owner']); ?>">
                            <input type="hidden" name="repo" value="<?php echo esc_attr($repo_data['repo']); ?>">
                            <?php wp_nonce_field('github_deployer_disable_auto_update'); ?>
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Disable Auto-Updates', 'github-deployer'); ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="description"><?php esc_html_e('Repositories listed here will be automatically checked for updates hourly. When changes are detected, the plugin/theme will be updated from GitHub.', 'github-deployer'); ?></p>
        
        <p><a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'repositories'), admin_url('admin.php'))); ?>" class="button button-secondary"><?php esc_html_e('View All Repositories', 'github-deployer'); ?></a></p>
    </div>
    <?php endif; ?>
</div> 