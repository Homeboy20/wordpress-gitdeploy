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
        <div class="github-deployer-card">
            <div class="github-deployer-card-header">
                <h2><?php esc_html_e('Repository Details', 'github-deployer'); ?></h2>
            </div>
            
            <div class="github-deployer-card-body">
                <?php 
                // Show info notice for private repository support
                if (empty($error_message)): 
                ?>
                <div class="github-deployer-notice github-deployer-notice-info">
                    <p>
                        <?php esc_html_e('Now with private repository support! Enter your repository details below to deploy.', 'github-deployer'); ?>
                        <?php esc_html_e('For private repositories, make sure your GitHub token has the "repo" scope.', 'github-deployer'); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="github-deployer-notice github-deployer-notice-error">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="github-deployer-form">
                    <input type="hidden" name="action" value="github_deployer_deploy">
                    <?php wp_nonce_field('github_deployer_deploy', 'github_deployer_deploy_nonce'); ?>
                    
                    <?php if (isset($_GET['owner']) && isset($_GET['repo'])): ?>
                        <input type="hidden" name="owner" value="<?php echo esc_attr($_GET['owner']); ?>">
                        <input type="hidden" name="repo" value="<?php echo esc_attr($_GET['repo']); ?>">
                        <input type="hidden" name="ref" value="<?php echo isset($_GET['ref']) ? esc_attr($_GET['ref']) : 'main'; ?>">
                        <input type="hidden" name="type" value="<?php echo isset($_GET['type']) ? esc_attr($_GET['type']) : 'plugin'; ?>">
                        <input type="hidden" name="update_existing" value="<?php echo isset($_GET['update']) && $_GET['update'] === '1' ? '1' : '0'; ?>">
                        
                        <div class="github-deployer-notice github-deployer-notice-info">
                            <p>
                                <?php 
                                printf(
                                    esc_html__('You are about to %s %s "%s/%s" from the %s branch.', 'github-deployer'),
                                    $update_mode ? esc_html__('update', 'github-deployer') : esc_html__('deploy', 'github-deployer'),
                                    isset($_GET['type']) && $_GET['type'] === 'theme' ? esc_html__('theme', 'github-deployer') : esc_html__('plugin', 'github-deployer'),
                                    esc_html($_GET['owner']),
                                    esc_html($_GET['repo']),
                                    isset($_GET['ref']) ? esc_html($_GET['ref']) : 'main'
                                );
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="owner"><?php esc_html_e('Repository Owner', 'github-deployer'); ?></label></th>
                                <td>
                                    <input type="text" id="owner" name="owner" class="regular-text" placeholder="<?php esc_attr_e('e.g., wordpress', 'github-deployer'); ?>" required>
                                    <p class="description"><?php esc_html_e('GitHub username or organization name', 'github-deployer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="repo"><?php esc_html_e('Repository Name', 'github-deployer'); ?></label></th>
                                <td>
                                    <input type="text" id="repo" name="repo" class="regular-text" placeholder="<?php esc_attr_e('e.g., gutenberg', 'github-deployer'); ?>" required>
                                    <p class="description"><?php esc_html_e('Name of the repository', 'github-deployer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ref"><?php esc_html_e('Branch/Tag', 'github-deployer'); ?></label></th>
                                <td>
                                    <input type="text" id="ref" name="ref" class="regular-text" placeholder="<?php esc_attr_e('e.g., main, v2.1.0', 'github-deployer'); ?>" value="main">
                                    <p class="description"><?php esc_html_e('Branch, tag, or commit reference (defaults to main branch)', 'github-deployer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Type', 'github-deployer'); ?></th>
                                <td>
                                    <fieldset>
                                        <label class="github-deployer-radio-label">
                                            <input type="radio" name="type" value="plugin" checked>
                                            <span><?php esc_html_e('Plugin', 'github-deployer'); ?></span>
                                        </label>
                                        <br>
                                        <label class="github-deployer-radio-label">
                                            <input type="radio" name="type" value="theme">
                                            <span><?php esc_html_e('Theme', 'github-deployer'); ?></span>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'github-deployer'); ?></th>
                                <td>
                                    <fieldset>
                                        <label class="github-deployer-checkbox-label">
                                            <input type="checkbox" name="update_existing" value="1" <?php echo $update_mode ? 'checked' : ''; ?>>
                                            <span><?php esc_html_e('Update existing installation', 'github-deployer'); ?></span>
                                        </label>
                                        <p class="description"><?php esc_html_e('Check this if you want to update an existing plugin or theme.', 'github-deployer'); ?></p>
                                        
                                        <label class="github-deployer-checkbox-label" style="margin-top: 10px; display: block;">
                                            <input type="checkbox" name="enable_auto_update" value="1" checked>
                                            <span><?php esc_html_e('Enable auto-updates for this repository', 'github-deployer'); ?></span>
                                        </label>
                                        <p class="description"><?php esc_html_e('GitHub Deployer will check for updates to this repository automatically.', 'github-deployer'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                    
                    <div class="github-deployer-card-footer">
                        <button type="submit" class="github-deployer-button github-deployer-button-primary">
                            <?php 
                            if ($update_mode) {
                                esc_html_e('Update Now', 'github-deployer');
                            } else {
                                esc_html_e('Deploy Now', 'github-deployer');
                            }
                            ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="github-deployer-notice github-deployer-notice-warning">
            <p>
                <?php 
                printf(
                    esc_html__('Please configure GitHub Deployer with your GitHub token in the %1$ssettings%2$s before deploying.', 'github-deployer'),
                    '<a href="' . esc_url(admin_url('admin.php?page=github-deployer&tab=settings')) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="github-deployer-card">
            <div class="github-deployer-card-header">
                <h2><?php esc_html_e('Quick Help', 'github-deployer'); ?></h2>
            </div>
            
            <div class="github-deployer-card-body">
                <p><?php esc_html_e('To deploy a plugin or theme from GitHub:', 'github-deployer'); ?></p>
                
                <ol>
                    <li><?php esc_html_e('Enter the repository owner (username or organization)', 'github-deployer'); ?></li>
                    <li><?php esc_html_e('Enter the repository name', 'github-deployer'); ?></li>
                    <li><?php esc_html_e('Specify a branch, tag, or commit reference (defaults to main)', 'github-deployer'); ?></li>
                    <li><?php esc_html_e('Select whether this is a plugin or theme', 'github-deployer'); ?></li>
                    <li><?php esc_html_e('Check "Update existing" if you are updating a previously deployed item', 'github-deployer'); ?></li>
                    <li><?php esc_html_e('Click "Deploy Now" to install', 'github-deployer'); ?></li>
                </ol>
                
                <p>
                    <strong><?php esc_html_e('Private Repositories:', 'github-deployer'); ?></strong>
                    <?php esc_html_e('Make sure your GitHub token has the "repo" scope to access private repositories.', 'github-deployer'); ?>
                </p>
            </div>
        </div>
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
                    <td><?php echo esc_html(is_object($repo_data) ? $repo_data->owner . '/' . $repo_data->repo : $repo_data['owner'] . '/' . $repo_data['repo']); ?></td>
                    <td><?php echo esc_html(is_object($repo_data) ? $repo_data->ref : $repo_data['ref']); ?></td>
                    <td><?php echo esc_html(is_object($repo_data) ? $repo_data->type : $repo_data['type']); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), is_object($repo_data) ? $repo_data->last_updated : $repo_data['last_updated'])); ?></td>
                    <td>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                            <input type="hidden" name="action" value="github_deployer_disable_auto_update">
                            <input type="hidden" name="owner" value="<?php echo esc_attr(is_object($repo_data) ? $repo_data->owner : $repo_data['owner']); ?>">
                            <input type="hidden" name="repo" value="<?php echo esc_attr(is_object($repo_data) ? $repo_data->repo : $repo_data['repo']); ?>">
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