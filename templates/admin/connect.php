<?php
/**
 * Template for connecting to a specific GitHub repository
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
$repo_connected = false;
$deployment_result = null;
$connection_error = false;
$connection_message = '';

if (isset($_POST['github_deployer_connect_url_nonce']) && wp_verify_nonce($_POST['github_deployer_connect_url_nonce'], 'github_deployer_connect_url')) {
    if (isset($_POST['repo_url']) && !empty($_POST['repo_url'])) {
        $repo_url = sanitize_text_field($_POST['repo_url']);
        $deploy_immediately = isset($_POST['deploy_immediately']) && $_POST['deploy_immediately'] == '1';
        $enable_auto_update = isset($_POST['enable_auto_update']) && $_POST['enable_auto_update'] == '1';
        
        // Get plugin instance
        $plugin = GitHub_Deployer\Plugin::get_instance();
        
        // Use the special connect method for WordPress GitDeploy repository
        $result = $plugin->connect_to_wordpress_gitdeploy($repo_url);
        
        if (is_wp_error($result)) {
            $connection_error = true;
            $connection_message = $result->get_error_message();
        } else {
            $repo_connected = true;
            
            if (isset($result['already_connected']) && $result['already_connected']) {
                $connection_message = sprintf(
                    __('The repository %s/%s is already connected.', 'github-deployer'),
                    $result['owner'],
                    $result['repo']
                );
            } else {
                $connection_message = sprintf(
                    __('Successfully connected to repository %s/%s (%s branch).', 'github-deployer'),
                    $result['owner'],
                    $result['repo'],
                    $result['branch']
                );
            }
            
            // Deploy immediately if requested
            if ($deploy_immediately) {
                $deployer = $plugin->get_deployer();
                $deployment_result = $deployer->deploy($result['owner'], $result['repo'], $result['branch'], $result['type']);
                
                if (is_wp_error($deployment_result)) {
                    $connection_message .= ' ' . sprintf(
                        __('However, deployment failed: %s', 'github-deployer'),
                        $deployment_result->get_error_message()
                    );
                } else {
                    $connection_message .= ' ' . __('Repository has been successfully deployed.', 'github-deployer');
                }
            }
            
            // Enable auto-update if requested
            if ($enable_auto_update) {
                // Find repository in database
                $db_repos = new GitHub_Deployer\Repositories();
                $db_results = $db_repos->get_repositories();
                
                foreach ($db_results as $db_repo) {
                    if ($db_repo->owner === $result['owner'] && $db_repo->name === $result['repo']) {
                        // Enable auto-update for this repository
                        $db_repos->update_repository($db_repo->id, array(
                            'auto_update' => 1
                        ));
                        
                        // Update tracked repositories in options
                        $tracked_repos = get_option('github_deployer_tracked_repos', array());
                        $tracked_repos[] = array(
                            'owner' => $result['owner'],
                            'repo' => $result['repo'],
                            'ref' => $result['branch'],
                            'type' => $result['type'],
                            'last_updated' => time()
                        );
                        update_option('github_deployer_tracked_repos', $tracked_repos);
                        
                        // Add message about auto-update
                        $connection_message .= ' ' . __('Auto-updates have been enabled.', 'github-deployer');
                        break;
                    }
                }
            }
        }
    } else {
        $connection_error = true;
        $connection_message = __('Please enter a valid GitHub repository URL.', 'github-deployer');
    }
}
?>

<div class="github-deployer-connect">
    <h2><?php esc_html_e('Connect to GitHub Repository', 'github-deployer'); ?></h2>
    
    <?php if ($repo_connected): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($connection_message); ?></p>
            <p>
                <a href="<?php echo esc_url(add_query_arg(array(
                    'page' => 'github-deployer',
                    'owner' => $result['owner'],
                    'repo' => $result['repo'],
                    'ref' => $result['branch'],
                    'type' => $result['type']
                ), admin_url('admin.php'))); ?>" class="button button-primary">
                    <?php esc_html_e('Deploy Now', 'github-deployer'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'repositories'), admin_url('admin.php'))); ?>" class="button">
                    <?php esc_html_e('View All Repositories', 'github-deployer'); ?>
                </a>
            </p>
        </div>
    <?php elseif ($connection_error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($connection_message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="github-deployer-connect-form">
        <p><?php esc_html_e('Enter the URL of a GitHub repository to connect it to GitHub Deployer.', 'github-deployer'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('github_deployer_connect_url', 'github_deployer_connect_url_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="repo-url"><?php esc_html_e('GitHub Repository URL', 'github-deployer'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="repo-url" name="repo_url" class="regular-text" placeholder="https://github.com/username/repository" value="<?php echo isset($_POST['repo_url']) ? esc_attr(sanitize_text_field($_POST['repo_url'])) : 'https://github.com/Homeboy20/wordpress-gitdeploy'; ?>" required>
                        <p class="description"><?php esc_html_e('Enter the full URL to the GitHub repository (e.g., https://github.com/username/repository).', 'github-deployer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Options', 'github-deployer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="deploy-immediately">
                                <input type="checkbox" id="deploy-immediately" name="deploy_immediately" value="1" checked>
                                <?php esc_html_e('Deploy immediately after connecting', 'github-deployer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('This will install the repository as a WordPress plugin or theme right after connecting.', 'github-deployer'); ?></p>
                            
                            <label for="enable-auto-update" style="margin-top: 10px; display: block;">
                                <input type="checkbox" id="enable-auto-update" name="enable_auto_update" value="1" checked>
                                <?php esc_html_e('Enable auto-updates', 'github-deployer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically check for and apply updates to this repository.', 'github-deployer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Connect Repository', 'github-deployer')); ?>
        </form>
    </div>
    
    <div class="github-deployer-connect-info">
        <h3><?php esc_html_e('What happens when you connect a repository?', 'github-deployer'); ?></h3>
        <ol>
            <li><?php esc_html_e('GitHub Deployer checks if the repository exists and is accessible with your credentials.', 'github-deployer'); ?></li>
            <li><?php esc_html_e('The repository is registered in your WordPress database for deployment.', 'github-deployer'); ?></li>
            <li><?php esc_html_e('You can then deploy the repository as a plugin or theme, and optionally enable auto-updates.', 'github-deployer'); ?></li>
        </ol>
        
        <p>
            <strong><?php esc_html_e('Note:', 'github-deployer'); ?></strong>
            <?php esc_html_e('For private repositories, make sure you have configured a valid GitHub token with repository access in the Settings tab.', 'github-deployer'); ?>
        </p>
    </div>
</div> 