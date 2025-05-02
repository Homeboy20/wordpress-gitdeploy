<div class="wrap github-deployer">
    <h1><?php esc_html_e('GitHub Deployer', 'github-deployer'); ?></h1>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="notice notice-success">
            <p>
                <?php 
                $type = isset($_GET['type']) ? esc_html($_GET['type']) : 'plugin';
                $repo = isset($_GET['repo']) ? esc_html($_GET['repo']) : '';
                $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
                
                if (!empty($repo)) {
                    printf(
                        esc_html__('Successfully %1$s %2$s "%3$s"!', 'github-deployer'),
                        $updated ? esc_html__('updated', 'github-deployer') : esc_html__('deployed', 'github-deployer'),
                        $type === 'plugin' ? esc_html__('plugin', 'github-deployer') : esc_html__('theme', 'github-deployer'),
                        $repo
                    );
                } else {
                    esc_html_e('Deployment successful!', 'github-deployer');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['auto_update_enabled'])): ?>
        <div class="notice notice-success">
            <p>
                <?php 
                $repo = isset($_GET['repo']) ? esc_html($_GET['repo']) : '';
                printf(
                    esc_html__('Auto-updates enabled for "%s". The repository will be checked for updates hourly.', 'github-deployer'),
                    $repo
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['auto_update_disabled'])): ?>
        <div class="notice notice-success">
            <p>
                <?php 
                $repo = isset($_GET['repo']) ? esc_html($_GET['repo']) : '';
                printf(
                    esc_html__('Auto-updates disabled for "%s".', 'github-deployer'),
                    $repo
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="github-deployer-container">
        <div class="github-deployer-settings">
            <form action="options.php" method="post">
                <?php
                settings_fields('github_deployer');
                do_settings_sections('github-deployer');
                submit_button();
                ?>
            </form>
        </div>
        
        <div class="github-deployer-deploy">
            <h2><?php esc_html_e('Deploy from GitHub', 'github-deployer'); ?></h2>
            
            <?php if (!GitHub_Deployer\is_github_deployer_configured()): ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Please configure your GitHub Personal Access Token in the settings above before deploying.', 'github-deployer'); ?></p>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" id="github-deployer-form">
                <input type="hidden" name="action" value="github_deployer_deploy">
                <?php wp_nonce_field('github_deployer_deploy'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github-deployer-owner"><?php esc_html_e('Repository Owner', 'github-deployer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="github-deployer-owner" name="owner" required class="regular-text">
                            <p class="description"><?php esc_html_e('GitHub username or organization name', 'github-deployer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="github-deployer-repo"><?php esc_html_e('Repository Name', 'github-deployer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="github-deployer-repo" name="repo" required class="regular-text">
                            <p class="description"><?php esc_html_e('Name of the GitHub repository', 'github-deployer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="github-deployer-ref"><?php esc_html_e('Branch/Tag/Commit', 'github-deployer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="github-deployer-ref" name="ref" value="main" required class="regular-text">
                            <p class="description"><?php esc_html_e('Branch name, tag name, or commit hash to deploy', 'github-deployer'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="github-deployer-type"><?php esc_html_e('Deployment Type', 'github-deployer'); ?></label>
                        </th>
                        <td>
                            <select id="github-deployer-type" name="type" required>
                                <option value="plugin"><?php esc_html_e('Plugin', 'github-deployer'); ?></option>
                                <option value="theme"><?php esc_html_e('Theme', 'github-deployer'); ?></option>
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
                                <input type="checkbox" id="github-deployer-update" name="update_existing" value="1">
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
                
                <?php submit_button(__('Deploy', 'github-deployer'), 'primary', 'submit', true, array('id' => 'github-deployer-submit')); ?>
                
                <div class="auto-update-option">
                    <label>
                        <input type="checkbox" id="github-deployer-auto-update" name="enable_auto_update" value="1">
                        <?php esc_html_e('Enable auto-updates for this repository', 'github-deployer'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('When checked, this plugin/theme will be automatically updated when changes are detected in the GitHub repository.', 'github-deployer'); ?></p>
                </div>
            </form>
        </div>
        
        <?php 
        // Get auto-updater instance to display tracked repositories
        $auto_updater = new GitHub_Deployer\Auto_Updater();
        $tracked_repos = $auto_updater->get_tracked_repos();
        
        if (!empty($tracked_repos)): 
        ?>
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
        </div>
        <?php endif; ?>
    </div>
</div> 