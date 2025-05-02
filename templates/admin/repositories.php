<?php
/**
 * Template for the Repositories tab
 */

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get repository manager instance safely via the plugin singleton
    $plugin = GitHub_Deployer\Plugin::get_instance();
    $repo_manager = $plugin->get_repository_manager();
    $deployed_repos = $repo_manager->get_deployed_repositories();
    
    // Debug output
    echo "<!-- Repository manager loaded successfully -->";
} catch (Exception $e) {
    echo "<!-- Error: " . esc_html($e->getMessage()) . " -->";
    echo "<!-- File: " . esc_html($e->getFile()) . " Line: " . esc_html($e->getLine()) . " -->";
}
?>

<div class="github-deployer-repositories">
    <?php 
    // Display success/error messages
    if (isset($_GET['status_refreshed']) && $_GET['status_refreshed'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>';
        esc_html_e('Repository connection status has been refreshed.', 'github-deployer');
        echo '</p></div>';
    }
    
    if (isset($_GET['auto_update']) && isset($_GET['repo'])) {
        $repo = sanitize_text_field($_GET['repo']);
        echo '<div class="notice notice-success is-dismissible"><p>';
        
        if ($_GET['auto_update'] === 'enabled') {
            printf(
                esc_html__('Auto-updates have been enabled for repository "%s".', 'github-deployer'),
                '<strong>' . esc_html($repo) . '</strong>'
            );
        } else if ($_GET['auto_update'] === 'disabled') {
            printf(
                esc_html__('Auto-updates have been disabled for repository "%s".', 'github-deployer'),
                '<strong>' . esc_html($repo) . '</strong>'
            );
        }
        
        echo '</p></div>';
    }
    ?>
    
    <div class="github-deployer-sections">
        <div class="github-deployer-browse-section">
            <h2><?php esc_html_e('Browse GitHub Repositories', 'github-deployer'); ?></h2>
            
            <div class="github-deployer-search-form">
                <div class="search-form-row">
                    <div class="search-form-type">
                        <label for="github-deployer-repo-type"><?php esc_html_e('Repository Type', 'github-deployer'); ?></label>
                        <select id="github-deployer-repo-type">
                            <option value="user"><?php esc_html_e('My Repositories', 'github-deployer'); ?></option>
                            <option value="public"><?php esc_html_e('User Repositories', 'github-deployer'); ?></option>
                            <option value="org"><?php esc_html_e('Organization Repositories', 'github-deployer'); ?></option>
                            <option value="search"><?php esc_html_e('Search', 'github-deployer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="search-form-query" style="display: none;">
                        <label for="github-deployer-repo-query" id="github-deployer-query-label"><?php esc_html_e('Search Query', 'github-deployer'); ?></label>
                        <input type="text" id="github-deployer-repo-query" placeholder="<?php esc_attr_e('Enter search query...', 'github-deployer'); ?>">
                    </div>
                    
                    <div class="search-form-actions">
                        <button type="button" id="github-deployer-fetch-repos" class="button button-primary"><?php esc_html_e('Fetch Repositories', 'github-deployer'); ?></button>
                    </div>
                </div>
            </div>
            
            <div id="github-deployer-repo-list" style="display: none;">
                <div class="loading"><?php esc_html_e('Loading repositories...', 'github-deployer'); ?></div>
                <div class="content"></div>
                <div class="pagination"></div>
            </div>
        </div>
        
        <div class="github-deployer-deployed-section">
            <div class="section-header">
                <h2><?php esc_html_e('Deployed Repositories', 'github-deployer'); ?></h2>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="refresh-status-form">
                    <input type="hidden" name="action" value="github_deployer_refresh_status">
                    <?php wp_nonce_field('github_deployer_refresh_status'); ?>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Refresh All Status', 'github-deployer'); ?></button>
                </form>
            </div>
            
            <?php if (empty($deployed_repos)): ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No repositories have been deployed yet. Use the form above to browse and deploy repositories from GitHub.', 'github-deployer'); ?></p>
                </div>
            <?php else: ?>
                <table class="widefat github-deployer-deployed-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Repository', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Type', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Branch/Tag', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Status', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Auto-Update', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'github-deployer'); ?></th>
                            <th><?php esc_html_e('Actions', 'github-deployer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deployed_repos as $repo): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url("https://github.com/{$repo['owner']}/{$repo['repo']}"); ?>" target="_blank">
                                        <?php echo esc_html("{$repo['owner']}/{$repo['repo']}"); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </td>
                                <td><?php echo esc_html($repo['type']); ?></td>
                                <td><?php echo esc_html($repo['ref']); ?></td>
                                <td class="connection-status">
                                    <?php if (isset($repo['connected']) && $repo['connected']): ?>
                                        <span class="status connected"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connected', 'github-deployer'); ?></span>
                                    <?php else: ?>
                                        <span class="status disconnected"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Disconnected', 'github-deployer'); ?></span>
                                        <?php if (isset($repo['error'])): ?>
                                            <span class="error-message"><?php echo esc_html($repo['error']); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="auto-update-status">
                                    <?php if (isset($repo['is_tracked']) && $repo['is_tracked']): ?>
                                        <span class="status enabled"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Enabled', 'github-deployer'); ?></span>
                                    <?php else: ?>
                                        <span class="status disabled"><span class="dashicons dashicons-no"></span> <?php esc_html_e('Disabled', 'github-deployer'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($repo['last_updated'])) {
                                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $repo['last_updated']));
                                    } else {
                                        esc_html_e('Never', 'github-deployer');
                                    }
                                    ?>
                                </td>
                                <td class="actions">
                                    <div class="action-buttons">
                                        <?php if (isset($repo['connected']) && $repo['connected']): ?>
                                            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="disconnect-form">
                                                <input type="hidden" name="action" value="github_deployer_disconnect_repo">
                                                <input type="hidden" name="owner" value="<?php echo esc_attr($repo['owner']); ?>">
                                                <input type="hidden" name="repo" value="<?php echo esc_attr($repo['repo']); ?>">
                                                <?php wp_nonce_field('github_deployer_disconnect_repo'); ?>
                                                <button type="submit" class="button button-secondary"><?php esc_html_e('Disconnect', 'github-deployer'); ?></button>
                                            </form>
                                        <?php else: ?>
                                            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="connect-form">
                                                <input type="hidden" name="action" value="github_deployer_connect_repo">
                                                <input type="hidden" name="owner" value="<?php echo esc_attr($repo['owner']); ?>">
                                                <input type="hidden" name="repo" value="<?php echo esc_attr($repo['repo']); ?>">
                                                <?php wp_nonce_field('github_deployer_connect_repo'); ?>
                                                <button type="submit" class="button button-primary"><?php esc_html_e('Connect', 'github-deployer'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($repo['is_tracked']) && $repo['is_tracked']): ?>
                                            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="disable-tracking-form">
                                                <input type="hidden" name="action" value="github_deployer_disable_auto_update">
                                                <input type="hidden" name="owner" value="<?php echo esc_attr($repo['owner']); ?>">
                                                <input type="hidden" name="repo" value="<?php echo esc_attr($repo['repo']); ?>">
                                                <?php wp_nonce_field('github_deployer_disable_auto_update'); ?>
                                                <button type="submit" class="button button-secondary"><?php esc_html_e('Disable Auto-Update', 'github-deployer'); ?></button>
                                            </form>
                                        <?php else: ?>
                                            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="enable-tracking-form">
                                                <input type="hidden" name="action" value="github_deployer_enable_auto_update">
                                                <input type="hidden" name="owner" value="<?php echo esc_attr($repo['owner']); ?>">
                                                <input type="hidden" name="repo" value="<?php echo esc_attr($repo['repo']); ?>">
                                                <input type="hidden" name="ref" value="<?php echo esc_attr($repo['ref']); ?>">
                                                <input type="hidden" name="type" value="<?php echo esc_attr($repo['type']); ?>">
                                                <?php wp_nonce_field('github_deployer_enable_auto_update'); ?>
                                                <button type="submit" class="button button-secondary"><?php esc_html_e('Enable Auto-Update', 'github-deployer'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo esc_url(add_query_arg(array(
                                            'page' => 'github-deployer',
                                            'owner' => $repo['owner'],
                                            'repo' => $repo['repo'],
                                            'ref' => $repo['ref'],
                                            'type' => $repo['type'],
                                            'update' => '1'
                                        ), admin_url('admin.php'))); ?>" class="button button-secondary"><?php esc_html_e('Update', 'github-deployer'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/template" id="github-deployer-repo-template">
    <div class="github-deployer-repo-item">
        <div class="repo-info">
            <h3><a href="{repoUrl}" target="_blank">{fullName}</a></h3>
            <p class="description">{description}</p>
            <p class="meta">
                <span class="stars"><span class="dashicons dashicons-star-filled"></span> {stars}</span>
                <span class="forks"><span class="dashicons dashicons-networking"></span> {forks}</span>
                <span class="updated"><span class="dashicons dashicons-calendar-alt"></span> {updated}</span>
            </p>
        </div>
        <div class="repo-actions">
            <a href="{deployUrl}" class="button button-primary">Deploy</a>
        </div>
    </div>
</script> 