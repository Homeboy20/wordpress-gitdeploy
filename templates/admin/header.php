<?php
/**
 * Admin header template
 */
// Add custom header actions
do_action('github_deployer_before_header');
?>
<div class="wrap github-deployer-wrap">
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
    
    <?php if (isset($_GET['disconnected'])): ?>
        <div class="notice notice-success">
            <p>
                <?php 
                $repo = isset($_GET['repo']) ? esc_html($_GET['repo']) : '';
                printf(
                    esc_html__('Repository "%s" has been disconnected.', 'github-deployer'),
                    $repo
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['status_refreshed'])): ?>
        <div class="notice notice-success">
            <p><?php esc_html_e('Repository connection status has been refreshed.', 'github-deployer'); ?></p>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'deploy'), admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'deploy' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Deploy', 'github-deployer'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'repositories'), admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'repositories' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Repositories', 'github-deployer'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'connect'), admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'connect' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Connect Repository', 'github-deployer'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(array('page' => 'github-deployer', 'tab' => 'settings'), admin_url('admin.php'))); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Settings', 'github-deployer'); ?>
        </a>
    </h2>
    
    <?php do_action('github_deployer_after_tabs'); ?>
</div> 